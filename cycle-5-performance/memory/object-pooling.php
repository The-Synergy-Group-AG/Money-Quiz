<?php
/**
 * Object Pooling and Reuse System
 * 
 * Implements object pooling patterns to reduce memory allocation overhead
 * and improve performance through object reuse.
 */

namespace MoneyQuiz\Performance\Memory;

use SplObjectStorage;
use WeakReference;
use Exception;

interface PoolableInterface {
    public function reset(): void;
    public function isReusable(): bool;
}

class ObjectPool {
    private array $pools = [];
    private array $config;
    private array $metrics = [
        'total_created' => 0,
        'total_reused' => 0,
        'current_pooled' => 0,
        'memory_saved' => 0
    ];
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'max_pool_size' => 100,
            'max_idle_time' => 300, // 5 minutes
            'enable_weak_refs' => PHP_VERSION_ID >= 70400,
            'track_metrics' => true,
            'auto_cleanup' => true,
            'cleanup_interval' => 60 // seconds
        ], $config);
        
        if ($this->config['auto_cleanup']) {
            $this->scheduleCleanup();
        }
    }
    
    /**
     * Register a class for pooling
     */
    public function registerClass(string $className, array $options = []): void {
        $this->pools[$className] = [
            'available' => [],
            'in_use' => $this->config['enable_weak_refs'] ? new WeakMap() : new SplObjectStorage(),
            'factory' => $options['factory'] ?? null,
            'max_size' => $options['max_size'] ?? $this->config['max_pool_size'],
            'created' => 0,
            'reused' => 0,
            'options' => $options
        ];
    }
    
    /**
     * Acquire object from pool
     */
    public function acquire(string $className, ...$constructorArgs) {
        if (!isset($this->pools[$className])) {
            $this->registerClass($className);
        }
        
        $pool = &$this->pools[$className];
        
        // Try to get from available pool
        while (!empty($pool['available'])) {
            $entry = array_pop($pool['available']);
            $object = $entry['object'];
            
            // Check if object is still valid
            if ($this->isObjectValid($object, $entry)) {
                // Reset and return
                if ($object instanceof PoolableInterface) {
                    $object->reset();
                }
                
                $this->trackInUse($className, $object);
                $pool['reused']++;
                $this->metrics['total_reused']++;
                
                return $object;
            }
        }
        
        // Create new object
        $object = $this->createObject($className, $constructorArgs);
        $this->trackInUse($className, $object);
        
        return $object;
    }
    
    /**
     * Release object back to pool
     */
    public function release(object $object): void {
        $className = get_class($object);
        
        if (!isset($this->pools[$className])) {
            return;
        }
        
        $pool = &$this->pools[$className];
        
        // Check if object can be reused
        if ($object instanceof PoolableInterface && !$object->isReusable()) {
            $this->removeFromTracking($className, $object);
            return;
        }
        
        // Check pool size limit
        if (count($pool['available']) >= $pool['max_size']) {
            $this->removeFromTracking($className, $object);
            return;
        }
        
        // Add to available pool
        $pool['available'][] = [
            'object' => $object,
            'released_at' => time(),
            'memory_size' => $this->estimateObjectSize($object)
        ];
        
        $this->removeFromTracking($className, $object);
        $this->metrics['current_pooled'] = $this->countPooledObjects();
    }
    
    /**
     * Create new object
     */
    private function createObject(string $className, array $args) {
        $pool = &$this->pools[$className];
        
        if (isset($pool['factory']) && is_callable($pool['factory'])) {
            $object = call_user_func_array($pool['factory'], $args);
        } else {
            $reflection = new \ReflectionClass($className);
            $object = $reflection->newInstanceArgs($args);
        }
        
        $pool['created']++;
        $this->metrics['total_created']++;
        
        return $object;
    }
    
    /**
     * Check if pooled object is still valid
     */
    private function isObjectValid(object $object, array $entry): bool {
        // Check idle time
        if (time() - $entry['released_at'] > $this->config['max_idle_time']) {
            return false;
        }
        
        // Check if object implements validation
        if ($object instanceof PoolableInterface) {
            return $object->isReusable();
        }
        
        return true;
    }
    
    /**
     * Track object in use
     */
    private function trackInUse(string $className, object $object): void {
        $pool = &$this->pools[$className];
        
        if ($this->config['enable_weak_refs']) {
            $pool['in_use'][$object] = time();
        } else {
            $pool['in_use']->attach($object, time());
        }
    }
    
    /**
     * Remove object from tracking
     */
    private function removeFromTracking(string $className, object $object): void {
        $pool = &$this->pools[$className];
        
        if ($this->config['enable_weak_refs']) {
            unset($pool['in_use'][$object]);
        } else {
            $pool['in_use']->detach($object);
        }
    }
    
    /**
     * Clean up expired objects
     */
    public function cleanup(): void {
        $now = time();
        $totalCleaned = 0;
        
        foreach ($this->pools as $className => &$pool) {
            $newAvailable = [];
            
            foreach ($pool['available'] as $entry) {
                if ($now - $entry['released_at'] <= $this->config['max_idle_time']) {
                    $newAvailable[] = $entry;
                } else {
                    $totalCleaned++;
                    $this->metrics['memory_saved'] += $entry['memory_size'];
                }
            }
            
            $pool['available'] = $newAvailable;
        }
        
        $this->metrics['current_pooled'] = $this->countPooledObjects();
        
        if ($totalCleaned > 0) {
            gc_collect_cycles();
        }
    }
    
    /**
     * Get pool statistics
     */
    public function getStats(string $className = null): array {
        if ($className) {
            if (!isset($this->pools[$className])) {
                return [];
            }
            
            $pool = $this->pools[$className];
            return [
                'created' => $pool['created'],
                'reused' => $pool['reused'],
                'available' => count($pool['available']),
                'in_use' => $this->countInUse($pool),
                'reuse_rate' => $pool['created'] > 0 
                    ? $pool['reused'] / ($pool['created'] + $pool['reused']) 
                    : 0
            ];
        }
        
        return $this->metrics;
    }
    
    /**
     * Clear pool for specific class
     */
    public function clearPool(string $className): void {
        if (isset($this->pools[$className])) {
            $this->pools[$className]['available'] = [];
            $this->metrics['current_pooled'] = $this->countPooledObjects();
        }
    }
    
    /**
     * Clear all pools
     */
    public function clearAll(): void {
        foreach ($this->pools as $className => $pool) {
            $this->clearPool($className);
        }
    }
    
    /**
     * Count pooled objects
     */
    private function countPooledObjects(): int {
        $count = 0;
        foreach ($this->pools as $pool) {
            $count += count($pool['available']);
        }
        return $count;
    }
    
    /**
     * Count objects in use
     */
    private function countInUse(array $pool): int {
        if ($this->config['enable_weak_refs']) {
            return count($pool['in_use']);
        } else {
            return $pool['in_use']->count();
        }
    }
    
    /**
     * Estimate object memory size
     */
    private function estimateObjectSize(object $object): int {
        // Basic estimation - can be overridden per class
        $size = 144; // Base object overhead
        
        $reflection = new \ReflectionObject($object);
        foreach ($reflection->getProperties() as $property) {
            $size += 32; // Property overhead
        }
        
        return $size;
    }
    
    /**
     * Schedule automatic cleanup
     */
    private function scheduleCleanup(): void {
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGALRM, [$this, 'cleanup']);
            pcntl_alarm($this->config['cleanup_interval']);
        }
    }
}

/**
 * Specialized pool for database connections
 */
class ConnectionPool extends ObjectPool {
    private array $connectionConfigs = [];
    
    public function __construct(array $config = []) {
        parent::__construct(array_merge([
            'max_pool_size' => 20,
            'max_idle_time' => 600,
            'ping_interval' => 60
        ], $config));
    }
    
    /**
     * Register database connection configuration
     */
    public function registerConnection(string $name, array $config): void {
        $this->connectionConfigs[$name] = $config;
        
        $this->registerClass($name, [
            'factory' => function () use ($config) {
                return $this->createConnection($config);
            },
            'max_size' => $config['pool_size'] ?? $this->config['max_pool_size']
        ]);
    }
    
    /**
     * Get connection from pool
     */
    public function getConnection(string $name): \PDO {
        if (!isset($this->connectionConfigs[$name])) {
            throw new Exception("Connection configuration '{$name}' not found");
        }
        
        return $this->acquire($name);
    }
    
    /**
     * Create PDO connection
     */
    private function createConnection(array $config): \PDO {
        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'] ?? 'mysql',
            $config['host'] ?? 'localhost',
            $config['port'] ?? 3306,
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );
        
        $pdo = new \PDO(
            $dsn,
            $config['username'],
            $config['password'],
            $config['options'] ?? [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        
        return new PooledConnection($pdo);
    }
}

/**
 * Pooled database connection wrapper
 */
class PooledConnection extends \PDO implements PoolableInterface {
    private \PDO $connection;
    private bool $inTransaction = false;
    private int $lastPing = 0;
    
    public function __construct(\PDO $connection) {
        $this->connection = $connection;
        $this->lastPing = time();
    }
    
    public function reset(): void {
        if ($this->inTransaction) {
            $this->rollBack();
        }
        
        // Clear any prepared statements
        $this->connection->exec("DEALLOCATE PREPARE stmt");
        
        // Reset connection state
        $this->connection->exec("SET SESSION sql_mode=DEFAULT");
        $this->connection->exec("SET SESSION time_zone=DEFAULT");
    }
    
    public function isReusable(): bool {
        // Check if connection is still alive
        if (time() - $this->lastPing > 60) {
            try {
                $this->connection->query("SELECT 1");
                $this->lastPing = time();
                return true;
            } catch (\PDOException $e) {
                return false;
            }
        }
        
        return !$this->inTransaction;
    }
    
    public function beginTransaction(): bool {
        $result = $this->connection->beginTransaction();
        if ($result) {
            $this->inTransaction = true;
        }
        return $result;
    }
    
    public function commit(): bool {
        $result = $this->connection->commit();
        if ($result) {
            $this->inTransaction = false;
        }
        return $result;
    }
    
    public function rollBack(): bool {
        $result = $this->connection->rollBack();
        if ($result) {
            $this->inTransaction = false;
        }
        return $result;
    }
    
    // Proxy all other methods to the wrapped connection
    public function __call($method, $args) {
        return call_user_func_array([$this->connection, $method], $args);
    }
}

/**
 * Array pool for large array allocations
 */
class ArrayPool {
    private array $pools = [];
    private array $config;
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'bucket_sizes' => [16, 32, 64, 128, 256, 512, 1024, 2048, 4096],
            'max_per_bucket' => 50
        ], $config);
        
        // Initialize buckets
        foreach ($this->config['bucket_sizes'] as $size) {
            $this->pools[$size] = [];
        }
    }
    
    /**
     * Get array from pool
     */
    public function allocate(int $size): array {
        $bucket = $this->getBucketSize($size);
        
        if (!empty($this->pools[$bucket])) {
            $array = array_pop($this->pools[$bucket]);
            // Clear array but keep allocated memory
            foreach (array_keys($array) as $key) {
                unset($array[$key]);
            }
            return $array;
        }
        
        // Pre-allocate array of bucket size
        $array = [];
        $array = array_pad($array, $bucket, null);
        $array = [];
        
        return $array;
    }
    
    /**
     * Return array to pool
     */
    public function deallocate(array &$array): void {
        $size = count($array);
        $bucket = $this->getBucketSize($size);
        
        // Only pool if under limit
        if (count($this->pools[$bucket]) < $this->config['max_per_bucket']) {
            $this->pools[$bucket][] = $array;
        }
        
        $array = null;
    }
    
    /**
     * Get appropriate bucket size
     */
    private function getBucketSize(int $size): int {
        foreach ($this->config['bucket_sizes'] as $bucketSize) {
            if ($size <= $bucketSize) {
                return $bucketSize;
            }
        }
        
        return end($this->config['bucket_sizes']);
    }
    
    /**
     * Clear all pools
     */
    public function clear(): void {
        foreach ($this->pools as &$pool) {
            $pool = [];
        }
    }
    
    /**
     * Get pool statistics
     */
    public function getStats(): array {
        $stats = [];
        
        foreach ($this->pools as $size => $pool) {
            $stats[$size] = [
                'pooled' => count($pool),
                'memory' => count($pool) * $size * 8 // Approximate bytes
            ];
        }
        
        return $stats;
    }
}

/**
 * String builder with pooled buffers
 */
class PooledStringBuilder implements PoolableInterface {
    private array $buffer = [];
    private int $length = 0;
    private static ?ArrayPool $arrayPool = null;
    
    public function __construct() {
        if (self::$arrayPool === null) {
            self::$arrayPool = new ArrayPool();
        }
    }
    
    public function append(string $str): self {
        $this->buffer[] = $str;
        $this->length += strlen($str);
        return $this;
    }
    
    public function toString(): string {
        return implode('', $this->buffer);
    }
    
    public function reset(): void {
        if (count($this->buffer) > 16) {
            self::$arrayPool->deallocate($this->buffer);
        }
        $this->buffer = [];
        $this->length = 0;
    }
    
    public function isReusable(): bool {
        return $this->length < 1048576; // 1MB limit
    }
    
    public function getLength(): int {
        return $this->length;
    }
}