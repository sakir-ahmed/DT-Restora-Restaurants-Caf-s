import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/app_state.dart';
import '../../core/theme.dart';
import '../../core/units.dart';
import '../../ui/food_image.dart';
import '../../ui/widgets.dart';
import 'menu_manage_screen.dart';

const wastageReasons = ['spoiled', 'dropped', 'complimentary', 'over_production', 'kitchen_mistake', 'other'];

/// Lookup lists (ingredients, units, suppliers, riders, tables) from
/// `/branches/{id}/reference`, cached for offline use.
///
/// Older servers do not have this endpoint; then an empty map comes back so
/// screens can fall back or explain what is missing.
Future<Map<String, dynamic>> loadReference(AppState app) async {
  try {
    final res = await app.cachedGet('/branches/${app.branchId}/reference');
    return Map<String, dynamic>.from(res.data as Map);
  } on ApiException catch (e) {
    if (e.status == 404) {
      return {};
    }
    rethrow;
  }
}

/// Asks why a record is being deleted (the same reasons the web offers) and returns the body
/// for the delete call, or null when cancelled.
Future<Map<String, dynamic>?> askDeleteReason(BuildContext context, String title, {String action = 'Delete'}) async {
  var reason = 'entry_mistake';
  final note = TextEditingController();
  final go = await showForm<bool>(
    context,
    title: title,
    builder: (ctx) => StatefulBuilder(
      builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('The record is kept as deleted, and stock and the books are put back.', style: TextStyle(color: ctx.muted, fontSize: 12)),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: reason,
          isExpanded: true,
          decoration: const InputDecoration(labelText: 'Reason'),
          items: [for (final r in const ['entry_mistake', 'duplicate_entry', 'wrong_amount', 'other']) DropdownMenuItem(value: r, child: Text(titleCase(r)))],
          onChanged: (v) => set(() => reason = v!),
        ),
        const SizedBox(height: 12),
        field(note, 'Note (optional)'),
        FilledButton(
          style: FilledButton.styleFrom(backgroundColor: Brand.danger),
          onPressed: () => Navigator.pop(ctx, true),
          child: Text(action),
        ),
      ]),
    ),
  );
  final text = note.text.trim();
  note.dispose();
  if (go != true) {
    return null;
  }
  return {'reason_code': reason, if (text.isNotEmpty) 'note': text};
}

const _needsUpdate = 'This screen needs the latest server update (the lookup lists are not available on this server yet).';

class _RefLoader extends StatelessWidget {
  const _RefLoader({required this.builder, this.require = const []});

  final Widget Function(BuildContext context, Map<String, dynamic> ref) builder;
  final List<String> require;

  @override
  Widget build(BuildContext context) {
    final app = AppScope.read(context);
    return FutureBuilder<Map<String, dynamic>>(
      future: loadReference(app),
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return EmptyState('${snap.error}');
        }
        if (require.any((k) => snap.data![k] == null)) {
          return const EmptyState(_needsUpdate);
        }
        return builder(context, snap.data!);
      },
    );
  }
}

// ---------------------------------------------------------------- stock

class StockScreen extends StatelessWidget {
  const StockScreen({super.key});

  /// Create or edit an ingredient (name, unit, reorder level, active).
  Future<void> _editIngredient(BuildContext context, Map<String, dynamic> ref,
      {Map<String, dynamic>? ingredient}) async {
    final app = AppScope.read(context);
    final name = TextEditingController(text: ingredient?['name'] as String? ?? '');
    final nameBn = TextEditingController(text: ingredient?['name_bn'] as String? ?? '');
    final reorder = TextEditingController(
        text: ingredient == null ? '' : toNum(ingredient['reorder_level']).toStringAsFixed(2));
    var unitId = (ingredient?['unit_id'] as num?)?.toInt() ?? (asRows(ref['units']).firstOrNull?['id'] as num?)?.toInt();
    var active = ingredient?['is_active'] as bool? ?? true;
    final units = asRows(ref['units']);
    var categoryId = (ingredient?['ingredient_category_id'] as num?)?.toInt();
    var categories = <Map<String, dynamic>>[];
    try {
      categories = asRows(await app.api.get('/ingredient-categories'));
    } on ApiException {
      categories = [];
    }
    if (!context.mounted) {
      return;
    }

    final go = await showForm<bool>(
      context,
      title: ingredient == null ? 'New ingredient' : 'Edit ingredient',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          field(name, 'Name (English)'),
          field(nameBn, 'Bangla name (optional)'),
          DropdownButtonFormField<int>(
            initialValue: unitId,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Unit'),
            items: [
              for (final u in units)
                DropdownMenuItem(value: (u['id'] as num).toInt(), child: Text('${u['name']} (${u['symbol'] ?? ''})')),
            ],
            onChanged: (v) => set(() => unitId = v),
          ),
          if (categories.isNotEmpty) ...[
            const SizedBox(height: 12),
            DropdownButtonFormField<int?>(
              initialValue: categoryId,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Category'),
              items: [
                const DropdownMenuItem<int?>(value: null, child: Text('No category')),
                for (final c in categories) DropdownMenuItem<int?>(value: (c['id'] as num).toInt(), child: Text('${c['name']}')),
              ],
              onChanged: (v) => set(() => categoryId = v),
            ),
          ],
          const SizedBox(height: 12),
          field(reorder, 'Low-stock alert level', type: const TextInputType.numberWithOptions(decimal: true)),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Active'),
            value: active,
            onChanged: (v) => set(() => active = v),
          ),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
        ]),
      ),
    );
    final n = name.text.trim();
    final level = double.tryParse(reorder.text.trim());
    final bn = nameBn.text.trim();
    name.dispose();
    nameBn.dispose();
    reorder.dispose();
    if (go != true || n.isEmpty || unitId == null || !context.mounted) {
      return;
    }
    final editing = ingredient != null;
    final body = {
      'name': n,
      ...bn.isNotEmpty ? {'name_bn': bn} : <String, dynamic>{},
      'unit_id': unitId,
      'ingredient_category_id': categoryId,
      'reorder_level': ?level,
      'is_active': active,
    };
    final ok = await guarded(context, () async {
      if (editing) {
        await app.api.put('/ingredients/${ingredient['id']}', body: body);
      } else {
        await app.api.post('/ingredients', body: body);
      }
    });
    if (ok && context.mounted) {
      snack(context, editing ? 'Ingredient updated.' : 'Ingredient created.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Stock'),
        actions: [
          if (app.can('inventory.view'))
            IconButton(
              tooltip: 'Ingredient categories',
              icon: const Icon(Icons.category_outlined),
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const IngredientCategoriesScreen())),
            ),
        ],
      ),
      floatingActionButton: app.can('inventory.manage')
          ? FloatingActionButton.extended(
              onPressed: () async {
                final ref = await loadReference(app);
                if (!context.mounted) {
                  return;
                }
                await _editIngredient(context, ref);
              },
              icon: const Icon(Icons.add),
              label: const Text('Ingredient'),
            )
          : null,
      body: _RefLoader(
        builder: (context, ref) {
          final ingredients = {for (final i in asRows(ref['ingredients'])) (i['id'] as num).toInt(): i};
          return DataView<List<Map<String, dynamic>>>(
            path: '/branches/${app.branchId}/inventory/stock',
            transform: asRows,
            builder: (context, rows, raw, reload) => ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(12),
              itemCount: rows.length,
              separatorBuilder: (_, _) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                final r = rows[i];
                final id = (r['ingredient_id'] as num).toInt();
                final meta = ingredients[id];
                // Stock is kept in the base unit (g, l, pcs); show and compare it in the ingredient's own unit.
                final shown = stockInUnit(toNum(r['stock_qty']), toNum(meta?['unit_factor']));
                final low = meta != null && shown <= toNum(meta['reorder_level']);
                return Card(
                  child: ListTile(
                    leading: Icon(low ? Icons.warning_amber : Icons.inventory_2,
                        color: low ? Brand.warning : context.brand),
                    title: Text('${r['name']}'),
                    subtitle: Text('Avg cost ${fmtMoney(r['avg_cost'])}${low ? '  -  Low stock' : ''}'),
                    trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                      Text('${formatQuantity(shown)} ${meta?['unit_symbol'] ?? ''}',
                          style: const TextStyle(fontWeight: FontWeight.w700)),
                      if (app.can('inventory.manage'))
                        PopupMenuButton<String>(
                          tooltip: 'Ingredient actions',
                          onSelected: (v) async {
                            if (v == 'edit') {
                              await _editIngredient(context, ref, ingredient: meta);
                            } else if (await confirm(context, 'Switch off ${r['name']}?', body: 'It stays in old records but is hidden from new purchases and recipes.', action: 'Switch off')) {
                              if (!context.mounted) {
                                return;
                              }
                              final ok = await guarded(context, () async => app.api.delete('/ingredients/$id'));
                              if (ok) {
                                await reload();
                              }
                            }
                          },
                          itemBuilder: (_) => const [
                            PopupMenuItem(value: 'edit', child: Text('Edit')),
                            PopupMenuItem(value: 'off', child: Text('Switch off')),
                          ],
                        ),
                    ]),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

/// Ingredient categories: add, rename, switch off and delete (blocked while ingredients use one).
class IngredientCategoriesScreen extends StatefulWidget {
  const IngredientCategoriesScreen({super.key});

  @override
  State<IngredientCategoriesScreen> createState() => _IngredientCategoriesScreenState();
}

class _IngredientCategoriesScreenState extends State<IngredientCategoriesScreen> {
  final _key = GlobalKey<DataViewState<List<Map<String, dynamic>>>>();

  Future<void> _edit({Map<String, dynamic>? category}) async {
    final app = AppScope.read(context);
    final name = TextEditingController(text: '${category?['name'] ?? ''}');
    final nameBn = TextEditingController(text: '${category?['name_bn'] ?? ''}');
    var active = category?['is_active'] != false;
    final go = await showForm<bool>(
      context,
      title: category == null ? 'New category' : 'Edit category',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          field(name, 'Name'),
          field(nameBn, 'Name in Bangla (optional)'),
          SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Active'), value: active, onChanged: (v) => set(() => active = v)),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
        ]),
      ),
    );
    final body = {'name': name.text.trim(), 'name_bn': nameBn.text.trim().isEmpty ? null : nameBn.text.trim(), 'is_active': active};
    name.dispose();
    nameBn.dispose();
    if (go != true || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      if (category == null) {
        await app.api.post('/ingredient-categories', body: body);
      } else {
        await app.api.put('/ingredient-categories/${category['id']}', body: body);
      }
    });
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  Future<void> _delete(Map<String, dynamic> c) async {
    final app = AppScope.read(context);
    if (!await confirm(context, 'Delete ${c['name']}?', action: 'Delete')) {
      return;
    }
    if (!mounted) {
      return;
    }
    final ok = await guarded(context, () async => app.api.delete('/ingredient-categories/${c['id']}'));
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final canManage = app.can('inventory.manage');
    return Scaffold(
      appBar: AppBar(title: const Text('Ingredient categories')),
      floatingActionButton: canManage ? FloatingActionButton.extended(onPressed: () => _edit(), icon: const Icon(Icons.add), label: const Text('Category')) : null,
      body: DataView<List<Map<String, dynamic>>>(
        key: _key,
        path: '/ingredient-categories',
        transform: asRows,
        builder: (context, rows, raw, reload) => rows.isEmpty
            ? ListView(children: const [SizedBox(height: 120), EmptyState('No categories yet.')])
            : ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.fromLTRB(12, 12, 12, 96), children: [
                for (final c in rows)
                  Card(
                    child: ListTile(
                      title: Text('${c['name']}${c['is_active'] == false ? '  (off)' : ''}'),
                      subtitle: Text('${c['ingredients_count']} ingredients'),
                      trailing: canManage
                          ? Row(mainAxisSize: MainAxisSize.min, children: [
                              IconButton(icon: const Icon(Icons.edit_outlined, size: 20), onPressed: () => _edit(category: c)),
                              IconButton(icon: const Icon(Icons.delete_outline, size: 20, color: Brand.danger), onPressed: () => _delete(c)),
                            ])
                          : null,
                    ),
                  ),
              ]),
      ),
    );
  }
}

// ---------------------------------------------------------------- wastage

/// Unit dropdown for a quantity field: every unit of the ingredient's kind with
/// its size, plus "Adds N to stock" when the unit differs from the ingredient's own.
Widget _unitPicker({
  required Map<String, dynamic> ref,
  required Map<String, dynamic>? ingredient,
  required int? unitId,
  required double qty,
  required ValueChanged<int?> onChanged,
}) {
  final units = [for (final u in asRows(ref['units'])) UnitInfo.fromJson(u)];
  final own = ingredient == null ? null : ingredientUnit(ingredient, units);
  if (own == null) {
    return const SizedBox.shrink();
  }
  final choices = unitsForType(units, own.type);
  final chosen = choices.where((u) => u.id == unitId).firstOrNull ?? own;
  final hint = stockHint(own, chosen, qty);
  return Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      DropdownButtonFormField<int>(
        initialValue: chosen.id,
        isExpanded: true,
        decoration: const InputDecoration(labelText: 'Unit'),
        items: [for (final u in choices) DropdownMenuItem(value: u.id, child: Text(unitLabel(u, units), overflow: TextOverflow.ellipsis))],
        onChanged: choices.length < 2 ? null : onChanged,
      ),
      if (hint != null) Padding(padding: const EdgeInsets.only(top: 4), child: Text(hint, style: const TextStyle(fontSize: 12))),
    ]),
  );
}

class WastageScreen extends StatelessWidget {
  const WastageScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Wastage'),
          bottom: const TabBar(
            tabs: [Tab(text: 'Log wastage'), Tab(text: 'History')],
          ),
        ),
        body: const TabBarView(children: [_LogWastageView(), _WastageHistoryView()]),
      ),
    );
  }
}

/// The wastage table, newest first.
class _WastageHistoryView extends StatelessWidget {
  const _WastageHistoryView();

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return DataView<List<Map<String, dynamic>>>(
      path: '/branches/${app.branchId}/wastage',
      transform: asRows,
      builder: (context, rows, raw, reload) => rows.isEmpty
          ? ListView(children: const [SizedBox(height: 160), EmptyState('No wastage recorded yet.')])
          : ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(12),
              itemCount: rows.length,
              separatorBuilder: (_, _) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                final w = rows[i];
                final ingredient = w['ingredient'] as Map?;
                final unit = w['unit'] as Map?;
                final user = w['user'] as Map?;
                return Card(
                  child: ListTile(
                    leading: const Icon(Icons.delete_sweep_outlined, color: Brand.danger),
                    title: Text('${ingredient?['name'] ?? 'Ingredient #${w['ingredient_id']}'}'
                        '  -  ${toNum(w['qty']).toStringAsFixed(2)} ${unit?['symbol'] ?? ''}'),
                    subtitle: Text(
                      [
                        titleCase('${w['reason_code']}'),
                        if (user?['name'] != null) '${user!['name']}',
                        if (w['created_at'] != null) fmtDate(w['created_at']),
                      ].join('  -  '),
                      style: TextStyle(color: context.muted, fontSize: 12),
                    ),
                    trailing: AppScope.of(context).can('wastage.log')
                        ? IconButton(
                            tooltip: 'Delete this entry',
                            icon: const Icon(Icons.delete_outline, color: Brand.danger),
                            onPressed: () async {
                              final app = AppScope.read(context);
                              final body = await askDeleteReason(context, 'Delete this wastage entry');
                              if (body == null || !context.mounted) {
                                return;
                              }
                              final ok = await guarded(context, () async => app.api.post('/branches/${app.branchId}/wastage/${w['id']}/delete', body: body));
                              if (ok) {
                                await reload();
                              }
                            },
                          )
                        : null,
                  ),
                );
              },
            ),
    );
  }
}

class _LogWastageView extends StatefulWidget {
  const _LogWastageView();

  @override
  State<_LogWastageView> createState() => _LogWastageViewState();
}

class _LogWastageViewState extends State<_LogWastageView> {
  int? _ingredient;
  int? _unitId;
  String _reason = wastageReasons.first;
  final _qty = TextEditingController();
  final _note = TextEditingController();
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _qty.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _qty.dispose();
    _note.dispose();
    super.dispose();
  }

  Future<void> _submit(Map<String, dynamic> ref) async {
    final app = AppScope.read(context);
    final ing = asRows(ref['ingredients']).where((i) => i['id'] == _ingredient).firstOrNull;
    final qty = double.tryParse(_qty.text);
    if (ing == null || qty == null || qty <= 0) {
      snack(context, 'Choose an ingredient and enter a quantity.', error: true);
      return;
    }
    setState(() => _busy = true);
    final sent = await app.queue(
      'wastage',
      {
        'branch_id': app.branchId,
        'ingredient_id': ing['id'],
        'unit_id': _unitId ?? ing['unit_id'],
        'qty': qty,
        'reason_code': _reason,
        if (_note.text.isNotEmpty) 'note': _note.text,
      },
      label: 'Wastage: ${ing['name']} $qty',
    );
    if (!mounted) {
      return;
    }
    setState(() => _busy = false);
    _qty.clear();
    _note.clear();
    snack(context, sent ? 'Wastage logged.' : 'Saved on this device. Will sync when online.');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Log wastage')),
      body: _RefLoader(
        require: const ['ingredients'],
        builder: (context, ref) {
          final ingredients = asRows(ref['ingredients']);
          return ListView(padding: const EdgeInsets.all(16), children: [
            DropdownButtonFormField<int>(
              initialValue: _ingredient,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Ingredient'),
              items: [
                for (final i in ingredients)
                  DropdownMenuItem(value: (i['id'] as num).toInt(), child: Text('${i['name']} (${i['unit_symbol'] ?? ''})')),
              ],
              onChanged: (v) => setState(() {
                _ingredient = v;
                _unitId = null;
              }),
            ),
            const SizedBox(height: 12),
            field(_qty, 'Quantity', type: const TextInputType.numberWithOptions(decimal: true)),
            _unitPicker(
              ref: ref,
              ingredient: ingredients.where((i) => i['id'] == _ingredient).firstOrNull,
              unitId: _unitId,
              qty: double.tryParse(_qty.text) ?? 0,
              onChanged: (v) => setState(() => _unitId = v),
            ),
            DropdownButtonFormField<String>(
              initialValue: _reason,
              decoration: const InputDecoration(labelText: 'Reason'),
              items: [for (final r in wastageReasons) DropdownMenuItem(value: r, child: Text(titleCase(r)))],
              onChanged: (v) => setState(() => _reason = v!),
            ),
            const SizedBox(height: 12),
            field(_note, 'Note (optional)'),
            FilledButton(onPressed: _busy ? null : () => _submit(ref), child: const Text('Log wastage')),
          ]);
        },
      ),
    );
  }
}

// ---------------------------------------------------------------- purchases

class PurchasesScreen extends StatelessWidget {
  const PurchasesScreen({super.key});

  /// Lines, then Edit (a received purchase is corrected safely), Receive (drafts) and Delete.
  Future<void> _open(BuildContext context, Map<String, dynamic> p, Future<void> Function() reload) async {
    final app = AppScope.read(context);
    final status = '${p['status']}';
    final action = await showForm<String>(
      context,
      title: '${p['invoice_no'] ?? 'Purchase #${p['id']}'}',
      builder: (ctx) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('${fmtDay(p['purchased_at'])}  -  ${titleCase(status)}  -  ${fmtMoney(p['total'])}', style: TextStyle(color: ctx.muted, fontSize: 12)),
        const SizedBox(height: 8),
        for (final i in asRows(p['items']))
          ListTile(
            dense: true,
            contentPadding: EdgeInsets.zero,
            title: Text('${i['ingredient'] is Map ? (i['ingredient'] as Map)['name'] : i['ingredient_id']}'),
            subtitle: Text('${formatQuantity(toNum(i['qty']))} ${i['unit'] is Map ? (i['unit'] as Map)['symbol'] : ''} at ${fmtMoney(i['unit_cost'])}'),
            trailing: Text(fmtMoney(i['line_total'])),
          ),
        if (status == 'draft')
          FilledButton.icon(onPressed: () => Navigator.pop(ctx, 'receive'), icon: const Icon(Icons.inventory_2), label: const Text('Receive into stock')),
        if (status != 'cancelled')
          OutlinedButton.icon(onPressed: () => Navigator.pop(ctx, 'edit'), icon: const Icon(Icons.edit_outlined), label: Text(status == 'received' ? 'Correct purchase' : 'Edit purchase')),
        if (status != 'cancelled')
          TextButton.icon(
            style: TextButton.styleFrom(foregroundColor: Brand.danger),
            onPressed: () => Navigator.pop(ctx, 'delete'),
            icon: const Icon(Icons.delete_outline),
            label: const Text('Delete purchase'),
          ),
      ]),
    );
    if (action == null || !context.mounted) {
      return;
    }
    if (action == 'receive') {
      final ok = await guarded(context, () async => app.api.post('/branches/${app.branchId}/purchases/${p['id']}/receive'));
      if (ok && context.mounted) {
        snack(context, 'Received into stock.');
        await reload();
      }
    } else if (action == 'edit') {
      await showForm<void>(context, title: 'Edit purchase', builder: (_) => _PurchaseForm(purchase: p));
      await reload();
    } else {
      final body = await askDeleteReason(context, 'Delete this purchase');
      if (body == null || !context.mounted) {
        return;
      }
      final ok = await guarded(context, () async => app.api.post('/branches/${app.branchId}/purchases/${p['id']}/delete', body: body));
      if (ok && context.mounted) {
        snack(context, 'Purchase deleted.');
        await reload();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return ListModule(
      title: 'Purchases',
      path: '/branches/${app.branchId}/purchases',
      itemBuilder: (context, p, reload) => Card(
        child: ListTile(
          onTap: () => _open(context, p, reload),
          title: Text('${p['invoice_no'] ?? 'Purchase #${p['id']}'}${p['supplier'] is Map ? '  -  ${(p['supplier'] as Map)['name']}' : ''}'),
          subtitle: Text('${fmtDay(p['purchased_at'])}  -  ${asRows(p['items']).length} lines'),
          trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text(fmtMoney(p['total']), style: const TextStyle(fontWeight: FontWeight.w700)),
            StatusChip('${p['status']}'),
          ]),
        ),
      ),
      fab: (reload) => FloatingActionButton.extended(
        onPressed: () async {
          await showForm<void>(context, title: 'New purchase', builder: (_) => const _PurchaseForm());
          await reload();
        },
        icon: const Icon(Icons.add),
        label: const Text('Purchase'),
      ),
    );
  }
}

class _PurchaseForm extends StatefulWidget {
  const _PurchaseForm({this.purchase});

  /// Null creates a purchase; a map edits (draft) or corrects (received) that purchase.
  final Map<String, dynamic>? purchase;

  @override
  State<_PurchaseForm> createState() => _PurchaseFormState();
}

class _PurchaseFormState extends State<_PurchaseForm> {
  Map<String, dynamic>? _ref;
  int? _supplier;
  final _invoice = TextEditingController();
  bool _receive = true;
  final List<_PurchaseLine> _lines = [];
  String? _error;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final existing = widget.purchase;
    if (existing != null) {
      _supplier = (existing['supplier_id'] as num?)?.toInt();
      _invoice.text = '${existing['invoice_no'] ?? ''}';
      for (final i in asRows(existing['items'])) {
        final line = _PurchaseLine((i['ingredient_id'] as num).toInt(), (i['unit_id'] as num?)?.toInt());
        line.qty.text = formatQuantity(toNum(i['qty'])).replaceAll(',', '');
        line.cost.text = '${toNum(i['unit_cost'])}';
        _lines.add(line);
      }
    }
    loadReference(AppScope.read(context)).then((r) {
      if (mounted) {
        setState(() => _ref = r);
      }
    }).catchError((Object e) {
      if (mounted) {
        setState(() => _error = '$e');
      }
    });
  }

  @override
  void dispose() {
    _invoice.dispose();
    for (final l in _lines) {
      l.qty.dispose();
      l.cost.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    final app = AppScope.read(context);
    final items = <Map<String, dynamic>>[];
    for (final l in _lines) {
      final ing = asRows(_ref!['ingredients']).firstWhere((i) => i['id'] == l.ingredient);
      final qty = double.tryParse(l.qty.text);
      final cost = double.tryParse(l.cost.text);
      if (qty != null && qty > 0 && cost != null) {
        // The unit chosen on the line (box, kg ...); the server converts it to the ingredient's stock unit.
        items.add({'ingredient_id': l.ingredient, 'unit_id': l.unitId ?? ing['unit_id'], 'qty': qty, 'unit_cost': cost});
      }
    }
    if (_supplier == null || items.isEmpty) {
      snack(context, 'Choose a supplier and add at least one line.', error: true);
      return;
    }
    setState(() => _busy = true);
    final editing = widget.purchase != null;
    final ok = await guarded(context, () async {
      final body = {
        'supplier_id': _supplier,
        if (_invoice.text.isNotEmpty) 'invoice_no': _invoice.text,
        'purchased_at': editing
            ? '${widget.purchase!['purchased_at']}'.substring(0, 10)
            : DateTime.now().toIso8601String().substring(0, 10),
        'items': items,
      };
      if (editing) {
        await app.api.put('/branches/${app.branchId}/purchases/${widget.purchase!['id']}', body: body);
      } else {
        await app.api.post('/branches/${app.branchId}/purchases', body: {...body, 'receive': _receive});
      }
    });
    if (!mounted) {
      return;
    }
    setState(() => _busy = false);
    if (ok) {
      Navigator.pop(context);
      snack(context, 'Purchase saved.');
    }
  }

  /// One purchase line: ingredient, quantity with a unit picker (every unit of
  /// the ingredient's kind, with its size), the cost per chosen unit, and what
  /// the line adds to stock when the unit differs from the ingredient's own.
  Widget _lineEditor(int i, List<Map<String, dynamic>> ingredients, List<UnitInfo> units) {
    final line = _lines[i];
    final ingredient = ingredients.firstWhere((x) => x['id'] == line.ingredient);
    final own = ingredientUnit(ingredient, units);
    final choices = own == null ? <UnitInfo>[] : unitsForType(units, own.type);
    final chosen = choices.where((u) => u.id == line.unitId).firstOrNull ?? own;
    final qty = double.tryParse(line.qty.text) ?? 0;
    final hint = stockHint(own, chosen, qty);
    final cost = double.tryParse(line.cost.text) ?? 0;

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text('${ingredient['name']}', style: const TextStyle(fontWeight: FontWeight.w600))),
          IconButton(
            visualDensity: VisualDensity.compact,
            icon: const Icon(Icons.close),
            onPressed: () => setState(() => _lines.removeAt(i)),
          ),
        ]),
        Row(children: [
          Expanded(
            flex: 2,
            child: TextField(
              controller: line.qty,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(labelText: 'Qty', isDense: true),
              onChanged: (_) => setState(() {}),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            flex: 3,
            child: DropdownButtonFormField<int>(
              isExpanded: true,
              initialValue: chosen?.id,
              decoration: const InputDecoration(labelText: 'Unit', isDense: true),
              items: [for (final u in choices) DropdownMenuItem(value: u.id, child: Text(unitLabel(u, units), overflow: TextOverflow.ellipsis))],
              onChanged: choices.length < 2 ? null : (v) => setState(() => line.unitId = v),
            ),
          ),
        ]),
        const SizedBox(height: 8),
        TextField(
          controller: line.cost,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(labelText: 'Cost per ${chosen?.symbol ?? 'unit'}', isDense: true),
          onChanged: (_) => setState(() {}),
        ),
        if (hint != null || (qty > 0 && cost > 0))
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              [if (hint != null) 'Adds $hint to stock', if (qty > 0 && cost > 0) 'Line total ${fmtMoney(qty * cost)}'].join('   '),
              style: TextStyle(fontSize: 12, color: context.muted),
            ),
          ),
      ]),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null) {
      return Text(_error!);
    }
    final ref = _ref;
    if (ref == null) {
      return const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator()));
    }
    if (ref['ingredients'] == null) {
      return const Padding(padding: EdgeInsets.symmetric(vertical: 24), child: EmptyState(_needsUpdate));
    }
    final ingredients = asRows(ref['ingredients']);
    final units = [for (final u in asRows(ref['units'])) UnitInfo.fromJson(u)];
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      DropdownButtonFormField<int>(
        initialValue: _supplier,
        decoration: const InputDecoration(labelText: 'Supplier'),
        items: [for (final s in asRows(ref['suppliers'])) DropdownMenuItem(value: (s['id'] as num).toInt(), child: Text('${s['name']}'))],
        onChanged: (v) => setState(() => _supplier = v),
      ),
      const SizedBox(height: 12),
      field(_invoice, 'Supplier invoice no. (optional)'),
      for (var i = 0; i < _lines.length; i++) _lineEditor(i, ingredients, units),
      DropdownButtonFormField<int>(
        // A new key after every pick clears the field, so the same ingredient can be added again.
        key: ValueKey(_lines.length),
        initialValue: null,
        decoration: const InputDecoration(labelText: 'Add ingredient'),
        items: [for (final i in ingredients) DropdownMenuItem(value: (i['id'] as num).toInt(), child: Text('${i['name']}'))],
        onChanged: (v) {
          if (v != null) {
            final ingredient = ingredients.firstWhere((x) => x['id'] == v);
            setState(() => _lines.add(_PurchaseLine(v, ingredientUnit(ingredient, units)?.id)));
          }
        },
      ),
      if (widget.purchase == null)
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Receive into stock now'),
          value: _receive,
          onChanged: (v) => setState(() => _receive = v),
        ),
      FilledButton(onPressed: _busy ? null : _save, child: Text(widget.purchase == null ? 'Save purchase' : 'Save changes')),
    ]);
  }
}

/// One line of the purchase form: the ingredient, the unit chosen for it, and
/// the quantity and cost typed so far.
class _PurchaseLine {
  _PurchaseLine(this.ingredient, this.unitId);

  final int ingredient;
  int? unitId;
  final TextEditingController qty = TextEditingController();
  final TextEditingController cost = TextEditingController();
}

// ---------------------------------------------------------------- stock count

/// Start a count, enter what is on the shelf (in each ingredient's own unit), then close it to
/// adjust stock. Past counts can be deleted with a reason.
class StockCountScreen extends StatefulWidget {
  const StockCountScreen({super.key});

  @override
  State<StockCountScreen> createState() => _StockCountScreenState();
}

class _StockCountScreenState extends State<StockCountScreen> {
  final _key = GlobalKey<DataViewState<Map<String, dynamic>>>();
  final Map<int, TextEditingController> _counts = {};
  bool _busy = false;

  @override
  void dispose() {
    for (final c in _counts.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _start() async {
    final app = AppScope.read(context);
    final ok = await guarded(context, () async => app.api.post('/branches/${app.branchId}/stock-counts'));
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  Future<bool> _save(Map<String, dynamic> open) async {
    final app = AppScope.read(context);
    final counted = <String, dynamic>{};
    for (final item in asRows(open['items'])) {
      final id = (item['id'] as num).toInt();
      final text = _counts[id]?.text.trim() ?? '';
      final qty = double.tryParse(text);
      if (qty != null) {
        // Stock is kept in base units (g, l, pcs); the count is typed in the ingredient's unit.
        counted['$id'] = qty * (toNum(item['unit_factor']) == 0 ? 1 : toNum(item['unit_factor']));
      }
    }
    if (counted.isEmpty) {
      snack(context, 'Enter at least one counted quantity.', error: true);
      return false;
    }
    return guarded(context, () async => app.api.put('/branches/${app.branchId}/stock-counts/${open['id']}/items', body: {'counted': counted}));
  }

  Future<void> _close(Map<String, dynamic> open) async {
    final app = AppScope.read(context);
    if (!await confirm(context, 'Close stock count?', body: 'Stock will be adjusted to the counted quantities. Items you did not count stay as they are.', action: 'Close count')) {
      return;
    }
    setState(() => _busy = true);
    var ok = await _save(open);
    if (ok && mounted) {
      ok = await guarded(context, () async => app.api.post('/branches/${app.branchId}/stock-counts/${open['id']}/close'));
    }
    if (!mounted) {
      return;
    }
    setState(() => _busy = false);
    if (ok) {
      for (final c in _counts.values) {
        c.clear();
      }
      snack(context, 'Stock count closed.');
      await _key.currentState?.reload();
    }
  }

  Future<void> _delete(Map<String, dynamic> count) async {
    final app = AppScope.read(context);
    final body = await askDeleteReason(context, 'Delete this stock count');
    if (body == null || !mounted) {
      return;
    }
    final ok = await guarded(context, () async => app.api.post('/branches/${app.branchId}/stock-counts/${count['id']}/delete', body: body));
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Stock count')),
      body: DataView<Map<String, dynamic>>(
        key: _key,
        path: '/branches/${app.branchId}/stock-counts',
        transform: (raw) => Map<String, dynamic>.from(raw as Map),
        builder: (context, data, raw, reload) {
          final open = data['open'] is Map ? Map<String, dynamic>.from(data['open'] as Map) : null;
          final canManage = data['can_manage'] == true;
          final history = asRows(data['counts']).where((c) => '${c['status']}' != 'open').toList();
          return ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.fromLTRB(12, 12, 12, 24), children: [
            if (open == null && canManage)
              FilledButton.icon(onPressed: _start, icon: const Icon(Icons.fact_check), label: const Text('Start a new count')),
            if (open != null) ...[
              const SectionTitle('Count in progress'),
              for (final item in asRows(open['items']))
                Builder(builder: (context) {
                  final id = (item['id'] as num).toInt();
                  final factor = toNum(item['unit_factor']) == 0 ? 1 : toNum(item['unit_factor']);
                  final c = _counts.putIfAbsent(id, () {
                    final controller = TextEditingController();
                    if (item['counted_qty'] != null) {
                      controller.text = formatQuantity(toNum(item['counted_qty']) / factor).replaceAll(',', '');
                    }
                    return controller;
                  });
                  return Card(
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(children: [
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text('${item['name']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                            Text('System: ${formatQuantity(toNum(item['system_qty']) / factor)} ${item['unit_symbol'] ?? ''}', style: TextStyle(color: context.muted, fontSize: 12)),
                          ]),
                        ),
                        SizedBox(
                          width: 120,
                          child: TextField(
                            controller: c,
                            enabled: canManage,
                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            decoration: InputDecoration(labelText: 'Counted ${item['unit_symbol'] ?? ''}', isDense: true),
                          ),
                        ),
                      ]),
                    ),
                  );
                }),
              if (canManage) ...[
                const SizedBox(height: 8),
                OutlinedButton(
                  onPressed: _busy
                      ? null
                      : () async {
                          final ok = await _save(open);
                          if (ok && context.mounted) {
                            snack(context, 'Counts saved.');
                          }
                        },
                  child: const Text('Save counts'),
                ),
                const SizedBox(height: 8),
                FilledButton(onPressed: _busy ? null : () => _close(open), child: const Text('Close count')),
              ],
            ],
            if (history.isNotEmpty) ...[
              const SectionTitle('Past counts'),
              for (final c in history)
                Card(
                  child: ListTile(
                    dense: true,
                    title: Text('Count #${c['id']}  -  ${c['items_count']} items'),
                    subtitle: Text(fmtDate(c['closed_at'] ?? c['created_at'])),
                    leading: StatusChip('${c['status']}' == 'closed' ? 'approved' : '${c['status']}'),
                    trailing: canManage ? IconButton(icon: const Icon(Icons.delete_outline, color: Brand.danger), onPressed: () => _delete(c)) : null,
                  ),
                ),
            ],
          ]);
        },
      ),
    );
  }
}

// ---------------------------------------------------------------- transfers

class TransfersScreen extends StatefulWidget {
  const TransfersScreen({super.key});

  @override
  State<TransfersScreen> createState() => _TransfersScreenState();
}

class _TransfersScreenState extends State<TransfersScreen> {
  int? _toBranch;
  int? _ingredient;
  int? _unitId;
  final _qty = TextEditingController();
  int _refresh = 0;
  bool _busy = false;
  String? _lastTransfer;

  @override
  void initState() {
    super.initState();
    _qty.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _qty.dispose();
    super.dispose();
  }

  Future<void> _create(Map<String, dynamic> ref) async {
    final app = AppScope.read(context);
    final ing = asRows(ref['ingredients']).where((i) => i['id'] == _ingredient).firstOrNull;
    final qty = double.tryParse(_qty.text);
    if (_toBranch == null || ing == null || qty == null || qty <= 0) {
      snack(context, 'Choose a destination branch, ingredient and quantity.', error: true);
      return;
    }
    setState(() => _busy = true);
    final ok = await guarded(context, () async {
      final res = await app.api.post('/stock-transfers', body: {
        'from_branch_id': app.branchId,
        'to_branch_id': _toBranch,
        'items': [
          {'ingredient_id': ing['id'], 'unit_id': _unitId ?? ing['unit_id'], 'qty': qty}
        ],
      }) as Map;
      _lastTransfer = 'Transfer #${res['id']} is ${res['status']}.';
    });
    if (mounted) {
      setState(() => _busy = false);
      if (ok) {
        _qty.clear();
        _refresh++;
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Stock transfers')),
      body: _RefLoader(
        require: const ['ingredients'],
        builder: (context, ref) => FutureBuilder<Cached<dynamic>>(
          future: app.cachedGet('/customer/branches'),
          builder: (context, snap) {
            final branches = asRows(snap.data?.data).where((b) => b['id'] != app.branchId).toList();
            return ListView(padding: const EdgeInsets.all(16), children: [
              const SectionTitle('Send stock to another branch'),
              DropdownButtonFormField<int>(
                initialValue: _toBranch,
                decoration: const InputDecoration(labelText: 'To branch'),
                items: [for (final b in branches) DropdownMenuItem(value: (b['id'] as num).toInt(), child: Text('${b['name']}'))],
                onChanged: (v) => setState(() => _toBranch = v),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<int>(
                initialValue: _ingredient,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'Ingredient'),
                items: [
                  for (final i in asRows(ref['ingredients']))
                    DropdownMenuItem(value: (i['id'] as num).toInt(), child: Text('${i['name']} (${i['unit_symbol'] ?? ''})')),
                ],
                onChanged: (v) => setState(() {
                  _ingredient = v;
                  _unitId = null;
                }),
              ),
              const SizedBox(height: 12),
              field(_qty, 'Quantity', type: const TextInputType.numberWithOptions(decimal: true)),
              _unitPicker(
                ref: ref,
                ingredient: asRows(ref['ingredients']).where((i) => i['id'] == _ingredient).firstOrNull,
                unitId: _unitId,
                qty: double.tryParse(_qty.text) ?? 0,
                onChanged: (v) => setState(() => _unitId = v),
              ),
              FilledButton(onPressed: _busy ? null : () => _create(ref), child: const Text('Create transfer')),
              if (_lastTransfer != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_lastTransfer!)),
              const SectionTitle('Transfers'),
              _TransferList(key: ValueKey(_refresh), onChanged: () => setState(() => _refresh++)),
              const SizedBox(height: 8),
              Text('Transfers need a connection and are not queued offline.',
                  style: TextStyle(color: context.muted, fontSize: 12)),
            ]);
          },
        ),
      ),
    );
  }
}

/// Transfers of this branch with approve, receive and delete for each.
class _TransferList extends StatelessWidget {
  const _TransferList({super.key, required this.onChanged});

  final VoidCallback onChanged;

  Future<void> _post(BuildContext context, String path, {Map<String, dynamic>? body, String? done}) async {
    final app = AppScope.read(context);
    final ok = await guarded(context, () async => app.api.post(path, body: body));
    if (ok && context.mounted) {
      if (done != null) {
        snack(context, done);
      }
      onChanged();
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return FutureBuilder<dynamic>(
      future: app.api.get('/branches/${app.branchId}/stock-transfers'),
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator()));
        }
        if (snap.hasError) {
          return Text('${snap.error}', style: TextStyle(color: context.muted));
        }
        final rows = asRows(snap.data);
        if (rows.isEmpty) {
          return const EmptyState('No transfers yet.');
        }
        return Column(children: [
          for (final t in rows)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Expanded(child: Text('#${t['id']}  ${t['from']} to ${t['to']}', style: const TextStyle(fontWeight: FontWeight.w700))),
                    StatusChip('${t['status']}'),
                  ]),
                  Text(asRows(t['items']).map((i) => '${i['ingredient']} ${formatQuantity(toNum(i['qty']))} ${i['unit'] ?? ''}').join(', '),
                      style: TextStyle(color: context.muted, fontSize: 12)),
                  const SizedBox(height: 6),
                  Row(children: [
                    if ('${t['status']}' == 'requested')
                      OutlinedButton(
                        onPressed: () => _post(context, '/stock-transfers/${t['id']}/approve', done: 'Transfer approved.'),
                        child: const Text('Approve'),
                      ),
                    if ('${t['status']}' == 'approved' || '${t['status']}' == 'in_transit') ...[
                      FilledButton(
                        onPressed: () => _post(context, '/stock-transfers/${t['id']}/receive', done: 'Transfer received.'),
                        child: const Text('Receive'),
                      ),
                    ],
                    const Spacer(),
                    IconButton(
                      tooltip: 'Delete transfer',
                      icon: const Icon(Icons.delete_outline, color: Brand.danger),
                      onPressed: () async {
                        final body = await askDeleteReason(context, 'Delete transfer #${t['id']}');
                        if (body != null && context.mounted) {
                          await _post(context, '/stock-transfers/${t['id']}/delete', body: body, done: 'Transfer deleted.');
                        }
                      },
                    ),
                  ]),
                ]),
              ),
            ),
        ]);
      },
    );
  }
}

// ---------------------------------------------------------------- recipes

class RecipesScreen extends StatelessWidget {
  const RecipesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final menuNames = {
      for (final c in asRows(app.bootstrap?['categories']))
        for (final i in asRows(c['items'])) (i['id'] as num).toInt(): localized(app, i),
    };
    return ListModule(
      title: 'Recipes',
      path: '/recipes',
      itemBuilder: (context, r, reload) => Card(
        child: ListTile(
          title: Text(menuNames[(r['menu_item_id'] as num).toInt()] ?? 'Menu item #${r['menu_item_id']}'),
          subtitle: Text('${asRows(r['items']).length} ingredients'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RecipeCostScreen(recipe: r, title: menuNames[(r['menu_item_id'] as num).toInt()] ?? 'Recipe'))),
        ),
      ),
      fab: (reload) => FloatingActionButton.extended(
        onPressed: () async {
          final saved = await Navigator.push<bool>(
            context,
            MaterialPageRoute(builder: (_) => const RecipeFormScreen()),
          );
          if (saved == true) {
            await reload();
          }
        },
        icon: const Icon(Icons.add),
        label: const Text('Recipe'),
      ),
    );
  }
}

/// Create or edit a recipe: the dish plus ingredient lines with quantities.
class RecipeFormScreen extends StatefulWidget {
  const RecipeFormScreen({super.key, this.recipe, this.fixedMenuItemId});

  /// Null creates a new recipe.
  final Map<String, dynamic>? recipe;

  /// Set when editing; the dish itself cannot change.
  final int? fixedMenuItemId;

  @override
  State<RecipeFormScreen> createState() => _RecipeFormScreenState();
}

/// One recipe line: the ingredient, the unit the quantity is typed in, and the quantity.
class _RecipeLine {
  _RecipeLine({this.ingredient, String qty = ''}) : qty = TextEditingController(text: qty);

  int? ingredient;
  int? unitId;
  final TextEditingController qty;
}

class _RecipeFormScreenState extends State<RecipeFormScreen> {
  final _note = TextEditingController();
  final List<_RecipeLine> _lines = [];
  int? _menuItem;
  bool _busy = false;
  bool _converted = false;

  @override
  void initState() {
    super.initState();
    _menuItem = widget.fixedMenuItemId ?? widget.recipe?['menu_item_id'] as int?;
    _note.text = '${widget.recipe?['note'] ?? ''}';
    for (final l in asRows(widget.recipe?['items'])) {
      // Stored in base units (g, l, pcs); converted to the reference unit once the unit list is loaded.
      _lines.add(_RecipeLine(ingredient: (l['ingredient_id'] as num?)?.toInt(), qty: '${toNum(l['qty'])}'));
    }
    if (_lines.isEmpty) {
      _lines.add(_RecipeLine());
    }
  }

  @override
  void dispose() {
    _note.dispose();
    for (final l in _lines) {
      l.qty.dispose();
    }
    super.dispose();
  }

  /// First time the lists are known: show saved quantities in g, ml or pcs instead of base units.
  void _convertSaved(List<Map<String, dynamic>> ingredients, List<UnitInfo> units) {
    if (_converted) {
      return;
    }
    _converted = true;
    if (widget.recipe == null) {
      return;
    }
    for (final l in _lines) {
      final ing = ingredients.where((i) => i['id'] == l.ingredient).firstOrNull;
      final own = ing == null ? null : ingredientUnit(ing, units);
      final ref = own == null ? null : referenceUnit(units, own.type);
      if (ref == null) {
        continue;
      }
      l.unitId = ref.id;
      l.qty.text = formatQuantity(toNum(l.qty.text) / ref.factor).replaceAll(',', '');
    }
  }

  UnitInfo? _unitOf(_RecipeLine l, Map<String, dynamic>? ing, List<UnitInfo> units) {
    final own = ing == null ? null : ingredientUnit(ing, units);
    if (own == null) {
      return null;
    }
    return units.where((u) => u.id == l.unitId).firstOrNull ?? referenceUnit(units, own.type) ?? own;
  }

  double _lineCost(_RecipeLine l, Map<String, dynamic>? ing, List<UnitInfo> units, Map costs) {
    final unit = _unitOf(l, ing, units);
    if (unit == null || l.ingredient == null) {
      return 0;
    }
    return toNum(l.qty.text) * unit.factor * toNum(costs['${l.ingredient}']);
  }

  Future<void> _save(List<UnitInfo> units, List<Map<String, dynamic>> ingredients) async {
    final app = AppScope.read(context);
    final items = <Map<String, dynamic>>[];
    for (final l in _lines) {
      final qty = double.tryParse(l.qty.text.trim());
      if (l.ingredient != null && qty != null && qty > 0) {
        final unit = _unitOf(l, ingredients.where((i) => i['id'] == l.ingredient).firstOrNull, units);
        items.add({'ingredient_id': l.ingredient, 'qty': qty, 'unit_id': ?unit?.id});
      }
    }
    if (_menuItem == null || items.isEmpty) {
      snack(context, 'Choose the dish and at least one ingredient.', error: true);
      return;
    }
    setState(() => _busy = true);
    final editing = widget.recipe != null || widget.fixedMenuItemId != null;
    final body = {
      if (!editing) 'menu_item_id': _menuItem,
      if (_note.text.trim().isNotEmpty) 'note': _note.text.trim(),
      'lines': items,
    };
    final ok = await guarded(context, () async {
      if (editing) {
        await app.api.put('/recipes/${widget.recipe?['id'] ?? 0}', body: body);
      } else {
        await app.api.post('/recipes', body: body);
      }
    });
    if (!mounted) {
      return;
    }
    setState(() => _busy = false);
    if (ok) {
      snack(context, editing ? 'Recipe updated.' : 'Recipe saved.');
      Navigator.pop(context, true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final editing = widget.fixedMenuItemId != null;
    final menuItems = [
      for (final c in asRows(app.bootstrap?['categories']))
        for (final i in asRows(c['items'])) i,
    ];
    return Scaffold(
      appBar: AppBar(title: Text(editing ? 'Edit recipe' : 'New recipe')),
      body: _RefLoader(
        require: const ['ingredients'],
        builder: (context, ref) {
          final ingredients = asRows(ref['ingredients']);
          final units = [for (final u in asRows(ref['units'])) UnitInfo.fromJson(u)];
          final costs = ref['ingredient_costs'] is Map ? ref['ingredient_costs'] as Map : const {};
          _convertSaved(ingredients, units);
          final total = _lines.fold<double>(0, (s, l) => s + _lineCost(l, ingredients.where((i) => i['id'] == l.ingredient).firstOrNull, units, costs));
          final item = menuItems.where((i) => i['id'] == _menuItem).firstOrNull;
          final price = item == null ? 0.0 : toNum(item['base_price']);
          final margin = price > 0 ? (price - total) / price * 100 : null;
          return Column(children: [
            Expanded(
              child: ListView(padding: const EdgeInsets.all(16), children: [
                DropdownButtonFormField<int>(
                  initialValue: _menuItem,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Menu item'),
                  items: [
                    for (final i in menuItems)
                      DropdownMenuItem(value: (i['id'] as num).toInt(), child: Text(localized(app, i))),
                  ],
                  onChanged: editing ? null : (v) => setState(() => _menuItem = v),
                ),
                const SizedBox(height: 12),
                for (var i = 0; i < _lines.length; i++) _lineEditor(i, ingredients, units, costs),
                Align(
                  alignment: Alignment.centerLeft,
                  child: TextButton.icon(
                    onPressed: () => setState(() => _lines.add(_RecipeLine())),
                    icon: const Icon(Icons.add, size: 18),
                    label: const Text('Add ingredient'),
                  ),
                ),
                field(_note, 'Note (optional)'),
              ]),
            ),
            if (costs.isNotEmpty)
              Container(
                width: double.infinity,
                color: context.pageBg,
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: Text(
                  'Cost ${fmtMoney(total)}'
                  '${price > 0 ? '  -  Price ${fmtMoney(price)}' : ''}'
                  '${margin == null ? '' : '  -  Margin ${margin.toStringAsFixed(1)}%'}',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: SizedBox(
                  width: double.infinity,
                  child: FilledButton(onPressed: _busy ? null : () => _save(units, ingredients), child: Text(_busy ? 'Saving...' : 'Save recipe')),
                ),
              ),
            ),
          ]);
        },
      ),
    );
  }

  Widget _lineEditor(int i, List<Map<String, dynamic>> ingredients, List<UnitInfo> units, Map costs) {
    final line = _lines[i];
    final ing = ingredients.where((x) => x['id'] == line.ingredient).firstOrNull;
    final own = ing == null ? null : ingredientUnit(ing, units);
    final choices = own == null ? <UnitInfo>[] : unitsForType(units, own.type);
    final chosen = _unitOf(line, ing, units);
    final cost = _lineCost(line, ing, units, costs);
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(children: [
          Expanded(
            child: DropdownButtonFormField<int>(
              initialValue: line.ingredient,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Ingredient', isDense: true),
              items: [
                for (final x in ingredients)
                  DropdownMenuItem(value: (x['id'] as num).toInt(), child: Text('${x['name']}', overflow: TextOverflow.ellipsis)),
              ],
              onChanged: (v) => setState(() {
                line.ingredient = v;
                line.unitId = null;
              }),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.remove_circle_outline, color: Brand.danger),
            onPressed: () => setState(() {
              line.qty.dispose();
              _lines.removeAt(i);
            }),
          ),
        ]),
        const SizedBox(height: 8),
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(
            flex: 2,
            child: TextField(
              controller: line.qty,
              onChanged: (_) => setState(() {}),
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(labelText: 'Qty', isDense: true),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            flex: 3,
            child: DropdownButtonFormField<int>(
              initialValue: chosen?.id,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Unit', isDense: true),
              items: [for (final u in choices) DropdownMenuItem(value: u.id, child: Text(unitLabel(u, units), overflow: TextOverflow.ellipsis))],
              onChanged: choices.length < 2 ? null : (v) => setState(() => line.unitId = v),
            ),
          ),
        ]),
        if (costs.isNotEmpty && cost > 0)
          Padding(padding: const EdgeInsets.only(top: 4), child: Text('Line cost ${fmtMoney(cost)}', style: TextStyle(fontSize: 12, color: context.muted))),
      ]),
    );
  }
}

class RecipeCostScreen extends StatelessWidget {
  const RecipeCostScreen({super.key, required this.recipe, required this.title});

  final Map<String, dynamic> recipe;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => RecipeFormScreen(
              recipe: recipe,
              fixedMenuItemId: (recipe['menu_item_id'] as num).toInt(),
            ),
          ),
        ),
        icon: const Icon(Icons.edit_outlined),
        label: const Text('Edit'),
      ),
      body: _RefLoader(
        builder: (context, _) => DataView<Map<String, dynamic>>(
          path: '/recipes/${recipe['id']}/cost',
          transform: (raw) => Map<String, dynamic>.from(raw as Map),
          builder: (context, cost, raw, reload) {
            final ref = {for (final e in cost.entries) e.key: e.value};
            return ListView(padding: const EdgeInsets.all(16), children: [
              Card(
                child: Column(children: [
                  for (final e in ref.entries.where((e) => e.value is! List && e.value is! Map))
                    ListTile(dense: true, title: Text(titleCase(e.key)), trailing: Text('${e.value}')),
                ]),
              ),
              const SectionTitle('Ingredients'),
              Card(
                child: Column(children: [
                  for (final i in asRows(recipe['items']))
                    ListTile(dense: true, title: Text('Ingredient #${i['ingredient_id']}'), trailing: Text(toNum(i['qty']).toStringAsFixed(2))),
                ]),
              ),
            ]);
          },
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------- menu & tables

class MenuViewScreen extends StatelessWidget {
  const MenuViewScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final cats = asRows(app.bootstrap?['categories']);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Menu'),
        actions: [
          if (app.can('menu.manage'))
            IconButton(
              tooltip: 'Add or edit menu items',
              icon: const Icon(Icons.edit_outlined),
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const MenuManageScreen()),
              ),
            ),
        ],
      ),
      body: cats.isEmpty
          ? const EmptyState('The menu has not been downloaded yet.')
          : ListView(padding: const EdgeInsets.all(12), children: [
              for (final c in cats) ...[
                SectionTitle(localized(app, c)),
                for (final i in asRows(c['items']))
                  Card(
                    child: ListTile(
                      title: Text(localized(app, i)),
                      leading: ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: SizedBox(width: 44, height: 44, child: FoodImage(url: foodImageUrl(app, i), iconSize: 18)),
                      ),
                      subtitle: Text(asRows(i['variants']).length > 1
                          ? asRows(i['variants']).map((v) => '${localized(app, v)} ${fmtMoney(v['price'])}').join('  -  ')
                          : fmtMoney(asRows(i['variants']).isEmpty ? i['base_price'] : asRows(i['variants']).first['price'])),
                      trailing: i['is_available'] == true ? null : const Text('Off', style: TextStyle(color: Brand.danger)),
                    ),
                  ),
              ],
            ]),
    );
  }
}

