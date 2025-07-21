<?php
/**
 * Version Tracker for Money Quiz Plugin
 * @package The Synergy Group AG
 * @Author: The Synergy Group AG
 * @Author URI: https://www.thesynergygroup.ch/
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MoneyQuizVersionTracker {
    
    private static $version_file = 'version-info.json';
    private static $changelog_file = 'CHANGELOG.md';
    
    /**
     * Get current version from version file
     */
    public static function get_current_version() {
        $version_file_path = MONEYQUIZ__PLUGIN_DIR . self::$version_file;
        
        if (file_exists($version_file_path)) {
            $version_data = json_decode(file_get_contents($version_file_path), true);
            return $version_data['current_version'] ?? '3.6';
        }
        
        return '3.6'; // Default fallback
    }
    
    /**
     * Update version and create changelog entry
     */
    public static function update_version($new_version, $changes = []) {
        $version_file_path = MONEYQUIZ__PLUGIN_DIR . self::$version_file;
        $changelog_file_path = MONEYQUIZ__PLUGIN_DIR . self::$changelog_file;
        
        // Create version data
        $version_data = [
            'current_version' => $new_version,
            'last_updated' => current_time('Y-m-d H:i:s'),
            'previous_version' => self::get_current_version(),
            'changes' => $changes
        ];
        
        // Save version file
        file_put_contents($version_file_path, json_encode($version_data, JSON_PRETTY_PRINT));
        
        // Update changelog
        self::update_changelog($new_version, $changes, $changelog_file_path);
        
        return true;
    }
    
    /**
     * Update changelog file
     */
    private static function update_changelog($version, $changes, $changelog_path) {
        $date = current_time('Y-m-d');
        $changelog_entry = "\n## [{$version}] - {$date}\n\n";
        
        if (!empty($changes)) {
            foreach ($changes as $change) {
                $changelog_entry .= "- {$change}\n";
            }
        } else {
            $changelog_entry .= "- General improvements and bug fixes\n";
        }
        
        $changelog_entry .= "\n";
        
        // Read existing changelog
        $existing_changelog = '';
        if (file_exists($changelog_path)) {
            $existing_changelog = file_get_contents($changelog_path);
        } else {
            $existing_changelog = "# Money Quiz Plugin Changelog\n\nAll notable changes to the Money Quiz Plugin will be documented in this file.\n\n";
        }
        
        // Insert new entry after the header
        $lines = explode("\n", $existing_changelog);
        $insert_position = 0;
        
        // Find the first version entry
        foreach ($lines as $index => $line) {
            if (strpos($line, '## [') === 0) {
                $insert_position = $index;
                break;
            }
        }
        
        // Insert the new changelog entry
        array_splice($lines, $insert_position, 0, explode("\n", trim($changelog_entry)));
        
        // Save updated changelog
        file_put_contents($changelog_path, implode("\n", $lines));
    }
    
    /**
     * Get version history
     */
    public static function get_version_history() {
        $version_file_path = MONEYQUIZ__PLUGIN_DIR . self::$version_file;
        
        if (file_exists($version_file_path)) {
            return json_decode(file_get_contents($version_file_path), true);
        }
        
        return null;
    }
    
    /**
     * Auto-increment patch version
     */
    public static function increment_patch_version() {
        $current_version = self::get_current_version();
        $version_parts = explode('.', $current_version);
        
        if (count($version_parts) >= 3) {
            $version_parts[2] = intval($version_parts[2]) + 1;
            $new_version = implode('.', $version_parts);
            
            self::update_version($new_version, ['Auto-incremented patch version']);
            return $new_version;
        }
        
        return $current_version;
    }
    
    /**
     * Auto-increment minor version
     */
    public static function increment_minor_version() {
        $current_version = self::get_current_version();
        $version_parts = explode('.', $current_version);
        
        if (count($version_parts) >= 2) {
            $version_parts[1] = intval($version_parts[1]) + 1;
            $version_parts[2] = 0; // Reset patch version
            $new_version = implode('.', $version_parts);
            
            self::update_version($new_version, ['Auto-incremented minor version']);
            return $new_version;
        }
        
        return $current_version;
    }
    
    /**
     * Auto-increment major version
     */
    public static function increment_major_version() {
        $current_version = self::get_current_version();
        $version_parts = explode('.', $current_version);
        
        if (count($version_parts) >= 1) {
            $version_parts[0] = intval($version_parts[0]) + 1;
            $version_parts[1] = 0; // Reset minor version
            $version_parts[2] = 0; // Reset patch version
            $new_version = implode('.', $version_parts);
            
            self::update_version($new_version, ['Auto-incremented major version']);
            return $new_version;
        }
        
        return $current_version;
    }
}

// Initialize version tracking on plugin activation (STRATEGIC FIX - WordPress readiness check)
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, function() {
        MoneyQuizVersionTracker::update_version('3.6', [
            'Initial version tracking implementation',
            'Updated menu structure with logical grouping',
            'Added comprehensive ReadMe documentation',
            'Updated company branding to The Synergy Group AG',
            'Moved Credits menu to end of navigation',
            'Updated integration links to new Google Forms URL'
        ]);
    });
} 