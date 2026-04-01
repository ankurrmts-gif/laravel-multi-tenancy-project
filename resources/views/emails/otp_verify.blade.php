<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Account - OTP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
            color: #333;
        }
        .email-wrapper {
            background-color: #f5f5f5;
            padding: 20px;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .header-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 40px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
            color: #555;
        }
        .greeting strong {
            color: #333;
        }
        .instruction {
            font-size: 15px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.8;
        }
        .otp-container {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 25px;
            margin: 30px 0;
            text-align: center;
        }
        .otp-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .otp-code {
            font-size: 42px;
            font-weight: 700;
            color: #667eea;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }
        .expiry-info {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .expiry-info p {
            font-size: 14px;
            color: #856404;
            margin: 0;
        }
        .expiry-info strong {
            color: #e74c3c;
            font-weight: 700;
        }
        .timer {
            display: inline-block;
            background-color: #e74c3c;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 13px;
        }
        .security-section {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 20px;
            margin: 25px 0;
        }
        .security-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        .security-title::before {
            content: "🔒";
            margin-right: 8px;
            font-size: 16px;
        }
        .security-note {
            font-size: 13px;
            color: #666;
            line-height: 1.6;
            margin: 8px 0;
        }
        .security-note strong {
            color: #e74c3c;
        }
        .action-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            margin: 20px 0;
            text-align: center;
        }
        .action-button:hover {
            opacity: 0.9;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 30px 20px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }
        .footer-text {
            font-size: 13px;
            color: #999;
            margin: 8px 0;
        }
        .footer-links {
            font-size: 12px;
            margin-top: 15px;
        }
        .footer-links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 8px;
        }
        .divider {
            height: 1px;
            background-color: #e0e0e0;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <!-- Header -->
            <div class="header">
                <div class="header-icon">✓</div>
                <h1>Verify Your Account</h1>
                <p>Secure Login Verification</p>
            </div>

            <!-- Content -->
            <div class="content">
                <div class="greeting">
                    Hello <strong>{{ $user->first_name ?? 'User' }}</strong>,
                </div>

                <div class="instruction">
                    We've received a request to access your account. To complete your login securely, please use the One-Time Password (OTP) below. This code is valid for a limited time only.
                </div>

                <!-- OTP Box -->
                <div class="otp-container">
                    <div class="otp-label">Your One-Time Password</div>
                    <div class="otp-code">{{ $otp }}</div>
                </div>

                <!-- Expiry Info -->
                <div class="expiry-info">
                    <p>
                        ⏰ This code expires in <span class="timer">{{ $expired }} minutes</span>
                    </p>
                </div>

                <!-- How to Use -->
                <div class="divider"></div>
                <h3 style="font-size: 16px; color: #333; margin: 20px 0 15px 0;">How to Use Your OTP:</h3>
                <ol style="list-style: none; padding-left: 0;">
                    <li style="padding: 8px 0; font-size: 14px; color: #666;">
                        <span style="color: #667eea; font-weight: 600; margin-right: 10px;">1.</span>
                        Copy the 6-digit code above
                    </li>
                    <li style="padding: 8px 0; font-size: 14px; color: #666;">
                        <span style="color: #667eea; font-weight: 600; margin-right: 10px;">2.</span>
                        Return to the login page
                    </li>
                    <li style="padding: 8px 0; font-size: 14px; color: #666;">
                        <span style="color: #667eea; font-weight: 600; margin-right: 10px;">3.</span>
                        Paste the code in the OTP verification field
                    </li>
                    <li style="padding: 8px 0; font-size: 14px; color: #666;">
                        <span style="color: #667eea; font-weight: 600; margin-right: 10px;">4.</span>
                        Click "Verify" to complete your login
                    </li>
                </ol>

                <!-- Security Section -->
                <div class="security-section">
                    <div class="security-title">Security Information</div>
                    <div class="security-note">
                        <strong>👤 Personal Account Access:</strong> This code is exclusively for your account. Never share it with anyone.
                    </div>
                    <div class="security-note">
                        <strong>⏰ Time-Limited:</strong> For security reasons, this code will expire after {{ $expired }} minutes.
                    </div>
                    <div class="security-note">
                        <strong>🤔 Didn't Request This?</strong> If you didn't attempt to log in, please ignore this email. Your account remains secure.
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                <div class="footer-text">
                    <strong>{{ config('app.name') }}</strong>
                </div>
                <div class="footer-text">
                    This is an automated message. Please do not reply to this email.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
