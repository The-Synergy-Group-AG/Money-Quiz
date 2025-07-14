#!/usr/bin/env python3
"""
Comprehensive script to analyze Money Quiz plugin with Grok AI
Handles large files, multiple analysis types, and generates detailed reports
"""

import requests
import json
import os
import time
from pathlib import Path
from datetime import datetime

# Configuration
API_KEY = os.environ.get('GROK_API_KEY', '')
API_ENDPOINT = "https://api.x.ai/v1/chat/completions"

if not API_KEY:
    print("Error: GROK_API_KEY environment variable not set")
    print("Please set it with: export GROK_API_KEY='your-api-key'")
    exit(1)

class GrokCodeReviewer:
    def __init__(self, api_key, api_endpoint):
        self.api_key = api_key
        self.api_endpoint = api_endpoint
        self.results = {}
        
    def read_file(self, file_path):
        """Read file content with error handling"""
        try:
            with open(file_path, 'r', encoding='utf-8') as file:
                return file.read()
        except Exception as e:
            print(f"Error reading {file_path}: {e}")
            return None
    
    def chunk_code(self, code, max_length=8000):
        """Split large code files into chunks"""
        if len(code) <= max_length:
            return [code]
        
        chunks = []
        lines = code.split('\n')
        current_chunk = []
        current_length = 0
        
        for line in lines:
            if current_length + len(line) > max_length and current_chunk:
                chunks.append('\n'.join(current_chunk))
                current_chunk = [line]
                current_length = len(line)
            else:
                current_chunk.append(line)
                current_length += len(line) + 1
        
        if current_chunk:
            chunks.append('\n'.join(current_chunk))
        
        return chunks
    
    def analyze_security(self, code, filename):
        """Perform security-focused analysis"""
        prompt = f"""
        Perform a SECURITY-FOCUSED review of this WordPress plugin file: {filename}
        
        Specifically check for:
        1. SQL Injection vulnerabilities
        2. Cross-Site Scripting (XSS) vulnerabilities
        3. Cross-Site Request Forgery (CSRF) issues
        4. Authentication and authorization flaws
        5. Insecure data storage or transmission
        6. Hardcoded sensitive information
        7. File upload vulnerabilities
        8. Command injection risks
        
        Provide specific line numbers and code examples for each vulnerability found.
        
        Code:
        ```php
        {code}
        ```
        """
        return self.call_grok_api(prompt, "security")
    
    def analyze_code_quality(self, code, filename):
        """Perform code quality analysis"""
        prompt = f"""
        Perform a CODE QUALITY review of this WordPress plugin file: {filename}
        
        Focus on:
        1. WordPress coding standards compliance
        2. PHP best practices
        3. Code organization and structure
        4. DRY (Don't Repeat Yourself) violations
        5. Function complexity and maintainability
        6. Error handling and logging
        7. Documentation and comments
        8. Performance issues
        
        Provide specific examples and improvement suggestions.
        
        Code:
        ```php
        {code}
        ```
        """
        return self.call_grok_api(prompt, "code_quality")
    
    def analyze_architecture(self, code, filename):
        """Analyze architectural patterns and design"""
        prompt = f"""
        Analyze the ARCHITECTURE and DESIGN PATTERNS in this WordPress plugin file: {filename}
        
        Evaluate:
        1. Separation of concerns
        2. Design patterns used (or should be used)
        3. Database design and queries
        4. API design and integration points
        5. Scalability considerations
        6. Testability
        7. Modularity and reusability
        
        Suggest architectural improvements for a version 4.0 rewrite.
        
        Code:
        ```php
        {code}
        ```
        """
        return self.call_grok_api(prompt, "architecture")
    
    def call_grok_api(self, prompt, analysis_type):
        """Make API call to Grok with retry logic"""
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json"
        }
        
        payload = {
            "model": "grok-4-0709",
            "messages": [
                {
                    "role": "system",
                    "content": f"You are an expert WordPress security auditor and plugin developer performing a {analysis_type} review."
                },
                {
                    "role": "user",
                    "content": prompt
                }
            ],
            "temperature": 0.3,  # Lower temperature for more focused analysis
            "max_tokens": 4000
        }
        
        max_retries = 3
        for attempt in range(max_retries):
            try:
                response = requests.post(
                    self.api_endpoint, 
                    headers=headers, 
                    json=payload,
                    timeout=60
                )
                response.raise_for_status()
                return response.json()
            except requests.exceptions.RequestException as e:
                print(f"API call failed (attempt {attempt + 1}/{max_retries}): {e}")
                if attempt < max_retries - 1:
                    time.sleep(2 ** attempt)  # Exponential backoff
                else:
                    return {"error": str(e)}
    
    def analyze_file(self, filepath):
        """Perform comprehensive analysis on a single file"""
        print(f"\nAnalyzing {filepath}...")
        code = self.read_file(filepath)
        
        if not code:
            return {"error": f"Could not read {filepath}"}
        
        filename = os.path.basename(filepath)
        file_results = {
            "filename": filename,
            "file_size": len(code),
            "analyses": {}
        }
        
        # For large files, analyze in chunks
        chunks = self.chunk_code(code)
        
        if len(chunks) > 1:
            print(f"  File is large, splitting into {len(chunks)} chunks for analysis")
        
        # Perform different types of analysis
        analysis_types = [
            ("security", self.analyze_security),
            ("code_quality", self.analyze_code_quality),
            ("architecture", self.analyze_architecture)
        ]
        
        for analysis_name, analysis_func in analysis_types:
            print(f"  Performing {analysis_name} analysis...")
            chunk_results = []
            
            for i, chunk in enumerate(chunks):
                if len(chunks) > 1:
                    chunk_filename = f"{filename} (chunk {i+1}/{len(chunks)})"
                else:
                    chunk_filename = filename
                
                result = analysis_func(chunk, chunk_filename)
                chunk_results.append(result)
                time.sleep(1)  # Rate limiting
            
            file_results["analyses"][analysis_name] = chunk_results
            print(f"  ✓ Completed {analysis_name} analysis")
        
        return file_results
    
    def generate_report(self):
        """Generate a comprehensive markdown report"""
        report = f"""# Money Quiz Plugin - Grok AI Code Review Report

**Generated:** {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
**Reviewed by:** Grok AI (grok-4-0709)

---

## Executive Summary

This report contains a comprehensive analysis of the Money Quiz WordPress plugin performed by Grok AI, 
covering security vulnerabilities, code quality issues, and architectural recommendations.

---

"""
        
        for filepath, results in self.results.items():
            report += f"\n## {results['filename']}\n\n"
            report += f"**File Size:** {results['file_size']} bytes\n\n"
            
            for analysis_type, chunks in results['analyses'].items():
                report += f"### {analysis_type.replace('_', ' ').title()} Analysis\n\n"
                
                for i, chunk_result in enumerate(chunks):
                    if 'error' in chunk_result:
                        report += f"**Error:** {chunk_result['error']}\n\n"
                    elif 'choices' in chunk_result:
                        content = chunk_result['choices'][0]['message']['content']
                        if len(chunks) > 1:
                            report += f"#### Chunk {i+1}/{len(chunks)}\n\n"
                        report += content + "\n\n"
            
            report += "---\n"
        
        return report
    
    def run_analysis(self, files_to_analyze):
        """Run comprehensive analysis on all files"""
        print("Starting comprehensive Money Quiz plugin analysis with Grok AI")
        print("=" * 60)
        
        for filepath in files_to_analyze:
            if Path(filepath).exists():
                self.results[filepath] = self.analyze_file(filepath)
            else:
                print(f"File not found: {filepath}")
        
        # Save raw results
        with open('grok-analysis-raw-results.json', 'w') as f:
            json.dump(self.results, f, indent=2)
        print("\n✓ Raw results saved to grok-analysis-raw-results.json")
        
        # Generate and save markdown report
        report = self.generate_report()
        with open('grok-analysis-report.md', 'w') as f:
            f.write(report)
        print("✓ Formatted report saved to grok-analysis-report.md")
        
        print("\nAnalysis complete!")


def main():
    # Initialize reviewer
    reviewer = GrokCodeReviewer(API_KEY, API_ENDPOINT)
    
    # Define files to analyze
    files_to_analyze = [
        "moneyquiz.php",
        "class.moneyquiz.php", 
        "quiz.moneycoach.php",
        "integration.admin.php",
        "questions.admin.php",
        "stats.admin.php",
        "cta.admin.php"
    ]
    
    # Run analysis
    reviewer.run_analysis(files_to_analyze)
    
    # Also create a summary for manual review
    summary = """
    The Grok AI analysis is complete! 
    
    Files generated:
    - grok-analysis-raw-results.json: Raw API responses
    - grok-analysis-report.md: Formatted markdown report
    
    The analysis covered:
    1. Security vulnerabilities
    2. Code quality issues  
    3. Architectural recommendations
    
    Please review the markdown report for detailed findings and recommendations.
    """
    
    print(summary)


if __name__ == "__main__":
    main()