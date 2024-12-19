<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bạn đã bị xóa khỏi phòng ban</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <style>
        .container {
            max-width: 600px;
            margin: 20px auto;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid #ddd;
            background-color: #ffffff;
        }

        .header {
            background-color: #D32F2F;
            /* Màu đỏ */
            color: white;
            border-radius: 8px 8px 0 0;
            padding: 25px;
        }

        .content {
            padding: 30px;
        }

        .cta-button {
            display: inline-block;
            background-color: #D32F2F;
            /* Màu đỏ */
            color: white;
            padding: 12px 24px;
            font-size: 16px;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            margin-top: 20px;
            text-transform: uppercase;
        }

        .cta-button:hover {
            background-color: #B71C1C;
            /* Màu đỏ đậm hơn khi hover */
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #777;
            padding: 10px 0;
        }

        h1 {
            color: #ffffff;
            font-size: 24px;
            text-align: center;
        }

        p {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1 class="text-center">Bạn đã bị xóa khỏi phòng ban</h1>
        </div>
        <div class="content">
            <p>Chào {{ $user->fullname }},</p>
            <p>Chúng tôi xin thông báo rằng bạn đã bị xóa khỏi phòng ban:
                <strong>{{ $department->department_name }}</strong>.</p>
            <p>Vui lòng liên hệ với quản trị viên nếu có bất kỳ câu hỏi nào.</p>
        </div>
        <div class="footer">
            <p>Trân trọng,</p>
            <p>Đội ngũ quản trị</p>
        </div>
    </div>
</body>

</html>
