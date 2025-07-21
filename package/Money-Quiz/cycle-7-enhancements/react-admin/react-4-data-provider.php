<?php
/**
 * React Data Provider
 * 
 * @package MoneyQuiz\React
 * @version 1.0.0
 */

namespace MoneyQuiz\React;

/**
 * Data Provider
 */
class DataProvider {
    
    /**
     * Generate data provider
     */
    public static function generateDataProvider() {
        return <<<'JS'
// Data Provider with Caching
import { useState, useCallback } from 'react';

class DataCache {
    constructor(ttl = 5 * 60 * 1000) { // 5 minutes default
        this.cache = new Map();
        this.ttl = ttl;
    }

    set(key, data) {
        this.cache.set(key, {
            data,
            timestamp: Date.now()
        });
    }

    get(key) {
        const item = this.cache.get(key);
        if (!item) return null;
        
        if (Date.now() - item.timestamp > this.ttl) {
            this.cache.delete(key);
            return null;
        }
        
        return item.data;
    }

    invalidate(pattern) {
        if (!pattern) {
            this.cache.clear();
            return;
        }
        
        for (const key of this.cache.keys()) {
            if (key.includes(pattern)) {
                this.cache.delete(key);
            }
        }
    }
}

// Global cache instance
const dataCache = new DataCache();

// Data Provider Hook
export function useDataProvider() {
    const [loading, setLoading] = useState({});
    const [errors, setErrors] = useState({});

    const fetchData = useCallback(async (key, fetcher, options = {}) => {
        const { cache = true, force = false } = options;
        
        // Check cache
        if (cache && !force) {
            const cached = dataCache.get(key);
            if (cached) return cached;
        }

        // Set loading state
        setLoading(prev => ({ ...prev, [key]: true }));
        setErrors(prev => ({ ...prev, [key]: null }));

        try {
            const data = await fetcher();
            
            // Cache the result
            if (cache) {
                dataCache.set(key, data);
            }
            
            return data;
        } catch (error) {
            setErrors(prev => ({ ...prev, [key]: error }));
            throw error;
        } finally {
            setLoading(prev => ({ ...prev, [key]: false }));
        }
    }, []);

    const invalidateCache = useCallback((pattern) => {
        dataCache.invalidate(pattern);
    }, []);

    return {
        fetchData,
        invalidateCache,
        loading,
        errors,
        isLoading: (key) => loading[key] || false,
        getError: (key) => errors[key] || null
    };
}

// Resource Hook
export function useResource(resourceName, id = null) {
    const { fetchData, invalidateCache, isLoading, getError } = useDataProvider();
    const [data, setData] = useState(null);

    const key = id ? `${resourceName}:${id}` : resourceName;

    const load = useCallback(async (options = {}) => {
        try {
            const result = await fetchData(
                key,
                async () => {
                    const api = (await import('./api-client')).default;
                    
                    switch (resourceName) {
                        case 'quiz':
                            return id ? api.getQuiz(id) : api.getQuizzes();
                        case 'results':
                            return api.getResults(options.params);
                        case 'user':
                            return id ? api.getUserStats(id) : api.getCurrentUser();
                        default:
                            throw new Error(`Unknown resource: ${resourceName}`);
                    }
                },
                options
            );
            
            setData(result.data);
            return result.data;
        } catch (error) {
            console.error(`Error loading ${resourceName}:`, error);
            throw error;
        }
    }, [fetchData, key, resourceName, id]);

    const refresh = useCallback(() => {
        invalidateCache(key);
        return load({ force: true });
    }, [invalidateCache, key, load]);

    return {
        data,
        loading: isLoading(key),
        error: getError(key),
        load,
        refresh
    };
}

// Mutation Hook
export function useMutation(mutationFn) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const { invalidateCache } = useDataProvider();

    const mutate = useCallback(async (...args) => {
        setLoading(true);
        setError(null);

        try {
            const result = await mutationFn(...args);
            
            // Invalidate related cache
            if (result.invalidate) {
                invalidateCache(result.invalidate);
            }
            
            return result;
        } catch (err) {
            setError(err);
            throw err;
        } finally {
            setLoading(false);
        }
    }, [mutationFn, invalidateCache]);

    return {
        mutate,
        loading,
        error
    };
}
JS;
    }
    
    /**
     * Generate store configuration
     */
    public static function generateStore() {
        return <<<'JS'
// Redux-style Store (optional)
import { createContext, useContext, useReducer } from 'react';

const StoreContext = createContext(null);

const initialState = {
    quizzes: [],
    currentQuiz: null,
    results: [],
    user: null,
    ui: {
        sidebarOpen: true,
        theme: 'light'
    }
};

function reducer(state, action) {
    switch (action.type) {
        case 'SET_QUIZZES':
            return { ...state, quizzes: action.payload };
        case 'SET_CURRENT_QUIZ':
            return { ...state, currentQuiz: action.payload };
        case 'UPDATE_QUIZ':
            return {
                ...state,
                quizzes: state.quizzes.map(q => 
                    q.id === action.payload.id ? action.payload : q
                )
            };
        case 'SET_USER':
            return { ...state, user: action.payload };
        case 'TOGGLE_SIDEBAR':
            return {
                ...state,
                ui: { ...state.ui, sidebarOpen: !state.ui.sidebarOpen }
            };
        default:
            return state;
    }
}

export function StoreProvider({ children }) {
    const [state, dispatch] = useReducer(reducer, initialState);

    return (
        <StoreContext.Provider value={{ state, dispatch }}>
            {children}
        </StoreContext.Provider>
    );
}

export function useStore() {
    const context = useContext(StoreContext);
    if (!context) {
        throw new Error('useStore must be used within StoreProvider');
    }
    return context;
}
JS;
    }
}