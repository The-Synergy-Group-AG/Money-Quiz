<?php
/**
 * Attempt Methods Trait
 *
 * Provides methods for Attempt entity.
 *
 * @package MoneyQuiz\Domain\Traits
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Traits;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Trait for Attempt entity methods.
 *
 * @since 7.0.0
 */
trait AttemptMethods {
    
    /**
     * Valid statuses.
     *
     * @var array
     */
    private const VALID_STATUSES = ['started', 'in_progress', 'completed', 'abandoned'];
    
    /**
     * Get quiz ID.
     *
     * @return int Quiz ID.
     */
    public function get_quiz_id(): int {
        return $this->quiz_id;
    }
    
    /**
     * Get user ID.
     *
     * @return int|null User ID or null.
     */
    public function get_user_id(): ?int {
        return $this->user_id;
    }
    
    /**
     * Get user email.
     *
     * @return string|null User email or null.
     */
    public function get_user_email(): ?string {
        return $this->user_email;
    }
    
    /**
     * Get status.
     *
     * @return string Status.
     */
    public function get_status(): string {
        return $this->status;
    }
    
    /**
     * Get started timestamp.
     *
     * @return \DateTimeInterface Started time.
     */
    public function get_started_at(): \DateTimeInterface {
        return $this->started_at;
    }
    
    /**
     * Get completed timestamp.
     *
     * @return \DateTimeInterface|null Completed time or null.
     */
    public function get_completed_at(): ?\DateTimeInterface {
        return $this->completed_at;
    }
    
    /**
     * Get answers.
     *
     * @return array Answers.
     */
    public function get_answers(): array {
        return $this->answers;
    }
    
    /**
     * Get questions.
     *
     * @return array Questions.
     */
    public function get_questions(): array {
        return $this->questions;
    }
    
    /**
     * Get time taken.
     *
     * @return int|null Time in seconds or null.
     */
    public function get_time_taken(): ?int {
        return $this->time_taken;
    }
    
    /**
     * Get result ID.
     *
     * @return int|null Result ID or null.
     */
    public function get_result_id(): ?int {
        return $this->result_id;
    }
    
    /**
     * Get session token.
     *
     * @return string|null Session token or null.
     */
    public function get_session_token(): ?string {
        return $this->session_token;
    }
    
    /**
     * Get metadata.
     *
     * @return array Metadata.
     */
    public function get_metadata(): array {
        return $this->metadata;
    }
    
    /**
     * Check if attempt is completed.
     *
     * @return bool True if completed.
     */
    public function is_completed(): bool {
        return $this->status === 'completed';
    }
    
    /**
     * Check if attempt is active.
     *
     * @return bool True if active.
     */
    public function is_active(): bool {
        return in_array($this->status, ['started', 'in_progress']);
    }
    
    /**
     * Get client IP address.
     *
     * @return string|null IP address or null.
     */
    protected function get_client_ip(): ?string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
    
    /**
     * Submit answers.
     *
     * @param array $answers Array of answers.
     * @return void
     */
    public function submit_answers(array $answers): void {
        if (!in_array($this->status, ['started', 'in_progress'])) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Cannot submit answers to completed or abandoned attempt');
        }
        
        $this->answers = array_merge($this->answers, $answers);
        $this->status = 'in_progress';
        $this->update_timestamps();
    }
    
    /**
     * Complete the attempt.
     *
     * @param int|null $result_id Result ID if calculated.
     * @return void
     */
    public function complete(?int $result_id = null): void {
        if ($this->status === 'completed') {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Attempt already completed');
        }
        
        $this->status = 'completed';
        $this->completed_at = new \DateTimeImmutable('now', wp_timezone());
        $this->result_id = $result_id;
        
        // Calculate time taken
        $interval = $this->started_at->diff($this->completed_at);
        $this->time_taken = ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
        
        $this->update_timestamps();
        
        $this->record_event(new \MoneyQuiz\Domain\Events\AttemptCompleted($this));
    }
    
    /**
     * Abandon the attempt.
     *
     * @return void
     */
    public function abandon(): void {
        if ($this->status === 'completed') {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Cannot abandon completed attempt');
        }
        
        $this->status = 'abandoned';
        $this->update_timestamps();
    }
    
    /**
     * Validate entity.
     *
     * @throws \MoneyQuiz\Domain\Exceptions\EntityException If validation fails.
     * @return void
     */
    public function validate(): void {
        if ($this->quiz_id <= 0) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Invalid quiz ID');
        }
        
        if (!$this->user_id && !$this->user_email) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Either user ID or email is required');
        }
        
        if ($this->user_email && !is_email($this->user_email)) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Invalid email address');
        }
        
        if (!in_array($this->status, self::VALID_STATUSES)) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Invalid attempt status');
        }
        
        if (empty($this->questions)) {
            throw new \MoneyQuiz\Domain\Exceptions\EntityException('Attempt must have at least one question');
        }
    }
}