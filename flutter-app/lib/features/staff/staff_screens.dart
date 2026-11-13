import 'package:flutter/material.dart' hide Text;
import 'package:image_picker/image_picker.dart';

import '../../core/theme.dart';
import '../../ui/food_image.dart';
import '../../ui/widgets.dart';
import '../../ui/tr_text.dart';
import '../../core/l10n.dart';

/// Employees (pay, device id, photo) and system users (sign-in accounts with roles), like the web Staff page.
class StaffScreen extends StatefulWidget {
  const StaffScreen({super.key});

  @override
  State<StaffScreen> createState() => _StaffScreenState();
}

class _StaffScreenState extends State<StaffScreen> {
  final _key = GlobalKey<DataViewState<Map<String, dynamic>>>();

  Future<void> _reload() async => _key.currentState?.reload();

  Future<void> _editEmployee(Map<String, dynamic> data, {Map<String, dynamic>? employee}) async {
    final app = AppScope.read(context);
    final name = TextEditingController(text: '${employee?['name'] ?? ''}');
    final nameBn = TextEditingController(text: '${employee?['name_bn'] ?? ''}');
    final designation = TextEditingController(text: '${employee?['designation'] ?? ''}');
    final mobile = TextEditingController(text: '${employee?['mobile'] ?? ''}');
    final device = TextEditingController(text: '${employee?['device_user_id'] ?? ''}');
    var basis = '${employee?['pay_basis'] ?? 'monthly'}';
    final pay = TextEditingController(
        text: employee == null ? '' : (basis == 'monthly' ? '${toNum(employee['monthly_salary'])}' : '${toNum(employee['pay_rate'])}'));
    var active = employee?['is_active'] != false;
    final go = await showForm<bool>(
      context,
      title: employee == null ? 'Add employee' : 'Edit ${employee['name']}',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          field(name, 'Full name'),
          field(nameBn, 'Name in Bangla (optional)'),
          field(designation, 'Designation'),
          field(mobile, 'Mobile', type: TextInputType.phone),
          field(device, 'Fingerprint device user id (optional)'),
          DropdownButtonFormField<String>(
            initialValue: basis,
            decoration: InputDecoration(labelText: tr('Pay basis')),
            items: const [
              DropdownMenuItem(value: 'monthly', child: Text('Monthly salary')),
              DropdownMenuItem(value: 'daily', child: Text('Daily rate')),
              DropdownMenuItem(value: 'hourly', child: Text('Hourly rate')),
            ],
            onChanged: (v) => set(() => basis = v!),
          ),
          const SizedBox(height: 12),
          field(pay, basis == 'monthly' ? 'Monthly salary' : 'Rate', type: const TextInputType.numberWithOptions(decimal: true)),
          if (employee != null) SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Active'), value: active, onChanged: (v) => set(() => active = v)),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
        ]),
      ),
    );
    final amount = double.tryParse(pay.text.trim());
    final body = {
      'name': name.text.trim(),
      'name_bn': nameBn.text.trim().isEmpty ? null : nameBn.text.trim(),
      'designation': designation.text.trim(),
      'mobile': mobile.text.trim().isEmpty ? null : mobile.text.trim(),
      'device_user_id': device.text.trim().isEmpty ? null : device.text.trim(),
      'pay_basis': basis,
      if (basis == 'monthly') 'monthly_salary': amount else 'pay_rate': amount,
      'is_active': active,
    };
    for (final c in [name, nameBn, designation, mobile, device, pay]) {
      c.dispose();
    }
    if (go != true || !mounted) {
      return;
    }
    if ('${body['name']}'.isEmpty || '${body['designation']}'.isEmpty || amount == null) {
      snack(context, 'Name, designation and pay are required.', error: true);
      return;
    }
    final ok = await guarded(context, () async {
      if (employee == null) {
        await app.api.post('/branches/${app.branchId}/employees', body: body);
      } else {
        await app.api.put('/branches/${app.branchId}/employees/${employee['id']}', body: body);
      }
    });
    if (ok) {
      await _reload();
    }
  }

  Future<void> _photo(Map<String, dynamic> employee) async {
    final app = AppScope.read(context);
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, maxWidth: 1200, imageQuality: 85);
    if (picked == null || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      await app.api.upload('/branches/${app.branchId}/employees/${employee['id']}/photo', fileField: 'photo', filePaths: [picked.path]);
    });
    if (ok) {
      await _reload();
    }
  }

  Future<void> _deactivate(String path, String name) async {
    final app = AppScope.read(context);
    if (!await confirm(context, 'Switch off $name?', body: 'Their history stays. They no longer count as active.', action: 'Switch off')) {
      return;
    }
    if (!mounted) {
      return;
    }
    final ok = await guarded(context, () async => app.api.delete(path));
    if (ok) {
      await _reload();
    }
  }

  Future<void> _editUser(Map<String, dynamic> data, {Map<String, dynamic>? account}) async {
    final app = AppScope.read(context);
    final name = TextEditingController(text: '${account?['name'] ?? ''}');
    final username = TextEditingController(text: '${account?['username'] ?? ''}');
    final email = TextEditingController(text: '${account?['email'] ?? ''}');
    final mobile = TextEditingController(text: '${account?['mobile'] ?? ''}');
    final password = TextEditingController();
    final roles = <String>{...((account?['roles'] as List?)?.map((e) => '$e') ?? const <String>[])};
    var active = account?['is_active'] != false;
    final allRoles = (data['roles'] as List).map((e) => '$e').toList();
    final go = await showForm<bool>(
      context,
      title: account == null ? 'Add user' : 'Edit ${account['name']}',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          field(name, 'Name'),
          field(username, 'Username'),
          field(email, 'Email (optional)', type: TextInputType.emailAddress),
          field(mobile, 'Mobile (optional)', type: TextInputType.phone),
          field(password, account == null ? 'Password (8 or more characters)' : 'New password (leave blank to keep)', obscure: true),
          const Text('Roles', style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          Wrap(spacing: 6, runSpacing: 6, children: [
            for (final r in allRoles)
              FilterChip(label: Text(titleCase(r)), selected: roles.contains(r), onSelected: (on) => set(() => on ? roles.add(r) : roles.remove(r))),
          ]),
          SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Active'), value: active, onChanged: (v) => set(() => active = v)),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
        ]),
      ),
    );
    String? t(TextEditingController c) => c.text.trim().isEmpty ? null : c.text.trim();
    final body = {
      'name': name.text.trim(),
      'username': t(username),
      'email': t(email),
      'mobile': t(mobile),
      if (password.text.isNotEmpty) 'password': password.text,
      'roles': roles.toList(),
      'is_active': active,
    };
    for (final c in [name, username, email, mobile, password]) {
      c.dispose();
    }
    if (go != true || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      if (account == null) {
        await app.api.post('/branches/${app.branchId}/users', body: body);
      } else {
        await app.api.put('/branches/${app.branchId}/users/${account['id']}', body: body);
      }
    });
    if (ok) {
      await _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(title: const Text('Staff'), bottom: const TabBar(tabs: [Tab(text: 'Employees'), Tab(text: 'Users and roles')])),
        body: DataView<Map<String, dynamic>>(
          key: _key,
          path: '/branches/${app.branchId}/staff',
          transform: (raw) => Map<String, dynamic>.from(raw as Map),
          builder: (context, data, raw, reload) {
            final canManage = data['can_manage'] == true;
            final canUsers = data['can_manage_users'] == true;
            return TabBarView(children: [
              Stack(children: [
                ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.fromLTRB(12, 12, 12, 96), children: [
                  if (asRows(data['employees']).isEmpty) const Padding(padding: EdgeInsets.only(top: 60), child: EmptyState('No employees yet.')),
                  for (final e in asRows(data['employees']))
                    Card(
                      child: ListTile(
                        leading: SizedBox(
                          width: 40,
                          height: 40,
                          child: ClipOval(
                            // A photo that is missing or broken falls back to the placeholder icon.
                            child: FoodImage(url: e['photo_url'] == null ? null : foodImageUrl(app, {'cover_url': e['photo_url']}), iconSize: 20, icon: Icons.person),
                          ),
                        ),
                        title: Text(localized(app, e)),
                        subtitle: Text('${e['designation']}${e['mobile'] == null ? '' : '  -  ${e['mobile']}'}'),
                        trailing: canManage
                            ? PopupMenuButton<String>(
                                onSelected: (v) {
                                  if (v == 'edit') {
                                    _editEmployee(data, employee: e);
                                  } else if (v == 'photo') {
                                    _photo(e);
                                  } else {
                                    _deactivate('/branches/${app.branchId}/employees/${e['id']}', '${e['name']}');
                                  }
                                },
                                itemBuilder: (_) => const [
                                  PopupMenuItem(value: 'edit', child: Text('Edit')),
                                  PopupMenuItem(value: 'photo', child: Text('Change photo')),
                                  PopupMenuItem(value: 'off', child: Text('Switch off')),
                                ],
                              )
                            : null,
                        isThreeLine: false,
                      ),
                    ),
                ]),
                if (canManage)
                  Positioned(
                    right: 16,
                    bottom: 16,
                    child: FloatingActionButton.extended(heroTag: 'emp', onPressed: () => _editEmployee(data), icon: const Icon(Icons.person_add), label: const Text('Employee')),
                  ),
              ]),
              Stack(children: [
                ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.fromLTRB(12, 12, 12, 96), children: [
                  for (final u in asRows(data['users']))
                    Card(
                      child: ListTile(
                        leading: CircleAvatar(backgroundColor: context.pageBg, child: Icon(Icons.admin_panel_settings_outlined, color: context.brand)),
                        title: Text('${u['name']}${u['is_active'] == false ? '  (off)' : ''}'),
                        subtitle: Text('${u['username'] ?? u['email'] ?? u['mobile'] ?? ''}  -  ${(u['roles'] as List).map((e) => titleCase('$e')).join(', ')}'),
                        trailing: canUsers
                            ? PopupMenuButton<String>(
                                onSelected: (v) => v == 'edit' ? _editUser(data, account: u) : _deactivate('/branches/${app.branchId}/users/${u['id']}', '${u['name']}'),
                                itemBuilder: (_) => const [
                                  PopupMenuItem(value: 'edit', child: Text('Edit')),
                                  PopupMenuItem(value: 'off', child: Text('Switch off')),
                                ],
                              )
                            : null,
                      ),
                    ),
                ]),
                if (canUsers)
                  Positioned(
                    right: 16,
                    bottom: 16,
                    child: FloatingActionButton.extended(heroTag: 'usr', onPressed: () => _editUser(data), icon: const Icon(Icons.person_add_alt), label: const Text('User')),
                  ),
              ]),
            ]);
          },
        ),
      ),
    );
  }
}
