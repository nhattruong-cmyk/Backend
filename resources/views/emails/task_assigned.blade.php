<!DOCTYPE html>
<html>
<head>
    <title>Thông báo phân công công việc</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .email-header {
            background: #007BFF;
            color: #ffffff;
            padding: 20px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
        }
        .email-body {
            padding: 20px;
            color: #333333;
        }
        .email-body h1 {
            font-size: 20px;
            color: #007BFF;
        }
        .email-body p {
            margin: 10px 0;
        }
        .email-body .highlight {
            font-weight: bold;
            color: #555555;
        }
        .email-footer {
            text-align: center;
            background: #f4f4f9;
            color: #666666;
            padding: 10px;
            font-size: 14px;
        }
        .email-footer a {
            color: #007BFF;
            text-decoration: none;
        }
        .email-footer a:hover {
            text-decoration: underline;
        }
        .cta-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #007BFF;
            color: #ffffff;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
        }
        .cta-button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            Thông Báo Phân Công Nhiệm Vụ
        </div>
        <div class="email-body">
            <h1>Xin chào,</h1>
            <p>Bạn đã được phân công một nhiệm vụ mới:</p>
            <p class="highlight">{{ $task_name }}</p>
            @if($note)
                <p><strong>Ghi chú:</strong> {{ $note }}</p>
            @endif
        </div>
        <div class="email-footer">
            <p>Cảm ơn bạn đã sử dụng nền tảng của chúng tôi.</p>
            <p><a href="https://yourapp.com">Truy cập website</a> để biết thêm thông tin.</p>
        </div>
    </div>
</body>
</html>
