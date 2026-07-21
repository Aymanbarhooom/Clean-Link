<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>رمز التحقق</title>
</head>
<body>
    <h2>Hello {{ $fullname }}!</h2>
    <p>Your verification code is:</p>
    <h1 style="color: #1b9da1; font-size: 32px; letter-spacing: 5px;">{{ $otpCode }}</h1>
    <p>This code is valid for 10 minutes only</p>
    <p>If you did not request this code, please ignore this message</p>
</body>
</html>