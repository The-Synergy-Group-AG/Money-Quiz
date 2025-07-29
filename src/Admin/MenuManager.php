<?php
/**
 * Menu Manager
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Admin;

/**
 * Manages admin menu items
 */
class MenuManager {
    
    /**
     * @var string Menu slug
     */
    private string $menu_slug = 'money-quiz';
    
    /**
     * @var array Menu pages
     */
    private array $menu_pages = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->menu_pages = $this->get_menu_structure();
    }
    
    /**
     * Register admin menus
     * 
     * @return void
     */
    public function register_menus(): void {
        // Check if legacy menu already exists
        global $submenu;
        if ( isset( $submenu['moneyquiz'] ) || isset( $submenu['money-quiz'] ) ) {
            // Add our new menu items to existing menu
            $this->add_to_legacy_menu();
            return;
        }
        
        // Create main menu
        add_menu_page(
            __( 'Money Quiz', 'money-quiz' ),
            __( 'Money Quiz', 'money-quiz' ),
            'manage_options',
            $this->menu_slug,
            [ $this, 'render_dashboard_page' ],
            $this->get_menu_icon(),
            30
        );
        
        // Add submenu pages
        foreach ( $this->menu_pages as $page ) {
            add_submenu_page(
                $this->menu_slug,
                $page['page_title'],
                $page['menu_title'],
                $page['capability'],
                $page['menu_slug'],
                $page['callback']
            );
        }
        
        // Rename first submenu item
        global $submenu;
        if ( isset( $submenu[ $this->menu_slug ] ) ) {
            $submenu[ $this->menu_slug ][0][0] = __( 'Dashboard', 'money-quiz' );
        }
    }
    
    /**
     * Get menu structure
     * 
     * @return array
     */
    private function get_menu_structure(): array {
        return [
            'quizzes' => [
                'page_title' => __( 'Quizzes', 'money-quiz' ),
                'menu_title' => __( 'Quizzes', 'money-quiz' ),
                'capability' => 'manage_options',
                'menu_slug'  => 'money-quiz-quizzes',
                'callback'   => [ $this, 'render_quizzes_page' ],
            ],
            'results' => [
                'page_title' => __( 'Results', 'money-quiz' ),
                'menu_title' => __( 'Results', 'money-quiz' ),
                'capability' => 'manage_options',
                'menu_slug'  => 'money-quiz-results',
                'callback'   => [ $this, 'render_results_page' ],
            ],
            'archetypes' => [
                'page_title' => __( 'Archetypes', 'money-quiz' ),
                'menu_title' => __( 'Archetypes', 'money-quiz' ),
                'capability' => 'manage_options',
                'menu_slug'  => 'money-quiz-archetypes',
                'callback'   => [ $this, 'render_archetypes_page' ],
            ],
            'prospects' => [
                'page_title' => __( 'Prospects', 'money-quiz' ),
                'menu_title' => __( 'Prospects', 'money-quiz' ),
                'capability' => 'manage_options',
                'menu_slug'  => 'money-quiz-prospects',
                'callback'   => [ $this, 'render_prospects_page' ],
            ],
            'settings' => [
                'page_title' => __( 'Settings', 'money-quiz' ),
                'menu_title' => __( 'Settings', 'money-quiz' ),
                'capability' => 'manage_options',
                'menu_slug'  => 'money-quiz-settings',
                'callback'   => [ $this, 'render_settings_page' ],
            ],
        ];
    }
    
    /**
     * Add to legacy menu
     * 
     * @return void
     */
    private function add_to_legacy_menu(): void {
        $parent_slug = 'moneyquiz'; // Legacy menu slug
        
        // Add modern pages that don't conflict with legacy
        add_submenu_page(
            $parent_slug,
            __( 'Modern Dashboard', 'money-quiz' ),
            __( 'Modern Dashboard', 'money-quiz' ),
            'manage_options',
            'money-quiz-modern',
            [ $this, 'render_dashboard_page' ]
        );
    }
    
    /**
     * Get menu icon
     * 
     * @return string
     */
    private function get_menu_icon(): string {
        // Use dashicon or base64 SVG
        return 'dashicons-chart-pie';
    }
    
    /**
     * Render dashboard page
     * 
     * @return void
     */
    public function render_dashboard_page(): void {
        // Check if controller exists
        $controller_class = 'MoneyQuiz\\Admin\\Controllers\\DashboardController';
        
        if ( class_exists( $controller_class ) ) {
            $controller = new $controller_class();
            $controller->index();
        } else {
            // Fallback to basic dashboard
            $this->render_basic_dashboard();
        }
    }
    
    /**
     * Render quizzes page
     * 
     * @return void
     */
    public function render_quizzes_page(): void {
        $controller_class = 'MoneyQuiz\\Admin\\Controllers\\QuizController';
        
        if ( class_exists( $controller_class ) ) {
            $controller = new $controller_class();
            
            // Route to appropriate action
            $action = $_GET['action'] ?? 'index';
            
            switch ( $action ) {
                case 'new':
                    $controller->create();
                    break;
                case 'edit':
                    $id = absint( $_GET['id'] ?? 0 );
                    $controller->edit( $id );
                    break;
                case 'delete':
                    $id = absint( $_GET['id'] ?? 0 );
                    $controller->delete( $id );
                    break;
                default:
                    $controller->index();
            }
        } else {
            // Redirect to legacy quiz management if available
            $this->redirect_to_legacy( 'quiz' );
        }
    }
    
    /**
     * Render results page
     * 
     * @return void
     */
    public function render_results_page(): void {
        $controller_class = 'MoneyQuiz\\Admin\\Controllers\\ResultsController';
        
        if ( class_exists( $controller_class ) ) {
            $controller = new $controller_class();
            $controller->index();
        } else {
            $this->redirect_to_legacy( 'results' );
        }
    }
    
    /**
     * Render archetypes page
     * 
     * @return void
     */
    public function render_archetypes_page(): void {
        $controller_class = 'MoneyQuiz\\Admin\\Controllers\\ArchetypeController';
        
        if ( class_exists( $controller_class ) ) {
            $controller = new $controller_class();
            $controller->index();
        } else {
            $this->redirect_to_legacy( 'archetypes' );
        }
    }
    
    /**
     * Render prospects page
     * 
     * @return void
     */
    public function render_prospects_page(): void {
        $controller_class = 'MoneyQuiz\\Admin\\Controllers\\ProspectsController';
        
        if ( class_exists( $controller_class ) ) {
            $controller = new $controller_class();
            $controller->index();
        } else {
            $this->redirect_to_legacy( 'prospects' );
        }
    }
    
    /**
     * Render settings page
     * 
     * @return void
     */
    public function render_settings_page(): void {
        $controller_class = 'MoneyQuiz\\Admin\\Controllers\\SettingsController';
        
        if ( class_exists( $controller_class ) ) {
            $controller = new $controller_class();
            $controller->index();
        } else {
            $this->redirect_to_legacy( 'settings' );
        }
    }
    
    /**
     * Render basic dashboard
     * 
     * @return void
     */
    private function render_basic_dashboard(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div class="money-quiz-dashboard">
                <div class="dashboard-widgets">
                    <?php $this->render_stats_widget(); ?>
                    <?php $this->render_recent_results_widget(); ?>
                    <?php $this->render_quick_actions_widget(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render stats widget
     * 
     * @return void
     */
    private function render_stats_widget(): void {
        global $wpdb;
        
        // Get basic stats
        $total_results = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_results" );
        $total_prospects = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_prospects" );
        
        // Check legacy tables too
        if ( ! $total_results ) {
            $total_results = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mq_results" );
        }
        ?>
        <div class="dashboard-widget">
            <h2><?php _e( 'Quick Stats', 'money-quiz' ); ?></h2>
            <ul>
                <li><?php printf( __( 'Total Results: %d', 'money-quiz' ), $total_results ); ?></li>
                <li><?php printf( __( 'Total Prospects: %d', 'money-quiz' ), $total_prospects ); ?></li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Render recent results widget
     * 
     * @return void
     */
    private function render_recent_results_widget(): void {
        ?>
        <div class="dashboard-widget">
            <h2><?php _e( 'Recent Results', 'money-quiz' ); ?></h2>
            <p><?php _e( 'No recent results to display.', 'money-quiz' ); ?></p>
        </div>
        <?php
    }
    
    /**
     * Render quick actions widget
     * 
     * @return void
     */
    private function render_quick_actions_widget(): void {
        ?>
        <div class="dashboard-widget">
            <h2><?php _e( 'Quick Actions', 'money-quiz' ); ?></h2>
            <p>
                <a href="<?php echo admin_url( 'admin.php?page=money-quiz-quizzes&action=new' ); ?>" 
                   class="button button-primary">
                    <?php _e( 'Create New Quiz', 'money-quiz' ); ?>
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=money-quiz-results' ); ?>" 
                   class="button">
                    <?php _e( 'View Results', 'money-quiz' ); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Redirect to legacy page
     * 
     * @param string $page Page identifier
     * @return void
     */
    private function redirect_to_legacy( string $page ): void {
        $legacy_pages = [
            'quiz' => 'admin.php?page=moneyquiz',
            'results' => 'admin.php?page=moneyquiz&mq_pages=User',
            'archetypes' => 'admin.php?page=moneyquiz&mq_pages=Archetype',
            'prospects' => 'admin.php?page=moneyquiz&mq_pages=User',
            'settings' => 'admin.php?page=moneyquiz&mq_pages=Setting',
        ];
        
        if ( isset( $legacy_pages[ $page ] ) ) {
            wp_redirect( admin_url( $legacy_pages[ $page ] ) );
            exit;
        }
        
        // Default message if no legacy page
        echo '<div class="wrap"><h1>' . __( 'Page Not Found', 'money-quiz' ) . '</h1>';
        echo '<p>' . __( 'This page is not yet available.', 'money-quiz' ) . '</p></div>';
    }
    
    /**
     * Check user capability for menu access
     * 
     * @param string $capability Required capability
     * @return bool
     */
    public function check_capability( string $capability = 'manage_options' ): bool {
        return current_user_can( $capability );
    }
}