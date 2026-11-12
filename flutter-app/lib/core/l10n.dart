import 'l10n_bn.dart';

/// Wording in the language chosen in Settings. English text is the key; Bangla comes from
/// [bnStrings] and anything not translated stays in English, so the app is never blank.
class Tr {
  const Tr._();

  /// Set from the app's language setting.
  static String language = 'en';

  static bool get bangla => language == 'bn';
}

/// Translates a fixed piece of wording. Dynamic text (names, numbers) passes through unchanged.
String tr(String text) => Tr.bangla ? (bnStrings[text] ?? text) : text;
