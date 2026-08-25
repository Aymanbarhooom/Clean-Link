enum HttpHeader {
  contentType('Content-Type'),
  accept('Accept'),
  acceptLanguage('Accept-Language'),
  accessControlAllowOrigin('Access-Control-Allow-Origin'),
  appVersion('appVersion'),
  osVersion('osVersion'),
  mobileModel('mobileModel'),
  language('language'),
  platform('platform'),
  mobileManufacture('mobileManufacture'),
  deviceId('deviceId'),
  registrationId('registrationId');

  const HttpHeader(this.value);

  final String value;
}