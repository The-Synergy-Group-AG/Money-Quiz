<?php
/**
 * React Authentication Provider
 * 
 * @package MoneyQuiz\React
 * @version 1.0.0
 */

namespace MoneyQuiz\React;

/**
 * Auth Provider
 */
class AuthProvider {
    
    /**
     * Generate auth context
     */
    public static function generateAuthContext() {
        return <<<'JS'
// Authentication Context
import React, { createContext, useContext, useState, useEffect } from 'react';
import api from './api-client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        checkAuth();
    }, []);

    const checkAuth = async () => {
        try {
            const response = await api.getCurrentUser();
            setUser(response.data);
        } catch (err) {
            setError(err.message);
            setUser(null);
        } finally {
            setLoading(false);
        }
    };

    const login = async (credentials) => {
        try {
            const response = await api.login(credentials);
            setUser(response.data.user);
            return response;
        } catch (err) {
            setError(err.message);
            throw err;
        }
    };

    const logout = async () => {
        try {
            await api.logout();
            setUser(null);
        } catch (err) {
            setError(err.message);
        }
    };

    const value = {
        user,
        loading,
        error,
        isAuthenticated: !!user,
        login,
        logout,
        checkAuth
    };

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within AuthProvider');
    }
    return context;
}

// Protected Route Component
export function ProtectedRoute({ children, requiredCapability }) {
    const { user, loading, isAuthenticated } = useAuth();

    if (loading) {
        return <div>Loading...</div>;
    }

    if (!isAuthenticated) {
        return <div>Please log in to continue.</div>;
    }

    if (requiredCapability && !user?.capabilities?.[requiredCapability]) {
        return <div>You do not have permission to access this page.</div>;
    }

    return children;
}

// HOC for protected components
export function withAuth(Component, requiredCapability) {
    return function AuthenticatedComponent(props) {
        return (
            <ProtectedRoute requiredCapability={requiredCapability}>
                <Component {...props} />
            </ProtectedRoute>
        );
    };
}
JS;
    }
    
    /**
     * Generate permission helpers
     */
    public static function generatePermissionHelpers() {
        return <<<'JS'
// Permission Helpers
export const permissions = {
    canCreateQuiz: (user) => user?.capabilities?.can_create_quiz || false,
    canEditQuiz: (user, quiz) => {
        if (!user) return false;
        if (user.capabilities?.manage_options) return true;
        return quiz?.created_by === user.id;
    },
    canDeleteQuiz: (user, quiz) => {
        if (!user) return false;
        if (user.capabilities?.manage_options) return true;
        return quiz?.created_by === user.id;
    },
    canViewResults: (user) => user?.capabilities?.can_view_all_results || false,
    isAdmin: (user) => user?.capabilities?.manage_options || false
};

// Permission Hook
export function usePermission(permission, ...args) {
    const { user } = useAuth();
    
    if (typeof permissions[permission] === 'function') {
        return permissions[permission](user, ...args);
    }
    
    return false;
}

// Permission Component
export function Can({ do: permission, on, children, fallback = null }) {
    const canPerform = usePermission(permission, on);
    
    return canPerform ? children : fallback;
}
JS;
    }
    
    /**
     * Register auth provider
     */
    public static function register() {
        add_action('wp_ajax_money_quiz_check_auth', [__CLASS__, 'ajaxCheckAuth']);
        add_action('wp_ajax_nopriv_money_quiz_check_auth', [__CLASS__, 'ajaxCheckAuthNoPriv']);
    }
    
    /**
     * AJAX auth check
     */
    public static function ajaxCheckAuth() {
        $user = wp_get_current_user();
        
        if ($user->ID) {
            wp_send_json_success([
                'user' => [
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'display_name' => $user->display_name,
                    'email' => $user->user_email,
                    'capabilities' => [
                        'can_create_quiz' => user_can($user, 'edit_posts'),
                        'can_view_all_results' => user_can($user, 'manage_options'),
                        'manage_options' => user_can($user, 'manage_options')
                    ]
                ]
            ]);
        } else {
            wp_send_json_error('Not authenticated', 401);
        }
    }
    
    /**
     * No privilege auth check
     */
    public static function ajaxCheckAuthNoPriv() {
        wp_send_json_error('Not authenticated', 401);
    }
}