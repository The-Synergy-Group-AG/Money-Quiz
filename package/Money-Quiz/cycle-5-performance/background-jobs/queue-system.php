<?php
/**
 * Redis/Beanstalkd Queue System Implementation
 * 
 * Provides a flexible queue system supporting both Redis and Beanstalkd
 * for background job processing with high performance and reliability.
 */

namespace MoneyQuiz\Performance\BackgroundJobs;

use Predis\Client as RedisClient;
use Pheanstalk\Pheanstalk;
use Exception;

interface QueueInterface {
    public function push(string $queue, array $payload, int $delay = 0): string;
    public function pop(string $queue): ?array;
    public function ack(string $queue, string $jobId): void;
    public function fail(string $queue, string $jobId, string $reason): void;
    public function retry(string $queue, string $jobId, int $delay = 300): void;
    public function getStats(string $queue): array;
}

class RedisQueue implements QueueInterface {
    private RedisClient $redis;
    private array $config;
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,
            'prefix' => 'queue:',
            'retry_limit' => 3,
            'visibility_timeout' => 300
        ], $config);
        
        $this->redis = new RedisClient([
            'scheme' => 'tcp',
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'database' => $this->config['database']
        ]);
    }
    
    public function push(string $queue, array $payload, int $delay = 0): string {
        $jobId = $this->generateJobId();
        $job = [
            'id' => $jobId,
            'payload' => $payload,
            'attempts' => 0,
            'created_at' => time(),
            'available_at' => time() + $delay
        ];
        
        $queueKey = $this->getQueueKey($queue);
        
        if ($delay > 0) {
            // Add to delayed queue
            $this->redis->zadd(
                $this->getDelayedKey($queue),
                [json_encode($job) => time() + $delay]
            );
        } else {
            // Add to ready queue
            $this->redis->lpush($queueKey, [json_encode($job)]);
        }
        
        // Update stats
        $this->redis->hincrby($this->getStatsKey($queue), 'total_jobs', 1);
        
        return $jobId;
    }
    
    public function pop(string $queue): ?array {
        // Move delayed jobs to ready queue
        $this->processDelayedJobs($queue);
        
        $queueKey = $this->getQueueKey($queue);
        $processingKey = $this->getProcessingKey($queue);
        
        // Atomic pop and move to processing
        $lua = <<<LUA
            local job = redis.call('rpop', KEYS[1])
            if job then
                local decoded = cjson.decode(job)
                decoded.started_at = tonumber(ARGV[1])
                decoded.attempts = decoded.attempts + 1
                local updated = cjson.encode(decoded)
                redis.call('hset', KEYS[2], decoded.id, updated)
                redis.call('zadd', KEYS[3], tonumber(ARGV[2]), decoded.id)
                return updated
            end
            return nil
LUA;
        
        $job = $this->redis->eval(
            $lua,
            3,
            $queueKey,
            $processingKey,
            $this->getTimeoutKey($queue),
            time(),
            time() + $this->config['visibility_timeout']
        );
        
        if ($job) {
            $decoded = json_decode($job, true);
            $this->redis->hincrby($this->getStatsKey($queue), 'processing', 1);
            return $decoded;
        }
        
        return null;
    }
    
    public function ack(string $queue, string $jobId): void {
        $processingKey = $this->getProcessingKey($queue);
        $timeoutKey = $this->getTimeoutKey($queue);
        
        // Remove from processing
        $this->redis->hdel($processingKey, [$jobId]);
        $this->redis->zrem($timeoutKey, $jobId);
        
        // Update stats
        $stats = $this->getStatsKey($queue);
        $this->redis->hincrby($stats, 'processing', -1);
        $this->redis->hincrby($stats, 'completed', 1);
    }
    
    public function fail(string $queue, string $jobId, string $reason): void {
        $processingKey = $this->getProcessingKey($queue);
        $failedKey = $this->getFailedKey($queue);
        
        $job = $this->redis->hget($processingKey, $jobId);
        if ($job) {
            $decoded = json_decode($job, true);
            $decoded['failed_at'] = time();
            $decoded['failure_reason'] = $reason;
            
            // Move to failed queue
            $this->redis->lpush($failedKey, [json_encode($decoded)]);
            $this->redis->hdel($processingKey, [$jobId]);
            $this->redis->zrem($this->getTimeoutKey($queue), $jobId);
            
            // Update stats
            $stats = $this->getStatsKey($queue);
            $this->redis->hincrby($stats, 'processing', -1);
            $this->redis->hincrby($stats, 'failed', 1);
        }
    }
    
    public function retry(string $queue, string $jobId, int $delay = 300): void {
        $processingKey = $this->getProcessingKey($queue);
        
        $job = $this->redis->hget($processingKey, $jobId);
        if ($job) {
            $decoded = json_decode($job, true);
            
            if ($decoded['attempts'] >= $this->config['retry_limit']) {
                $this->fail($queue, $jobId, 'Exceeded retry limit');
                return;
            }
            
            // Remove from processing
            $this->redis->hdel($processingKey, [$jobId]);
            $this->redis->zrem($this->getTimeoutKey($queue), $jobId);
            
            // Add back to queue with delay
            $decoded['retry_at'] = time();
            $decoded['available_at'] = time() + $delay;
            
            $this->redis->zadd(
                $this->getDelayedKey($queue),
                [json_encode($decoded) => time() + $delay]
            );
            
            // Update stats
            $this->redis->hincrby($this->getStatsKey($queue), 'processing', -1);
            $this->redis->hincrby($this->getStatsKey($queue), 'retries', 1);
        }
    }
    
    public function getStats(string $queue): array {
        $stats = $this->redis->hgetall($this->getStatsKey($queue));
        
        return [
            'total_jobs' => (int)($stats['total_jobs'] ?? 0),
            'processing' => (int)($stats['processing'] ?? 0),
            'completed' => (int)($stats['completed'] ?? 0),
            'failed' => (int)($stats['failed'] ?? 0),
            'retries' => (int)($stats['retries'] ?? 0),
            'queue_size' => $this->redis->llen($this->getQueueKey($queue)),
            'delayed_size' => $this->redis->zcard($this->getDelayedKey($queue)),
            'failed_size' => $this->redis->llen($this->getFailedKey($queue))
        ];
    }
    
    private function processDelayedJobs(string $queue): void {
        $delayedKey = $this->getDelayedKey($queue);
        $queueKey = $this->getQueueKey($queue);
        
        // Move ready delayed jobs to main queue
        $lua = <<<LUA
            local ready = redis.call('zrangebyscore', KEYS[1], 0, ARGV[1])
            if #ready > 0 then
                for i, job in ipairs(ready) do
                    redis.call('lpush', KEYS[2], job)
                end
                redis.call('zremrangebyscore', KEYS[1], 0, ARGV[1])
            end
            return #ready
LUA;
        
        $this->redis->eval($lua, 2, $delayedKey, $queueKey, time());
    }
    
    private function generateJobId(): string {
        return uniqid('job_', true);
    }
    
    private function getQueueKey(string $queue): string {
        return $this->config['prefix'] . $queue . ':ready';
    }
    
    private function getDelayedKey(string $queue): string {
        return $this->config['prefix'] . $queue . ':delayed';
    }
    
    private function getProcessingKey(string $queue): string {
        return $this->config['prefix'] . $queue . ':processing';
    }
    
    private function getTimeoutKey(string $queue): string {
        return $this->config['prefix'] . $queue . ':timeout';
    }
    
    private function getFailedKey(string $queue): string {
        return $this->config['prefix'] . $queue . ':failed';
    }
    
    private function getStatsKey(string $queue): string {
        return $this->config['prefix'] . $queue . ':stats';
    }
}

class BeanstalkdQueue implements QueueInterface {
    private Pheanstalk $pheanstalk;
    private array $config;
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'host' => '127.0.0.1',
            'port' => 11300,
            'timeout' => 10,
            'ttr' => 300,
            'priority' => 1024,
            'retry_limit' => 3
        ], $config);
        
        $this->pheanstalk = Pheanstalk::create(
            $this->config['host'],
            $this->config['port'],
            $this->config['timeout']
        );
    }
    
    public function push(string $queue, array $payload, int $delay = 0): string {
        $jobId = $this->generateJobId();
        
        $job = [
            'id' => $jobId,
            'payload' => $payload,
            'attempts' => 0,
            'created_at' => time()
        ];
        
        $this->pheanstalk
            ->useTube($queue)
            ->put(
                json_encode($job),
                $this->config['priority'],
                $delay,
                $this->config['ttr']
            );
        
        return $jobId;
    }
    
    public function pop(string $queue): ?array {
        try {
            $job = $this->pheanstalk
                ->watch($queue)
                ->ignore('default')
                ->reserve();
            
            if ($job) {
                $decoded = json_decode($job->getData(), true);
                $decoded['beanstalk_id'] = $job->getId();
                $decoded['attempts']++;
                $decoded['started_at'] = time();
                
                return $decoded;
            }
        } catch (Exception $e) {
            // No job available
        }
        
        return null;
    }
    
    public function ack(string $queue, string $jobId): void {
        // In Beanstalkd, we need the job ID from the job data
        // This would typically be stored during pop()
        if (isset($this->jobMap[$jobId])) {
            $this->pheanstalk->delete($this->jobMap[$jobId]);
            unset($this->jobMap[$jobId]);
        }
    }
    
    public function fail(string $queue, string $jobId, string $reason): void {
        if (isset($this->jobMap[$jobId])) {
            $this->pheanstalk->bury($this->jobMap[$jobId]);
            unset($this->jobMap[$jobId]);
        }
    }
    
    public function retry(string $queue, string $jobId, int $delay = 300): void {
        if (isset($this->jobMap[$jobId])) {
            $this->pheanstalk->release(
                $this->jobMap[$jobId],
                $this->config['priority'],
                $delay
            );
            unset($this->jobMap[$jobId]);
        }
    }
    
    public function getStats(string $queue): array {
        $stats = $this->pheanstalk->statsTube($queue);
        
        return [
            'total_jobs' => $stats['total-jobs'] ?? 0,
            'current_jobs_ready' => $stats['current-jobs-ready'] ?? 0,
            'current_jobs_reserved' => $stats['current-jobs-reserved'] ?? 0,
            'current_jobs_delayed' => $stats['current-jobs-delayed'] ?? 0,
            'current_jobs_buried' => $stats['current-jobs-buried'] ?? 0
        ];
    }
    
    private function generateJobId(): string {
        return uniqid('job_', true);
    }
    
    private array $jobMap = []; // Temporary storage for job mapping
}

class QueueFactory {
    public static function create(string $driver, array $config = []): QueueInterface {
        switch ($driver) {
            case 'redis':
                return new RedisQueue($config);
            case 'beanstalkd':
                return new BeanstalkdQueue($config);
            default:
                throw new Exception("Unsupported queue driver: {$driver}");
        }
    }
}

// Usage example
class QueueManager {
    private QueueInterface $queue;
    
    public function __construct(string $driver = 'redis', array $config = []) {
        $this->queue = QueueFactory::create($driver, $config);
    }
    
    public function dispatch(string $jobClass, array $data, string $queue = 'default', int $delay = 0): string {
        $payload = [
            'class' => $jobClass,
            'data' => $data,
            'dispatched_at' => microtime(true)
        ];
        
        return $this->queue->push($queue, $payload, $delay);
    }
    
    public function work(string $queue = 'default', callable $handler = null): void {
        while (true) {
            $job = $this->queue->pop($queue);
            
            if ($job) {
                try {
                    if ($handler) {
                        $handler($job);
                    } else {
                        $this->processJob($job);
                    }
                    
                    $this->queue->ack($queue, $job['id']);
                } catch (Exception $e) {
                    if ($job['attempts'] >= 3) {
                        $this->queue->fail($queue, $job['id'], $e->getMessage());
                    } else {
                        $this->queue->retry($queue, $job['id']);
                    }
                }
            } else {
                // No job available, wait a bit
                usleep(100000); // 100ms
            }
        }
    }
    
    private function processJob(array $job): void {
        $class = $job['payload']['class'];
        $data = $job['payload']['data'];
        
        if (class_exists($class)) {
            $instance = new $class();
            if (method_exists($instance, 'handle')) {
                $instance->handle($data);
            }
        }
    }
}