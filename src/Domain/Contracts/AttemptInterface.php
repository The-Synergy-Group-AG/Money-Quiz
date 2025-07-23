<?php
/**
 * Attempt Interface
 *
 * Contract for Attempt entity operations.
 *
 * @package MoneyQuiz\Domain\Contracts
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Contracts;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Attempt interface.
 *
 * @since 7.0.0
 */
interface AttemptInterface {
    
    /**
     * Get quiz ID.
     *
     * @return int Quiz ID.
     */
    public function get_quiz_id(): int;
    
    /**
     * Get user ID.
     *
     * @return int|null User ID or null.
     */
    public function get_user_id(): ?int;
    
    /**
     * Get user email.
     *
     * @return string|null User email or null.
     */
    public function get_user_email(): ?string;
    
    /**
     * Get status.
     *
     * @return string Status.
     */
    public function get_status(): string;
    
    /**
     * Get started timestamp.
     *
     * @return \DateTimeInterface Started time.
     */
    public function get_started_at(): \DateTimeInterface;
    
    /**
     * Get completed timestamp.
     *
     * @return \DateTimeInterface|null Completed time or null.
     */
    public function get_completed_at(): ?\DateTimeInterface;
    
    /**
     * Get answers.
     *
     * @return array Answers.
     */
    public function get_answers(): array;
    
    /**
     * Get questions.
     *
     * @return array Questions.
     */
    public function get_questions(): array;
    
    /**
     * Get time taken.
     *
     * @return int|null Time in seconds or null.
     */
    public function get_time_taken(): ?int;
    
    /**
     * Get result ID.
     *
     * @return int|null Result ID or null.
     */
    public function get_result_id(): ?int;
    
    /**
     * Get session token.
     *
     * @return string|null Session token or null.
     */
    public function get_session_token(): ?string;
    
    /**
     * Get metadata.
     *
     * @return array Metadata.
     */
    public function get_metadata(): array;
    
    /**
     * Check if attempt is completed.
     *
     * @return bool True if completed.
     */
    public function is_completed(): bool;
    
    /**
     * Check if attempt is active.
     *
     * @return bool True if active.
     */
    public function is_active(): bool;
    
    /**
     * Submit answers.
     *
     * @param array $answers Array of answers.
     * @return void
     */
    public function submit_answers(array $answers): void;
    
    /**
     * Complete the attempt.
     *
     * @param int|null $result_id Result ID if calculated.
     * @return void
     */
    public function complete(?int $result_id = null): void;
    
    /**
     * Abandon the attempt.
     *
     * @return void
     */
    public function abandon(): void;
    
    /**
     * Validate entity.
     *
     * @throws \MoneyQuiz\Domain\Exceptions\EntityException If validation fails.
     * @return void
     */
    public function validate(): void;
}