import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import 'api_client.dart';

/// One message from the server: the event name (without the leading dot), the channel and its data.
class LiveEvent {
  const LiveEvent(this.event, this.channel, this.data);

  final String event;
  final String channel;
  final Map<String, dynamic> data;
}

/// Live updates over Laravel Reverb, which speaks the Pusher protocol: a WebSocket to
/// `/app/{key}`, private channels authorised by the server with the app's token. It reconnects with a
/// growing pause when the connection drops. Screens also poll, so a server without websockets still works.
class RealtimeClient {
  RealtimeClient(this._api);

  final ApiClient _api;
  final _events = StreamController<LiveEvent>.broadcast();
  WebSocket? _socket;
  Timer? _retry;
  int _attempt = 0;
  bool _stopped = true;
  Map<String, dynamic>? _config;

  Stream<LiveEvent> get events => _events.stream;
  bool get connected => _socket != null && _socket!.readyState == WebSocket.open;

  /// Reads the connection details from the server and connects. Does nothing when websockets are off.
  Future<void> start() async {
    _stopped = false;
    try {
      final cfg = await _api.get('/realtime');
      if (cfg is! Map || cfg['enabled'] != true) {
        return;
      }
      _config = Map<String, dynamic>.from(cfg);
      await _connect();
    } on ApiException catch (e) {
      debugPrint('Realtime config not available: ${e.message}');
    }
  }

  void stop() {
    _stopped = true;
    _retry?.cancel();
    _socket?.close();
    _socket = null;
  }

  Future<void> _connect() async {
    final cfg = _config;
    if (cfg == null || _stopped) {
      return;
    }
    final scheme = cfg['scheme'] == 'http' ? 'ws' : 'wss';
    final uri = Uri.parse('$scheme://${cfg['host']}:${cfg['port']}/app/${cfg['key']}?protocol=7&client=dt-restora&version=1.0');
    try {
      final socket = await WebSocket.connect(uri.toString()).timeout(const Duration(seconds: 10));
      _socket = socket;
      _attempt = 0;
      socket.listen(_onMessage, onDone: _lost, onError: (Object _) => _lost(), cancelOnError: true);
    } on Exception catch (e) {
      debugPrint('Realtime connect failed: $e');
      _lost();
    }
  }

  void _lost() {
    _socket = null;
    if (_stopped) {
      return;
    }
    _attempt = min(_attempt + 1, 6);
    _retry?.cancel();
    _retry = Timer(Duration(seconds: 2 << _attempt), _connect);
  }

  void _send(Map<String, Object?> frame) => _socket?.add(jsonEncode(frame));

  Future<void> _onMessage(dynamic raw) async {
    Map<String, dynamic> frame;
    try {
      frame = Map<String, dynamic>.from(jsonDecode('$raw') as Map);
    } on FormatException {
      return;
    }
    final event = '${frame['event']}';
    final data = frame['data'];
    final payload = data is String && data.isNotEmpty ? _decode(data) : (data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{});

    if (event == 'pusher:connection_established') {
      await _subscribeAll('${payload['socket_id']}');
    } else if (event == 'pusher:ping') {
      _send({'event': 'pusher:pong', 'data': {}});
    } else if (!event.startsWith('pusher')) {
      _events.add(LiveEvent(event.startsWith('.') ? event.substring(1) : event, '${frame['channel']}', payload));
    }
  }

  Map<String, dynamic> _decode(String text) {
    try {
      final v = jsonDecode(text);
      return v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
    } on FormatException {
      return <String, dynamic>{};
    }
  }

  /// Joins every private channel this person may listen to; the server signs each one.
  Future<void> _subscribeAll(String socketId) async {
    final cfg = _config!;
    for (final channel in (cfg['channels'] as List).map((e) => '$e')) {
      try {
        final res = await http.post(
          Uri.parse('${cfg['auth_endpoint']}'),
          headers: {'Accept': 'application/json', 'Authorization': 'Bearer ${_api.tokenForRealtime}'},
          body: {'socket_id': socketId, 'channel_name': channel},
        ).timeout(const Duration(seconds: 10));
        if (res.statusCode == 200) {
          final auth = _decode(res.body);
          _send({'event': 'pusher:subscribe', 'data': {'channel': channel, 'auth': auth['auth']}});
        }
      } on Exception catch (e) {
        debugPrint('Channel $channel not joined: $e');
      }
    }
  }
}
