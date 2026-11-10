import 'package:intl/intl.dart';

/// Units of measure and how they relate: 1 kg = 1,000 g, 1 box = 12 pcs.
///
/// Plain Dart with no widgets, so purchases, recipes, wastage and transfers
/// share one set of rules. It mirrors `web-app/resources/js/lib/units.js` and
/// the server side `App\Support\StandardUnits`: every unit has a type
/// (weight, volume or count) and a factor, how many of the type's base unit
/// (g, l, pcs) it holds. Within a type units are described against one
/// reference unit (g, ml, pcs), the unit people count the small things in.
class UnitInfo {
  const UnitInfo({
    required this.id,
    required this.name,
    required this.symbol,
    required this.type,
    required this.factor,
    this.isSystem = false,
  });

  final int id;
  final String name;
  final String symbol;
  final String type;
  final double factor;
  final bool isSystem;

  /// Reads a unit from the API. Numbers may arrive as strings (`"0.001"`).
  factory UnitInfo.fromJson(Map<String, dynamic> json) {
    num number(Object? v, num fallback) =>
        v is num ? v : (num.tryParse('$v') ?? fallback);

    return UnitInfo(
      id: number(json['id'], 0).toInt(),
      name: '${json['name'] ?? ''}',
      symbol: '${json['symbol'] ?? ''}',
      type: '${json['type'] ?? ''}',
      factor: number(json['factor'], 1).toDouble(),
      isSystem: json['is_system'] == true || json['is_system'] == 1,
    );
  }

  @override
  bool operator ==(Object other) => other is UnitInfo && other.id == id;

  @override
  int get hashCode => id.hashCode;
}

/// The unit each type is described against.
const Map<String, String> referenceSymbols = {
  'weight': 'g',
  'volume': 'ml',
  'count': 'pcs',
};

/// Up to six decimals, no trailing zeros, thousands separators: `10000` is `10,000`.
String formatQuantity(num value) =>
    NumberFormat('#,##0.######', 'en_US').format(_round6(value));

double _round6(num value) => (value * 1000000).round() / 1000000;

/// The reference unit of a type (g, ml or pcs), or the smallest one when that
/// symbol is not in the list.
UnitInfo? referenceUnit(Iterable<UnitInfo> units, String type) {
  final ofType = units.where((u) => u.type == type).toList();

  if (ofType.isEmpty) {
    return null;
  }

  for (final unit in ofType) {
    if (unit.symbol == referenceSymbols[type]) {
      return unit;
    }
  }

  ofType.sort((a, b) {
    final byFactor = a.factor.compareTo(b.factor);

    return byFactor != 0 ? byFactor : a.id.compareTo(b.id);
  });

  return ofType.first;
}

/// How many reference units one of this unit holds.
double inReference(UnitInfo unit, Iterable<UnitInfo> units) {
  final reference = referenceUnit(units, unit.type);

  return reference == null ? unit.factor : unit.factor / reference.factor;
}

/// "1 box = 12 pcs", or null for the reference unit itself.
String? relationText(UnitInfo unit, Iterable<UnitInfo> units) {
  final reference = referenceUnit(units, unit.type);

  if (reference == null || reference.id == unit.id) {
    return null;
  }

  return '1 ${unit.symbol} = ${formatQuantity(inReference(unit, units))} ${reference.symbol}';
}

/// Label for a unit in a picker: "box (12 pcs)" so the size is visible at a glance.
String unitLabel(UnitInfo unit, Iterable<UnitInfo> units) {
  final reference = referenceUnit(units, unit.type);

  if (reference == null || reference.id == unit.id) {
    return unit.symbol;
  }

  return '${unit.symbol} (${formatQuantity(inReference(unit, units))} ${reference.symbol})';
}

/// The quantity in the type's base unit (g, l, pcs), which is what stock and costs are kept in.
double toBase(num qty, UnitInfo unit) => _round6(qty * unit.factor);

/// What a line adds to stock when it is entered in [unit] but the ingredient
/// is tracked in [ingredientUnit]: 10 l of an ml ingredient is 10,000 ml.
/// Returns null when there is nothing to say (same unit, no quantity, or the
/// units are not of the same type).
String? stockHint(UnitInfo? ingredientUnit, UnitInfo? unit, num qty) {
  if (ingredientUnit == null ||
      unit == null ||
      unit.id == ingredientUnit.id ||
      qty <= 0) {
    return null;
  }

  if (ingredientUnit.type != unit.type || ingredientUnit.factor == 0) {
    return null;
  }

  final inStockUnit = toBase(qty, unit) / ingredientUnit.factor;

  return '${formatQuantity(inStockUnit)} ${ingredientUnit.symbol}';
}

/// Units an ingredient may be entered in: every unit of its type, smallest first.
List<UnitInfo> unitsForType(Iterable<UnitInfo> units, String type) {
  final list = units.where((u) => u.type == type).toList();

  list.sort((a, b) {
    final byFactor = a.factor.compareTo(b.factor);

    return byFactor != 0 ? byFactor : a.id.compareTo(b.id);
  });

  return list;
}
