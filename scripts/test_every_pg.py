#!/home/ace/bin/uvrun
# /// script
# requires-python = ">=3.8"
# dependencies = [
#     "termcolor",
# ]
# ///
"""
Master test runner for AI Office application.
Discovers and runs all test.py files in pg_* subdirectories.
"""

import os
import sys
import subprocess
from pathlib import Path
from termcolor import colored

def find_test_files():
    """Find all test.py files in pg_* directories."""
    test_files = []
    # Use www symlink - it points to either www_up or www_down
    # This way tests run against whatever is currently "live"
    www_dir = Path(__file__).parent.parent / 'www'
    
    for pg_dir in www_dir.glob("pg_*/"):
        test_file = pg_dir / "test.py"
        if test_file.exists():
            test_files.append(test_file)
    
    return sorted(test_files)

def run_test(test_file):
    """Run a single test file and return success status."""
    relative_path = test_file.relative_to(Path(__file__).parent.parent)
    print(f"\n{colored('Running:', 'cyan')} {relative_path}")
    print("=" * 60)
    
    try:
        result = subprocess.run(
            [str(test_file)],
            capture_output=False,
            text=True,
            cwd=test_file.parent
        )
        
        if result.returncode == 0:
            print(colored(f"✓ {relative_path} passed", "green"))
            return True
        else:
            print(colored(f"✗ {relative_path} failed", "red"))
            return False
    except Exception as e:
        print(colored(f"✗ {relative_path} error: {e}", "red"))
        return False

def main():
    """Main test runner."""
    print(colored("AI Office Test Suite", "yellow", attrs=["bold"]))
    print(colored("=" * 60, "yellow"))
    
    test_files = find_test_files()
    
    if not test_files:
        print(colored("No test files found in pg_* directories", "yellow"))
        return 0
    
    print(f"Found {len(test_files)} test file(s)")
    
    passed = 0
    failed = 0
    
    for test_file in test_files:
        if run_test(test_file):
            passed += 1
        else:
            failed += 1
    
    # Print summary
    print("\n" + "=" * 60)
    print(colored("Test Summary", "cyan", attrs=["bold"]))
    print(f"Total: {len(test_files)}")
    print(colored(f"Passed: {passed}", "green"))
    if failed > 0:
        print(colored(f"Failed: {failed}", "red"))
    
    # Return non-zero exit code if any tests failed
    return 1 if failed > 0 else 0

if __name__ == "__main__":
    sys.exit(main())