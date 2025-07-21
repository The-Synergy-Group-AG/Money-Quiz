#!/usr/bin/env python3
"""
Test Grok API connection and send review
"""

import requests
import json
import time
import os

# Configuration
API_KEY = os.environ.get('GROK_API_KEY', '')
API_ENDPOINT = "https://api.x.ai/v1/chat/completions"

if not API_KEY:
    print("Error: GROK_API_KEY environment variable not set")
    print("Please set it with: export GROK_API_KEY='your-api-key'")
    exit(1)

def test_grok_api():
    """Test the Grok API with a simple request"""
    
    print("Testing Grok API connection...")
    
    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json"
    }
    
    # Simple test message
    payload = {
        "model": "grok-4-0709",
        "messages": [
            {
                "role": "user",
                "content": "Hello Grok, please confirm you can receive this message. I need your help reviewing a WordPress plugin for security vulnerabilities."
            }
        ],
        "temperature": 0.7,
        "max_tokens": 100
    }
    
    try:
        print("Sending test request...")
        response = requests.post(
            API_ENDPOINT,
            headers=headers,
            json=payload,
            timeout=30
        )
        
        print(f"Status Code: {response.status_code}")
        print(f"Headers: {response.headers}")
        
        if response.status_code == 200:
            result = response.json()
            print("\n✓ API Connection Successful!")
            print(f"Response: {json.dumps(result, indent=2)}")
            return True
        else:
            print(f"\n✗ API Error: {response.status_code}")
            print(f"Response: {response.text}")
            return False
            
    except requests.exceptions.ConnectionError as e:
        print(f"\n✗ Connection Error: Could not connect to {API_ENDPOINT}")
        print(f"Error: {e}")
        return False
    except requests.exceptions.Timeout as e:
        print(f"\n✗ Timeout Error: Request timed out after 30 seconds")
        print(f"Error: {e}")
        return False
    except Exception as e:
        print(f"\n✗ Unexpected Error: {type(e).__name__}: {e}")
        return False

def send_code_review():
    """Send a shorter code review request"""
    
    print("\nPreparing code review request...")
    
    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json"
    }
    
    # Shorter, focused request
    review_prompt = """I need your help reviewing a WordPress plugin called Money Quiz for security vulnerabilities.

Here are critical security issues I found:

1. SQL Injection:
```php
$results = $wpdb->get_row( "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS." WHERE Email = '".$Email."'", OBJECT );
```

2. XSS Vulnerability:
```php
echo $_REQUEST['Question'];
echo $row->Value;
```

3. Missing CSRF Protection:
```php
if(isset($_POST['action']) && $_POST['action'] == "update"){
    // No nonce verification
    $wpdb->update($table, $data);
}
```

4. Hardcoded Credentials:
```php
define('MONEYQUIZ_SPECIAL_SECRET_KEY', '5bcd52f5276855.46942741');
```

Please:
1. Confirm these are security vulnerabilities
2. Suggest specific fixes
3. Identify any other security issues in these patterns
4. Recommend best practices for WordPress plugin security

Can you provide a brief security assessment?"""

    payload = {
        "model": "grok-4-0709",
        "messages": [
            {
                "role": "system",
                "content": "You are a WordPress security expert."
            },
            {
                "role": "user",
                "content": review_prompt
            }
        ],
        "temperature": 0.3,
        "max_tokens": 2000
    }
    
    try:
        print("Sending code review request...")
        response = requests.post(
            API_ENDPOINT,
            headers=headers,
            json=payload,
            timeout=45
        )
        
        if response.status_code == 200:
            result = response.json()
            grok_response = result['choices'][0]['message']['content']
            
            # Save response
            with open('grok-security-assessment.md', 'w') as f:
                f.write("# Grok Security Assessment of Money Quiz Plugin\n\n")
                f.write(f"**Date:** {time.strftime('%Y-%m-%d %H:%M:%S')}\n\n")
                f.write("---\n\n")
                f.write(grok_response)
            
            print("\n✓ Successfully received Grok's security assessment!")
            print("✓ Saved to: grok-security-assessment.md")
            print("\nPreview:")
            print("="*60)
            print(grok_response[:500] + "...")
            
        else:
            print(f"\n✗ API Error: {response.status_code}")
            print(f"Response: {response.text}")
            
    except Exception as e:
        print(f"\n✗ Error: {type(e).__name__}: {e}")

if __name__ == "__main__":
    # First test the connection
    if test_grok_api():
        # If successful, send the code review
        send_code_review()
    else:
        print("\n❌ Could not establish connection to Grok API")
        print("\nPossible issues:")
        print("1. API endpoint might be incorrect")
        print("2. API key might be invalid or expired")
        print("3. Network connectivity issues")
        print("4. API service might be down")
        print("\nPlease verify your API credentials and endpoint.")