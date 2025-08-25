#!/home/ace/bin/uvrun
# /// script
# requires-python = ">=3.8"
# dependencies = [
#     "termcolor",
# ]
# ///
"""
Automated visual testing for web pages using webshot and Claude Code validation.
This script captures a screenshot of the page and uses Claude to validate it against README.md specifications.
"""

import os
import sys
import json
import subprocess
import tempfile
from pathlib import Path
from termcolor import colored


def find_base_url():
    """Find the base URL from configuration files."""
    # Try to find config file with base URL
    config_locations = [
        Path.cwd() / 'config.json',
        Path.cwd().parent / 'config.json',
        Path.cwd().parent / 'www' / 'config.json',
        Path.cwd() / '.env',
        Path.cwd().parent / '.env',
    ]
    
    for config_path in config_locations:
        if config_path.exists():
            try:
                if config_path.suffix == '.json':
                    with open(config_path, 'r') as f:
                        config = json.load(f)
                        if 'base_url' in config:
                            return config['base_url'].rstrip('/')
                        if 'BASE_URL' in config:
                            return config['BASE_URL'].rstrip('/')
                elif config_path.name == '.env':
                    with open(config_path, 'r') as f:
                        for line in f:
                            if line.startswith('BASE_URL='):
                                return line.split('=', 1)[1].strip().strip('"').strip("'").rstrip('/')
            except:
                continue
    
    # Default to localhost
    print(colored("Warning: Could not find base URL in config, using http://localhost", "yellow"))
    return "http://localhost"


def capture_screenshot(url, output_file, dimensions="1280x720"):
    """Capture a screenshot using the webshot command."""
    try:
        cmd = ['webshot', url, output_file, dimensions]
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
        
        if result.returncode != 0:
            print(colored(f"Error capturing screenshot: {result.stderr}", "red"))
            return False
        
        return os.path.exists(output_file)
    except subprocess.TimeoutExpired:
        print(colored("Screenshot capture timed out", "red"))
        return False
    except FileNotFoundError:
        print(colored("Error: webshot command not found. Please ensure it's installed.", "red"))
        return False
    except Exception as e:
        print(colored(f"Error capturing screenshot: {e}", "red"))
        return False


def read_readme():
    """Read the README.md file in the current directory."""
    readme_path = Path.cwd() / 'README.md'
    if not readme_path.exists():
        return None
    
    try:
        with open(readme_path, 'r') as f:
            return f.read()
    except Exception as e:
        print(colored(f"Error reading README.md: {e}", "red"))
        return None


def validate_with_claude(screenshot_path, readme_content, page_name):
    """Use Claude Code to validate the screenshot against README specifications."""
    try:
        # Create the prompt with clear instructions for structured output
        prompt = f"""You are a QA engineer validating a web page's UI against its documentation.

Page being tested: {page_name}

README Documentation:
---
{readme_content}
---

Please analyze the provided screenshot and determine if the web page matches the expected behavior and appearance described in the README.

Consider:
1. Does the page layout match what's described?
2. Are the expected UI elements present (forms, buttons, text fields, etc.)?
3. Does the page appear to be rendering correctly without errors?
4. Are placeholder elements showing appropriately (if mentioned in README)?
5. Does the overall appearance suggest the page is working as intended?

IMPORTANT: Your response MUST follow this exact format:

VERDICT: [PASS or FAIL]

ANALYSIS:
[Provide a detailed analysis with 3-5 bullet points explaining your verdict. Include specific observations about what you see in the screenshot and how it compares to the README expectations.]

Be lenient with minor styling differences, focusing on functional elements and overall structure. Only mark as FAIL if there are significant discrepancies or missing critical functionality."""

        # Run claude command with the screenshot and prompt via stdin
        claude_path = os.path.expanduser('~/.npm-global/bin/claude')
        cmd = [
            claude_path, 
            '--print',  # Use print mode for non-interactive output
            '--model', 'sonnet',  # Use Sonnet model
            screenshot_path
        ]
        
        result = subprocess.run(cmd, input=prompt, capture_output=True, text=True, timeout=60)
        
        if result.returncode != 0:
            print(colored(f"Error running claude command: {result.stderr}", "red"))
            return None, result.stderr
        
        response = result.stdout.strip()
        
        # Parse the response to extract verdict and analysis
        lines = response.split('\n')
        verdict = None
        analysis_lines = []
        in_analysis = False
        
        for line in lines:
            if line.startswith('VERDICT:'):
                verdict_text = line.replace('VERDICT:', '').strip()
                if 'PASS' in verdict_text.upper():
                    verdict = True
                elif 'FAIL' in verdict_text.upper():
                    verdict = False
            elif line.startswith('ANALYSIS:'):
                in_analysis = True
            elif in_analysis:
                analysis_lines.append(line)
        
        analysis = '\n'.join(analysis_lines).strip()
        
        # If we couldn't parse the structured format, try to infer from the response
        if verdict is None:
            response_lower = response.lower()
            if 'pass' in response_lower and 'fail' not in response_lower[:100]:
                verdict = True
            else:
                verdict = False
            analysis = response  # Use full response as analysis if parsing failed
        
        return verdict, analysis
        
    except subprocess.TimeoutExpired:
        print(colored("Claude command timed out", "red"))
        return None, "Claude validation timed out"
    except FileNotFoundError:
        print(colored("Error: claude command not found at ~/.npm-global/bin/claude. Please ensure Claude Code is installed.", "red"))
        return None, "Claude Code not found at ~/.npm-global/bin/claude"
    except Exception as e:
        print(colored(f"Error during Claude validation: {e}", "red"))
        return None, str(e)


def run_visual_test(page_name=None, url_path=None, dimensions="1280x720"):
    """Run the visual test for a page. Returns (success, ai_analysis)."""
    # Determine page name and URL
    if not page_name:
        page_name = Path.cwd().name
    
    if not url_path:
        # Assume we're in a pg_* directory
        if page_name.startswith('pg_'):
            url_path = f"/{page_name}/"
        else:
            url_path = "/"
    
    # Get base URL and construct full URL
    base_url = find_base_url()
    full_url = f"{base_url}{url_path}"
    
    print(colored(f"\n=== Visual Test for {page_name} ===", "cyan", attrs=["bold"]))
    print(f"URL: {full_url}")
    
    # Read README
    readme_content = read_readme()
    if not readme_content:
        print(colored("Warning: No README.md found in current directory", "yellow"))
        readme_content = "No specific documentation available. Validate general page functionality."
    
    # Save screenshot directly in the pg_* directory as webshot.png
    screenshot_path = "./webshot.png"
    
    # Capture screenshot
    print(f"Capturing screenshot...")
    if not capture_screenshot(full_url, screenshot_path, dimensions):
        return False, "Failed to capture screenshot"
    
    print(colored(f"✓ Screenshot captured as webshot.png", "green"))
    
    # Validate with Claude
    print(f"Validating with Claude...")
    verdict, analysis = validate_with_claude(screenshot_path, readme_content, page_name)
    
    if verdict is None:
        print(colored("✗ Claude validation failed", "red"))
        return False, f"Claude validation failed: {analysis}"
    
    # Display results
    print("\n" + "=" * 60)
    print(colored("Claude Validation Result:", "cyan"))
    if verdict:
        print(colored("VERDICT: PASS", "green", attrs=["bold"]))
    else:
        print(colored("VERDICT: FAIL", "red", attrs=["bold"]))
    print("\nANALYSIS:")
    print(analysis)
    print("=" * 60)
    
    if verdict:
        print(colored(f"\n✓ Visual test PASSED for {page_name}", "green", attrs=["bold"]))
        return True, analysis
    else:
        print(colored(f"\n✗ Visual test FAILED for {page_name}", "red", attrs=["bold"]))
        return False, analysis


def cleanup_result_files():
    """Remove any existing test result files."""
    test_pass = Path('./test_pass.txt')
    test_fail = Path('./test_fail.txt')
    
    if test_pass.exists():
        test_pass.unlink()
    if test_fail.exists():
        test_fail.unlink()


def write_pass_result(test_name="Visual validation", analysis=""):
    """Write test_pass.txt with test information and analysis."""
    with open('./test_pass.txt', 'w') as f:
        f.write("All tests passed successfully\n\n")
        f.write("Tests performed:\n")
        f.write(f"- {test_name}\n")
        if analysis:
            f.write("\nAI Analysis:\n")
            f.write("=" * 60 + "\n")
            f.write(analysis + "\n")
            f.write("=" * 60 + "\n")


def write_fail_result(error_message, analysis=""):
    """Write test_fail.txt with error details and analysis."""
    with open('./test_fail.txt', 'w') as f:
        f.write("Test failure detected\n\n")
        f.write(f"Error: {error_message}\n")
        if analysis:
            f.write("\nAI Analysis:\n")
            f.write("=" * 60 + "\n")
            f.write(analysis + "\n")
            f.write("=" * 60 + "\n")


def main():
    """Main function for standalone execution."""
    import argparse
    
    parser = argparse.ArgumentParser(description='Visual testing for web pages using Claude validation')
    parser.add_argument('--page', help='Page name (defaults to current directory name)')
    parser.add_argument('--url', help='URL path to test (defaults to /page_name/)')
    parser.add_argument('--dimensions', default='1280x720', help='Screenshot dimensions (default: 1280x720)')
    
    args = parser.parse_args()
    
    # Clean up any existing result files
    cleanup_result_files()
    
    success, analysis = run_visual_test(
        page_name=args.page,
        url_path=args.url,
        dimensions=args.dimensions
    )
    
    # Write result files when run standalone
    page_name = args.page or Path.cwd().name
    if success:
        write_pass_result(f"Visual validation of {page_name}", analysis)
        print("\n✅ Test passed - see test_pass.txt for details")
    else:
        write_fail_result(f"Visual validation failed for {page_name}", analysis)
        print("\n❌ Test failed - see test_fail.txt for details")
    
    sys.exit(0 if success else 1)


if __name__ == "__main__":
    main()