# Passwordless Login Page

## Purpose

This page implements the standalone passwordless authentication system for Medical Office Assistant. It handles email verification, session creation, and automatic login for returning users.

## Features

- **Passwordless Authentication**: Users log in with just their email address
- **Dual-Purpose Form**: Single form that adapts between email entry and code verification
- **Auto-Login**: Automatically checks for existing session and redirects logged-in users
- **Session Management**: Creates persistent sessions with 32-day expiry
- **Responsive Design**: Mobile-friendly interface with gradient background

## Files

- `index.php` - Server-side auth check, then shows login form if needed
- `login.php` - API endpoint handling three operations:
  - Token validation (auto-login check)
  - Email verification code sending
  - Code verification and session creation
- `style.css` - Responsive styling with gradient background
- `app.js` - Client-side logic for form handling
- `test.py` - Automated tests for all login scenarios
- `README.md` - This documentation file

## Authentication Flow

1. **Initial Page Load**: PHP checks for `aiofc_session` cookie server-side
   - If valid session exists, immediately redirects to `../pg_main/`
   - Otherwise renders the login form
   - This ensures users who click "Get Started" from pg_index go directly to pg_main if already logged in

2. **Email Submission**: User enters email and clicks "Send Verification Code"
   - Server generates 6-digit code
   - Stores code in database with 15-minute expiry
   - Sends code via email
   - Form reveals verification code field

3. **Code Verification**: User enters code and clicks "Verify & Login"
   - Server validates code against database
   - Creates user if first-time login
   - Creates new session with 32-day expiry
   - Sets `aiofc_session` cookie
   - Redirects to `/pg_main/`

## Database Tables

The login system creates and manages three tables in `auth.db`:

- `users` - Stores user emails and IDs
- `sessions` - Active sessions with tokens and expiry times
- `verification_codes` - Temporary codes for email verification

## Security Features

- Verification codes expire after 15 minutes
- Sessions last 32 days, refreshed to 32 days from last use on each API call
- HttpOnly cookies prevent JavaScript access  
- SameSite=Strict prevents CSRF attacks
- Input sanitization prevents XSS

## Session Persistence

- Sessions are created with 32-day expiry when user logs in
- Each authenticated API call refreshes the session to 32 days from current time
- Logout explicitly invalidates the session in the database
- This allows users to stay logged in between visits without re-authenticating
- Prepared statements prevent SQL injection

## Visual Design

- Gradient background (purple to pink)
- White card with shadow for login form
- Clear visual hierarchy with proper spacing
- Success/error messages with appropriate colors
- Smooth transitions and hover effects

## Testing

Run tests with:
```bash
python test.py
```

Tests verify:
- Email validation
- Code generation and expiry
- Session creation and persistence
- Auto-login functionality
- Error handling