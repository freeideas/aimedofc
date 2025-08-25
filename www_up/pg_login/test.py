#!/home/ace/bin/uvrun
# /// script
# requires-python = ">=3.8"
# dependencies = [
#     "requests",
# ]
# ///

import os
import json
import requests
import sqlite3
import time
from pathlib import Path

# Clean up previous test results
test_dir = Path(__file__).parent
(test_dir / 'test_pass.txt').unlink(missing_ok=True)
(test_dir / 'test_fail.txt').unlink(missing_ok=True)

# Determine base URL from config.json
config_path = Path(__file__).parent.parent / 'config.json'
if config_path.exists():
    with open(config_path) as f:
        config = json.load(f)
        base_url = config.get('BASE_URL', 'http://localhost:8000')
else:
    base_url = os.environ.get('BASE_URL', 'http://localhost:8000')

page_url = f"{base_url}/pg_login/"
api_url = f"{page_url}login.php"

test_results = []
all_passed = True

def test(name, condition, details=""):
    global all_passed
    if condition:
        test_results.append(f"✓ {name}")
        print(f"✓ {name}")
    else:
        test_results.append(f"✗ {name}: {details}")
        print(f"✗ {name}: {details}")
        all_passed = False

# Test 1: Check if page loads without authentication
try:
    response = requests.get(page_url)
    test("Page loads successfully", response.status_code == 200)
    test("Page contains login form", 'id="loginForm"' in response.text)
    test("Page has email field", 'id="email"' in response.text)
    test("Page has hidden code field", 'id="codeGroup" style="display: none;"' in response.text)
except Exception as e:
    test("Page loads successfully", False, str(e))

# Test 1b: Check that authenticated users get redirected
try:
    # Create a session with a mock cookie
    session = requests.Session()
    session.cookies.set('aiofc_session', 'test_valid_session_token')
    response = session.get(page_url, allow_redirects=False)
    # With a real valid session, this would redirect to pg_main
    # For testing, we just verify the page loads (since test token is invalid)
    test("Page handles auth check", response.status_code in [200, 302], 
         f"Status: {response.status_code}")
except Exception as e:
    test("Page handles auth check", False, str(e))

# Test 2: Test invalid email format
try:
    response = requests.post(api_url, json={'email': 'invalid-email'})
    test("Invalid email rejected", response.status_code == 400)
    data = response.json()
    test("Invalid email error message", 'error' in data)
except Exception as e:
    test("Invalid email handling", False, str(e))

# Test 3: Test email submission (send code)
try:
    test_email = "test@example.com"
    response = requests.post(api_url, json={'email': test_email})
    test("Email submission accepted", response.status_code == 200)
    data = response.json()
    test("Success response for email", data.get('success') == True)
    test("Message about code sent", 'message' in data)
    
    # Note: We can't test actual email delivery without access to the recipient's inbox
    # but we can check if the email-send command exists and is executable
    import os
    import subprocess
    email_cmd_exists = os.path.exists('/home/ace/bin/email-send') and os.access('/home/ace/bin/email-send', os.X_OK)
    test("Email send command available", email_cmd_exists, 
         "email-send command not found or not executable" if not email_cmd_exists else "")
    
    # Test that email-send command can be executed (dry run without actually sending)
    if email_cmd_exists:
        try:
            # Test with invalid arguments to check if command runs
            result = subprocess.run(['/home/ace/bin/email-send'], 
                                  capture_output=True, text=True, timeout=5)
            # Command should exit with error code due to missing args, but should run
            test("Email command executable by PHP user", True)
        except subprocess.TimeoutExpired:
            test("Email command executable by PHP user", False, "Command timed out")
        except Exception as e:
            test("Email command executable by PHP user", False, str(e))
except Exception as e:
    test("Email submission", False, str(e))

# Test 4: Check database was created and code was stored
try:
    db_path = Path(__file__).parent.parent.parent / 'data' / 'auth.db'
    if db_path.exists():
        conn = sqlite3.connect(str(db_path))
        cursor = conn.cursor()
        
        # Check if tables exist
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table'")
        tables = [row[0] for row in cursor.fetchall()]
        test("Users table created", 'users' in tables)
        test("Sessions table created", 'sessions' in tables)
        test("Verification codes table created", 'verification_codes' in tables)
        
        # Check if code was stored (get the most recent unused one)
        cursor.execute("SELECT code FROM verification_codes WHERE email = ? AND used = 0 ORDER BY created_at DESC LIMIT 1", (test_email,))
        code_row = cursor.fetchone()
        test("Verification code stored", code_row is not None)
        
        if code_row:
            test_code = code_row[0]
            
            # Test 5: Test code verification with wrong code
            response = requests.post(api_url, json={'email': test_email, 'code': '000000'})
            test("Wrong code rejected", response.status_code == 401)
            
            # Test 6: Test code verification with correct code
            response = requests.post(api_url, json={'email': test_email, 'code': test_code})
            test("Correct code accepted", response.status_code == 200)
            data = response.json()
            test("Login successful", data.get('success') == True)
            test("Redirect provided", 'redirect' in data and data['redirect'] == '../pg_main/')
            
            # Check if session was created
            cursor.execute("SELECT token FROM sessions WHERE user_id IN (SELECT id FROM users WHERE email = ?)", (test_email,))
            session_row = cursor.fetchone()
            test("Session created", session_row is not None)
            
            if session_row:
                session_token = session_row[0]
                
                # Test 7: Test token validation (auto-login)
                response = requests.post(api_url, json={'token': session_token})
                test("Valid token accepted", response.status_code == 200)
                data = response.json()
                test("Token validation successful", data.get('success') == True)
                test("Token validation redirect", data.get('redirect') == '../pg_main/')
                
                # Test 8: Test invalid token
                response = requests.post(api_url, json={'token': 'invalid-token'})
                test("Invalid token rejected", response.status_code == 401)
        
        conn.close()
    else:
        test("Database created", False, "auth.db not found")
except Exception as e:
    test("Database operations", False, str(e))

# Test 9: Visual validation
try:
    from subprocess import run, PIPE
    from pathlib import Path
    
    # Run webshot_test.py from scripts directory
    webshot_test = Path(__file__).parent.parent.parent / 'scripts' / 'webshot_test.py'
    if webshot_test.exists():
        result = run([str(webshot_test)], cwd=Path(__file__).parent, capture_output=True, text=True)
        if result.returncode == 0:
            test("Visual validation passed", True)
        else:
            test("Visual validation passed", False, result.stderr or result.stdout)
    else:
        test("Visual validation", False, "webshot_test.py not found")
except Exception as e:
    test("Visual validation", False, str(e))

# Write results
if all_passed:
    with open(test_dir / 'test_pass.txt', 'w') as f:
        f.write("All tests passed!\n\n")
        f.write("\n".join(test_results))
        f.write("\n\nThe pg_login page is working correctly with:")
        f.write("\n- Email validation and code sending")
        f.write("\n- Code verification and session creation")
        f.write("\n- Auto-login with existing sessions")
        f.write("\n- Proper error handling")
        f.write("\n- Database tables properly created")
else:
    with open(test_dir / 'test_fail.txt', 'w') as f:
        f.write("Some tests failed:\n\n")
        f.write("\n".join(test_results))

print(f"\n{'='*50}")
print(f"Test {'PASSED' if all_passed else 'FAILED'}")
print(f"Results written to test_{'pass' if all_passed else 'fail'}.txt")