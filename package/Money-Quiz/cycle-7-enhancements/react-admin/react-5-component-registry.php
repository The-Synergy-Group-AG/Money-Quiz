<?php
/**
 * React Component Registry
 * 
 * @package MoneyQuiz\React
 * @version 1.0.0
 */

namespace MoneyQuiz\React;

/**
 * Component Registry
 */
class ComponentRegistry {
    
    /**
     * Generate component registry
     */
    public static function generateRegistry() {
        return <<<'JS'
// Component Registry for Dynamic Loading
import { lazy, Suspense } from 'react';

// Loading component
const Loading = () => <div className="mq-loading">Loading...</div>;

// Lazy load components
const components = {
    // Dashboard
    Dashboard: lazy(() => import('./components/Dashboard')),
    
    // Quiz Management
    QuizList: lazy(() => import('./components/quiz/QuizList')),
    QuizEditor: lazy(() => import('./components/quiz/QuizEditor')),
    QuizPreview: lazy(() => import('./components/quiz/QuizPreview')),
    QuestionEditor: lazy(() => import('./components/quiz/QuestionEditor')),
    
    // Results & Analytics
    ResultsList: lazy(() => import('./components/results/ResultsList')),
    ResultDetail: lazy(() => import('./components/results/ResultDetail')),
    Analytics: lazy(() => import('./components/analytics/Analytics')),
    
    // User Management
    UserList: lazy(() => import('./components/users/UserList')),
    UserProfile: lazy(() => import('./components/users/UserProfile')),
    Leaderboard: lazy(() => import('./components/users/Leaderboard')),
    
    // Settings
    Settings: lazy(() => import('./components/settings/Settings')),
    ApiKeys: lazy(() => import('./components/settings/ApiKeys')),
    Webhooks: lazy(() => import('./components/settings/Webhooks')),
    
    // Common Components
    Header: lazy(() => import('./components/common/Header')),
    Sidebar: lazy(() => import('./components/common/Sidebar')),
    Footer: lazy(() => import('./components/common/Footer'))
};

// Component wrapper with error boundary
class ComponentWrapper extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true };
    }

    componentDidCatch(error, errorInfo) {
        console.error('Component Error:', error, errorInfo);
    }

    render() {
        if (this.state.hasError) {
            return (
                <div className="mq-error">
                    <h3>Something went wrong</h3>
                    <button onClick={() => this.setState({ hasError: false })}>
                        Try Again
                    </button>
                </div>
            );
        }

        return this.props.children;
    }
}

// Get component by name
export function getComponent(name) {
    const Component = components[name];
    
    if (!Component) {
        console.error(`Component "${name}" not found`);
        return () => <div>Component not found: {name}</div>;
    }

    return (props) => (
        <ComponentWrapper>
            <Suspense fallback={<Loading />}>
                <Component {...props} />
            </Suspense>
        </ComponentWrapper>
    );
}

// Register custom component
export function registerComponent(name, component) {
    components[name] = lazy(() => Promise.resolve({ default: component }));
}

// Get all registered components
export function getRegisteredComponents() {
    return Object.keys(components);
}

// Component factory
export function createComponent(type, props = {}) {
    const Component = getComponent(type);
    return <Component key={props.key || type} {...props} />;
}

// Batch component loader
export async function preloadComponents(componentNames) {
    const promises = componentNames.map(name => {
        const component = components[name];
        return component ? component._ctor() : Promise.resolve();
    });
    
    await Promise.all(promises);
}
JS;
    }
    
    /**
     * Generate base components
     */
    public static function generateBaseComponents() {
        return [
            'Dashboard' => <<<'JS'
// Dashboard Component
import React from 'react';
import { useQuizzes } from '../../hooks/api-hooks';

export default function Dashboard() {
    const { quizzes, loading } = useQuizzes({ per_page: 5 });
    
    return (
        <div className="mq-dashboard">
            <h1>Money Quiz Dashboard</h1>
            {loading ? (
                <p>Loading...</p>
            ) : (
                <div className="mq-stats">
                    <div className="stat-card">
                        <h3>Total Quizzes</h3>
                        <p>{quizzes.length}</p>
                    </div>
                </div>
            )}
        </div>
    );
}
JS,
            'QuizList' => <<<'JS'
// Quiz List Component
import React from 'react';
import { Link } from 'react-router-dom';
import { useQuizzes } from '../../hooks/api-hooks';

export default function QuizList() {
    const { quizzes, loading, error } = useQuizzes();
    
    if (loading) return <div>Loading quizzes...</div>;
    if (error) return <div>Error: {error}</div>;
    
    return (
        <div className="mq-quiz-list">
            <h2>Quizzes</h2>
            <Link to="/quiz/new" className="button">Create New Quiz</Link>
            
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {quizzes.map(quiz => (
                        <tr key={quiz.id}>
                            <td>{quiz.title}</td>
                            <td>{quiz.status}</td>
                            <td>{new Date(quiz.created_at).toLocaleDateString()}</td>
                            <td>
                                <Link to={`/quiz/${quiz.id}`}>Edit</Link>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
JS
        ];
    }
    
    /**
     * Register components in WordPress
     */
    public static function register() {
        add_action('admin_footer', [__CLASS__, 'printComponentRegistry']);
    }
    
    /**
     * Print component registry
     */
    public static function printComponentRegistry() {
        if (!wp_script_is('money-quiz-react-admin', 'enqueued')) {
            return;
        }
        
        ?>
        <script>
        window.MoneyQuizComponents = window.MoneyQuizComponents || {};
        window.MoneyQuizComponents.registry = <?php echo json_encode(self::getComponentList()); ?>;
        </script>
        <?php
    }
    
    /**
     * Get component list
     */
    private static function getComponentList() {
        return [
            'dashboard' => ['name' => 'Dashboard', 'path' => '/'],
            'quizzes' => ['name' => 'QuizList', 'path' => '/quizzes'],
            'quiz-editor' => ['name' => 'QuizEditor', 'path' => '/quiz/:id'],
            'results' => ['name' => 'ResultsList', 'path' => '/results'],
            'analytics' => ['name' => 'Analytics', 'path' => '/analytics'],
            'settings' => ['name' => 'Settings', 'path' => '/settings']
        ];
    }
}