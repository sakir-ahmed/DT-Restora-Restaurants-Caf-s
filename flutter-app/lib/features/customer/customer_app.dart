import 'dart:async';

import 'package:flutter/material.dart' hide Text;
import 'package:url_launcher/url_launcher.dart';

import '../../core/api_client.dart';
import '../../core/app_state.dart';
import '../../core/l10n.dart';
import '../../core/theme.dart';
import '../../ui/food_image.dart';
import '../../ui/tr_text.dart';
import '../../ui/widgets.dart';

/// The customer side of the app: sign in with a mobile number and a code, browse the menu of a branch,
/// order for takeaway or delivery, pay online, follow the order, and keep addresses and points. It uses the
/// customer part of the API with its own sign-in token, separate from any staff sign-in.
class CustomerSession extends ChangeNotifier {
  CustomerSession(AppState staff) : api = ApiClient(tokenKey: 'customer_token')..baseUrl = staff.api.baseUrl;

  final ApiClient api;
  Map<String, dynamic>? me;
  Map<String, dynamic>? branch;
  final List<CartLine> cart = [];

  bool get signedIn => api.hasToken && me != null;

  Future<void> restore() async {
    await api.restore();
    if (api.hasToken) {
      try {
        me = Map<String, dynamic>.from(await api.get('/customer/me') as Map);
      } on ApiException {
        me = null;
      }
    }
    notifyListeners();
  }

  Future<void> signOut() async {
    await api.clearToken();
    me = null;
    cart.clear();
    notifyListeners();
  }

  double get subtotal => cart.fold(0, (s, l) => s + l.total);
  int get count => cart.fold(0, (s, l) => s + l.quantity);

  void add(CartLine line) {
    final same = cart.where((l) => l.key == line.key);
    if (same.isNotEmpty) {
      same.first.quantity += line.quantity;
    } else {
      cart.add(line);
    }
    notifyListeners();
  }

  void changed() => notifyListeners();
}

class CartLine {
  CartLine({required this.item, this.variant, this.addOns = const [], this.quantity = 1});

  final Map<String, dynamic> item;
  final Map<String, dynamic>? variant;
  final List<Map<String, dynamic>> addOns;
  int quantity;

  String get key => '${item['id']}|${variant?['id']}|${addOns.map((a) => a['id']).join(',')}';
  double get unit => toNum(variant?['price'] ?? item['base_price']) + addOns.fold<double>(0, (s, a) => s + toNum(a['price']));
  double get total => unit * quantity;

  Map<String, dynamic> toJson() => {
        'menu_item_id': item['id'],
        if (variant != null) 'variant_id': variant!['id'],
        'quantity': quantity,
        if (addOns.isNotEmpty) 'add_ons': [for (final a in addOns) {'id': a['id'], 'qty': 1}],
      };
}

class CustomerApp extends StatefulWidget {
  const CustomerApp({super.key});

  @override
  State<CustomerApp> createState() => _CustomerAppState();
}

class _CustomerAppState extends State<CustomerApp> {
  CustomerSession? _session;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final s = CustomerSession(AppScope.read(context));
      await s.restore();
      if (mounted) {
        setState(() => _session = s);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final s = _session;
    if (s == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    return ListenableBuilder(
      listenable: s,
      builder: (context, _) => s.signedIn ? _CustomerHome(session: s) : _CustomerLogin(session: s),
    );
  }
}

class _CustomerLogin extends StatefulWidget {
  const _CustomerLogin({required this.session});

  final CustomerSession session;

  @override
  State<_CustomerLogin> createState() => _CustomerLoginState();
}

class _CustomerLoginState extends State<_CustomerLogin> {
  final _mobile = TextEditingController();
  final _code = TextEditingController();
  bool _sent = false;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _mobile.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _go() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    final api = widget.session.api;
    try {
      if (!_sent) {
        await api.post('/auth/otp/send', body: {'mobile': _mobile.text.trim()});
        setState(() => _sent = true);
      } else {
        final res = await api.post('/auth/otp/verify', body: {'mobile': _mobile.text.trim(), 'code': _code.text.trim()}) as Map;
        await api.saveToken('${res['token']}');
        await widget.session.restore();
      }
    } on ApiException catch (e) {
      setState(() => _error = e.fullMessage);
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Order food')),
      body: ListView(padding: const EdgeInsets.all(24), children: [
        const Text('Sign in with your mobile number. We send you a code.', style: TextStyle(fontSize: 15)),
        const SizedBox(height: 20),
        TextField(
          controller: _mobile,
          enabled: !_sent,
          keyboardType: TextInputType.phone,
          decoration: InputDecoration(labelText: tr('Mobile number'), hintText: '01XXXXXXXXX'),
        ),
        if (_sent) ...[
          const SizedBox(height: 12),
          TextField(
            controller: _code,
            keyboardType: TextInputType.number,
            maxLength: 6,
            decoration: InputDecoration(labelText: tr('6 digit code')),
          ),
        ],
        if (_error != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
        const SizedBox(height: 16),
        FilledButton(onPressed: _busy ? null : _go, child: Text(_sent ? 'Verify and continue' : 'Send code')),
        if (_sent) TextButton(onPressed: () => setState(() => _sent = false), child: const Text('Use another number')),
      ]),
    );
  }
}

class _CustomerHome extends StatefulWidget {
  const _CustomerHome({required this.session});

  final CustomerSession session;

  @override
  State<_CustomerHome> createState() => _CustomerHomeState();
}

class _CustomerHomeState extends State<_CustomerHome> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    final s = widget.session;
    final pages = [_CustomerMenu(session: s, onCart: () => setState(() => _tab = 1)), _CustomerCart(session: s, onPlaced: () => setState(() => _tab = 2)), _CustomerOrders(session: s), _CustomerAccount(session: s)];
    return Scaffold(
      body: IndexedStack(index: _tab, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: [
          NavigationDestination(icon: const Icon(Icons.restaurant_menu), label: tr('Menu')),
          NavigationDestination(
            icon: Badge(isLabelVisible: s.count > 0, label: Text('${s.count}'), child: const Icon(Icons.shopping_basket_outlined)),
            label: tr('Basket'),
          ),
          NavigationDestination(icon: const Icon(Icons.receipt_long), label: tr('Orders')),
          NavigationDestination(icon: const Icon(Icons.person_outline), label: tr('Account')),
        ],
      ),
    );
  }
}

class _CustomerMenu extends StatefulWidget {
  const _CustomerMenu({required this.session, required this.onCart});

  final CustomerSession session;
  final VoidCallback onCart;

  @override
  State<_CustomerMenu> createState() => _CustomerMenuState();
}

class _CustomerMenuState extends State<_CustomerMenu> {
  List<Map<String, dynamic>> _branches = const [];
  List<Map<String, dynamic>> _categories = const [];
  bool _loading = true;
  String? _error;
  String _search = '';

  @override
  void initState() {
    super.initState();
    _loadBranches();
  }

  Future<void> _loadBranches() async {
    final s = widget.session;
    try {
      _branches = asRows(await s.api.get('/customer/branches'));
      s.branch ??= _branches.firstOrNull;
      await _loadMenu();
    } on ApiException catch (e) {
      setState(() {
        _error = e.fullMessage;
        _loading = false;
      });
    }
  }

  Future<void> _loadMenu() async {
    final s = widget.session;
    if (s.branch == null) {
      setState(() => _loading = false);
      return;
    }
    setState(() => _loading = true);
    try {
      final cats = asRows(await s.api.get('/customer/branches/${s.branch!['id']}/menu'));
      setState(() {
        _categories = cats.where((c) => asRows(c['items']).isNotEmpty).toList();
        _loading = false;
        _error = null;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.fullMessage;
        _loading = false;
      });
    }
  }

  Future<void> _add(Map<String, dynamic> item) async {
    final app = AppScope.read(context);
    final s = widget.session;
    final variants = asRows(item['variants']);
    final addOns = asRows(item['add_ons']).where((a) => a['is_active'] != false).toList();
    Map<String, dynamic>? variant = variants.length == 1 ? variants.first : null;
    final picked = <int, Map<String, dynamic>>{};
    if (variants.length > 1 || addOns.isNotEmpty) {
      variant = variants.length > 1 ? (variants.where((v) => v['is_default'] == true).firstOrNull ?? variants.first) : variant;
      final go = await showForm<bool>(
        context,
        title: localized(app, item),
        builder: (ctx) => StatefulBuilder(
          builder: (ctx, set) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            if (variants.length > 1) ...[
              Wrap(spacing: 6, runSpacing: 6, children: [
                for (final v in variants)
                  ChoiceChip(label: Text('${localized(app, v)}  ${fmtMoney(v['price'])}'), selected: variant?['id'] == v['id'], onSelected: (_) => set(() => variant = v)),
              ]),
              const SizedBox(height: 12),
            ],
            if (addOns.isNotEmpty)
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
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Add to basket')),
          ]),
        ),
      );
      if (go != true) {
        return;
      }
    }
    s.add(CartLine(item: item, variant: variant, addOns: picked.values.toList()));
    if (mounted) {
      snack(context, 'Added to the basket.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final s = widget.session;
    final term = _search.trim().toLowerCase();
    return Scaffold(
      appBar: AppBar(
        title: const Text('Menu'),
        actions: [
          if (_branches.length > 1)
            PopupMenuButton<Map<String, dynamic>>(
              icon: const Icon(Icons.storefront),
              tooltip: tr('Choose a branch'),
              onSelected: (b) {
                if (s.branch?['id'] != b['id']) {
                  s.cart.clear();
                  s.branch = b;
                  s.changed();
                  _loadMenu();
                }
              },
              itemBuilder: (_) => [for (final b in _branches) PopupMenuItem(value: b, child: Text('${b['name']}'))],
            ),
        ],
      ),
      floatingActionButton: s.count > 0 ? FloatingActionButton.extended(onPressed: widget.onCart, icon: const Icon(Icons.shopping_basket), label: Text('${s.count} - ${fmtMoney(s.subtotal)}')) : null,
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!)))
              : ListView(padding: const EdgeInsets.fromLTRB(12, 12, 12, 96), children: [
                  if (s.branch != null) Text('${s.branch!['name']}', style: TextStyle(color: context.muted)),
                  const SizedBox(height: 6),
                  TextField(
                    onChanged: (v) => setState(() => _search = v),
                    decoration: InputDecoration(hintText: tr('Search food and drinks'), prefixIcon: const Icon(Icons.search), isDense: true),
                  ),
                  for (final c in _categories) ...[
                    if (asRows(c['items']).any((i) => term.isEmpty || '${i['name']}'.toLowerCase().contains(term))) SectionTitle(localized(app, c)),
                    for (final i in asRows(c['items']).where((i) => term.isEmpty || '${i['name']}'.toLowerCase().contains(term)))
                      Card(
                        child: ListTile(
                          leading: ClipRRect(borderRadius: BorderRadius.circular(8), child: SizedBox(width: 52, height: 52, child: FoodImage(url: foodImageUrl(app, i), iconSize: 20))),
                          title: Text(localized(app, i)),
                          subtitle: Text(asRows(i['variants']).length > 1 ? 'From ${fmtMoney(asRows(i['variants']).map((v) => toNum(v['price'])).reduce((a, b) => a < b ? a : b))}' : fmtMoney(asRows(i['variants']).isEmpty ? i['base_price'] : asRows(i['variants']).first['price'])),
                          trailing: IconButton.filledTonal(onPressed: () => _add(i), icon: const Icon(Icons.add)),
                        ),
                      ),
                  ],
                ]),
    );
  }
}

class _CustomerCart extends StatefulWidget {
  const _CustomerCart({required this.session, required this.onPlaced});

  final CustomerSession session;
  final VoidCallback onPlaced;

  @override
  State<_CustomerCart> createState() => _CustomerCartState();
}

class _CustomerCartState extends State<_CustomerCart> {
  String _type = 'takeaway';
  List<Map<String, dynamic>> _zones = const [];
  List<Map<String, dynamic>> _addresses = const [];
  int? _zone;
  final _address = TextEditingController();
  final _coupon = TextEditingController();
  double _discount = 0;
  bool _busy = false;
  int? _loadedBranch;

  @override
  void dispose() {
    _address.dispose();
    _coupon.dispose();
    super.dispose();
  }

  Future<void> _loadZones() async {
    final s = widget.session;
    final id = (s.branch?['id'] as num?)?.toInt();
    if (id == null || id == _loadedBranch) {
      return;
    }
    _loadedBranch = id;
    try {
      final zones = asRows(await s.api.get('/customer/branches/$id/delivery-zones'));
      final addresses = asRows(await s.api.get('/customer/addresses'));
      if (mounted) {
        setState(() {
          _zones = zones;
          _addresses = addresses;
        });
      }
    } on ApiException {
      // Delivery stays unavailable until the next try.
    }
  }

  Map<String, dynamic>? get _zoneRow => _zones.where((z) => z['id'] == _zone).firstOrNull;
  double get _fee => _type == 'delivery' ? toNum(_zoneRow?['fee']) : 0;

  Future<void> _applyCoupon() async {
    final s = widget.session;
    final code = _coupon.text.trim();
    if (code.isEmpty) {
      return;
    }
    final ok = await guarded(context, () async {
      final res = await s.api.post('/customer/coupons/validate', body: {'code': code, 'subtotal': s.subtotal}) as Map;
      setState(() => _discount = toNum(res['discount']));
    });
    if (!ok) {
      setState(() => _discount = 0);
    } else if (mounted) {
      snack(context, 'Coupon applied.');
    }
  }

  Future<void> _place() async {
    final s = widget.session;
    if (_type == 'delivery') {
      if (_zone == null || _address.text.trim().isEmpty) {
        snack(context, 'Choose a delivery area and enter your address.', error: true);
        return;
      }
      if (s.subtotal < toNum(_zoneRow?['min_order'])) {
        snack(context, 'The minimum order for this area is ${fmtMoney(_zoneRow?['min_order'])}.', error: true);
        return;
      }
    }
    setState(() => _busy = true);
    Map? order;
    final ok = await guarded(context, () async {
      order = await s.api.post(
        '/customer/orders',
        idempotencyKey: newClientId(),
        body: {
          'branch_id': s.branch!['id'],
          'order_type': _type,
          'items': [for (final l in s.cart) l.toJson()],
          if (_type == 'delivery') ...{'delivery_zone_id': _zone, 'delivery_address': _address.text.trim()},
          if (_coupon.text.trim().isNotEmpty && _discount > 0) 'coupon_code': _coupon.text.trim(),
        },
      ) as Map;
    });
    if (!mounted) {
      return;
    }
    setState(() => _busy = false);
    if (ok && order != null) {
      s.cart.clear();
      s.changed();
      snack(context, 'Order placed: ${order!['invoice_no']}.');
      widget.onPlaced();
      await _offerPayment(Map<String, dynamic>.from(order!));
    }
  }

  /// Cash is the default; offer an online payment right after ordering.
  Future<void> _offerPayment(Map<String, dynamic> order) async {
    final gateway = await showForm<String>(
      context,
      title: 'Pay now?',
      builder: (ctx) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('Total ${fmtMoney(order['total'])}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
        const SizedBox(height: 12),
        for (final g in const ['bkash', 'sslcommerz'])
          Padding(padding: const EdgeInsets.only(bottom: 8), child: FilledButton.tonal(onPressed: () => Navigator.pop(ctx, g), child: Text(g == 'bkash' ? 'Pay with bKash' : 'Pay by card or wallet'))),
        TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Pay cash on delivery or at the counter')),
      ]),
    );
    if (gateway == null || !mounted) {
      return;
    }
    await guarded(context, () async {
      final res = await widget.session.api.post('/customer/orders/${order['id']}/pay', idempotencyKey: newClientId(), body: {'gateway': gateway}) as Map;
      final url = '${res['redirect_url'] ?? ''}';
      if (url.isNotEmpty) {
        await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final s = widget.session;
    _loadZones();
    final total = (s.subtotal - _discount).clamp(0, double.infinity) + _fee;
    return Scaffold(
      appBar: AppBar(title: const Text('Basket')),
      body: s.cart.isEmpty
          ? const EmptyState('Your basket is empty. Add something from the menu.')
          : ListView(padding: const EdgeInsets.all(12), children: [
              for (final l in s.cart)
                Card(
                  child: ListTile(
                    title: Text(localized(app, l.item)),
                    subtitle: Text([
                      if (l.variant != null && asRows(l.item['variants']).length > 1) localized(app, l.variant!),
                      if (l.addOns.isNotEmpty) '+ ${l.addOns.map((a) => a['name']).join(', ')}',
                      fmtMoney(l.total),
                    ].join('  -  ')),
                    trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                      IconButton(
                        icon: const Icon(Icons.remove_circle_outline),
                        onPressed: () {
                          l.quantity -= 1;
                          if (l.quantity <= 0) {
                            s.cart.remove(l);
                          }
                          s.changed();
                          setState(() {});
                        },
                      ),
                      Text('${l.quantity}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      IconButton(
                        icon: const Icon(Icons.add_circle_outline),
                        onPressed: () {
                          l.quantity += 1;
                          s.changed();
                          setState(() {});
                        },
                      ),
                    ]),
                  ),
                ),
              const SizedBox(height: 8),
              SegmentedButton<String>(
                segments: [ButtonSegment(value: 'takeaway', label: Text(tr('Takeaway'))), ButtonSegment(value: 'delivery', label: Text(tr('Delivery')))],
                selected: {_type},
                onSelectionChanged: (v) => setState(() => _type = v.first),
              ),
              if (_type == 'delivery') ...[
                const SizedBox(height: 12),
                if (_zones.isEmpty)
                  Text('Delivery is not available for this branch right now.', style: TextStyle(color: context.muted))
                else ...[
                  DropdownButtonFormField<int>(
                    initialValue: _zone,
                    isExpanded: true,
                    decoration: InputDecoration(labelText: tr('Delivery area')),
                    items: [for (final z in _zones) DropdownMenuItem(value: (z['id'] as num).toInt(), child: Text('${localized(app, z)}  -  ${fmtMoney(z['fee'])}'))],
                    onChanged: (v) => setState(() => _zone = v),
                  ),
                  if (_zoneRow != null) Padding(padding: const EdgeInsets.only(top: 4), child: Text('Minimum order ${fmtMoney(_zoneRow!['min_order'])}${_zoneRow!['eta_minutes'] == null ? '' : '  -  about ${_zoneRow!['eta_minutes']} min'}', style: TextStyle(color: context.muted, fontSize: 12))),
                  const SizedBox(height: 12),
                  if (_addresses.isNotEmpty)
                    Wrap(spacing: 6, children: [for (final a in _addresses) ActionChip(label: Text('${a['label']}'), onPressed: () => setState(() => _address.text = '${a['address']}'))]),
                  field(_address, 'Delivery address', maxLines: 2),
                ],
              ],
              Row(children: [
                Expanded(child: field(_coupon, 'Coupon code')),
                const SizedBox(width: 8),
                Padding(padding: const EdgeInsets.only(bottom: 12), child: OutlinedButton(onPressed: _applyCoupon, child: const Text('Apply'))),
              ]),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(children: [
                    _row('Subtotal', fmtMoney(s.subtotal)),
                    if (_discount > 0) _row('Discount', '-${fmtMoney(_discount)}'),
                    if (_fee > 0) _row('Delivery fee', fmtMoney(_fee)),
                    const Divider(),
                    _row('Total (VAT and charges are added at checkout)', fmtMoney(total), bold: true),
                  ]),
                ),
              ),
              const SizedBox(height: 12),
              FilledButton(onPressed: _busy ? null : _place, child: Text(_busy ? 'Placing...' : 'Place order')),
            ]),
    );
  }

  Widget _row(String label, String value, {bool bold = false}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(children: [
          Expanded(child: Text(label, style: TextStyle(fontWeight: bold ? FontWeight.w800 : null))),
          Text(value, style: TextStyle(fontWeight: bold ? FontWeight.w800 : null)),
        ]),
      );
}

class _CustomerOrders extends StatelessWidget {
  const _CustomerOrders({required this.session});

  final CustomerSession session;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Orders')),
      body: FutureBuilder<dynamic>(
        key: ValueKey(session.cart.length),
        future: session.api.get('/customer/orders'),
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return Center(child: Text('${snap.error}'));
          }
          final rows = asRows((snap.data as Map)['orders']);
          if (rows.isEmpty) {
            return const EmptyState('No orders yet.');
          }
          return ListView(padding: const EdgeInsets.all(12), children: [
            for (final o in rows)
              Card(
                child: ListTile(
                  title: Text('${o['invoice_no']}  -  ${fmtMoney(o['total'])}'),
                  subtitle: Text('${titleCase('${o['order_type']}')}  -  ${fmtDate(o['created_at'])}'),
                  trailing: StatusChip('${o['status']}'),
                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _OrderTracking(session: session, order: o))),
                ),
              ),
          ]);
        },
      ),
    );
  }
}

/// One order with its live status, the rider when there is one, and cancel while it is still pending.
class _OrderTracking extends StatefulWidget {
  const _OrderTracking({required this.session, required this.order});

  final CustomerSession session;
  final Map<String, dynamic> order;

  @override
  State<_OrderTracking> createState() => _OrderTrackingState();
}

class _OrderTrackingState extends State<_OrderTracking> {
  Map<String, dynamic>? _tracking;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _load();
    _timer = Timer.periodic(const Duration(seconds: 10), (_) => _load());
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final res = await widget.session.api.get('/customer/orders/${widget.order['id']}/tracking');
      if (mounted) {
        setState(() => _tracking = Map<String, dynamic>.from(res as Map));
      }
    } on ApiException {
      // Keep the last copy on screen.
    }
  }

  Future<void> _cancel() async {
    if (!await confirm(context, 'Cancel this order?', body: 'You can only cancel while the restaurant has not started it.', action: 'Cancel order')) {
      return;
    }
    if (!mounted) {
      return;
    }
    final ok = await guarded(context, () async => widget.session.api.post('/customer/orders/${widget.order['id']}/cancel'));
    if (ok) {
      await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final o = widget.order;
    final status = '${_tracking?['status'] ?? o['status']}';
    const flow = ['pending', 'preparing', 'ready', 'served', 'paid'];
    final step = flow.indexOf(status);
    return Scaffold(
      appBar: AppBar(title: Text('${o['invoice_no']}')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Row(children: [StatusChip(status), const Spacer(), Text(fmtMoney(o['total']), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18))]),
        if (step >= 0) ...[
          const SizedBox(height: 12),
          Row(children: [
            for (var i = 0; i < flow.length; i++) ...[
              Expanded(child: Container(height: 5, decoration: BoxDecoration(color: i <= step ? context.brand : context.lineColor, borderRadius: BorderRadius.circular(3)))),
              if (i < flow.length - 1) const SizedBox(width: 4),
            ],
          ]),
        ],
        if (_tracking?['rider'] != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text('Rider: ${(_tracking!['rider'] as Map)['name']}')),
        const SizedBox(height: 12),
        Card(
          child: Column(children: [
            for (final i in asRows(o['items'])) ListTile(dense: true, title: Text(localized(app, i)), subtitle: Text('Qty ${i['quantity']}'), trailing: Text(fmtMoney(i['line_total']))),
          ]),
        ),
        if (status == 'pending') ...[
          const SizedBox(height: 12),
          OutlinedButton(style: OutlinedButton.styleFrom(foregroundColor: Brand.danger), onPressed: _cancel, child: const Text('Cancel order')),
        ],
      ]),
    );
  }
}

class _CustomerAccount extends StatefulWidget {
  const _CustomerAccount({required this.session});

  final CustomerSession session;

  @override
  State<_CustomerAccount> createState() => _CustomerAccountState();
}

class _CustomerAccountState extends State<_CustomerAccount> {
  int _refresh = 0;

  Future<void> _editName() async {
    final s = widget.session;
    final name = TextEditingController(text: '${s.me?['name'] ?? ''}');
    final go = await showForm<bool>(
      context,
      title: 'Your name',
      builder: (ctx) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        field(name, 'Name'),
        FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
      ]),
    );
    final value = name.text.trim();
    name.dispose();
    if (go != true || value.isEmpty || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      s.me = Map<String, dynamic>.from(await s.api.send('PATCH', '/customer/me', body: {'name': value}).then((r) => r.data) as Map);
    });
    if (ok) {
      s.changed();
    }
  }

  Future<void> _address({Map<String, dynamic>? address}) async {
    final s = widget.session;
    final label = TextEditingController(text: '${address?['label'] ?? ''}');
    final text = TextEditingController(text: '${address?['address'] ?? ''}');
    final go = await showForm<bool>(
      context,
      title: address == null ? 'New address' : 'Edit address',
      builder: (ctx) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        field(label, 'Label (home, office)'),
        field(text, 'Address', maxLines: 2),
        FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
      ]),
    );
    final body = {'label': label.text.trim(), 'address': text.text.trim()};
    label.dispose();
    text.dispose();
    if (go != true || !mounted) {
      return;
    }
    final ok = await guarded(context, () async {
      if (address == null) {
        await s.api.post('/customer/addresses', body: body);
      } else {
        await s.api.put('/customer/addresses/${address['id']}', body: body);
      }
    });
    if (ok) {
      setState(() => _refresh++);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = widget.session;
    return Scaffold(
      appBar: AppBar(title: const Text('Account')),
      body: ListView(padding: const EdgeInsets.all(12), children: [
        Card(
          child: Column(children: [
            ListTile(leading: const Icon(Icons.person_outline), title: Text('${s.me?['name'] ?? ''}'), subtitle: Text('${s.me?['mobile'] ?? ''}'), trailing: IconButton(icon: const Icon(Icons.edit_outlined), onPressed: _editName)),
            FutureBuilder<dynamic>(
              key: ValueKey('points$_refresh'),
              future: s.api.get('/customer/loyalty'),
              builder: (context, snap) => ListTile(leading: const Icon(Icons.stars, color: Brand.warning), title: const Text('Loyalty points'), trailing: Text(snap.hasData ? '${(snap.data as Map)['balance']}' : '...', style: const TextStyle(fontWeight: FontWeight.w800))),
            ),
          ]),
        ),
        const SectionTitle('Saved addresses'),
        FutureBuilder<dynamic>(
          key: ValueKey('addr$_refresh'),
          future: s.api.get('/customer/addresses'),
          builder: (context, snap) {
            final rows = asRows(snap.data);
            return Column(children: [
              for (final a in rows)
                Card(
                  child: ListTile(
                    title: Text('${a['label']}'),
                    subtitle: Text('${a['address']}'),
                    trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                      IconButton(icon: const Icon(Icons.edit_outlined, size: 20), onPressed: () => _address(address: a)),
                      IconButton(
                        icon: const Icon(Icons.delete_outline, size: 20, color: Brand.danger),
                        onPressed: () async {
                          final ok = await guarded(context, () async => s.api.delete('/customer/addresses/${a['id']}'));
                          if (ok) {
                            setState(() => _refresh++);
                          }
                        },
                      ),
                    ]),
                  ),
                ),
              OutlinedButton.icon(onPressed: () => _address(), icon: const Icon(Icons.add_location_alt_outlined), label: const Text('Add an address')),
            ]);
          },
        ),
        const SizedBox(height: 16),
        TextButton.icon(
          style: TextButton.styleFrom(foregroundColor: Brand.danger),
          onPressed: () async {
            if (await confirm(context, 'Sign out?', action: 'Sign out')) {
              await s.signOut();
            }
          },
          icon: const Icon(Icons.logout),
          label: const Text('Sign out'),
        ),
      ]),
    );
  }
}
