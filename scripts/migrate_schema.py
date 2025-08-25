#!/home/ace/bin/uvrun
# /// script
# requires-python = ">=3.8"
# dependencies = []
# ///

import sqlite3
import os
import sys
from datetime import datetime

def migrate_database():
    db_path = 'data/aioffice.db'
    
    if not os.path.exists(db_path):
        print(f"Database {db_path} does not exist")
        return False
    
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    
    try:
        # Begin transaction
        conn.execute("BEGIN TRANSACTION")
        
        # Create backup tables with new schema
        print("Creating new tables with descriptive column names...")
        
        # Create new patients table (no change needed, already has user_id)
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS patients_new (
                user_id TEXT PRIMARY KEY,
                full_name TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)
        
        # Create new medical_records table with record_id
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS medical_records_new (
                record_id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL,
                record_title TEXT,
                record_type TEXT,
                record_date DATE,
                content TEXT NOT NULL,
                source_filename TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES patients(user_id)
            )
        """)
        
        # Create conversations table (new)
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS conversations (
                conversation_id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL,
                title TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES patients(user_id)
            )
        """)
        
        # Create new chat_messages table with message_id and conversation_id
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS chat_messages_new (
                message_id TEXT PRIMARY KEY,
                conversation_id TEXT NOT NULL,
                role TEXT CHECK(role IN ('patient', 'assistant')),
                message TEXT NOT NULL,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id)
            )
        """)
        
        # Create new appointments table with appointment_id
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS appointments_new (
                appointment_id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL,
                doctor_name TEXT NOT NULL,
                appointment_date DATE NOT NULL,
                appointment_time TIME,
                appointment_datetime_utc DATETIME,
                appointment_type TEXT,
                location TEXT,
                notes TEXT,
                status TEXT DEFAULT 'scheduled',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES patients(user_id)
            )
        """)
        
        # Migrate existing data
        print("Migrating existing data...")
        
        # Migrate patients (fixing column name from 'name' to 'full_name')
        cursor.execute("""
            INSERT INTO patients_new (user_id, full_name, created_at, updated_at)
            SELECT user_id, name, created_at, updated_at FROM patients
        """)
        
        # Migrate medical_records (renaming id to record_id)
        cursor.execute("""
            INSERT INTO medical_records_new (record_id, user_id, record_title, record_type, 
                                            record_date, content, source_filename, created_at)
            SELECT id, user_id, record_title, record_type, 
                   record_date, content, source_filename, created_at 
            FROM medical_records
        """)
        
        # Migrate appointments (renaming id to appointment_id)
        cursor.execute("""
            INSERT INTO appointments_new (appointment_id, user_id, doctor_name, appointment_date,
                                        appointment_time, appointment_datetime_utc, appointment_type,
                                        location, notes, status, created_at, updated_at)
            SELECT id, user_id, doctor_name, appointment_date,
                   appointment_time, appointment_datetime_utc, appointment_type,
                   location, notes, status, created_at, updated_at
            FROM appointments
        """)
        
        # Migrate chat messages - create a default conversation for each user's existing messages
        print("Creating default conversations for existing chat messages...")
        cursor.execute("""
            SELECT DISTINCT user_id FROM chat_messages
        """)
        users_with_messages = cursor.fetchall()
        
        for (user_id,) in users_with_messages:
            # Generate a unique conversation ID
            import hashlib
            conv_id = hashlib.sha256(f"default_{user_id}_{datetime.now().isoformat()}".encode()).hexdigest()[:16]
            
            # Create a default conversation for this user
            cursor.execute("""
                INSERT INTO conversations (conversation_id, user_id, title, created_at)
                VALUES (?, ?, 'Previous Conversation', CURRENT_TIMESTAMP)
            """, (conv_id, user_id))
            
            # Migrate all messages for this user to the new conversation
            cursor.execute("""
                INSERT INTO chat_messages_new (message_id, conversation_id, role, message, timestamp)
                SELECT id, ?, role, message, timestamp
                FROM chat_messages
                WHERE user_id = ?
            """, (conv_id, user_id))
        
        # Drop old tables and rename new ones
        print("Replacing old tables with new schema...")
        cursor.execute("DROP TABLE IF EXISTS patients")
        cursor.execute("DROP TABLE IF EXISTS medical_records")
        cursor.execute("DROP TABLE IF EXISTS chat_messages")
        cursor.execute("DROP TABLE IF EXISTS appointments")
        
        cursor.execute("ALTER TABLE patients_new RENAME TO patients")
        cursor.execute("ALTER TABLE medical_records_new RENAME TO medical_records")
        cursor.execute("ALTER TABLE chat_messages_new RENAME TO chat_messages")
        cursor.execute("ALTER TABLE appointments_new RENAME TO appointments")
        
        # Commit transaction
        conn.commit()
        print("Migration completed successfully!")
        
        # Show new schema
        print("\nNew schema:")
        cursor.execute("SELECT sql FROM sqlite_master WHERE type='table'")
        for row in cursor.fetchall():
            if row[0]:
                print(f"\n{row[0]};")
        
        return True
        
    except Exception as e:
        conn.rollback()
        print(f"Migration failed: {e}")
        return False
    finally:
        conn.close()

if __name__ == "__main__":
    os.chdir('/home/ace/prjx/aiofc')
    success = migrate_database()
    sys.exit(0 if success else 1)