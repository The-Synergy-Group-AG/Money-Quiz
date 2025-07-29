<?php
/**
 * Database Query Migration Tool
 * 
 * Automatically migrates unsafe database queries to use safe wrappers
 * 
 * Usage: php migrate-database-queries.php [--dry-run] [--file=path/to/file.php]
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

// CLI check
if ( php_sapi_name() !== 'cli' ) {
    die( 'This script must be run from the command line.' );
}

class Database_Query_Migrator {
    
    /**
     * @var array Patterns to find and replace
     */
    private $patterns = [
        // Direct wpdb->query with concatenation
        [
            'pattern' => '/\$wpdb->query\s*\(\s*"([^"]+)"\s*\.\s*\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\.\s*"([^"]*)"\s*\)/',
            'replacement' => 'mq_safe_db()->safe_query( "$1%s$3", [ $$$2 ] )',
            'description' => 'Direct query with variable concatenation'
        ],
        
        // Direct wpdb->query with embedded variables
        [
            'pattern' => '/\$wpdb->query\s*\(\s*"([^"]*)\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)([^"]*)"\s*\)/',
            'replacement' => 'mq_safe_db()->safe_query( "$1%s$3", [ $$$2 ] )',
            'description' => 'Direct query with embedded variable'
        ],
        
        // wpdb->get_results with concatenation
        [
            'pattern' => '/\$wpdb->get_results\s*\(\s*"([^"]+)"\s*\.\s*\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\.\s*"([^"]*)"\s*\)/',
            'replacement' => 'mq_safe_db()->safe_get_results( "$1%s$3", [ $$$2 ] )',
            'description' => 'get_results with variable concatenation'
        ],
        
        // wpdb->get_row with concatenation
        [
            'pattern' => '/\$wpdb->get_row\s*\(\s*"([^"]+)"\s*\.\s*\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\.\s*"([^"]*)"\s*\)/',
            'replacement' => 'mq_safe_db()->safe_get_row( "$1%s$3", [ $$$2 ] )',
            'description' => 'get_row with variable concatenation'
        ],
        
        // wpdb->get_var with concatenation
        [
            'pattern' => '/\$wpdb->get_var\s*\(\s*"([^"]+)"\s*\.\s*\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\.\s*"([^"]*)"\s*\)/',
            'replacement' => 'mq_safe_db()->safe_get_var( "$1%s$3", [ $$$2 ] )',
            'description' => 'get_var with variable concatenation'
        ],
        
        // Multiple variable concatenations
        [
            'pattern' => '/\$wpdb->query\s*\(\s*"([^"]+)"\s*\.\s*\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\.\s*"([^"]+)"\s*\.\s*\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\.\s*"([^"]*)"\s*\)/',
            'replacement' => 'mq_safe_db()->safe_query( "$1%s$3%s$5", [ $$$2, $$$4 ] )',
            'description' => 'Query with multiple variable concatenations'
        ],
        
        // Array access in queries
        [
            'pattern' => '/\$wpdb->query\s*\(\s*"([^"]*)\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\[\'([^\']+)\'\]([^"]*)"\s*\)/',
            'replacement' => 'mq_safe_db()->safe_query( "$1%s$4", [ $$$2[\'$3\'] ] )',
            'description' => 'Query with array access'
        ]
    ];
    
    /**
     * @var array Statistics
     */
    private $stats = [
        'files_processed' => 0,
        'queries_found' => 0,
        'queries_migrated' => 0,
        'errors' => 0
    ];
    
    /**
     * @var bool Dry run mode
     */
    private $dry_run = false;
    
    /**
     * Run the migration
     */
    public function run( $args ) {
        $this->parse_args( $args );
        
        echo "Money Quiz Database Query Migration Tool\n";
        echo "=======================================\n\n";
        
        if ( $this->dry_run ) {
            echo "Running in DRY RUN mode - no files will be modified\n\n";
        }
        
        // Get files to process
        $files = $this->get_files_to_process( $args );
        
        if ( empty( $files ) ) {
            echo "No PHP files found to process.\n";
            return;
        }
        
        echo "Found " . count( $files ) . " files to process\n\n";
        
        // Process each file
        foreach ( $files as $file ) {
            $this->process_file( $file );
        }
        
        // Show summary
        $this->show_summary();
    }
    
    /**
     * Parse command line arguments
     */
    private function parse_args( $args ) {
        foreach ( $args as $arg ) {
            if ( $arg === '--dry-run' ) {
                $this->dry_run = true;
            }
        }
    }
    
    /**
     * Get files to process
     */
    private function get_files_to_process( $args ) {
        $files = [];
        
        // Check for specific file
        foreach ( $args as $arg ) {
            if ( strpos( $arg, '--file=' ) === 0 ) {
                $file = substr( $arg, 7 );
                if ( file_exists( $file ) ) {
                    return [ $file ];
                } else {
                    echo "Error: File not found: $file\n";
                    exit( 1 );
                }
            }
        }
        
        // Default to all PHP files in plugin directory
        $plugin_dir = dirname( __DIR__ );
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $plugin_dir )
        );
        
        foreach ( $iterator as $file ) {
            if ( $file->isFile() && $file->getExtension() === 'php' ) {
                $path = $file->getPathname();
                
                // Skip vendor and test directories
                if ( strpos( $path, '/vendor/' ) !== false ||
                     strpos( $path, '/tests/' ) !== false ||
                     strpos( $path, '/tools/' ) !== false ) {
                    continue;
                }
                
                $files[] = $path;
            }
        }
        
        return $files;
    }
    
    /**
     * Process a single file
     */
    private function process_file( $file ) {
        $this->stats['files_processed']++;
        
        echo "Processing: " . basename( $file ) . "... ";
        
        $content = file_get_contents( $file );
        $original_content = $content;
        $changes = [];
        
        // Apply each pattern
        foreach ( $this->patterns as $pattern_info ) {
            $pattern = $pattern_info['pattern'];
            $replacement = $pattern_info['replacement'];
            
            if ( preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
                $this->stats['queries_found'] += count( $matches[0] );
                
                foreach ( $matches[0] as $match ) {
                    $changes[] = [
                        'line' => $this->get_line_number( $original_content, $match[1] ),
                        'original' => $match[0],
                        'replacement' => preg_replace( $pattern, $replacement, $match[0] ),
                        'description' => $pattern_info['description']
                    ];
                }
                
                // Replace in content
                $content = preg_replace( $pattern, $replacement, $content );
                $this->stats['queries_migrated'] += count( $matches[0] );
            }
        }
        
        // Check for any remaining unsafe queries
        $unsafe_patterns = [
            '/\$wpdb->query\s*\([^)]*\$[^)]+\)/',
            '/\$wpdb->get_results\s*\([^)]*\$[^)]+\)/',
            '/\$wpdb->get_row\s*\([^)]*\$[^)]+\)/',
            '/\$wpdb->get_var\s*\([^)]*\$[^)]+\)/'
        ];
        
        $remaining_unsafe = 0;
        foreach ( $unsafe_patterns as $pattern ) {
            if ( preg_match_all( $pattern, $content, $matches ) ) {
                $remaining_unsafe += count( $matches[0] );
            }
        }
        
        if ( ! empty( $changes ) ) {
            echo "Found " . count( $changes ) . " queries to migrate\n";
            
            // Show changes
            foreach ( $changes as $change ) {
                echo "  Line {$change['line']}: {$change['description']}\n";
                if ( $this->dry_run ) {
                    echo "    Original:     " . trim( $change['original'] ) . "\n";
                    echo "    Replacement:  " . trim( $change['replacement'] ) . "\n";
                }
            }
            
            // Save file if not dry run
            if ( ! $this->dry_run ) {
                // Create backup
                $backup_file = $file . '.bak.' . date( 'YmdHis' );
                copy( $file, $backup_file );
                
                // Save modified content
                if ( file_put_contents( $file, $content ) !== false ) {
                    echo "  ✓ File updated (backup: " . basename( $backup_file ) . ")\n";
                } else {
                    echo "  ✗ Error saving file\n";
                    $this->stats['errors']++;
                }
            }
            
            if ( $remaining_unsafe > 0 ) {
                echo "  ⚠ Warning: {$remaining_unsafe} potentially unsafe queries remain that need manual review\n";
            }
        } else {
            echo "No unsafe queries found\n";
        }
        
        echo "\n";
    }
    
    /**
     * Get line number for offset
     */
    private function get_line_number( $content, $offset ) {
        return substr_count( substr( $content, 0, $offset ), "\n" ) + 1;
    }
    
    /**
     * Show summary
     */
    private function show_summary() {
        echo "\nMigration Summary\n";
        echo "=================\n";
        echo "Files processed:    {$this->stats['files_processed']}\n";
        echo "Queries found:      {$this->stats['queries_found']}\n";
        echo "Queries migrated:   {$this->stats['queries_migrated']}\n";
        echo "Errors:             {$this->stats['errors']}\n";
        
        if ( $this->dry_run ) {
            echo "\nThis was a DRY RUN - no files were modified.\n";
            echo "Run without --dry-run to apply changes.\n";
        }
        
        if ( $this->stats['queries_migrated'] > 0 ) {
            echo "\nNext steps:\n";
            echo "1. Review the changes in your version control system\n";
            echo "2. Test the modified queries thoroughly\n";
            echo "3. Look for any queries that need manual migration\n";
            echo "4. Run the test suite to ensure everything works\n";
        }
    }
}

// Run the migrator
$migrator = new Database_Query_Migrator();
$migrator->run( array_slice( $argv, 1 ) );