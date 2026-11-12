import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';

import 'api_client.dart';

/// Sends the rider's position to the server while a delivery is on the way, so the
/// customer's tracking page and the dispatcher can follow it. Foreground only,
/// every 15 seconds (the server accepts one point per 5 seconds).
class RiderLocationSender {
  RiderLocationSender(this._api);

  final ApiClient _api;
  Timer? _timer;

  bool get running => _timer != null;

  /// Asks for permission if needed and starts sending. Returns false when location is off or refused.
  Future<bool> start() async {
    if (running) {
      return true;
    }
    if (!await Geolocator.isLocationServiceEnabled()) {
      return false;
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
      return false;
    }
    await _send();
    _timer = Timer.periodic(const Duration(seconds: 15), (_) => _send());
    return true;
  }

  void stop() {
    _timer?.cancel();
    _timer = null;
  }

  Future<void> _send() async {
    try {
      final p = await Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high, timeLimit: const Duration(seconds: 10));
      await _api.post('/rider/location', body: {
        'lat': p.latitude,
        'lng': p.longitude,
        'accuracy': p.accuracy,
        'recorded_at': p.timestamp.toUtc().toIso8601String(),
      });
    } on ApiException catch (e) {
      debugPrint('Rider location not sent: ${e.message}');
    } on Exception catch (e) {
      debugPrint('Rider location failed: $e');
    }
  }
}
