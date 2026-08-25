import 'package:flutter/material.dart';

abstract class AppColor {
  const AppColor();

  static const Color dialogSuccess = Color(0xff01E47A);
  static const Color dialogFailed = Color(0xffFE5151);
  static const Color transparent = Colors.transparent;

  static const Color primaryColorLighter = Color(0xFF6d539b);
  static const Color primaryColor = Color(0xFF56417A);
  static const Color primaryColorDarker = Color(0xFF3f2f59);

  /// light
  static const Color backgroundColorLight = Color(0xFFFFFFFF);
  static const Color onBackgroundColorLight = Color(0xFF121212);
  static const Color bottomNavigationBarLight = Color(0xFF56417A);
  static const Color primaryLight = Color(0xFF8475A0);
  static const Color onPrimaryLight = Color(0xFFFFFFFF);
  static const Color secondaryLight = Color(0xFF9C5087);
  static const Color shadeColor = Color(0xFFD3D3D3);
  static const Color onSecondaryLight = Color(0xFFFFFFFF);
  static const Color borderLightOnFocus = Color(0xFFFFFFFF);
  static const Color errorLight = Colors.redAccent;
  static const Color onErrorLight = Color(0xFFFFFFFF);
  static const Color surfaceLight = Color(0xFFFFFFFF);
  static const Color onSurfaceLight = Color(0xFF121212);
  static const Color successColor = Color(0xFF24B364);

  /// dark
  static const Color backgroundColorDark = Color(0xFF0E0E0E);
  static const Color secondaryBackgroundColorDark = Color(0xFF1A1919);
  static const Color bottomNavigationBarDark = Color(0xFF1A1919);
  static const Color primaryDark = Color(0xFF785BAF);
  static const Color onPrimaryDark = Color(0xFFFFFFFF);
  static const Color secondaryDark = Color(0xFF8A3A74);
  static const Color onSecondaryDark = Color(0xFFFFFFFF);
  static const Color borderDarkOnFocus = Color(0xFFFFFFFF);
  static const Color errorDark = Colors.redAccent;
  static const Color onErrorDark = Color(0xFFFFFFFF);
  static const Color surfaceDark = Color(0xFF121212);
  static const Color onSurfaceDark = Color(0xFFFFFFFF);

  // Gray
  static const Color gray100 = Color(0XFFF2F4F6);
  static const Color gray300 = Color(0XFFE0E2E4);
  static const Color gray400 = Color(0XFFBDBDBD);
  static const Color gray500 = Color(0XFF90979E);
  static const Color gray600 = Color(0XFF6D747C);
  static const Color gray700 = Color(0X147C7C7C);
  static const Color gray800 = Color(0XFF424242);
  static const Color gray900 = Color(0XFF212121);

  // Shimmer Color

  static Color shimmerBaseColor = Colors.grey[300]!;
  static Color shimmerHighlightColor = Colors.grey[100]!;
}
