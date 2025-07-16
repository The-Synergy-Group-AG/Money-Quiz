<?php
/**
 * File Upload Validation
 * 
 * Secure file upload handling for the Money Quiz plugin
 * 
 * @package MoneyQuiz\Security\Validation
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Validation;

class FileUploadValidation {
    
    /**
     * Allowed MIME types for different file categories
     */
    private static $allowed_types = [
        'image' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml'
        ],
        'document' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ],
        'spreadsheet' => [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv'
        ]
    ];
    
    /**
     * Maximum file sizes (in bytes)
     */
    private static $max_sizes = [
        'image' => 5242880,      // 5MB
        'document' => 10485760,  // 10MB
        'spreadsheet' => 5242880, // 5MB
        'default' => 2097152     // 2MB
    ];
    
    /**
     * Validate uploaded file
     */
    public static function validate_upload($file, $type = 'image', $options = []) {
        $options = wp_parse_args($options, [
            'max_size' => null,
            'allowed_types' => null,
            'check_image_dimensions' => true,
            'max_width' => 4096,
            'max_height' => 4096,
            'scan_for_malware' => true
        ]);
        
        // Check if file upload succeeded
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', self::get_upload_error_message($file['error']));
        }
        
        // Validate file size
        $max_size = $options['max_size'] ?? self::$max_sizes[$type] ?? self::$max_sizes['default'];
        if ($file['size'] > $max_size) {
            return new \WP_Error('file_too_large', sprintf(
                'File size exceeds maximum allowed size of %s',
                size_format($max_size)
            ));
        }
        
        // Validate MIME type
        $allowed_types = $options['allowed_types'] ?? self::$allowed_types[$type] ?? [];
        $file_type = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        
        if (!in_array($file_type['type'], $allowed_types)) {
            return new \WP_Error('invalid_file_type', 'File type not allowed');
        }
        
        // Additional security checks
        if ($options['scan_for_malware']) {
            $malware_check = self::scan_for_malware($file['tmp_name']);
            if (is_wp_error($malware_check)) {
                return $malware_check;
            }
        }
        
        // Image-specific validations
        if ($type === 'image' && $options['check_image_dimensions']) {
            $image_check = self::validate_image_dimensions($file['tmp_name'], $options);
            if (is_wp_error($image_check)) {
                return $image_check;
            }
        }
        
        return true;
    }
    
    /**
     * Scan file for potential malware
     */
    private static function scan_for_malware($file_path) {
        // Check for PHP code in uploaded files
        $content = file_get_contents($file_path);
        
        // Common PHP patterns
        $dangerous_patterns = [
            '/<\?php/i',
            '/<\?=/i',
            '/<script/i',
            '/eval\s*\(/i',
            '/base64_decode/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/passthru\s*\(/i',
            '/shell_exec\s*\(/i',
            '/\$_POST\[/i',
            '/\$_GET\[/i',
            '/\$_REQUEST\[/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return new \WP_Error('malware_detected', 'Potentially malicious content detected');
            }
        }
        
        // Check for embedded executables
        $exe_signatures = [
            "\x4D\x5A", // PE/COFF
            "\x7F\x45\x4C\x46", // ELF
            "\xFE\xED\xFA\xCE", // Mach-O
            "\xFE\xED\xFA\xCF", // Mach-O
            "\xCE\xFA\xED\xFE", // Mach-O
            "\xCF\xFA\xED\xFE"  // Mach-O
        ];
        
        foreach ($exe_signatures as $signature) {
            if (strpos($content, $signature) !== false) {
                return new \WP_Error('executable_detected', 'Executable content not allowed');
            }
        }
        
        return true;
    }
    
    /**
     * Validate image dimensions
     */
    private static function validate_image_dimensions($file_path, $options) {
        $image_info = @getimagesize($file_path);
        
        if (!$image_info) {
            return new \WP_Error('invalid_image', 'Unable to read image dimensions');
        }
        
        list($width, $height) = $image_info;
        
        if ($width > $options['max_width'] || $height > $options['max_height']) {
            return new \WP_Error('image_too_large', sprintf(
                'Image dimensions exceed maximum allowed size of %dx%d pixels',
                $options['max_width'],
                $options['max_height']
            ));
        }
        
        return true;
    }
    
    /**
     * Get human-readable upload error message
     */
    private static function get_upload_error_message($error_code) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        
        return $messages[$error_code] ?? 'Unknown upload error';
    }
    
    /**
     * Sanitize filename
     */
    public static function sanitize_filename($filename) {
        // Remove any path information
        $filename = basename($filename);
        
        // WordPress sanitization
        $filename = sanitize_file_name($filename);
        
        // Additional sanitization
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Ensure proper extension
        $parts = explode('.', $filename);
        if (count($parts) > 1) {
            $extension = array_pop($parts);
            $name = implode('.', $parts);
            
            // Limit name length
            $name = substr($name, 0, 100);
            
            $filename = $name . '.' . $extension;
        }
        
        return $filename;
    }
}