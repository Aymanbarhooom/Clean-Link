import 'dart:ui';
import 'package:flutter/material.dart';

class AppThemeInfo {
  static bool _isLight = true;

  static Future<void> initialize() async {
    // if (!await SharedStorage.hasData(StorageData.isLight)) {
    //   bool systemIsLight =
    //       WidgetsBinding.instance.platformDispatcher.platformBrightness ==
    //       Brightness.light;
    //   _isLight = systemIsLight;
    //   await setTheme(_isLight);
    // } else {
    //   _isLight = await SharedStorage.get(StorageData.isLight) ?? true;
    // }
  }

  static bool get isLight {
    return _isLight;
  }

  static bool get isDark {
    return !_isLight;
  }

  static Brightness get brightness {
    return _isLight ? Brightness.light : Brightness.dark;
  }

  static Future<void> setTheme(bool isLight) async {
    // _isLight = isLight;
    // await SharedStorage.set(StorageData.isLight, isLight);
  }

  static Future<void> toggleTheme() async {
    // _isLight = !_isLight;
    // await SharedStorage.set(StorageData.isLight, _isLight);
  }
}
