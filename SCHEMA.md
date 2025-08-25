# Database Schema Documentation

The Medical Office Assistant application uses two SQLite databases for data storage:

1. **auth.db** - Authentication database (see [AUTH.md](AUTH.md) for details)
2. **aioffice.db** - Application database

## Application Database (aioffice.db)

### patients
Extends auth users with patient-specific information:

```sql
CREATE TABLE patients (
    user_id TEXT PRIMARY KEY,  -- References auth.db users.id
    full_name TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### medical_records
Medical records provided by doctor's office (not uploaded by patients):

```sql
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
```

### conversations
Each patient can have multiple chat conversations:

```sql
CREATE TABLE conversations (
    conversation_id TEXT PRIMARY KEY,  -- Generated unique ID
    user_id TEXT NOT NULL,  -- References auth.db users.id
    title TEXT NOT NULL,  -- Conversation title (auto-generated from first message)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_flag INTEGER DEFAULT 0,  -- Soft delete flag (0=active, 1=deleted)
    FOREIGN KEY (user_id) REFERENCES patients(user_id)
);
```

**Sorting**: Conversations should be displayed sorted by `updated_at` DESC (most recent first). The `updated_at` field should be updated whenever a new message is added to the conversation.

### chat_messages
Messages within conversations:

```sql
CREATE TABLE chat_messages (
    message_id TEXT PRIMARY KEY,  -- Generated unique ID
    conversation_id TEXT NOT NULL,  -- References conversations.conversation_id
    role TEXT CHECK(role IN ('patient', 'assistant')),
    message TEXT NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted INTEGER DEFAULT 0,  -- Soft delete flag (0=active, 1=deleted)
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id)
);
```

### appointments
Past and future appointments:

```sql
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

## Soft Delete Implementation

Both `conversations` and `chat_messages` tables implement soft delete functionality:

- **Conversations**: The `deleted_flag` column (0=active, 1=deleted) marks conversations as deleted without removing them from the database
- **Messages**: The `deleted` column (0=active, 1=deleted) marks individual messages as deleted
- **UI Behavior**: Deleted items are hidden from the user interface but remain in the database for audit/recovery purposes
- **Cascade**: When a conversation is soft-deleted, its messages remain intact but are effectively hidden since the conversation won't be displayed

## Cross-Database References

The application database references the authentication database for user information:

```sql
-- Example: Get patient with auth info
SELECT 
    p.full_name,
    u.email
FROM patients p
JOIN '../auth.db'.users u ON p.user_id = u.id
WHERE p.user_id = ?;
```

For more details on the authentication database schema, see [AUTH.md](AUTH.md).