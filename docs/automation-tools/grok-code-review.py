#!/usr/bin/env python3
"""
Script to send Money Quiz plugin code to Grok AI for review
"""

import requests
import json
import os
from pathlib import Path

# Configuration
API_KEY = os.environ.get('GROK_API_KEY', '')
API_ENDPOINT = "https://api.x.ai/v1/chat/completions"

if not API_KEY:
    print("Error: GROK_API_KEY environment variable not set")
    print("Please set it with: export GROK_API_KEY='your-api-key'")
    exit(1)

def read_file(file_path):
    """Read file content"""
    with open(file_path, 'r', encoding='utf-8') as file:
        return file.read()

def analyze_code_with_grok(code_content, filename):
    """Send code to Grok for analysis"""
    
    prompt = f"""
    Please perform a comprehensive code review of this WordPress plugin file: {filename}
    
    Focus on:
    1. Security vulnerabilities (SQL injection, XSS, CSRF)
    2. Code quality and WordPress coding standards
    3. Performance issues
    4. Bugs and potential errors
    5. Suggestions for improvement
    
    Code:
    ```php
    {code_content}
    ```
    """
    
    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json"
    }
    
    payload = {
        "model": "grok-4-0709",  # Update based on API docs
        "messages": [
            {
                "role": "system",
                "content": "You are a expert WordPress plugin developer performing a security and code quality audit."
            },
            {
                "role": "user",
                "content": prompt
            }
        ],
        "temperature": 0.7,
        "max_tokens": 4000
    }
    
    try:
        response = requests.post(API_ENDPOINT, headers=headers, json=payload)
        response.raise_for_status()
        return response.json()
    except requests.exceptions.RequestException as e:
        print(f"Error calling Grok API: {e}")
        return None

def main():
    """Main function to review Money Quiz plugin files"""
    
    # Define files to review
    files_to_review = [
        "moneyquiz.php",
        "class.moneyquiz.php",
        "quiz.moneycoach.php",
        "integration.admin.php"
    ]
    
    results = {}
    
    for filename in files_to_review:
        file_path = Path(filename)
        if file_path.exists():
            print(f"Analyzing {filename}...")
            code_content = read_file(file_path)
            
            # Limit code length if needed (some APIs have limits)
            if len(code_content) > 10000:
                code_content = code_content[:10000] + "\n... [truncated]"
            
            result = analyze_code_with_grok(code_content, filename)
            if result:
                results[filename] = result
                print(f"✓ Completed analysis of {filename}")
            else:
                print(f"✗ Failed to analyze {filename}")
        else:
            print(f"File not found: {filename}")
    
    # Save results
    with open('grok-analysis-results.json', 'w') as f:
        json.dump(results, f, indent=2)
    
    print("\nAnalysis complete! Results saved to grok-analysis-results.json")

if __name__ == "__main__":
    print("Starting Money Quiz plugin code review with Grok AI...")
    print("=" * 60)
    main()