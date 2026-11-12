import 'package:flutter/material.dart' hide Text;

import '../../core/theme.dart';
import '../../ui/generic_view.dart';
import '../../ui/widgets.dart';
import '../reports/reports_screen.dart';
import '../../ui/tr_text.dart';
import '../../core/l10n.dart';

const statements = {
  'trial-balance': 'Trial balance',
  'profit-loss': 'Profit and loss',
  'balance-sheet': 'Balance sheet',
  'cash-flow': 'Cash flow',
  'vat-summary': 'VAT summary',
};

class AccountingScreen extends StatelessWidget {
  const AccountingScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return DefaultTabController(
      length: 7,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Accounting'),
          bottom: const TabBar(
            isScrollable: true,
            tabs: [Tab(text: 'Statements'), Tab(text: 'Journals'), Tab(text: 'Vouchers'), Tab(text: 'Payables'), Tab(text: 'Accounts'), Tab(text: 'Bank'), Tab(text: 'Year lock')],
          ),
        ),
        body: TabBarView(children: [
          ListView(padding: const EdgeInsets.all(12), children: [
            for (final e in statements.entries)
              Card(
                child: ListTile(
                  leading: Icon(Icons.account_balance, color: context.brand),
                  title: Text(e.value),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => ReportScreen(type: e.key, title: e.value, basePath: '/accounting/reports')),
                  ),
                ),
              ),
          ]),
          _Journals(canPost: app.can('journals.post')),
          _Vouchers(canManage: app.can('vouchers.manage')),
          DataView<Object?>(
            path: '/accounting/payables',
            transform: (raw) => raw,
            builder: (context, data, raw, reload) => ListView(
              padding: const EdgeInsets.all(12),
              physics: const AlwaysScrollableScrollPhysics(),
              children: [GenericView(data)],
            ),
          ),
          _Accounts(),
          _BankStatements(canPost: app.can('journals.post')),
          _FiscalYears(canLock: app.can('periods.lock')),
        ]),
      ),
    );
  }
}

class _Journals extends StatelessWidget {
  const _Journals({required this.canPost});

  final bool canPost;

  @override
  Widget build(BuildContext context) {
    final app = AppScope.read(context);
    return DataView<List<Map<String, dynamic>>>(
      path: '/accounting/journals',
      transform: asRows,
      builder: (context, rows, raw, reload) => ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(12),
        itemCount: rows.length,
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (context, i) {
          final j = rows[i];
          final lines = asRows(j['lines']);
          final total = lines.fold<double>(0, (s, l) => s + toNum(l['debit']));
          final status = '${j['status']}';
          return Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Expanded(child: Text('${j['reference'] ?? 'Journal #${j['id']}'}', style: const TextStyle(fontWeight: FontWeight.w700))),
                  StatusChip(status),
                ]),
                Text('${fmtDay(j['entry_date'])}  -  ${j['note'] ?? ''}', style: TextStyle(color: context.muted, fontSize: 12)),
                Text(fmtMoney(total), style: const TextStyle(fontWeight: FontWeight.w600)),
                if (canPost && (status == 'draft' || status == 'posted'))
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton(
                      onPressed: () async {
                        final action = status == 'draft' ? 'approve' : 'reverse';
                        if (!await confirm(context, '${titleCase(action)} this journal?', action: titleCase(action))) {
                          return;
                        }
                        if (!context.mounted) {
                          return;
                        }
                        final ok = await guarded(context, () async {
                          await app.api.post('/accounting/journals/${j['id']}/$action');
                        });
                        if (ok) {
                          await reload();
                        }
                      },
                      child: Text(status == 'draft' ? 'Approve' : 'Reverse'),
                    ),
                  ),
              ]),
            ),
          );
        },
      ),
    );
  }
}

class _Vouchers extends StatefulWidget {
  const _Vouchers({required this.canManage});

  final bool canManage;

  @override
  State<_Vouchers> createState() => _VouchersState();
}

class _VouchersState extends State<_Vouchers> {
  final _key = GlobalKey<DataViewState<List<Map<String, dynamic>>>>();

  Future<void> _create() async {
    final app = AppScope.read(context);
    final amount = TextEditingController();
    final note = TextEditingController();
    var type = 'expense';
    var payFrom = '1100';
    final go = await showForm<bool>(
      context,
      title: 'New voucher',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'expense', label: Text('Expense')),
              ButtonSegment(value: 'payment', label: Text('Supplier payment')),
            ],
            selected: {type},
            onSelectionChanged: (s) => set(() => type = s.first),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: payFrom,
            decoration: InputDecoration(labelText: tr('Pay from')),
            items: const [
              DropdownMenuItem(value: '1100', child: Text('Cash in hand')),
              DropdownMenuItem(value: '1110', child: Text('Bank')),
              DropdownMenuItem(value: '1120', child: Text('bKash')),
              DropdownMenuItem(value: '1130', child: Text('Nagad')),
            ],
            onChanged: (v) => set(() => payFrom = v!),
          ),
          const SizedBox(height: 12),
          field(amount, 'Amount', type: const TextInputType.numberWithOptions(decimal: true)),
          field(note, 'Note'),
          if (type == 'payment') Text('Supplier payments need a supplier; use the web app for those.', style: TextStyle(fontSize: 12, color: context.muted)),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save voucher')),
        ]),
      ),
    );
    final a = double.tryParse(amount.text);
    final n = note.text.trim();
    amount.dispose();
    note.dispose();
    if (go != true || a == null || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      await app.api.post('/accounting/vouchers', body: {
        'type': type,
        'amount': a,
        'entry_date': DateTime.now().toIso8601String().substring(0, 10),
        'pay_from_code': payFrom,
        if (n.isNotEmpty) 'note': n,
      });
    });
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: widget.canManage
          ? FloatingActionButton.extended(onPressed: _create, icon: const Icon(Icons.add), label: const Text('Voucher'))
          : null,
      body: DataView<List<Map<String, dynamic>>>(
        key: _key,
        path: '/accounting/vouchers',
        transform: asRows,
        builder: (context, rows, raw, reload) => ListView.separated(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 88),
          itemCount: rows.length,
          separatorBuilder: (_, _) => const SizedBox(height: 8),
          itemBuilder: (context, i) {
            final v = rows[i];
            return Card(
              child: ListTile(
                title: Text('${titleCase('${v['type']}')}  -  ${fmtMoney(v['amount'])}'),
                subtitle: Text('${fmtDay(v['entry_date'])}  ${v['note'] ?? ''}'),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _Accounts extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return DataView<List<Map<String, dynamic>>>(
      path: '/accounting/accounts',
      transform: asRows,
      builder: (context, rows, raw, reload) => ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(12),
        itemCount: rows.length,
        separatorBuilder: (_, _) => const SizedBox(height: 6),
        itemBuilder: (context, i) {
          final a = rows[i];
          return Card(
            child: ListTile(
              dense: true,
              leading: Text('${a['code']}', style: TextStyle(fontWeight: FontWeight.w700, color: context.brand)),
              title: Text('${a['name']}'),
              subtitle: Text(titleCase('${a['type']}')),
              trailing: a['is_active'] == true ? null : const StatusChip('inactive'),
            ),
          );
        },
      ),
    );
  }
}

/// Bank and wallet statement lines to match against posted journals.
class _BankStatements extends StatefulWidget {
  const _BankStatements({required this.canPost});

  final bool canPost;

  @override
  State<_BankStatements> createState() => _BankStatementsState();
}

class _BankStatementsState extends State<_BankStatements> {
  final _key = GlobalKey<DataViewState<List<Map<String, dynamic>>>>();

  Future<void> _add() async {
    final app = AppScope.read(context);
    var account = '1110';
    final amount = TextEditingController();
    final description = TextEditingController();
    final go = await showForm<bool>(
      context,
      title: 'Statement line',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          DropdownButtonFormField<String>(
            initialValue: account,
            decoration: const InputDecoration(labelText: 'Account code'),
            items: [for (final c in const ['1100', '1110', '1120', '1130']) DropdownMenuItem(value: c, child: Text(c))],
            onChanged: (v) => set(() => account = v!),
          ),
          const SizedBox(height: 12),
          field(amount, 'Amount (negative for money out)', type: const TextInputType.numberWithOptions(decimal: true, signed: true)),
          field(description, 'Description (optional)'),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
        ]),
      ),
    );
    final value = double.tryParse(amount.text.trim());
    final text = description.text.trim();
    amount.dispose();
    description.dispose();
    if (go != true || value == null || value == 0 || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      await app.api.post('/accounting/bank-statements', body: {
        'account_code': account,
        'occurred_on': DateTime.now().toIso8601String().substring(0, 10),
        'amount': value,
        if (text.isNotEmpty) 'description': text,
      });
    });
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  Future<void> _match(Map<String, dynamic> line) async {
    final app = AppScope.read(context);
    final journal = TextEditingController();
    final go = await showForm<bool>(
      context,
      title: 'Match to a journal',
      builder: (ctx) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('${fmtMoney(line['amount'])} on ${line['account_code']}', style: const TextStyle(fontWeight: FontWeight.w700)),
        const SizedBox(height: 8),
        field(journal, 'Journal number', type: TextInputType.number),
        FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Match')),
      ]),
    );
    final id = int.tryParse(journal.text.trim());
    journal.dispose();
    if (go != true || id == null || !mounted) {
      return;
    }
    final ok = await guarded(context, () async => app.api.post('/accounting/bank-statements/${line['id']}/match', body: {'journal_id': id}));
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Stack(children: [
      DataView<List<Map<String, dynamic>>>(
        key: _key,
        path: '/accounting/bank-statements',
        transform: asRows,
        builder: (context, rows, raw, reload) => ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.fromLTRB(12, 12, 12, 96), children: [
          if (rows.isEmpty) const Padding(padding: EdgeInsets.only(top: 60), child: EmptyState('No statement lines yet.')),
          for (final l in rows)
            Card(
              child: ListTile(
                dense: true,
                title: Text('${fmtMoney(l['amount'])}  -  ${l['account_code']}'),
                subtitle: Text('${fmtDay(l['occurred_on'])}${l['description'] == null ? '' : '  -  ${l['description']}'}'),
                trailing: l['matched'] == true
                    ? const StatusChip('approved')
                    : widget.canPost
                        ? TextButton(onPressed: () => _match(l), child: const Text('Match'))
                        : null,
              ),
            ),
        ]),
      ),
      if (widget.canPost)
        Positioned(right: 16, bottom: 16, child: FloatingActionButton.extended(heroTag: 'bank', onPressed: _add, icon: const Icon(Icons.add), label: const Text('Line'))),
    ]);
  }
}

/// Fiscal years and locking one so nothing can be posted into it any more.
class _FiscalYears extends StatefulWidget {
  const _FiscalYears({required this.canLock});

  final bool canLock;

  @override
  State<_FiscalYears> createState() => _FiscalYearsState();
}

class _FiscalYearsState extends State<_FiscalYears> {
  final _key = GlobalKey<DataViewState<List<Map<String, dynamic>>>>();

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return DataView<List<Map<String, dynamic>>>(
      key: _key,
      path: '/accounting/fiscal-years',
      transform: asRows,
      builder: (context, rows, raw, reload) => ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(12), children: [
        if (rows.isEmpty) const Padding(padding: EdgeInsets.only(top: 60), child: EmptyState('No fiscal years yet.')),
        for (final y in rows)
          Card(
            child: ListTile(
              leading: Icon(y['is_locked'] == true ? Icons.lock : Icons.lock_open, color: y['is_locked'] == true ? Brand.danger : Brand.success),
              title: Text('${y['name']}'),
              subtitle: Text('${fmtDay(y['starts_at'])} to ${fmtDay(y['ends_at'])}'),
              trailing: y['is_locked'] == true || !widget.canLock
                  ? null
                  : TextButton(
                      onPressed: () async {
                        if (!await confirm(context, 'Lock ${y['name']}?', body: 'Nothing can be posted into this year afterwards.', action: 'Lock year')) {
                          return;
                        }
                        if (!context.mounted) {
                          return;
                        }
                        final ok = await guarded(context, () async => app.api.post('/accounting/fiscal-years/${y['id']}/lock'));
                        if (ok) {
                          await reload();
                        }
                      },
                      child: const Text('Lock'),
                    ),
            ),
          ),
      ]),
    );
  }
}
