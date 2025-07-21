<?php
/**
 * User Guide Generator
 * 
 * @package MoneyQuiz\Documentation
 * @version 1.0.0
 */

namespace MoneyQuiz\Documentation;

/**
 * User Guide Generator
 */
class UserGuideGenerator {
    
    private static $instance = null;
    
    private function __construct() {}
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate user guide
     */
    public function generate($format = 'markdown') {
        switch ($format) {
            case 'markdown':
                return $this->generateMarkdown();
            case 'html':
                return $this->generateHtml();
            case 'pdf':
                return $this->generatePdf();
            default:
                throw new \Exception("Unknown format: $format");
        }
    }
    
    /**
     * Generate markdown guide
     */
    private function generateMarkdown() {
        $guide = <<<MARKDOWN
# Money Quiz User Guide

## Table of Contents

1. [Getting Started](#getting-started)
2. [Creating Quizzes](#creating-quizzes)
3. [Managing Questions](#managing-questions)
4. [Quiz Settings](#quiz-settings)
5. [Taking Quizzes](#taking-quizzes)
6. [Viewing Results](#viewing-results)
7. [Analytics](#analytics)
8. [API Usage](#api-usage)
9. [Troubleshooting](#troubleshooting)

## Getting Started

### Installation

1. Upload the `money-quiz` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Navigate to **Money Quiz** in your WordPress admin

### First Steps

After activation:
1. Create your first quiz
2. Add questions to the quiz
3. Configure quiz settings
4. Publish and share

## Creating Quizzes

### Basic Quiz Creation

1. Go to **Money Quiz > Add New**
2. Enter a quiz title
3. Add a description (optional)
4. Save as draft or publish

### Quiz Types

- **Standard Quiz**: Traditional multiple-choice format
- **Personality Quiz**: Results based on answer patterns
- **Scored Quiz**: Points-based with pass/fail

## Managing Questions

### Adding Questions

1. Navigate to your quiz
2. Click **Add Question**
3. Choose question type:
   - Multiple Choice
   - True/False
   - Multiple Select

### Question Settings

- **Points**: Assign point values
- **Explanation**: Add answer explanations
- **Order**: Drag to reorder questions

## Quiz Settings

### General Settings

- **Time Limit**: Set quiz duration
- **Attempts**: Limit attempts per user
- **Randomization**: Randomize questions/answers

### Display Options

- **Theme**: Choose quiz appearance
- **Progress Bar**: Show/hide progress
- **Navigation**: Allow skip/previous

### Results Settings

- **Show Score**: Display final score
- **Show Answers**: Reveal correct answers
- **Certificate**: Enable completion certificates

## Taking Quizzes

### Embedding Quizzes

Use the shortcode:
```
[money_quiz id="123"]
```

### Widget Usage

1. Go to **Appearance > Widgets**
2. Add "Money Quiz" widget
3. Select quiz and configure

### Direct Links

Share quiz URL:
```
https://yoursite.com/quiz/quiz-name/
```

## Viewing Results

### Individual Results

1. Go to **Money Quiz > Results**
2. Filter by quiz, user, or date
3. View detailed responses

### Bulk Export

1. Select results to export
2. Choose format (CSV, Excel, PDF)
3. Download file

## Analytics

### Dashboard Overview

- Total quizzes taken
- Average scores
- Completion rates
- Popular quizzes

### Detailed Reports

1. **Quiz Performance**: Individual quiz metrics
2. **User Analytics**: User engagement data
3. **Question Analysis**: Question difficulty stats

### Custom Reports

Create custom reports:
1. Select metrics
2. Choose date range
3. Apply filters
4. Generate report

## API Usage

### Authentication

Include nonce in requests:
```javascript
fetch('/wp-json/money-quiz/v1/quizzes', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
});
```

### Common Endpoints

- `GET /quizzes` - List quizzes
- `POST /results` - Submit results
- `GET /analytics` - Get analytics data

## Troubleshooting

### Common Issues

**Quiz not displaying:**
- Check shortcode syntax
- Verify quiz is published
- Clear cache

**Results not saving:**
- Check user permissions
- Verify database tables
- Check error logs

**Styling issues:**
- Check theme compatibility
- Disable conflicting plugins
- Use custom CSS

### Support

For additional help:
- Check [documentation](https://docs.moneyquiz.com)
- Visit [support forum](https://support.moneyquiz.com)
- Contact support@moneyquiz.com

## Advanced Features

### Webhooks

Configure webhooks for:
- Quiz completion
- New user registration
- Score thresholds

### Custom Fields

Add custom data:
1. Define fields in settings
2. Map to quiz/user data
3. Include in exports

### Integration

Integrate with:
- Email marketing tools
- LMS platforms
- CRM systems

MARKDOWN;
        
        return $guide;
    }
    
    /**
     * Generate HTML guide
     */
    private function generateHtml() {
        $markdown = $this->generateMarkdown();
        
        // Simple markdown to HTML conversion
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Money Quiz User Guide</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2, h3 { color: #2c3e50; }
        h1 { border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        h2 { margin-top: 30px; }
        code {
            background: #f4f4f4;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: Consolas, Monaco, monospace;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        ul { padding-left: 30px; }
        a { color: #3498db; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .toc {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 20px;
            margin-bottom: 30px;
        }
        .note {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
HTML;
        
        // Convert markdown to HTML (simplified)
        $html .= $this->convertMarkdownToHtml($markdown);
        
        $html .= "</body></html>";
        
        return $html;
    }
    
    /**
     * Convert markdown to HTML
     */
    private function convertMarkdownToHtml($markdown) {
        // Basic conversion
        $html = $markdown;
        
        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
        
        // Lists
        $html = preg_replace('/^\- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $html);
        
        // Code blocks
        $html = preg_replace('/```(.+?)```/s', '<pre><code>$1</code></pre>', $html);
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
        
        // Links
        $html = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $html);
        
        // Bold
        $html = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $html);
        
        // Paragraphs
        $html = '<p>' . preg_replace('/\n\n/', '</p><p>', $html) . '</p>';
        
        return $html;
    }
    
    /**
     * Generate PDF guide
     */
    private function generatePdf() {
        $html = $this->generateHtml();
        
        // Would use PDF library
        // For now, return HTML with PDF headers
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="money-quiz-guide.pdf"');
        
        return $html;
    }
    
    /**
     * Generate contextual help
     */
    public function generateContextualHelp($screen) {
        $help = [];
        
        switch ($screen) {
            case 'quiz-list':
                $help = [
                    'title' => 'Managing Quizzes',
                    'content' => 'View and manage all your quizzes from this screen.',
                    'tips' => [
                        'Click on a quiz title to edit',
                        'Use bulk actions to delete multiple quizzes',
                        'Filter by status to find specific quizzes'
                    ]
                ];
                break;
                
            case 'quiz-edit':
                $help = [
                    'title' => 'Editing Quiz',
                    'content' => 'Configure your quiz settings and add questions.',
                    'tips' => [
                        'Save frequently to avoid losing changes',
                        'Preview your quiz before publishing',
                        'Use categories to organize questions'
                    ]
                ];
                break;
                
            case 'results':
                $help = [
                    'title' => 'Quiz Results',
                    'content' => 'View and analyze quiz submissions.',
                    'tips' => [
                        'Export results for external analysis',
                        'Filter by date range for reports',
                        'Click on a result to view details'
                    ]
                ];
                break;
        }
        
        return $help;
    }
    
    /**
     * Generate tooltip
     */
    public function generateTooltip($key) {
        $tooltips = [
            'quiz_type' => 'Choose the type of quiz you want to create',
            'time_limit' => 'Set a time limit in minutes (0 for unlimited)',
            'passing_score' => 'Minimum percentage required to pass',
            'randomize' => 'Randomize question order for each attempt',
            'show_answers' => 'Display correct answers after submission',
            'webhook_url' => 'URL to receive quiz completion notifications',
            'export_format' => 'Choose format for downloading results'
        ];
        
        return $tooltips[$key] ?? '';
    }
}