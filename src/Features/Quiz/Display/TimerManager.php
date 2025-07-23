<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Quiz\Display;

use MoneyQuiz\Domain\Entities\Attempt;

/**
 * Manages quiz timer functionality
 */
class TimerManager
{
    /**
     * Get timer data for display
     */
    public function getTimerData(Attempt $attempt, int $timeLimit): array
    {
        $startTime = strtotime($attempt->getStartedAt());
        $currentTime = time();
        $elapsedSeconds = $currentTime - $startTime;
        $timeLimitSeconds = $timeLimit * 60;
        $remainingSeconds = max(0, $timeLimitSeconds - $elapsedSeconds);
        
        return [
            'time_limit' => $timeLimit,
            'elapsed_seconds' => $elapsedSeconds,
            'remaining_seconds' => $remainingSeconds,
            'is_expired' => $remainingSeconds <= 0,
            'display' => $this->formatTime($remainingSeconds),
            'percentage' => $timeLimitSeconds > 0 
                ? round(($remainingSeconds / $timeLimitSeconds) * 100) 
                : 0
        ];
    }

    /**
     * Check if timer has expired
     */
    public function isExpired(Attempt $attempt, int $timeLimit): bool
    {
        if ($timeLimit <= 0) {
            return false;
        }
        
        $startTime = strtotime($attempt->getStartedAt());
        $currentTime = time();
        $elapsedMinutes = ($currentTime - $startTime) / 60;
        
        return $elapsedMinutes >= $timeLimit;
    }

    /**
     * Format time for display
     */
    private function formatTime(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0:00';
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        return sprintf('%d:%02d', $minutes, $remainingSeconds);
    }

    /**
     * Get warning threshold (when to show timer warning)
     */
    public function getWarningThreshold(int $timeLimit): int
    {
        // Warn when 20% of time remains or 5 minutes, whichever is less
        $twentyPercent = (int) ($timeLimit * 0.2);
        return min($twentyPercent, 5) * 60; // Convert to seconds
    }

    /**
     * Should show timer warning
     */
    public function shouldShowWarning(array $timerData): bool
    {
        if ($timerData['is_expired']) {
            return false;
        }
        
        $warningThreshold = $this->getWarningThreshold($timerData['time_limit']);
        return $timerData['remaining_seconds'] <= $warningThreshold;
    }
}