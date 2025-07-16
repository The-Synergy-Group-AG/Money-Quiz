<?php
/**
 * React Route Configuration
 * 
 * @package MoneyQuiz\React
 * @version 1.0.0
 */

namespace MoneyQuiz\React;

/**
 * Route Configuration
 */
class RouteConfig {
    
    /**
     * Generate router configuration
     */
    public static function generateRouter() {
        return <<<'JS'
// Router Configuration
import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { getComponent } from './component-registry';
import { ProtectedRoute } from './auth-provider';

// Layout wrapper
function AdminLayout({ children }) {
    const Header = getComponent('Header');
    const Sidebar = getComponent('Sidebar');
    const Footer = getComponent('Footer');
    
    return (
        <div className="mq-admin-layout">
            <Header />
            <div className="mq-main">
                <Sidebar />
                <main className="mq-content">
                    {children}
                </main>
            </div>
            <Footer />
        </div>
    );
}

// Route configuration
const routes = [
    {
        path: '/',
        component: 'Dashboard',
        exact: true,
        title: 'Dashboard'
    },
    {
        path: '/quizzes',
        component: 'QuizList',
        title: 'Quizzes',
        capability: 'edit_posts'
    },
    {
        path: '/quiz/new',
        component: 'QuizEditor',
        title: 'Create Quiz',
        capability: 'edit_posts'
    },
    {
        path: '/quiz/:id',
        component: 'QuizEditor',
        title: 'Edit Quiz',
        capability: 'edit_posts'
    },
    {
        path: '/quiz/:id/preview',
        component: 'QuizPreview',
        title: 'Preview Quiz'
    },
    {
        path: '/results',
        component: 'ResultsList',
        title: 'Results',
        capability: 'edit_posts'
    },
    {
        path: '/results/:id',
        component: 'ResultDetail',
        title: 'Result Detail'
    },
    {
        path: '/analytics',
        component: 'Analytics',
        title: 'Analytics',
        capability: 'manage_options'
    },
    {
        path: '/users',
        component: 'UserList',
        title: 'Users',
        capability: 'manage_options'
    },
    {
        path: '/leaderboard',
        component: 'Leaderboard',
        title: 'Leaderboard'
    },
    {
        path: '/settings',
        component: 'Settings',
        title: 'Settings',
        capability: 'manage_options'
    }
];

// Router component
export default function AppRouter() {
    const basename = window.moneyQuizAdmin?.adminUrl 
        ? new URL(window.moneyQuizAdmin.adminUrl).pathname + 'admin.php?page=money-quiz'
        : '/';
    
    return (
        <BrowserRouter basename={basename}>
            <AdminLayout>
                <Routes>
                    {routes.map(route => {
                        const Component = getComponent(route.component);
                        
                        return (
                            <Route
                                key={route.path}
                                path={route.path}
                                element={
                                    route.capability ? (
                                        <ProtectedRoute requiredCapability={route.capability}>
                                            <Component />
                                        </ProtectedRoute>
                                    ) : (
                                        <Component />
                                    )
                                }
                            />
                        );
                    })}
                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
            </AdminLayout>
        </BrowserRouter>
    );
}

// Navigation hook
export function useNavigation() {
    return {
        routes: routes.filter(r => r.title),
        getRoute: (path) => routes.find(r => r.path === path),
        isActive: (path) => window.location.pathname.includes(path)
    };
}

// Breadcrumb component
export function Breadcrumbs() {
    const { pathname } = window.location;
    const segments = pathname.split('/').filter(Boolean);
    
    return (
        <nav className="mq-breadcrumbs">
            <a href="/">Home</a>
            {segments.map((segment, index) => {
                const path = '/' + segments.slice(0, index + 1).join('/');
                const route = routes.find(r => r.path === path);
                
                return (
                    <React.Fragment key={path}>
                        <span>/</span>
                        {route ? (
                            <a href={path}>{route.title}</a>
                        ) : (
                            <span>{segment}</span>
                        )}
                    </React.Fragment>
                );
            })}
        </nav>
    );
}
JS;
    }
    
    /**
     * Register admin pages
     */
    public static function registerAdminPages() {
        add_action('admin_menu', [__CLASS__, 'addAdminMenus']);
    }
    
    /**
     * Add admin menus
     */
    public static function addAdminMenus() {
        // Main menu
        add_menu_page(
            'Money Quiz',
            'Money Quiz',
            'read',
            'money-quiz',
            [__CLASS__, 'renderReactApp'],
            'dashicons-chart-pie',
            30
        );
        
        // Submenu items (handled by React Router)
        $submenus = [
            ['title' => 'Dashboard', 'slug' => 'money-quiz'],
            ['title' => 'Quizzes', 'slug' => 'money-quiz-quizzes', 'cap' => 'edit_posts'],
            ['title' => 'Results', 'slug' => 'money-quiz-results', 'cap' => 'edit_posts'],
            ['title' => 'Analytics', 'slug' => 'money-quiz-analytics', 'cap' => 'manage_options'],
            ['title' => 'Settings', 'slug' => 'money-quiz-settings', 'cap' => 'manage_options']
        ];
        
        foreach ($submenus as $submenu) {
            add_submenu_page(
                'money-quiz',
                $submenu['title'],
                $submenu['title'],
                $submenu['cap'] ?? 'read',
                $submenu['slug'],
                [__CLASS__, 'renderReactApp']
            );
        }
    }
    
    /**
     * Render React app
     */
    public static function renderReactApp() {
        echo '<div id="money-quiz-react-root"></div>';
    }
}