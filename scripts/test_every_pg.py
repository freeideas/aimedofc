#!/home/ace/bin/uvrun
# /// script
# requires-python = ">=3.8"
# dependencies = [
#     "termcolor",
#     "tabulate",
# ]
# ///
"""
Master test runner for AI Office application.
Discovers and runs all test.py files in pg_* subdirectories.
Provides comprehensive coverage reporting and test validation.
"""

import os
import sys
import subprocess
import time
import json
from pathlib import Path
from termcolor import colored
from tabulate import tabulate

def find_all_pg_directories():
    """Find all pg_* directories."""
    www_dir = Path(__file__).parent.parent / 'www'
    return sorted([d for d in www_dir.glob("pg_*/") if d.is_dir()])

def analyze_pg_directory(pg_dir):
    """Analyze a pg_* directory for test coverage."""
    info = {
        'name': pg_dir.name,
        'has_test': (pg_dir / 'test.py').exists(),
        'has_readme': (pg_dir / 'README.md').exists(),
        'has_index': any((pg_dir / f).exists() for f in ['index.php', 'index.html']),
        'api_files': list(pg_dir.glob('api_*.php')),
        'test_pass': (pg_dir / 'test_pass.txt').exists(),
        'test_fail': (pg_dir / 'test_fail.txt').exists(),
        'webshot': (pg_dir / 'webshot.png').exists()
    }
    return info

def find_test_files():
    """Find all test.py files in pg_* directories with analysis."""
    test_info = []
    www_dir = Path(__file__).parent.parent / 'www'
    
    for pg_dir in find_all_pg_directories():
        info = analyze_pg_directory(pg_dir)
        if info['has_test']:
            test_info.append({
                'path': pg_dir / 'test.py',
                'info': info
            })
    
    return test_info

def run_test(test_data):
    """Run a single test file and return detailed results."""
    test_file = test_data['path']
    info = test_data['info']
    relative_path = test_file.relative_to(Path(__file__).parent.parent)
    
    print(f"\n{colored('Testing:', 'cyan')} {colored(info['name'], 'yellow')}")
    print("─" * 60)
    
    # Show what's being tested
    components = []
    if info['has_index']:
        components.append("✓ Main page")
    if info['api_files']:
        components.append(f"✓ {len(info['api_files'])} API endpoint(s)")
    if info['has_readme']:
        components.append("✓ Documentation")
    if info['webshot']:
        components.append("✓ Visual test")
    
    if components:
        print(f"Components: {', '.join(components)}")
    
    start_time = time.time()
    
    try:
        # Run test with timeout
        result = subprocess.run(
            [str(test_file)],
            capture_output=True,
            text=True,
            cwd=test_file.parent,
            timeout=60  # 60 second timeout
        )
        
        elapsed = time.time() - start_time
        
        # Check for test result files
        test_pass_file = test_file.parent / 'test_pass.txt'
        test_fail_file = test_file.parent / 'test_fail.txt'
        
        success = result.returncode == 0
        
        # Read test output for details
        if test_pass_file.exists():
            with open(test_pass_file, 'r') as f:
                pass_msg = f.read().strip()[:100]  # First 100 chars
        
        if success:
            print(colored(f"✓ All tests passed ({elapsed:.2f}s)", "green"))
            
            # Show stdout if there were warnings
            if 'warning' in result.stdout.lower() or '⚠' in result.stdout:
                print(colored("Warnings:", "yellow"))
                for line in result.stdout.split('\n'):
                    if 'warning' in line.lower() or '⚠' in line:
                        print(f"  {line.strip()}")
        else:
            print(colored(f"✗ Tests failed ({elapsed:.2f}s)", "red"))
            if result.stderr:
                print(colored("Error output:", "red"))
                print(result.stderr[:500])  # First 500 chars of error
            
            if test_fail_file.exists():
                with open(test_fail_file, 'r') as f:
                    fail_msg = f.read().strip()[:200]
                    print(colored(f"Failure details: {fail_msg}", "red"))
        
        return {
            'name': info['name'],
            'success': success,
            'time': elapsed,
            'has_warnings': 'warning' in result.stdout.lower() or '⚠' in result.stdout,
            'api_count': len(info['api_files']),
            'has_visual': info['webshot']
        }
        
    except subprocess.TimeoutExpired:
        print(colored(f"✗ Test timed out after 60 seconds", "red"))
        return {
            'name': info['name'],
            'success': False,
            'time': 60.0,
            'has_warnings': False,
            'api_count': len(info['api_files']),
            'has_visual': info['webshot'],
            'timeout': True
        }
    except Exception as e:
        print(colored(f"✗ Test error: {e}", "red"))
        return {
            'name': info['name'],
            'success': False,
            'time': 0,
            'has_warnings': False,
            'api_count': len(info['api_files']),
            'has_visual': info['webshot'],
            'error': str(e)
        }

def generate_basic_test(pg_dir):
    """Generate a basic test for a pg_* directory without one."""
    test_content = f'''#!/home/ace/bin/uvrun
# /// script
# requires-python = ">=3.8"
# dependencies = [
#     "requests",
#     "beautifulsoup4",
# ]
# ///

import requests
from bs4 import BeautifulSoup
import json
import sys
import os
from pathlib import Path

# Load BASE_URL from config.json
config_path = Path(__file__).parent.parent / 'config.json'
if config_path.exists():
    with open(config_path, 'r') as f:
        config = json.load(f)
        BASE_URL = config['BASE_URL'].rstrip('/')
else:
    BASE_URL = "http://localhost:8080"

def test_{pg_dir.name.replace('-', '_')}():
    """Basic test for {pg_dir.name}"""
    print("Testing {pg_dir.name}...")
    
    # Test 1: Check if page loads
    print("  ✓ Testing page loads...")
    response = requests.get(f"{{BASE_URL}}/{pg_dir.name}/")
    assert response.status_code in [200, 302], f"Expected 200/302, got {{response.status_code}}"
    
    # Test 2: Check for basic HTML structure
    if response.status_code == 200:
        print("  ✓ Testing HTML structure...")
        soup = BeautifulSoup(response.text, 'html.parser')
        assert soup.find('html') is not None, "No HTML tag found"
    
    print("  ✅ All {pg_dir.name} tests passed!")
    return True

if __name__ == "__main__":
    os.chdir(os.path.dirname(os.path.abspath(__file__)))
    try:
        if test_{pg_dir.name.replace('-', '_')}():
            sys.exit(0)
        else:
            sys.exit(1)
    except Exception as e:
        print(f"  ❌ Test failed: {{e}}")
        sys.exit(1)
'''
    
    test_file = pg_dir / 'test.py'
    with open(test_file, 'w') as f:
        f.write(test_content)
    os.chmod(test_file, 0o755)
    print(colored(f"  Generated basic test for {pg_dir.name}", "yellow"))
    return test_file

def main():
    """Main test runner with comprehensive coverage."""
    print(colored("╔════════════════════════════════════════════════════════════╗", "cyan"))
    print(colored("║         AI Office Comprehensive Test Suite                 ║", "cyan", attrs=["bold"]))
    print(colored("╚════════════════════════════════════════════════════════════╝", "cyan"))
    
    # Analyze all pg_* directories
    all_pg_dirs = find_all_pg_directories()
    
    print(f"\n{colored('Coverage Analysis:', 'yellow')}")
    print("─" * 60)
    
    coverage_data = []
    missing_tests = []
    
    for pg_dir in all_pg_dirs:
        info = analyze_pg_directory(pg_dir)
        coverage_data.append([
            info['name'],
            colored("✓", "green") if info['has_test'] else colored("✗", "red"),
            colored("✓", "green") if info['has_readme'] else colored("✗", "yellow"),
            colored("✓", "green") if info['has_index'] else colored("✗", "yellow"),
            len(info['api_files']),
            colored("✓", "green") if info['webshot'] else colored("-", "gray")
        ])
        
        if not info['has_test']:
            missing_tests.append(pg_dir)
    
    # Display coverage table
    headers = ["Directory", "Test", "README", "Index", "APIs", "Visual"]
    print(tabulate(coverage_data, headers=headers, tablefmt="grid"))
    
    # Handle missing tests
    if missing_tests:
        print(f"\n{colored('Missing Tests:', 'yellow')}")
        for pg_dir in missing_tests:
            print(f"  • {pg_dir.name}")
        
        if '--generate-missing' in sys.argv:
            print(f"\n{colored('Generating missing tests...', 'yellow')}")
            for pg_dir in missing_tests:
                generate_basic_test(pg_dir)
    
    # Run tests
    test_data = find_test_files()
    
    if not test_data:
        print(colored("\nNo test files found!", "red"))
        return 1
    
    print(f"\n{colored('Running Tests:', 'cyan')}")
    print("═" * 60)
    
    results = []
    for test_item in test_data:
        result = run_test(test_item)
        results.append(result)
    
    # Generate detailed summary
    print("\n" + "═" * 60)
    print(colored("Test Results Summary", "cyan", attrs=["bold"]))
    print("─" * 60)
    
    # Summary table
    summary_data = []
    total_time = 0
    passed = 0
    failed = 0
    warnings = 0
    
    for result in results:
        status = colored("PASS", "green") if result['success'] else colored("FAIL", "red")
        if result.get('timeout'):
            status = colored("TIMEOUT", "red")
        elif result.get('error'):
            status = colored("ERROR", "red")
        
        if result['success']:
            passed += 1
            if result['has_warnings']:
                warnings += 1
                status += colored(" ⚠", "yellow")
        else:
            failed += 1
        
        total_time += result['time']
        
        summary_data.append([
            result['name'],
            status,
            f"{result['time']:.2f}s",
            result['api_count'],
            colored("✓", "green") if result['has_visual'] else "-"
        ])
    
    headers = ["Page", "Status", "Time", "APIs", "Visual"]
    print(tabulate(summary_data, headers=headers, tablefmt="grid"))
    
    # Overall statistics
    print(f"\n{colored('Statistics:', 'cyan')}")
    print(f"  Total Pages Tested: {len(results)}")
    print(f"  {colored(f'Passed: {passed}', 'green')}")
    if failed > 0:
        print(f"  {colored(f'Failed: {failed}', 'red')}")
    if warnings > 0:
        print(f"  {colored(f'Warnings: {warnings}', 'yellow')}")
    print(f"  Total Time: {total_time:.2f}s")
    print(f"  Average Time: {total_time/len(results):.2f}s per test")
    
    # Coverage percentage
    total_pg = len(all_pg_dirs)
    tested_pg = len([d for d in coverage_data if d[1] == colored("✓", "green")])
    coverage_pct = (tested_pg / total_pg) * 100 if total_pg > 0 else 0
    
    print(f"\n{colored('Test Coverage:', 'cyan')}")
    print(f"  {tested_pg}/{total_pg} pages have tests ({coverage_pct:.1f}%)")
    
    if coverage_pct < 100 and '--generate-missing' not in sys.argv:
        print(colored(f"\n💡 Tip: Run with --generate-missing to create basic tests for untested pages", "yellow"))
    
    # Return non-zero exit code if any tests failed
    return 1 if failed > 0 else 0

if __name__ == "__main__":
    sys.exit(main())