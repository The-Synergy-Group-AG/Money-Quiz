#!/usr/bin/env python3
"""
Grok Verification System - Ensures truthful reporting of Grok assessments.
This system prevents false reporting by automatically parsing and verifying scores.
"""

import os
import re
import json
import hashlib
from datetime import datetime
from typing import Dict, List, Optional, Tuple

class GrokVerificationSystem:
    """
    Immutable verification system for Grok assessments.
    Prevents false reporting through automated parsing and verification.
    """
    
    def __init__(self, base_dir: str):
        self.base_dir = base_dir
        self.audit_log_path = os.path.join(base_dir, "grok-audit-log.json")
        self.verified_scores_path = os.path.join(base_dir, "verified-grok-scores.json")
        self._load_audit_log()
    
    def _load_audit_log(self):
        """Load existing audit log or create new one."""
        if os.path.exists(self.audit_log_path):
            with open(self.audit_log_path, 'r') as f:
                self.audit_log = json.load(f)
        else:
            self.audit_log = {"entries": []}
    
    def parse_grok_response(self, response_text: str) -> Dict[str, any]:
        """
        Parse Grok response to extract actual scores.
        Returns dict with all scores and overall percentage.
        """
        scores = {}
        
        # Extract individual scores (X/10 format)
        score_patterns = [
            (r'Security\s+[Ss]core:\s*(\d+(?:\.\d+)?)/10', 'security'),
            (r'Architecture\s+[Ss]core:\s*(\d+(?:\.\d+)?)/10', 'architecture'),
            (r'Code\s+[Qq]uality\s+[Ss]core:\s*(\d+(?:\.\d+)?)/10', 'code_quality'),
            (r'Completeness\s+[Ss]core:\s*(\d+(?:\.\d+)?)/10', 'completeness')
        ]
        
        for pattern, key in score_patterns:
            match = re.search(pattern, response_text, re.IGNORECASE)
            if match:
                scores[key] = float(match.group(1))
        
        # Extract overall percentage
        overall_pattern = r'Overall\s+[Ss]core:\s*(\d+)%'
        overall_match = re.search(overall_pattern, response_text, re.IGNORECASE)
        if overall_match:
            scores['overall_percentage'] = int(overall_match.group(1))
        
        # Calculate hash of response for integrity
        scores['response_hash'] = hashlib.sha256(response_text.encode()).hexdigest()
        
        return scores
    
    def verify_and_log_assessment(self, 
                                  response_text: str, 
                                  phase: str,
                                  attempt_number: int,
                                  timestamp: Optional[str] = None) -> Dict[str, any]:
        """
        Verify assessment and create immutable audit log entry.
        Returns verified scores and audit entry.
        """
        if not timestamp:
            timestamp = datetime.now().isoformat()
        
        # Parse actual scores
        scores = self.parse_grok_response(response_text)
        
        # Create audit entry
        audit_entry = {
            "timestamp": timestamp,
            "phase": phase,
            "attempt": attempt_number,
            "scores": scores,
            "passed_95_threshold": scores.get('overall_percentage', 0) >= 95,
            "response_length": len(response_text)
        }
        
        # Add to audit log
        self.audit_log["entries"].append(audit_entry)
        
        # Save audit log
        with open(self.audit_log_path, 'w') as f:
            json.dump(self.audit_log, f, indent=2)
        
        # Update verified scores
        self._update_verified_scores(phase, attempt_number, scores)
        
        return audit_entry
    
    def _update_verified_scores(self, phase: str, attempt: int, scores: Dict):
        """Update the verified scores file."""
        if os.path.exists(self.verified_scores_path):
            with open(self.verified_scores_path, 'r') as f:
                verified = json.load(f)
        else:
            verified = {}
        
        if phase not in verified:
            verified[phase] = {}
        
        verified[phase][f"attempt_{attempt}"] = {
            "scores": scores,
            "timestamp": datetime.now().isoformat(),
            "passed": scores.get('overall_percentage', 0) >= 95
        }
        
        with open(self.verified_scores_path, 'w') as f:
            json.dump(verified, f, indent=2)
    
    def get_latest_verified_score(self, phase: str) -> Optional[Dict]:
        """Get the latest verified score for a phase."""
        if not os.path.exists(self.verified_scores_path):
            return None
        
        with open(self.verified_scores_path, 'r') as f:
            verified = json.load(f)
        
        if phase not in verified:
            return None
        
        # Get latest attempt
        attempts = sorted(verified[phase].keys(), 
                         key=lambda x: int(x.split('_')[1]))
        if attempts:
            return verified[phase][attempts[-1]]
        
        return None
    
    def generate_truth_report(self) -> str:
        """Generate a truthful report of all Grok assessments."""
        report = ["# Grok Assessment Truth Report\n"]
        report.append(f"Generated: {datetime.now().isoformat()}\n")
        
        # Summary of all attempts
        phase_summary = {}
        for entry in self.audit_log["entries"]:
            phase = entry["phase"]
            if phase not in phase_summary:
                phase_summary[phase] = []
            phase_summary[phase].append(entry)
        
        for phase, entries in phase_summary.items():
            report.append(f"\n## {phase}")
            report.append(f"Total Attempts: {len(entries)}")
            
            # Show all attempts
            for entry in entries:
                score = entry["scores"].get("overall_percentage", "Unknown")
                passed = "✅ PASSED" if entry["passed_95_threshold"] else "❌ FAILED"
                report.append(f"- Attempt {entry['attempt']}: {score}% {passed}")
            
            # Latest status
            latest = self.get_latest_verified_score(phase)
            if latest:
                report.append(f"\n**Latest Verified Score**: {latest['scores'].get('overall_percentage', 'Unknown')}%")
                report.append(f"**Status**: {'Ready for next phase' if latest['passed'] else 'Requires improvements'}")
        
        return "\n".join(report)
    
    def check_discrepancy(self, claimed_score: int, phase: str) -> Tuple[bool, str]:
        """
        Check if a claimed score matches verified score.
        Returns (is_truthful, explanation).
        """
        latest = self.get_latest_verified_score(phase)
        if not latest:
            return False, f"No verified score found for {phase}"
        
        actual_score = latest['scores'].get('overall_percentage', 0)
        if claimed_score != actual_score:
            return False, f"Claimed {claimed_score}% but actual is {actual_score}%"
        
        return True, "Score verified as accurate"


# CLI tool for verification
if __name__ == "__main__":
    import sys
    
    if len(sys.argv) < 2:
        print("Usage: python grok-verification-system.py <command> [args]")
        print("Commands:")
        print("  verify <response_file> <phase> <attempt> - Verify a Grok response")
        print("  report - Generate truth report")
        print("  check <claimed_score> <phase> - Check if claimed score is truthful")
        sys.exit(1)
    
    verifier = GrokVerificationSystem("/home/andre/projects/money-quiz/money-quiz-v7")
    
    command = sys.argv[1]
    
    if command == "verify" and len(sys.argv) >= 5:
        with open(sys.argv[2], 'r') as f:
            response = f.read()
        result = verifier.verify_and_log_assessment(
            response, sys.argv[3], int(sys.argv[4])
        )
        print(f"Verified score: {result['scores'].get('overall_percentage', 'Unknown')}%")
        print(f"Passed 95% threshold: {result['passed_95_threshold']}")
    
    elif command == "report":
        print(verifier.generate_truth_report())
    
    elif command == "check" and len(sys.argv) >= 4:
        is_truthful, explanation = verifier.check_discrepancy(
            int(sys.argv[2]), sys.argv[3]
        )
        print(f"Truthful: {is_truthful}")
        print(f"Explanation: {explanation}")
    
    else:
        print("Invalid command or arguments")