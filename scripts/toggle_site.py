#!/home/ace/bin/uvrun
# /// script
# requires-python = ">=3.8"
# dependencies = []
# ///

import os
import sys
from pathlib import Path

def main():
    if len(sys.argv) != 2 or sys.argv[1] not in ['up', 'down']:
        print("Usage: ./toggle_site.py [up|down]")
        print("  up   - Make site live (www → www_up)")
        print("  down - Put site in maintenance mode (www → www_down)")
        sys.exit(1)
    
    action = sys.argv[1]
    
    # Get the project root directory (parent of scripts/)
    project_root = Path(__file__).parent.parent
    www_link = project_root / 'www'
    www_up = project_root / 'www_up'
    www_down = project_root / 'www_down'
    
    # Check that required directories exist
    if not www_up.exists():
        print(f"Error: {www_up} does not exist")
        print("Run this script from the project root after www has been renamed to www_up")
        sys.exit(1)
    
    if not www_down.exists():
        print(f"Error: {www_down} does not exist")
        print("Create www_down directory with maintenance page first")
        sys.exit(1)
    
    # Remove existing symlink if it exists
    if www_link.exists() or www_link.is_symlink():
        if www_link.is_symlink():
            www_link.unlink()
        else:
            print(f"Error: {www_link} exists but is not a symbolic link")
            print("Please manually resolve this before running the toggle script")
            sys.exit(1)
    
    # Create new symlink
    if action == 'up':
        os.symlink('www_up', www_link)
        print("✅ Site is now UP (live)")
        print(f"   www → www_up")
    else:
        os.symlink('www_down', www_link)
        print("🚧 Site is now DOWN (maintenance mode)")
        print(f"   www → www_down")
    
    # Verify the symlink was created correctly
    if www_link.is_symlink():
        target = os.readlink(www_link)
        print(f"   Verified: www is linked to {target}")
    else:
        print("⚠️  Warning: Failed to verify symbolic link")

if __name__ == "__main__":
    main()