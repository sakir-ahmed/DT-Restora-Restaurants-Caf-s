import 'package:flutter/material.dart' as m;
import 'package:flutter/widgets.dart';

import '../core/l10n.dart';

/// A drop-in replacement for Flutter's `Text` that translates fixed wording into Bangla. Screens import
/// the Material library with `hide Text` and this file instead, so existing `Text('...')` calls (also
/// `const` ones) are translated with no other change.
class Text extends StatelessWidget {
  const Text(
    this.data, {
    super.key,
    this.style,
    this.textAlign,
    this.maxLines,
    this.overflow,
    this.softWrap,
    this.semanticsLabel,
  });

  final String data;
  final TextStyle? style;
  final TextAlign? textAlign;
  final int? maxLines;
  final TextOverflow? overflow;
  final bool? softWrap;
  final String? semanticsLabel;

  @override
  Widget build(BuildContext context) => m.Text(
        tr(data),
        style: style,
        textAlign: textAlign,
        maxLines: maxLines,
        overflow: overflow,
        softWrap: softWrap,
        semanticsLabel: semanticsLabel,
      );
}
