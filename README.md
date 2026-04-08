# PW Portal — Complete PHP Website

## File Structure
```
pw_website/
├── index.php           ← Login page (OTP + Token)
├── dashboard.php       ← User courses dashboard
├── batch.php           ← Batch/course detail page
├── .htaccess           ← Security rules
├── includes/
│   ├── config.php      ← API keys & admin credentials
│   ├── api.php         ← All PW API functions
│   └── auth.php        ← Session management
├── admin/
│   ├── index.php       ← Admin login (/admin)
│   └── dashboard.php   ← Admin panel (users + tokens)
└── data/
    ├── .htaccess       ← Blocks web access to data
    └── users.json      ← User database (auto-created)
```

## Setup

1. Upload all files to your PHP server (PHP 7.4+, cURL enabled)
2. Make sure `data/` folder is writable: `chmod 755 data/`
3. **Change admin password** in `includes/config.php`:
   ```php
   define('ADMIN_PASSWORD_HASH', hash('sha256', 'YourNewPasswordHere'));
   ```

## Access

- **User Login:** `https://yourdomain.com/`
- **Admin Panel:** `https://yourdomain.com/admin/`
  - Default username: `admin`
  - Default password: `Admin@PW#2024` ← **CHANGE THIS!**

## Security Features

- Passwords stored as SHA-256 hash (never plain text)
- Admin credentials never exposed to frontend/inspector
- Tokens visible only in admin panel (not in frontend HTML)
- Session regeneration on login
- HttpOnly cookies
- .htaccess blocks direct access to `/includes/` and `/data/`
- DevTools / Right-click blocked on admin and login pages
- Constant-time comparison to prevent timing attacks
- 1-second delay on wrong admin login (brute-force protection)
- X-Frame-Options, XSS protection headers

## How It Works

1. User visits `index.php` → enters phone → gets OTP via PW API
2. Enters OTP → gets access_token + refresh_token
3. OR pastes token directly (Token tab)
4. Token validated → user stored in `data/users.json`
5. Dashboard shows all purchased PW batches
6. Admin at `/admin` sees ALL users, their tokens, login count, etc.
