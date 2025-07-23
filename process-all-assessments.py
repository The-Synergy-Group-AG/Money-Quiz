#!/usr/bin/env python3
"""Process all existing Grok assessments to build audit log."""

import os
import re
import sys
sys.path.append('/home/andre/projects/money-quiz/money-quiz-v7')
from grok_verification_system import GrokVerificationSystem

def process_assessments():
    verifier = GrokVerificationSystem("/home/andre/projects/money-quiz/money-quiz-v7")
    assessment_dir = "/home/andre/projects/money-quiz/money-quiz-v7/docs/70-reviews/01-grok-reviews"
    
    # Map of files to their actual attempt numbers and scores
    assessments = [
        ("phase-1-attempt-2-92-percent-approval.md", 2, "Phase 1"),
        ("phase-1-attempt-3-92-percent-approval.md", 3, "Phase 1"),
        ("phase-1-attempt-4-92-percent-approval.md", 4, "Phase 1"),
        ("phase-1-attempt-5-90-percent-approval.md", 5, "Phase 1"),
        ("phase-1-attempt-6-92-percent-approval.md", 6, "Phase 1"),
        ("phase-1-attempt-7-final-95-percent-approval.md", 7, "Phase 1"),
        ("phase1-attempt3-enhanced-unknownpercent-20250723_134019.md", 8, "Phase 1 Enhanced"),
    ]
    
    for filename, attempt, phase in assessments:
        filepath = os.path.join(assessment_dir, filename)
        if os.path.exists(filepath):
            print(f"\nProcessing {filename}...")
            with open(filepath, 'r') as f:
                content = f.read()
            
            # Extract timestamp from file
            timestamp_match = re.search(r'\*\*Date\*\*:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})', content)
            timestamp = timestamp_match.group(1) if timestamp_match else None
            
            result = verifier.verify_and_log_assessment(
                content, phase, attempt, timestamp
            )
            
            score = result['scores'].get('overall_percentage', 'Unknown')
            passed = result['passed_95_threshold']
            print(f"  Score: {score}%")
            print(f"  Passed 95% threshold: {passed}")
    
    # Generate truth report
    print("\n" + "="*50)
    print("TRUTH REPORT")
    print("="*50)
    print(verifier.generate_truth_report())

if __name__ == "__main__":
    process_assessments()