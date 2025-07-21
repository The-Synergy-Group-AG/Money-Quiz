<?php
/**
 * Prediction Models
 * 
 * @package MoneyQuiz\AI\Analytics
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Analytics;

/**
 * Score Prediction Model
 */
class ScorePredictionModel extends PredictionModel {
    
    public function predict($features) {
        $this->loadWeights();
        
        $predicted_score = 0;
        
        // Linear combination of features
        foreach ($this->weights as $feature => $weight) {
            if (isset($features[$feature])) {
                $predicted_score += $features[$feature] * $weight;
            }
        }
        
        // Add baseline
        $predicted_score += 50; // Baseline score
        
        // Ensure score is within valid range
        return max(0, min(100, $predicted_score));
    }
    
    protected function getWeightKey() {
        return 'money_quiz_ml_score_weights';
    }
    
    protected function getDefaultWeights() {
        return [
            'avg_score' => 0.6,
            'score_std' => -0.1,
            'total_quizzes' => 0.05,
            'user_experience_level' => 0.15,
            'quiz_difficulty_rating' => -0.3,
            'avg_time' => -0.05
        ];
    }
}

/**
 * Dropout Risk Model
 */
class DropoutRiskModel extends PredictionModel {
    
    public function predict($features) {
        $this->loadWeights();
        
        $risk_score = 0;
        
        // Calculate risk factors
        foreach ($this->weights as $feature => $weight) {
            if (isset($features[$feature])) {
                $risk_score += $features[$feature] * $weight;
            }
        }
        
        // Sigmoid activation for probability
        $probability = 1 / (1 + exp(-$risk_score));
        
        return [
            'risk_level' => $this->categorizeRisk($probability),
            'probability' => $probability,
            'factors' => $this->identifyRiskFactors($features)
        ];
    }
    
    private function categorizeRisk($probability) {
        if ($probability >= 0.8) return 'critical';
        if ($probability >= 0.6) return 'high';
        if ($probability >= 0.4) return 'medium';
        if ($probability >= 0.2) return 'low';
        return 'minimal';
    }
    
    private function identifyRiskFactors($features) {
        $factors = [];
        
        if ($features['days_active'] < 3) {
            $factors[] = 'Low activity in past month';
        }
        
        if ($features['consistency_score'] < 0.3) {
            $factors[] = 'Inconsistent engagement pattern';
        }
        
        if ($features['trend'] < -0.3) {
            $factors[] = 'Declining activity trend';
        }
        
        if ($features['last_7_days_activity'] < 0.2) {
            $factors[] = 'Minimal recent activity';
        }
        
        return $factors;
    }
    
    protected function getWeightKey() {
        return 'money_quiz_ml_dropout_weights';
    }
    
    protected function getDefaultWeights() {
        return [
            'days_active' => -0.4,
            'avg_daily_quizzes' => -0.3,
            'consistency_score' => -0.5,
            'trend' => -0.6,
            'last_7_days_activity' => -0.8
        ];
    }
}

/**
 * Engagement Forecast Model
 */
class EngagementForecastModel extends PredictionModel {
    
    public function predict($features) {
        // Not used for this model
        return null;
    }
    
    public function forecast($historical_data, $days) {
        if (empty($historical_data)) {
            return $this->getEmptyForecast($days);
        }
        
        // Extract time series data
        $time_series = $this->extractTimeSeries($historical_data);
        
        // Apply smoothing
        $smoothed = $this->exponentialSmoothing($time_series);
        
        // Generate forecast
        $forecast = $this->generateForecast($smoothed, $days);
        
        return [
            'forecast' => $forecast,
            'confidence_interval' => $this->calculateConfidenceInterval($forecast),
            'trend_direction' => $this->determineTrend($smoothed),
            'seasonality' => $this->detectSeasonality($time_series)
        ];
    }
    
    private function extractTimeSeries($data) {
        $series = [];
        
        foreach ($data as $day) {
            $series[] = [
                'date' => $day['date'],
                'value' => $day['quizzes']
            ];
        }
        
        return $series;
    }
    
    private function exponentialSmoothing($series, $alpha = 0.3) {
        if (empty($series)) return [];
        
        $smoothed = [$series[0]['value']];
        
        for ($i = 1; $i < count($series); $i++) {
            $smoothed[] = $alpha * $series[$i]['value'] + (1 - $alpha) * $smoothed[$i - 1];
        }
        
        return $smoothed;
    }
    
    private function generateForecast($smoothed, $days) {
        if (empty($smoothed)) {
            return array_fill(0, $days, 0);
        }
        
        // Simple linear extrapolation
        $n = count($smoothed);
        $recent = array_slice($smoothed, -min(7, $n));
        
        // Calculate trend
        $trend = $n > 1 ? ($smoothed[$n - 1] - $smoothed[0]) / $n : 0;
        
        $forecast = [];
        $last_value = end($smoothed);
        
        for ($i = 1; $i <= $days; $i++) {
            $value = $last_value + ($trend * $i);
            $forecast[] = max(0, $value); // Ensure non-negative
        }
        
        return $forecast;
    }
    
    private function calculateConfidenceInterval($forecast) {
        $mean = array_sum($forecast) / count($forecast);
        $variance = 0;
        
        foreach ($forecast as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($forecast);
        
        $std_dev = sqrt($variance);
        
        return [
            'lower' => array_map(function($v) use ($std_dev) {
                return max(0, $v - 1.96 * $std_dev);
            }, $forecast),
            'upper' => array_map(function($v) use ($std_dev) {
                return $v + 1.96 * $std_dev;
            }, $forecast)
        ];
    }
    
    private function determineTrend($smoothed) {
        if (count($smoothed) < 3) return 'insufficient_data';
        
        $first_third = array_slice($smoothed, 0, floor(count($smoothed) / 3));
        $last_third = array_slice($smoothed, -floor(count($smoothed) / 3));
        
        $first_avg = array_sum($first_third) / count($first_third);
        $last_avg = array_sum($last_third) / count($last_third);
        
        if ($last_avg > $first_avg * 1.1) return 'increasing';
        if ($last_avg < $first_avg * 0.9) return 'decreasing';
        return 'stable';
    }
    
    private function detectSeasonality($series) {
        if (count($series) < 14) return 'insufficient_data';
        
        // Simple weekly seasonality detection
        $day_averages = [];
        
        foreach ($series as $point) {
            $day_of_week = date('w', strtotime($point['date']));
            $day_averages[$day_of_week][] = $point['value'];
        }
        
        $weekly_pattern = [];
        foreach ($day_averages as $day => $values) {
            $weekly_pattern[$day] = array_sum($values) / count($values);
        }
        
        return [
            'type' => 'weekly',
            'pattern' => $weekly_pattern
        ];
    }
    
    private function getEmptyForecast($days) {
        return [
            'forecast' => array_fill(0, $days, 0),
            'confidence_interval' => [
                'lower' => array_fill(0, $days, 0),
                'upper' => array_fill(0, $days, 0)
            ],
            'trend_direction' => 'no_data',
            'seasonality' => 'no_data'
        ];
    }
    
    protected function getWeightKey() {
        return 'money_quiz_ml_engagement_weights';
    }
    
    protected function getDefaultWeights() {
        return []; // Not used for time series
    }
}

/**
 * Difficulty Adjustment Model
 */
class DifficultyAdjustmentModel extends PredictionModel {
    
    public function predict($features) {
        // Not used for this model
        return null;
    }
    
    public function suggest($user_id, $current_performance) {
        $optimal_difficulty = $this->calculateOptimalDifficulty($current_performance);
        
        return [
            'current_level' => $current_performance['difficulty_level'] ?? 'medium',
            'suggested_level' => $optimal_difficulty,
            'adjustment' => $this->getAdjustmentDirection($current_performance['difficulty_level'], $optimal_difficulty),
            'confidence' => $this->calculateConfidence($current_performance),
            'reasoning' => $this->explainAdjustment($current_performance, $optimal_difficulty)
        ];
    }
    
    private function calculateOptimalDifficulty($performance) {
        $avg_score = $performance['avg_score'] ?? 70;
        $completion_rate = $performance['completion_rate'] ?? 0.8;
        $time_efficiency = $performance['time_efficiency'] ?? 0.5;
        
        // Target: 70-80% success rate with good engagement
        if ($avg_score > 85 && $completion_rate > 0.9) {
            return 'hard';
        } elseif ($avg_score > 75 && $completion_rate > 0.8) {
            return 'medium-hard';
        } elseif ($avg_score > 65 && $completion_rate > 0.7) {
            return 'medium';
        } elseif ($avg_score > 55 && $completion_rate > 0.6) {
            return 'medium-easy';
        } else {
            return 'easy';
        }
    }
    
    private function getAdjustmentDirection($current, $suggested) {
        $levels = ['easy' => 1, 'medium-easy' => 2, 'medium' => 3, 'medium-hard' => 4, 'hard' => 5];
        
        $current_val = $levels[$current] ?? 3;
        $suggested_val = $levels[$suggested] ?? 3;
        
        if ($suggested_val > $current_val) return 'increase';
        if ($suggested_val < $current_val) return 'decrease';
        return 'maintain';
    }
    
    private function calculateConfidence($performance) {
        $data_points = $performance['total_quizzes'] ?? 0;
        
        if ($data_points >= 20) return 0.9;
        if ($data_points >= 10) return 0.7;
        if ($data_points >= 5) return 0.5;
        return 0.3;
    }
    
    private function explainAdjustment($performance, $suggested) {
        $reasons = [];
        
        if ($performance['avg_score'] > 85) {
            $reasons[] = 'High average score indicates mastery';
        }
        
        if ($performance['completion_rate'] < 0.6) {
            $reasons[] = 'Low completion rate suggests difficulty is too high';
        }
        
        if ($performance['time_efficiency'] < 0.3) {
            $reasons[] = 'Slow completion times may indicate struggle';
        }
        
        return $reasons;
    }
    
    protected function getWeightKey() {
        return 'money_quiz_ml_difficulty_weights';
    }
    
    protected function getDefaultWeights() {
        return []; // Not used for this model
    }
}