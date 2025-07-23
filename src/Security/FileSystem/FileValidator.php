<?php
/**
 * File Validator
 *
 * Validates uploaded files for security.
 *
 * @package MoneyQuiz\Security\FileSystem
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\FileSystem;

use MoneyQuiz\Core\Logging\Logger;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * File validator class.
 *
 * @since 7.0.0
 */
class FileValidator {
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Allowed file extensions.
     *
     * @var array
     */
    private array $allowed_extensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',  // Images
        'pdf', 'doc', 'docx',                  // Documents
        'csv', 'xlsx', 'xls',                  // Spreadsheets
        'txt', 'md',                           // Text
        'zip'                                  // Archives
    ];
    
    /**
     * Maximum file sizes by type (in bytes).
     *
     * @var array
     */
    private array $max_sizes = [
        'image' => 5 * 1024 * 1024,      // 5MB
        'document' => 10 * 1024 * 1024,   // 10MB
        'spreadsheet' => 10 * 1024 * 1024, // 10MB
        'archive' => 20 * 1024 * 1024,    // 20MB
        'default' => 5 * 1024 * 1024      // 5MB
    ];
    
    /**
     * Dangerous patterns in filenames.
     *
     * @var array
     */
    private array $dangerous_patterns = [
        '/\.\./', // Directory traversal
        '/[<>:"|?*]/', // Invalid characters
        '/\.(php|phtml|php\d|phps|phar|exe|sh|bat|cmd|com)$/i', // Executable extensions
        '/^\./', // Hidden files
        '/\x00/', // Null bytes
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
     * Validate uploaded file.
     *
     * Performs comprehensive security validation on uploaded files:
     * 1. Checks upload errors from PHP
     * 2. Validates filename for dangerous patterns
     * 3. Enforces size limits by file type
     * 4. Verifies MIME type matches extension
     * 5. Scans content for malicious code
     *
     * @since 7.0.0
     *
     * @param array $file $_FILES array element containing:
     *                    - 'name': Original filename
     *                    - 'type': MIME type from browser (untrusted)
     *                    - 'tmp_name': Temporary file path
     *                    - 'error': PHP upload error code
     *                    - 'size': File size in bytes
     * 
     * @return ValidationResult Object containing validation status and any error message.
     * 
     * @example
     * ```php
     * $validator = new FileValidator($logger);
     * $result = $validator->validate($_FILES['upload']);
     * if (!$result->is_valid()) {
     *     wp_die($result->get_error());
     * }
     * ```
     */
    public function validate(array $file): ValidationResult {
        // Check upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new ValidationResult(false, $this->get_upload_error_message($file['error']));
        }
        
        // Validate filename
        $filename_result = $this->validate_filename($file['name']);
        if (!$filename_result->is_valid()) {
            return $filename_result;
        }
        
        // Validate size
        $size_result = $this->validate_size($file['size'], $file['name']);
        if (!$size_result->is_valid()) {
            return $size_result;
        }
        
        // Validate MIME type
        $mime_result = $this->validate_mime_type($file['tmp_name'], $file['name']);
        if (!$mime_result->is_valid()) {
            return $mime_result;
        }
        
        // Check for malicious content
        $content_result = $this->validate_content($file['tmp_name'], $file['type']);
        if (!$content_result->is_valid()) {
            return $content_result;
        }
        
        $this->logger->info('File validation passed', [
            'filename' => $file['name'],
            'size' => $file['size'],
            'type' => $file['type']
        ]);
        
        return new ValidationResult(true);
    }
    
    /**
     * Validate filename.
     *
     * Checks filename for security threats including:
     * - Directory traversal attempts (../)
     * - Invalid/dangerous characters
     * - Executable file extensions
     * - Hidden files (starting with .)
     * - Null byte injection
     *
     * @since 7.0.0
     * @access private
     *
     * @param string $filename The filename to validate.
     * 
     * @return ValidationResult Validation result with error if dangerous.
     */
    private function validate_filename(string $filename): ValidationResult {
        // Check for dangerous patterns
        // Each pattern targets specific security threats
        foreach ($this->dangerous_patterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                $this->logger->warning('Dangerous filename pattern detected', [
                    'filename' => $filename,
                    'pattern' => $pattern
                ]);
                return new ValidationResult(false, __('Invalid filename.', 'money-quiz'));
            }
        }
        
        // Check extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_extensions, true)) {
            $this->logger->warning('Disallowed file extension', [
                'filename' => $filename,
                'extension' => $extension
            ]);
            return new ValidationResult(
                false,
                sprintf(__('File type .%s is not allowed.', 'money-quiz'), $extension)
            );
        }
        
        // Check filename length
        if (strlen($filename) > 255) {
            return new ValidationResult(false, __('Filename too long.', 'money-quiz'));
        }
        
        return new ValidationResult(true);
    }
    
    /**
     * Validate file size.
     *
     * @param int    $size     File size in bytes.
     * @param string $filename Filename for type detection.
     * @return ValidationResult Validation result.
     */
    private function validate_size(int $size, string $filename): ValidationResult {
        $type = $this->get_file_type($filename);
        $max_size = $this->max_sizes[$type] ?? $this->max_sizes['default'];
        
        if ($size > $max_size) {
            return new ValidationResult(
                false,
                sprintf(
                    __('File size exceeds maximum allowed size of %s.', 'money-quiz'),
                    size_format($max_size)
                )
            );
        }
        
        if ($size === 0) {
            return new ValidationResult(false, __('File is empty.', 'money-quiz'));
        }
        
        return new ValidationResult(true);
    }
    
    /**
     * Validate MIME type.
     *
     * @param string $tmp_name Temporary file path.
     * @param string $filename Original filename.
     * @return ValidationResult Validation result.
     */
    private function validate_mime_type(string $tmp_name, string $filename): ValidationResult {
        $mime_checker = new MimeTypeChecker($this->logger);
        return $mime_checker->validate($tmp_name, $filename);
    }
    
    /**
     * Validate file content.
     *
     * Performs deep content inspection to detect:
     * 1. PHP code injection (<?php, <?=)
     * 2. Dangerous function calls (eval, exec, system)
     * 3. Obfuscation techniques (base64_decode)
     * 4. Shell command execution attempts
     * 
     * This prevents malicious code execution even if other checks pass.
     *
     * @since 7.0.0
     * @access private
     *
     * @param string $tmp_name  Path to temporary uploaded file.
     * @param string $mime_type MIME type for logging purposes.
     * 
     * @return ValidationResult Result with specific error if malicious content found.
     */
    private function validate_content(string $tmp_name, string $mime_type): ValidationResult {
        // Read entire file content for scanning
        // This is safe as file size was already validated
        $content = file_get_contents($tmp_name);
        if ($content === false) {
            return new ValidationResult(false, __('Could not read file.', 'money-quiz'));
        }
        
        // Check for PHP tags
        // Both <?php and <?= short tags are blocked
        if (preg_match('/<\?php|<\?=/i', $content)) {
            $this->logger->error('PHP code detected in upload', [
                'mime_type' => $mime_type
            ]);
            return new ValidationResult(false, __('File contains prohibited content.', 'money-quiz'));
        }
        
        // Check for suspicious function patterns
        // These functions are commonly used in web shells and exploits
        $suspicious_patterns = [
            '/eval\s*\(/i',          // Code evaluation
            '/base64_decode\s*\(/i', // Often used for obfuscation
            '/system\s*\(/i',        // System command execution
            '/exec\s*\(/i',          // Command execution
            '/passthru\s*\(/i',      // Command execution with output
            '/shell_exec\s*\(/i'     // Shell command execution
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->logger->error('Suspicious pattern in upload', [
                    'pattern' => $pattern
                ]);
                return new ValidationResult(false, __('File contains suspicious content.', 'money-quiz'));
            }
        }
        
        return new ValidationResult(true);
    }
    
    /**
     * Get file type category.
     *
     * @param string $filename Filename.
     * @return string File type category.
     */
    private function get_file_type(string $filename): string {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $types = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'document' => ['pdf', 'doc', 'docx', 'txt', 'md'],
            'spreadsheet' => ['csv', 'xlsx', 'xls'],
            'archive' => ['zip']
        ];
        
        foreach ($types as $type => $extensions) {
            if (in_array($extension, $extensions, true)) {
                return $type;
            }
        }
        
        return 'default';
    }
    
    /**
     * Get upload error message.
     *
     * @param int $error_code Upload error code.
     * @return string Error message.
     */
    private function get_upload_error_message(int $error_code): string {
        $messages = [
            UPLOAD_ERR_INI_SIZE => __('File exceeds server size limit.', 'money-quiz'),
            UPLOAD_ERR_FORM_SIZE => __('File exceeds form size limit.', 'money-quiz'),
            UPLOAD_ERR_PARTIAL => __('File was only partially uploaded.', 'money-quiz'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'money-quiz'),
            UPLOAD_ERR_NO_TMP_DIR => __('Missing temporary folder.', 'money-quiz'),
            UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk.', 'money-quiz'),
            UPLOAD_ERR_EXTENSION => __('Upload stopped by extension.', 'money-quiz')
        ];
        
        return $messages[$error_code] ?? __('Unknown upload error.', 'money-quiz');
    }
    
    /**
     * Add allowed extension.
     *
     * @param string $extension Extension to allow.
     */
    public function add_allowed_extension(string $extension): void {
        $this->allowed_extensions[] = strtolower($extension);
    }
    
    /**
     * Set max size for file type.
     *
     * @param string $type Type category.
     * @param int    $size Size in bytes.
     */
    public function set_max_size(string $type, int $size): void {
        $this->max_sizes[$type] = $size;
    }
}

/**
 * File validation result.
 *
 * Encapsulates the result of file validation with a boolean status
 * and optional error message for failed validations.
 *
 * @since 7.0.0
 */
class ValidationResult {
    
    /**
     * Validation status.
     *
     * @var bool
     */
    private bool $valid;
    
    /**
     * Error message.
     *
     * @var string
     */
    private string $error;
    
    /**
     * Constructor.
     *
     * @param bool   $valid Valid status.
     * @param string $error Error message.
     */
    public function __construct(bool $valid, string $error = '') {
        $this->valid = $valid;
        $this->error = $error;
    }
    
    public function is_valid(): bool {
        return $this->valid;
    }
    
    public function get_error(): string {
        return $this->error;
    }
}