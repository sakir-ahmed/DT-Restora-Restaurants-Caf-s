import 'package:flutter/material.dart';

import '../../core/theme.dart';
import '../../ui/widgets.dart';

/// Hub for the settings the web admin keeps under Settings. Branch pages say
/// they apply to this branch only; the rest are shared by all branches.
class BusinessSettingsScreen extends StatelessWidget {
  const BusinessSettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final tiles = <(IconData, String, String, Widget)>[
      (Icons.tune, 'General', 'Website, limits, delete rules, tax number, social links', const GeneralSettingsScreen()),
      (Icons.payments_outlined, 'Payment methods', 'Shared by all branches', const PaymentMethodsScreen()),
      (Icons.point_of_sale, 'POS and billing', 'This branch: tax, buttons, edit and delete', const BranchPosSettingsScreen()),
      (Icons.receipt_long, 'Invoice print', 'This branch: style and paper size', const InvoicePrintScreen()),
      (Icons.delivery_dining, 'Delivery areas', 'This branch: areas, fees and the delivery switch', const DeliveryAreasScreen()),
      (Icons.sms_outlined, 'SMS provider', 'Gateway and token for text messages', const SmsProviderScreen()),
      (Icons.message_outlined, 'SMS messages', 'Switch each message on or off and edit its wording', const SmsMessagesScreen()),
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(padding: const EdgeInsets.all(12), children: [
        Text('Branch: ${(app.bootstrap?['branch'] as Map?)?['name'] ?? ''}', style: TextStyle(color: context.muted, fontSize: 12)),
        const SizedBox(height: 8),
        for (final t in tiles)
          Card(
            child: ListTile(
              leading: Icon(t.$1, color: context.brand),
              title: Text(t.$2),
              subtitle: Text(t.$3, style: const TextStyle(fontSize: 12)),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => t.$4)),
            ),
          ),
      ]),
    );
  }
}

/// Loads a JSON map from [path] and hands it to [builder]; [reload] fetches it again.
class _MapLoader extends StatelessWidget {
  const _MapLoader({required this.path, required this.builder});

  final String path;
  final Widget Function(BuildContext context, Map<String, dynamic> data, Future<void> Function() reload) builder;

  @override
  Widget build(BuildContext context) => DataView<Map<String, dynamic>>(
        path: path,
        transform: (raw) => Map<String, dynamic>.from(raw as Map),
        builder: (context, data, raw, reload) => builder(context, data, reload),
      );
}

String _s(Object? v) => v == null ? '' : '$v';
bool _b(Object? v) => v == true || v == 1 || v == '1';

class GeneralSettingsScreen extends StatelessWidget {
  const GeneralSettingsScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('General settings')),
        body: _MapLoader(path: '/settings', builder: (c, data, reload) => _GeneralForm(general: Map<String, dynamic>.from(data['general'] as Map), onSaved: reload)),
      );
}

class _GeneralForm extends StatefulWidget {
  const _GeneralForm({required this.general, required this.onSaved});

  final Map<String, dynamic> general;
  final Future<void> Function() onSaved;

  @override
  State<_GeneralForm> createState() => _GeneralFormState();
}

class _GeneralFormState extends State<_GeneralForm> {
  late final Map<String, dynamic> _g = Map.of(widget.general);
  late final Map<String, TextEditingController> _text = {
    for (final k in ['discount_limit_percent', 'wastage_spike_multiplier', 'pos_pin_minutes', 'delete_confirm_delay_seconds', 'tax_registration_no', 'established_year', 'social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok', 'social_whatsapp'])
      k: TextEditingController(text: _s(widget.general[k])),
  };
  bool _busy = false;

  @override
  void dispose() {
    for (final c in _text.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    final app = AppScope.read(context);
    setState(() => _busy = true);
    String? t(String k) => _text[k]!.text.trim().isEmpty ? null : _text[k]!.text.trim();
    final ok = await guarded(context, () async {
      await app.api.put('/settings', body: {
        'discount_limit_percent': double.tryParse(_text['discount_limit_percent']!.text) ?? 0,
        'wastage_spike_multiplier': double.tryParse(_text['wastage_spike_multiplier']!.text) ?? 2,
        'pos_pin_minutes': int.tryParse(_text['pos_pin_minutes']!.text) ?? 5,
        'delete_confirm_delay_seconds': int.tryParse(_text['delete_confirm_delay_seconds']!.text) ?? 5,
        'pos_lock_enabled': _b(_g['pos_lock_enabled']),
        'delete_confirm_delay_enabled': _b(_g['delete_confirm_delay_enabled']),
        'show_deleted_items': _b(_g['show_deleted_items']),
        'delivery_otp_enabled': _b(_g['delivery_otp_enabled']),
        'website_enabled': _b(_g['website_enabled']),
        'tax_registration_no': t('tax_registration_no'),
        'established_year': int.tryParse(_text['established_year']!.text),
        for (final k in ['social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok', 'social_whatsapp']) k: t(k),
      });
    });
    if (!mounted) {
      return;
    }
    setState(() => _busy = false);
    if (ok) {
      snack(context, 'Settings saved.');
      await widget.onSaved();
    }
  }

  Widget _sw(String key, String title, {String? sub}) => SwitchListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(title),
        subtitle: sub == null ? null : Text(sub, style: const TextStyle(fontSize: 12)),
        value: _b(_g[key]),
        onChanged: (v) => setState(() => _g[key] = v),
      );

  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.all(16), children: [
        Text('These apply to every branch.', style: TextStyle(color: context.muted, fontSize: 12)),
        const SizedBox(height: 12),
        _sw('website_enabled', 'Public website on', sub: 'Turn the public website off or on.'),
        const SectionTitle('Limits'),
        field(_text['discount_limit_percent']!, 'Largest discount without a manager (%)', type: const TextInputType.numberWithOptions(decimal: true)),
        field(_text['wastage_spike_multiplier']!, 'Wastage alert multiplier', type: const TextInputType.numberWithOptions(decimal: true)),
        const SectionTitle('POS lock and delete'),
        _sw('pos_lock_enabled', 'Lock the POS when idle'),
        field(_text['pos_pin_minutes']!, 'Idle minutes before the lock', type: TextInputType.number),
        _sw('delete_confirm_delay_enabled', 'Wait before confirming a delete'),
        field(_text['delete_confirm_delay_seconds']!, 'Wait seconds', type: TextInputType.number),
        _sw('show_deleted_items', 'Show deleted orders in lists'),
        _sw('delivery_otp_enabled', 'Delivery code from the customer'),
        const SectionTitle('Business'),
        field(_text['tax_registration_no']!, 'Tax registration number'),
        field(_text['established_year']!, 'Established year', type: TextInputType.number),
        const SectionTitle('Social links'),
        field(_text['social_facebook']!, 'Facebook link', type: TextInputType.url),
        field(_text['social_instagram']!, 'Instagram link', type: TextInputType.url),
        field(_text['social_youtube']!, 'YouTube link', type: TextInputType.url),
        field(_text['social_tiktok']!, 'TikTok link', type: TextInputType.url),
        field(_text['social_whatsapp']!, 'WhatsApp link', type: TextInputType.url),
        FilledButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Saving...' : 'Save settings')),
      ]);
}

class PaymentMethodsScreen extends StatefulWidget {
  const PaymentMethodsScreen({super.key});

  @override
  State<PaymentMethodsScreen> createState() => _PaymentMethodsScreenState();
}

class _PaymentMethodsScreenState extends State<PaymentMethodsScreen> {
  final _key = GlobalKey<DataViewState<Map<String, dynamic>>>();

  Future<void> _edit({Map<String, dynamic>? method}) async {
    final app = AppScope.read(context);
    final system = method != null && _b(method['is_system']);
    final code = TextEditingController(text: _s(method?['code']));
    final name = TextEditingController(text: _s(method?['name']));
    final nameBn = TextEditingController(text: _s(method?['name_bn']));
    var type = _s(method?['type']).isEmpty ? 'wallet' : _s(method?['type']);
    var active = method == null || _b(method['is_active']);
    final go = await showForm<bool>(
      context,
      title: method == null ? 'New payment method' : 'Edit ${method['name']}',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          if (method == null) field(code, 'Code (letters, numbers, underscore)', hint: 'rocket'),
          field(name, 'Name'),
          field(nameBn, 'Name in Bangla (optional)'),
          if (!system)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Wrap(spacing: 6, children: [
                for (final t in const ['wallet', 'card', 'other'])
                  ChoiceChip(label: Text(titleCase(t)), selected: type == t, onSelected: (_) => set(() => type = t)),
              ]),
            ),
          SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Active'), value: active, onChanged: (v) => set(() => active = v)),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
        ]),
      ),
    );
    final body = {
      if (method == null) 'code': code.text.trim(),
      'name': name.text.trim(),
      'name_bn': nameBn.text.trim().isEmpty ? null : nameBn.text.trim(),
      if (!system) 'type': type,
      'is_active': active,
    };
    for (final c in [code, name, nameBn]) {
      c.dispose();
    }
    if (go != true || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      if (method == null) {
        await app.api.post('/settings/payment-methods', body: body);
      } else {
        await app.api.put('/settings/payment-methods/${method['id']}', body: body);
      }
    });
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  Future<void> _delete(Map<String, dynamic> m) async {
    final app = AppScope.read(context);
    if (!await confirm(context, 'Delete ${m['name']}?', action: 'Delete')) {
      return;
    }
    if (!mounted) {
      return;
    }
    final ok = await guarded(context, () async => app.api.delete('/settings/payment-methods/${m['id']}'));
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Payment methods')),
        floatingActionButton: FloatingActionButton.extended(onPressed: () => _edit(), icon: const Icon(Icons.add), label: const Text('Add method')),
        body: DataView<Map<String, dynamic>>(
          key: _key,
          path: '/settings',
          transform: (raw) => Map<String, dynamic>.from(raw as Map),
          builder: (context, data, raw, reload) => ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 96),
            children: [
              Text('Each branch picks which of these its POS offers under POS and billing.', style: TextStyle(color: context.muted, fontSize: 12)),
              for (final m in asRows(data['payment_methods']))
                Card(
                  child: ListTile(
                    title: Text('${m['name']}${_b(m['is_active']) ? '' : '  (off)'}'),
                    subtitle: Text('${m['code']}  -  ${titleCase(_s(m['type']))}  -  ${m['payments_count']} payments'),
                    trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                      IconButton(icon: const Icon(Icons.edit_outlined, size: 20), onPressed: () => _edit(method: m)),
                      if (!_b(m['is_system']) && (m['payments_count'] as num? ?? 0) == 0)
                        IconButton(icon: const Icon(Icons.delete_outline, size: 20, color: Brand.danger), onPressed: () => _delete(m)),
                    ]),
                  ),
                ),
            ],
          ),
        ),
      );
}

class BranchPosSettingsScreen extends StatelessWidget {
  const BranchPosSettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('POS and billing')),
      body: _MapLoader(
        path: '/branches/${app.branchId}/settings',
        builder: (c, data, reload) => _BranchPosForm(data: data, onSaved: reload),
      ),
    );
  }
}

class _BranchPosForm extends StatefulWidget {
  const _BranchPosForm({required this.data, required this.onSaved});

  final Map<String, dynamic> data;
  final Future<void> Function() onSaved;

  @override
  State<_BranchPosForm> createState() => _BranchPosFormState();
}

class _BranchPosFormState extends State<_BranchPosForm> {
  late final Map<String, dynamic> _pos = _deep(widget.data['pos'] as Map);
  late final Map<String, dynamic> _flags = {
    for (final k in ['allow_negative_stock', 'vat_inclusive', 'online_ordering_enabled']) k: _b(widget.data[k]),
  };
  late final _vat = TextEditingController(text: _s(widget.data['vat_percent']));
  late final _service = TextEditingController(text: _s(widget.data['service_charge_percent']));
  late final _cash = TextEditingController(text: (_pos['cash_suggestions'] as List? ?? const []).join(', '));
  bool _busy = false;

  static Map<String, dynamic> _deep(Map m) => {
        for (final e in m.entries) '${e.key}': e.value is Map ? _deep(e.value as Map) : (e.value is List ? List.of(e.value as List) : e.value),
      };

  @override
  void dispose() {
    _vat.dispose();
    _service.dispose();
    _cash.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final app = AppScope.read(context);
    setState(() => _busy = true);
    _pos['cash_suggestions'] = _cash.text.split(RegExp(r'[,\s]+')).map(int.tryParse).whereType<int>().where((n) => n > 0).toList();
    final ok = await guarded(context, () async {
      await app.api.put('/branches/${app.branchId}/settings/branch-pos', body: {
        'vat_percent': double.tryParse(_vat.text) ?? 0,
        'service_charge_percent': double.tryParse(_service.text) ?? 0,
        ..._flags,
        'pos': _pos,
      });
    });
    if (!mounted) {
      return;
    }
    setState(() => _busy = false);
    if (ok) {
      snack(context, 'Settings saved.');
      await app.loadBootstrap();
      await widget.onSaved();
    }
  }

  static const _actionNames = {
    'send_to_kitchen': 'Send to kitchen',
    'confirm_payment': 'Confirm payment',
    'quick_save': 'Quick save',
    'due_sale': 'Due sale',
  };

  @override
  Widget build(BuildContext context) {
    final actions = Map<String, dynamic>.from(_pos['actions'] as Map);
    final methods = (_pos['payment_methods'] as List).whereType<Map>().toList();
    return ListView(padding: const EdgeInsets.all(16), children: [
      Text('These apply to this branch only.', style: TextStyle(color: context.muted, fontSize: 12)),
      const SectionTitle('Tax and charges'),
      field(_vat, 'VAT (%)', type: const TextInputType.numberWithOptions(decimal: true)),
      field(_service, 'Service charge (%)', type: const TextInputType.numberWithOptions(decimal: true)),
      SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Prices include VAT'), value: _b(_flags['vat_inclusive']), onChanged: (v) => setState(() => _flags['vat_inclusive'] = v)),
      SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Sell without stock'), subtitle: const Text('Allow negative ingredient stock'), value: _b(_flags['allow_negative_stock']), onChanged: (v) => setState(() => _flags['allow_negative_stock'] = v)),
      SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Online ordering'), value: _b(_flags['online_ordering_enabled']), onChanged: (v) => setState(() => _flags['online_ordering_enabled'] = v)),
      const SectionTitle('Order rules'),
      SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Review the bill before saving'), value: _b(_pos['review_before_save']), onChanged: (v) => setState(() => _pos['review_before_save'] = v)),
      SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Allow editing orders'), value: _b(_pos['order_edit_enabled']), onChanged: (v) => setState(() => _pos['order_edit_enabled'] = v)),
      SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Allow deleting orders'), value: _b(_pos['order_delete_enabled']), onChanged: (v) => setState(() => _pos['order_delete_enabled'] = v)),
      const SectionTitle('POS buttons'),
      for (final e in _actionNames.entries) ...[
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(e.value),
          value: _b((actions[e.key] as Map?)?['enabled']),
          onChanged: (v) => setState(() => ((_pos['actions'] as Map)[e.key] as Map)['enabled'] = v),
        ),
      ],
      const SectionTitle('Payment methods on the POS'),
      for (final m in methods)
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(_s(m['name'])),
          value: _b(m['enabled']),
          onChanged: (v) => setState(() => m['enabled'] = v),
        ),
      const SectionTitle('Cash suggestions'),
      field(_cash, 'Quick cash amounts (comma separated)', hint: '100, 500, 1000'),
      FilledButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Saving...' : 'Save settings')),
    ]);
  }
}

class InvoicePrintScreen extends StatelessWidget {
  const InvoicePrintScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Invoice print')),
      body: _MapLoader(
        path: '/settings',
        builder: (c, general, reload) => _MapLoader(
          path: '/branches/${app.branchId}/settings',
          builder: (c, branch, reload2) => _InvoiceForm(
            layouts: (general['layouts'] as List).map((e) => '$e').toList(),
            papers: (general['papers'] as List).map((e) => '$e').toList(),
            layout: _s(branch['invoice_layout']),
            paper: _s(branch['invoice_paper']),
            onSaved: reload2,
          ),
        ),
      ),
    );
  }
}

class _InvoiceForm extends StatefulWidget {
  const _InvoiceForm({required this.layouts, required this.papers, required this.layout, required this.paper, required this.onSaved});

  final List<String> layouts;
  final List<String> papers;
  final String layout;
  final String paper;
  final Future<void> Function() onSaved;

  @override
  State<_InvoiceForm> createState() => _InvoiceFormState();
}

class _InvoiceFormState extends State<_InvoiceForm> {
  late String _layout = widget.layout;
  late String _paper = widget.paper;
  bool _busy = false;

  Future<void> _save() async {
    final app = AppScope.read(context);
    setState(() => _busy = true);
    final ok = await guarded(context, () async {
      await app.api.put('/branches/${app.branchId}/settings/invoice-print', body: {'layout': _layout, 'paper': _paper});
    });
    if (!mounted) {
      return;
    }
    setState(() => _busy = false);
    if (ok) {
      snack(context, 'Invoice settings saved.');
      await app.loadBootstrap();
      await widget.onSaved();
    }
  }

  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.all(16), children: [
        Text('This applies to this branch only. The web invoice and the app receipt use the same choice.', style: TextStyle(color: context.muted, fontSize: 12)),
        const SectionTitle('Style'),
        Wrap(spacing: 8, runSpacing: 8, children: [
          for (final l in widget.layouts) ChoiceChip(label: Text(titleCase(l)), selected: _layout == l, onSelected: (_) => setState(() => _layout = l)),
        ]),
        const SectionTitle('Paper size'),
        Wrap(spacing: 8, runSpacing: 8, children: [
          for (final p in widget.papers) ChoiceChip(label: Text(p.toUpperCase()), selected: _paper == p, onSelected: (_) => setState(() => _paper = p)),
        ]),
        const SizedBox(height: 20),
        FilledButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Saving...' : 'Save')),
      ]);
}

class DeliveryAreasScreen extends StatefulWidget {
  const DeliveryAreasScreen({super.key});

  @override
  State<DeliveryAreasScreen> createState() => _DeliveryAreasScreenState();
}

class _DeliveryAreasScreenState extends State<DeliveryAreasScreen> {
  final _key = GlobalKey<DataViewState<Map<String, dynamic>>>();

  Future<void> _toggle(bool v) async {
    final app = AppScope.read(context);
    final ok = await guarded(context, () async => app.api.put('/branches/${app.branchId}/settings/delivery-enabled', body: {'enabled': v}));
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  Future<void> _edit({Map<String, dynamic>? zone}) async {
    final app = AppScope.read(context);
    final name = TextEditingController(text: _s(zone?['name']));
    final nameBn = TextEditingController(text: _s(zone?['name_bn']));
    final fee = TextEditingController(text: _s(zone?['fee']));
    final min = TextEditingController(text: _s(zone?['min_order']).isEmpty ? '0' : _s(zone?['min_order']));
    final eta = TextEditingController(text: _s(zone?['eta_minutes']));
    var type = _s(zone?['fee_type']).isEmpty ? 'fixed' : _s(zone?['fee_type']);
    var active = zone == null || _b(zone['is_active']);
    final go = await showForm<bool>(
      context,
      title: zone == null ? 'New delivery area' : 'Edit ${zone['name']}',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          field(name, 'Area name'),
          field(nameBn, 'Name in Bangla (optional)'),
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Wrap(spacing: 6, children: [
              for (final t in const ['fixed', 'flexible']) ChoiceChip(label: Text(titleCase(t)), selected: type == t, onSelected: (_) => set(() => type = t)),
            ]),
          ),
          field(fee, 'Delivery fee', type: const TextInputType.numberWithOptions(decimal: true)),
          field(min, 'Minimum order', type: const TextInputType.numberWithOptions(decimal: true)),
          field(eta, 'Time in minutes (optional)', type: TextInputType.number),
          SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Active'), value: active, onChanged: (v) => set(() => active = v)),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
        ]),
      ),
    );
    final body = {
      'name': name.text.trim(),
      'name_bn': nameBn.text.trim().isEmpty ? null : nameBn.text.trim(),
      'fee': double.tryParse(fee.text) ?? 0,
      'fee_type': type,
      'min_order': double.tryParse(min.text) ?? 0,
      'eta_minutes': int.tryParse(eta.text),
      'is_active': active,
    };
    for (final c in [name, nameBn, fee, min, eta]) {
      c.dispose();
    }
    if (go != true || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      if (zone == null) {
        await app.api.post('/branches/${app.branchId}/settings/delivery-zones', body: body);
      } else {
        await app.api.put('/branches/${app.branchId}/settings/delivery-zones/${zone['id']}', body: body);
      }
    });
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  Future<void> _delete(Map<String, dynamic> z) async {
    final app = AppScope.read(context);
    if (!await confirm(context, 'Delete ${z['name']}?', action: 'Delete')) {
      return;
    }
    if (!mounted) {
      return;
    }
    final ok = await guarded(context, () async => app.api.delete('/branches/${app.branchId}/settings/delivery-zones/${z['id']}'));
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Delivery areas')),
      floatingActionButton: FloatingActionButton.extended(onPressed: () => _edit(), icon: const Icon(Icons.add), label: const Text('Add area')),
      body: DataView<Map<String, dynamic>>(
        key: _key,
        path: '/branches/${app.branchId}/settings',
        transform: (raw) => Map<String, dynamic>.from(raw as Map),
        builder: (context, data, raw, reload) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 96),
          children: [
            Text('This applies to this branch only. Website orders can only be delivered to these areas.', style: TextStyle(color: context.muted, fontSize: 12)),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Website delivery'),
              value: _b(data['online_delivery_enabled']),
              onChanged: _toggle,
            ),
            for (final z in asRows(data['delivery_zones']))
              Card(
                child: ListTile(
                  title: Text('${z['name']}${_b(z['is_active']) ? '' : '  (off)'}'),
                  subtitle: Text('Fee ${fmtMoney(z['fee'])} (${_s(z['fee_type'])})  -  Min ${fmtMoney(z['min_order'])}${z['eta_minutes'] == null ? '' : '  -  ${z['eta_minutes']} min'}'),
                  trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                    IconButton(icon: const Icon(Icons.edit_outlined, size: 20), onPressed: () => _edit(zone: z)),
                    IconButton(icon: const Icon(Icons.delete_outline, size: 20, color: Brand.danger), onPressed: () => _delete(z)),
                  ]),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class SmsProviderScreen extends StatefulWidget {
  const SmsProviderScreen({super.key});

  @override
  State<SmsProviderScreen> createState() => _SmsProviderScreenState();
}

class _SmsProviderScreenState extends State<SmsProviderScreen> {
  final _key = GlobalKey<DataViewState<Map<String, dynamic>>>();
  String? _provider;
  final _values = <String, TextEditingController>{};
  final _testMobile = TextEditingController();

  @override
  void dispose() {
    for (final c in _values.values) {
      c.dispose();
    }
    _testMobile.dispose();
    super.dispose();
  }

  Future<void> _save(Map<String, dynamic> provider) async {
    final app = AppScope.read(context);
    final fields = <String, String>{
      for (final f in asRows(provider['fields'])) '${f['key']}': _values['${provider['key']}.${f['key']}']?.text ?? '',
    };
    final ok = await guarded(context, () async => app.api.put('/settings/sms', body: {'provider': provider['key'], 'fields': fields}));
    if (ok && mounted) {
      snack(context, 'SMS provider saved.');
      await _key.currentState?.reload();
    }
  }

  Future<void> _test() async {
    final app = AppScope.read(context);
    final mobile = _testMobile.text.trim();
    if (mobile.isEmpty) {
      snack(context, 'Enter a mobile number for the test.', error: true);
      return;
    }
    String? message;
    final ok = await guarded(context, () async {
      final res = await app.api.send('POST', '/settings/sms/test', body: {'mobile': mobile});
      message = res.message;
    });
    if (ok && mounted) {
      snack(context, message ?? 'Test message sent.');
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('SMS provider')),
        body: DataView<Map<String, dynamic>>(
          key: _key,
          path: '/settings',
          transform: (raw) => Map<String, dynamic>.from((raw as Map)['sms'] as Map),
          builder: (context, sms, raw, reload) {
            final providers = asRows(sms['providers']);
            _provider ??= '${sms['provider']}';
            final current = providers.firstWhere((p) => '${p['key']}' == _provider, orElse: () => providers.first);
            return ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(16), children: [
              Text('Secrets are never shown. Leave a secret blank to keep the saved one.', style: TextStyle(color: context.muted, fontSize: 12)),
              if (_b(sms['demo']))
                Padding(padding: const EdgeInsets.only(top: 8), child: Text('Demo mode: messages are logged but not sent.', style: TextStyle(color: Brand.warning, fontSize: 12))),
              const SizedBox(height: 12),
              Wrap(spacing: 8, runSpacing: 8, children: [
                for (final p in providers)
                  ChoiceChip(
                    label: Text('${p['name']}${_b(p['configured']) ? '' : ' (not set)'}'),
                    selected: _provider == '${p['key']}',
                    onSelected: (_) => setState(() => _provider = '${p['key']}'),
                  ),
              ]),
              const SizedBox(height: 16),
              for (final f in asRows(current['fields']))
                field(
                  _values.putIfAbsent('${current['key']}.${f['key']}', () => TextEditingController(text: _s(f['value']))),
                  '${titleCase(_s(f['key']))}${_b(f['required']) ? ' *' : ''}${_b(f['secret']) && _b(f['saved']) ? ' (saved)' : ''}',
                  obscure: _b(f['secret']),
                ),
              FilledButton(onPressed: () => _save(current), child: const Text('Save provider')),
              const SectionTitle('Send a test message'),
              field(_testMobile, 'Mobile number', type: TextInputType.phone),
              OutlinedButton(onPressed: _test, child: const Text('Send test')),
            ]);
          },
        ),
      );
}

class SmsMessagesScreen extends StatefulWidget {
  const SmsMessagesScreen({super.key});

  @override
  State<SmsMessagesScreen> createState() => _SmsMessagesScreenState();
}

class _SmsMessagesScreenState extends State<SmsMessagesScreen> {
  final _key = GlobalKey<DataViewState<Map<String, dynamic>>>();

  Future<void> _switch(Map<String, dynamic> feature, bool on) async {
    final app = AppScope.read(context);
    final ok = await guarded(context, () async => app.api.put('/settings/sms/features', body: {'features': {'${feature['key']}': on}}));
    if (ok) {
      await _key.currentState?.reload();
    }
  }

  Future<void> _edit(Map<String, dynamic> t) async {
    final app = AppScope.read(context);
    final custom = Map<String, dynamic>.from(t['custom'] as Map);
    final defaults = Map<String, dynamic>.from(t['defaults'] as Map);
    final en = TextEditingController(text: _s(custom['en']).isEmpty ? _s(defaults['en']) : _s(custom['en']));
    final bn = TextEditingController(text: _s(custom['bn']).isEmpty ? _s(defaults['bn']) : _s(custom['bn']));
    final vars = asRows(t['variables']).map((v) => '{${v['name']}}${_b(v['required']) ? '*' : ''}').join('  ');
    final go = await showForm<bool>(
      context,
      title: 'Wording: ${titleCase(_s(t['key']))}',
      builder: (ctx) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('Variables: $vars', style: TextStyle(color: ctx.muted, fontSize: 12)),
        const SizedBox(height: 4),
        Text('* must stay in the text.', style: TextStyle(color: ctx.muted, fontSize: 11)),
        const SizedBox(height: 10),
        field(en, 'English', maxLines: 4),
        field(bn, 'Bangla', maxLines: 4),
        FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save wording')),
      ]),
    );
    final body = {'en': en.text.trim(), 'bn': bn.text.trim()};
    en.dispose();
    bn.dispose();
    if (go != true || !mounted) {
      return;
    }
    final ok = await guarded(context, () async => app.api.put('/settings/sms/templates/${t['key']}', body: body));
    if (ok) {
      if (mounted) {
        snack(context, 'Wording saved.');
      }
      await _key.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('SMS messages')),
        body: DataView<Map<String, dynamic>>(
          key: _key,
          path: '/settings',
          transform: (raw) => Map<String, dynamic>.from((raw as Map)['sms'] as Map),
          builder: (context, sms, raw, reload) {
            final templates = {for (final t in asRows(sms['templates'])) '${t['feature']}': t};
            return ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(12), children: [
              for (final f in asRows(sms['features']))
                Card(
                  child: Column(children: [
                    SwitchListTile(
                      title: Text(titleCase(_s(f['key']))),
                      subtitle: Text(_b(f['locked']) ? 'Always on' : titleCase(_s(f['group']))),
                      value: _b(f['enabled']),
                      onChanged: _b(f['locked']) ? null : (v) => _switch(f, v),
                    ),
                    if (templates['${f['key']}'] != null)
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(onPressed: () => _edit(templates['${f['key']}']!), child: const Text('Edit wording')),
                      ),
                  ]),
                ),
            ]);
          },
        ),
      );
}

/// SMS history with filters, and sending a promotion to a customer group.
class SmsModuleScreen extends StatefulWidget {
  const SmsModuleScreen({super.key});

  @override
  State<SmsModuleScreen> createState() => _SmsModuleScreenState();
}

class _SmsModuleScreenState extends State<SmsModuleScreen> {
  String? _status;
  String _q = '';

  Future<void> _promote(Map<String, dynamic> data) async {
    final app = AppScope.read(context);
    final counts = Map<String, dynamic>.from(data['audiences'] as Map);
    final message = TextEditingController();
    var audience = 'all';
    final go = await showForm<bool>(
      context,
      title: 'Send a promotion',
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Wrap(spacing: 6, children: [
            for (final a in const ['all', 'recent', 'inactive'])
              ChoiceChip(label: Text('${titleCase(a)} (${counts[a] ?? 0})'), selected: audience == a, onSelected: (_) => set(() => audience = a)),
          ]),
          const SizedBox(height: 12),
          field(message, 'Message (max 320 characters)', maxLines: 4),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Send')),
        ]),
      ),
    );
    final text = message.text.trim();
    message.dispose();
    if (go != true || text.isEmpty || !mounted) {
      return;
    }
    if (!await confirm(context, 'Send to ${counts[audience] ?? 0} customers?', body: 'This cannot be undone.', action: 'Send')) {
      return;
    }
    if (!mounted) {
      return;
    }
    String? msg;
    final ok = await guarded(context, () async {
      final res = await app.api.send('POST', '/sms/promotion', body: {'message': text, 'audience': audience});
      msg = res.message;
    });
    if (ok && mounted) {
      snack(context, msg ?? 'Promotion queued.');
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('SMS')),
        body: DataView<Map<String, dynamic>>(
          key: ValueKey('$_status|$_q'),
          path: '/sms/history',
          query: {'per_page': 50, if (_status != null) 'status': _status, if (_q.isNotEmpty) 'q': _q},
          transform: (raw) => Map<String, dynamic>.from(raw as Map),
          builder: (context, data, raw, reload) {
            final rows = asRows(data['history']);
            return ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.fromLTRB(12, 12, 12, 24), children: [
              if (_b(data['promotion_enabled']))
                FilledButton.icon(onPressed: () => _promote(data), icon: const Icon(Icons.campaign_outlined), label: const Text('Send a promotion')),
              const SizedBox(height: 8),
              TextField(
                keyboardType: TextInputType.phone,
                onSubmitted: (v) => setState(() => _q = v.trim()),
                decoration: const InputDecoration(hintText: 'Find by mobile number', prefixIcon: Icon(Icons.search), isDense: true),
              ),
              const SizedBox(height: 8),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(children: [
                  for (final s in const [null, 'sent', 'failed', 'skipped', 'demo'])
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(label: Text(s == null ? 'All' : titleCase(s)), selected: _status == s, onSelected: (_) => setState(() => _status = s)),
                    ),
                ]),
              ),
              if (rows.isEmpty) const Padding(padding: EdgeInsets.only(top: 60), child: EmptyState('No messages yet.')),
              for (final r in rows)
                Card(
                  child: ListTile(
                    dense: true,
                    title: Text('${r['mobile']}  -  ${titleCase(_s(r['feature']))}'),
                    subtitle: Text('${r['message']}\n${fmtDate(r['at'])}${r['by'] == null ? '' : '  -  ${r['by']}'}'),
                    isThreeLine: true,
                    trailing: StatusChip(_s(r['status'])),
                  ),
                ),
            ]);
          },
        ),
      );
}
