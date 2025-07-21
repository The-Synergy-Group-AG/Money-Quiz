<?php
/**
 * NLP Processing System Loader
 * 
 * @package MoneyQuiz\AI\NLP
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\NLP;

// Load NLP components
require_once __DIR__ . '/nlp-1-processor.php';
require_once __DIR__ . '/nlp-2-question-analyzer.php';

/**
 * NLP Manager
 */
class NLPManager {
    
    private static $instance = null;
    private $processor;
    private $analyzer;
    
    private function __construct() {
        $this->processor = NLPProcessor::getInstance();
        $this->analyzer = QuestionAnalyzer::getInstance();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize NLP system
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Add admin interface
        add_action('admin_menu', [$instance, 'addAdminMenu']);
        
        // Register REST endpoints
        add_action('rest_api_init', [$instance, 'registerEndpoints']);
        
        // Add question analysis on save
        add_action('money_quiz_question_saved', [$instance, 'analyzeQuestionOnSave'], 10, 2);
        
        // Add quiz analysis tools
        add_action('money_quiz_quiz_edit_sidebar', [$instance, 'addAnalysisTools']);
        
        // Register AJAX handlers
        add_action('wp_ajax_analyze_question_text', [$instance, 'ajaxAnalyzeQuestion']);
        add_action('wp_ajax_generate_similar_questions', [$instance, 'ajaxGenerateSimilar']);
        
        // Add filters
        add_filter('money_quiz_ai_nlp_processing_insights', [$instance, 'getInsights']);
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        add_submenu_page(
            'money-quiz-ai',
            'NLP Analysis',
            'NLP Analysis',
            'edit_posts',
            'money-quiz-nlp',
            [$instance, 'renderNLPPage']
        );
    }
    
    /**
     * Render NLP page
     */
    public function renderNLPPage() {
        ?>
        <div class="wrap">
            <h1>NLP Question Analysis</h1>
            
            <div class="card">
                <h2>Analyze Question Text</h2>
                <textarea id="nlp-question-input" rows="4" class="large-text" 
                    placeholder="Enter a question to analyze..."></textarea>
                <p>
                    <button class="button button-primary" onclick="analyzeQuestion()">
                        Analyze Question
                    </button>
                    <button class="button" onclick="generateSimilar()">
                        Generate Similar
                    </button>
                </p>
                <div id="nlp-analysis-results"></div>
            </div>
            
            <div class="card">
                <h2>Quiz Analysis</h2>
                <select id="quiz-selector">
                    <option value="">Select a quiz...</option>
                    <?php
                    $quizzes = $this->getQuizzes();
                    foreach ($quizzes as $quiz) {
                        echo '<option value="' . esc_attr($quiz->id) . '">' . 
                             esc_html($quiz->title) . '</option>';
                    }
                    ?>
                </select>
                <button class="button" onclick="analyzeQuiz()">Analyze Quiz</button>
                <div id="quiz-analysis-results"></div>
            </div>
        </div>
        
        <script>
        function analyzeQuestion() {
            const text = jQuery('#nlp-question-input').val();
            
            jQuery.post(ajaxurl, {
                action: 'analyze_question_text',
                text: text,
                nonce: '<?php echo wp_create_nonce('nlp_analysis'); ?>'
            }, function(response) {
                jQuery('#nlp-analysis-results').html(response.data.html);
            });
        }
        
        function generateSimilar() {
            const text = jQuery('#nlp-question-input').val();
            
            jQuery.post(ajaxurl, {
                action: 'generate_similar_questions',
                text: text,
                nonce: '<?php echo wp_create_nonce('nlp_analysis'); ?>'
            }, function(response) {
                jQuery('#nlp-analysis-results').html(response.data.html);
            });
        }
        
        function analyzeQuiz() {
            const quizId = jQuery('#quiz-selector').val();
            
            if (!quizId) return;
            
            // Would implement quiz analysis display
            alert('Analyzing quiz ' + quizId);
        }
        </script>
        <?php
    }
    
    /**
     * Register REST endpoints
     */
    public function registerEndpoints() {
        register_rest_route('money-quiz/v1', '/nlp/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'analyzeText'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
            'args' => [
                'text' => [
                    'required' => true,
                    'type' => 'string'
                ]
            ]
        ]);
        
        register_rest_route('money-quiz/v1', '/nlp/analyze-quiz', [
            'methods' => 'GET',
            'callback' => [$this, 'analyzeQuiz'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
            'args' => [
                'quiz_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        register_rest_route('money-quiz/v1', '/nlp/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generateQuestion'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);
    }
    
    /**
     * Analyze text endpoint
     */
    public function analyzeText($request) {
        $text = $request->get_param('text');
        $analysis = $this->processor->processQuestion($text);
        
        return [
            'success' => true,
            'data' => $analysis
        ];
    }
    
    /**
     * Analyze quiz endpoint
     */
    public function analyzeQuiz($request) {
        $quiz_id = $request->get_param('quiz_id');
        $analysis = $this->analyzer->analyzeQuizQuestions($quiz_id);
        
        return [
            'success' => true,
            'data' => $analysis
        ];
    }
    
    /**
     * Generate question endpoint
     */
    public function generateQuestion($request) {
        $topic = $request->get_param('topic') ?: 'financial';
        $difficulty = $request->get_param('difficulty') ?: 'medium';
        
        $question = $this->analyzer->generateQuestionFromTopic($topic, $difficulty);
        
        return [
            'success' => true,
            'data' => [
                'question' => $question,
                'topic' => $topic,
                'difficulty' => $difficulty
            ]
        ];
    }
    
    /**
     * Analyze question on save
     */
    public function analyzeQuestionOnSave($question_id, $question_data) {
        $analysis = $this->analyzer->analyzeQuestion((object)$question_data);
        
        // Store analysis
        update_post_meta($question_id, '_nlp_analysis', $analysis);
        
        // Alert on quality issues
        if (!empty($analysis['quality_issues'])) {
            $high_severity = array_filter($analysis['quality_issues'], function($issue) {
                return $issue['severity'] === 'high';
            });
            
            if (!empty($high_severity)) {
                set_transient('money_quiz_question_issues_' . $question_id, $high_severity, HOUR_IN_SECONDS);
            }
        }
    }
    
    /**
     * Add analysis tools to quiz editor
     */
    public function addAnalysisTools($quiz_id) {
        ?>
        <div class="quiz-nlp-tools">
            <h3>NLP Analysis</h3>
            <button type="button" class="button" onclick="analyzeAllQuestions(<?php echo $quiz_id; ?>)">
                Analyze All Questions
            </button>
            <button type="button" class="button" onclick="suggestImprovements(<?php echo $quiz_id; ?>)">
                Suggest Improvements
            </button>
            <div id="nlp-tools-results"></div>
        </div>
        <?php
    }
    
    /**
     * AJAX handlers
     */
    public function ajaxAnalyzeQuestion() {
        check_ajax_referer('nlp_analysis', 'nonce');
        
        $text = sanitize_text_field($_POST['text']);
        $analysis = $this->processor->processQuestion($text);
        
        ob_start();
        $this->renderAnalysisResults($analysis);
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html, 'analysis' => $analysis]);
    }
    
    public function ajaxGenerateSimilar() {
        check_ajax_referer('nlp_analysis', 'nonce');
        
        $text = sanitize_text_field($_POST['text']);
        $similar = $this->processor->generateSimilarQuestions($text);
        
        ob_start();
        ?>
        <h3>Similar Questions</h3>
        <ol>
            <?php foreach ($similar as $question): ?>
                <li><?php echo esc_html($question); ?></li>
            <?php endforeach; ?>
        </ol>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html, 'questions' => $similar]);
    }
    
    /**
     * Render analysis results
     */
    private function renderAnalysisResults($analysis) {
        ?>
        <h3>Analysis Results</h3>
        
        <h4>Complexity</h4>
        <ul>
            <li>Score: <?php echo round($analysis['complexity']['complexity_score'], 1); ?>/10</li>
            <li>Words: <?php echo $analysis['complexity']['word_count']; ?></li>
            <li>Readability: <?php echo $analysis['readability']['level']; ?></li>
        </ul>
        
        <h4>Topic & Sentiment</h4>
        <ul>
            <li>Primary Topic: <?php echo $analysis['topic']['primary']; ?></li>
            <li>Sentiment: <?php echo $analysis['sentiment']['sentiment']; ?></li>
        </ul>
        
        <h4>Keywords</h4>
        <p><?php echo implode(', ', array_keys($analysis['keywords'])); ?></p>
        <?php
    }
    
    /**
     * Get insights
     */
    public function getInsights() {
        return [
            'questions_analyzed' => $this->getQuestionsAnalyzed(),
            'average_complexity' => $this->getAverageComplexity(),
            'topic_distribution' => $this->getTopicDistribution(),
            'quality_issues' => $this->getQualityIssuesSummary()
        ];
    }
    
    /**
     * Helper methods
     */
    private function getQuizzes() {
        global $wpdb;
        return $wpdb->get_results("
            SELECT id, title FROM {$wpdb->prefix}money_quiz_quizzes 
            WHERE status = 'published' 
            ORDER BY created_at DESC
        ");
    }
    
    private function getQuestionsAnalyzed() {
        global $wpdb;
        return $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = '_nlp_analysis'
        ");
    }
    
    private function getAverageComplexity() {
        // Would calculate from stored analyses
        return 5.2; // Placeholder
    }
    
    private function getTopicDistribution() {
        // Would aggregate from analyses
        return [
            'financial' => 35,
            'psychological' => 25,
            'behavioral' => 20,
            'educational' => 15,
            'motivational' => 5
        ];
    }
    
    private function getQualityIssuesSummary() {
        // Would aggregate quality issues
        return [
            'high_severity' => 12,
            'medium_severity' => 28,
            'low_severity' => 45
        ];
    }
}

// Initialize NLP system
add_action('plugins_loaded', [NLPManager::class, 'init']);