#!/usr/bin/env python3
"""
Check GitHub Actions workflow status for Money Quiz repository
"""

import requests
import json
import time
from datetime import datetime

# GitHub API configuration
REPO_OWNER = "The-Synergy-Group-AG"
REPO_NAME = "Money-Quiz"
BRANCH = "arj-upgrade"
API_BASE = f"https://api.github.com/repos/{REPO_OWNER}/{REPO_NAME}"

def get_workflow_runs():
    """Get recent workflow runs"""
    url = f"{API_BASE}/actions/runs"
    params = {
        "branch": BRANCH,
        "per_page": 10
    }
    
    response = requests.get(url, params=params)
    if response.status_code == 200:
        return response.json()
    else:
        print(f"Error fetching workflow runs: {response.status_code}")
        return None

def get_workflow_jobs(run_id):
    """Get jobs for a specific workflow run"""
    url = f"{API_BASE}/actions/runs/{run_id}/jobs"
    
    response = requests.get(url)
    if response.status_code == 200:
        return response.json()
    else:
        return None

def format_time(timestamp):
    """Format ISO timestamp to readable format"""
    dt = datetime.fromisoformat(timestamp.replace('Z', '+00:00'))
    return dt.strftime('%Y-%m-%d %H:%M:%S')

def print_workflow_status():
    """Print current workflow status"""
    print("=== GitHub Actions Workflow Status ===")
    print(f"Repository: {REPO_OWNER}/{REPO_NAME}")
    print(f"Branch: {BRANCH}")
    print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("=" * 50)
    
    runs_data = get_workflow_runs()
    if not runs_data:
        print("No workflow data available")
        return
    
    runs = runs_data.get('workflow_runs', [])
    if not runs:
        print("No workflow runs found")
        return
    
    for run in runs[:5]:  # Show last 5 runs
        print(f"\nWorkflow: {run['name']}")
        print(f"Status: {run['status']} - {run['conclusion'] or 'in progress'}")
        print(f"Started: {format_time(run['created_at'])}")
        commit_msg = run['head_commit']['message'].split('\n')[0][:60]
        print(f"Commit: {commit_msg}...")
        
        # Get job details if run is in progress or failed
        if run['status'] in ['in_progress', 'completed']:
            jobs_data = get_workflow_jobs(run['id'])
            if jobs_data:
                jobs = jobs_data.get('jobs', [])
                for job in jobs:
                    status_icon = {
                        'completed': '✅' if job['conclusion'] == 'success' else '❌',
                        'in_progress': '🔄',
                        'queued': '⏳'
                    }.get(job['status'], '❓')
                    
                    print(f"  {status_icon} {job['name']}: {job['status']} - {job['conclusion'] or 'running'}")
                    
                    # Show failed steps
                    if job['conclusion'] == 'failure':
                        for step in job['steps']:
                            if step['conclusion'] == 'failure':
                                print(f"     ❌ Failed step: {step['name']}")

def check_for_errors():
    """Check for workflow errors and suggest fixes"""
    print("\n=== Checking for Common Workflow Errors ===")
    
    runs_data = get_workflow_runs()
    if not runs_data:
        return
    
    errors_found = False
    runs = runs_data.get('workflow_runs', [])
    
    for run in runs:
        if run['conclusion'] == 'failure':
            errors_found = True
            print(f"\n❌ Failed workflow: {run['name']}")
            
            jobs_data = get_workflow_jobs(run['id'])
            if jobs_data:
                jobs = jobs_data.get('jobs', [])
                for job in jobs:
                    if job['conclusion'] == 'failure':
                        print(f"   Failed job: {job['name']}")
                        
                        # Analyze common errors and suggest fixes
                        for step in job['steps']:
                            if step['conclusion'] == 'failure':
                                step_name = step['name'].lower()
                                
                                # Suggest fixes based on step name
                                if 'phpcs' in step_name or 'coding standards' in step_name:
                                    print("   💡 Fix: Run 'composer install' and 'composer cs:fix' locally")
                                elif 'phpstan' in step_name:
                                    print("   💡 Fix: Check PHPStan errors with 'composer analyze'")
                                elif 'phpunit' in step_name:
                                    print("   💡 Fix: Ensure test bootstrap file exists and dependencies are installed")
                                elif 'syntax' in step_name:
                                    print("   💡 Fix: Check for PHP syntax errors with 'php -l'")
    
    if not errors_found:
        print("✅ No workflow errors found!")
    
    return errors_found

if __name__ == "__main__":
    print_workflow_status()
    
    # Check for errors
    has_errors = check_for_errors()
    
    # Provide summary
    print("\n=== Summary ===")
    if has_errors:
        print("⚠️  Some workflows are failing. Review the errors above and apply the suggested fixes.")
    else:
        print("✅ All workflows are passing or in progress!")
    
    print("\nView full details at:")
    print(f"https://github.com/{REPO_OWNER}/{REPO_NAME}/actions")