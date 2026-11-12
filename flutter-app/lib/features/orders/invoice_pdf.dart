import 'dart:typed_data';

import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;

import '../../core/pdf_fonts.dart';
import '../../core/pdf_shaper.dart';
import '../../ui/widgets.dart';
import 'receipt.dart';

/// The six invoice styles and four paper sizes of the web panel, drawn with the
/// same choices (`invoice_layout`, `invoice_paper` of the branch). It mirrors
/// `App\Support\ReceiptStyle`: classic, compact, bold, modern, detailed and
/// minimal; 58 mm and 80 mm rolls, A5 and A4 sheets. Wide papers and the
/// detailed style list items as a table (item, qty, rate, amount).
const invoiceLayouts = ['classic', 'compact', 'bold', 'modern', 'detailed', 'minimal'];
const invoicePapers = ['58mm', '80mm', 'a5', 'a4'];

/// Layout and paper saved for the branch, falling back to classic on 80 mm.
({String layout, String paper}) invoiceChoice(Map<String, dynamic> branch) {
  final pos = branch['pos_settings'] is Map ? branch['pos_settings'] as Map : const {};
  final layout = '${pos['invoice_layout'] ?? 'classic'}';
  final paper = '${pos['invoice_paper'] ?? '80mm'}';
  return (
    layout: invoiceLayouts.contains(layout) ? layout : 'classic',
    paper: invoicePapers.contains(paper) ? paper : '80mm',
  );
}

/// Page format for a paper size.
PdfPageFormat invoiceFormat(String paper) => switch (paper) {
      '58mm' => const PdfPageFormat(58 * PdfPageFormat.mm, double.infinity, marginAll: 3 * PdfPageFormat.mm),
      'a5' => PdfPageFormat.a5.copyWith(marginTop: 10 * PdfPageFormat.mm, marginBottom: 10 * PdfPageFormat.mm, marginLeft: 10 * PdfPageFormat.mm, marginRight: 10 * PdfPageFormat.mm),
      'a4' => PdfPageFormat.a4.copyWith(marginTop: 14 * PdfPageFormat.mm, marginBottom: 14 * PdfPageFormat.mm, marginLeft: 14 * PdfPageFormat.mm, marginRight: 14 * PdfPageFormat.mm),
      _ => const PdfPageFormat(80 * PdfPageFormat.mm, double.infinity, marginAll: 5 * PdfPageFormat.mm),
    };

bool _isWide(String paper) => paper == 'a5' || paper == 'a4';

Future<Uint8List> buildInvoicePdf(ReceiptData data, {required String layout, required String paper}) async {
  final doc = pw.Document(theme: await PdfFonts.theme());
  final format = invoiceFormat(paper);
  final textWidth = format.width - format.marginLeft - format.marginRight;
  final tabular = layout == 'detailed' || _isWide(paper);

  // Base size per paper, adjusted by style.
  var base = switch (paper) { '58mm' => 8.0, 'a5' => 10.5, 'a4' => 11.5, _ => 9.0 };
  if (layout == 'compact') {
    base *= 0.9;
  }
  final large = base * 1.18;
  final shaper = PdfShaper();
  final branch = data.branch;
  final branchName = '${branch['name'] ?? ''}';

  await shaper.prepare(branchName, large, maxWidth: textWidth);
  await shaper.prepare('${branch['address'] ?? ''}', base * 0.9, maxWidth: textWidth);
  await shaper.prepare(data.footer, base * 0.9, maxWidth: textWidth);
  final itemWidth = tabular ? textWidth * 0.5 : textWidth;
  for (final i in data.items) {
    await shaper.prepare(data.name(i), base, maxWidth: itemWidth);
    for (final a in asRows(i['add_ons'])) {
      await shaper.prepare(data.addOnLabel(a), base, maxWidth: itemWidth);
    }
  }

  pw.Widget rule() => switch (layout) {
        'minimal' => pw.SizedBox(height: 8),
        'bold' => pw.Divider(thickness: 1.6, height: 8, color: PdfColors.black),
        'modern' => pw.Divider(thickness: 0.8, height: 8, color: PdfColors.grey600),
        'detailed' => pw.Divider(thickness: 0.8, height: 8, color: PdfColors.black),
        'compact' => pw.Divider(thickness: 0.4, height: 4, borderStyle: pw.BorderStyle.dashed),
        _ => pw.Divider(thickness: 0.5, height: 8, borderStyle: pw.BorderStyle.dashed),
      };

  pw.Widget pair(ReceiptRow r, {double? size}) => pw.Padding(
        padding: const pw.EdgeInsets.symmetric(vertical: 1),
        child: pw.Row(
          mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
          children: [
            shaper.text(r.left, size ?? base, bold: r.bold, maxWidth: textWidth),
            if (r.right != null)
              pw.Text(r.right!, style: pw.TextStyle(fontSize: size ?? base, fontWeight: r.bold ? pw.FontWeight.bold : pw.FontWeight.normal)),
          ],
        ),
      );

  pw.Widget grandTotal(ReceiptRow r) {
    final label = pw.Text(r.left, style: pw.TextStyle(fontSize: layout == 'bold' ? base * 1.5 : large, fontWeight: pw.FontWeight.bold, color: layout == 'modern' ? PdfColors.white : PdfColors.black));
    final value = pw.Text(r.right ?? '', style: pw.TextStyle(fontSize: layout == 'bold' ? base * 1.5 : large, fontWeight: pw.FontWeight.bold, color: layout == 'modern' ? PdfColors.white : PdfColors.black));
    final row = pw.Row(mainAxisAlignment: pw.MainAxisAlignment.spaceBetween, children: [label, value]);
    return switch (layout) {
      'modern' => pw.Container(color: PdfColors.black, padding: const pw.EdgeInsets.symmetric(horizontal: 6, vertical: 4), child: row),
      'bold' => pw.Container(
          padding: const pw.EdgeInsets.symmetric(vertical: 3),
          decoration: const pw.BoxDecoration(border: pw.Border(top: pw.BorderSide(width: 1.6), bottom: pw.BorderSide(width: 1.6))),
          child: row,
        ),
      'detailed' => pw.Container(
          padding: const pw.EdgeInsets.symmetric(vertical: 3),
          decoration: const pw.BoxDecoration(border: pw.Border(top: pw.BorderSide(width: 0.8), bottom: pw.BorderSide(width: 2.4))),
          child: row,
        ),
      'minimal' => pw.Container(
          padding: const pw.EdgeInsets.only(top: 3),
          decoration: const pw.BoxDecoration(border: pw.Border(top: pw.BorderSide(width: 0.8))),
          child: row,
        ),
      _ => pair(r, size: large),
    };
  }

  double unitPrice(Map<String, dynamic> i) {
    final qty = toNum(i['quantity']) == 0 ? 1 : toNum(i['quantity']);
    return toNum(i['unit_price'] ?? toNum(i['line_total']) / qty);
  }

  pw.Widget header() {
    final children = <pw.Widget>[
      if (layout == 'modern')
        pw.Container(
          color: PdfColors.black,
          padding: const pw.EdgeInsets.symmetric(vertical: 6, horizontal: 4),
          child: pw.Center(child: pw.Text('DT Restora', style: pw.TextStyle(fontSize: large * 1.2, fontWeight: pw.FontWeight.bold, color: PdfColors.white))),
        )
      else
        pw.Center(child: pw.Text('DT Restora', style: pw.TextStyle(fontSize: large * 1.3, fontWeight: pw.FontWeight.bold))),
      pw.Center(child: shaper.text(branchName, large, maxWidth: textWidth)),
      if (branch['address'] != null) pw.Center(child: shaper.text('${branch['address']}', base * 0.9, maxWidth: textWidth, align: pw.TextAlign.center)),
      if (branch['phone'] != null) pw.Center(child: pw.Text('Phone ${branch['phone']}', style: pw.TextStyle(fontSize: base * 0.9))),
    ];
    return pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.stretch, children: children);
  }

  pw.Widget items() {
    if (!tabular) {
      return pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.stretch, children: [
        for (final i in data.items) ...[
          shaper.text(data.name(i), base, maxWidth: textWidth),
          pair(ReceiptRow('  ${i['quantity']} x ${unitPrice(i).toStringAsFixed(2)}', toNum(i['line_total']).toStringAsFixed(2))),
          for (final a in asRows(i['add_ons'])) pair(ReceiptRow(data.addOnLabel(a), data.addOnAmount(a))),
        ],
      ]);
    }
    pw.Widget cell(pw.Widget child, double flex, {pw.Alignment align = pw.Alignment.centerRight}) => pw.Expanded(flex: (flex * 10).round(), child: pw.Align(alignment: align, child: child));
    pw.Widget head(String t, double flex, {bool left = false}) =>
        cell(pw.Text(t, style: pw.TextStyle(fontSize: base, fontWeight: pw.FontWeight.bold)), flex, align: left ? pw.Alignment.centerLeft : pw.Alignment.centerRight);
    final border = layout == 'detailed'
        ? const pw.BoxDecoration(border: pw.Border(bottom: pw.BorderSide(width: 0.4, color: PdfColors.grey600, style: pw.BorderStyle.dotted)))
        : null;
    return pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.stretch, children: [
      pw.Container(
        padding: const pw.EdgeInsets.symmetric(vertical: 3),
        decoration: const pw.BoxDecoration(border: pw.Border(top: pw.BorderSide(width: 0.8), bottom: pw.BorderSide(width: 0.8))),
        child: pw.Row(children: [head('Item', 5, left: true), head('Qty', 1), head('Rate', 2), head('Amount', 2)]),
      ),
      for (final i in data.items)
        pw.Container(
          padding: const pw.EdgeInsets.symmetric(vertical: 3),
          decoration: border,
          child: pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.stretch, children: [
            pw.Row(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
              cell(shaper.text(data.name(i), base, maxWidth: itemWidth), 5, align: pw.Alignment.centerLeft),
              cell(pw.Text('${i['quantity']}', style: pw.TextStyle(fontSize: base)), 1),
              cell(pw.Text(unitPrice(i).toStringAsFixed(2), style: pw.TextStyle(fontSize: base)), 2),
              cell(pw.Text(toNum(i['line_total']).toStringAsFixed(2), style: pw.TextStyle(fontSize: base)), 2),
            ]),
            for (final a in asRows(i['add_ons']))
              pw.Row(children: [
                cell(shaper.text(data.addOnLabel(a), base * 0.92, maxWidth: itemWidth), 5, align: pw.Alignment.centerLeft),
                cell(pw.SizedBox(), 1),
                cell(pw.SizedBox(), 2),
                cell(pw.Text(data.addOnAmount(a), style: pw.TextStyle(fontSize: base * 0.92)), 2),
              ]),
          ]),
        ),
    ]);
  }

  final totals = data.totalRows();
  doc.addPage(
    pw.Page(
      pageFormat: format,
      build: (context) => pw.Column(
        crossAxisAlignment: pw.CrossAxisAlignment.stretch,
        children: [
          header(),
          rule(),
          if (layout == 'modern')
            pw.Wrap(spacing: 12, runSpacing: 2, children: [for (final r in data.headerRows()) pair(r)])
          else
            ...[for (final r in data.headerRows()) pair(r)],
          rule(),
          items(),
          rule(),
          for (final r in totals.where((r) => !r.rule)) pair(r),
          for (final r in totals.where((r) => r.rule)) grandTotal(r),
          rule(),
          for (final r in data.paymentRows()) pair(r),
          rule(),
          pw.Center(child: shaper.text(data.footer, base * 0.9, maxWidth: textWidth, align: pw.TextAlign.center)),
        ],
      ),
    ),
  );
  return doc.save();
}
