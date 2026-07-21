<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>رمز التحقق</title>
</head>
<body>
    <h2>مرحباً {{ $fullname }}</h2>
    <p>رمز التحقق الخاص بك هو:</p>
    <h1 style="color: #4CAF50; font-size: 32px; letter-spacing: 5px;">{{ $otpCode }}</h1>
    <p>هذا الرمز صالح لمدة 10 دقائق فقط</p>
    <p>إذا لم تطلب هذا الرمز، يرجى تجاهل هذه الرسالة</p>
</body>
</html>