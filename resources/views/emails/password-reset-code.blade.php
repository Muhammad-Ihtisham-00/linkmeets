<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Password Reset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #222;
            max-width: 600px;
            margin: 0 auto;
            padding: 24px;
        }

        .code {
            display: block;
            width: fit-content;
            margin: 16px 0;
            padding: 12px 20px;
            font-size: 28px;
            font-weight: bold;
            background-color: #f3f3f3;
            border-radius: 6px;
            letter-spacing: 4px;
        }

        .footer {
            margin-top: 32px;
            font-size: 13px;
            color: #777;
        }
    </style>
</head>

<body>
    <h2>Password Reset</h2>

    <p>Hello {{ trim($user->first_name . ' ' . ($user->last_name ?? '')) }},</p>

    <p>A request was made to reset your password. Use the code below to continue:</p>

    <span class="code">{{ $code }}</span>

    <p>Thanks,<br>{{ config('app.name') }} Team</p>

    <div class="footer">
        This is an automated message. Please do not reply.
    </div>
</body>

</html>
