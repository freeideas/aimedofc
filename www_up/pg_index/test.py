#!/home/ace/bin/uvrun
# /// script
# requires-python = ">=3.8"
# dependencies = []
# ///

import os
import sys
import subprocess
from pathlib import Path

def test_landing_page():
    """Test the pg_index landing page using visual validation."""
    print("Testing pg_index landing page...")
    
    # Clean up any existing result files
    test_pass = Path('./test_pass.txt')
    test_fail = Path('./test_fail.txt')
    
    if test_pass.exists():
        test_pass.unlink()
    if test_fail.exists():
        test_fail.unlink()
    
    # Run webshot_test.py directly from scripts/
    webshot_test = Path(__file__).parent.parent.parent / 'scripts' / 'webshot_test.py'
    
    if webshot_test.exists():
        print("Running visual validation test...")
        # Run webshot_test from scripts/ in the current directory context
        result = subprocess.run([str(webshot_test)], capture_output=False, text=True, cwd=Path(__file__).parent)
        
        # webshot_test.py already writes test_pass.txt or test_fail.txt with AI analysis
        return result.returncode == 0
    else:
        print(f"Error: webshot_test.py not found at {webshot_test}")
        print("Please ensure scripts/webshot_test.py exists")
        
        # Write failure file
        with open('./test_fail.txt', 'w') as f:
            f.write("Test failure detected\n\n")
            f.write("Error: webshot_test.py not found at scripts/ - visual validation unavailable\n")
        
        return False

if __name__ == '__main__':
    success = test_landing_page()
    
    if success:
        print("\n✅ All tests passed - see test_pass.txt for details including AI analysis")
        sys.exit(0)
    else:
        print("\n❌ Tests failed - see test_fail.txt for details including AI analysis")
        sys.exit(1)