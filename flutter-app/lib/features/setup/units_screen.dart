import 'package:flutter/material.dart' hide Text;

import '../../core/theme.dart';
import '../../core/units.dart';
import '../../ui/widgets.dart';
import '../../ui/tr_text.dart';

/// Units of measure and conversions, like the web Settings tab: the standard
/// units are fixed, custom units (box, large box) are described as
/// "1 box = 12 pcs" and are locked once something uses them.
class UnitsScreen extends StatefulWidget {
  const UnitsScreen({super.key});

  @override
  State<UnitsScreen> createState() => _UnitsScreenState();
}

class _UnitsScreenState extends State<UnitsScreen> {
  final _key = GlobalKey<DataViewState<List<Map<String, dynamic>>>>();

  static const _typeNames = {'weight': 'Weight', 'volume': 'Volume', 'count': 'Count'};

  Future<void> _edit(List<Map<String, dynamic>> rows, {Map<String, dynamic>? unit}) async {
    final app = AppScope.read(context);
    final all = rows.map(UnitInfo.fromJson).toList();
    final locked = unit != null && unit['in_use'] == true;
    final name = TextEditingController(text: '${unit?['name'] ?? ''}');
    final nameBn = TextEditingController(text: '${unit?['name_bn'] ?? ''}');
    final symbol = TextEditingController(text: '${unit?['symbol'] ?? ''}');
    var type = '${unit?['type'] ?? 'count'}';
    final existing = unit == null ? null : UnitInfo.fromJson(unit);
    final qty = TextEditingController(
        text: existing == null ? '' : formatQuantity(inReference(existing, all)).replaceAll(',', ''));

    final go = await showForm<bool>(
      context,
      title: unit == null ? 'New unit' : 'Edit unit',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) {
          final ref = referenceUnit(all, type);
          return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            if (locked)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text('This unit is in use, so its size is locked. You can still rename it.',
                    style: TextStyle(color: ctx.muted, fontSize: 12)),
              ),
            field(name, 'Name'),
            field(nameBn, 'Name in Bangla (optional)'),
            field(symbol, 'Symbol'),
            if (!locked) ...[
              if (unit == null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Wrap(spacing: 6, children: [
                    for (final t in _typeNames.entries)
                      ChoiceChip(
                        label: Text(t.value),
                        selected: type == t.key,
                        onSelected: (_) => set(() => type = t.key),
                      ),
                  ]),
                ),
              field(qty, ref == null ? 'Size' : '1 ${symbol.text.isEmpty ? 'unit' : symbol.text} = how many ${ref.symbol}?',
                  type: const TextInputType.numberWithOptions(decimal: true)),
            ],
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
          ]);
        },
      ),
    );
    final body = <String, dynamic>{
      'name': name.text.trim(),
      'name_bn': nameBn.text.trim(),
      'symbol': symbol.text.trim(),
      if (!locked) ...{
        'type': type,
        'qty': double.tryParse(qty.text.trim()) ?? 0,
        'reference_unit_id': referenceUnit(all, type)?.id,
      },
    };
    for (final c in [name, nameBn, symbol, qty]) {
      c.dispose();
    }
    if (go != true || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      if (unit == null) {
        await app.api.post('/units', body: body);
      } else {
        await app.api.put('/units/${unit['id']}', body: body);
      }
    });
    if (ok) {
      await _key.currentState?.reload();
      await app.loadBootstrap();
    }
  }

  Future<void> _delete(Map<String, dynamic> unit) async {
    final app = AppScope.read(context);
    if (!await confirm(context, 'Delete ${unit['name']}?', action: 'Delete')) {
      return;
    }
    if (!mounted) {
      return;
    }
    final ok = await guarded(context, () async => app.api.delete('/units/${unit['id']}'));
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Units and conversions')),
      body: DataView<List<Map<String, dynamic>>>(
        key: _key,
        path: '/units',
        transform: asRows,
        builder: (context, rows, raw, reload) {
          final all = rows.map(UnitInfo.fromJson).toList();
          return ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 96),
            children: [
              Text('Standard units are fixed. Add your own, such as a box or a large box, and say how big it is.',
                  style: TextStyle(color: context.muted, fontSize: 12)),
              for (final type in _typeNames.keys) ...[
                SectionTitle(_typeNames[type]!),
                for (final u in rows.where((r) => r['type'] == type))
                  Card(
                    child: ListTile(
                      dense: true,
                      title: Text('${u['name']} (${u['symbol']})'),
                      subtitle: Text(relationText(UnitInfo.fromJson(u), all) ?? 'Reference unit'),
                      trailing: u['is_system'] == true
                          ? Text('Fixed', style: TextStyle(color: context.muted, fontSize: 11))
                          : Row(mainAxisSize: MainAxisSize.min, children: [
                              IconButton(
                                visualDensity: VisualDensity.compact,
                                icon: const Icon(Icons.edit_outlined, size: 20),
                                onPressed: () => _edit(rows, unit: u),
                              ),
                              if (u['in_use'] != true)
                                IconButton(
                                  visualDensity: VisualDensity.compact,
                                  icon: const Icon(Icons.delete_outline, size: 20, color: Brand.danger),
                                  onPressed: () => _delete(u),
                                ),
                            ]),
                    ),
                  ),
              ],
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: () => _edit(rows),
                icon: const Icon(Icons.add),
                label: const Text('Add a unit'),
              ),
            ],
          );
        },
      ),
    );
  }
}
