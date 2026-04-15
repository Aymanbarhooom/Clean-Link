//
// import '../../../l10n/app_localizations.dart';
//
// class Validator {
//   static String? validateGsm(String? value, context) {
//     if (value!.isEmpty) {
//       return AppLocalizations.of(context)!.empty_field_error_message;
//     } else if (value.length < 10 || !value.startsWith("09")) {
//       return AppLocalizations.of(context)!.invalid_mobile_number_error_message;
//     }
//   }
//
//   static String? validatePassword(String? value, context) {
//     if (value!.isEmpty) {
//       return AppLocalizations.of(context)!.empty_field_error_message;
//     } else if (value.length < 6) {
//       return AppLocalizations.of(context)!.short_password_error_message;
//     } else if (!RegExp(Constants.passwordRegex).hasMatch(value)) {
//       return AppLocalizations.of(context)!.password_with_regex_error_message;
//     }
//   }
//
//   static String? validateConfirmPassword(
//       String? value, String password, context) {
//     if (value!.isEmpty) {
//       return AppLocalizations.of(context)!.empty_field_error_message;
//     } else if (value != password) {
//       return AppLocalizations.of(context)!.password_confirmation_error_message;
//     }
//   }
//
//   static String? validateEmpty(String value, context) {
//     if (value.isEmpty) {
//       return AppLocalizations.of(context)!.empty_field_error_message;
//     }
//   }
//
//   static String? validateCode(String value, context) {
//     if (value.length < 4) {
//       return AppLocalizations.of(context)!.empty_field_error_message;
//     }
//     return null;
//   }
// }
