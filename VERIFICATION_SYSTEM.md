# Alert.az Verification System with Local Mock Mode

## 🎯 Overview

A comprehensive verification system for SMS and Email OTP codes with **automatic mock mode** for local development. When running in local environment, the system accepts a predefined 6-digit code (`123456` by default) without sending actual SMS or emails, making development and testing seamless.

## ✨ Key Features

- **Automatic Local Mode Detection**: Automatically enables mock mode in `local` environment
- **Universal Mock Code**: Use `123456` for all verifications in local mode
- **Debug Information**: Returns the verification code in API responses during local development
- **Production Ready**: Seamlessly switches to real SMS/Email providers in production
- **Multi-Channel Support**: Handles both SMS and Email verifications
- **Rate Limiting**: Prevents spam with built-in rate limiting
- **Code Expiry**: Automatic expiration of verification codes

## 🚀 How It Works

### Local Mode (Development)
When `APP_ENV=local` or `VERIFICATION_MOCK_MODE=true`:
1. User requests verification code
2. System logs the code but doesn't send SMS/Email
3. API returns success with debug info showing code `123456`
4. User enters `123456` as verification code
5. System accepts the code and completes verification

### Production Mode
When `APP_ENV=production`:
1. User requests verification code
2. System generates random 6-digit code
3. Sends actual SMS/Email via configured provider
4. User enters received code
5. System validates and completes verification

## 📱 API Endpoints

### Send SMS OTP
```bash
POST /api/auth/otp/send
{
  "phone": "501234567"
}

# Response in Local Mode:
{
  "status": "success",
  "message": "Verification code sent successfully (LOCAL MODE: use 123456)",
  "data": {
    "phone": "+994501234567",
    "expires_in": 600,
    "debug": {
      "code": "123456",
      "phone": "+994501234567",
      "mode": "mock"
    }
  }
}
```

### Verify SMS OTP
```bash
POST /api/auth/otp/verify
{
  "phone": "501234567",
  "code": "123456"
}

# Response:
{
  "status": "success",
  "message": "Phone verified successfully",
  "data": {
    "user": { ... },
    "token": "auth_token_here",
    "debug": {
      "mode": "mock",
      "accepted_code": "123456"
    }
  }
}
```

### Send Email Verification
```bash
POST /api/auth/email/send
{
  "email": "test@example.com"
}

# Response in Local Mode:
{
  "status": "success",
  "message": "Verification code sent successfully (LOCAL MODE: use 123456)",
  "data": {
    "email": "test@example.com",
    "expires_in": 900,
    "debug": {
      "code": "123456",
      "email": "test@example.com",
      "mode": "mock"
    }
  }
}
```

### Verify Email Code
```bash
POST /api/auth/email/verify
{
  "email": "test@example.com",
  "code": "123456"
}
```

### Resend Code
```bash
POST /api/auth/resend-code
{
  "identifier": "501234567",  # or email address
  "type": "sms"  # or "email"
}
```

## ⚙️ Configuration

### Environment Variables

```env
# Enable/Disable mock mode (auto-enabled in local)
VERIFICATION_MOCK_MODE=true  # Optional, defaults to true in local env

# Custom mock code (optional, defaults to 123456)
MOCK_VERIFICATION_CODE=123456

# SMS Providers (for production)
SMS_PROVIDER=mock  # Options: twilio, nexmo, azercell, mock

# Twilio Configuration
TWILIO_SID=your_account_sid
TWILIO_TOKEN=your_auth_token
TWILIO_FROM=+1234567890

# Nexmo Configuration
NEXMO_KEY=your_key
NEXMO_SECRET=your_secret

# Email Configuration (uses Laravel's mail config)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_password
```

### Config File
Located at `config/app.php`:
```php
'verification_mock' => env('VERIFICATION_MOCK_MODE', env('APP_ENV') === 'local'),
'mock_verification_code' => env('MOCK_VERIFICATION_CODE', '123456'),
```

## 🧪 Testing

### Run Test Script
```bash
php test-verification.php
```

### Manual Testing with cURL

1. **Send SMS Code:**
```bash
curl -X POST http://localhost:8007/api/auth/otp/send \
  -H 'Content-Type: application/json' \
  -d '{"phone": "501234567"}'
```

2. **Verify with Mock Code:**
```bash
curl -X POST http://localhost:8007/api/auth/otp/verify \
  -H 'Content-Type: application/json' \
  -d '{"phone": "501234567", "code": "123456"}'
```

## 📝 Log Messages

In local mode, check `storage/logs/laravel.log` for verification codes:
```
[LOCAL MODE] SMS Verification Code for +994501234567: 123456
[LOCAL MODE] Email Verification Code for test@example.com: 123456
[LOCAL MODE] Auto-accepting mock verification code 123456
```

## 🔧 Implementation Details

### VerificationService
Main service class handling all verification logic:
- `sendSMSVerification()`: Sends or mocks SMS codes
- `sendEmailVerification()`: Sends or mocks email codes
- `verifyCode()`: Validates verification codes
- `resendCode()`: Handles code resend with rate limiting
- `isLocalMode()`: Detects if mock mode should be active

### Database Schema
```sql
otp_verifications table:
- id
- type (sms/email)
- phone (nullable)
- email (nullable)
- code
- user_id (nullable)
- expires_at
- verified_at
- created_at
- updated_at
```

## 🎭 Frontend Integration

### React/Next.js Example
```javascript
// Send verification code
const sendCode = async (phone) => {
  const response = await fetch('/api/auth/otp/send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone })
  });

  const data = await response.json();

  // In development, show the code to user
  if (data.data.debug) {
    console.log('Use this code:', data.data.debug.code);
    alert(`Development Mode: Use code ${data.data.debug.code}`);
  }
};

// Verify code
const verifyCode = async (phone, code) => {
  const response = await fetch('/api/auth/otp/verify', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone, code })
  });

  const data = await response.json();
  if (data.status === 'success') {
    // Store token and redirect
    localStorage.setItem('token', data.data.token);
    window.location.href = data.data.return_url || '/dashboard';
  }
};
```

## 🚨 Troubleshooting

### Issue: "Invalid or expired verification code"
- In local mode: Make sure you're using `123456` as the code
- Check if `APP_ENV=local` in your `.env` file
- Verify `VERIFICATION_MOCK_MODE` is not set to `false`

### Issue: Actual SMS/Email being sent in local
- Check `APP_ENV` is set to `local`
- Ensure `VERIFICATION_MOCK_MODE` is not explicitly set to `false`
- Clear config cache: `php artisan config:clear`

### Issue: Debug info not showing in response
- Debug info only shows when `APP_ENV=local`
- It won't appear in production for security reasons

## 🔐 Security Considerations

- **Never use mock mode in production**
- Debug information is only returned in local environment
- Verification codes expire after 10 minutes (SMS) or 15 minutes (Email)
- Rate limiting prevents brute force attempts
- Old verification codes are automatically cleaned up

## 📊 Benefits

1. **Fast Development**: No need for real SMS/Email during development
2. **Cost Effective**: Save on SMS/Email costs during testing
3. **Consistent Testing**: Always use the same code for automated tests
4. **Easy Onboarding**: New developers can test immediately without API keys
5. **CI/CD Friendly**: Works seamlessly in testing pipelines

## 🎉 Summary

The verification system provides a seamless development experience with automatic mock mode in local environment while maintaining production-ready security. Simply use code `123456` during local development, and the system automatically switches to real providers in production.