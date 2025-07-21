<?php
/**
 * REST API Response Formatter
 * 
 * @package MoneyQuiz\API
 * @version 1.0.0
 */

namespace MoneyQuiz\API;

/**
 * Response Formatter
 */
class ResponseFormatter {
    
    /**
     * Format success response
     */
    public static function success($data = null, $message = '', $meta = []) {
        $response = [
            'success' => true,
            'code' => 'success'
        ];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        $response['timestamp'] = current_time('c');
        
        return rest_ensure_response($response);
    }
    
    /**
     * Format error response
     */
    public static function error($code, $message, $status = 400, $data = null) {
        $response = [
            'success' => false,
            'code' => $code,
            'message' => $message,
            'timestamp' => current_time('c')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return new \WP_Error($code, $message, array_merge(['status' => $status], $response));
    }
    
    /**
     * Format paginated response
     */
    public static function paginated($items, $total, $page, $per_page, $meta = []) {
        $total_pages = ceil($total / $per_page);
        
        $response = self::success($items, '', array_merge($meta, [
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ]));
        
        // Add pagination headers
        $response->header('X-Total-Count', $total);
        $response->header('X-Page', $page);
        $response->header('X-Per-Page', $per_page);
        $response->header('X-Total-Pages', $total_pages);
        
        return $response;
    }
    
    /**
     * Format validation errors
     */
    public static function validationError($errors) {
        $formatted_errors = [];
        
        foreach ($errors as $field => $messages) {
            $formatted_errors[] = [
                'field' => $field,
                'messages' => is_array($messages) ? $messages : [$messages]
            ];
        }
        
        return self::error(
            'validation_failed',
            'Validation failed',
            422,
            ['errors' => $formatted_errors]
        );
    }
    
    /**
     * Add CORS headers
     */
    public static function addCorsHeaders($response) {
        $origin = get_http_origin();
        $allowed_origins = apply_filters('money_quiz_api_allowed_origins', ['*']);
        
        if (in_array('*', $allowed_origins) || in_array($origin, $allowed_origins)) {
            $response->header('Access-Control-Allow-Origin', $origin ?: '*');
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-WP-Nonce, X-API-Key');
            $response->header('Access-Control-Max-Age', '3600');
        }
        
        return $response;
    }
    
    /**
     * Add cache headers
     */
    public static function addCacheHeaders($response, $cache_time = 0) {
        if ($cache_time > 0) {
            $response->header('Cache-Control', sprintf('public, max-age=%d', $cache_time));
            $response->header('Expires', gmdate('D, d M Y H:i:s', time() + $cache_time) . ' GMT');
        } else {
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
        }
        
        return $response;
    }
    
    /**
     * Format file response
     */
    public static function file($file_path, $filename = null, $mime_type = null) {
        if (!file_exists($file_path)) {
            return self::error('file_not_found', 'File not found', 404);
        }
        
        if (!$filename) {
            $filename = basename($file_path);
        }
        
        if (!$mime_type) {
            $mime_type = mime_content_type($file_path);
        }
        
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        
        readfile($file_path);
        exit;
    }
    
    /**
     * Transform item for API
     */
    public static function transform($item, $transformer) {
        if (is_callable($transformer)) {
            return call_user_func($transformer, $item);
        }
        
        if (is_string($transformer) && class_exists($transformer)) {
            $instance = new $transformer();
            if (method_exists($instance, 'transform')) {
                return $instance->transform($item);
            }
        }
        
        return $item;
    }
    
    /**
     * Transform collection
     */
    public static function transformCollection($items, $transformer) {
        return array_map(function($item) use ($transformer) {
            return self::transform($item, $transformer);
        }, $items);
    }
}