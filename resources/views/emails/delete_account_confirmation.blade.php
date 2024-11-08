<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Nhận Xóa Tài Khoản</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .header {
            background-color: #ff0000;
            color: white;
            text-align: center;
            padding: 20px;
        }

        .content {
            text-align: center;
            padding: 20px;
        }

        .button {
            background-color: #ff0000;
            color: white;
            padding: 15px 32px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            border-radius: 5px;
            font-size: 16px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #777;
        }

        .token-box {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 20px;
            margin-top: 20px;
            text-align: center;
            font-size: 16px;
            border-radius: 5px;
        }

        .token-box span {
            font-weight: bold;
            color: #333;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Xin Chào {{ $userName }}</h1>
        </div>
        <div class="content">
            <p>Chúng tôi đã nhận được yêu cầu xóa tài khoản của bạn. Vui lòng nhấp vào nút dưới đây để xác nhận xóa tài
                khoản của bạn.</p>
            <a href="{{ $confirmationUrl }}" class="button">Xác Nhận Xóa Tài Khoản</a>

            <div class="token-box">
                <p><strong>Mã Xác Nhận:</strong></p>
                <p>{{ $user->delete_token }}</p>
            </div>
        </div>
        <div class="footer">
            <p>Nếu bạn không yêu cầu xóa tài khoản này, vui lòng bỏ qua email này.</p>
        </div>
    </div>
</body>

</html>
