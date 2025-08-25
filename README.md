# Medical Office Assistant Application

## Overview

Medical Office Assistant (aiofc) is a web-based medical assistant application that provides patients with AI-powered preparation for their doctor appointments. The system uses Google's Gemini Pro Flash to analyze patient medical records and answer questions, helping patients better understand their health information and prepare for consultations.

The root index.html uses an invisible iframe to load pg_index/, which presents a welcoming description of the application's features and benefits, along with "Get Started" buttons. These buttons link to pg_login/, which first checks if the user is already authenticated. If authenticated, it immediately redirects to pg_main/; otherwise, it shows the passwordless login form. Upon successful authentication, users are redirected to pg_main/, which contains the main application menu.

## Architecture

This application follows a flat, directory-based PHP framework with self-contained page apps. See [doc/PHP_FRAMEWORK.md](doc/PHP_FRAMEWORK.md) for the architectural philosophy and patterns.

### Authentication

The application uses a standalone passwordless authentication system with session management via the `aiofc_session` cookie. See [AUTH.md](AUTH.md) for complete details on the authentication implementation, database schema, and security considerations.

### Key Directories

- `www/` - Web root directory containing all web-accessible files
  - `pg_*/` - Self-contained page applications (excluding auth pages)
  - `infrastructure/` - Shared components (database, email, AI integration)
- `data/` - Private data directory (outside www)
  - `auth.db` - Authentication database (see [AUTH.md](AUTH.md))
  - `aioffice.db` - Application database (medical records, chat history)
  - `uploads/` - PDF source documents for medical records (referenced by source_filename)
- `tmp/` - Temporary files (gitignored)

## Development Guidelines

All code must follow the guidelines in [doc/PHP_CODING_GUIDELINES.md](doc/PHP_CODING_GUIDELINES.md). Key principles:
- Brevity above all - fewer lines of code is better
- Only write what's needed now
- Early returns to avoid nesting
- Minimal comments

## Core Features

### Chat Interface

Located in `pg_chat/`:

1. **Initial Message** (Hard-coded, no AI)
   ```
   "I have read your medical records, [Patient Name], and -- though I am not a doctor -- 
   I can review your reports and your prescriptions and prepare you for your next appointment"
   ```

2. **AI Conversation**
   - Patient types questions or concerns
   - System loads patient's medical records from database into LLM context
   - Medical records are provided by doctor's office in markdown format (no transformation needed)
   - Gemini Pro Flash reads patient's medical records and answers questions
   - AI provides contextual responses based on medical history
   - All interactions logged for audit purposes

## Technical Implementation

### Required Components

- **PHP 8.1+** with strict typing enabled
- **SQLite** for patient data (single file database)
- **Email System** - Uses `~/bin/email-send` command (not PHP mail or SMTP)
- **Gemini API** credentials for AI responses
- **Optional**: PHP GMP extension (falls back to random ID generation if not available)

### Database Schema

#### Application Database (aioffice.db)
Application-specific data, references auth.db users:

```sql
-- Patient profiles (extends auth users)
CREATE TABLE patients (
    user_id TEXT PRIMARY KEY,  -- References auth.db users.id
    full_name TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Medical records (provided by doctor's office, not uploaded by patients)
CREATE TABLE medical_records (
    record_id TEXT PRIMARY KEY,  -- Generated unique ID
    user_id TEXT NOT NULL,  -- References auth.db users.id
    record_title TEXT,  -- Human-readable title (e.g., 'Hormone Report', 'Blood Work Results')
    record_type TEXT,  -- e.g., 'lab_results', 'prescriptions', 'visit_notes'
    record_date DATE,
    content TEXT NOT NULL,  -- Markdown-formatted medical information
    source_filename TEXT,  -- Original PDF filename (can be NULL)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES patients(user_id)
);

-- Conversations (each patient can have multiple conversations)
CREATE TABLE conversations (
    conversation_id TEXT PRIMARY KEY,  -- Generated unique ID
    user_id TEXT NOT NULL,  -- References auth.db users.id
    title TEXT NOT NULL,  -- Conversation title (e.g., 'Questions about hormone therapy', 'Lab results discussion')
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES patients(user_id)
);

-- Chat messages within conversations
CREATE TABLE chat_messages (
    message_id TEXT PRIMARY KEY,  -- Generated unique ID
    conversation_id TEXT NOT NULL,  -- References conversations.conversation_id
    role TEXT CHECK(role IN ('patient', 'assistant')),
    message TEXT NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id)
);

-- Appointments (past and future)
CREATE TABLE appointments (
    appointment_id TEXT PRIMARY KEY,  -- Generated unique ID
    user_id TEXT NOT NULL,  -- References auth.db users.id
    doctor_name TEXT NOT NULL,
    appointment_date DATE NOT NULL,  -- Date in YYYY-MM-DD format
    appointment_time TIME,  -- Local time for display (legacy)
    appointment_datetime_utc DATETIME,  -- UTC datetime for accurate scheduling
    appointment_type TEXT,  -- e.g., 'routine', 'follow-up', 'specialist', 'lab_work'
    location TEXT,
    notes TEXT,
    status TEXT DEFAULT 'scheduled',  -- 'scheduled', 'completed', 'cancelled', 'no-show'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES patients(user_id)
);
```

For authentication database schema and cross-database access patterns, see [AUTH.md](AUTH.md).

## Development Workflow

1. Each developer works on their assigned `pg_*` directory
2. Every `pg_*` directory is a self-contained app (see [doc/PHP_FRAMEWORK.md](doc/PHP_FRAMEWORK.md))
3. Test locally before committing: `./scripts/test_every_pg.py` from project root
4. Use `loadCreds()` for all credential access
5. Keep infrastructure code minimal and reusable

## Configuration

### Email System
The application uses the system command `~/bin/email-send` for sending emails, not PHP mail() or SMTP. Verification codes are sent using:
```bash
~/bin/email-send <email> "Medical Office Assistant - Verification Code" "Your code is: 123456"
```

### Credentials
Use the `loadCreds()` function from `infrastructure/lib.php` to access configuration:

```php
<?php
require_once 'infrastructure/lib.php';

$config = loadCreds();
$geminiKey = $config['gemini_api_key'];
// Note: SMTP settings in config are not used - email uses ~/bin/email-send
```

## Page Directories

The application consists of self-contained page directories (`pg_*`), each with its own README.md documenting its purpose and implementation:

- **`pg_index/`** - Landing page with app description and login link
- **`pg_login/`** - Passwordless authentication (see [AUTH.md](AUTH.md) for details)
- **`pg_main/`** - Main menu/dashboard (loaded after successful login)
- **`pg_chat/`** - AI chat interface for medical consultation preparation
- **`pg_profile/`** - Patient profile management
- **`pg_records/`** - View medical records (read-only, provided by doctor's office)

Each `pg_*` directory is a complete micro-application with its own tests and documentation. See individual README.md files within each directory for specific implementation details.

## Directory Structure

```
/
├── www/                          # Web root directory
│   ├── index.html               # Invisible iframe loader for pg_index/
│   ├── pg_*/                    # Self-contained page applications
│   └── infrastructure/          # Shared backend components
├── data/                        # Private data directory
│   ├── auth.db                  # Authentication database
│   ├── aioffice.db              # Application database
│   ├── uploads/                 # PDF source documents
│   ├── credentials.json         # API keys and configuration (gitignored)
├── doc/                         # Documentation
│   ├── PHP_FRAMEWORK.md         # Architecture philosophy
│   ├── PHP_CODING_GUIDELINES.md # Code style guidelines
│   └── copy_src/                # Template files
├── scripts/                     # Testing and utility scripts
│   ├── test_every_pg.py         # Master test runner
│   └── webshot_test.py          # Visual validation script
└── tmp/                         # Temporary files (gitignored)
```

## Authentication Flow

The application uses a seamless authentication flow with session persistence:

1. **First Visit**: User visits root URL → sees landing page (pg_index) with "Get Started" button
2. **Get Started Click**: Button links to pg_login → pg_login checks for existing session
3. **Already Logged In**: If valid session exists → immediate redirect to pg_main
4. **Not Logged In**: Shows login form → user enters email → receives code → verifies → redirected to pg_main
5. **Session Persistence**: Sessions last 32 days and are refreshed on each use
6. **Return Visits**: User with valid session clicking "Get Started" goes directly to pg_main
7. **Logout**: Explicitly invalidates session → user returns to landing page

### Key Components
- **Root index.html**: Simple iframe loader for pg_index
- **pg_index**: Landing page with "Get Started" buttons (no auth logic)
- **pg_login/index.php**: Checks for existing session before showing login form
- **pg_main**: Dashboard accessible only with valid session
- **Session Cookie**: `aiofc_session` with 32-day expiry, refreshed on use

## Getting Started

1. Clone repository
2. Create `www/config.json` with: `{"BASE_URL": "https://your-domain.com/path"}`
3. Ensure `data/` directory is writable by web server: `chmod 777 data/`
4. Copy lib.php: `cp doc/copy_src/lib.php www/infrastructure/lib.php`
5. Databases are created automatically on first use (no manual setup needed)
6. Test email system: `~/bin/email-send test@example.com "Test" "Body"`
7. Configure Gemini API credentials
8. Start development server

### Medical Records Management

- Medical records are provided by the doctor's office in markdown format
- Records are stored in the `medical_records` table in the database
- Each record is associated with a patient's user_id
- Multiple records can exist per patient (lab results, prescriptions, visit notes, etc.)
- All relevant medical records are loaded into LLM context when answering questions
- Records are read-only from the patient's perspective (no upload functionality)
- The doctor's office manages and inserts records directly into the database

### Utility Functions

The `www/infrastructure/lib.php` file provides utility functions used throughout the application. If this file doesn't exist yet, copy it from [doc/copy_src/lib.php](doc/copy_src/lib.php). See [AUTH.md](AUTH.md) for details on authentication-related utilities.

### Testing Scripts

The testing scripts are located in the `scripts/` directory:
- `test_every_pg.py` - Master test runner that discovers and runs all `pg_*/test.py` files
- `webshot_test.py` - Visual validation script using Claude Code CLI to validate screenshots against README.md specifications

## Important Notes

- Use `loadCreds()` function for all credential access
- Follow the flat architecture - no nested page directories
- Keep infrastructure code minimal and reusable
- Medical records are stored in the database, not as files
- All web-accessible files must be in `www/` directory
- Sensitive files (database, configs) stay outside `www/`
