#!/usr/bin/env php
<?php
/**
 * Syntax validation script
 * 
 * Validates PHP syntax for all files in the src directory
 */

$errors = [];
$checked = 0;

function check_syntax($file) {
    $output = [];
    $return = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return);
    return $return === 0;
}

function check_directory($dir) {
    global $errors, $checked;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $filepath = $file->getPathname();
            $checked++;
            
            if (!check_syntax($filepath)) {
                $errors[] = $filepath;
                echo "✗ Syntax error in: $filepath\n";
            } else {
                echo "✓ $filepath\n";
            }
        }
    }
}

echo "Validating PHP syntax...\n";
echo "========================\n\n";

// Check src directory
if (is_dir(__DIR__ . '/src')) {
    check_directory(__DIR__ . '/src');
}

// Check main plugin file
if (file_exists(__DIR__ . '/money-quiz.php')) {
    $checked++;
    if (!check_syntax(__DIR__ . '/money-quiz.php')) {
        $errors[] = __DIR__ . '/money-quiz.php';
        echo "✗ Syntax error in: money-quiz.php\n";
    } else {
        echo "✓ money-quiz.php\n";
    }
}

echo "\n========================\n";
echo "Checked $checked files\n";

if (empty($errors)) {
    echo "✅ All files have valid syntax!\n";
    exit(0);
} else {
    echo "❌ Found " . count($errors) . " files with syntax errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}