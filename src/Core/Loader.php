<?php
/**
 * Plugin Loader
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Core;

/**
 * Register all actions and filters for the plugin
 * 
 * Maintains a list of all hooks that are registered throughout
 * the plugin, and registers them with WordPress API.
 */
class Loader {
    
    /**
     * @var array<array{hook: string, component: object|null, callback: callable, priority: int, accepted_args: int}> Actions
     */
    protected array $actions = [];
    
    /**
     * @var array<array{hook: string, component: object|null, callback: callable, priority: int, accepted_args: int}> Filters
     */
    protected array $filters = [];
    
    /**
     * Add a new action to the collection
     * 
     * @param string        $hook          WordPress action hook
     * @param object|null   $component     Object instance
     * @param string        $callback      Method name
     * @param int           $priority      Priority
     * @param int           $accepted_args Number of accepted arguments
     * @return void
     */
    public function add_action( 
        string $hook, 
        ?object $component, 
        string $callback, 
        int $priority = 10, 
        int $accepted_args = 1 
    ): void {
        $this->actions[] = $this->create_hook_array( 
            $hook, 
            $component, 
            $callback, 
            $priority, 
            $accepted_args 
        );
    }
    
    /**
     * Add a new filter to the collection
     * 
     * @param string        $hook          WordPress filter hook
     * @param object|null   $component     Object instance
     * @param string        $callback      Method name
     * @param int           $priority      Priority
     * @param int           $accepted_args Number of accepted arguments
     * @return void
     */
    public function add_filter( 
        string $hook, 
        ?object $component, 
        string $callback, 
        int $priority = 10, 
        int $accepted_args = 1 
    ): void {
        $this->filters[] = $this->create_hook_array( 
            $hook, 
            $component, 
            $callback, 
            $priority, 
            $accepted_args 
        );
    }
    
    /**
     * Remove an action from the collection
     * 
     * @param string        $hook      WordPress action hook
     * @param object|null   $component Object instance
     * @param string        $callback  Method name
     * @return bool Whether the action was removed
     */
    public function remove_action( string $hook, ?object $component, string $callback ): bool {
        return $this->remove_hook( $this->actions, $hook, $component, $callback );
    }
    
    /**
     * Remove a filter from the collection
     * 
     * @param string        $hook      WordPress filter hook
     * @param object|null   $component Object instance
     * @param string        $callback  Method name
     * @return bool Whether the filter was removed
     */
    public function remove_filter( string $hook, ?object $component, string $callback ): bool {
        return $this->remove_hook( $this->filters, $hook, $component, $callback );
    }
    
    /**
     * Register all hooks with WordPress
     * 
     * @return void
     */
    public function run(): void {
        // Register all filters
        foreach ( $this->filters as $hook ) {
            add_filter( 
                $hook['hook'], 
                $hook['callback'], 
                $hook['priority'], 
                $hook['accepted_args'] 
            );
        }
        
        // Register all actions
        foreach ( $this->actions as $hook ) {
            add_action( 
                $hook['hook'], 
                $hook['callback'], 
                $hook['priority'], 
                $hook['accepted_args'] 
            );
        }
    }
    
    /**
     * Create hook array
     * 
     * @param string        $hook          WordPress hook
     * @param object|null   $component     Object instance
     * @param string        $callback      Method name
     * @param int           $priority      Priority
     * @param int           $accepted_args Number of accepted arguments
     * @return array{hook: string, component: object|null, callback: callable, priority: int, accepted_args: int}
     */
    private function create_hook_array( 
        string $hook, 
        ?object $component, 
        string $callback, 
        int $priority, 
        int $accepted_args 
    ): array {
        return [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $this->create_callback( $component, $callback ),
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }
    
    /**
     * Create a proper callback
     * 
     * @param object|null $component Object instance
     * @param string      $callback  Method name
     * @return callable
     */
    private function create_callback( ?object $component, string $callback ): callable {
        if ( null === $component ) {
            return $callback;
        }
        
        return [ $component, $callback ];
    }
    
    /**
     * Remove a hook from the collection
     * 
     * @param array         $hooks     Hook collection (actions or filters)
     * @param string        $hook      WordPress hook
     * @param object|null   $component Object instance
     * @param string        $callback  Method name
     * @return bool Whether the hook was removed
     */
    private function remove_hook( array &$hooks, string $hook, ?object $component, string $callback ): bool {
        $callback_to_remove = $this->create_callback( $component, $callback );
        
        foreach ( $hooks as $key => $hook_data ) {
            if ( $hook_data['hook'] === $hook && $hook_data['callback'] === $callback_to_remove ) {
                unset( $hooks[ $key ] );
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all registered actions
     * 
     * @return array<array{hook: string, component: object|null, callback: callable, priority: int, accepted_args: int}>
     */
    public function get_actions(): array {
        return $this->actions;
    }
    
    /**
     * Get all registered filters
     * 
     * @return array<array{hook: string, component: object|null, callback: callable, priority: int, accepted_args: int}>
     */
    public function get_filters(): array {
        return $this->filters;
    }
}