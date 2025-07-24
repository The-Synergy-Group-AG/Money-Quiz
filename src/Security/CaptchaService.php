<?php
declare(strict_types=1);

namespace MoneyQuiz\Security;

use MoneyQuiz\Application\Exceptions\ServiceException;

/**
 * CAPTCHA service for preventing spam and bot submissions
 */
class CaptchaService
{
    private const OPTION_KEY = 'money_quiz_captcha_settings';
    
    private ?SecurityLogger $logger = null;
    
    public function __construct(
        private NonceManager $nonceManager
    ) {}
    
    /**
     * Set security logger
     */
    public function setLogger(SecurityLogger $logger): void
    {
        $this->logger = $logger;
    }
    
    /**
     * Check if CAPTCHA is required for the current context
     */
    public function isRequired(?int $userId = null): bool
    {
        // Logged-in users typically don't need CAPTCHA
        if ($userId && $userId > 0) {
            return false;
        }
        
        // Check if CAPTCHA is enabled for anonymous users
        $settings = get_option(self::OPTION_KEY, []);
        return !empty($settings['enabled_anonymous']);
    }
    
    /**
     * Render CAPTCHA field
     */
    public function renderField(): string
    {
        if (!$this->isConfigured()) {
            return $this->renderSimpleMathCaptcha();
        }
        
        // If using external service like reCAPTCHA
        $settings = get_option(self::OPTION_KEY, []);
        
        if ($settings['provider'] === 'recaptcha_v2') {
            return $this->renderRecaptchaV2($settings);
        }
        
        // Default to simple math CAPTCHA
        return $this->renderSimpleMathCaptcha();
    }
    
    /**
     * Verify CAPTCHA response
     */
    public function verify(array $data): bool
    {
        if (!$this->isRequired(get_current_user_id())) {
            return true;
        }
        
        if (!$this->isConfigured()) {
            return $this->verifySimpleMathCaptcha($data);
        }
        
        $settings = get_option(self::OPTION_KEY, []);
        
        if ($settings['provider'] === 'recaptcha_v2') {
            return $this->verifyRecaptchaV2($data, $settings);
        }
        
        return $this->verifySimpleMathCaptcha($data);
    }
    
    /**
     * Check if CAPTCHA is properly configured
     */
    private function isConfigured(): bool
    {
        $settings = get_option(self::OPTION_KEY, []);
        return !empty($settings['provider']) && !empty($settings['site_key']);
    }
    
    /**
     * Render simple math CAPTCHA
     */
    private function renderSimpleMathCaptcha(): string
    {
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $answer = $num1 + $num2;
        
        // Store answer in transient with unique key
        $key = wp_generate_password(12, false);
        set_transient('mq_captcha_' . $key, $answer, 300); // 5 minutes
        
        return sprintf(
            '<div class="mq-captcha-field">
                <label for="mq_captcha">%s</label>
                <input type="text" id="mq_captcha" name="mq_captcha_answer" required>
                <input type="hidden" name="mq_captcha_key" value="%s">
                <input type="hidden" name="mq_captcha_nonce" value="%s">
            </div>',
            sprintf(__('What is %d + %d?', 'money-quiz'), $num1, $num2),
            esc_attr($key),
            wp_create_nonce('mq_captcha_verify')
        );
    }
    
    /**
     * Verify simple math CAPTCHA
     */
    private function verifySimpleMathCaptcha(array $data): bool
    {
        if (empty($data['mq_captcha_answer']) || empty($data['mq_captcha_key']) || empty($data['mq_captcha_nonce'])) {
            if ($this->logger) {
                $this->logger->logCaptchaFailure('Missing data', ['data_keys' => array_keys($data)]);
            }
            throw new ServiceException('CAPTCHA verification failed: Missing data');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($data['mq_captcha_nonce'], 'mq_captcha_verify')) {
            if ($this->logger) {
                $this->logger->logCaptchaFailure('Invalid nonce');
            }
            throw new ServiceException('CAPTCHA verification failed: Invalid nonce');
        }
        
        $key = sanitize_text_field($data['mq_captcha_key']);
        $userAnswer = (int) $data['mq_captcha_answer'];
        
        $correctAnswer = get_transient('mq_captcha_' . $key);
        
        if ($correctAnswer === false) {
            if ($this->logger) {
                $this->logger->logCaptchaFailure('Expired CAPTCHA', ['key' => $key]);
            }
            throw new ServiceException('CAPTCHA expired. Please try again.');
        }
        
        // Delete transient to prevent reuse
        delete_transient('mq_captcha_' . $key);
        
        if ($userAnswer !== (int) $correctAnswer) {
            if ($this->logger) {
                $this->logger->logCaptchaFailure('Incorrect answer', [
                    'provided' => $userAnswer,
                    'expected' => '***' // Don't log the actual answer
                ]);
            }
            throw new ServiceException('Incorrect CAPTCHA answer. Please try again.');
        }
        
        return true;
    }
    
    /**
     * Render reCAPTCHA v2
     */
    private function renderRecaptchaV2(array $settings): string
    {
        wp_enqueue_script(
            'google-recaptcha',
            'https://www.google.com/recaptcha/api.js',
            [],
            null,
            true
        );
        
        return sprintf(
            '<div class="g-recaptcha" data-sitekey="%s"></div>',
            esc_attr($settings['site_key'])
        );
    }
    
    /**
     * Verify reCAPTCHA v2
     */
    private function verifyRecaptchaV2(array $data, array $settings): bool
    {
        if (empty($data['g-recaptcha-response'])) {
            if ($this->logger) {
                $this->logger->logCaptchaFailure('Missing reCAPTCHA response');
            }
            throw new ServiceException('Please complete the CAPTCHA');
        }
        
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $settings['secret_key'],
                'response' => $data['g-recaptcha-response'],
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]
        ]);
        
        if (is_wp_error($response)) {
            if ($this->logger) {
                $this->logger->logCaptchaFailure('reCAPTCHA API error', [
                    'error' => $response->get_error_message()
                ]);
            }
            throw new ServiceException('CAPTCHA verification failed');
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (empty($result['success'])) {
            if ($this->logger) {
                $this->logger->logCaptchaFailure('reCAPTCHA verification failed', [
                    'error_codes' => $result['error-codes'] ?? []
                ]);
            }
            throw new ServiceException('CAPTCHA verification failed');
        }
        
        return true;
    }
    
    /**
     * Clean up expired CAPTCHA transients
     */
    public function cleanupExpiredTransients(): int
    {
        global $wpdb;
        
        // WordPress transients that have expired are automatically cleaned up
        // But we can force cleanup of our specific CAPTCHA transients
        $prefix = '_transient_mq_captcha_';
        $timeout_prefix = '_transient_timeout_mq_captcha_';
        
        // Delete expired transients
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE t, to
                FROM {$wpdb->options} t
                LEFT JOIN {$wpdb->options} to ON to.option_name = CONCAT('_transient_timeout_', SUBSTRING(t.option_name, 12))
                WHERE t.option_name LIKE %s
                AND (
                    to.option_value < %d
                    OR to.option_value IS NULL
                )",
                $wpdb->esc_like($prefix) . '%',
                time()
            )
        );
        
        // Also clean up orphaned timeout options
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s
                AND option_name NOT IN (
                    SELECT CONCAT('_transient_timeout_', SUBSTRING(option_name, 12))
                    FROM (SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s) AS t
                )",
                $wpdb->esc_like($timeout_prefix) . '%',
                $wpdb->esc_like($prefix) . '%'
            )
        );
        
        return $deleted;
    }
    
    /**
     * Schedule cleanup of expired transients
     */
    public static function scheduleCleanup(): void
    {
        if (!wp_next_scheduled('mq_captcha_cleanup')) {
            wp_schedule_event(time(), 'daily', 'mq_captcha_cleanup');
        }
        
        add_action('mq_captcha_cleanup', [__CLASS__, 'performScheduledCleanup']);
    }
    
    /**
     * Perform scheduled cleanup
     */
    public static function performScheduledCleanup(): void
    {
        $captcha = new self(new NonceManager());
        $deleted = $captcha->cleanupExpiredTransients();
        
        if ($deleted > 0) {
            error_log(sprintf('[Money Quiz] Cleaned up %d expired CAPTCHA transients', $deleted));
        }
    }
}