import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart' hide Text;
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/api_client.dart';
import '../../core/app_state.dart';
import '../../core/realtime.dart';
import '../../core/sounds.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';
import '../../ui/tr_text.dart';

/// Lives while someone is signed in: listens for live order updates, chimes for website and platform
/// orders (live over websockets when the server has them, a 15 second poll otherwise) and locks the
/// screen behind a device PIN after the idle time set in Settings, like the web POS lock.
class SessionHost extends StatefulWidget {
  const SessionHost({super.key, required this.child});

  final Widget child;

  @override
  State<SessionHost> createState() => _SessionHostState();
}

class _SessionHostState extends State<SessionHost> with WidgetsBindingObserver {
  RealtimeClient? _live;
  StreamSubscription<LiveEvent>? _sub;
  Timer? _poll;
  Timer? _idle;
  int? _lastIncoming;
  bool _lockEnabled = false;
  int _lockMinutes = 5;
  bool _locked = false;
  DateTime _lastTouch = DateTime.now();
  final _seen = <Object>{};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => _start());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _sub?.cancel();
    _live?.stop();
    _poll?.cancel();
    _idle?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _checkIdle();
    }
  }

  Future<void> _start() async {
    final app = AppScope.read(context);
    if (!app.isLoggedIn) {
      return;
    }
    _live = RealtimeClient(app.api);
    _sub = _live!.events.listen(_onLive);
    unawaited(_live!.start());
    _poll = Timer.periodic(const Duration(seconds: 15), (_) => _pollIncoming());
    unawaited(_pollIncoming());
    unawaited(_loadLock());
    _idle = Timer.periodic(const Duration(seconds: 20), (_) => _checkIdle());
  }

  void _onLive(LiveEvent e) {
    final app = AppScope.read(context);
    app.liveTick.value++;
    if (e.event == 'OrderCreated') {
      final id = e.data['order_id'];
      if (id != null && _seen.add(id)) {
        SoundFx.play('kds_new');
        rootMessengerKey.currentState?.showSnackBar(SnackBar(content: Text('New order ${e.data['invoice_no'] ?? ''}')));
      }
    }
  }

  /// Website, QR and delivery app orders that arrived since the last look. Works without websockets.
  Future<void> _pollIncoming() async {
    final app = AppScope.read(context);
    if (!app.isLoggedIn || !app.can('orders.create')) {
      return;
    }
    try {
      final res = await app.api.get('/branches/${app.branchId}/orders/incoming', query: {'after': _lastIncoming ?? 0}) as Map;
      final first = _lastIncoming == null;
      _lastIncoming = (res['last_id'] as num?)?.toInt() ?? _lastIncoming ?? 0;
      if (first) {
        return;
      }
      for (final o in asRows(res['orders'])) {
        if (_seen.add(o['id'])) {
          SoundFx.play(o['channel'] == 'qr' ? 'order_created' : 'kds_new');
          final source = o['aggregator'] ?? (o['channel'] == 'qr' ? 'Table QR${o['table'] == null ? '' : ' ${o['table']}'}' : titleCase('${o['channel']}'));
          rootMessengerKey.currentState?.showSnackBar(SnackBar(content: Text('New order from $source: ${o['invoice_no']}')));
          app.liveTick.value++;
        }
      }
    } on ApiException {
      // Offline: try again at the next tick.
    }
  }

  Future<void> _loadLock() async {
    final app = AppScope.read(context);
    try {
      final ref = await app.api.get('/branches/${app.branchId}/reference') as Map;
      final lock = ref['pos_lock'];
      if (lock is Map && mounted) {
        setState(() {
          _lockEnabled = lock['enabled'] == true;
          _lockMinutes = (lock['minutes'] as num?)?.toInt() ?? 5;
        });
      }
    } on ApiException {
      // No access to the lookup lists: no lock.
    }
  }

  void _touch() => _lastTouch = DateTime.now();

  void _checkIdle() {
    if (_lockEnabled && !_locked && DateTime.now().difference(_lastTouch) >= Duration(minutes: _lockMinutes)) {
      setState(() => _locked = true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: (_) => _touch(),
      child: Stack(children: [
        widget.child,
        if (_locked)
          Positioned.fill(
            child: _LockScreen(onUnlocked: () {
              _touch();
              setState(() => _locked = false);
            }),
          ),
      ]),
    );
  }
}

/// Asks for the device PIN. The first time it asks to choose one. A forgotten PIN means signing in again.
class _LockScreen extends StatefulWidget {
  const _LockScreen({required this.onUnlocked});

  final VoidCallback onUnlocked;

  @override
  State<_LockScreen> createState() => _LockScreenState();
}

class _LockScreenState extends State<_LockScreen> {
  static const _key = 'device_pin';
  final _pin = TextEditingController();
  String? _saved;
  String? _first;
  String? _error;
  bool _loaded = false;

  @override
  void initState() {
    super.initState();
    SharedPreferences.getInstance().then((p) {
      if (mounted) {
        setState(() {
          _saved = p.getString(_key);
          _loaded = true;
        });
      }
    });
  }

  @override
  void dispose() {
    _pin.dispose();
    super.dispose();
  }

  /// A light salted hash so the PIN is not stored in the clear. It is a screen lock, not strong security.
  static String _hash(String pin) {
    var h = 5381;
    for (final b in utf8.encode('dt-restora-lock:$pin')) {
      h = ((h << 5) + h + b) & 0x7fffffff;
    }
    return '$h';
  }

  Future<void> _submit() async {
    final entered = _pin.text.trim();
    if (entered.length < 4) {
      setState(() => _error = 'Use at least 4 digits.');
      return;
    }
    final prefs = await SharedPreferences.getInstance();
    if (_saved != null) {
      if (_hash(entered) == _saved) {
        widget.onUnlocked();
      } else {
        setState(() => _error = 'Wrong PIN.');
        _pin.clear();
      }
      return;
    }
    if (_first == null) {
      setState(() {
        _first = entered;
        _error = null;
        _pin.clear();
      });
    } else if (_first == entered) {
      await prefs.setString(_key, _hash(entered));
      widget.onUnlocked();
    } else {
      setState(() {
        _first = null;
        _error = 'The two PINs did not match. Start again.';
        _pin.clear();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final title = _saved != null ? 'Enter your PIN' : (_first == null ? 'Choose a PIN for this device' : 'Repeat the PIN');
    return Material(
      color: context.pageBg,
      child: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 320),
            child: !_loaded
                ? const CircularProgressIndicator()
                : Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(mainAxisSize: MainAxisSize.min, children: [
                      const BrandLogo(size: 56),
                      const SizedBox(height: 16),
                      Text('${app.appName} is locked', style: Theme.of(context).textTheme.titleLarge),
                      const SizedBox(height: 4),
                      Text(app.userName, style: TextStyle(color: context.muted)),
                      const SizedBox(height: 20),
                      Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _pin,
                        autofocus: true,
                        obscureText: true,
                        textAlign: TextAlign.center,
                        keyboardType: TextInputType.number,
                        maxLength: 8,
                        onSubmitted: (_) => _submit(),
                        decoration: InputDecoration(errorText: _error, counterText: ''),
                      ),
                      const SizedBox(height: 12),
                      FilledButton(onPressed: _submit, child: Text(_saved != null ? 'Unlock' : 'Continue')),
                      TextButton(onPressed: () => app.logout(), child: const Text('Sign out instead')),
                    ]),
                  ),
          ),
        ),
      ),
    );
  }
}
