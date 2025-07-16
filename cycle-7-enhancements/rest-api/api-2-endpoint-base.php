<?php
/**
 * REST API Base Endpoint Class
 * 
 * @package MoneyQuiz\API
 * @version 1.0.0
 */

namespace MoneyQuiz\API;

/**
 * Base API Endpoint
 */
abstract class ApiEndpointBase {
    
    protected $router;
    protected $namespace;
    protected $resource;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->router = ApiRouter::getInstance();
        $this->namespace = $this->router->getNamespace();
        $this->setupEndpoints();
    }
    
    /**
     * Setup endpoints - must be implemented by child classes
     */
    abstract protected function setupEndpoints();
    
    /**
     * Register CRUD endpoints
     */
    protected function registerCrudEndpoints() {
        // List/Create
        $this->router->addRoute("/{$this->resource}", 'GET', [$this, 'getItems'], [
            'permission_callback' => [$this, 'getItemsPermission'],
            'args' => $this->getCollectionParams()
        ]);
        
        $this->router->addRoute("/{$this->resource}", 'POST', [$this, 'createItem'], [
            'permission_callback' => [$this, 'createItemPermission'],
            'args' => $this->getCreateParams()
        ]);
        
        // Read/Update/Delete
        $this->router->addRoute("/{$this->resource}/(?P<id>[\d]+)", 'GET', [$this, 'getItem'], [
            'permission_callback' => [$this, 'getItemPermission'],
            'args' => ['id' => ['validate_callback' => [$this, 'validateId']]]
        ]);
        
        $this->router->addRoute("/{$this->resource}/(?P<id>[\d]+)", 'PUT', [$this, 'updateItem'], [
            'permission_callback' => [$this, 'updateItemPermission'],
            'args' => array_merge(
                ['id' => ['validate_callback' => [$this, 'validateId']]],
                $this->getUpdateParams()
            )
        ]);
        
        $this->router->addRoute("/{$this->resource}/(?P<id>[\d]+)", 'DELETE', [$this, 'deleteItem'], [
            'permission_callback' => [$this, 'deleteItemPermission'],
            'args' => ['id' => ['validate_callback' => [$this, 'validateId']]]
        ]);
    }
    
    /**
     * Default permission callbacks
     */
    public function getItemsPermission() {
        return true;
    }
    
    public function createItemPermission() {
        return current_user_can('edit_posts');
    }
    
    public function getItemPermission() {
        return true;
    }
    
    public function updateItemPermission() {
        return current_user_can('edit_posts');
    }
    
    public function deleteItemPermission() {
        return current_user_can('delete_posts');
    }
    
    /**
     * Validate ID parameter
     */
    public function validateId($value) {
        return is_numeric($value) && $value > 0;
    }
    
    /**
     * Get collection parameters
     */
    protected function getCollectionParams() {
        return [
            'page' => [
                'default' => 1,
                'validate_callback' => function($value) {
                    return is_numeric($value) && $value > 0;
                }
            ],
            'per_page' => [
                'default' => 10,
                'validate_callback' => function($value) {
                    return is_numeric($value) && $value > 0 && $value <= 100;
                }
            ],
            'search' => [
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'orderby' => [
                'default' => 'id',
                'enum' => ['id', 'title', 'date', 'modified']
            ],
            'order' => [
                'default' => 'desc',
                'enum' => ['asc', 'desc']
            ]
        ];
    }
    
    /**
     * Prepare item for response
     */
    protected function prepareItem($item) {
        return [
            'id' => $item->id,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at
        ];
    }
    
    /**
     * Prepare collection response
     */
    protected function prepareCollectionResponse($items, $total, $request) {
        $page = (int) $request->get_param('page');
        $per_page = (int) $request->get_param('per_page');
        
        $response = rest_ensure_response([
            'items' => array_map([$this, 'prepareItem'], $items),
            'total' => $total,
            'pages' => ceil($total / $per_page)
        ]);
        
        $response->header('X-Total-Count', $total);
        $response->header('X-Page', $page);
        $response->header('X-Per-Page', $per_page);
        
        return $response;
    }
    
    /**
     * Handle errors
     */
    protected function error($code, $message, $status = 400) {
        return new \WP_Error($code, $message, ['status' => $status]);
    }
    
    /**
     * Success response
     */
    protected function success($data = [], $message = '') {
        $response = ['success' => true];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        if (!empty($data)) {
            $response['data'] = $data;
        }
        
        return rest_ensure_response($response);
    }
    
    /**
     * Get create parameters - override in child classes
     */
    protected function getCreateParams() {
        return [];
    }
    
    /**
     * Get update parameters - override in child classes
     */
    protected function getUpdateParams() {
        return [];
    }
}