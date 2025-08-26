# Main Dashboard Page

## Purpose

This page serves as the main dashboard/menu for Medical Office Assistant users. It provides a central hub with navigation to all major features of the application. The page renders immediately with placeholder content, then dynamically loads personalized data if the user is authenticated.

## Architecture

Following the API-first design principle:
- **Static HTML renders first** with meaningful placeholder content
- **JavaScript fetches data** from PHP REST API endpoints after page load
- **PHP APIs validate authentication** using the session token from cookies
- **No server-side rendering** - complete separation of presentation and data

## Features

- **Welcome Message**: Shows "Welcome, Patient" by default, personalized when authenticated
- **Navigation Menu**: Clear links to all major application features (always visible)
- **Quick Actions**: Direct access to most common tasks (always visible)
- **Recent Activity**: Shows sample conversations, replaced with real data when authenticated
- **Session Status**: Login/Logout button that changes based on authentication state

## Files

- `index.html` - Main dashboard interface
- `style.css` - Dashboard-specific styling
- `app.js` - Client-side logic for dashboard functionality
- `api_dashboard.php` - API endpoint for loading user data and recent activity
- `test.py` - Automated tests for dashboard functionality
- `README.md` - This documentation file

## Page Layout

The page renders immediately with this static structure:

1. **Header Section**
   - Medical Office Assistant logo/title (always visible)
   - Welcome message - defaults to "Welcome, Patient" (personalized via API)
   - Auth button - shows "Login" by default, changes to "Logout" when authenticated

2. **Main Navigation Cards** (always visible)
   - **Chat with AI**: Start a medical consultation prep session
   - **View Medical Records**: Access your medical history
   - **My Profile**: Manage account settings

3. **Next/Last Appointment Section**
   - Shows the latest appointment from the database (past or future)
   - Heading shows "Next Appointment" if in future, "Last Appointment" if in past
   - Displays "(none)" if no appointments exist
   - Times stored in UTC in database, converted to user's local timezone in browser
   - Updates dynamically when authenticated

4. **Recent Activity Section**
   - Shows 3 placeholder chat items by default
   - Replaced with actual recent chats when authenticated
   - Each item has a "Resume" link

5. **Quick Actions** (always visible)
   - "Start New Chat" button
   - "View All Records" button

## Data Flow

1. **Initial Page Load**
   - Browser requests `/pg_main/index.html`
   - Static HTML renders immediately with placeholder content
   - Page is fully interactive and visually complete

2. **JavaScript Initialization**
   - `app.js` runs after DOM loads
   - Attempts to fetch data from `api_dashboard.php`
   - Includes session cookie automatically with request

3. **API Authentication Check**
   - `api_dashboard.php` validates the `aiofc_session` cookie
   - Uses `getUserIdFromToken()` to verify session validity
   - Returns 401 if not authenticated, 200 with data if valid

4. **Dynamic Content Update**
   - If authenticated: JavaScript updates DOM with personalized data
   - If not authenticated: Page remains with placeholder content
   - No redirects or page reloads needed

## API Endpoints

### `api_dashboard.php`
- **Method**: GET
- **Authentication**: Required via `aiofc_session` cookie
- **Authentication Check**: 
  - Retrieves session token from cookie
  - Validates using `getUserIdFromToken($token)`
  - Returns 401 if invalid/missing token
- **Success Response** (200): JSON with user data
```json
{
    "user": {
        "name": "John Doe",
        "email": "john@example.com"
    },
    "next_appointment": {
        "date": "2025-05-23",
        "time": "12:30",
        "doctor_name": "Dr. O'Connor",
        "appointment_type": "follow-up",
        "location": "Dublin"
    },
    "recent_chats": [
        {
            "id": "abc123",
            "preview": "Question about medications...",
            "timestamp": "2024-01-15 10:30:00"
        }
    ],
    "has_medical_records": true
}
```
Note: 
- `next_appointment` contains the latest appointment (could be past or future)
- `next_appointment` will be `null` if no appointments exist at all
- Times are stored in UTC format in the database
- JavaScript should convert UTC times to the user's local timezone for display
- JavaScript determines if appointment is past/future and updates heading accordingly
- **Error Response** (401): 
```json
{
    "error": "Not authenticated"
}
```

## Visual Design

- Utilizes the shared glassmorphic design system from infrastructure/glassmorphic.css
- Features dark blue frosted glass panels with white text throughout
- Purple gradient buttons for primary actions (Get Started, Chat, etc.)
- Backdrop blur effects create depth and visual hierarchy
- Clean card-based layout with rounded corners and soft shadows
- Responsive design optimized for both mobile and desktop viewing
- Smooth hover animations on all interactive elements

## Security

- **No client-side authentication checks** - page is public, data is protected
- **API-level authentication** - all sensitive data requires valid session
- **Token validation in PHP** - `getUserIdFromToken()` verifies every API request
- **Cookie-based sessions** - `aiofc_session` cookie sent automatically with API calls
- **No cross-user data access** - APIs only return data for authenticated user
- **Graceful degradation** - unauthenticated users see functional page with placeholders

## Testing

Run tests with:
```bash
python test.py
```

Tests verify:
- **Page loads successfully** without authentication
- **HTML contains all required elements** (navigation cards, buttons, sections)
- **API enforces authentication** (returns 401 without valid session)
- **Visual validation** confirms UI matches README specification
- **All required files exist** (HTML, CSS, JS, PHP, README)

The test correctly shows the page with placeholder content, demonstrating that:
- UI renders without server-side dependencies
- Authentication is properly enforced at the API level only
- Page is testable and functional for all users