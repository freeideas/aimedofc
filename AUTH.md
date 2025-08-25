# Passwordless Authentication System

## Overview

The authentication system is completely standalone and independent, implemented as a single page directory. You can delete all non-auth pages and build a completely different web app while keeping the passwordless login system intact.

## Architecture

- **Single Page Design**: All authentication handled in `pg_login/` directory
- **Separate Database**: Authentication uses its own `auth.db` file in `data/` directory
- **Independent Operation**: Login, verification, and session management run independently
- **Cross-Database Access**: Other pages use SQLite's ATTACH feature to read auth data
- **Modular Design**: The entire application can be replaced without touching authentication

## Authentication Flow

### Single-Page Authentication (`pg_login/`)

The authentication system uses a single page with a dual-purpose form:

1. **Initial State**
   - Form displays email field only
   - User enters email address
   - Form submits to `login.php` with email only

2. **Email Sent State**
   - `login.php` receives email, sends 6-digit verification code
   - System sends code via email (using `~/bin/email-send`)
   - Testing of email-send can be accomplished with `~/bin/email-read`
   - Form reveals the verification code field (previously hidden)
   - User enters the 6-digit code

3. **Verification**
   - Form submits to `login.php` with both email and code
   - `login.php` validates the code against stored verification codes
   - On success: creates session and redirects to `pg_main/`
   - On failure: shows error, allows retry

### API Endpoint (`login.php`)

The `login.php` endpoint handles both operations:

```php
// Pseudo-logic:
if (received email only) {
    generate 6-digit code
    store code in verification_codes table
    send email with code
    return success (triggers UI to show code field)
}
else if (received email + code) {
    validate code from verification_codes table
    if valid:
        create user if not exists
        create session
        set cookie
        redirect to pg_main/
    else:
        return error
}
```

### Session Management
- Cookie name: `aiofc_session` (contains base62 UUID session token)
- Successful verification creates a new session entry in database
- Each device/browser gets its own unique session token
- Multiple devices can be logged in simultaneously for the same user
- Sessions expire after 32 days of inactivity
- Cookie contains session token (base62 UUID) that maps to sessions table
- Sessions table links session token to user_id for user lookup

## Database Schema

### Authentication Database (auth.db)

Standalone database for authentication system:

```sql
-- Users table (minimal, auth-only data)
CREATE TABLE users (
    id TEXT PRIMARY KEY,  -- Generated unique ID
    email TEXT UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
);

-- Sessions table (supports multiple devices per user)
CREATE TABLE sessions (
    id TEXT PRIMARY KEY,  -- Generated unique ID
    user_id TEXT NOT NULL,
    token TEXT UNIQUE NOT NULL,  -- Session token stored in cookie
    device_info TEXT,  -- Optional: user agent, IP for security tracking
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Verification codes for passwordless login
CREATE TABLE verification_codes (
    id TEXT PRIMARY KEY,
    email TEXT NOT NULL,
    code TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT 0
);
```

## Cross-Database Access

Pages requiring user data use SQLite's ATTACH feature:

```php
// Example: Accessing user info from application pages
$db = new SQLite3('/path/to/data/aioffice.db');
$db->exec("ATTACH DATABASE '/path/to/data/auth.db' AS auth");

// Now can JOIN across databases
$stmt = $db->prepare("
    SELECT p.*, auth.users.email 
    FROM patients p 
    JOIN auth.users ON p.user_id = auth.users.id 
    WHERE p.user_id = :user_id
");
```

## Authentication Components

### Single Page Directory (`pg_login/`)
- `index.html` - Combined login form with email and verification code fields
  - Email field: always visible
  - Verification code field: hidden initially, shown after email sent
  - JavaScript handles field visibility and form state
  - **Automatic Login Check**: On page load, JavaScript checks for existing session cookie
    - If valid session cookie exists, calls server to validate token
    - If token is valid, refreshes cookie expiry (+32 days) and database session expiry
    - Immediately redirects to `pg_main/` without displaying login form
    - If no cookie or invalid token, displays login form normally
- `login.php` - Dual-purpose endpoint
  - Accepts email only → sends verification code
  - Accepts email + code → validates and creates session
  - Accepts token only → validates existing session and refreshes expiry
- `style.css` - Login page styles
- `app.js` - Form handling, field visibility, AJAX requests, automatic login check

### Infrastructure
- `infrastructure/auth/`
  - `session.php` - Session management functions
  - `verify.php` - Authentication verification
  - `middleware.php` - Auth middleware for protected pages

## Security Considerations

- Verification codes expire after 15 minutes
- Rate limiting on login attempts
- SQL injection prevention via prepared statements
- XSS protection on all user inputs

## Utility Functions

The `www/infrastructure/lib.php` file provides authentication-related utilities (copy from `doc/copy_src/lib.php` if it doesn't exist):
- `generateID()` - Creates unique IDs for users and sessions
- `generateVerificationCode()` - Creates 6-digit email verification codes
- `isValidEmail()` - Validates email addresses
- `sanitizeInput()` - Prevents XSS attacks
- `getUserIdFromToken(string $token)` - Validates session token and returns user_id or null

### Token Validation for API Endpoints

All PHP API endpoints that need authentication should use the `getUserIdFromToken()` function:

```php
// In any pg_*/api_*.php file:
require_once '../infrastructure/lib.php';

// Get token from Authorization header or cookie
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_COOKIE['aiofc_session'] ?? '';

$userId = getUserIdFromToken($token);
if (!$userId) {
    http_response_code(401);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Not authenticated']));
}

// Now use $userId for database queries...
```

The function automatically:
- Validates the token exists in the sessions table
- Checks the session hasn't expired
- Updates the last_activity timestamp
- Returns the user_id for valid sessions, null for invalid/expired