import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/theme.dart';
import '../../ui/widgets.dart';

/// Floor plan: tables with their live state, and for managers add, edit,
/// remove and the table QR link (the same link the web prints on cards).
class TablesScreen extends StatefulWidget {
  const TablesScreen({super.key});

  @override
  State<TablesScreen> createState() => _TablesScreenState();
}

class _TablesScreenState extends State<TablesScreen> {
  final _key = GlobalKey<DataViewState<Map<String, dynamic>>>();

  Future<void> _edit({Map<String, dynamic>? table}) async {
    final app = AppScope.read(context);
    final name = TextEditingController(text: '${table?['name'] ?? ''}');
    final area = TextEditingController(text: '${table?['area'] ?? ''}');
    final seats = TextEditingController(text: '${table?['seats'] ?? 4}');
    var active = table?['is_active'] != false;
    final go = await showForm<bool>(
      context,
      title: table == null ? 'New table' : 'Edit table',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          field(name, 'Table name'),
          field(area, 'Area (optional)'),
          field(seats, 'Seats', type: TextInputType.number),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('In use'),
            value: active,
            onChanged: (v) => set(() => active = v),
          ),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
        ]),
      ),
    );
    final body = {
      'name': name.text.trim(),
      'area': area.text.trim(),
      'seats': int.tryParse(seats.text.trim()) ?? 0,
      'is_active': active,
    };
    for (final c in [name, area, seats]) {
      c.dispose();
    }
    if (go != true || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      if (table == null) {
        await app.api.post('/branches/${app.branchId}/tables', body: body);
      } else {
        await app.api.put('/branches/${app.branchId}/tables/${table['id']}', body: body);
      }
    });
    if (ok) {
      await _key.currentState?.reload();
      await app.loadBootstrap();
    }
  }

  Future<void> _remove(Map<String, dynamic> table) async {
    final app = AppScope.read(context);
    if (!await confirm(context, 'Remove ${table['name']}?',
        body: 'A table that already has orders is switched off instead of deleted.', action: 'Remove')) {
      return;
    }
    if (!mounted) {
      return;
    }
    final ok = await guarded(context, () async => app.api.delete('/branches/${app.branchId}/tables/${table['id']}'));
    if (ok) {
      await _key.currentState?.reload();
      await app.loadBootstrap();
    }
  }

  Future<void> _qr(Map<String, dynamic> table, bool canManage) async {
    final app = AppScope.read(context);
    final link = '${table['qr_url']}';
    final action = await showForm<String>(
      context,
      title: 'Table ${table['name']} QR link',
      builder: (ctx) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        SelectableText(link),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: () => Navigator.pop(ctx, 'copy'),
          icon: const Icon(Icons.copy, size: 18),
          label: const Text('Copy link'),
        ),
        if (canManage)
          TextButton(
            onPressed: () => Navigator.pop(ctx, 'new'),
            child: const Text('Make a new link (the printed QR stops working)'),
          ),
      ]),
    );
    if (!mounted) {
      return;
    }
    if (action == 'copy') {
      await Clipboard.setData(ClipboardData(text: link));
      if (mounted) {
        snack(context, 'Link copied.');
      }
    } else if (action == 'new') {
      final ok = await guarded(context, () async => app.api.post('/branches/${app.branchId}/tables/${table['id']}/qr'));
      if (ok) {
        await _key.currentState?.reload();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Tables')),
      floatingActionButton: app.can('tables.manage')
          ? FloatingActionButton.extended(
              onPressed: () => _edit(),
              icon: const Icon(Icons.add),
              label: const Text('Add table'),
            )
          : null,
      body: DataView<Map<String, dynamic>>(
        key: _key,
        path: '/branches/${app.branchId}/tables',
        transform: (raw) => Map<String, dynamic>.from(raw as Map),
        builder: (context, data, raw, reload) {
          final tables = asRows(data['tables']);
          final canManage = data['can_manage'] == true;
          if (tables.isEmpty) {
            return ListView(children: const [SizedBox(height: 120), EmptyState('No tables for this branch.')]);
          }
          return GridView.count(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 96),
            crossAxisCount: 2,
            childAspectRatio: 1.25,
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            children: [
              for (final t in tables)
                Opacity(
                  opacity: t['is_active'] == false ? 0.5 : 1,
                  child: Card(
                    child: InkWell(
                      borderRadius: BorderRadius.circular(12),
                      onTap: () => _qr(t, canManage),
                      child: Padding(
                        padding: const EdgeInsets.all(10),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Row(children: [
                            Icon(Icons.table_restaurant, color: context.brand, size: 20),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Text('${t['name']}',
                                  maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
                            ),
                            if (canManage)
                              SizedBox(
                                width: 28,
                                height: 28,
                                child: PopupMenuButton<String>(
                                  padding: EdgeInsets.zero,
                                  onSelected: (v) => v == 'edit' ? _edit(table: t) : _remove(t),
                                  itemBuilder: (_) => const [
                                    PopupMenuItem(value: 'edit', child: Text('Edit')),
                                    PopupMenuItem(value: 'remove', child: Text('Remove')),
                                  ],
                                ),
                              ),
                          ]),
                          Text([if (t['area'] != null) '${t['area']}', '${t['seats']} seats'].join('  -  '),
                              style: TextStyle(fontSize: 11, color: context.muted)),
                          const Spacer(),
                          Row(children: [
                            StatusChip(t['is_active'] == false ? 'off' : '${t['state']}'),
                            const Spacer(),
                            Icon(Icons.qr_code_2, size: 18, color: context.muted),
                          ]),
                        ]),
                      ),
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
