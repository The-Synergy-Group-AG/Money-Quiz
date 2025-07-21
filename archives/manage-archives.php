<?php
/**
 * Money Quiz Plugin Archive Manager
 * 
 * This script helps manage the plugin archives, including listing versions,
 * searching for specific features, and basic archive operations.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('CLI_SCRIPT')) {
    define('CLI_SCRIPT', true);
}

class MoneyQuizArchiveManager {
    private $archiveDir;
    private $versionIndexFile;
    
    public function __construct() {
        $this->archiveDir = __DIR__ . '/old-versions/';
        $this->versionIndexFile = __DIR__ . '/version-index.json';
    }
    
    /**
     * List all archived versions
     */
    public function listVersions() {
        $index = $this->loadVersionIndex();
        
        echo "=== Money Quiz Plugin Archives ===\n\n";
        echo "Total versions: " . $index['archive_info']['total_versions'] . "\n";
        echo "Total size: " . $index['archive_info']['total_size_mb'] . "MB\n\n";
        
        foreach ($index['versions'] as $version) {
            $status = $version['status'] === 'production' ? '🟢' : '🔵';
            echo sprintf(
                "%s %s (%s) - %sMB - %s\n",
                $status,
                $version['version'],
                $version['filename'],
                $version['size_mb'],
                $version['notes']
            );
        }
        
        echo "\n=== Backup Files ===\n";
        foreach ($index['backup_files'] as $backup) {
            echo sprintf(
                "📄 %s - %sMB - %s\n",
                $backup['filename'],
                $backup['size_mb'],
                $backup['notes']
            );
        }
    }
    
    /**
     * Search for versions with specific features
     */
    public function searchVersions($searchTerm) {
        $index = $this->loadVersionIndex();
        $results = [];
        
        foreach ($index['versions'] as $version) {
            foreach ($version['features'] as $feature) {
                if (stripos($feature, $searchTerm) !== false) {
                    $results[] = $version;
                    break;
                }
            }
        }
        
        if (empty($results)) {
            echo "No versions found with feature: '$searchTerm'\n";
            return;
        }
        
        echo "=== Versions with feature: '$searchTerm' ===\n\n";
        foreach ($results as $version) {
            echo sprintf(
                "🔍 %s (%s) - %sMB\n",
                $version['version'],
                $version['filename'],
                $version['size_mb']
            );
            echo "   Features: " . implode(', ', $version['features']) . "\n\n";
        }
    }
    
    /**
     * Get archive statistics
     */
    public function getStatistics() {
        $index = $this->loadVersionIndex();
        
        $stats = [
            'total_versions' => 0,
            'production_versions' => 0,
            'archived_versions' => 0,
            'emergency_versions' => 0,
            'total_size_mb' => 0,
            'largest_version' => null,
            'smallest_version' => null
        ];
        
        $largestSize = 0;
        $smallestSize = PHP_FLOAT_MAX;
        
        foreach ($index['versions'] as $version) {
            $stats['total_versions']++;
            $stats['total_size_mb'] += $version['size_mb'];
            
            if ($version['status'] === 'production') {
                $stats['production_versions']++;
            } else {
                $stats['archived_versions']++;
            }
            
            if (strpos($version['filename'], 'emergency') !== false) {
                $stats['emergency_versions']++;
            }
            
            if ($version['size_mb'] > $largestSize) {
                $largestSize = $version['size_mb'];
                $stats['largest_version'] = $version;
            }
            
            if ($version['size_mb'] < $smallestSize) {
                $smallestSize = $version['size_mb'];
                $stats['smallest_version'] = $version;
            }
        }
        
        echo "=== Archive Statistics ===\n\n";
        echo "Total versions: " . $stats['total_versions'] . "\n";
        echo "Production versions: " . $stats['production_versions'] . "\n";
        echo "Archived versions: " . $stats['archived_versions'] . "\n";
        echo "Emergency versions: " . $stats['emergency_versions'] . "\n";
        echo "Total size: " . $stats['total_size_mb'] . "MB\n\n";
        
        if ($stats['largest_version']) {
            echo "Largest version: " . $stats['largest_version']['version'] . 
                 " (" . $stats['largest_version']['size_mb'] . "MB)\n";
        }
        
        if ($stats['smallest_version']) {
            echo "Smallest version: " . $stats['smallest_version']['version'] . 
                 " (" . $stats['smallest_version']['size_mb'] . "MB)\n";
        }
    }
    
    /**
     * Check archive integrity
     */
    public function checkIntegrity() {
        $index = $this->loadVersionIndex();
        $missingFiles = [];
        $existingFiles = [];
        
        foreach ($index['versions'] as $version) {
            $filePath = $this->archiveDir . $version['filename'];
            if (file_exists($filePath)) {
                $existingFiles[] = $version['filename'];
            } else {
                $missingFiles[] = $version['filename'];
            }
        }
        
        echo "=== Archive Integrity Check ===\n\n";
        echo "Existing files: " . count($existingFiles) . "\n";
        echo "Missing files: " . count($missingFiles) . "\n\n";
        
        if (!empty($missingFiles)) {
            echo "Missing files:\n";
            foreach ($missingFiles as $file) {
                echo "  ❌ $file\n";
            }
        } else {
            echo "✅ All archived files are present\n";
        }
    }
    
    /**
     * Load version index from JSON
     */
    private function loadVersionIndex() {
        if (!file_exists($this->versionIndexFile)) {
            throw new Exception("Version index file not found: " . $this->versionIndexFile);
        }
        
        $content = file_get_contents($this->versionIndexFile);
        $index = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in version index: " . json_last_error_msg());
        }
        
        return $index;
    }
    
    /**
     * Show help information
     */
    public function showHelp() {
        echo "Money Quiz Plugin Archive Manager\n\n";
        echo "Usage:\n";
        echo "  php manage-archives.php list                    - List all versions\n";
        echo "  php manage-archives.php search <term>          - Search for features\n";
        echo "  php manage-archives.php stats                  - Show statistics\n";
        echo "  php manage-archives.php integrity             - Check file integrity\n";
        echo "  php manage-archives.php help                   - Show this help\n\n";
        echo "Examples:\n";
        echo "  php manage-archives.php search \"critical\"\n";
        echo "  php manage-archives.php search \"emergency\"\n";
    }
}

// CLI interface
if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
    $manager = new MoneyQuizArchiveManager();
    
    if ($argc < 2) {
        $manager->showHelp();
        exit(1);
    }
    
    $command = $argv[1];
    
    try {
        switch ($command) {
            case 'list':
                $manager->listVersions();
                break;
                
            case 'search':
                if ($argc < 3) {
                    echo "Error: Search term required\n";
                    exit(1);
                }
                $manager->searchVersions($argv[2]);
                break;
                
            case 'stats':
                $manager->getStatistics();
                break;
                
            case 'integrity':
                $manager->checkIntegrity();
                break;
                
            case 'help':
                $manager->showHelp();
                break;
                
            default:
                echo "Unknown command: $command\n";
                $manager->showHelp();
                exit(1);
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
} 