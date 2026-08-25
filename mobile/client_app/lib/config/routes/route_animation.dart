import 'dart:io';

import 'package:flutter/cupertino.dart';
import 'package:go_router/go_router.dart';

CustomTransitionPage scaleTransition(page) => CustomTransitionPage<void>(
      child: page,
      transitionsBuilder: (context, animation, secondaryAnimation, child) =>
          ScaleTransition(
        scale: Tween<double>(begin: 0.0, end: 1.0).animate(animation),
        child: child,
      ),
      transitionDuration: const Duration(milliseconds: 300),
      reverseTransitionDuration: const Duration(milliseconds: 300),
    );

Page slideTransitionHorizontal(Widget page) {
  if (Platform.isIOS) {
    return CupertinoPage(child: page);
  } else {
    return CustomTransitionPage<void>(
      child: page,
      transitionsBuilder: (context, animation, secondaryAnimation, child) =>
          SlideTransition(
            position: Tween<Offset>(
              begin: const Offset(1.0, 0.0),
              end: Offset.zero,
            ).animate(animation),
            child: child,
          ),
      transitionDuration: const Duration(milliseconds: 200),
      reverseTransitionDuration: const Duration(milliseconds: 200),
    );
  }
}

CustomTransitionPage slideTransitionVertical(page) => CustomTransitionPage<void>(
  child: page,
  transitionsBuilder: (context, animation, secondaryAnimation, child) =>
      SlideTransition(
        position: Tween<Offset>(
          begin: const Offset(0.0, 1.0),
          end: Offset.zero,
        ).animate(animation),
        child: child,
      ),
  transitionDuration: const Duration(milliseconds: 400),
  reverseTransitionDuration: const Duration(milliseconds: 400),
);

CustomTransitionPage fadeTransition(page) => CustomTransitionPage<void>(
      child: page,
      transitionsBuilder: (context, animation, secondaryAnimation, child) =>
          FadeTransition(
        opacity: CurveTween(curve: Curves.ease).animate(animation),
        child: child,
      ),
      transitionDuration: const Duration(milliseconds: 200),
      reverseTransitionDuration: const Duration(milliseconds: 200),
    );
