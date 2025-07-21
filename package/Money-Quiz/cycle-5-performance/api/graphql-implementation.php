<?php
/**
 * GraphQL Implementation for Efficient Queries
 * Provides GraphQL endpoint for optimized data fetching
 */

class GraphQLImplementation {
    private $config = [
        'max_query_depth' => 10,
        'max_query_complexity' => 1000,
        'introspection_enabled' => true,
        'query_batching' => true,
        'persistent_queries' => true,
        'cache_ttl' => 3600,
        'rate_limit' => [
            'max_requests' => 100,
            'window' => 60 // seconds
        ],
        'field_middleware' => [
            'auth' => true,
            'validation' => true,
            'logging' => true
        ]
    ];
    
    private $performance_monitor;
    private $schema;
    private $resolvers = [];
    private $query_cache = [];
    private $persistent_query_map = [];
    
    public function __construct() {
        $this->performance_monitor = new PerformanceMonitor();
        $this->initializeSchema();
    }
    
    /**
     * Initialize GraphQL schema
     */
    private function initializeSchema() {
        $this->schema = [
            'types' => $this->defineTypes(),
            'queries' => $this->defineQueries(),
            'mutations' => $this->defineMutations(),
            'subscriptions' => $this->defineSubscriptions()
        ];
    }
    
    /**
     * Define GraphQL types
     */
    private function defineTypes() {
        return [
            'User' => [
                'fields' => [
                    'id' => ['type' => 'ID!'],
                    'name' => ['type' => 'String!'],
                    'email' => ['type' => 'String!'],
                    'avatar' => ['type' => 'String'],
                    'posts' => [
                        'type' => '[Post!]',
                        'args' => [
                            'limit' => ['type' => 'Int', 'default' => 10],
                            'offset' => ['type' => 'Int', 'default' => 0]
                        ]
                    ],
                    'stats' => ['type' => 'UserStats']
                ]
            ],
            'Post' => [
                'fields' => [
                    'id' => ['type' => 'ID!'],
                    'title' => ['type' => 'String!'],
                    'content' => ['type' => 'String!'],
                    'author' => ['type' => 'User!'],
                    'comments' => [
                        'type' => '[Comment!]',
                        'args' => [
                            'limit' => ['type' => 'Int', 'default' => 20]
                        ]
                    ],
                    'createdAt' => ['type' => 'DateTime!'],
                    'updatedAt' => ['type' => 'DateTime!']
                ]
            ],
            'Comment' => [
                'fields' => [
                    'id' => ['type' => 'ID!'],
                    'content' => ['type' => 'String!'],
                    'author' => ['type' => 'User!'],
                    'post' => ['type' => 'Post!'],
                    'createdAt' => ['type' => 'DateTime!']
                ]
            ],
            'UserStats' => [
                'fields' => [
                    'postCount' => ['type' => 'Int!'],
                    'commentCount' => ['type' => 'Int!'],
                    'followerCount' => ['type' => 'Int!'],
                    'followingCount' => ['type' => 'Int!']
                ]
            ]
        ];
    }
    
    /**
     * Define queries
     */
    private function defineQueries() {
        return [
            'user' => [
                'type' => 'User',
                'args' => [
                    'id' => ['type' => 'ID!']
                ],
                'resolver' => 'resolveUser'
            ],
            'users' => [
                'type' => '[User!]',
                'args' => [
                    'filter' => ['type' => 'UserFilter'],
                    'sort' => ['type' => 'UserSort'],
                    'limit' => ['type' => 'Int', 'default' => 20],
                    'offset' => ['type' => 'Int', 'default' => 0]
                ],
                'resolver' => 'resolveUsers'
            ],
            'post' => [
                'type' => 'Post',
                'args' => [
                    'id' => ['type' => 'ID!']
                ],
                'resolver' => 'resolvePost'
            ],
            'posts' => [
                'type' => '[Post!]',
                'args' => [
                    'filter' => ['type' => 'PostFilter'],
                    'sort' => ['type' => 'PostSort'],
                    'limit' => ['type' => 'Int', 'default' => 20],
                    'offset' => ['type' => 'Int', 'default' => 0]
                ],
                'resolver' => 'resolvePosts'
            ],
            'search' => [
                'type' => 'SearchResult',
                'args' => [
                    'query' => ['type' => 'String!'],
                    'types' => ['type' => '[SearchType!]']
                ],
                'resolver' => 'resolveSearch'
            ]
        ];
    }
    
    /**
     * Define mutations
     */
    private function defineMutations() {
        return [
            'createUser' => [
                'type' => 'User',
                'args' => [
                    'input' => ['type' => 'CreateUserInput!']
                ],
                'resolver' => 'resolveCreateUser'
            ],
            'updateUser' => [
                'type' => 'User',
                'args' => [
                    'id' => ['type' => 'ID!'],
                    'input' => ['type' => 'UpdateUserInput!']
                ],
                'resolver' => 'resolveUpdateUser'
            ],
            'createPost' => [
                'type' => 'Post',
                'args' => [
                    'input' => ['type' => 'CreatePostInput!']
                ],
                'resolver' => 'resolveCreatePost'
            ],
            'deletePost' => [
                'type' => 'Boolean',
                'args' => [
                    'id' => ['type' => 'ID!']
                ],
                'resolver' => 'resolveDeletePost'
            ]
        ];
    }
    
    /**
     * Define subscriptions
     */
    private function defineSubscriptions() {
        return [
            'postCreated' => [
                'type' => 'Post',
                'args' => [
                    'authorId' => ['type' => 'ID']
                ],
                'resolver' => 'resolvePostCreated'
            ],
            'commentAdded' => [
                'type' => 'Comment',
                'args' => [
                    'postId' => ['type' => 'ID!']
                ],
                'resolver' => 'resolveCommentAdded'
            ]
        ];
    }
    
    /**
     * Execute GraphQL query
     */
    public function execute($query, $variables = [], $operationName = null) {
        $startTime = microtime(true);
        
        try {
            // Parse query
            $parsedQuery = $this->parseQuery($query);
            
            // Check for persistent query
            if ($this->config['persistent_queries'] && isset($variables['_persistentQueryId'])) {
                $parsedQuery = $this->loadPersistentQuery($variables['_persistentQueryId']);
            }
            
            // Validate query
            $validation = $this->validateQuery($parsedQuery);
            if (!$validation['valid']) {
                return [
                    'errors' => $validation['errors']
                ];
            }
            
            // Check cache
            $cacheKey = $this->generateCacheKey($parsedQuery, $variables);
            if (isset($this->query_cache[$cacheKey])) {
                $cached = $this->query_cache[$cacheKey];
                if ($cached['expires'] > time()) {
                    return $cached['result'];
                }
            }
            
            // Execute query
            $result = $this->executeQuery($parsedQuery, $variables, $operationName);
            
            // Cache result
            $this->query_cache[$cacheKey] = [
                'result' => $result,
                'expires' => time() + $this->config['cache_ttl']
            ];
            
            // Record metrics
            $this->performance_monitor->recordMetric('graphql_query', [
                'query_complexity' => $this->calculateComplexity($parsedQuery),
                'execution_time' => microtime(true) - $startTime,
                'fields_requested' => $this->countFields($parsedQuery),
                'cached' => false
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            return [
                'errors' => [[
                    'message' => $e->getMessage(),
                    'extensions' => [
                        'code' => 'INTERNAL_ERROR'
                    ]
                ]]
            ];
        }
    }
    
    /**
     * Parse GraphQL query
     */
    private function parseQuery($query) {
        // Simplified parser - in production use proper GraphQL parser
        $parsed = [
            'operation' => 'query',
            'name' => null,
            'selections' => []
        ];
        
        // Detect operation type
        if (preg_match('/^\s*(query|mutation|subscription)\s+([\w]+)?/i', $query, $matches)) {
            $parsed['operation'] = strtolower($matches[1]);
            $parsed['name'] = $matches[2] ?? null;
        }
        
        // Extract selections
        preg_match('/{([^}]+)}/', $query, $bodyMatch);
        if (isset($bodyMatch[1])) {
            $parsed['selections'] = $this->parseSelections($bodyMatch[1]);
        }
        
        return $parsed;
    }
    
    /**
     * Parse field selections
     */
    private function parseSelections($selectionString) {
        $selections = [];
        
        // Simple field extraction
        preg_match_all('/([\w]+)(?:\(([^)]*)\))?(?:\s*{([^}]+)})?/', $selectionString, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $field = [
                'name' => $match[1],
                'args' => [],
                'selections' => []
            ];
            
            // Parse arguments
            if (!empty($match[2])) {
                $field['args'] = $this->parseArguments($match[2]);
            }
            
            // Parse sub-selections
            if (!empty($match[3])) {
                $field['selections'] = $this->parseSelections($match[3]);
            }
            
            $selections[] = $field;
        }
        
        return $selections;
    }
    
    /**
     * Parse field arguments
     */
    private function parseArguments($argString) {
        $args = [];
        
        preg_match_all('/([\w]+):\s*([^,]+)/', $argString, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $name = $match[1];
            $value = trim($match[2]);
            
            // Parse value type
            if ($value === 'true' || $value === 'false') {
                $value = $value === 'true';
            } elseif (is_numeric($value)) {
                $value = strpos($value, '.') !== false ? floatval($value) : intval($value);
            } elseif (preg_match('/^["\'](.*)["\'\]$/', $value, $stringMatch)) {
                $value = $stringMatch[1];
            }
            
            $args[$name] = $value;
        }
        
        return $args;
    }
    
    /**
     * Validate query
     */
    private function validateQuery($parsedQuery) {
        $errors = [];
        
        // Check query depth
        $depth = $this->calculateDepth($parsedQuery['selections']);
        if ($depth > $this->config['max_query_depth']) {
            $errors[] = [
                'message' => "Query depth {$depth} exceeds maximum allowed depth of {$this->config['max_query_depth']}",
                'extensions' => ['code' => 'DEPTH_LIMIT_EXCEEDED']
            ];
        }
        
        // Check query complexity
        $complexity = $this->calculateComplexity($parsedQuery);
        if ($complexity > $this->config['max_query_complexity']) {
            $errors[] = [
                'message' => "Query complexity {$complexity} exceeds maximum allowed complexity of {$this->config['max_query_complexity']}",
                'extensions' => ['code' => 'COMPLEXITY_LIMIT_EXCEEDED']
            ];
        }
        
        // Validate fields exist
        foreach ($parsedQuery['selections'] as $selection) {
            if (!$this->fieldExists($parsedQuery['operation'], $selection['name'])) {
                $errors[] = [
                    'message' => "Field '{$selection['name']}' does not exist on type '{$parsedQuery['operation']}'",
                    'extensions' => ['code' => 'FIELD_NOT_FOUND']
                ];
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Calculate query depth
     */
    private function calculateDepth($selections, $currentDepth = 1) {
        $maxDepth = $currentDepth;
        
        foreach ($selections as $selection) {
            if (!empty($selection['selections'])) {
                $subDepth = $this->calculateDepth($selection['selections'], $currentDepth + 1);
                $maxDepth = max($maxDepth, $subDepth);
            }
        }
        
        return $maxDepth;
    }
    
    /**
     * Calculate query complexity
     */
    private function calculateComplexity($parsedQuery) {
        $complexity = 0;
        
        foreach ($parsedQuery['selections'] as $selection) {
            $complexity += $this->calculateFieldComplexity($selection);
        }
        
        return $complexity;
    }
    
    /**
     * Calculate field complexity
     */
    private function calculateFieldComplexity($field, $multiplier = 1) {
        $complexity = 1 * $multiplier;
        
        // Add complexity for list fields
        if (isset($field['args']['limit'])) {
            $complexity *= min($field['args']['limit'], 100);
        }
        
        // Add complexity for nested selections
        foreach ($field['selections'] ?? [] as $selection) {
            $complexity += $this->calculateFieldComplexity($selection, $multiplier);
        }
        
        return $complexity;
    }
    
    /**
     * Count fields in query
     */
    private function countFields($parsedQuery) {
        $count = 0;
        
        $countSelections = function($selections) use (&$count, &$countSelections) {
            foreach ($selections as $selection) {
                $count++;
                if (!empty($selection['selections'])) {
                    $countSelections($selection['selections']);
                }
            }
        };
        
        $countSelections($parsedQuery['selections']);
        
        return $count;
    }
    
    /**
     * Check if field exists
     */
    private function fieldExists($operation, $fieldName) {
        $rootFields = [];
        
        switch ($operation) {
            case 'query':
                $rootFields = array_keys($this->schema['queries']);
                break;
            case 'mutation':
                $rootFields = array_keys($this->schema['mutations']);
                break;
            case 'subscription':
                $rootFields = array_keys($this->schema['subscriptions']);
                break;
        }
        
        return in_array($fieldName, $rootFields);
    }
    
    /**
     * Execute parsed query
     */
    private function executeQuery($parsedQuery, $variables, $operationName) {
        $data = [];
        $errors = [];
        
        foreach ($parsedQuery['selections'] as $selection) {
            try {
                $fieldName = $selection['name'];
                $fieldDef = $this->getFieldDefinition($parsedQuery['operation'], $fieldName);
                
                if (!$fieldDef) {
                    throw new \Exception("Field '{$fieldName}' not found");
                }
                
                // Resolve field
                $resolver = $fieldDef['resolver'];
                $args = $this->resolveArguments($selection['args'], $fieldDef['args'] ?? [], $variables);
                
                $value = $this->$resolver($args, $selection['selections']);
                $data[$fieldName] = $value;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'message' => $e->getMessage(),
                    'path' => [$fieldName],
                    'extensions' => [
                        'code' => 'FIELD_ERROR'
                    ]
                ];
            }
        }
        
        $result = ['data' => $data];
        if (!empty($errors)) {
            $result['errors'] = $errors;
        }
        
        return $result;
    }
    
    /**
     * Get field definition
     */
    private function getFieldDefinition($operation, $fieldName) {
        switch ($operation) {
            case 'query':
                return $this->schema['queries'][$fieldName] ?? null;
            case 'mutation':
                return $this->schema['mutations'][$fieldName] ?? null;
            case 'subscription':
                return $this->schema['subscriptions'][$fieldName] ?? null;
        }
        return null;
    }
    
    /**
     * Resolve arguments with variables
     */
    private function resolveArguments($args, $argDefs, $variables) {
        $resolved = [];
        
        foreach ($argDefs as $argName => $argDef) {
            if (isset($args[$argName])) {
                $value = $args[$argName];
                
                // Handle variable references
                if (is_string($value) && strpos($value, '$') === 0) {
                    $varName = substr($value, 1);
                    $value = $variables[$varName] ?? $argDef['default'] ?? null;
                }
                
                $resolved[$argName] = $value;
            } elseif (isset($argDef['default'])) {
                $resolved[$argName] = $argDef['default'];
            }
        }
        
        return $resolved;
    }
    
    /**
     * Generate cache key
     */
    private function generateCacheKey($parsedQuery, $variables) {
        return md5(json_encode($parsedQuery) . json_encode($variables));
    }
    
    /**
     * Load persistent query
     */
    private function loadPersistentQuery($queryId) {
        if (!isset($this->persistent_query_map[$queryId])) {
            throw new \Exception("Persistent query not found: {$queryId}");
        }
        
        return $this->parseQuery($this->persistent_query_map[$queryId]);
    }
    
    /**
     * Register persistent query
     */
    public function registerPersistentQuery($queryId, $query) {
        $this->persistent_query_map[$queryId] = $query;
    }
    
    // Sample resolvers
    private function resolveUser($args, $selections) {
        // Simulate database fetch
        return [
            'id' => $args['id'],
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'avatar' => 'https://example.com/avatar.jpg'
        ];
    }
    
    private function resolveUsers($args, $selections) {
        // Simulate database fetch with pagination
        $users = [];
        for ($i = 0; $i < min($args['limit'], 20); $i++) {
            $users[] = [
                'id' => $args['offset'] + $i + 1,
                'name' => "User " . ($args['offset'] + $i + 1),
                'email' => "user" . ($args['offset'] + $i + 1) . "@example.com"
            ];
        }
        return $users;
    }
    
    private function resolvePost($args, $selections) {
        return [
            'id' => $args['id'],
            'title' => 'Sample Post',
            'content' => 'This is a sample post content.',
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];
    }
    
    private function resolvePosts($args, $selections) {
        $posts = [];
        for ($i = 0; $i < min($args['limit'], 20); $i++) {
            $posts[] = [
                'id' => $args['offset'] + $i + 1,
                'title' => "Post " . ($args['offset'] + $i + 1),
                'content' => "Content for post " . ($args['offset'] + $i + 1),
                'createdAt' => date('c', strtotime("-{$i} days")),
                'updatedAt' => date('c', strtotime("-{$i} days"))
            ];
        }
        return $posts;
    }
    
    /**
     * Generate GraphQL client
     */
    public function generateClient() {
        return '
// GraphQL Client with optimizations
class GraphQLClient {
    constructor(endpoint, options = {}) {
        this.endpoint = endpoint;
        this.options = {
            persistentQueries: true,
            batchRequests: true,
            cacheResponses: true,
            cacheTime: 300000, // 5 minutes
            maxBatchSize: 10,
            batchInterval: 10,
            ...options
        };
        
        this.cache = new Map();
        this.batchQueue = [];
        this.batchTimer = null;
        this.persistentQueryMap = new Map();
    }
    
    /**
     * Execute GraphQL query
     */
    async query(query, variables = {}, options = {}) {
        // Check cache
        if (this.options.cacheResponses) {
            const cacheKey = this.getCacheKey(query, variables);
            const cached = this.cache.get(cacheKey);
            
            if (cached && cached.expires > Date.now()) {
                return cached.data;
            }
        }
        
        // Handle persistent queries
        let queryToSend = query;
        if (this.options.persistentQueries) {
            const queryId = this.getQueryId(query);
            if (this.persistentQueryMap.has(queryId)) {
                variables._persistentQueryId = queryId;
                queryToSend = null;
            } else {
                this.persistentQueryMap.set(queryId, query);
            }
        }
        
        // Execute query
        const result = await this.executeQuery(queryToSend, variables, options);
        
        // Cache result
        if (this.options.cacheResponses && !result.errors) {
            const cacheKey = this.getCacheKey(query, variables);
            this.cache.set(cacheKey, {
                data: result,
                expires: Date.now() + this.options.cacheTime
            });
        }
        
        return result;
    }
    
    /**
     * Execute mutation
     */
    async mutate(mutation, variables = {}, options = {}) {
        // Mutations are never cached or batched
        return this.executeQuery(mutation, variables, { ...options, batch: false });
    }
    
    /**
     * Subscribe to GraphQL subscription
     */
    subscribe(subscription, variables = {}, options = {}) {
        // Return observable or event emitter
        const eventSource = new EventSource(
            `${this.endpoint}?subscription=${encodeURIComponent(subscription)}&variables=${encodeURIComponent(JSON.stringify(variables))}`
        );
        
        return {
            on: (event, handler) => eventSource.addEventListener(event, handler),
            close: () => eventSource.close()
        };
    }
    
    /**
     * Execute query with batching support
     */
    async executeQuery(query, variables, options = {}) {
        if (this.options.batchRequests && options.batch !== false) {
            return this.batchQuery(query, variables);
        }
        
        return this.sendRequest([{ query, variables }]).then(results => results[0]);
    }
    
    /**
     * Batch query execution
     */
    batchQuery(query, variables) {
        return new Promise((resolve, reject) => {
            this.batchQueue.push({ query, variables, resolve, reject });
            
            if (this.batchQueue.length >= this.options.maxBatchSize) {
                this.flushBatch();
            } else if (!this.batchTimer) {
                this.batchTimer = setTimeout(() => this.flushBatch(), this.options.batchInterval);
            }
        });
    }
    
    /**
     * Flush batch queue
     */
    async flushBatch() {
        if (this.batchTimer) {
            clearTimeout(this.batchTimer);
            this.batchTimer = null;
        }
        
        if (this.batchQueue.length === 0) return;
        
        const batch = this.batchQueue.splice(0, this.options.maxBatchSize);
        const requests = batch.map(({ query, variables }) => ({ query, variables }));
        
        try {
            const results = await this.sendRequest(requests);
            
            batch.forEach((item, index) => {
                if (results[index].errors) {
                    item.reject(new Error(results[index].errors[0].message));
                } else {
                    item.resolve(results[index]);
                }
            });
        } catch (error) {
            batch.forEach(item => item.reject(error));
        }
    }
    
    /**
     * Send request to GraphQL endpoint
     */
    async sendRequest(requests) {
        const response = await fetch(this.endpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                ...this.options.headers
            },
            body: JSON.stringify(requests.length === 1 ? requests[0] : { batch: requests })
        });
        
        if (!response.ok) {
            throw new Error(`GraphQL request failed: ${response.statusText}`);
        }
        
        const data = await response.json();
        return Array.isArray(data) ? data : [data];
    }
    
    /**
     * Get cache key
     */
    getCacheKey(query, variables) {
        return `${this.getQueryId(query)}:${JSON.stringify(variables)}`;
    }
    
    /**
     * Get query ID (hash)
     */
    getQueryId(query) {
        let hash = 0;
        for (let i = 0; i < query.length; i++) {
            const char = query.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return hash.toString(36);
    }
    
    /**
     * Clear cache
     */
    clearCache() {
        this.cache.clear();
    }
    
    /**
     * Prefetch query
     */
    async prefetch(query, variables = {}) {
        return this.query(query, variables, { priority: "low" });
    }
}

// Usage example:
const client = new GraphQLClient("/api/graphql");

// Simple query
const user = await client.query(`
    query GetUser($id: ID!) {
        user(id: $id) {
            id
            name
            email
            posts(limit: 5) {
                id
                title
            }
        }
    }
`, { id: "123" });

// Batched queries (automatically batched)
const [users, posts] = await Promise.all([
    client.query(`query { users(limit: 10) { id name } }`),
    client.query(`query { posts(limit: 20) { id title } }`)
]);

// Mutation
const newPost = await client.mutate(`
    mutation CreatePost($input: CreatePostInput!) {
        createPost(input: $input) {
            id
            title
            content
        }
    }
`, {
    input: {
        title: "New Post",
        content: "Post content"
    }
});

// Subscription
const subscription = client.subscribe(`
    subscription OnCommentAdded($postId: ID!) {
        commentAdded(postId: $postId) {
            id
            content
            author {
                name
            }
        }
    }
`, { postId: "123" });

subscription.on("message", (event) => {
    console.log("New comment:", JSON.parse(event.data));
});
';
    }
}

// Example usage
$graphql = new GraphQLImplementation();

// Register persistent queries
$graphql->registerPersistentQuery('getUserQuery', '
    query GetUser($id: ID!) {
        user(id: $id) {
            id
            name
            email
        }
    }
');

// Execute queries
$queries = [
    [
        'query' => 'query { users(limit: 3) { id name email } }',
        'variables' => []
    ],
    [
        'query' => 'query GetUser($id: ID!) { user(id: $id) { id name email posts(limit: 2) { id title } } }',
        'variables' => ['id' => '123']
    ],
    [
        'query' => 'query { posts(limit: 5, offset: 10) { id title content createdAt } }',
        'variables' => []
    ]
];

echo "GraphQL Query Results:\n\n";

foreach ($queries as $i => $q) {
    $result = $graphql->execute($q['query'], $q['variables']);
    
    echo "Query " . ($i + 1) . ":\n";
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
}

// Test with persistent query
$persistentResult = $graphql->execute('', ['_persistentQueryId' => 'getUserQuery', 'id' => '456']);
echo "Persistent Query Result:\n";
echo json_encode($persistentResult, JSON_PRETTY_PRINT) . "\n";

// Generate client
file_put_contents('graphql-client.js', $graphql->generateClient());
