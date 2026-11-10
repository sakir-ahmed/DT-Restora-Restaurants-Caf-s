import 'package:dt_restora/core/units.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const g = UnitInfo(
    id: 1,
    name: 'Gram',
    symbol: 'g',
    type: 'weight',
    factor: 1,
    isSystem: true,
  );
  const mg = UnitInfo(
    id: 2,
    name: 'Milligram',
    symbol: 'mg',
    type: 'weight',
    factor: 0.001,
    isSystem: true,
  );
  const kg = UnitInfo(
    id: 3,
    name: 'Kilogram',
    symbol: 'kg',
    type: 'weight',
    factor: 1000,
    isSystem: true,
  );
  const l = UnitInfo(
    id: 4,
    name: 'Litre',
    symbol: 'l',
    type: 'volume',
    factor: 1,
    isSystem: true,
  );
  const ml = UnitInfo(
    id: 5,
    name: 'Millilitre',
    symbol: 'ml',
    type: 'volume',
    factor: 0.001,
    isSystem: true,
  );
  const tsp = UnitInfo(
    id: 6,
    name: 'Teaspoon',
    symbol: 'tsp',
    type: 'volume',
    factor: 0.005,
    isSystem: true,
  );
  const pcs = UnitInfo(
    id: 7,
    name: 'Piece',
    symbol: 'pcs',
    type: 'count',
    factor: 1,
    isSystem: true,
  );
  const dozen = UnitInfo(
    id: 8,
    name: 'Dozen',
    symbol: 'dozen',
    type: 'count',
    factor: 12,
    isSystem: true,
  );
  const box = UnitInfo(
    id: 9,
    name: 'Box',
    symbol: 'box',
    type: 'count',
    factor: 12,
  );
  const largeBox = UnitInfo(
    id: 10,
    name: 'Large box',
    symbol: 'lbox',
    type: 'count',
    factor: 24,
  );
  const all = [g, mg, kg, l, ml, tsp, pcs, dozen, box, largeBox];

  test(
    'every type is described against g, ml or pcs, not the smallest unit',
    () {
      expect(referenceUnit(all, 'weight'), g);
      expect(referenceUnit(all, 'volume'), ml);
      expect(referenceUnit(all, 'count'), pcs);
      expect(referenceUnit(all, 'other'), isNull);
    },
  );

  test(
    'falls back to the smallest unit when the reference symbol is missing',
    () {
      expect(referenceUnit([kg, mg], 'weight'), mg);
    },
  );

  test('relations read like the web Settings page', () {
    expect(relationText(kg, all), '1 kg = 1,000 g');
    expect(relationText(mg, all), '1 mg = 0.001 g');
    expect(relationText(l, all), '1 l = 1,000 ml');
    expect(relationText(tsp, all), '1 tsp = 5 ml');
    expect(relationText(box, all), '1 box = 12 pcs');
    expect(relationText(largeBox, all), '1 lbox = 24 pcs');
    expect(relationText(g, all), isNull);
    expect(relationText(pcs, all), isNull);
  });

  test('picker labels show the size at a glance', () {
    expect(unitLabel(box, all), 'box (12 pcs)');
    expect(unitLabel(l, all), 'l (1,000 ml)');
    expect(unitLabel(g, all), 'g');
  });

  test(
    'quantities are formatted with up to six decimals and thousands separators',
    () {
      expect(formatQuantity(10000), '10,000');
      expect(formatQuantity(0.25), '0.25');
      expect(formatQuantity(1234.5678901), '1,234.56789');
      expect(formatQuantity(0.1 + 0.2), '0.3');
    },
  );

  test('a quantity in any unit converts to the base unit', () {
    expect(toBase(10, l), 10);
    expect(toBase(10, ml), 0.01);
    expect(toBase(2.5, kg), 2500);
    expect(toBase(3, box), 36);
    expect(toBase(1, largeBox), 24);
  });

  test('buying 10 l of an ml ingredient adds 10,000 ml to stock', () {
    expect(stockHint(ml, l, 10), '10,000 ml');
    expect(stockHint(g, kg, 2.5), '2,500 g');
    expect(stockHint(pcs, box, 4), '48 pcs');
    expect(stockHint(kg, g, 500), '0.5 kg');
  });

  test(
    'no hint for the same unit, an empty quantity or a unit of another kind',
    () {
      expect(stockHint(ml, ml, 10), isNull);
      expect(stockHint(ml, l, 0), isNull);
      expect(stockHint(ml, kg, 1), isNull);
      expect(stockHint(null, l, 1), isNull);
      expect(stockHint(ml, null, 1), isNull);
    },
  );

  test(
    'an ingredient may be entered in every unit of its type, smallest first',
    () {
      expect(unitsForType(all, 'count').map((u) => u.symbol), [
        'pcs',
        'dozen',
        'box',
        'lbox',
      ]);
      expect(unitsForType(all, 'volume').map((u) => u.symbol), [
        'ml',
        'tsp',
        'l',
      ]);
    },
  );

  test('reads a unit from API JSON whose numbers arrive as strings', () {
    final unit = UnitInfo.fromJson({
      'id': '9',
      'name': 'Box',
      'symbol': 'box',
      'type': 'count',
      'factor': '12.000000',
      'is_system': 0,
    });

    expect(unit.id, 9);
    expect(unit.factor, 12);
    expect(unit.isSystem, isFalse);
  });

  test('finds the unit an ingredient is tracked in from its reference row', () {
    expect(ingredientUnit({'id': 1, 'unit_id': 5}, all), ml);
    expect(ingredientUnit({'id': 1, 'unit_id': '9'}, all), box);
    expect(ingredientUnit({'id': 1, 'unit_id': 99}, all), isNull);
    expect(ingredientUnit({'id': 1}, all), isNull);
  });

  test('stock kept in grams is shown in the ingredient unit', () {
    expect(stockInUnit(2000, 1000), 2);
    expect(stockInUnit(-770, 1000), -0.77);
    expect(stockInUnit(250, 0.001), 250000);
    expect(stockInUnit(36, 12), 3);
    expect(stockInUnit(5, 1), 5);
    expect(stockInUnit(5, 0), 5);
  });
}
