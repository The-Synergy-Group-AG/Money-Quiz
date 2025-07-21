<?php
/**
 * React API Client Configuration
 * 
 * @package MoneyQuiz\React
 * @version 1.0.0
 */

namespace MoneyQuiz\React;

/**
 * API Client
 */
class ApiClient {
    
    /**
     * Generate API client code
     */
    public static function generateClient() {
        return <<<'JS'
// Money Quiz API Client
class MoneyQuizAPI {
    constructor() {
        this.baseURL = window.moneyQuizAdmin.apiUrl;
        this.nonce = window.moneyQuizAdmin.nonce;
    }

    // Request helper
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const config = {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.nonce,
                ...options.headers
            }
        };

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'API request failed');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // Quiz methods
    getQuizzes(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/quizzes?${query}`);
    }

    getQuiz(id) {
        return this.request(`/quizzes/${id}`);
    }

    createQuiz(data) {
        return this.request('/quizzes', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    updateQuiz(id, data) {
        return this.request(`/quizzes/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    deleteQuiz(id) {
        return this.request(`/quizzes/${id}`, {
            method: 'DELETE'
        });
    }

    // Result methods
    getResults(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/results?${query}`);
    }

    // User methods
    getCurrentUser() {
        return this.request('/users/me');
    }

    getUserStats(userId) {
        return this.request(`/users/${userId}/stats`);
    }

    getLeaderboard(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/users/leaderboard?${query}`);
    }
}

// Export singleton instance
export default new MoneyQuizAPI();
JS;
    }
    
    /**
     * Generate React hooks
     */
    public static function generateHooks() {
        return <<<'JS'
// React Hooks for API
import { useState, useEffect } from 'react';
import api from './api-client';

// Quiz hooks
export function useQuizzes(params = {}) {
    const [quizzes, setQuizzes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        fetchQuizzes();
    }, [JSON.stringify(params)]);

    const fetchQuizzes = async () => {
        try {
            setLoading(true);
            const response = await api.getQuizzes(params);
            setQuizzes(response.data.items || []);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    return { quizzes, loading, error, refetch: fetchQuizzes };
}

export function useQuiz(id) {
    const [quiz, setQuiz] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (id) fetchQuiz();
    }, [id]);

    const fetchQuiz = async () => {
        try {
            setLoading(true);
            const response = await api.getQuiz(id);
            setQuiz(response.data);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    return { quiz, loading, error, refetch: fetchQuiz };
}

// User hooks
export function useCurrentUser() {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.getCurrentUser()
            .then(response => setUser(response.data))
            .catch(console.error)
            .finally(() => setLoading(false));
    }, []);

    return { user, loading };
}
JS;
    }
    
    /**
     * Register API client script
     */
    public static function registerScript() {
        $client_code = self::generateClient();
        $hooks_code = self::generateHooks();
        
        // Save to files for build process
        $src_dir = plugin_dir_path(__FILE__) . 'src/';
        if (!file_exists($src_dir)) {
            wp_mkdir_p($src_dir);
        }
        
        file_put_contents($src_dir . 'api-client.js', $client_code);
        file_put_contents($src_dir . 'api-hooks.js', $hooks_code);
    }
}