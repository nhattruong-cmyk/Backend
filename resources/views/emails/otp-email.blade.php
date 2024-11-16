<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Nhận Mã OTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <style>
        .container {
            max-width: 600px;
            margin: 20px auto;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid #ddd;
        }

        .header {
            background-color: #FF5722;
            color: white;
            border-radius: 8px 8px 0 0;
            padding: 25px;
        }

        .content {
            padding: 30px;
        }

        .otp-code {
            font-size: 28px;
            font-weight: bold;
            color: #FF5722;
            margin: 20px 0;
            letter-spacing: 2px;
            text-align: center;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #777;
            padding: 10px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1 class="text-center">Mã Xác Thực OTP</h1>
        </div>
        <div class="content">
            <p>Xin chào {{ $user->fullname }},</p>
            <p>Bạn đã yêu cầu đặt lại mật khẩu. Vui lòng sử dụng mã OTP dưới đây để tiếp tục:</p>
            <div class="otp-code">{{ $verificationCode }}</div>
            <p>Mã này có hiệu lực trong 10 phút. Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.</p>
        </div>
        <div class="footer">
            <p>Trân trọng,</p>
            <p>Đội ngũ Hỗ trợ</p>
            <h2>NHĐT</h2>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-kenU1KFdBIe4zVFQWjxhNqHJ8VZN9lmOUWC0yPW4fCWDiIWsYmEjqDKIiQ0GOsO8r" crossorigin="anonymous">
    </script>
</body>

</html>
