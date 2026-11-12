import 'package:flutter/material.dart';

import '../../core/app_state.dart';
import '../../core/sounds.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';

class CustomersScreen extends StatefulWidget {
  const CustomersScreen({super.key});

  @override
  State<CustomersScreen> createState() => _CustomersScreenState();
}

class _CustomersScreenState extends State<CustomersScreen> {
  String _mobile = '';
  final _search = TextEditingController();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _adjust(Map<String, dynamic> c) async {
    final app = AppScope.read(context);
    final points = TextEditingController();
    final reason = TextEditingController();
    final go = await showForm<bool>(
      context,
      title: 'Adjust points for ${c['name']}',
      builder: (ctx) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        field(points, 'Points (use - to remove)', type: const TextInputType.numberWithOptions(signed: true)),
        field(reason, 'Reason'),
        FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Apply')),
      ]),
    );
    final p = int.tryParse(points.text);
    final r = reason.text.trim();
    points.dispose();
    reason.dispose();
    if (go != true || p == null || r.isEmpty || !mounted) {
      return;
    }
    await guarded(context, () async {
      final res = await app.api.post('/loyalty/adjust', body: {'customer_id': c['id'], 'points': p, 'reason': r}) as Map;
      if (mounted) {
        snack(context, 'New balance: ${res['balance']} points.');
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return ListModule(
      key: ValueKey(_mobile),
      title: 'Customers',
      path: '/customers',
      query: {if (_mobile.isNotEmpty) 'filter[mobile]': _mobile},
      header: TextField(
        controller: _search,
        keyboardType: TextInputType.phone,
        onSubmitted: (v) => setState(() => _mobile = v.trim()),
        decoration: InputDecoration(
          hintText: 'Find by mobile number',
          prefixIcon: const Icon(Icons.search),
          isDense: true,
          suffixIcon: _mobile.isEmpty
              ? null
              : IconButton(
                  icon: const Icon(Icons.clear),
                  onPressed: () {
                    _search.clear();
                    setState(() => _mobile = '');
                  },
                ),
        ),
      ),
      itemBuilder: (context, c, reload) => Card(
        child: ListTile(
          leading: CircleAvatar(backgroundColor: context.pageBg, child: Icon(Icons.person, color: context.brand)),
          title: Text('${c['name']}'),
          subtitle: Text('${c['mobile']}'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => CustomerDetailScreen(customer: c, onAdjust: app.can('loyalty.manage') ? () => _adjust(c) : null)),
          ),
        ),
      ),
    );
  }
}

/// One customer: contact, loyalty points and the latest orders.
class CustomerDetailScreen extends StatelessWidget {
  const CustomerDetailScreen({super.key, required this.customer, this.onAdjust});

  final Map<String, dynamic> customer;
  final Future<void> Function()? onAdjust;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('${customer['name']}')),
      body: DataView<Map<String, dynamic>>(
        path: '/customers/${customer['id']}',
        transform: (raw) => Map<String, dynamic>.from(raw as Map),
        builder: (context, c, raw, reload) {
          final orders = asRows(c['orders']);
          return ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(12), children: [
            Row(children: [
              Expanded(child: Metric('Points', '${c['points']}', icon: Icons.stars)),
              const SizedBox(width: 8),
              Expanded(child: Metric('Orders', '${c['orders_count']}', icon: Icons.receipt_long)),
            ]),
            Card(
              child: Column(children: [
                ListTile(dense: true, leading: const Icon(Icons.phone), title: Text('${c['mobile']}')),
                if (c['email'] != null) ListTile(dense: true, leading: const Icon(Icons.email_outlined), title: Text('${c['email']}')),
                if (onAdjust != null)
                  ListTile(
                    dense: true,
                    leading: const Icon(Icons.stars, color: Brand.warning),
                    title: const Text('Adjust loyalty points'),
                    onTap: () async {
                      await onAdjust!();
                      await reload();
                    },
                  ),
              ]),
            ),
            const SectionTitle('Recent orders'),
            if (orders.isEmpty) const EmptyState('No orders yet.'),
            for (final o in orders)
              Card(
                child: ListTile(
                  dense: true,
                  title: Text('${o['invoice_no']}  -  ${fmtMoney(o['total'])}'),
                  subtitle: Text('${titleCase('${o['order_type']}')}  -  ${fmtDate(o['created_at'])}'),
                  trailing: StatusChip('${o['status']}'),
                ),
              ),
          ]);
        },
      ),
    );
  }
}

class CouponsScreen extends StatelessWidget {
  const CouponsScreen({super.key});

  /// Runs a code against the server's coupon rules with a sample subtotal,
  /// like the web coupon validator.
  Future<void> _checkCode(BuildContext context, AppState app) async {
    final code = TextEditingController();
    final subtotal = TextEditingController();
    final go = await showForm<bool>(
      context,
      title: 'Check a coupon code',
      builder: (ctx) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        field(code, 'Code'),
        field(subtotal, 'Order subtotal (BDT)', type: const TextInputType.numberWithOptions(decimal: true)),
        FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Check')),
      ]),
    );
    final c = code.text.trim().toUpperCase();
    final s = double.tryParse(subtotal.text.trim());
    code.dispose();
    subtotal.dispose();
    if (go != true || c.isEmpty || s == null || !context.mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      final res = await app.api.post('/coupons/validate', body: {'code': c, 'subtotal': s}) as Map;
      if (context.mounted) {
        final discount = toNum(res['discount'] ?? res['value']);
        snack(
          context,
          discount > 0
              ? 'Valid: ${res['type'] == 'percent' ? '${toNum(res['value']).toStringAsFixed(0)}% off' : fmtMoney(res['value'])} - you save ${fmtMoney(discount)}.'
              : 'Coupon accepted for this order.',
        );
      }
    });
    if (ok) {
      SoundFx.play('notification');
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return ListModule(
      title: 'Coupons',
      path: '/coupons',
      actions: [
        IconButton(
          tooltip: 'Check a coupon code',
          icon: const Icon(Icons.verified_outlined),
          onPressed: () => _checkCode(context, app),
        ),
      ],
      itemBuilder: (context, c, reload) => Card(
        child: ListTile(
          title: Text('${c['code']}', style: const TextStyle(fontWeight: FontWeight.w700)),
          subtitle: Text(
            '${c['type'] == 'percent' ? '${toNum(c['value']).toStringAsFixed(0)}% off' : '${fmtMoney(c['value'])} off'}'
            '${toNum(c['min_order']) > 0 ? '  -  min ${fmtMoney(c['min_order'])}' : ''}'
            '${c['redemptions_count'] == null ? '' : '  -  used ${c['redemptions_count']} times'}'
            '${c['ends_at'] == null ? '' : '  -  until ${fmtDay(c['ends_at'])}'}',
          ),
          onLongPress: () async {
            if (await confirm(context, 'Switch off ${c['code']}?', body: 'Past orders keep the discount.', action: 'Switch off')) {
              if (!context.mounted) {
                return;
              }
              final ok = await guarded(context, () async => app.api.delete('/coupons/${c['id']}'));
              if (ok) {
                await reload();
              }
            }
          },
          trailing: Switch(
            value: c['is_active'] == true,
            onChanged: (v) async {
              final ok = await guarded(context, () async {
                await app.api.put('/coupons/${c['id']}', body: {
                  'code': c['code'],
                  'type': c['type'],
                  'value': toNum(c['value']),
                  'is_active': v,
                });
              });
              if (ok) {
                await reload();
              }
            },
          ),
        ),
      ),
      fab: (reload) => FloatingActionButton.extended(
        onPressed: () async {
          final code = TextEditingController();
          final value = TextEditingController();
          var type = 'percent';
          final go = await showForm<bool>(
            context,
            title: 'New coupon',
            builder: (ctx) => StatefulBuilder(
              builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                field(code, 'Code'),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'percent', label: Text('Percent')),
                    ButtonSegment(value: 'fixed', label: Text('Fixed')),
                  ],
                  selected: {type},
                  onSelectionChanged: (s) => set(() => type = s.first),
                ),
                const SizedBox(height: 12),
                field(value, 'Value', type: const TextInputType.numberWithOptions(decimal: true)),
                FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Create')),
              ]),
            ),
          );
          final v = double.tryParse(value.text);
          final c = code.text.trim().toUpperCase();
          code.dispose();
          value.dispose();
          if (go == true && v != null && c.isNotEmpty && context.mounted) {
            await guarded(context, () async {
              await app.api.post('/coupons', body: {'code': c, 'type': type, 'value': v});
            });
            await reload();
          }
        },
        icon: const Icon(Icons.add),
        label: const Text('Coupon'),
      ),
    );
  }
}
