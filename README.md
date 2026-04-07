# PW Portal v2 — Complete Setup Guide

## Files
```
pw_v2/
├── index.php           ← Login (OTP + Token) — BUG FIXED
├── dashboard.php       ← User's batch list
├── batch.php           ← Batch → Subjects
├── subject.php         ← Subject → Videos / Notes / DPP + Video Player
├── .htaccess           ← Security
├── includes/
│   ├── config.php      ← API constants + admin credentials
│   ├── api.php         ← All PW API calls (mapped from Python file)
│   └── auth.php        ← Session management
├── admin/
│   ├── index.php       ← Admin login (matrix animation)
│   └── dashboard.php   ← Full user management + Bearer tokens
└── data/
    ├── .htaccess       ← Blocks web access
    └── users.json      ← Auto-created user database
```

## Setup (2 minutes)

1. Upload `pw_v2/` folder to your PHP server (PHP 7.4+ with cURL)
2. Make `data/` writable:
   ```bash
   chmod 755 data/
   ```
3. **Change admin password** in `includes/config.php`:
   ```php
   define('ADMIN_PASS_HASH', hash('sha256', 'YourNewPassword'));
   ```

## Access URLs

| Page | URL |
|------|-----|
| User Login | `yourdomain.com/index.php` |
| Admin Login | `yourdomain.com/admin/index.php` |
| Admin Panel | `yourdomain.com/admin/dashboard.php` |

Default admin: `admin` / `Admin@PW#Secure2024`

## What's Fixed & Added

### Bug Fix: OTP Login
- Old code failed if PW API returned any non-standard response body
- NEW: Only checks HTTP status code 200, not response body
- OTP sends → user goes straight to OTP verification screen

### Bearer Token Flow
- User logs in via OTP → `access_token` extracted from PW API response
- Token stored in `data/users.json` with full history
- Admin panel shows: `Authorization: Bearer eyJ...` format
- Copy buttons: "Copy Token" (raw) and "Copy Bearer" (with Bearer prefix)

### Admin Panel
- Card-based responsive layout
- Each card shows: name, phone, login count, token count, batches
- Full Bearer token with expand + copy buttons
- Token history (last 10 tokens stored)
- Sorted by most recent login
- Search by name or phone

### User Flow
1. Login → Dashboard (all batches)
2. Click batch → Subjects list
3. Click subject → Videos / Notes / DPP tabs
4. Click video → Modal player (HLS M3U8 supported)

### Video Player
- Opens in modal overlay
- HLS.js library for M3U8 streams
- URL transform from Python: `d1d34p8vz63oiq` → `d26g5bnklkwsh4`, `.mpd` → `.m3u8`

## Security
- Admin password: SHA-256 hashed, never plain text
- Tokens: never in page HTML (admin panel only)
- DevTools/F12 blocked on login & admin pages
- `/data/` and `/includes/` blocked from web access
- Session cookies: HttpOnly, SameSite=Strict
- Brute force delay on wrong admin login
