<?php
/**
 * MIME Type Checker
 *
 * Validates file MIME types for security.
 *
 * @package MoneyQuiz\Security\FileSystem
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\FileSystem;

use MoneyQuiz\Core\Logging\Logger;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * MIME type checker class.
 *
 * @since 7.0.0
 */
class MimeTypeChecker {
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Allowed MIME types by extension.
     *
     * @var array
     */
    private array $allowed_mime_types = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'xls' => ['application/vnd.ms-excel'],
        'txt' => ['text/plain'],
        'md' => ['text/plain', 'text/markdown'],
        'zip' => ['application/zip', 'application/x-zip-compressed']
    ];
    
    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }
    
    /**
     * Validate file MIME type.
     *
     * @param string $file_path File path.
     * @param string $filename  Original filename.
     * @return ValidationResult Validation result.
     */
    public function validate(string $file_path, string $filename): ValidationResult {
        // Get file extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Check if extension is allowed
        if (!isset($this->allowed_mime_types[$extension])) {
            return new ValidationResult(
                false,
                sprintf(__('File type .%s is not allowed.', 'money-quiz'), $extension)
            );
        }
        
        // Get actual MIME type
        $actual_mime = $this->get_mime_type($file_path);
        if (!$actual_mime) {
            return new ValidationResult(false, __('Could not determine file type.', 'money-quiz'));
        }
        
        // Check if MIME type matches extension
        $allowed_mimes = $this->allowed_mime_types[$extension];
        if (!in_array($actual_mime, $allowed_mimes, true)) {
            $this->logger->warning('MIME type mismatch', [
                'filename' => $filename,
                'extension' => $extension,
                'expected' => $allowed_mimes,
                'actual' => $actual_mime
            ]);
            
            return new ValidationResult(
                false,
                __('File type does not match file extension.', 'money-quiz')
            );
        }
        
        // Additional validation for specific types
        $type_result = $this->validate_specific_type($file_path, $extension, $actual_mime);
        if (!$type_result->is_valid()) {
            return $type_result;
        }
        
        return new ValidationResult(true);
    }
    
    /**
     * Get file MIME type.
     *
     * @param string $file_path File path.
     * @return string|false MIME type or false.
     */
    private function get_mime_type(string $file_path) {
        // Try multiple methods for reliability
        
        // Method 1: FileInfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file_path);
            finfo_close($finfo);
            
            if ($mime !== false) {
                return $mime;
            }
        }
        
        // Method 2: mime_content_type
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($file_path);
            if ($mime !== false) {
                return $mime;
            }
        }
        
        // Method 3: Use WordPress function
        $mime = wp_check_filetype($file_path);
        if ($mime['type']) {
            return $mime['type'];
        }
        
        return false;
    }
    
    /**
     * Validate specific file types.
     *
     * @param string $file_path File path.
     * @param string $extension File extension.
     * @param string $mime_type MIME type.
     * @return ValidationResult Validation result.
     */
    private function validate_specific_type(string $file_path, string $extension, string $mime_type): ValidationResult {
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'webp':
                return $this->validate_image($file_path);
                
            case 'pdf':
                return $this->validate_pdf($file_path);
                
            case 'zip':
                return $this->validate_zip($file_path);
                
            default:
                return new ValidationResult(true);
        }
    }
    
    /**
     * Validate image file.
     *
     * @param string $file_path File path.
     * @return ValidationResult Validation result.
     */
    private function validate_image(string $file_path): ValidationResult {
        // Check if it's a valid image
        $image_info = @getimagesize($file_path);
        if ($image_info === false) {
            return new ValidationResult(false, __('Invalid image file.', 'money-quiz'));
        }
        
        // Check dimensions
        $max_width = 5000;
        $max_height = 5000;
        
        if ($image_info[0] > $max_width || $image_info[1] > $max_height) {
            return new ValidationResult(
                false,
                sprintf(
                    __('Image dimensions exceed maximum of %dx%d pixels.', 'money-quiz'),
                    $max_width,
                    $max_height
                )
            );
        }
        
        return new ValidationResult(true);
    }
    
    /**
     * Validate PDF file.
     *
     * @param string $file_path File path.
     * @return ValidationResult Validation result.
     */
    private function validate_pdf(string $file_path): ValidationResult {
        // Check PDF header
        $handle = fopen($file_path, 'rb');
        if (!$handle) {
            return new ValidationResult(false, __('Could not read PDF file.', 'money-quiz'));
        }
        
        $header = fread($handle, 5);
        fclose($handle);
        
        if ($header !== '%PDF-') {
            return new ValidationResult(false, __('Invalid PDF file.', 'money-quiz'));
        }
        
        // Check for embedded JavaScript (basic check)
        $content = file_get_contents($file_path);
        if (preg_match('/\/JavaScript|\/JS\s*\<\</i', $content)) {
            $this->logger->warning('JavaScript detected in PDF', [
                'file' => basename($file_path)
            ]);
            return new ValidationResult(false, __('PDF contains embedded scripts.', 'money-quiz'));
        }
        
        return new ValidationResult(true);
    }
    
    /**
     * Validate ZIP file.
     *
     * @param string $file_path File path.
     * @return ValidationResult Validation result.
     */
    private function validate_zip(string $file_path): ValidationResult {
        if (!class_exists('ZipArchive')) {
            return new ValidationResult(true); // Can't validate, allow
        }
        
        $zip = new \ZipArchive();
        if ($zip->open($file_path) !== true) {
            return new ValidationResult(false, __('Invalid ZIP file.', 'money-quiz'));
        }
        
        // Check for dangerous files in archive
        $dangerous_extensions = ['php', 'phtml', 'exe', 'sh', 'bat'];
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($extension, $dangerous_extensions, true)) {
                $zip->close();
                return new ValidationResult(
                    false,
                    sprintf(__('ZIP contains prohibited file type: .%s', 'money-quiz'), $extension)
                );
            }
            
            // Check for path traversal
            if (strpos($filename, '..') !== false) {
                $zip->close();
                return new ValidationResult(false, __('ZIP contains invalid paths.', 'money-quiz'));
            }
        }
        
        $zip->close();
        return new ValidationResult(true);
    }
}