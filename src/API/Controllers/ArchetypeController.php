<?php
/**
 * Archetype REST API Controller
 *
 * Handles archetype-related REST API endpoints.
 *
 * @package MoneyQuiz\API\Controllers
 * @since   7.0.0
 */

namespace MoneyQuiz\API\Controllers;

use MoneyQuiz\Domain\Repositories\ArchetypeRepository;
use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Security\Authorization;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Archetype controller class.
 *
 * @since 7.0.0
 */
class ArchetypeController {
    
    /**
     * API namespace.
     *
     * @var string
     */
    private const NAMESPACE = 'money-quiz/v1';
    
    /**
     * Archetype repository.
     *
     * @var ArchetypeRepository
     */
    private ArchetypeRepository $archetype_repository;
    
    /**
     * Authorization service.
     *
     * @var Authorization
     */
    private Authorization $authorization;
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Constructor.
     *
     * @param ArchetypeRepository $archetype_repository Archetype repository.
     * @param Authorization       $authorization        Authorization service.
     * @param Logger             $logger               Logger instance.
     */
    public function __construct(
        ArchetypeRepository $archetype_repository,
        Authorization $authorization,
        Logger $logger
    ) {
        $this->archetype_repository = $archetype_repository;
        $this->authorization = $authorization;
        $this->logger = $logger;
    }
    
    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // List archetypes
        register_rest_route(self::NAMESPACE, '/archetypes', [
            'methods' => 'GET',
            'callback' => [$this, 'get_archetypes'],
            'permission_callback' => '__return_true', // Public access
            'args' => [
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100
                ],
                'active_only' => [
                    'type' => 'boolean',
                    'default' => true
                ]
            ]
        ]);
        
        // Get single archetype
        register_rest_route(self::NAMESPACE, '/archetypes/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_archetype'],
            'permission_callback' => '__return_true', // Public access
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1
                ]
            ]
        ]);
        
        // Get archetype by slug
        register_rest_route(self::NAMESPACE, '/archetypes/slug/(?P<slug>[a-z0-9\-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_archetype_by_slug'],
            'permission_callback' => '__return_true', // Public access
            'args' => [
                'slug' => [
                    'type' => 'string',
                    'required' => true,
                    'pattern' => '^[a-z0-9\-]+$'
                ]
            ]
        ]);
        
        // Create archetype (admin only)
        register_rest_route(self::NAMESPACE, '/archetypes', [
            'methods' => 'POST',
            'callback' => [$this, 'create_archetype'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'name' => [
                    'type' => 'string',
                    'required' => true,
                    'minLength' => 3,
                    'maxLength' => 100
                ],
                'slug' => [
                    'type' => 'string',
                    'required' => true,
                    'pattern' => '^[a-z0-9\-]+$'
                ],
                'description' => [
                    'type' => 'string',
                    'required' => true,
                    'minLength' => 10,
                    'maxLength' => 2000
                ],
                'characteristics' => [
                    'type' => 'array',
                    'required' => true,
                    'minItems' => 1,
                    'items' => [
                        'type' => 'string'
                    ]
                ],
                'criteria' => [
                    'type' => 'object',
                    'required' => true
                ],
                'recommendation_templates' => [
                    'type' => 'array',
                    'default' => []
                ],
                'order' => [
                    'type' => 'integer',
                    'default' => 0,
                    'minimum' => 0
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'default' => true
                ]
            ]
        ]);
        
        // Update archetype (admin only)
        register_rest_route(self::NAMESPACE, '/archetypes/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_archetype'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1
                ],
                'name' => [
                    'type' => 'string',
                    'minLength' => 3,
                    'maxLength' => 100
                ],
                'description' => [
                    'type' => 'string',
                    'minLength' => 10,
                    'maxLength' => 2000
                ],
                'characteristics' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'type' => 'string'
                    ]
                ],
                'criteria' => [
                    'type' => 'object'
                ],
                'recommendation_templates' => [
                    'type' => 'array'
                ],
                'order' => [
                    'type' => 'integer',
                    'minimum' => 0
                ],
                'is_active' => [
                    'type' => 'boolean'
                ]
            ]
        ]);
        
        // Delete archetype (admin only)
        register_rest_route(self::NAMESPACE, '/archetypes/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_archetype'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1
                ]
            ]
        ]);
        
        // Get archetype usage statistics
        register_rest_route(self::NAMESPACE, '/archetypes/(?P<id>\d+)/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_archetype_stats'],
            'permission_callback' => [$this, 'check_view_stats_permission'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1
                ],
                'days' => [
                    'type' => 'integer',
                    'default' => 30,
                    'minimum' => 0
                ]
            ]
        ]);
    }
    
    /**
     * Get archetypes.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_archetypes(WP_REST_Request $request) {
        try {
            $page = $request->get_param('page');
            $per_page = $request->get_param('per_page');
            $active_only = $request->get_param('active_only');
            
            $criteria = [];
            if ($active_only) {
                $criteria['is_active'] = true;
            }
            
            $criteria['limit'] = $per_page;
            $criteria['offset'] = ($page - 1) * $per_page;
            
            $archetypes = $this->archetype_repository->find_all($criteria);
            
            $data = array_map([$this, 'prepare_archetype_response'], $archetypes);
            
            return new WP_REST_Response($data, 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get archetypes', [
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'get_archetypes_failed',
                __('Failed to retrieve archetypes.', 'money-quiz'),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Get single archetype.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_archetype(WP_REST_Request $request) {
        try {
            $id = (int) $request->get_param('id');
            
            $archetype = $this->archetype_repository->find_by_id($id);
            
            if (!$archetype) {
                return new WP_Error(
                    'archetype_not_found',
                    __('Archetype not found.', 'money-quiz'),
                    ['status' => 404]
                );
            }
            
            return new WP_REST_Response($this->prepare_archetype_response($archetype), 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get archetype', [
                'archetype_id' => $request->get_param('id'),
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'get_archetype_failed',
                __('Failed to retrieve archetype.', 'money-quiz'),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Get archetype by slug.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_archetype_by_slug(WP_REST_Request $request) {
        try {
            $slug = $request->get_param('slug');
            
            $archetype = $this->archetype_repository->find_by_slug($slug);
            
            if (!$archetype) {
                return new WP_Error(
                    'archetype_not_found',
                    __('Archetype not found.', 'money-quiz'),
                    ['status' => 404]
                );
            }
            
            return new WP_REST_Response($this->prepare_archetype_response($archetype), 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get archetype by slug', [
                'slug' => $request->get_param('slug'),
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'get_archetype_failed',
                __('Failed to retrieve archetype.', 'money-quiz'),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Create archetype.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function create_archetype(WP_REST_Request $request) {
        try {
            $data = [
                'name' => $request->get_param('name'),
                'slug' => $request->get_param('slug'),
                'description' => $request->get_param('description'),
                'characteristics' => $request->get_param('characteristics'),
                'criteria' => $request->get_param('criteria'),
                'recommendation_templates' => $request->get_param('recommendation_templates'),
                'order' => $request->get_param('order'),
                'is_active' => $request->get_param('is_active')
            ];
            
            // Create archetype entity
            $archetype = \MoneyQuiz\Domain\Entities\Archetype::from_array($data);
            
            // Save to repository
            if (!$this->archetype_repository->save($archetype)) {
                throw new \Exception('Failed to save archetype');
            }
            
            $this->logger->info('Archetype created', [
                'archetype_id' => $archetype->get_id(),
                'user_id' => get_current_user_id()
            ]);
            
            return new WP_REST_Response($this->prepare_archetype_response($archetype), 201);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to create archetype', [
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'create_archetype_failed',
                $e->getMessage(),
                ['status' => 400]
            );
        }
    }
    
    /**
     * Update archetype.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function update_archetype(WP_REST_Request $request) {
        try {
            $id = (int) $request->get_param('id');
            
            $archetype = $this->archetype_repository->find_by_id($id);
            if (!$archetype) {
                return new WP_Error(
                    'archetype_not_found',
                    __('Archetype not found.', 'money-quiz'),
                    ['status' => 404]
                );
            }
            
            // Build update data
            $data = $archetype->to_array();
            
            foreach (['name', 'description', 'characteristics', 'criteria', 
                     'recommendation_templates', 'order', 'is_active'] as $field) {
                if ($request->has_param($field)) {
                    $data[$field] = $request->get_param($field);
                }
            }
            
            // Create updated archetype
            $updated_archetype = \MoneyQuiz\Domain\Entities\Archetype::from_array($data);
            
            // Save to repository
            if (!$this->archetype_repository->save($updated_archetype)) {
                throw new \Exception('Failed to update archetype');
            }
            
            $this->logger->info('Archetype updated', [
                'archetype_id' => $id,
                'user_id' => get_current_user_id()
            ]);
            
            return new WP_REST_Response($this->prepare_archetype_response($updated_archetype), 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to update archetype', [
                'archetype_id' => $request->get_param('id'),
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'update_archetype_failed',
                $e->getMessage(),
                ['status' => 400]
            );
        }
    }
    
    /**
     * Delete archetype.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function delete_archetype(WP_REST_Request $request) {
        try {
            $id = (int) $request->get_param('id');
            
            if (!$this->archetype_repository->delete_by_id($id)) {
                return new WP_Error(
                    'delete_archetype_failed',
                    __('Failed to delete archetype.', 'money-quiz'),
                    ['status' => 500]
                );
            }
            
            $this->logger->info('Archetype deleted', [
                'archetype_id' => $id,
                'user_id' => get_current_user_id()
            ]);
            
            return new WP_REST_Response(null, 204);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete archetype', [
                'archetype_id' => $request->get_param('id'),
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'delete_archetype_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Get archetype usage statistics.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_archetype_stats(WP_REST_Request $request) {
        try {
            $id = (int) $request->get_param('id');
            $days = (int) $request->get_param('days');
            
            $archetype = $this->archetype_repository->find_by_id($id);
            if (!$archetype) {
                return new WP_Error(
                    'archetype_not_found',
                    __('Archetype not found.', 'money-quiz'),
                    ['status' => 404]
                );
            }
            
            $usage_count = $this->archetype_repository->get_usage_count($id, $days);
            
            return new WP_REST_Response([
                'archetype_id' => $id,
                'archetype_name' => $archetype->get_name(),
                'days' => $days,
                'usage_count' => $usage_count
            ], 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get archetype stats', [
                'archetype_id' => $request->get_param('id'),
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'get_archetype_stats_failed',
                __('Failed to retrieve statistics.', 'money-quiz'),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Check admin permission.
     *
     * @return bool True if admin.
     */
    public function check_admin_permission(): bool {
        return $this->authorization->can_manage_quizzes(get_current_user_id());
    }
    
    /**
     * Check view stats permission.
     *
     * @return bool True if allowed.
     */
    public function check_view_stats_permission(): bool {
        return $this->authorization->can_view_analytics(get_current_user_id());
    }
    
    /**
     * Prepare archetype response.
     *
     * @param \MoneyQuiz\Domain\Entities\Archetype $archetype Archetype entity.
     * @return array Prepared response.
     */
    private function prepare_archetype_response($archetype): array {
        return [
            'id' => $archetype->get_id(),
            'name' => $archetype->get_name(),
            'slug' => $archetype->get_slug(),
            'description' => $archetype->get_description(),
            'characteristics' => $archetype->get_characteristics(),
            'order' => $archetype->get_order(),
            'is_active' => $archetype->is_active()
        ];
    }
}