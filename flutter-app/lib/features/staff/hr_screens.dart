import 'package:flutter/material.dart' hide Text;
import 'package:flutter/services.dart';
import 'package:printing/printing.dart';

import '../../core/api_client.dart';
import '../../core/app_state.dart';
import '../../core/sounds.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';
import '../../ui/tr_text.dart';
import '../../core/l10n.dart';

/// The day's attendance board: punch staff in and out, like the web page.
class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key});

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  Future<Cached<dynamic>>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<Cached<dynamic>> _load() =>
      AppScope.read(context).cachedGet('/branches/${AppScope.read(context).branchId}/attendance/today');

  Future<void> _reload() async {
    final next = _load();
    setState(() => _future = next);
    try {
      await next;
    } on ApiException {
      // Shown by the builder.
    }
  }

  Future<void> _punch(Map<String, dynamic> employee, String direction) async {
    final app = AppScope.read(context);
    final ok = await guarded(context, () async {
      await app.api.post('/branches/${app.branchId}/attendance/punch',
          body: {'employee_id': employee['id'], 'direction': direction});
    });
    if (ok) {
      SoundFx.play('notification');
      await _reload();
    }
  }

  String? _time(Object? v) {
    final d = v == null ? null : DateTime.tryParse('$v')?.toLocal();
    return d == null ? null : '${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
  }

  /// Record a day by hand: present with in and out times, or absent, for any past date.
  Future<void> _manual(List<Map<String, dynamic>> employees) async {
    final app = AppScope.read(context);
    int? employee;
    var status = 'present';
    var date = DateTime.now();
    var inTime = const TimeOfDay(hour: 9, minute: 0);
    TimeOfDay? outTime = const TimeOfDay(hour: 17, minute: 0);
    final go = await showForm<bool>(
      context,
      title: 'Record attendance',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          DropdownButtonFormField<int>(
            initialValue: employee,
            isExpanded: true,
            decoration: InputDecoration(labelText: tr('Employee')),
            items: [for (final e in employees) DropdownMenuItem(value: (e['id'] as num).toInt(), child: Text('${e['name']}'))],
            onChanged: (v) => set(() => employee = v),
          ),
          const SizedBox(height: 12),
          Wrap(spacing: 8, children: [
            ChoiceChip(label: const Text('Present'), selected: status == 'present', onSelected: (_) => set(() => status = 'present')),
            ChoiceChip(label: const Text('Absent'), selected: status == 'absent', onSelected: (_) => set(() => status = 'absent')),
          ]),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            icon: const Icon(Icons.event),
            label: Text(fmtDay(date.toIso8601String())),
            onPressed: () async {
              final d = await showDatePicker(context: ctx, initialDate: date, firstDate: DateTime(2024), lastDate: DateTime.now());
              if (d != null) {
                set(() => date = d);
              }
            },
          ),
          if (status == 'present') ...[
            const SizedBox(height: 8),
            Row(children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () async {
                    final t = await showTimePicker(context: ctx, initialTime: inTime);
                    if (t != null) {
                      set(() => inTime = t);
                    }
                  },
                  child: Text('In ${inTime.format(ctx)}'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton(
                  onPressed: () async {
                    final t = await showTimePicker(context: ctx, initialTime: outTime ?? inTime);
                    if (t != null) {
                      set(() => outTime = t);
                    }
                  },
                  child: Text(outTime == null ? 'Out (none)' : 'Out ${outTime!.format(ctx)}'),
                ),
              ),
            ]),
          ],
          const SizedBox(height: 12),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
        ]),
      ),
    );
    if (go != true || employee == null || !mounted) {
      return;
    }
    DateTime at(TimeOfDay t) => DateTime(date.year, date.month, date.day, t.hour, t.minute);
    final ok = await guarded(context, () async {
      await app.api.post('/branches/${app.branchId}/attendance', body: {
        'employee_id': employee,
        'work_date': date.toIso8601String().substring(0, 10),
        'status': status,
        if (status == 'present') 'clock_in': at(inTime).toIso8601String(),
        if (status == 'present' && outTime != null) 'clock_out': at(outTime!).toIso8601String(),
      });
    });
    if (ok) {
      if (mounted) {
        snack(context, 'Attendance recorded.');
      }
      await _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Attendance today'), actions: [
        IconButton(
          tooltip: 'Record by hand',
          icon: const Icon(Icons.edit_calendar_outlined),
          onPressed: () async {
            try {
              final res = await app.api.get('/branches/${app.branchId}/attendance');
              if (mounted) {
                await _manual(asRows((res as Map)['rows']).map((r) => {'id': r['employee_id'], 'name': r['employee']}).toList());
              }
            } on ApiException catch (e) {
              if (mounted) {
                snack(this.context, e.fullMessage, error: true);
              }
            }
          },
        ),
        if (app.can('attendance.manage'))
          IconButton(
            tooltip: 'Fingerprint devices',
            icon: const Icon(Icons.fingerprint),
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AttendanceDevicesScreen())),
          ),
      ]),
      body: FutureBuilder<Cached<dynamic>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Text('${snap.error}', textAlign: TextAlign.center, style: TextStyle(color: context.muted)),
                const SizedBox(height: 16),
                OutlinedButton(onPressed: _reload, child: const Text('Try again')),
              ]),
            );
          }
          final data = Map<String, dynamic>.from(snap.data!.data as Map);
          final employees = asRows(data['employees']);
          final byEmployee = {
            for (final a in asRows(data['attendance'])) a['employee_id']: a,
          };
          return RefreshIndicator(
            onRefresh: _reload,
            child: employees.isEmpty
                ? ListView(children: const [SizedBox(height: 160), EmptyState('No staff on this branch.')])
                : ListView.separated(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(12),
                    itemCount: employees.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 8),
                    itemBuilder: (context, i) {
                      final e = employees[i];
                      final a = byEmployee[e['id']];
                      final clockIn = _time(a?['clock_in']);
                      final clockOut = _time(a?['clock_out']);
                      return Card(
                        child: ListTile(
                          leading: CircleAvatar(
                            backgroundColor: context.brand.withValues(alpha: 0.1),
                            child: Text(
                              '${e['name']}'.isEmpty ? '?' : '${e['name']}'[0].toUpperCase(),
                              style: TextStyle(color: context.brand, fontWeight: FontWeight.w800),
                            ),
                          ),
                          title: Text('${e['name']}'),
                          subtitle: Text(
                            clockIn == null
                                ? '${e['designation'] ?? ''}'
                                : 'In $clockIn${clockOut != null ? '  -  Out $clockOut' : ''}',
                            style: TextStyle(
                              fontSize: 12.5,
                              color: clockIn == null ? context.muted : Brand.success,
                              fontWeight: clockIn == null ? null : FontWeight.w600,
                            ),
                          ),
                          trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                            if (clockIn == null)
                              FilledButton.tonal(
                                style: FilledButton.styleFrom(minimumSize: const Size(0, 36)),
                                onPressed: () => _punch(e, 'in'),
                                child: const Text('In'),
                              )
                            else if (clockOut == null)
                              FilledButton(
                                style: FilledButton.styleFrom(minimumSize: const Size(0, 36)),
                                onPressed: () => _punch(e, 'out'),
                                child: const Text('Out'),
                              )
                            else
                              const Icon(Icons.check_circle, color: Brand.success),
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

/// Computed pay per employee for this month, read-only, like the web
/// payroll page.
class PayrollScreen extends StatefulWidget {
  const PayrollScreen({super.key});

  @override
  State<PayrollScreen> createState() => _PayrollScreenState();
}

class _PayrollScreenState extends State<PayrollScreen> {
  Future<Cached<dynamic>>? _future;
  DateTime _month = DateTime.now();

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<Cached<dynamic>> _load() {
    final app = AppScope.read(context);
    final from = DateTime(_month.year, _month.month, 1);
    final to = DateTime.now().isBefore(DateTime(_month.year, _month.month + 1, 1))
        ? DateTime.now()
        : DateTime(_month.year, _month.month + 1, 0);
    return app.cachedGet('/branches/${app.branchId}/payroll', query: {
      'from': from.toIso8601String().substring(0, 10),
      'to': to.toIso8601String().substring(0, 10),
    });
  }

  Future<void> _reload() async {
    final next = _load();
    setState(() => _future = next);
    try {
      await next;
    } on ApiException {
      // Shown by the builder.
    }
  }

  /// Salary slip as a PDF for the chosen month, to print or share.
  Future<void> _slip(Map<String, dynamic> row) async {
    final app = AppScope.read(context);
    final from = DateTime(_month.year, _month.month, 1);
    final to = DateTime.now().isBefore(DateTime(_month.year, _month.month + 1, 1)) ? DateTime.now() : DateTime(_month.year, _month.month + 1, 0);
    final ok = await guarded(context, () async {
      final bytes = await app.api.getBytes(
          '/branches/${app.branchId}/payroll/slips/${row['id']}?from=${from.toIso8601String().substring(0, 10)}&to=${to.toIso8601String().substring(0, 10)}');
      await Printing.sharePdf(bytes: bytes, filename: 'salary-slip-${row['id']}.pdf');
    });
    if (!ok) {
      return;
    }
  }

  Future<void> _pickMonth() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _month,
      firstDate: DateTime(2024),
      lastDate: DateTime.now(),
      helpText: 'Choose a payroll month (pick any day in it)',
    );
    if (picked != null) {
      setState(() => _month = picked);
      await _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Payroll'),
        actions: [
          IconButton(
            tooltip: 'Salary advances',
            icon: const Icon(Icons.request_quote_outlined),
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SalaryAdvancesScreen(month: _month))),
          ),
          IconButton(
            tooltip: 'Choose month',
            icon: const Icon(Icons.calendar_month),
            onPressed: _pickMonth,
          ),
        ],
      ),
      body: FutureBuilder<Cached<dynamic>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Text('${snap.error}', textAlign: TextAlign.center, style: TextStyle(color: context.muted)),
                const SizedBox(height: 16),
                OutlinedButton(onPressed: _reload, child: const Text('Try again')),
              ]),
            );
          }
          final data = Map<String, dynamic>.from(snap.data!.data as Map);
          final rows = asRows(data['rows']);
          return RefreshIndicator(
            onRefresh: _reload,
            child: rows.isEmpty
                ? ListView(children: const [SizedBox(height: 160), EmptyState('No staff on this branch.')])
                : ListView.separated(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(12),
                    itemCount: rows.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 8),
                    itemBuilder: (context, i) {
                      final r = rows[i];
                      final numbers = r.entries
                          .where((e) => e.value is num && e.key != 'id')
                          .map((e) => MapEntry(titleCase(e.key), toNum(e.value)));
                      return Card(
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Row(children: [
                              Expanded(
                                child: Text('${r['name']}',
                                    style: const TextStyle(fontWeight: FontWeight.w800)),
                              ),
                              if (r['is_active'] == false) const StatusChip('inactive'),
                            ]),
                            if (r['designation'] != null)
                              Text('${r['designation']}', style: TextStyle(color: context.muted, fontSize: 12)),
                            const SizedBox(height: 6),
                            Align(
                              alignment: Alignment.centerRight,
                              child: TextButton.icon(
                                onPressed: () => _slip(r),
                                icon: const Icon(Icons.picture_as_pdf_outlined, size: 18),
                                label: const Text('Salary slip'),
                              ),
                            ),
                            Wrap(
                              spacing: 12,
                              runSpacing: 4,
                              children: [
                                for (final n in numbers)
                                  Text('${n.key}: ${n.value % 1 == 0 ? n.value.toInt() : n.value.toStringAsFixed(2)}',
                                      style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600)),
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

/// Fingerprint and card devices that post punches to the server. The device token is shown once.
class AttendanceDevicesScreen extends StatefulWidget {
  const AttendanceDevicesScreen({super.key});

  @override
  State<AttendanceDevicesScreen> createState() => _AttendanceDevicesScreenState();
}

class _AttendanceDevicesScreenState extends State<AttendanceDevicesScreen> {
  final _key = GlobalKey<DataViewState<Map<String, dynamic>>>();

  Future<void> _showToken(String name, String token) async {
    await showForm<void>(
      context,
      title: 'Token for $name',
      builder: (ctx) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('Copy it into the device now. It is not shown again.', style: TextStyle(color: ctx.muted, fontSize: 12)),
        const SizedBox(height: 10),
        SelectableText(token, style: const TextStyle(fontFamily: 'monospace')),
        const SizedBox(height: 12),
        FilledButton.icon(
          icon: const Icon(Icons.copy, size: 18),
          label: const Text('Copy token'),
          onPressed: () async {
            await Clipboard.setData(ClipboardData(text: token));
            if (ctx.mounted) {
              snack(ctx, 'Token copied.');
            }
          },
        ),
      ]),
    );
  }

  Future<void> _edit({Map<String, dynamic>? device}) async {
    final app = AppScope.read(context);
    final name = TextEditingController(text: '${device?['name'] ?? ''}');
    final serial = TextEditingController(text: '${device?['serial'] ?? ''}');
    final ip = TextEditingController(text: '${device?['ip_address'] ?? ''}');
    var active = device?['is_active'] != false;
    final go = await showForm<bool>(
      context,
      title: device == null ? 'New device' : 'Edit device',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          field(name, 'Name'),
          field(serial, 'Serial number'),
          field(ip, 'IP address (if no serial)'),
          SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Active'), value: active, onChanged: (v) => set(() => active = v)),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
        ]),
      ),
    );
    final body = {
      'name': name.text.trim(),
      'serial': serial.text.trim().isEmpty ? null : serial.text.trim(),
      'ip_address': ip.text.trim().isEmpty ? null : ip.text.trim(),
      'is_active': active,
    };
    for (final c in [name, serial, ip]) {
      c.dispose();
    }
    if (go != true || !mounted) {
      return;
    }
    Map? created;
    final ok = await guarded(context, () async {
      if (device == null) {
        created = await app.api.post('/branches/${app.branchId}/attendance/devices', body: body) as Map;
      } else {
        await app.api.put('/branches/${app.branchId}/attendance/devices/${device['id']}', body: body);
      }
    });
    if (ok) {
      await _key.currentState?.reload();
      if (created != null && mounted) {
        await _showToken('${created!['name']}', '${created!['token']}');
      }
    }
  }

  Future<void> _rotate(Map<String, dynamic> device) async {
    final app = AppScope.read(context);
    if (!await confirm(context, 'Make a new token for ${device['name']}?', body: 'The old token stops working at once.', action: 'New token')) {
      return;
    }
    if (!mounted) {
      return;
    }
    Map? res;
    final ok = await guarded(context, () async {
      res = await app.api.post('/branches/${app.branchId}/attendance/devices/${device['id']}/token') as Map;
    });
    if (ok && res != null && mounted) {
      await _showToken('${res!['name']}', '${res!['token']}');
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Attendance devices')),
      floatingActionButton: FloatingActionButton.extended(onPressed: () => _edit(), icon: const Icon(Icons.add), label: const Text('Device')),
      body: DataView<Map<String, dynamic>>(
        key: _key,
        path: '/branches/${app.branchId}/attendance',
        transform: (raw) => Map<String, dynamic>.from(raw as Map),
        builder: (context, data, raw, reload) {
          final devices = asRows(data['devices']);
          return devices.isEmpty
              ? ListView(children: const [SizedBox(height: 120), EmptyState('No devices yet.')])
              : ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.fromLTRB(12, 12, 12, 96), children: [
                  for (final d in devices)
                    Card(
                      child: ListTile(
                        leading: const Icon(Icons.fingerprint),
                        title: Text('${d['name']}${d['is_active'] == false ? '  (off)' : ''}'),
                        subtitle: Text([if (d['serial'] != null) 'SN ${d['serial']}', if (d['ip_address'] != null) '${d['ip_address']}', if (d['last_seen_at'] != null) 'Seen ${fmtDate(d['last_seen_at'])}'].join('  -  ')),
                        trailing: PopupMenuButton<String>(
                          onSelected: (v) => v == 'edit' ? _edit(device: d) : _rotate(d),
                          itemBuilder: (_) => const [
                            PopupMenuItem(value: 'edit', child: Text('Edit')),
                            PopupMenuItem(value: 'token', child: Text('New token')),
                          ],
                        ),
                      ),
                    ),
                ]);
        },
      ),
    );
  }
}

/// Advances paid to staff in a month: record one or delete one with a reason.
class SalaryAdvancesScreen extends StatefulWidget {
  const SalaryAdvancesScreen({super.key, required this.month});

  final DateTime month;

  @override
  State<SalaryAdvancesScreen> createState() => _SalaryAdvancesScreenState();
}

class _SalaryAdvancesScreenState extends State<SalaryAdvancesScreen> {
  final _key = GlobalKey<DataViewState<Map<String, dynamic>>>();

  String get _from => DateTime(widget.month.year, widget.month.month, 1).toIso8601String().substring(0, 10);
  String get _to {
    final end = DateTime(widget.month.year, widget.month.month + 1, 0);
    return (DateTime.now().isBefore(end) ? DateTime.now() : end).toIso8601String().substring(0, 10);
  }

  Future<void> _add() async {
    final app = AppScope.read(context);
    List<Map<String, dynamic>> employees;
    try {
      employees = asRows(((await app.api.get('/branches/${app.branchId}/staff')) as Map)['employees']).where((e) => e['is_active'] != false).toList();
    } on ApiException catch (e) {
      if (mounted) {
        snack(context, e.fullMessage, error: true);
      }
      return;
    }
    if (!mounted) {
      return;
    }
    int? employee;
    final amount = TextEditingController();
    final note = TextEditingController();
    final go = await showForm<bool>(
      context,
      title: 'Salary advance',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          DropdownButtonFormField<int>(
            initialValue: employee,
            isExpanded: true,
            decoration: InputDecoration(labelText: tr('Employee')),
            items: [for (final e in employees) DropdownMenuItem(value: (e['id'] as num).toInt(), child: Text('${e['name']}'))],
            onChanged: (v) => set(() => employee = v),
          ),
          const SizedBox(height: 12),
          field(amount, 'Amount', type: const TextInputType.numberWithOptions(decimal: true)),
          field(note, 'Note (optional)'),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Record advance')),
        ]),
      ),
    );
    final value = double.tryParse(amount.text.trim());
    final noteText = note.text.trim();
    amount.dispose();
    note.dispose();
    if (go != true || employee == null || value == null || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      await app.api.post('/branches/${app.branchId}/payroll/advances', body: {
        'employee_id': employee,
        'amount': value,
        'advanced_on': DateTime.now().toIso8601String().substring(0, 10),
        if (noteText.isNotEmpty) 'note': noteText,
      });
    });
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  Future<void> _delete(Map<String, dynamic> a) async {
    final app = AppScope.read(context);
    var reason = 'entry_mistake';
    final go = await showForm<bool>(
      context,
      title: 'Delete this advance',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          DropdownButtonFormField<String>(
            initialValue: reason,
            decoration: InputDecoration(labelText: tr('Reason')),
            items: [for (final r in const ['entry_mistake', 'duplicate_entry', 'wrong_amount', 'other']) DropdownMenuItem(value: r, child: Text(titleCase(r)))],
            onChanged: (v) => set(() => reason = v!),
          ),
          const SizedBox(height: 12),
          FilledButton(style: FilledButton.styleFrom(backgroundColor: Brand.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete')),
        ]),
      ),
    );
    if (go != true || !mounted) {
      return;
    }
    final ok = await guarded(context, () async => app.api.post('/branches/${app.branchId}/payroll/advances/${a['id']}/delete', body: {'reason_code': reason}));
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Salary advances')),
      floatingActionButton: FloatingActionButton.extended(onPressed: _add, icon: const Icon(Icons.add), label: const Text('Advance')),
      body: DataView<Map<String, dynamic>>(
        key: _key,
        path: '/branches/${app.branchId}/payroll/advances',
        query: {'from': _from, 'to': _to},
        transform: (raw) => Map<String, dynamic>.from(raw as Map),
        builder: (context, data, raw, reload) {
          final rows = asRows(data['advances']);
          return rows.isEmpty
              ? ListView(children: const [SizedBox(height: 120), EmptyState('No advances this month.')])
              : ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.fromLTRB(12, 12, 12, 96), children: [
                  for (final a in rows)
                    Card(
                      child: ListTile(
                        title: Text('${a['employee']}  -  ${fmtMoney(a['amount'])}'),
                        subtitle: Text('${fmtDay(a['advanced_on'])}${a['note'] == null ? '' : '  -  ${a['note']}'}${a['deleted'] == true ? '  (deleted)' : ''}'),
                        trailing: a['deleted'] == true ? null : IconButton(icon: const Icon(Icons.delete_outline, color: Brand.danger), onPressed: () => _delete(a)),
                      ),
                    ),
                ]);
        },
      ),
    );
  }
}
