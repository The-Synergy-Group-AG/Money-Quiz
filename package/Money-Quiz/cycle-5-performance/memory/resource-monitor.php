<?php
/**
 * Resource Usage Tracking and Monitoring System
 * 
 * Comprehensive resource monitoring for CPU, memory, I/O, and network usage
 * with real-time tracking and historical analysis.
 */

namespace MoneyQuiz\Performance\Memory;

use Exception;

class ResourceMonitor {
    private array $metrics = [];
    private array $alerts = [];
    private array $config;
    private bool $isMonitoring = false;
    private $metricsFile;
    private array $thresholds = [];
    private array $callbacks = [];
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'sample_interval' => 1, // seconds
            'history_size' => 3600, // 1 hour of per-second data
            'metrics_file' => '/tmp/resource_metrics.dat',
            'enable_alerts' => true,
            'enable_logging' => true,
            'track_cpu' => true,
            'track_memory' => true,
            'track_io' => true,
            'track_network' => true,
            'track_handles' => true
        ], $config);
        
        $this->initializeThresholds();
        $this->initializeMetrics();
    }
    
    /**
     * Start monitoring resources
     */
    public function start(): void {
        if ($this->isMonitoring) {
            return;
        }
        
        $this->isMonitoring = true;
        
        // Open metrics file
        if ($this->config['enable_logging']) {
            $this->metricsFile = fopen($this->config['metrics_file'], 'a+b');
        }
        
        // Start monitoring loop in background
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                // Child process
                $this->runMonitoringLoop();
                exit(0);
            }
        } else {
            // Use tick function as fallback
            declare(ticks=1);
            register_tick_function([$this, 'collectMetrics']);
        }
    }
    
    /**
     * Stop monitoring
     */
    public function stop(): void {
        $this->isMonitoring = false;
        
        if ($this->metricsFile) {
            fclose($this->metricsFile);
        }
    }
    
    /**
     * Collect current metrics
     */
    public function collectMetrics(): array {
        $metrics = [
            'timestamp' => microtime(true),
            'cpu' => $this->config['track_cpu'] ? $this->getCPUMetrics() : null,
            'memory' => $this->config['track_memory'] ? $this->getMemoryMetrics() : null,
            'io' => $this->config['track_io'] ? $this->getIOMetrics() : null,
            'network' => $this->config['track_network'] ? $this->getNetworkMetrics() : null,
            'handles' => $this->config['track_handles'] ? $this->getHandleMetrics() : null
        ];
        
        // Store metrics
        $this->storeMetrics($metrics);
        
        // Check thresholds
        if ($this->config['enable_alerts']) {
            $this->checkThresholds($metrics);
        }
        
        // Log to file
        if ($this->metricsFile) {
            fwrite($this->metricsFile, serialize($metrics) . "\n");
        }
        
        return $metrics;
    }
    
    /**
     * Get CPU metrics
     */
    private function getCPUMetrics(): array {
        $metrics = [
            'usage' => 0,
            'user' => 0,
            'system' => 0,
            'idle' => 0,
            'load_average' => sys_getloadavg()
        ];
        
        if (PHP_OS_FAMILY === 'Linux') {
            // Read /proc/stat
            if (file_exists('/proc/stat')) {
                $stat = file_get_contents('/proc/stat');
                if (preg_match('/cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat, $matches)) {
                    $user = (int)$matches[1];
                    $nice = (int)$matches[2];
                    $system = (int)$matches[3];
                    $idle = (int)$matches[4];
                    
                    $total = $user + $nice + $system + $idle;
                    
                    // Calculate usage based on previous sample
                    static $lastCPU = null;
                    if ($lastCPU !== null) {
                        $deltaTotal = $total - $lastCPU['total'];
                        $deltaIdle = $idle - $lastCPU['idle'];
                        
                        if ($deltaTotal > 0) {
                            $metrics['usage'] = 100 * (1 - ($deltaIdle / $deltaTotal));
                            $metrics['user'] = 100 * (($user - $lastCPU['user']) / $deltaTotal);
                            $metrics['system'] = 100 * (($system - $lastCPU['system']) / $deltaTotal);
                            $metrics['idle'] = 100 * ($deltaIdle / $deltaTotal);
                        }
                    }
                    
                    $lastCPU = [
                        'total' => $total,
                        'user' => $user,
                        'system' => $system,
                        'idle' => $idle
                    ];
                }
            }
            
            // Process-specific CPU usage
            if (file_exists('/proc/self/stat')) {
                $stat = file_get_contents('/proc/self/stat');
                $fields = explode(' ', $stat);
                
                if (count($fields) > 14) {
                    $utime = (int)$fields[13];
                    $stime = (int)$fields[14];
                    
                    static $lastProcess = null;
                    if ($lastProcess !== null) {
                        $deltaUtime = $utime - $lastProcess['utime'];
                        $deltaStime = $stime - $lastProcess['stime'];
                        $deltaTime = microtime(true) - $lastProcess['time'];
                        
                        if ($deltaTime > 0) {
                            $hz = 100; // Typical USER_HZ value
                            $metrics['process_usage'] = 100 * (($deltaUtime + $deltaStime) / $hz) / $deltaTime;
                        }
                    }
                    
                    $lastProcess = [
                        'utime' => $utime,
                        'stime' => $stime,
                        'time' => microtime(true)
                    ];
                }
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get memory metrics
     */
    private function getMemoryMetrics(): array {
        $metrics = [
            'php_current' => memory_get_usage(true),
            'php_peak' => memory_get_peak_usage(true),
            'php_real' => memory_get_usage(false),
            'php_real_peak' => memory_get_peak_usage(false)
        ];
        
        if (PHP_OS_FAMILY === 'Linux') {
            // System memory from /proc/meminfo
            if (file_exists('/proc/meminfo')) {
                $meminfo = file_get_contents('/proc/meminfo');
                
                preg_match_all('/(\w+):\s+(\d+)/', $meminfo, $matches);
                $memData = array_combine($matches[1], $matches[2]);
                
                $metrics['system_total'] = ($memData['MemTotal'] ?? 0) * 1024;
                $metrics['system_free'] = ($memData['MemFree'] ?? 0) * 1024;
                $metrics['system_available'] = ($memData['MemAvailable'] ?? 0) * 1024;
                $metrics['system_buffers'] = ($memData['Buffers'] ?? 0) * 1024;
                $metrics['system_cached'] = ($memData['Cached'] ?? 0) * 1024;
                $metrics['swap_total'] = ($memData['SwapTotal'] ?? 0) * 1024;
                $metrics['swap_free'] = ($memData['SwapFree'] ?? 0) * 1024;
                
                $metrics['system_used'] = $metrics['system_total'] - $metrics['system_available'];
                $metrics['system_usage_percent'] = $metrics['system_total'] > 0 
                    ? ($metrics['system_used'] / $metrics['system_total']) * 100 
                    : 0;
            }
            
            // Process memory from /proc/self/status
            if (file_exists('/proc/self/status')) {
                $status = file_get_contents('/proc/self/status');
                
                preg_match('/VmRSS:\s+(\d+)/', $status, $matches);
                $metrics['process_rss'] = isset($matches[1]) ? $matches[1] * 1024 : 0;
                
                preg_match('/VmSize:\s+(\d+)/', $status, $matches);
                $metrics['process_vsize'] = isset($matches[1]) ? $matches[1] * 1024 : 0;
                
                preg_match('/VmPeak:\s+(\d+)/', $status, $matches);
                $metrics['process_peak'] = isset($matches[1]) ? $matches[1] * 1024 : 0;
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get I/O metrics
     */
    private function getIOMetrics(): array {
        $metrics = [
            'reads' => 0,
            'writes' => 0,
            'read_bytes' => 0,
            'write_bytes' => 0
        ];
        
        if (PHP_OS_FAMILY === 'Linux') {
            // Process I/O from /proc/self/io
            if (file_exists('/proc/self/io')) {
                $io = file_get_contents('/proc/self/io');
                
                preg_match('/read_bytes:\s+(\d+)/', $io, $matches);
                $readBytes = isset($matches[1]) ? (int)$matches[1] : 0;
                
                preg_match('/write_bytes:\s+(\d+)/', $io, $matches);
                $writeBytes = isset($matches[1]) ? (int)$matches[1] : 0;
                
                static $lastIO = null;
                if ($lastIO !== null) {
                    $metrics['read_bytes'] = $readBytes - $lastIO['read_bytes'];
                    $metrics['write_bytes'] = $writeBytes - $lastIO['write_bytes'];
                }
                
                $lastIO = [
                    'read_bytes' => $readBytes,
                    'write_bytes' => $writeBytes
                ];
                
                preg_match('/syscr:\s+(\d+)/', $io, $matches);
                $metrics['reads'] = isset($matches[1]) ? (int)$matches[1] : 0;
                
                preg_match('/syscw:\s+(\d+)/', $io, $matches);
                $metrics['writes'] = isset($matches[1]) ? (int)$matches[1] : 0;
            }
            
            // Disk stats from /proc/diskstats
            if (file_exists('/proc/diskstats')) {
                $diskstats = file_get_contents('/proc/diskstats');
                $lines = explode("\n", trim($diskstats));
                
                $metrics['disk_stats'] = [];
                foreach ($lines as $line) {
                    $fields = preg_split('/\s+/', trim($line));
                    if (count($fields) >= 14) {
                        $device = $fields[2];
                        if (preg_match('/^(sd|nvme|vd)[a-z]$/', $device)) {
                            $metrics['disk_stats'][$device] = [
                                'reads_completed' => (int)$fields[3],
                                'sectors_read' => (int)$fields[5],
                                'writes_completed' => (int)$fields[7],
                                'sectors_written' => (int)$fields[9],
                                'io_time' => (int)$fields[12]
                            ];
                        }
                    }
                }
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get network metrics
     */
    private function getNetworkMetrics(): array {
        $metrics = [
            'interfaces' => []
        ];
        
        if (PHP_OS_FAMILY === 'Linux') {
            // Network stats from /proc/net/dev
            if (file_exists('/proc/net/dev')) {
                $netdev = file_get_contents('/proc/net/dev');
                $lines = explode("\n", $netdev);
                
                foreach ($lines as $line) {
                    if (strpos($line, ':') !== false) {
                        list($interface, $stats) = explode(':', $line, 2);
                        $interface = trim($interface);
                        $fields = preg_split('/\s+/', trim($stats));
                        
                        if (count($fields) >= 16) {
                            $rxBytes = (int)$fields[0];
                            $rxPackets = (int)$fields[1];
                            $rxErrors = (int)$fields[2];
                            $rxDropped = (int)$fields[3];
                            $txBytes = (int)$fields[8];
                            $txPackets = (int)$fields[9];
                            $txErrors = (int)$fields[10];
                            $txDropped = (int)$fields[11];
                            
                            static $lastNet = [];
                            
                            $current = [
                                'rx_bytes' => $rxBytes,
                                'rx_packets' => $rxPackets,
                                'rx_errors' => $rxErrors,
                                'rx_dropped' => $rxDropped,
                                'tx_bytes' => $txBytes,
                                'tx_packets' => $txPackets,
                                'tx_errors' => $txErrors,
                                'tx_dropped' => $txDropped
                            ];
                            
                            if (isset($lastNet[$interface])) {
                                $deltaTime = time() - $lastNet[$interface]['time'];
                                if ($deltaTime > 0) {
                                    $current['rx_rate'] = ($rxBytes - $lastNet[$interface]['rx_bytes']) / $deltaTime;
                                    $current['tx_rate'] = ($txBytes - $lastNet[$interface]['tx_bytes']) / $deltaTime;
                                }
                            }
                            
                            $current['time'] = time();
                            $lastNet[$interface] = $current;
                            
                            $metrics['interfaces'][$interface] = $current;
                        }
                    }
                }
            }
            
            // TCP connection stats
            if (file_exists('/proc/net/tcp')) {
                $tcp = file_get_contents('/proc/net/tcp');
                $lines = explode("\n", $tcp);
                
                $states = ['ESTABLISHED' => 0, 'TIME_WAIT' => 0, 'CLOSE_WAIT' => 0];
                foreach ($lines as $line) {
                    if (preg_match('/^\s*\d+:/', $line)) {
                        $fields = preg_split('/\s+/', trim($line));
                        if (count($fields) >= 4) {
                            $state = hexdec($fields[3]);
                            if ($state == 1) $states['ESTABLISHED']++;
                            elseif ($state == 6) $states['TIME_WAIT']++;
                            elseif ($state == 8) $states['CLOSE_WAIT']++;
                        }
                    }
                }
                
                $metrics['tcp_connections'] = $states;
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get file handle metrics
     */
    private function getHandleMetrics(): array {
        $metrics = [
            'open_files' => 0,
            'max_files' => 0
        ];
        
        if (PHP_OS_FAMILY === 'Linux') {
            // Open file descriptors from /proc/self/fd
            if (is_dir('/proc/self/fd')) {
                $metrics['open_files'] = count(scandir('/proc/self/fd')) - 2; // Exclude . and ..
            }
            
            // Limits from /proc/self/limits
            if (file_exists('/proc/self/limits')) {
                $limits = file_get_contents('/proc/self/limits');
                if (preg_match('/Max open files\s+(\d+)\s+(\d+)/', $limits, $matches)) {
                    $metrics['max_files'] = (int)$matches[2];
                }
            }
        }
        
        // PHP resource count
        $metrics['php_resources'] = 0;
        foreach (get_resources() as $resource) {
            $metrics['php_resources']++;
        }
        
        return $metrics;
    }
    
    /**
     * Store metrics in memory
     */
    private function storeMetrics(array $metrics): void {
        $this->metrics[] = $metrics;
        
        // Limit history size
        if (count($this->metrics) > $this->config['history_size']) {
            array_shift($this->metrics);
        }
    }
    
    /**
     * Check thresholds and trigger alerts
     */
    private function checkThresholds(array $metrics): void {
        foreach ($this->thresholds as $metric => $threshold) {
            $value = $this->getMetricValue($metrics, $metric);
            
            if ($value !== null && $value > $threshold['value']) {
                $alert = [
                    'metric' => $metric,
                    'value' => $value,
                    'threshold' => $threshold['value'],
                    'severity' => $threshold['severity'],
                    'timestamp' => $metrics['timestamp'],
                    'message' => sprintf(
                        '%s exceeded threshold: %.2f > %.2f',
                        $metric,
                        $value,
                        $threshold['value']
                    )
                ];
                
                $this->alerts[] = $alert;
                
                // Trigger callback
                if (isset($threshold['callback'])) {
                    call_user_func($threshold['callback'], $alert);
                }
                
                // Trigger global callbacks
                foreach ($this->callbacks as $callback) {
                    call_user_func($callback, $alert);
                }
            }
        }
    }
    
    /**
     * Get metric value from nested array
     */
    private function getMetricValue(array $metrics, string $path): ?float {
        $parts = explode('.', $path);
        $value = $metrics;
        
        foreach ($parts as $part) {
            if (!isset($value[$part])) {
                return null;
            }
            $value = $value[$part];
        }
        
        return is_numeric($value) ? (float)$value : null;
    }
    
    /**
     * Initialize default thresholds
     */
    private function initializeThresholds(): void {
        $this->thresholds = [
            'cpu.usage' => ['value' => 80, 'severity' => 'warning'],
            'cpu.process_usage' => ['value' => 50, 'severity' => 'warning'],
            'memory.system_usage_percent' => ['value' => 90, 'severity' => 'critical'],
            'memory.php_current' => ['value' => 256 * 1024 * 1024, 'severity' => 'warning'],
            'io.read_bytes' => ['value' => 100 * 1024 * 1024, 'severity' => 'info'],
            'io.write_bytes' => ['value' => 100 * 1024 * 1024, 'severity' => 'info']
        ];
    }
    
    /**
     * Set threshold for metric
     */
    public function setThreshold(string $metric, float $value, string $severity = 'warning', callable $callback = null): void {
        $this->thresholds[$metric] = [
            'value' => $value,
            'severity' => $severity,
            'callback' => $callback
        ];
    }
    
    /**
     * Register alert callback
     */
    public function onAlert(callable $callback): void {
        $this->callbacks[] = $callback;
    }
    
    /**
     * Get current metrics snapshot
     */
    public function getCurrentMetrics(): array {
        return $this->collectMetrics();
    }
    
    /**
     * Get historical metrics
     */
    public function getHistory(int $seconds = 60): array {
        $cutoff = microtime(true) - $seconds;
        
        return array_filter($this->metrics, function ($metric) use ($cutoff) {
            return $metric['timestamp'] >= $cutoff;
        });
    }
    
    /**
     * Get metric statistics
     */
    public function getStats(string $metric, int $seconds = 60): array {
        $history = $this->getHistory($seconds);
        $values = [];
        
        foreach ($history as $data) {
            $value = $this->getMetricValue($data, $metric);
            if ($value !== null) {
                $values[] = $value;
            }
        }
        
        if (empty($values)) {
            return [];
        }
        
        return [
            'min' => min($values),
            'max' => max($values),
            'avg' => array_sum($values) / count($values),
            'current' => end($values),
            'samples' => count($values)
        ];
    }
    
    /**
     * Get alerts
     */
    public function getAlerts(int $seconds = null): array {
        if ($seconds === null) {
            return $this->alerts;
        }
        
        $cutoff = microtime(true) - $seconds;
        return array_filter($this->alerts, function ($alert) use ($cutoff) {
            return $alert['timestamp'] >= $cutoff;
        });
    }
    
    /**
     * Clear alerts
     */
    public function clearAlerts(): void {
        $this->alerts = [];
    }
    
    /**
     * Initialize metrics structure
     */
    private function initializeMetrics(): void {
        $this->metrics = [];
        $this->alerts = [];
    }
    
    /**
     * Run monitoring loop
     */
    private function runMonitoringLoop(): void {
        while ($this->isMonitoring) {
            $this->collectMetrics();
            sleep($this->config['sample_interval']);
        }
    }
    
    /**
     * Generate resource report
     */
    public function generateReport(int $duration = 3600): array {
        $history = $this->getHistory($duration);
        $report = [
            'duration' => $duration,
            'samples' => count($history),
            'alerts' => $this->getAlerts($duration)
        ];
        
        // CPU stats
        $report['cpu'] = [
            'usage' => $this->getStats('cpu.usage', $duration),
            'process' => $this->getStats('cpu.process_usage', $duration),
            'load_average' => $this->getStats('cpu.load_average.0', $duration)
        ];
        
        // Memory stats
        $report['memory'] = [
            'php_current' => $this->getStats('memory.php_current', $duration),
            'php_peak' => $this->getStats('memory.php_peak', $duration),
            'system_usage' => $this->getStats('memory.system_usage_percent', $duration)
        ];
        
        // I/O stats
        $report['io'] = [
            'read_bytes' => $this->getStats('io.read_bytes', $duration),
            'write_bytes' => $this->getStats('io.write_bytes', $duration)
        ];
        
        // Network stats
        $report['network'] = [];
        $latestMetrics = end($history);
        if ($latestMetrics && isset($latestMetrics['network']['interfaces'])) {
            foreach ($latestMetrics['network']['interfaces'] as $interface => $data) {
                $report['network'][$interface] = [
                    'rx_rate' => $this->getStats("network.interfaces.{$interface}.rx_rate", $duration),
                    'tx_rate' => $this->getStats("network.interfaces.{$interface}.tx_rate", $duration)
                ];
            }
        }
        
        return $report;
    }
}