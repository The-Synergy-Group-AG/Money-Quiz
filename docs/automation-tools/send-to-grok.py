#!/usr/bin/env python3
"""
Send Money Quiz review package to Grok AI
"""

import requests
import json
import time
import os
from pathlib import Path

# Configuration
API_KEY = os.environ.get('GROK_API_KEY', '')
API_ENDPOINT = "https://api.x.ai/v1/chat/completions"

if not API_KEY:
    print("Error: GROK_API_KEY environment variable not set")
    print("Please set it with: export GROK_API_KEY='your-api-key'")
    exit(1)

def read_file(filepath):
    """Read file content"""
    with open(filepath, 'r', encoding='utf-8') as f:
        return f.read()

def send_to_grok():
    """Send the review request and key files to Grok"""
    
    print("Preparing to send Money Quiz plugin review to Grok AI...")
    print("="*60)
    
    # Read the main review request
    review_request = read_file("../ai-reviews/review-request.md")
    
    # Read critical code examples
    critical_code = read_file("sample-code/critical-code-examples.php")
    
    # Read a sample of the main plugin file
    main_plugin_sample = read_file("sample-code/moneyquiz.php")[:5000]
    
    # Construct the comprehensive prompt
    prompt = f"""{review_request}

## Critical Code Examples:

```php
{critical_code}
```

## Sample from Main Plugin File (moneyquiz.php):

```php
{main_plugin_sample}
... [truncated for length]
```

Please provide a comprehensive security audit and architectural review of this WordPress plugin, focusing on:

1. **Security Vulnerabilities**: Confirm the issues I found and identify any I missed
2. **Code Quality**: Assess against WordPress coding standards
3. **Architecture**: Recommend modern patterns for a v4.0 rewrite
4. **Performance**: Identify bottlenecks and optimization opportunities
5. **Testing Strategy**: Suggest comprehensive testing approach

Please be specific with line numbers and code examples in your recommendations.
"""

    # Prepare API request
    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json"
    }
    
    payload = {
        "model": "grok-4-0709",
        "messages": [
            {
                "role": "system",
                "content": "You are Grok, an expert WordPress security auditor and plugin architect. Another AI (Claude) has already reviewed this plugin and is asking for your independent assessment and additional insights."
            },
            {
                "role": "user", 
                "content": prompt
            }
        ],
        "temperature": 0.3,
        "max_tokens": 4000
    }
    
    print("Sending request to Grok AI...")
    
    try:
        response = requests.post(
            API_ENDPOINT,
            headers=headers,
            json=payload,
            timeout=60
        )
        response.raise_for_status()
        
        result = response.json()
        
        # Extract Grok's response
        if 'choices' in result and len(result['choices']) > 0:
            grok_response = result['choices'][0]['message']['content']
            
            # Save Grok's response
            with open('grok-review-response.md', 'w') as f:
                f.write("# Grok AI Review of Money Quiz Plugin\n\n")
                f.write(f"**Review Date:** {time.strftime('%Y-%m-%d %H:%M:%S')}\n\n")
                f.write("---\n\n")
                f.write(grok_response)
            
            print("\n✓ Successfully received response from Grok!")
            print("✓ Response saved to: grok-review-response.md")
            
            # Also save the raw response
            with open('grok-review-raw.json', 'w') as f:
                json.dump(result, f, indent=2)
            print("✓ Raw response saved to: grok-review-raw.json")
            
            # Print a preview
            print("\n" + "="*60)
            print("PREVIEW OF GROK'S RESPONSE:")
            print("="*60)
            print(grok_response[:1000] + "...\n[See full response in grok-review-response.md]")
            
        else:
            print("✗ Unexpected response format from Grok")
            print(json.dumps(result, indent=2))
            
    except requests.exceptions.RequestException as e:
        print(f"\n✗ Error communicating with Grok API: {e}")
        if hasattr(e.response, 'text'):
            print(f"Response: {e.response.text}")
    except Exception as e:
        print(f"\n✗ Unexpected error: {e}")

if __name__ == "__main__":
    send_to_grok()