#!/usr/bin/env python3
"""
Prepare code files and analysis for Grok review
"""

import os
import shutil
from pathlib import Path

def prepare_review_package():
    """Prepare a complete package for Grok to review"""
    
    # Create review package directory
    package_dir = Path("../ai-reviews")
    package_dir.mkdir(exist_ok=True)
    
    # Key files to include
    key_files = [
        "moneyquiz.php",
        "class.moneyquiz.php",
        "quiz.moneycoach.php",
        "integration.admin.php",
        "questions.admin.php",
        "stats.admin.php",
        "cta.admin.php",
        "assets/js/mq.js",
        "style.css"
    ]
    
    # Copy key files
    code_dir = Path("sample-code")
    code_dir.mkdir(exist_ok=True)
    
    for file in key_files:
        src = Path(file)
        if src.exists():
            # Create subdirectories if needed
            dst = code_dir / file
            dst.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(src, dst)
            print(f"✓ Copied {file}")
        else:
            print(f"✗ File not found: {file}")
    
    # Copy Claude's analysis reports
    reports = [
        "Money-Quiz-Plugin-Analysis-Report.md",
        "Money-Quiz-Code-Review-Report.md"
    ]
    
    for report in reports:
        if Path(report).exists():
            shutil.copy2(report, package_dir / report)
            print(f"✓ Copied {report}")
    
    # Create a code snippets file with problematic examples
    with open("sample-code/critical-code-examples.php", "w") as f:
        f.write("""<?php
// CRITICAL CODE EXAMPLES FROM MONEY QUIZ PLUGIN

// 1. SQL INJECTION VULNERABILITIES
// From quiz.moneycoach.php line 303
$results = $wpdb->get_row( "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS." WHERE Email = '".$Email."'", OBJECT );

// From questions.admin.php line 30
if(isset($_REQUEST['questionid']) && $_REQUEST['questionid'] > 0 ){
    $where = " where Master_ID = ".$_REQUEST['questionid']; 
}

// 2. XSS VULNERABILITIES
// Direct output without escaping
echo $row->Question;
echo $_REQUEST['Question'];
echo '<div class="result">' . $user_data['name'] . '</div>';

// 3. MISSING CSRF PROTECTION
// From moneyquiz.php line 854
if(isset($_POST['action']) && $_POST['action'] == "update"){
    // No nonce verification
    $wpdb->update($table, $data);
}

// 4. DIVISION BY ZERO BUG
// From moneyquiz.php line 1446
function get_percentage($Initiator_question,$score_total_value){
    $ques_total_value = ($Initiator_question * 8);
    return $cal_percentage = ($score_total_value/$ques_total_value*100); // Crashes if $Initiator_question is 0
}

// 5. HARDCODED CREDENTIALS
// From moneyquiz.php lines 35-38
define('MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL', 'andre@101businessinsights.info');
define('MONEYQUIZ_SPECIAL_SECRET_KEY', '5bcd52f5276855.46942741');
define('MONEYQUIZ_LICENSE_SERVER_URL', 'https://www.101businessinsights.com');

// 6. NO ERROR HANDLING
$wpdb->insert( 
    $table_prefix.TABLE_MQ_PROSPECTS,
    $data_insert
);
$prospect_id = $wpdb->insert_id; // No check if insert succeeded

// 7. WEAK ACCESS CONTROL
if ( !function_exists( 'add_action' ) ) {
    echo 'direct access is not allowed.';
    exit;
}
// Should use: defined('ABSPATH') or die();

// 8. UNREACHABLE CODE
// From quiz.moneycoach.php line 290
exit;
$prospect_data = $_POST['prospect_data']; // This line never executes

// 9. EXTERNAL DEPENDENCY
// From quiz.moneycoach.php line 285
<img src='https://mindfulmoneycoaching.online/wp-content/plugins/moneyquiz/assets/images/mind-full-preloader.webp'>
?>
""")
    
    print(f"✓ Created critical code examples file")
    
    # Create instructions file
    with open(package_dir / "grok-instructions.md", "w") as f:
        f.write("""# Instructions for Grok Review

## How to Review This Plugin:

1. **Start with**: `../ai-reviews/review-request.md` - Contains Claude's findings and specific questions
2. **Review code files**: Check the `../automation-tools/sample-code/` directory for the actual plugin code
3. **Critical examples**: See `../automation-tools/sample-code/critical-code-examples.php` for the most problematic code snippets
4. **Compare analyses**: Review Claude's reports to provide additional insights

## Priority Areas:

1. **Security vulnerabilities** - Confirm and find additional issues
2. **Architecture recommendations** - Suggest modern patterns
3. **Performance optimizations** - Identify bottlenecks
4. **Testing strategies** - Recommend comprehensive approach
5. **Migration path** - From current to modern architecture

## Key Questions to Answer:

1. What critical security issues did Claude miss?
2. What's your recommended architecture for v4.0?
3. How would you prioritize the fixes?
4. What testing framework would work best?
5. Any WordPress-specific best practices to add?

## Deliverables Requested:

1. Security audit report
2. Architecture recommendations
3. Performance analysis
4. Testing strategy
5. Implementation roadmap

Please provide detailed, actionable recommendations with code examples where applicable.
""")
    
    print(f"✓ Created Grok instructions")
    
    # Create summary statistics
    stats_content = """# Money Quiz Plugin Statistics

## Codebase Overview:
- **Total PHP Files**: 25+
- **Total Lines of Code**: ~10,000+
- **Database Tables**: 15 custom tables
- **Main Plugin File**: 1000+ lines (monolithic)
- **Version**: 3.3
- **WordPress Minimum**: 2.0

## Vulnerability Count (Claude's Analysis):
- **SQL Injection Points**: 10+
- **XSS Vulnerabilities**: 20+
- **CSRF Vulnerabilities**: All forms
- **Hardcoded Secrets**: 3
- **Missing Error Handling**: Throughout

## Code Quality Metrics:
- **Coding Standards**: Not followed
- **Documentation**: Minimal
- **Error Handling**: None
- **Test Coverage**: 0%
- **Code Duplication**: High

## Feature Set:
- Quiz System (4 variations)
- 8 Jungian Archetypes
- Lead Generation
- Email Integration (MailerLite only)
- Analytics Dashboard
- Customizable CTAs
- Multi-language: No
- REST API: No
"""
    
    with open(package_dir / "plugin-statistics.md", "w") as f:
        f.write(stats_content)
    
    print(f"✓ Created plugin statistics")
    
    print("\n" + "="*60)
    print("✓ Grok review package prepared successfully!")
    print(f"✓ Package location: {package_dir.absolute()}")
    print("\nThe package contains:")
    print("  - ../ai-reviews/review-request.md (main review request with Claude's findings)")
    print("  - ../automation-tools/sample-code/ (key plugin files)")
    print("  - ../automation-tools/sample-code/critical-code-examples.php (most problematic code)")
    print("  - Claude's analysis reports")
    print("  - grok-instructions.md (review instructions)")
    print("  - plugin-statistics.md (codebase metrics)")
    print("\nYou can now share this package with Grok for review.")

if __name__ == "__main__":
    prepare_review_package()