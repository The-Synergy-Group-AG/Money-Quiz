<?php
/**
 * Jest Configuration Generator
 * 
 * @package MoneyQuiz\Testing
 * @version 1.0.0
 */

namespace MoneyQuiz\Testing;

/**
 * Jest Config
 */
class JestConfig {
    
    /**
     * Generate Jest configuration
     */
    public static function generate() {
        return [
            'preset' => '@wordpress/jest-preset-default',
            'rootDir' => '../../../',
            'testMatch' => [
                '<rootDir>/assets/js/**/__tests__/**/*.js',
                '<rootDir>/assets/js/**/*.test.js',
                '<rootDir>/cycle-7-enhancements/react-admin/src/**/*.test.js'
            ],
            'moduleNameMapper' => [
                '^@/(.*)$' => '<rootDir>/assets/js/$1',
                '^@components/(.*)$' => '<rootDir>/assets/js/components/$1',
                '^@utils/(.*)$' => '<rootDir>/assets/js/utils/$1'
            ],
            'setupFilesAfterEnv' => [
                '<rootDir>/tests/js/setup.js'
            ],
            'coverageDirectory' => '<rootDir>/coverage',
            'collectCoverageFrom' => [
                'assets/js/**/*.{js,jsx}',
                '!assets/js/**/*.test.js',
                '!assets/js/vendor/**'
            ],
            'coverageThreshold' => [
                'global' => [
                    'branches' => 70,
                    'functions' => 70,
                    'lines' => 70,
                    'statements' => 70
                ]
            ]
        ];
    }
    
    /**
     * Generate test setup file
     */
    public static function generateSetup() {
        return <<<'JS'
// Jest Setup File
import '@testing-library/jest-dom';
import { configure } from '@testing-library/react';

// Configure testing library
configure({ testIdAttribute: 'data-testid' });

// Mock WordPress globals
global.wp = {
    element: {
        createElement: jest.fn(),
        render: jest.fn()
    },
    components: {
        Button: jest.fn(),
        TextControl: jest.fn()
    },
    apiFetch: jest.fn(),
    data: {
        select: jest.fn(),
        dispatch: jest.fn()
    }
};

// Mock window.moneyQuizAdmin
global.moneyQuizAdmin = {
    apiUrl: 'http://localhost/wp-json/money-quiz/v1',
    nonce: 'test-nonce',
    userId: 1,
    adminUrl: 'http://localhost/wp-admin/'
};

// Mock fetch
global.fetch = jest.fn(() =>
    Promise.resolve({
        ok: true,
        json: () => Promise.resolve({})
    })
);

// Suppress console errors in tests
const originalError = console.error;
beforeAll(() => {
    console.error = (...args) => {
        if (
            typeof args[0] === 'string' &&
            args[0].includes('Warning: ReactDOM.render')
        ) {
            return;
        }
        originalError.call(console, ...args);
    };
});

afterAll(() => {
    console.error = originalError;
});
JS;
    }
    
    /**
     * Generate sample test
     */
    public static function generateSampleTest() {
        return <<<'JS'
// Sample Component Test
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { QuizList } from '../QuizList';

describe('QuizList Component', () => {
    const mockQuizzes = [
        { id: 1, title: 'Test Quiz 1', status: 'published' },
        { id: 2, title: 'Test Quiz 2', status: 'draft' }
    ];

    beforeEach(() => {
        fetch.mockClear();
    });

    test('renders quiz list', () => {
        render(<QuizList quizzes={mockQuizzes} />);
        
        expect(screen.getByText('Test Quiz 1')).toBeInTheDocument();
        expect(screen.getByText('Test Quiz 2')).toBeInTheDocument();
    });

    test('filters quizzes by status', () => {
        render(<QuizList quizzes={mockQuizzes} />);
        
        const filterSelect = screen.getByLabelText('Filter by status');
        fireEvent.change(filterSelect, { target: { value: 'published' } });
        
        expect(screen.getByText('Test Quiz 1')).toBeInTheDocument();
        expect(screen.queryByText('Test Quiz 2')).not.toBeInTheDocument();
    });

    test('handles quiz deletion', async () => {
        const onDelete = jest.fn();
        render(<QuizList quizzes={mockQuizzes} onDelete={onDelete} />);
        
        const deleteButton = screen.getAllByText('Delete')[0];
        fireEvent.click(deleteButton);
        
        expect(onDelete).toHaveBeenCalledWith(1);
    });
});

// API Hook Test
import { renderHook, waitFor } from '@testing-library/react';
import { useQuizzes } from '../hooks/useQuizzes';

describe('useQuizzes Hook', () => {
    beforeEach(() => {
        fetch.mockClear();
    });

    test('fetches quizzes successfully', async () => {
        const mockData = {
            data: {
                items: mockQuizzes,
                total: 2
            }
        };

        fetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockData
        });

        const { result } = renderHook(() => useQuizzes());

        expect(result.current.loading).toBe(true);

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.quizzes).toEqual(mockQuizzes);
        expect(result.current.error).toBe(null);
    });

    test('handles fetch error', async () => {
        fetch.mockRejectedValueOnce(new Error('Network error'));

        const { result } = renderHook(() => useQuizzes());

        await waitFor(() => {
            expect(result.current.loading).toBe(false);
        });

        expect(result.current.error).toBe('Network error');
        expect(result.current.quizzes).toEqual([]);
    });
});
JS;
    }
    
    /**
     * Generate package.json scripts
     */
    public static function getScripts() {
        return [
            'test' => 'jest',
            'test:watch' => 'jest --watch',
            'test:coverage' => 'jest --coverage',
            'test:update' => 'jest -u',
            'test:debug' => 'node --inspect-brk ./node_modules/.bin/jest --runInBand'
        ];
    }
    
    /**
     * Generate GitHub Actions workflow
     */
    public static function generateGitHubActions() {
        return <<<'YAML'
name: JavaScript Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        node-version: [14.x, 16.x, 18.x]
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Use Node.js ${{ matrix.node-version }}
      uses: actions/setup-node@v3
      with:
        node-version: ${{ matrix.node-version }}
        cache: 'npm'
    
    - name: Install dependencies
      run: npm ci
    
    - name: Run tests
      run: npm test
    
    - name: Generate coverage report
      run: npm run test:coverage
    
    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage/lcov.info
        flags: javascript
        name: codecov-umbrella
YAML;
    }
    
    /**
     * Write configuration files
     */
    public static function writeConfig($plugin_dir) {
        // Jest config
        $jest_config = self::generate();
        file_put_contents(
            $plugin_dir . '/jest.config.js',
            'module.exports = ' . json_encode($jest_config, JSON_PRETTY_PRINT)
        );
        
        // Test setup
        $setup_dir = $plugin_dir . '/tests/js';
        if (!file_exists($setup_dir)) {
            wp_mkdir_p($setup_dir);
        }
        file_put_contents($setup_dir . '/setup.js', self::generateSetup());
        
        // Sample test
        $test_dir = $plugin_dir . '/assets/js/__tests__';
        if (!file_exists($test_dir)) {
            wp_mkdir_p($test_dir);
        }
        file_put_contents($test_dir . '/QuizList.test.js', self::generateSampleTest());
        
        // GitHub Actions
        $github_dir = $plugin_dir . '/.github/workflows';
        if (!file_exists($github_dir)) {
            wp_mkdir_p($github_dir);
        }
        file_put_contents($github_dir . '/js-tests.yml', self::generateGitHubActions());
    }
}