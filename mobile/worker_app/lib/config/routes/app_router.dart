import 'dart:developer';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'route_animation.dart';

abstract class AppRouter {
  //////////////////////////////////////////////////////////////////////////////
  static const kOnboardingPage = '/onboarding';
  static const kLoginPage = '/login';
  static const kRegisterPage = '/register';
  static const kConnectionTimeoutPage = '/connection_timeout';
  static const kHomePage = '/home';

  static final rootNavigatorKey = GlobalKey<NavigatorState>();
  static final _rootNavigatorHome = GlobalKey<NavigatorState>(
    debugLabel: 'shellHome',
  );

  static bool _splashScreenShown = false;

  static final router = GoRouter(
    observers: [MyNavigatorObserver()],
    initialLocation: '/',
    navigatorKey: rootNavigatorKey,
    routes: [
      //   GoRoute(
      //     path: '/',
      //     pageBuilder: (context, state) {
      //       _splashScreenShown = true;
      //       return const MaterialPage(child: SplashPage());
      //     },
      //   ),
      //   GoRoute(
      //     path: kConnectionTimeoutPage,
      //     pageBuilder: (context, state) {
      //       _splashScreenShown = true;
      //       return const MaterialPage(child: CustomConnectionTimeout());
      //     },
      //   ),
      //   GoRoute(
      //     path: kOnboardingPage,
      //     pageBuilder: (context, state) {
      //       _splashScreenShown = true;
      //       return const MaterialPage(child: OnboardingPage());
      //     },
      //   ),
      //   GoRoute(
      //     path: kLoginPage,
      //     pageBuilder: (context, state) {
      //       _splashScreenShown = true;
      //       return const MaterialPage(child: LoginPage());
      //     },
      //     routes: [
      //       GoRoute(
      //         path: kForgotPasswordPage,
      //         pageBuilder: (context, state) =>
      //             slideTransitionHorizontal(const ForgotPasswordPage()),
      //         routes: [
      //           GoRoute(
      //             path: "$kResetPasswordPage/:gsm",
      //             pageBuilder: (context, state) => slideTransitionHorizontal(
      //               ResetPasswordPage(state.pathParameters['gsm'].toString()),
      //             ),
      //           ),
      //         ],
      //       ),
      //     ],
      //   ),
      //   GoRoute(
      //     path: kRegisterPage,
      //     pageBuilder: (context, state) =>
      //         slideTransitionHorizontal(const RegisterPage()),
      //     routes: [
      //       GoRoute(
      //         path: '$kVerificationAccountPage/:gsm',
      //         pageBuilder: (context, state) => slideTransitionHorizontal(
      //           VerificationCodePage(
      //             state.pathParameters['gsm']!.toString() ?? '',
      //           ),
      //         ),
      //         routes: [
      //           GoRoute(
      //             path: '$kPersonalDetailsPage/:gsmDetails',
      //             pageBuilder: (context, state) => slideTransitionHorizontal(
      //               PersonalDetailsPage(
      //                 state.pathParameters['gsmDetails']!.toString() ?? '',
      //               ),
      //             ),
      //           ),
      //         ],
      //       ),
      //     ],
      //   ),
      //   StatefulShellRoute.indexedStack(
      //     builder: (context, state, navigationShell) {
      //       return BasePage(navigationShell: navigationShell);
      //     },
      //     branches: <StatefulShellBranch>[
      //       // Branch Home
      //       StatefulShellBranch(
      //         navigatorKey: _rootNavigatorHome,
      //         routes: [
      //           //////////////////////////////////////////////////////////////////
      //           // Home page
      //           GoRoute(
      //             path: kHomePage,
      //             name: 'home',
      //             pageBuilder: (context, state) =>
      //                 slideTransitionHorizontal(HomePage(key: state.pageKey)),
      //           ),
      //         ],
      //       ),
      //     ],
      //   ),
    ],
    redirect: (context, state) async {
      debugPrint(state.fullPath);
      if (!_splashScreenShown) {
        return '/';
      }
      // if (!await SharedStorage.hasData(StorageData.isOnboarding)) {
      //   return kOnboardingPage;
      // }
      // if (!await SharedStorage.authenticated && state.fullPath == kHomePage) {
      //   return kLoginPage;
      // }
      return null;
    },
  );

  static String returnFullPath() {
    return AppRouter.router.state!.fullPath!;
  }
}

class MyNavigatorObserver extends NavigatorObserver {
  @override
  void didPush(Route<dynamic> route, Route<dynamic>? previousRoute) {
    log('did push route $route');
  }

  @override
  void didPop(Route<dynamic> route, Route<dynamic>? previousRoute) {
    log('did pop route $route');
  }
}
