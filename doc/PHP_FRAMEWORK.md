# PHP Architecture - API-First with Self-Contained Page Apps

## Overview

This application uses an API-first architecture with self-contained page directories that function as independent micro-applications. Each page directory (`pg_*`) is a complete, testable unit with its own frontend, APIs, tests, and documentation.

**Critical principle**: All UI pages render completely without any server-side data. Authentication and personalization happen through JavaScript API calls after the page loads. This ensures pages are always testable and accessible.

## Core Philosophy

### Self-Contained Page Apps
- Each `pg_*` directory is an independent micro-application
- Every page directory contains:
  - `index.html` or `index.php` (entry point)
  - `test.py` (automated endpoint testing)
  - `README.md` (purpose and mechanisms documentation)
  - Associated assets (CSS, JS, API endpoints)

### API-First Design
- PHP scripts implement JSON REST APIs exclusively
- Frontend is pure HTML/CSS/JavaScript
- Complete separation of presentation and logic

### Flexible Entry Points
- Use `index.html` for static pages with JavaScript-driven interactions
- Use `index.php` when server-side rendering is needed (e.g., JSON REST API endpoints)
- Root project can also use `index.php` if required
- Web server automatically serves `index.html` or `index.php` when accessing directory URLs (e.g., `/pg_login/` serves `pg_login/index.html`)

### Testing Strategy
- **API Endpoints**: Tested via `test.py` scripts in each directory
- **HTML Pages**: Tested via `webshot` screenshot + AI validation against README.md expectations
- Each page directory's tests are self-contained and independently runnable

### Advantages
1. **Modularity**: Each page directory is a complete, deployable unit
2. **Easy Testing**: Every endpoint has its own test suite
3. **Clear Documentation**: Each directory's README explains its specific purpose
4. **Frontend Independence**: UI can be built and tested without a running server
5. **Clear Contracts**: JSON APIs provide clear data contracts between frontend and backend
6. **Scalability**: Frontend can be served from CDN, APIs can scale independently

## Directory Structure Pattern

```
/
├── www/                          # Web root directory
│   ├── index.php or index.html  # Root entry point
│   ├── pg_*/                    # Self-contained page applications
│   │   ├── index.html or index.php  # Page entry point
│   │   ├── style.css            # Page-specific styles (optional)
│   │   ├── app.js               # JavaScript logic (optional)
│   │   ├── api_*.php            # JSON API endpoints (optional)
│   │   ├── test.py              # Page-specific tests (required)
│   │   └── README.md            # Page documentation (required)
│   └── infrastructure/          # Shared backend components
│       └── [organized by function]
├── data/                        # Private data (outside web root)
├── doc/                         # Documentation
└── scripts/                     # Testing and utility scripts
    ├── test_every_pg.py         # Master test runner
    └── webshot_test.py          # Visual validation script
```

## Key Principles

### 1. UI Design for Testability
- **Pages MUST render complete UI without any server-side data access**
- Static HTML loads with meaningful placeholder data that looks realistic
- The ONLY visual differences between authenticated and unauthenticated states:
  1. Placeholder data instead of real user data (e.g., "Patient" instead of "John Doe")
  2. "LOGIN" button instead of "LOGOUT" button in the header
- JavaScript loads real data after page render, replacing placeholders if authenticated
- This ensures webshot captures representative UI without authentication
- Static GET of index.html should be visually identical to authenticated state (except for the two differences above)

### 2. JavaScript Handles API Calls
- Frontend JavaScript makes fetch requests to PHP API endpoints
- Complete separation between UI logic and server communication
- All server interactions happen via JSON APIs

### 3. PHP Scripts Return JSON Only
- PHP files are pure API endpoints that return JSON
- No HTML generation in PHP files
- Authentication checks happen ONLY in PHP API endpoints, never in JavaScript
- PHP APIs validate session tokens from cookies using `getUserIdFromToken()`
- Clear separation of concerns: presentation (HTML/JS) vs data/security (PHP)

### 4. API Naming Convention
- API files use `api_` prefix: `api_send_code.php`, `api_validate.php`
- This clearly distinguishes APIs from static files
- APIs live in the same directory as their related frontend

## Testing Strategy

### Self-Contained Page Testing
- Each `pg_*` directory contains its own `test.py` script
- Tests validate both API endpoints and "happy path" expected behaviors  
- Tests are self-contained and can run independently
- A master `test_every_pg.py` file exists in the scripts/ directory that automatically discovers and runs all individual page test.py files
- Test scripts read `www/config.json` to obtain the BASE_URL for the deployment
- The config.json file should contain: `{"BASE_URL": "https://your-domain.com/path"}`
- URLs are built dynamically using the base URL from config.json

### Test Result Files
Each `test.py` script must:
1. Start by deleting any existing `test_pass.txt` and `test_fail.txt` files in its directory
2. Run all tests for that page
3. Write exactly ONE result file:
   - `test_pass.txt` - Contains tests performed and AI analysis (if all tests pass)
   - `test_fail.txt` - Contains error messages and AI analysis (if any test fails)
4. This allows for easy programmatic checking of test results by other tools

### Visual Testing for HTML Pages
- **Centralized Visual Testing**: The `scripts/webshot_test.py` script provides automated visual validation
- Each pg_* test.py runs webshot_test.py directly from `scripts/` directory
- Creates `webshot.png` in each pg_* directory as a permanent reference screenshot
- Uses Claude Code CLI (`~/.npm-global/bin/claude`) to validate screenshots against README.md
- AI analysis is automatically included in test_pass.txt or test_fail.txt files

### Test URL Construction and Visual Testing

Each page's test.py script will:
- Dynamically determine the base URL from configuration files
- Construct API endpoint URLs for testing
- Use the webshot command to capture UI screenshots for visual validation
- Validate screenshots against the page's README.md specifications

### Running All Tests

To run all tests in the project:
```bash
# From the project root directory
./scripts/test_every_pg.py
```

The master test_every_pg.py script will:
- Automatically discover all pg_*/test.py files in www/
- Run each test suite in sequence
- Report results for all page tests
- Provide a summary of test outcomes

### Automated Visual Testing with webshot_test.py

The `scripts/webshot_test.py` provides centralized automated visual validation for UI pages:

**Features:**
- Automatically captures screenshots using the `webshot` command
- Uses Claude Code CLI to validate screenshots against README.md specifications  
- Saves `webshot.png` in each pg_* directory as a permanent reference
- Detects base URL from configuration files
- Provides clear pass/fail verdicts with detailed AI analysis
- AI analysis is automatically included in test_pass.txt or test_fail.txt

**Usage:**
Each pg_* test.py runs webshot_test.py directly from the scripts directory:
```python
# In pg_*/test.py:
webshot_test = Path(__file__).parent.parent.parent / 'scripts' / 'webshot_test.py'
result = subprocess.run([str(webshot_test)], cwd=Path(__file__).parent)
```

No copying needed - all pages use the same centralized script.

**Requirements:**
- `webshot` command must be available
- Claude Code CLI installed at `~/.npm-global/bin/claude`
- README.md in each page directory describing expected UI behavior

## Development Workflow

### Creating a New Page Directory
1. Create `pg_<name>/` directory
2. Add `README.md` documenting purpose and mechanisms
3. Choose entry point:
   - `index.html` for UI pages
   - `index.php` for API-only endpoints
4. Add `test.py` for automated testing
5. Implement functionality (HTML/CSS/JS/PHP as needed)

### For Fully Static Pages
- Create only HTML/CSS/JS files
- Include placeholder data that resembles real content
- Add LOGIN/LOGOUT button that switches based on session state
- Add `test.py` using webshot for visual validation
- No PHP needed until server interaction is required

### Adding Server Functionality
1. Keep existing HTML/CSS/JS
2. Add `api_*.php` files as needed
3. Update JavaScript to call new APIs
4. Update `test.py` to test new endpoints

### Progressive Enhancement
- Site can start completely static
- Add PHP APIs only when needed for:
  - Database operations
  - Authentication
  - Email sending
  - External API calls

## Benefits

1. **Self-Contained Apps**: Each page directory is complete with tests and docs
2. **Testability**: Every endpoint has automated tests in its directory
3. **Development Speed**: Frontend developers work without backend dependencies
4. **Clear Separation**: Business logic in PHP, presentation in HTML/JS
5. **Documentation**: Each directory's README provides local context
6. **Incremental Complexity**: Start with static HTML, add APIs as needed
7. **Performance**: Static files can be cached and served quickly
8. **Debugging**: Easy to inspect API calls in browser dev tools
9. **Modularity**: Pages can be developed, tested, and deployed independently

## Example: Authentication-Aware Page

### How Authentication Works in Practice

```html
<!-- index.html - Always renders completely -->
<header>
  <h1>AI Office</h1>
  <span id="userName">Welcome, Patient</span>  <!-- Placeholder name -->
  <button id="authBtn">LOGIN</button>          <!-- Default to LOGIN -->
</header>

<div class="content">
  <!-- All UI elements render with placeholder data -->
  <div class="recent-items">
    <h2>Recent Activity</h2>
    <div id="activity">
      <!-- Placeholder items that look real -->
      <div>Sample activity from 2 hours ago</div>
      <div>Sample activity from yesterday</div>
    </div>
  </div>
</div>
```

```javascript
// app.js - Updates content IF authenticated
document.addEventListener('DOMContentLoaded', async () => {
  // Try to load user data from API
  const response = await fetch('api_dashboard.php');
  
  if (response.ok) {
    // User is authenticated - update UI
    const data = await response.json();
    document.getElementById('userName').textContent = `Welcome, ${data.user.name}`;
    document.getElementById('authBtn').textContent = 'LOGOUT';
    document.getElementById('activity').innerHTML = data.recentActivity.map(...);
  } else {
    // Not authenticated - page already shows placeholder content
    // Change button to LOGIN if needed
    document.getElementById('authBtn').onclick = () => location.href = '/pg_login/';
  }
});
```

```php
// api_dashboard.php - ONLY place authentication is checked
<?php
require_once '../infrastructure/lib.php';

$token = $_COOKIE['aiofc_session'] ?? '';
$userId = getUserIdFromToken($token);

if (!$userId) {
    http_response_code(401);
    die(json_encode(['error' => 'Not authenticated']));
}

// Return user-specific data
echo json_encode([
    'user' => ['name' => getUserName($userId)],
    'recentActivity' => getRecentActivity($userId)
]);
```

## Example: Generic Page Application

### pg_example/ Directory Structure
```
pg_example/
├── index.html           # Main UI with placeholder data
├── style.css            # Page-specific styles (optional)
├── app.js               # Client-side logic (optional)
├── api_action.php       # API endpoint (optional)
├── test.py              # Automated tests (required)
└── README.md            # Documentation (required)
```

Key points:
- HTML always renders completely with placeholder content
- JavaScript enhances the page if user is authenticated
- PHP APIs are the ONLY place authentication is validated
- Visual difference between states is minimal (data + LOGIN/LOGOUT button)
- Each `pg_*` directory is completely self-contained
- No dependencies between different `pg_*` directories
- Shared code goes in `infrastructure/`