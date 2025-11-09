#!/usr/bin/env python3
"""
Remove fixture declarations from all test files
"""
import os
import re
from pathlib import Path

def remove_fixtures_from_file(filepath):
    """Remove fixture property declarations from a PHP test file"""
    with open(filepath, 'r') as f:
        content = f.read()
    
    # Pattern to match fixture declarations with their docblocks
    # Matches from /** to the end of the fixtures array
    pattern = r'(\s*\/\*\*\s*\n\s*\* Fixtures\s*\n(?:\s*\*[^\n]*\n)*\s*\*\/\s*\n)?\s*(protected|public)\s+(array\s+)?\$fixtures\s*=\s*\[[^\]]*\];?\s*\n'
    
    original_content = content
    content = re.sub(pattern, '', content, flags=re.MULTILINE)
    
    if content != original_content:
        with open(filepath, 'w') as f:
            f.write(content)
        return True
    return False

def main():
    base_path = Path('/workspaces/KMP/app')
    
    # Process main test files
    test_dirs = [
        base_path / 'tests',
    ]
    
    # Add plugin test directories
    plugins_dir = base_path / 'plugins'
    if plugins_dir.exists():
        for plugin in plugins_dir.iterdir():
            plugin_tests = plugin / 'tests'
            if plugin_tests.exists():
                test_dirs.append(plugin_tests)
    
    modified_count = 0
    for test_dir in test_dirs:
        if not test_dir.exists():
            continue
            
        for php_file in test_dir.rglob('*.php'):
            if remove_fixtures_from_file(php_file):
                print(f"Updated: {php_file}")
                modified_count += 1
    
    print(f"\nTotal files modified: {modified_count}")

if __name__ == '__main__':
    main()
