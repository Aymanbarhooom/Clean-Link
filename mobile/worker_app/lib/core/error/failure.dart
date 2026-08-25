import 'package:dio/dio.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../config/routes/app_router.dart';

// Error codes for specific failure types
class ErrorCode {
  static const String connectionTimeout = 'CONNECTION_TIMEOUT';
  static const String sendTimeout = 'SEND_TIMEOUT';
  static const String receiveTimeout = 'RECEIVE_TIMEOUT';
  static const String noInternet = 'NO_INTERNET';
}

abstract class Failure extends Equatable {
  final String message;
  final String errorCode;

  const Failure(this.message, this.errorCode);

  bool get isConnectionTimeout => errorCode == ErrorCode.connectionTimeout;
  bool get isSendTimeout => errorCode == ErrorCode.sendTimeout;
  bool get isReceiveTimeout => errorCode == ErrorCode.receiveTimeout;
  bool get isTimeout =>
      isConnectionTimeout || isSendTimeout || isReceiveTimeout;

  @override
  List<Object> get props => [message, errorCode];
}

class ServerFailure extends Failure {
  const ServerFailure(super.message, super.errorCode);

  factory ServerFailure.fromDioError(DioException e) {
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
        BuildContext context =
        AppRouter.router.configuration.navigatorKey.currentContext!;
        final router = GoRouter.of(context);
        router.pushReplacement(AppRouter.kConnectionTimeoutPage);
        return const ServerFailure(
          'Connection timeout with api server',
          ErrorCode.connectionTimeout,
        );
      case DioExceptionType.sendTimeout:
        return const ServerFailure(
          'Send timeout with ApiServer',
          ErrorCode.sendTimeout,
        );
      case DioExceptionType.receiveTimeout:
        return const ServerFailure(
          'Receive timeout with ApiServer',
          ErrorCode.receiveTimeout,
        );
      case DioExceptionType.badCertificate:
        return const ServerFailure('badCertificate with api server', "");
      case DioExceptionType.badResponse:
        return ServerFailure.fromResponse(e.response!.data);
      case DioExceptionType.cancel:
        return const ServerFailure('Request to ApiServer was canceld', "");
      case DioExceptionType.connectionError:
        return const ServerFailure(
          'No Internet Connection',
          ErrorCode.noInternet,
        );
      case DioExceptionType.unknown:
        return const ServerFailure(
          'Ops, There was an Error, Please try again',
          "",
        );
    }
  }

  factory ServerFailure.fromResponse(dynamic response) {
    if (response['ErrorMessage'] != null) {
      return ServerFailure(
        response['ErrorMessage'],
        response['ErrorCode'].toString(),
      );
    } else {
      return const ServerFailure('There was an error , please try again', "");
    }
  }

  @override
  String toString() {
    return 'ServerFailure{errorMessage: $message}';
  }
}

class ConnectionFailure extends Failure {
  const ConnectionFailure(super.message, super.errorCode);

  @override
  String toString() {
    return 'ConnectionFailure{errorMessage: $message}';
  }
}

class DatabaseFailure extends Failure {
  const DatabaseFailure(super.message, super.errorCode);
}

class PlaybackFailure extends Failure {
  const PlaybackFailure(super.message, super.errorCode);
}
