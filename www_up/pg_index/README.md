# Landing Page (pg_index)

## Purpose

The landing page serves as the entry point for the Medical Office Assistant application. It presents a welcoming and informative introduction to the service, explaining how AI can help patients prepare for doctor appointments.

## Behavior

### Initial Load
- Displays a clean, professional landing page
- Shows application title "Medical Office Assistant"
- Presents key benefits and features
- Includes prominent "Get Started" buttons that link to ../pg_login/
- This page is loaded in an invisible iframe from the root index.html

### Features Section
- Describes how the AI reads and understands medical records
- Explains the chat-based preparation for appointments
- Lists supported document types (lab results, prescriptions, etc.)

### Visual Design
- Text and images displayed on rounded blue glass panels with frosted glass effect
- Background video (bg.mp4) plays continuously on loop
- Nearly white semi-transparent glass pane overlays the video for readability
- Professional typography with good contrast against the glass panels
- Responsive layout for mobile and desktop
- Accessible design with proper contrast ratios

### User Actions
- "Get Started" buttons simply link to ../pg_login/
- No authentication checks performed here
- pg_login will handle checking if user is already logged in
- All content is static (no API calls needed)

### Authentication Flow
1. User visits root URL → index.html loads pg_index in iframe
2. User clicks "Get Started" → navigates to pg_login
3. pg_login checks for existing session:
   - If authenticated → redirects to pg_main
   - If not authenticated → shows login form

## Files
- `index.php` - Main landing page structure (with cache prevention headers)
- `style.css` - Landing page styling with glass effects
- `app.js` - Minimal JavaScript for smooth scrolling
- `bg.mp4` - Background video that plays on loop
- `test.py` - Visual validation tests