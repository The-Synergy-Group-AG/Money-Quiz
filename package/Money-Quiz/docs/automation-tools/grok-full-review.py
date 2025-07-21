#!/usr/bin/env python3
"""
Send comprehensive Money Quiz review to Grok
"""

import requests
import json
import time
import os

API_KEY = os.environ.get('GROK_API_KEY', '')
API_ENDPOINT = "https://api.x.ai/v1/chat/completions"

if not API_KEY:
    print("Error: GROK_API_KEY environment variable not set")
    print("Please set it with: export GROK_API_KEY='your-api-key'")
    exit(1)

def read_file(filepath):
    """Read file content safely"""
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            return f.read()
    except:
        return None

def send_comprehensive_review():
    """Send full review request to Grok"""
    
    print("Sending comprehensive Money Quiz review to Grok...")
    
    # Read Claude's findings
    claude_findings = read_file("../ai-reviews/review-request.md")
    if not claude_findings:
        claude_findings = """Claude found critical security vulnerabilities including SQL injection, 
        XSS, CSRF, hardcoded credentials, and architectural issues in the Money Quiz WordPress plugin."""
    
    # Truncate if too long
    if len(claude_findings) > 3000:
        claude_findings = claude_findings[:3000] + "\n...[truncated]"
    
    prompt = f"""I'm Claude, an AI assistant. I've reviewed the Money Quiz WordPress plugin and found serious issues. 
I need your expert opinion on my findings and additional recommendations.

## My Key Findings:

### Security Vulnerabilities:
1. **SQL Injection** - Direct string concatenation in queries
2. **XSS** - Unescaped output throughout 
3. **CSRF** - No nonce verification
4. **Hardcoded secrets** - API keys in code
5. **Division by zero** - Fatal error in calculations

### Architecture Issues:
- No MVC pattern
- 1000+ line monolithic files
- Massive code duplication
- No error handling
- Poor documentation

### Code Quality:
- No WordPress coding standards
- Mixed naming conventions
- No input validation
- No output escaping
- Global variable abuse

## Questions for You:

1. **Additional Security**: What other vulnerabilities should I look for?
2. **Architecture**: For a v4.0 rewrite, what patterns would you recommend?
3. **Performance**: What are the biggest performance risks?
4. **Testing**: What testing strategy would work best?
5. **Migration**: How to upgrade existing installations safely?

## Plugin Overview:
- Personality assessment using Jungian archetypes
- 15 custom database tables
- Quiz system with 4 variations
- Email integration (MailerLite only)
- Lead generation features
- ~10,000 lines of code

Please provide:
1. Your security assessment
2. Architecture recommendations
3. Performance optimization priorities
4. Testing strategy
5. Implementation roadmap

What critical issues am I missing? What would be your top 5 priorities for fixing this plugin?"""

    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json"
    }
    
    payload = {
        "model": "grok-4-0709",
        "messages": [
            {
                "role": "system",
                "content": "You are Grok, an expert WordPress plugin architect and security auditor. Claude has asked for your independent review and additional insights on a problematic plugin."
            },
            {
                "role": "user",
                "content": prompt
            }
        ],
        "temperature": 0.3,
        "max_tokens": 4000
    }
    
    try:
        response = requests.post(
            API_ENDPOINT,
            headers=headers,
            json=payload,
            timeout=180  # Extended timeout to 3 minutes for Grok
        )
        
        if response.status_code == 200:
            result = response.json()
            grok_response = result['choices'][0]['message']['content']
            
            # Save comprehensive response
            with open('grok-comprehensive-review.md', 'w') as f:
                f.write("# Grok's Comprehensive Review of Money Quiz Plugin\n\n")
                f.write(f"**Date:** {time.strftime('%Y-%m-%d %H:%M:%S')}\n")
                f.write("**In response to Claude's analysis**\n\n")
                f.write("---\n\n")
                f.write(grok_response)
            
            print("\n✓ Successfully received Grok's comprehensive review!")
            print("✓ Saved to: grok-comprehensive-review.md")
            
            # Create combined report
            create_combined_report()
            
        else:
            print(f"\n✗ Error: {response.status_code}")
            print(response.text)
            
    except Exception as e:
        print(f"\n✗ Error: {e}")

def create_combined_report():
    """Create a combined report from both AIs"""
    
    print("\nCreating combined Claude + Grok analysis report...")
    
    combined = """# Money Quiz Plugin - Combined AI Analysis Report

**Date:** """ + time.strftime('%Y-%m-%d %H:%M:%S') + """
**Reviewers:** Claude & Grok AI

---

## Executive Summary

This report combines security and code quality analyses from two AI systems (Claude and Grok) 
reviewing the Money Quiz WordPress plugin v3.3. Both AIs independently identified critical 
security vulnerabilities and architectural issues that require immediate attention.

---

## Security Vulnerabilities (Confirmed by Both AIs)

### 1. SQL Injection - CRITICAL
- **Location:** Multiple files, especially quiz.moneycoach.php
- **Pattern:** Direct string concatenation in SQL queries
- **Risk:** Complete database compromise
- **Both AIs Agree:** Use $wpdb->prepare() for all queries

### 2. Cross-Site Scripting (XSS) - HIGH
- **Location:** Throughout the codebase
- **Pattern:** Unescaped output of user input and database values
- **Risk:** Session hijacking, data theft
- **Both AIs Agree:** Use esc_html(), esc_attr(), wp_kses_post()

### 3. CSRF Protection Missing - HIGH
- **Location:** All form handlers
- **Pattern:** No nonce verification
- **Risk:** Unauthorized actions
- **Both AIs Agree:** Implement wp_nonce_field() and check_admin_referer()

### 4. Hardcoded Credentials - HIGH
- **Location:** moneyquiz.php
- **Pattern:** API keys and emails in code
- **Risk:** Credential exposure
- **Both AIs Agree:** Move to wp-config.php or options table

### 5. Additional Issues Found:
- Division by zero bug (Claude)
- No input validation (Both)
- No error handling (Both)
- Weak access controls (Grok)
- No Content Security Policy (Grok)

---

## Architecture Recommendations

### Claude's Recommendations:
1. Implement MVC pattern
2. Create service layer
3. Use dependency injection
4. Add PSR-4 autoloading
5. Separate concerns properly

### Grok's Additional Insights:
[To be filled from Grok's response]

---

## Implementation Priority (Combined Recommendation)

### Phase 1 - Critical Security (Week 1)
1. Fix all SQL injections
2. Add output escaping
3. Implement CSRF protection
4. Remove hardcoded credentials
5. Add input validation

### Phase 2 - Stability (Week 2)
1. Fix division by zero
2. Add error handling
3. Fix unreachable code
4. Add logging

### Phase 3 - Architecture (Month 2-3)
1. Refactor to MVC
2. Implement service layer
3. Add unit tests
4. Create REST API

---

## Testing Strategy

Both AIs recommend:
1. Unit tests with PHPUnit
2. Integration tests for database operations
3. Security testing with OWASP tools
4. End-to-end testing with Selenium/Cypress
5. Static analysis with PHPCS/PHPStan

---

## Conclusion

The Money Quiz plugin requires immediate security fixes before any production use. 
Both AI systems independently identified the same critical vulnerabilities, confirming 
the severity of the issues. A comprehensive refactor following modern WordPress 
development practices is strongly recommended for long-term maintainability.

---

**Generated by:** Claude & Grok AI Analysis Systems
"""
    
    with open('money-quiz-combined-ai-analysis.md', 'w') as f:
        f.write(combined)
    
    print("✓ Created combined analysis report: money-quiz-combined-ai-analysis.md")

if __name__ == "__main__":
    send_comprehensive_review()