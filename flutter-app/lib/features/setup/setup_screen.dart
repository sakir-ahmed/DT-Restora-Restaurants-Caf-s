import 'package:flutter/material.dart' hide Text;

import '../../core/config.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';
import '../../ui/tr_text.dart';

/// One-time configuration shown on a fresh install: the app name (used on
/// the splash screen and around the app) and the API server. Saved on the
/// device and never shown again until app storage is cleared.
class SetupScreen extends StatefulWidget {
  const SetupScreen({super.key});

  @override
  State<SetupScreen> createState() => _SetupScreenState();
}

class _SetupScreenState extends State<SetupScreen> {
  final _name = TextEditingController();
  final _url = TextEditingController();
  bool _ready = false;

  /// Null until a choice is made on the first open: the demo server or the customer's own (production) server.
  bool? _demo;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_ready) {
      _ready = true;
    }
  }

  /// Demo: the ready-made name and server. Production: empty fields to fill in.
  void _choose(bool demo) {
    setState(() {
      _demo = demo;
      _name.text = demo ? 'DT Restora' : '';
      _url.text = demo ? AppConfig.apiBaseUrl : '';
    });
  }

  @override
  void dispose() {
    _name.dispose();
    _url.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final app = AppScope.read(context);
    final name = _name.text.trim();
    final url = _url.text.trim();
    if (_demo == null) {
      snack(context, 'Choose Demo or Production first.', error: true);
      return;
    }
    if (name.isEmpty) {
      snack(context, 'Give the app a name, for example your restaurant\'s name.', error: true);
      return;
    }
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      snack(context, 'The server address must start with http:// or https://', error: true);
      return;
    }
    await app.completeSetup(name: name, baseUrl: url, demo: _demo == true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            const SizedBox(height: 16),
            const Center(child: BrandLogo(size: 72)),
            const SizedBox(height: 22),
            Text('Welcome',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(color: context.fg, fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            Text('One-time setup: choose Demo to try the app, or Production to use your own server.',
                textAlign: TextAlign.center, style: TextStyle(color: context.muted)),
            const SizedBox(height: 20),
            Row(children: [
              Expanded(
                child: _demo == true
                    ? FilledButton.icon(onPressed: () => _choose(true), icon: const Icon(Icons.science_outlined), label: const Text('Demo'))
                    : OutlinedButton.icon(onPressed: () => _choose(true), icon: const Icon(Icons.science_outlined), label: const Text('Demo')),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _demo == false
                    ? FilledButton.icon(onPressed: () => _choose(false), icon: const Icon(Icons.storefront), label: const Text('Production'))
                    : OutlinedButton.icon(onPressed: () => _choose(false), icon: const Icon(Icons.storefront), label: const Text('Production')),
              ),
            ]),
            if (_demo == null) ...[
              const SizedBox(height: 28),
              Center(child: Text('Pick one to continue.', style: TextStyle(color: context.muted))),
            ],
            if (_demo != null) ...[
            const SizedBox(height: 20),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  field(_name, 'App name', hint: 'e.g. Arman\'s Kitchen'),
                  field(_url, 'Server address (API base URL)',
                      hint: 'https://your-server.com/api/v1', type: TextInputType.url),
                ]),
              ),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: _save,
              icon: const Icon(Icons.arrow_forward),
              label: const Text('Save and continue'),
            ),
            const SizedBox(height: 10),
            Text(
              'The app name appears on the splash screen. You can change the server later from the login screen.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 11, color: context.muted),
            ),
            ],
          ],
        ),
      ),
    );
  }
}
