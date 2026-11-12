import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api_client.dart';
import '../../core/app_state.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';

/// Menu-book pre-orders: no table or time slot is held and every guest
/// detail is optional. Mirrors the web Pre-order page (list, new, serve,
/// cancel, share link).
class PreordersScreen extends StatefulWidget {
  const PreordersScreen({super.key});

  @override
  State<PreordersScreen> createState() => _PreordersScreenState();
}

class _PreordersScreenState extends State<PreordersScreen> {
  final _key = GlobalKey<DataViewState<Map<String, dynamic>>>();
  String? _status = 'pending';

  Future<void> _act(Map<String, dynamic> p, String action, Future<void> Function() reload) async {
    final app = AppScope.read(context);
    final label = action == 'complete' ? 'Mark ${p['order_no']} as served?' : 'Cancel ${p['order_no']}?';
    if (!await confirm(context, label, action: action == 'complete' ? 'Served' : 'Cancel pre-order')) {
      return;
    }
    if (!mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      await app.api.post('/branches/${app.branchId}/preorders/${p['id']}/$action');
    });
    if (ok) {
      await reload();
    }
  }

  Future<void> _create(Future<void> Function() reload) async {
    final done = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => const NewPreorderScreen()));
    if (done == true) {
      await reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Pre-orders')),
      floatingActionButton: app.can('preorders.manage')
          ? FloatingActionButton.extended(
              onPressed: () => _create(() async => _key.currentState?.reload()),
              icon: const Icon(Icons.add),
              label: const Text('New pre-order'),
            )
          : null,
      body: DataView<Map<String, dynamic>>(
        key: ValueKey(_status),
        path: '/branches/${app.branchId}/preorders',
        query: {if (_status != null) 'filter[status]': _status},
        transform: (raw) => Map<String, dynamic>.from(raw as Map),
        builder: (context, data, raw, reload) {
          final rows = asRows(data['preorders']);
          final share = '${data['share_url'] ?? ''}';
          return ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 96),
            children: [
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(children: [
                  for (final s in [null, 'pending', 'completed', 'cancelled'])
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(s == null ? 'All' : (s == 'pending' ? 'Waiting' : s == 'completed' ? 'Served' : 'Cancelled')),
                        selected: _status == s,
                        onSelected: (_) => setState(() => _status = s),
                      ),
                    ),
                ]),
              ),
              if (share.isNotEmpty)
                Card(
                  child: ListTile(
                    dense: true,
                    leading: const Icon(Icons.link),
                    title: const Text('Share the menu book'),
                    subtitle: Text(share, maxLines: 1, overflow: TextOverflow.ellipsis),
                    trailing: IconButton(
                      tooltip: 'Copy link',
                      icon: const Icon(Icons.copy, size: 18),
                      onPressed: () async {
                        await Clipboard.setData(ClipboardData(text: share));
                        if (context.mounted) {
                          snack(context, 'Link copied.');
                        }
                      },
                    ),
                  ),
                ),
              if (rows.isEmpty)
                const Padding(padding: EdgeInsets.only(top: 60), child: EmptyState('No pre-orders yet. Tap New pre-order to take the first one.')),
              for (final p in rows)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: _PreorderCard(
                    p: p,
                    canManage: data['can_manage'] == true,
                    onComplete: () => _act(p, 'complete', reload),
                    onCancel: () => _act(p, 'cancel', reload),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _PreorderCard extends StatelessWidget {
  const _PreorderCard({required this.p, required this.canManage, required this.onComplete, required this.onCancel});

  final Map<String, dynamic> p;
  final bool canManage;
  final VoidCallback onComplete;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final items = asRows(p['items']);
    final status = '${p['status']}';
    final name = '${p['customer_name'] ?? ''}'.isEmpty ? 'No name given' : '${p['customer_name']}';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Expanded(child: Text('${p['order_no']}', style: const TextStyle(fontWeight: FontWeight.w700))),
            StatusChip(status == 'completed' ? 'served' : status == 'pending' ? 'pending' : 'cancelled'),
          ]),
          const SizedBox(height: 2),
          Text(
            [
              name,
              if (p['customer_mobile'] != null) '${p['customer_mobile']}',
              if (p['expected_at'] != null) 'For ${fmtDate(p['expected_at'])}',
              if (p['party_size'] != null) '${p['party_size']} guests',
              if (p['table'] != null) 'Table ${p['table']}',
            ].join('  -  '),
            style: TextStyle(color: context.muted, fontSize: 12),
          ),
          if (p['address'] != null) Text('${p['address']}', style: TextStyle(color: context.muted, fontSize: 12)),
          const SizedBox(height: 6),
          Text(
            items.map((i) => '${i['quantity']} x ${localized(app, i)}').join(', '),
            style: const TextStyle(fontSize: 13),
          ),
          if (p['note'] != null) Text('Note: ${p['note']}', style: TextStyle(color: context.muted, fontSize: 12)),
          const SizedBox(height: 6),
          Row(children: [
            Expanded(
              child: Text(
                'Total ${fmtMoney(p['total'])}'
                '${toNum(p['advance_amount']) > 0 ? '  -  Advance ${fmtMoney(p['advance_amount'])}  -  Balance ${fmtMoney(p['balance_due'])}' : ''}',
                style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
              ),
            ),
          ]),
          if (canManage && status == 'pending') ...[
            const SizedBox(height: 8),
            Row(children: [
              Expanded(child: FilledButton(onPressed: onComplete, child: const Text('Mark served'))),
              const SizedBox(width: 8),
              OutlinedButton(
                style: OutlinedButton.styleFrom(foregroundColor: Brand.danger),
                onPressed: onCancel,
                child: const Text('Cancel'),
              ),
            ]),
          ],
        ]),
      ),
    );
  }
}

class _Line {
  _Line({required this.item, this.variant, this.addOns = const []});

  final Map<String, dynamic> item;
  final Map<String, dynamic>? variant;
  final List<Map<String, dynamic>> addOns;
  int quantity = 1;

  double get unit =>
      toNum(variant?['price'] ?? item['base_price']) + addOns.fold<double>(0, (s, a) => s + toNum(a['price']));
  double get total => unit * quantity;

  Map<String, dynamic> toJson() => {
        'menu_item_id': item['id'],
        if (variant != null) 'variant_id': variant!['id'],
        'quantity': quantity,
        if (addOns.isNotEmpty) 'add_ons': [for (final a in addOns) {'id': a['id'], 'qty': 1}],
      };
}

/// Pick dishes, then add optional guest details. Everything but the items is optional.
class NewPreorderScreen extends StatefulWidget {
  const NewPreorderScreen({super.key});

  @override
  State<NewPreorderScreen> createState() => _NewPreorderScreenState();
}

class _NewPreorderScreenState extends State<NewPreorderScreen> {
  final _lines = <_Line>[];
  final _name = TextEditingController();
  final _mobile = TextEditingController();
  final _address = TextEditingController();
  final _guests = TextEditingController();
  final _advance = TextEditingController();
  final _note = TextEditingController();
  DateTime? _when;
  int? _tableId;
  List<Map<String, dynamic>> _tables = const [];
  List<Map<String, dynamic>> _categories = const [];
  bool _loading = true;
  bool _busy = false;
  String? _error;
  int _step = 0;
  String _search = '';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    for (final c in [_name, _mobile, _address, _guests, _advance, _note]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    final app = AppScope.read(context);
    try {
      final menu = await app.cachedGet('/branches/${app.branchId}/menu');
      var tables = <Map<String, dynamic>>[];
      try {
        final ref = await app.api.get('/branches/${app.branchId}/reference');
        tables = asRows((ref as Map)['tables']);
      } on ApiException {
        tables = [];
      }
      if (mounted) {
        setState(() {
          _categories = asRows(menu.data).where((c) => asRows(c['items']).isNotEmpty).toList();
          _tables = tables;
          _loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.fullMessage;
          _loading = false;
        });
      }
    }
  }

  double get _total => _lines.fold(0, (s, l) => s + l.total);
  int get _count => _lines.fold(0, (s, l) => s + l.quantity);

  Future<void> _add(Map<String, dynamic> item) async {
    final app = AppScope.read(context);
    final variants = asRows(item['variants']);
    final addOns = asRows(item['add_ons']);
    Map<String, dynamic>? variant = variants.length == 1 ? variants.first : null;
    final picked = <int, Map<String, dynamic>>{};
    if (variants.length > 1 || addOns.isNotEmpty) {
      variant = variants.length > 1
          ? (variants.where((v) => v['is_default'] == true).firstOrNull ?? variants.first)
          : variant;
      final go = await showForm<bool>(
        context,
        title: localized(app, item),
        builder: (ctx) => StatefulBuilder(
          builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            if (variants.length > 1) ...[
              const Text('Size', style: TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 6),
              Wrap(spacing: 6, runSpacing: 6, children: [
                for (final v in variants)
                  ChoiceChip(
                    label: Text('${localized(app, v)}  ${fmtMoney(v['price'])}'),
                    selected: variant?['id'] == v['id'],
                    onSelected: (_) => set(() => variant = v),
                  ),
              ]),
              const SizedBox(height: 12),
            ],
            if (addOns.isNotEmpty) ...[
              const Text('Extras', style: TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 6),
              Wrap(spacing: 6, runSpacing: 6, children: [
                for (final a in addOns)
                  FilterChip(
                    label: Text('${localized(app, a)}  ${fmtMoney(a['price'])}'),
                    selected: picked.containsKey((a['id'] as num).toInt()),
                    onSelected: (on) => set(() {
                      final id = (a['id'] as num).toInt();
                      on ? picked[id] = a : picked.remove(id);
                    }),
                  ),
              ]),
              const SizedBox(height: 12),
            ],
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Add to pre-order')),
          ]),
        ),
      );
      if (go != true) {
        return;
      }
    }
    final extras = picked.values.toList();
    final existing = _lines.where((l) =>
        l.item['id'] == item['id'] &&
        l.variant?['id'] == variant?['id'] &&
        l.addOns.map((a) => a['id']).join(',') == extras.map((a) => a['id']).join(','));
    setState(() {
      if (existing.isNotEmpty) {
        existing.first.quantity += 1;
      } else {
        _lines.add(_Line(item: item, variant: variant, addOns: extras));
      }
    });
  }

  Future<void> _pickWhen() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: _when ?? now,
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: now.add(const Duration(days: 365)),
    );
    if (date == null || !mounted) {
      return;
    }
    final time = await showTimePicker(context: context, initialTime: TimeOfDay.fromDateTime(_when ?? now));
    if (time == null) {
      return;
    }
    setState(() => _when = DateTime(date.year, date.month, date.day, time.hour, time.minute));
  }

  Future<void> _submit() async {
    final app = AppScope.read(context);
    final advance = double.tryParse(_advance.text.trim()) ?? 0;
    if (advance > _total + 0.009) {
      snack(context, 'The advance cannot be more than the total.', error: true);
      return;
    }
    setState(() => _busy = true);
    final ok = await guarded(context, () async {
      await app.api.post(
        '/branches/${app.branchId}/preorders',
        idempotencyKey: newClientId(),
        body: {
          'items': [for (final l in _lines) l.toJson()],
          if (_name.text.trim().isNotEmpty) 'customer_name': _name.text.trim(),
          if (_mobile.text.trim().isNotEmpty) 'customer_mobile': _mobile.text.trim(),
          if (_address.text.trim().isNotEmpty) 'address': _address.text.trim(),
          if (int.tryParse(_guests.text.trim()) != null) 'party_size': int.parse(_guests.text.trim()),
          if (_when != null) 'expected_at': _when!.toUtc().toIso8601String(),
          'restaurant_table_id': ?_tableId,
          if (advance > 0) 'advance_amount': advance,
          if (_note.text.trim().isNotEmpty) 'note': _note.text.trim(),
        },
      );
    });
    if (!mounted) {
      return;
    }
    setState(() => _busy = false);
    if (ok) {
      snack(context, 'Pre-order taken.');
      Navigator.pop(context, true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_step == 0 ? 'New pre-order' : 'Guest details'),
        leading: _step == 1 ? BackButton(onPressed: () => setState(() => _step = 0)) : null,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!)))
              : _step == 0
                  ? _menuStep(context)
                  : _detailsStep(context),
      bottomNavigationBar: _loading || _error != null
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                child: _step == 0
                    ? FilledButton(
                        onPressed: _lines.isEmpty ? null : () => setState(() => _step = 1),
                        child: Text(_lines.isEmpty ? 'Pick dishes' : 'Continue ($_count items, ${fmtMoney(_total)})'),
                      )
                    : FilledButton(
                        onPressed: _busy ? null : _submit,
                        child: Text(_busy ? 'Saving...' : 'Take pre-order (${fmtMoney(_total)})'),
                      ),
              ),
            ),
    );
  }

  Widget _menuStep(BuildContext context) {
    final app = AppScope.of(context);
    final term = _search.trim().toLowerCase();
    return ListView(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
      children: [
        TextField(
          onChanged: (v) => setState(() => _search = v),
          decoration: const InputDecoration(hintText: 'Search dishes', prefixIcon: Icon(Icons.search), isDense: true),
        ),
        for (final c in _categories) ...[
          if (asRows(c['items']).any((i) => term.isEmpty || '${i['name']}'.toLowerCase().contains(term))) SectionTitle(localized(app, c)),
          for (final i in asRows(c['items']).where((i) => term.isEmpty || '${i['name']}'.toLowerCase().contains(term)))
            Builder(builder: (context) {
              final qty = _lines.where((l) => l.item['id'] == i['id']).fold<int>(0, (s, l) => s + l.quantity);
              final variants = asRows(i['variants']);
              final price = variants.isEmpty ? i['base_price'] : variants.first['price'];
              return Card(
                child: ListTile(
                  dense: true,
                  title: Text(localized(app, i)),
                  subtitle: Text(variants.length > 1 ? 'From ${fmtMoney(price)}' : fmtMoney(price)),
                  trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                    if (qty > 0)
                      Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: Text('$qty', style: const TextStyle(fontWeight: FontWeight.w800, color: Brand.success)),
                      ),
                    IconButton.filledTonal(onPressed: () => _add(i), icon: const Icon(Icons.add)),
                  ]),
                ),
              );
            }),
        ],
        if (_lines.isNotEmpty) ...[
          const SectionTitle('Basket'),
          for (final l in _lines)
            Card(
              child: ListTile(
                dense: true,
                title: Text(localized(app, l.item)),
                subtitle: Text([
                  if (l.variant != null && asRows(l.item['variants']).length > 1) localized(app, l.variant!),
                  if (l.addOns.isNotEmpty) '+ ${l.addOns.map((a) => a['name']).join(', ')}',
                  fmtMoney(l.total),
                ].join('  -  ')),
                trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    icon: const Icon(Icons.remove_circle_outline),
                    onPressed: () => setState(() {
                      l.quantity -= 1;
                      if (l.quantity <= 0) {
                        _lines.remove(l);
                      }
                    }),
                  ),
                  Text('${l.quantity}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    icon: const Icon(Icons.add_circle_outline),
                    onPressed: () => setState(() => l.quantity += 1),
                  ),
                ]),
              ),
            ),
        ],
      ],
    );
  }

  Widget _detailsStep(BuildContext context) {
    return ListView(padding: const EdgeInsets.all(16), children: [
      Text('All of this is optional.', style: TextStyle(color: context.muted)),
      const SizedBox(height: 12),
      field(_name, 'Name'),
      field(_mobile, 'Mobile (the guest gets an SMS copy)', type: TextInputType.phone),
      field(_address, 'Address', maxLines: 2),
      Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Expanded(child: field(_guests, 'Guests', type: TextInputType.number)),
        const SizedBox(width: 12),
        Expanded(child: field(_advance, 'Advance', type: const TextInputType.numberWithOptions(decimal: true))),
      ]),
      Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: OutlinedButton.icon(
          onPressed: _pickWhen,
          icon: const Icon(Icons.event),
          label: Text(_when == null ? 'Visit date and time' : fmtDate(_when!.toIso8601String())),
        ),
      ),
      if (_tables.isNotEmpty)
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: DropdownButtonFormField<int?>(
            initialValue: _tableId,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Table'),
            items: [
              const DropdownMenuItem<int?>(value: null, child: Text('No table')),
              for (final t in _tables) DropdownMenuItem<int?>(value: (t['id'] as num).toInt(), child: Text('${t['name']}')),
            ],
            onChanged: (v) => setState(() => _tableId = v),
          ),
        ),
      field(_note, 'Note', maxLines: 2),
    ]);
  }
}
