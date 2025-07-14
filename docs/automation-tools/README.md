# Grok Review Scripts

These scripts integrate with the Grok AI API to provide additional code review and security analysis of the Money Quiz plugin.

## Setup

Before using these scripts, you must set the Grok API key as an environment variable:

```bash
export GROK_API_KEY='your-api-key-here'
```

## Scripts

1. **grok-api-test.py** - Tests the API connection with Grok
2. **grok-code-review.py** - Performs code review on specified files
3. **grok-comprehensive-review.py** - Comprehensive analysis with multiple perspectives
4. **grok-full-review.py** - Full review combining Claude's findings
5. **send-to-grok.py** - Sends the review package to Grok

## Usage

```bash
# Test the connection first
python grok-api-test.py

# Run comprehensive review
python grok-comprehensive-review.py

# Send full review package
python send-to-grok.py
```

## Security Note

Never commit API keys to version control. Always use environment variables or secure key management systems.