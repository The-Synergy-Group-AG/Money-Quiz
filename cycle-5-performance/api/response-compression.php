<?php
/**
 * Response Compression Implementation
 * Implements Gzip/Brotli compression for API responses
 */

class ResponseCompressor {
    private $config = [
        'compression_methods' => [
            'gzip' => ['priority' => 1, 'level' => 9],
            'brotli' => ['priority' => 0, 'level' => 11],
            'deflate' => ['priority' => 2, 'level' => 9]
        ],
        'min_size' => 1024, // Don't compress below 1KB
        'max_size' => 10485760, // Don't compress above 10MB
        'compressible_types' => [
            'application/json',
            'application/xml',
            'text/html',
            'text/plain',
            'text/css',
            'text/javascript',
            'application/javascript'
        ],
        'exclude_paths' => [
            '/api/stream',
            '/api/download'
        ],
        'cache_compressed' => true,
        'cache_ttl' => 3600 // 1 hour
    ];
    
    private $performance_monitor;
    private $compression_cache = [];
    private $stats = [
        'total_responses' => 0,
        'compressed_responses' => 0,
        'total_original_size' => 0,
        'total_compressed_size' => 0,
        'cache_hits' => 0
    ];
    
    public function __construct() {
        $this->performance_monitor = new PerformanceMonitor();
    }
    
    /**
     * Compress response based on client capabilities
     */
    public function compressResponse($data, $contentType, $acceptEncoding = '') {
        $startTime = microtime(true);
        
        // Check if compression should be applied
        if (!$this->shouldCompress($data, $contentType)) {
            return [
                'data' => $data,
                'encoding' => null,
                'original_size' => strlen($data),
                'compressed_size' => strlen($data)
            ];
        }
        
        // Determine best compression method
        $method = $this->selectCompressionMethod($acceptEncoding);
        
        if (!$method) {
            return [
                'data' => $data,
                'encoding' => null,
                'original_size' => strlen($data),
                'compressed_size' => strlen($data)
            ];
        }
        
        // Check cache
        $cacheKey = $this->generateCacheKey($data, $method);
        if ($this->config['cache_compressed'] && isset($this->compression_cache[$cacheKey])) {
            $cached = $this->compression_cache[$cacheKey];
            if ($cached['expires'] > time()) {
                $this->stats['cache_hits']++;
                return $cached['result'];
            }
        }
        
        // Compress data
        $originalSize = strlen($data);
        $compressed = $this->compress($data, $method);
        $compressedSize = strlen($compressed);
        
        // Only use compression if it reduces size
        if ($compressedSize >= $originalSize * 0.9) {
            $result = [
                'data' => $data,
                'encoding' => null,
                'original_size' => $originalSize,
                'compressed_size' => $originalSize
            ];
        } else {
            $result = [
                'data' => $compressed,
                'encoding' => $method,
                'original_size' => $originalSize,
                'compressed_size' => $compressedSize,
                'compression_ratio' => round((1 - $compressedSize / $originalSize) * 100, 2)
            ];
            
            $this->stats['compressed_responses']++;
            $this->stats['total_compressed_size'] += $compressedSize;
        }
        
        $this->stats['total_responses']++;
        $this->stats['total_original_size'] += $originalSize;
        
        // Cache result
        if ($this->config['cache_compressed']) {
            $this->compression_cache[$cacheKey] = [
                'result' => $result,
                'expires' => time() + $this->config['cache_ttl']
            ];
        }
        
        // Record metrics
        $this->performance_monitor->recordMetric('response_compression', [
            'method' => $method,
            'original_size' => $originalSize,
            'compressed_size' => $compressedSize,
            'compression_time' => microtime(true) - $startTime,
            'content_type' => $contentType
        ]);
        
        return $result;
    }
    
    /**
     * Check if response should be compressed
     */
    private function shouldCompress($data, $contentType) {
        $size = strlen($data);
        
        // Check size constraints
        if ($size < $this->config['min_size'] || $size > $this->config['max_size']) {
            return false;
        }
        
        // Check content type
        $compressible = false;
        foreach ($this->config['compressible_types'] as $type) {
            if (stripos($contentType, $type) !== false) {
                $compressible = true;
                break;
            }
        }
        
        if (!$compressible) {
            return false;
        }
        
        // Check if already compressed
        if ($this->isAlreadyCompressed($data)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if data is already compressed
     */
    private function isAlreadyCompressed($data) {
        if (strlen($data) < 2) {
            return false;
        }
        
        $header = substr($data, 0, 2);
        
        // Check for gzip magic number
        if ($header === "\x1f\x8b") {
            return true;
        }
        
        // Check for deflate
        if ($header === "\x78\x9c" || $header === "\x78\xda") {
            return true;
        }
        
        // Check for brotli
        if (ord($header[0]) === 0xce && (ord($header[1]) & 0xf0) === 0xb0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Select best compression method
     */
    private function selectCompressionMethod($acceptEncoding) {
        $acceptedEncodings = array_map('trim', explode(',', strtolower($acceptEncoding)));
        $availableMethods = [];
        
        foreach ($acceptedEncodings as $encoding) {
            // Parse quality value
            $parts = explode(';', $encoding);
            $method = trim($parts[0]);
            $quality = 1.0;
            
            if (isset($parts[1]) && preg_match('/q=([0-9.]+)/', $parts[1], $matches)) {
                $quality = floatval($matches[1]);
            }
            
            if (isset($this->config['compression_methods'][$method]) && $quality > 0) {
                $availableMethods[$method] = $quality;
            }
        }
        
        if (empty($availableMethods)) {
            return null;
        }
        
        // Sort by quality and priority
        $methods = [];
        foreach ($availableMethods as $method => $quality) {
            $priority = $this->config['compression_methods'][$method]['priority'];
            $methods[] = [
                'method' => $method,
                'score' => $quality * 10 - $priority
            ];
        }
        
        usort($methods, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Check method availability
        foreach ($methods as $method) {
            if ($this->isMethodAvailable($method['method'])) {
                return $method['method'];
            }
        }
        
        return null;
    }
    
    /**
     * Check if compression method is available
     */
    private function isMethodAvailable($method) {
        switch ($method) {
            case 'gzip':
                return function_exists('gzencode');
            case 'brotli':
                return function_exists('brotli_compress');
            case 'deflate':
                return function_exists('gzdeflate');
            default:
                return false;
        }
    }
    
    /**
     * Compress data using specified method
     */
    private function compress($data, $method) {
        $level = $this->config['compression_methods'][$method]['level'];
        
        switch ($method) {
            case 'gzip':
                return gzencode($data, $level);
            case 'brotli':
                return brotli_compress($data, $level);
            case 'deflate':
                return gzdeflate($data, $level);
            default:
                return $data;
        }
    }
    
    /**
     * Generate cache key
     */
    private function generateCacheKey($data, $method) {
        return md5($data . $method);
    }
    
    /**
     * Apply compression middleware
     */
    public function middleware($request, $response, $next) {
        // Check if path is excluded
        $path = $request->getUri()->getPath();
        foreach ($this->config['exclude_paths'] as $excludePath) {
            if (strpos($path, $excludePath) === 0) {
                return $next($request, $response);
            }
        }
        
        // Process request
        $response = $next($request, $response);
        
        // Get response data
        $body = (string) $response->getBody();
        $contentType = $response->getHeaderLine('Content-Type');
        $acceptEncoding = $request->getHeaderLine('Accept-Encoding');
        
        // Compress response
        $compressed = $this->compressResponse($body, $contentType, $acceptEncoding);
        
        if ($compressed['encoding']) {
            // Update response
            $response = $response
                ->withHeader('Content-Encoding', $compressed['encoding'])
                ->withHeader('Vary', 'Accept-Encoding')
                ->withHeader('X-Original-Size', $compressed['original_size'])
                ->withHeader('X-Compressed-Size', $compressed['compressed_size']);
            
            // Replace body
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $compressed['data']);
            rewind($stream);
            $response = $response->withBody(new \GuzzleHttp\Psr7\Stream($stream));
        }
        
        return $response;
    }
    
    /**
     * Generate compression headers
     */
    public function generateHeaders($encoding, $originalSize, $compressedSize) {
        $headers = [];
        
        if ($encoding) {
            $headers['Content-Encoding'] = $encoding;
            $headers['Vary'] = 'Accept-Encoding';
            $headers['X-Compression-Ratio'] = round((1 - $compressedSize / $originalSize) * 100, 2) . '%';
        }
        
        return $headers;
    }
    
    /**
     * Generate client decompression script
     */
    public function generateClientScript() {
        return '
// Response Decompression Client
class ResponseDecompressor {
    constructor() {
        this.stats = {
            totalResponses: 0,
            compressedResponses: 0,
            totalOriginalSize: 0,
            totalCompressedSize: 0,
            decompressionTime: 0
        };
        
        // Intercept fetch
        this.interceptFetch();
    }
    
    /**
     * Intercept fetch to handle compression
     */
    interceptFetch() {
        const originalFetch = window.fetch;
        const self = this;
        
        window.fetch = async function(...args) {
            const [url, options = {}] = args;
            
            // Add compression headers
            const headers = new Headers(options.headers || {});
            
            // Set Accept-Encoding if not already set
            if (!headers.has("Accept-Encoding")) {
                const supported = self.getSupportedEncodings();
                headers.set("Accept-Encoding", supported.join(", "));
            }
            
            options.headers = headers;
            
            // Make request
            const response = await originalFetch(url, options);
            
            // Check if response is compressed
            const encoding = response.headers.get("Content-Encoding");
            
            if (encoding && encoding !== "identity") {
                return self.decompressResponse(response, encoding);
            }
            
            return response;
        };
    }
    
    /**
     * Get supported compression encodings
     */
    getSupportedEncodings() {
        const encodings = [];
        
        // Check for native decompression support
        if ("CompressionStream" in window) {
            encodings.push("gzip", "deflate");
        }
        
        // Brotli is usually handled by browser
        if (this.isBrotliSupported()) {
            encodings.push("br");
        }
        
        // Always support uncompressed
        encodings.push("identity");
        
        return encodings;
    }
    
    /**
     * Check if Brotli is supported
     */
    isBrotliSupported() {
        // Most modern browsers support Brotli
        const ua = navigator.userAgent;
        const isChrome = ua.includes("Chrome") && !ua.includes("Edge");
        const isFirefox = ua.includes("Firefox");
        const isSafari = ua.includes("Safari") && !ua.includes("Chrome");
        
        return isChrome || isFirefox || (isSafari && parseFloat(ua.match(/Version\/(\d+)/)?.[1] || 0) >= 11);
    }
    
    /**
     * Decompress response
     */
    async decompressResponse(response, encoding) {
        const startTime = performance.now();
        
        // Clone response to avoid consuming original
        const cloned = response.clone();
        
        try {
            let decompressedData;
            
            if (encoding === "gzip" || encoding === "deflate") {
                decompressedData = await this.decompressWithStream(cloned, encoding);
            } else {
                // Browser should handle automatically
                decompressedData = await cloned.text();
            }
            
            // Update stats
            const originalSize = parseInt(response.headers.get("X-Original-Size") || "0");
            const compressedSize = parseInt(response.headers.get("X-Compressed-Size") || "0");
            
            this.stats.totalResponses++;
            this.stats.compressedResponses++;
            this.stats.totalOriginalSize += originalSize;
            this.stats.totalCompressedSize += compressedSize;
            this.stats.decompressionTime += performance.now() - startTime;
            
            // Create new response with decompressed data
            const decompressedResponse = new Response(decompressedData, {
                status: response.status,
                statusText: response.statusText,
                headers: response.headers
            });
            
            // Add custom properties
            decompressedResponse.wasCompressed = true;
            decompressedResponse.compressionRatio = compressedSize > 0 
                ? (1 - compressedSize / originalSize) * 100 
                : 0;
            
            return decompressedResponse;
        } catch (error) {
            console.error("Decompression failed:", error);
            return response;
        }
    }
    
    /**
     * Decompress using Compression Streams API
     */
    async decompressWithStream(response, encoding) {
        if (!("DecompressionStream" in window)) {
            // Fallback to browser decompression
            return await response.text();
        }
        
        const stream = response.body
            .pipeThrough(new DecompressionStream(encoding === "deflate" ? "deflate" : "gzip"));
        
        const reader = stream.getReader();
        const chunks = [];
        
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            chunks.push(value);
        }
        
        // Combine chunks
        const totalLength = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
        const combined = new Uint8Array(totalLength);
        let offset = 0;
        
        for (const chunk of chunks) {
            combined.set(chunk, offset);
            offset += chunk.length;
        }
        
        return new TextDecoder().decode(combined);
    }
    
    /**
     * Get compression statistics
     */
    getStats() {
        const avgCompressionRatio = this.stats.totalOriginalSize > 0
            ? (1 - this.stats.totalCompressedSize / this.stats.totalOriginalSize) * 100
            : 0;
        
        return {
            ...this.stats,
            averageCompressionRatio: avgCompressionRatio.toFixed(2) + "%",
            averageDecompressionTime: this.stats.compressedResponses > 0
                ? (this.stats.decompressionTime / this.stats.compressedResponses).toFixed(2) + "ms"
                : "0ms",
            totalSaved: this.formatBytes(this.stats.totalOriginalSize - this.stats.totalCompressedSize)
        };
    }
    
    /**
     * Format bytes to human readable
     */
    formatBytes(bytes) {
        const units = ["B", "KB", "MB", "GB"];
        let size = bytes;
        let unitIndex = 0;
        
        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }
        
        return size.toFixed(2) + " " + units[unitIndex];
    }
}

// Initialize decompressor
const decompressor = new ResponseDecompressor();

// Export for use
window.responseDecompressor = decompressor;
';
    }
    
    /**
     * Get compression statistics
     */
    public function getStats() {
        $avgCompressionRatio = $this->stats['total_original_size'] > 0
            ? (1 - $this->stats['total_compressed_size'] / $this->stats['total_original_size']) * 100
            : 0;
        
        return array_merge($this->stats, [
            'compression_ratio' => round($avgCompressionRatio, 2),
            'total_saved' => $this->stats['total_original_size'] - $this->stats['total_compressed_size'],
            'cache_hit_rate' => $this->stats['total_responses'] > 0
                ? round(($this->stats['cache_hits'] / $this->stats['total_responses']) * 100, 2)
                : 0
        ]);
    }
    
    /**
     * Generate compression report
     */
    public function generateReport() {
        $stats = $this->getStats();
        
        return [
            'summary' => [
                'total_responses' => $stats['total_responses'],
                'compressed_responses' => $stats['compressed_responses'],
                'compression_rate' => round(($stats['compressed_responses'] / max($stats['total_responses'], 1)) * 100, 2) . '%',
                'average_compression_ratio' => $stats['compression_ratio'] . '%',
                'total_saved' => $this->formatBytes($stats['total_saved'])
            ],
            'performance' => [
                'cache_hits' => $stats['cache_hits'],
                'cache_hit_rate' => $stats['cache_hit_rate'] . '%',
                'cache_size' => count($this->compression_cache)
            ],
            'methods' => $this->getMethodStats()
        ];
    }
    
    /**
     * Get statistics by compression method
     */
    private function getMethodStats() {
        // This would be tracked in real implementation
        return [
            'gzip' => ['count' => 0, 'avg_ratio' => 0],
            'brotli' => ['count' => 0, 'avg_ratio' => 0],
            'deflate' => ['count' => 0, 'avg_ratio' => 0]
        ];
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

// Example usage
$compressor = new ResponseCompressor();

// Compress sample JSON response
$jsonData = json_encode([
    'users' => [
        ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ['id' => 3, 'name' => 'Bob Johnson', 'email' => 'bob@example.com']
    ],
    'meta' => [
        'total' => 3,
        'page' => 1,
        'per_page' => 10
    ]
]);

// Simulate different Accept-Encoding headers
$acceptEncodings = [
    'gzip, deflate, br',
    'gzip',
    'br;q=1.0, gzip;q=0.8, *;q=0.1',
    ''
];

echo "Compression Test Results:\n\n";

foreach ($acceptEncodings as $encoding) {
    $result = $compressor->compressResponse($jsonData, 'application/json', $encoding);
    
    echo "Accept-Encoding: $encoding\n";
    echo "Method Used: " . ($result['encoding'] ?? 'none') . "\n";
    echo "Original Size: {$result['original_size']} bytes\n";
    echo "Compressed Size: {$result['compressed_size']} bytes\n";
    
    if (isset($result['compression_ratio'])) {
        echo "Compression Ratio: {$result['compression_ratio']}%\n";
    }
    
    echo "\n";
}

// Get overall statistics
$stats = $compressor->getStats();
echo "Overall Statistics:\n";
echo "Total Responses: {$stats['total_responses']}\n";
echo "Compressed Responses: {$stats['compressed_responses']}\n";
echo "Average Compression Ratio: {$stats['compression_ratio']}%\n";
echo "Total Saved: " . $compressor->formatBytes($stats['total_saved']) . "\n";

// Generate client script
file_put_contents('response-decompressor.js', $compressor->generateClientScript());

// Generate report
$report = $compressor->generateReport();
file_put_contents('compression-report.json', json_encode($report, JSON_PRETTY_PRINT));
