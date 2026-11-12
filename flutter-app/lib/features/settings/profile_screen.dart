import 'package:flutter/material.dart' hide Text;
import 'package:image_picker/image_picker.dart';

import '../../core/theme.dart';
import '../../ui/food_image.dart';
import '../../ui/widgets.dart';
import '../../ui/tr_text.dart';

/// The signed-in person's own profile: name, contact, language, photo and password.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _key = GlobalKey<DataViewState<Map<String, dynamic>>>();

  Future<void> _edit(Map<String, dynamic> p) async {
    final app = AppScope.read(context);
    final name = TextEditingController(text: '${p['name'] ?? ''}');
    final email = TextEditingController(text: '${p['email'] ?? ''}');
    final username = TextEditingController(text: '${p['username'] ?? ''}');
    final mobile = TextEditingController(text: '${p['mobile'] ?? ''}');
    var locale = '${p['locale'] ?? 'en'}';
    final go = await showForm<bool>(
      context,
      title: 'Edit profile',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          field(name, 'Name'),
          field(email, 'Email', type: TextInputType.emailAddress),
          field(username, 'Username'),
          field(mobile, 'Mobile', type: TextInputType.phone),
          SegmentedButton<String>(
            segments: const [ButtonSegment(value: 'en', label: Text('English')), ButtonSegment(value: 'bn', label: Text('Bangla'))],
            selected: {locale},
            onSelectionChanged: (s) => set(() => locale = s.first),
          ),
          const SizedBox(height: 12),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
        ]),
      ),
    );
    String? t(TextEditingController c) => c.text.trim().isEmpty ? null : c.text.trim();
    final body = {'name': name.text.trim(), 'email': t(email), 'username': t(username), 'mobile': t(mobile), 'locale': locale};
    for (final c in [name, email, username, mobile]) {
      c.dispose();
    }
    if (go != true || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      await app.api.post('/profile', body: body);
      await app.setLocale(locale);
    });
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  Future<void> _photo() async {
    final app = AppScope.read(context);
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, maxWidth: 800, imageQuality: 85);
    if (picked == null || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      await app.api.upload('/profile', fields: {'name': app.userName, 'locale': app.locale}, fileField: 'avatar', filePaths: [picked.path]);
    });
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  Future<void> _password() async {
    final app = AppScope.read(context);
    final current = TextEditingController();
    final next = TextEditingController();
    final confirmText = TextEditingController();
    final go = await showForm<bool>(
      context,
      title: 'Change password',
      builder: (ctx) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        field(current, 'Current password', obscure: true),
        field(next, 'New password (8 or more characters)', obscure: true),
        field(confirmText, 'Repeat the new password', obscure: true),
        FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Change password')),
      ]),
    );
    final body = {'current_password': current.text, 'password': next.text, 'password_confirmation': confirmText.text};
    for (final c in [current, next, confirmText]) {
      c.dispose();
    }
    if (go != true || !mounted) {
      return;
    }
    final ok = await guarded(context, () async => app.api.put('/profile/password', body: body));
    if (ok && mounted) {
      snack(context, 'Password changed.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('My profile')),
      body: DataView<Map<String, dynamic>>(
        key: _key,
        path: '/profile',
        transform: (raw) => Map<String, dynamic>.from(raw as Map),
        builder: (context, p, raw, reload) => ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(16), children: [
          Center(
            child: GestureDetector(
              onTap: _photo,
              child: CircleAvatar(
                radius: 44,
                backgroundColor: context.pageBg,
                backgroundImage: p['avatar_url'] == null ? null : NetworkImage(foodImageUrl(app, {'cover_url': p['avatar_url']}) ?? ''),
                child: p['avatar_url'] == null ? Icon(Icons.person, size: 40, color: context.brand) : null,
              ),
            ),
          ),
          const SizedBox(height: 6),
          Center(child: Text('Tap the photo to change it', style: TextStyle(color: context.muted, fontSize: 12))),
          const SizedBox(height: 12),
          Card(
            child: Column(children: [
              ListTile(dense: true, leading: const Icon(Icons.badge_outlined), title: Text('${p['name']}'), subtitle: Text((p['roles'] as List).map((e) => titleCase('$e')).join(', '))),
              if (p['username'] != null) ListTile(dense: true, leading: const Icon(Icons.alternate_email), title: Text('${p['username']}')),
              if (p['email'] != null) ListTile(dense: true, leading: const Icon(Icons.email_outlined), title: Text('${p['email']}')),
              if (p['mobile'] != null) ListTile(dense: true, leading: const Icon(Icons.phone), title: Text('${p['mobile']}')),
              if (p['branch'] != null) ListTile(dense: true, leading: const Icon(Icons.storefront), title: Text('${p['branch']}')),
              ListTile(dense: true, leading: const Icon(Icons.language), title: Text(p['locale'] == 'bn' ? 'Bangla' : 'English')),
            ]),
          ),
          const SizedBox(height: 12),
          FilledButton.icon(onPressed: () => _edit(p), icon: const Icon(Icons.edit_outlined), label: const Text('Edit profile')),
          const SizedBox(height: 8),
          OutlinedButton.icon(onPressed: _password, icon: const Icon(Icons.lock_outline), label: const Text('Change password')),
        ]),
      ),
    );
  }
}
