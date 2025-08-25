#!/home/ace/bin/uvrun
# /// script
# requires-python = ">=3.8"
# dependencies = [
#     "requests",
# ]
# ///

import json
import os
import sys
import subprocess
from pathlib import Path
import requests
import time

# Delete any existing test result files
test_dir = Path(__file__).parent
for file in ['test_pass.txt', 'test_fail.txt']:
    (test_dir / file).unlink(missing_ok=True)

# Read config to get base URL
config_path = Path(__file__).parent.parent / 'config.json'
if not config_path.exists():
    with open(test_dir / 'test_fail.txt', 'w') as f:
        f.write("ERROR: config.json not found\n")
        f.write("Please create www/config.json with: {\"BASE_URL\": \"https://your-domain.com/path\"}\n")
    sys.exit(1)

with open(config_path) as f:
    config = json.load(f)
    base_url = config['BASE_URL'].rstrip('/')

# Test results
test_results = []
all_passed = True

def test(name, condition, details=""):
    global all_passed
    passed = bool(condition)
    if not passed:
        all_passed = False
    status = "✓" if passed else "✗"
    result = f"{status} {name}"
    if details:
        result += f" - {details}"
    test_results.append(result)
    print(result)
    return passed

print("Testing pg_main...")
print("=" * 50)

# Test 1: Check if page loads
try:
    response = requests.get(f"{base_url}/pg_main/", timeout=10)
    test("Page loads", response.status_code == 200, f"Status: {response.status_code}")
    page_content = response.text
except Exception as e:
    test("Page loads", False, str(e))
    page_content = ""

# Test 2: Check for required HTML elements
if page_content:
    test("Has welcome message", "Welcome" in page_content)
    test("Has auth button", "authBtn" in page_content and ("Login" in page_content or "Logout" in page_content))
    test("Has action cards", "action-card" in page_content)
    test("Has chat link", "pg_chat" in page_content)
    test("Has records link", "pg_records" in page_content)
    test("Has profile link", "pg_profile" in page_content)
    test("Has recent activity section", "Recent" in page_content or "recent" in page_content)
    test("Has appointment section", "appointmentHeading" in page_content)
    test("Has appointment placeholder date", "January 1, 2000" in page_content)
    test("Has appointment placeholder doctor", "Dr. Smith" in page_content)

# Test 3: Check API endpoint (should require authentication)
try:
    response = requests.get(f"{base_url}/pg_main/api_dashboard.php", timeout=10)
    test("API requires authentication", response.status_code == 401, f"Status: {response.status_code}")
except Exception as e:
    test("API endpoint exists", False, str(e))

# Test 4: Check logout API endpoint exists
try:
    response = requests.post(f"{base_url}/pg_main/api_logout.php", timeout=10)
    test("Logout API endpoint exists", response.status_code == 200, f"Status: {response.status_code}")
except Exception as e:
    test("Logout API endpoint exists", False, str(e))

# Test 5: Check for required files
required_files = ['index.html', 'style.css', 'app.js', 'api_dashboard.php', 'api_logout.php', 'README.md']
for file in required_files:
    file_path = test_dir / file
    test(f"File exists: {file}", file_path.exists())

# Test 5: Visual validation using webshot
print("\n" + "=" * 50)
print("Running visual validation...")

# Run webshot_test.py from scripts directory
webshot_test = Path(__file__).parent.parent.parent / 'scripts' / 'webshot_test.py'
if webshot_test.exists():
    result = subprocess.run(
        [str(webshot_test)], 
        cwd=test_dir,
        capture_output=True,
        text=True
    )
    
    # Include webshot output in test results
    if result.stdout:
        test_results.append("\nVisual Test Output:")
        test_results.append(result.stdout)
    
    if result.returncode != 0:
        all_passed = False
        if result.stderr:
            test_results.append("Visual Test Error:")
            test_results.append(result.stderr)
else:
    test_results.append("Visual test skipped: webshot_test.py not found")

# Write results
print("\n" + "=" * 50)
if all_passed:
    print("All tests PASSED!")
    with open(test_dir / 'test_pass.txt', 'w') as f:
        f.write("pg_main Test Results\n")
        f.write("=" * 50 + "\n")
        f.write("\n".join(test_results))
        f.write("\n\nAll tests passed successfully!")
else:
    print("Some tests FAILED!")
    with open(test_dir / 'test_fail.txt', 'w') as f:
        f.write("pg_main Test Results\n")
        f.write("=" * 50 + "\n")
        f.write("\n".join(test_results))
        f.write("\n\nSome tests failed. Please review and fix.")
    sys.exit(1)