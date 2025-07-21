<?php
/**
 * CI/CD Integration
 * 
 * @package MoneyQuiz\Testing
 * @version 1.0.0
 */

namespace MoneyQuiz\Testing;

/**
 * CI Integration
 */
class CIIntegration {
    
    /**
     * Generate GitHub Actions workflow
     */
    public static function generateGitHubActions() {
        return <<<'YAML'
name: CI/CD Pipeline

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test-php:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: ['7.4', '8.0', '8.1']
        wordpress-version: ['5.9', '6.0', 'latest']
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: mbstring, intl, mysql
        coverage: xdebug
    
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
    
    - name: Setup WordPress
      run: |
        bash bin/install-wp-tests.sh wordpress_test root root localhost ${{ matrix.wordpress-version }}
    
    - name: Run PHPUnit
      run: vendor/bin/phpunit --coverage-clover coverage.xml
    
    - name: Upload coverage
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml
        flags: php

  test-js:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '16'
        cache: 'npm'
    
    - name: Install dependencies
      run: npm ci
    
    - name: Run tests
      run: npm test -- --coverage
    
    - name: Upload coverage
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage/lcov.info
        flags: javascript

  security-scan:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Run security scan
      uses: aquasecurity/trivy-action@master
      with:
        scan-type: 'fs'
        scan-ref: '.'
        format: 'sarif'
        output: 'trivy-results.sarif'
    
    - name: Upload results
      uses: github/codeql-action/upload-sarif@v2
      with:
        sarif_file: 'trivy-results.sarif'

  code-quality:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.0'
    
    - name: Install dependencies
      run: composer install
    
    - name: Run PHPCS
      run: vendor/bin/phpcs --standard=WordPress-Extra .
    
    - name: Run PHPStan
      run: vendor/bin/phpstan analyse --level=5

  deploy:
    needs: [test-php, test-js, security-scan, code-quality]
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Build plugin
      run: |
        npm ci
        npm run build
        composer install --no-dev --optimize-autoloader
    
    - name: Create artifact
      run: |
        zip -r money-quiz.zip . -x ".*" -x "node_modules/*" -x "tests/*"
    
    - name: Upload artifact
      uses: actions/upload-artifact@v3
      with:
        name: money-quiz-plugin
        path: money-quiz.zip
YAML;
    }
    
    /**
     * Generate GitLab CI config
     */
    public static function generateGitLabCI() {
        return <<<'YAML'
stages:
  - test
  - security
  - quality
  - deploy

variables:
  MYSQL_DATABASE: wordpress_test
  MYSQL_ROOT_PASSWORD: root
  WP_VERSION: latest

test:php:
  stage: test
  image: php:8.0
  services:
    - mysql:5.7
  before_script:
    - apt-get update && apt-get install -y git unzip
    - docker-php-ext-install mysqli pdo_mysql
    - curl -sS https://getcomposer.org/installer | php
    - php composer.phar install
  script:
    - vendor/bin/phpunit --coverage-text --colors=never
  coverage: '/^\s*Lines:\s*\d+.\d+\%/'
  artifacts:
    reports:
      coverage_report:
        coverage_format: cobertura
        path: coverage.xml

test:js:
  stage: test
  image: node:16
  before_script:
    - npm ci
  script:
    - npm test -- --coverage
  coverage: '/All files[^|]*\|[^|]*\s+([\d\.]+)/'

security:scan:
  stage: security
  image: aquasec/trivy
  script:
    - trivy fs --exit-code 1 --severity HIGH,CRITICAL .

quality:phpcs:
  stage: quality
  image: php:8.0
  before_script:
    - composer install
  script:
    - vendor/bin/phpcs --standard=WordPress-Extra .

deploy:production:
  stage: deploy
  only:
    - main
  script:
    - echo "Deploy to production"
YAML;
    }
    
    /**
     * Generate Jenkins pipeline
     */
    public static function generateJenkinsPipeline() {
        return <<<'GROOVY'
pipeline {
    agent any
    
    environment {
        WP_TESTS_DIR = '/tmp/wordpress-tests-lib'
        WP_CORE_DIR = '/tmp/wordpress'
    }
    
    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }
        
        stage('Install Dependencies') {
            parallel {
                stage('PHP Dependencies') {
                    steps {
                        sh 'composer install'
                    }
                }
                stage('JS Dependencies') {
                    steps {
                        sh 'npm ci'
                    }
                }
            }
        }
        
        stage('Test') {
            parallel {
                stage('PHP Tests') {
                    steps {
                        sh 'vendor/bin/phpunit --coverage-clover coverage.xml'
                        publishCoverage adapters: [coberturaAdapter('coverage.xml')]
                    }
                }
                stage('JS Tests') {
                    steps {
                        sh 'npm test'
                    }
                }
            }
        }
        
        stage('Code Quality') {
            steps {
                sh 'vendor/bin/phpcs --standard=WordPress-Extra . || true'
                sh 'vendor/bin/phpstan analyse --level=5 || true'
            }
        }
        
        stage('Security Scan') {
            steps {
                sh 'trivy fs .'
            }
        }
        
        stage('Build') {
            when {
                branch 'main'
            }
            steps {
                sh 'npm run build'
                sh 'composer install --no-dev --optimize-autoloader'
                sh 'zip -r money-quiz.zip . -x ".*" -x "node_modules/*" -x "tests/*"'
                archiveArtifacts artifacts: 'money-quiz.zip'
            }
        }
    }
    
    post {
        always {
            junit '**/test-results/*.xml'
            cleanWs()
        }
    }
}
GROOVY;
    }
    
    /**
     * Generate CircleCI config
     */
    public static function generateCircleCI() {
        return <<<'YAML'
version: 2.1

orbs:
  php: circleci/php@1.1.0
  node: circleci/node@5.0.0

jobs:
  test-php:
    docker:
      - image: cimg/php:8.0
      - image: cimg/mysql:5.7
        environment:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
    steps:
      - checkout
      - php/install-packages
      - run:
          name: Setup WordPress
          command: bash bin/install-wp-tests.sh
      - run:
          name: Run tests
          command: vendor/bin/phpunit --coverage-text
      - store_test_results:
          path: test-results

  test-js:
    docker:
      - image: cimg/node:16.0
    steps:
      - checkout
      - node/install-packages
      - run:
          name: Run tests
          command: npm test
      - store_test_results:
          path: test-results

workflows:
  test-and-deploy:
    jobs:
      - test-php
      - test-js
      - hold:
          type: approval
          requires:
            - test-php
            - test-js
          filters:
            branches:
              only: main
YAML;
    }
    
    /**
     * Generate Travis CI config
     */
    public static function generateTravisCI() {
        return <<<'YAML'
language: php

php:
  - 7.4
  - 8.0
  - 8.1

env:
  - WP_VERSION=latest WP_MULTISITE=0
  - WP_VERSION=5.9 WP_MULTISITE=0

matrix:
  include:
    - php: 8.0
      env: WP_VERSION=latest WP_MULTISITE=1

services:
  - mysql

before_install:
  - nvm install 16
  - npm install -g npm@latest

install:
  - composer install
  - npm ci

before_script:
  - bash bin/install-wp-tests.sh wordpress_test root '' localhost $WP_VERSION

script:
  - vendor/bin/phpunit --coverage-clover=coverage.xml
  - npm test
  - vendor/bin/phpcs --standard=WordPress-Extra .

after_success:
  - bash <(curl -s https://codecov.io/bash)
YAML;
    }
}