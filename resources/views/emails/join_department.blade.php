<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mời Tham Gia Phòng Ban</title>
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
            background-color: #007BFF;
            color: white;
            border-radius: 8px 8px 0 0;
            padding: 25px;
        }

        .content {
            padding: 30px;
        }

        .confirmation-text {
            font-size: 20px;
            font-weight: bold;
            margin: 20px 0;
            text-align: center;
        }

        .button {
            background-color: #007BFF;
            color: white !important;
            padding: 15px 32px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            border-radius: 5px;
            font-size: 16px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .button:hover {
            background-color: #074990;
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
            <h1 class="text-center">Mời Tham Gia Phòng Ban</h1>
        </div>
        <div class="content">
            <p>Xin chào {{ $user->fullname }},</p>
            <p>Bạn đã được mời tham gia phòng ban: <strong>{{ $department->department_name }}</strong>.</p>
            <p>Vui lòng nhấp vào nút dưới đây để xác nhận tham gia:</p>
            <div class="confirmation-text">
                <a href="{{ url('http://localhost:3000/taskmaneger/departments/confirm/' . $department->id . '/' . $confirmationToken) }}"
                    class="button">Xác nhận tham gia</a>
            </div>
        </div>
        <div class="footer">
            <p>Chúng tôi hy vọng bạn sẽ tham gia và đóng góp vào sự phát triển của phòng ban.</p>
            <p>Trân trọng,</p>
            <h2>NHĐT</h2>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-kenU1KFdBIe4zVFQWjxhNqHJ8VZN9lmOUWC0yPW4fCWDiIWsYmEjqDKIiQ0GOsO8r" crossorigin="anonymous">
    </script>
</body>

</html>
