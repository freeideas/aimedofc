#!/home/ace/bin/uvrun
# /// script
# requires-python = ">=3.8"
# dependencies = [
#     "requests",
#     "beautifulsoup4",
# ]
# ///

import os
import sys
import requests
from bs4 import BeautifulSoup
import json
import sqlite3
from pathlib import Path
import tempfile
import shutil

# Test configuration
BASE_URL = "http://localhost:8080"
TEST_EMAIL = "test_records@example.com"
TEST_USER_ID = "test_user_records_123"

def setup_test_environment():
    """Set up test databases and sample data"""
    print("Setting up test environment...")
    
    # Create data directories if they don't exist
    os.makedirs("../../data/uploads", exist_ok=True)
    
    # Set up auth database
    auth_db_path = "../../data/auth.db"
    auth_conn = sqlite3.connect(auth_db_path)
    auth_cur = auth_conn.cursor()
    
    # Create auth tables if not exist
    auth_cur.execute("""
        CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY,
            email TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    """)
    
    auth_cur.execute("""
        CREATE TABLE IF NOT EXISTS sessions (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            token TEXT UNIQUE NOT NULL,
            device_info TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    """)
    
    # Insert test user
    auth_cur.execute("DELETE FROM users WHERE email = ?", (TEST_EMAIL,))
    auth_cur.execute("INSERT INTO users (id, email) VALUES (?, ?)", 
                     (TEST_USER_ID, TEST_EMAIL))
    
    # Create test session
    test_session_id = "test_session_records_" + os.urandom(16).hex()
    test_token = os.urandom(32).hex()
    auth_cur.execute("""
        INSERT INTO sessions (id, user_id, token, expires_at)
        VALUES (?, ?, ?, datetime('now', '+32 days'))
    """, (test_session_id, TEST_USER_ID, test_token))
    
    auth_conn.commit()
    auth_conn.close()
    
    # Return the token to use as cookie value
    token_to_return = test_token
    
    # Set up app database
    app_db_path = "../../data/aioffice.db"
    app_conn = sqlite3.connect(app_db_path)
    app_cur = app_conn.cursor()
    
    # Create tables
    app_cur.execute("""
        CREATE TABLE IF NOT EXISTS patients (
            user_id TEXT PRIMARY KEY,
            full_name TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    """)
    
    app_cur.execute("""
        CREATE TABLE IF NOT EXISTS medical_records (
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
    
    # Insert test patient
    app_cur.execute("DELETE FROM patients WHERE user_id = ?", (TEST_USER_ID,))
    app_cur.execute("INSERT INTO patients (user_id, full_name) VALUES (?, ?)",
                    (TEST_USER_ID, "Test Patient"))
    
    # Create sample PDF files
    sample_pdfs = [
        ("lab_results_2024.pdf", "Lab Results - Blood Work", "lab_results", "2024-01-15"),
        ("prescription_2024.pdf", "Prescription - Medication", "prescriptions", "2024-01-10"),
        ("visit_notes_2024.pdf", "Visit Notes - Checkup", "visit_notes", "2024-01-05"),
    ]
    
    # Clear existing test records
    app_cur.execute("DELETE FROM medical_records WHERE user_id = ?", (TEST_USER_ID,))
    
    for idx, (filename, title, record_type, date) in enumerate(sample_pdfs):
        # Create a simple PDF file (just a text file for testing)
        pdf_path = f"../../data/uploads/{filename}"
        with open(pdf_path, "wb") as f:
            # Write a minimal PDF header (enough for testing)
            f.write(b"%PDF-1.4\n")
            f.write(b"1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n")
            f.write(b"2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n")
            f.write(b"3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n")
            f.write(b"xref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n")
            f.write(b"0000000058 00000 n\n0000000115 00000 n\ntrailer\n")
            f.write(b"<< /Size 4 /Root 1 0 R >>\nstartxref\n195\n%%EOF\n")
        
        # Insert record into database
        record_id = f"test_record_{idx+1}"
        app_cur.execute("""
            INSERT INTO medical_records 
            (record_id, user_id, record_title, record_type, record_date, content, source_filename)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        """, (record_id, TEST_USER_ID, title, record_type, date, 
              f"Sample medical content for {title}", filename))
    
    app_conn.commit()
    app_conn.close()
    
    return token_to_return

def test_page_loads():
    """Test that the page loads with authentication"""
    print("\n1. Testing page load with authentication...")
    
    token = setup_test_environment()
    cookies = {"aiofc_session": token}
    
    response = requests.get(f"{BASE_URL}/pg_records/", cookies=cookies)
    assert response.status_code == 200, f"Expected 200, got {response.status_code}"
    
    soup = BeautifulSoup(response.text, 'html.parser')
    assert soup.find('h1', string='Medical Records'), "Page should show Medical Records header"
    
    print("   ✓ Page loads successfully with authentication")

def test_redirect_without_auth():
    """Test that page redirects to login without authentication"""
    print("\n2. Testing redirect without authentication...")
    
    response = requests.get(f"{BASE_URL}/pg_records/", allow_redirects=False)
    assert response.status_code == 302, f"Expected 302 redirect, got {response.status_code}"
    assert '/pg_login/' in response.headers.get('Location', ''), "Should redirect to login"
    
    print("   ✓ Redirects to login without authentication")

def test_document_list():
    """Test that documents are listed in sidebar"""
    print("\n3. Testing document list in sidebar...")
    
    token = setup_test_environment()
    cookies = {"aiofc_session": token}
    
    response = requests.get(f"{BASE_URL}/pg_records/", cookies=cookies)
    soup = BeautifulSoup(response.text, 'html.parser')
    
    # Check for document items
    doc_items = soup.find_all(class_='record-item')
    assert len(doc_items) == 3, f"Expected 3 documents, found {len(doc_items)}"
    
    # Check document titles
    titles = [item.find(class_='record-title').text for item in doc_items]
    assert "Lab Results - Blood Work" in titles, "Should show lab results"
    assert "Prescription - Medication" in titles, "Should show prescription"
    assert "Visit Notes - Checkup" in titles, "Should show visit notes"
    
    print("   ✓ Documents listed correctly in sidebar")

def test_pdf_endpoint():
    """Test that PDF endpoint serves files correctly"""
    print("\n4. Testing PDF endpoint...")
    
    token = setup_test_environment()
    cookies = {"aiofc_session": token}
    
    # Test valid PDF request
    response = requests.get(f"{BASE_URL}/pg_records/get_pdf.php?id=test_record_1", 
                           cookies=cookies)
    assert response.status_code == 200, f"Expected 200, got {response.status_code}"
    assert response.headers.get('Content-Type') == 'application/pdf', "Should return PDF content type"
    assert response.content.startswith(b'%PDF'), "Should return PDF content"
    
    # Test invalid record ID
    response = requests.get(f"{BASE_URL}/pg_records/get_pdf.php?id=invalid", 
                           cookies=cookies)
    assert response.status_code == 404, "Should return 404 for invalid record"
    
    # Test without authentication
    response = requests.get(f"{BASE_URL}/pg_records/get_pdf.php?id=test_record_1")
    assert response.status_code == 403, "Should return 403 without authentication"
    
    print("   ✓ PDF endpoint works correctly")

def test_pdf_iframe():
    """Test that PDF iframe is rendered with correct source"""
    print("\n5. Testing PDF iframe rendering...")
    
    token = setup_test_environment()
    cookies = {"aiofc_session": token}
    
    # Load page with specific record selected
    response = requests.get(f"{BASE_URL}/pg_records/?id=test_record_1", cookies=cookies)
    soup = BeautifulSoup(response.text, 'html.parser')
    
    # Check for iframe
    iframe = soup.find('iframe', class_='pdf-frame')
    assert iframe is not None, "Should have PDF iframe"
    assert iframe.get('src') == 'get_pdf.php?id=test_record_1', "Iframe should point to correct PDF"
    
    # Check that the selected item is marked as active
    active_item = soup.find(class_='record-item active')
    assert active_item is not None, "Should have active record item"
    
    print("   ✓ PDF iframe rendered correctly")

def test_responsive_layout():
    """Test that layout elements are present"""
    print("\n6. Testing responsive layout elements...")
    
    token = setup_test_environment()
    cookies = {"aiofc_session": token}
    
    response = requests.get(f"{BASE_URL}/pg_records/", cookies=cookies)
    soup = BeautifulSoup(response.text, 'html.parser')
    
    # Check layout structure
    assert soup.find(class_='header'), "Should have header"
    assert soup.find(class_='sidebar'), "Should have sidebar"
    assert soup.find(class_='content'), "Should have content area"
    assert soup.find(class_='back-link'), "Should have back to dashboard link"
    
    # Check that external stylesheet is included
    link = soup.find('link', {'rel': 'stylesheet', 'href': 'style.css'})
    assert link is not None, "Should have external stylesheet"
    
    print("   ✓ Layout elements present and responsive")

def cleanup_test_environment():
    """Clean up test data"""
    print("\nCleaning up test environment...")
    
    # Clean up test PDFs
    for filename in ["lab_results_2024.pdf", "prescription_2024.pdf", "visit_notes_2024.pdf"]:
        pdf_path = f"../../data/uploads/{filename}"
        if os.path.exists(pdf_path):
            os.remove(pdf_path)
    
    # Clean up database entries
    app_conn = sqlite3.connect("../../data/aioffice.db")
    app_cur = app_conn.cursor()
    app_cur.execute("DELETE FROM medical_records WHERE user_id = ?", (TEST_USER_ID,))
    app_cur.execute("DELETE FROM patients WHERE user_id = ?", (TEST_USER_ID,))
    app_conn.commit()
    app_conn.close()
    
    auth_conn = sqlite3.connect("../../data/auth.db")
    auth_cur = auth_conn.cursor()
    auth_cur.execute("DELETE FROM sessions WHERE user_id = ?", (TEST_USER_ID,))
    auth_cur.execute("DELETE FROM users WHERE id = ?", (TEST_USER_ID,))
    auth_conn.commit()
    auth_conn.close()
    
    print("   ✓ Test environment cleaned up")

def main():
    """Run all tests"""
    print("=" * 50)
    print("Testing pg_records Page")
    print("=" * 50)
    
    # Change to script directory
    os.chdir(os.path.dirname(os.path.abspath(__file__)))
    
    try:
        test_page_loads()
        test_redirect_without_auth()
        test_document_list()
        test_pdf_endpoint()
        test_pdf_iframe()
        test_responsive_layout()
        
        print("\n" + "=" * 50)
        print("✓ All tests passed!")
        print("=" * 50)
        
    except AssertionError as e:
        print(f"\n✗ Test failed: {e}")
        sys.exit(1)
    except Exception as e:
        print(f"\n✗ Unexpected error: {e}")
        sys.exit(1)
    finally:
        cleanup_test_environment()

if __name__ == "__main__":
    main()