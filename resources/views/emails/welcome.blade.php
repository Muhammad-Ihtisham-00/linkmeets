<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #222;
            max-width: 600px;
            margin: 0 auto;
            padding: 24px;
        }

        .header {
            margin-bottom: 24px;
            text-align: left;
        }

        .footer {
            margin-top: 32px;
            font-size: 13px;
            color: #777;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>Welcome to {{ config('app.name') }}</h2>
    </div>

    <p>Hello {{ trim($user->first_name . ' ' . ($user->last_name ?? '')) }},</p>

    <p>Thank you for joining {{ config('app.name') }}. Your account has been successfully created using the email:
        <strong>{{ $user->email }}</strong>.</p>

    <p>We’re glad to have you with us and hope you enjoy your experience. If you have any questions or need assistance,
        our team is here to help.</p>

    <p>Warm regards,<br>
        The {{ config('app.name') }} Team</p>

    <div class="footer">
        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
    </div>
</body>

</html>
