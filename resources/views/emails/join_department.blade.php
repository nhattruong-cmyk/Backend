<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bạn đã được thêm vào phòng ban</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7fc;
            color: #333;
            padding: 20px;
        }

        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }

        h1 {
            color: #2d3a4b;
            font-size: 24px;
            text-align: center;
        }

        p {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .cta-button {
            display: inline-block;
            background-color: #4CAF50;
            color: #fff;
            padding: 10px 20px;
            font-size: 16px;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
        }

        .cta-button:hover {
            background-color: #45a049;
        }

        .footer {
            font-size: 14px;
            color: #777;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Chào {{ $user->name }},</h1>
        <p>Bạn đã được mời tham gia phòng ban: <strong>{{ $department->department_name }}</strong>.</p>
        <p>Vui lòng nhấp vào liên kết dưới đây để xác nhận việc tham gia của bạn:</p>
      <a href="{{ url('http://localhost:3000/taskmaneger/departments/confirm/' . $department->id . '/' . $confirmationToken) }}" class="cta-button">
            Xác nhận tham gia phòng ban
        </a>
        
        <div class="footer">
            <p>Chúng tôi hy vọng bạn sẽ tham gia và đóng góp vào sự phát triển của phòng ban.</p>
            <p>Cảm ơn bạn!</p>
        </div>
    </div>
</body>
</html>
