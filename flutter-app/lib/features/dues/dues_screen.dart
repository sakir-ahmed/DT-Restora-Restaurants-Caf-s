import 'package:flutter/material.dart';

import '../../core/app_state.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';
import '../orders/orders_screens.dart';

/// Due sales: what customers still owe, with partial collection. Mirrors the
/// web Dues page; needs a connection because a collection posts money.
class DuesScreen extends StatefulWidget {
  const DuesScreen({super.key});

  @override
  State<DuesScreen> createState() => _DuesScreenState();
}

class _DuesScreenState extends State<DuesScreen> {
  final _key = GlobalKey<DataViewState<Map<String, dynamic>>>();
  String _search = '';

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Dues')),
      body: DataView<Map<String, dynamic>>(
        key: _key,
        path: '/branches/${app.branchId}/dues',
        transform: (raw) => Map<String, dynamic>.from(raw as Map),
        builder: (context, data, raw, reload) {
          final all = asRows(data['dues']);
          final term = _search.trim().toLowerCase();
          final rows = term.isEmpty
              ? all
              : all
                  .where((d) => [d['invoice_no'], d['customer_name'], d['customer_mobile']]
                      .any((v) => '$v'.toLowerCase().contains(term)))
                  .toList();
          final methods = asRows(data['methods']);
          return ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 88),
            children: [
              Row(children: [
                Expanded(child: Metric('To collect', fmtMoney(data['outstanding']), icon: Icons.account_balance_wallet_outlined, negative: true)),
                const SizedBox(width: 8),
                Expanded(child: Metric('Open dues', '${all.length}', icon: Icons.receipt_long)),
              ]),
              const SizedBox(height: 10),
              TextField(
                onChanged: (v) => setState(() => _search = v),
                decoration: const InputDecoration(
                  hintText: 'Search invoice, name or mobile',
                  prefixIcon: Icon(Icons.search),
                  isDense: true,
                ),
              ),
              const SizedBox(height: 10),
              if (rows.isEmpty) const Padding(padding: EdgeInsets.only(top: 60), child: EmptyState('No dues to collect.')),
              for (final d in rows)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: _DueCard(due: d, methods: methods, onChanged: reload),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _DueCard extends StatelessWidget {
  const _DueCard({required this.due, required this.methods, required this.onChanged});

  final Map<String, dynamic> due;
  final List<Map<String, dynamic>> methods;
  final Future<void> Function() onChanged;

  Future<void> _collect(BuildContext context) async {
    final app = AppScope.read(context);
    final balance = toNum(due['balance']);
    final amount = TextEditingController(text: balance.toStringAsFixed(2));
    final reference = TextEditingController();
    var method = methods.isEmpty ? 'cash' : '${methods.first['method']}';
    final go = await showForm<bool>(
      context,
      title: 'Collect for ${due['invoice_no']}',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text('Balance ${fmtMoney(balance)}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
          const SizedBox(height: 10),
          Wrap(spacing: 6, runSpacing: 6, children: [
            for (final m in methods)
              ChoiceChip(
                label: Text(localized(app, m)),
                selected: method == '${m['method']}',
                onSelected: (_) => set(() => method = '${m['method']}'),
              ),
          ]),
          const SizedBox(height: 12),
          field(amount, 'Amount to collect', type: const TextInputType.numberWithOptions(decimal: true)),
          if (method != 'cash') field(reference, 'Reference (optional)'),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Collect')),
        ]),
      ),
    );
    final value = double.tryParse(amount.text.trim()) ?? 0;
    final ref = reference.text.trim();
    amount.dispose();
    reference.dispose();
    if (go != true || !context.mounted) {
      return;
    }
    if (value <= 0 || value > balance + 0.009) {
      snack(context, 'Enter an amount between 0 and ${fmtMoney(balance)}.', error: true);
      return;
    }
    final ok = await guarded(context, () async {
      await app.api.post(
        '/branches/${app.branchId}/dues/${due['id']}/collect',
        body: {'method': method, 'amount': value, if (ref.isNotEmpty) 'reference': ref},
        idempotencyKey: newClientId(),
      );
    });
    if (ok && context.mounted) {
      snack(context, 'Collected ${fmtMoney(value)}.');
      await onChanged();
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final name = '${due['customer_name'] ?? ''}'.isEmpty ? 'Customer' : '${due['customer_name']}';
    final deleted = due['deleted'] == true;
    final collections = asRows(due['collections']);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Expanded(child: Text(name, style: const TextStyle(fontWeight: FontWeight.w700))),
            Text(fmtMoney(due['balance']),
                style: TextStyle(fontWeight: FontWeight.w800, color: deleted ? context.muted : Brand.danger)),
          ]),
          const SizedBox(height: 2),
          Text(
            [
              '${due['invoice_no']}',
              if (due['customer_mobile'] != null) '${due['customer_mobile']}',
              if (due['sold_at'] != null) fmtDate(due['sold_at']),
            ].join('  -  '),
            style: TextStyle(color: context.muted, fontSize: 12),
          ),
          const SizedBox(height: 4),
          Text('Total ${fmtMoney(due['total'])}  -  Received ${fmtMoney(due['paid'])}',
              style: TextStyle(color: context.muted, fontSize: 12)),
          if (collections.isNotEmpty)
            Text('${collections.length} collection${collections.length == 1 ? '' : 's'} so far',
                style: TextStyle(color: context.muted, fontSize: 12)),
          const SizedBox(height: 8),
          Row(children: [
            OutlinedButton(
              onPressed: () async {
                await Navigator.push(context, MaterialPageRoute(builder: (_) => OrderDetailScreen(order: due)));
                await onChanged();
              },
              child: const Text('Details'),
            ),
            const SizedBox(width: 8),
            if (!deleted && app.can('orders.create'))
              Expanded(
                child: FilledButton.icon(
                  onPressed: () => _collect(context),
                  icon: const Icon(Icons.payments, size: 18),
                  label: const Text('Collect'),
                ),
              ),
          ]),
        ]),
      ),
    );
  }
}
