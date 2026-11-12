import 'package:dt_restora/core/l10n.dart';
import 'package:dt_restora/features/orders/invoice_pdf.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  tearDown(() => Tr.language = 'en');

  test('English wording passes through unchanged', () {
    Tr.language = 'en';
    expect(tr('Save settings'), 'Save settings');
  });

  test('Bangla wording is used when known and English is kept when not', () {
    Tr.language = 'bn';
    expect(tr('Dashboard'), isNot('Dashboard'));
    expect(tr('A sentence that has no translation yet'), 'A sentence that has no translation yet');
  });

  test('invoice choice falls back to classic on 80 mm for unknown values', () {
    expect(invoiceChoice({}), (layout: 'classic', paper: '80mm'));
    expect(invoiceChoice({'pos_settings': {'invoice_layout': 'modern', 'invoice_paper': 'a4'}}), (layout: 'modern', paper: 'a4'));
    expect(invoiceChoice({'pos_settings': {'invoice_layout': 'x', 'invoice_paper': 'y'}}), (layout: 'classic', paper: '80mm'));
  });

  test('every web paper size has a page format', () {
    for (final paper in invoicePapers) {
      expect(invoiceFormat(paper).width, greaterThan(0));
    }
  });
}
