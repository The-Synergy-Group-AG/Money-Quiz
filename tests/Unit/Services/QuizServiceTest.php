<?php
/**
 * QuizService unit tests
 *
 * @package MoneyQuiz
 */

namespace MoneyQuiz\Tests\Unit\Services;

use MoneyQuiz\Tests\TestCase;
use MoneyQuiz\Services\QuizService;
use MoneyQuiz\Services\CacheService;
use MoneyQuiz\Database\Repositories\QuizRepository;
use MoneyQuiz\Database\Repositories\ArchetypeRepository;

/**
 * Test the quiz service
 */
class QuizServiceTest extends TestCase {
    
    /**
     * @var QuizService
     */
    private QuizService $service;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $quiz_repository_mock;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $archetype_repository_mock;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $cache_service_mock;
    
    /**
     * Setup before each test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Create mocks
        $this->quiz_repository_mock = $this->createMock( QuizRepository::class );
        $this->archetype_repository_mock = $this->createMock( ArchetypeRepository::class );
        $this->cache_service_mock = $this->createMock( CacheService::class );
        
        // Create service with mocks
        $this->service = new QuizService(
            $this->quiz_repository_mock,
            $this->archetype_repository_mock,
            $this->cache_service_mock
        );
    }
    
    /**
     * Test get quiz with cache hit
     */
    public function test_get_quiz_with_cache_hit() {
        $quiz_id = 1;
        $cached_quiz = (object) [ 'id' => $quiz_id, 'title' => 'Cached Quiz' ];
        
        $this->cache_service_mock
            ->expects( $this->once() )
            ->method( 'get' )
            ->with( "quiz_{$quiz_id}" )
            ->willReturn( $cached_quiz );
        
        // Repository should not be called
        $this->quiz_repository_mock
            ->expects( $this->never() )
            ->method( 'get_with_questions' );
        
        $result = $this->service->get_quiz( $quiz_id );
        
        $this->assertEquals( $cached_quiz, $result );
    }
    
    /**
     * Test get quiz with cache miss
     */
    public function test_get_quiz_with_cache_miss() {
        $quiz_id = 1;
        $quiz = (object) [ 'id' => $quiz_id, 'title' => 'Test Quiz' ];
        
        $this->cache_service_mock
            ->expects( $this->once() )
            ->method( 'get' )
            ->with( "quiz_{$quiz_id}" )
            ->willReturn( false );
        
        $this->quiz_repository_mock
            ->expects( $this->once() )
            ->method( 'get_with_questions' )
            ->with( $quiz_id )
            ->willReturn( $quiz );
        
        $this->cache_service_mock
            ->expects( $this->once() )
            ->method( 'set' )
            ->with( "quiz_{$quiz_id}", $quiz, 3600 );
        
        $result = $this->service->get_quiz( $quiz_id );
        
        $this->assertEquals( $quiz, $result );
    }
    
    /**
     * Test get quiz returns null for non-existent quiz
     */
    public function test_get_quiz_returns_null_for_non_existent() {
        $quiz_id = 999;
        
        $this->cache_service_mock
            ->expects( $this->once() )
            ->method( 'get' )
            ->willReturn( false );
        
        $this->quiz_repository_mock
            ->expects( $this->once() )
            ->method( 'get_with_questions' )
            ->with( $quiz_id )
            ->willReturn( null );
        
        $this->cache_service_mock
            ->expects( $this->never() )
            ->method( 'set' );
        
        $result = $this->service->get_quiz( $quiz_id );
        
        $this->assertNull( $result );
    }
    
    /**
     * Test calculate result
     */
    public function test_calculate_result() {
        $quiz_id = 1;
        $answers = [
            '1' => 'a',
            '2' => 'b',
        ];
        
        $quiz = (object) [
            'id' => $quiz_id,
            'questions' => [
                (object) [
                    'id' => '1',
                    'archetype_weights' => [
                        '1' => [ 'a' => 10, 'b' => 5 ],
                        '2' => [ 'a' => 5, 'b' => 10 ],
                    ],
                ],
                (object) [
                    'id' => '2',
                    'archetype_weights' => [
                        '1' => [ 'a' => 5, 'b' => 10 ],
                        '2' => [ 'a' => 10, 'b' => 5 ],
                    ],
                ],
            ],
        ];
        
        $archetype1 = (object) [ 'id' => '1', 'name' => 'Archetype 1' ];
        $archetype2 = (object) [ 'id' => '2', 'name' => 'Archetype 2' ];
        
        $this->cache_service_mock
            ->method( 'get' )
            ->willReturn( false );
        
        $this->quiz_repository_mock
            ->expects( $this->once() )
            ->method( 'get_with_questions' )
            ->willReturn( $quiz );
        
        $this->archetype_repository_mock
            ->expects( $this->exactly( 2 ) )
            ->method( 'find' )
            ->willReturnMap( [
                [ '1', $archetype1 ],
                [ '2', $archetype2 ],
            ] );
        
        $result = $this->service->calculate_result( $quiz_id, $answers );
        
        $this->assertIsArray( $result );
        $this->assertArrayHasKeys( [ 'archetype', 'score', 'scores' ], $result );
        $this->assertEquals( $archetype1, $result['archetype'] );
        $this->assertEquals( 100, $result['score'] );
    }
    
    /**
     * Test save result
     */
    public function test_save_result() {
        $quiz_id = 1;
        $result = [
            'archetype' => (object) [ 'id' => '1', 'name' => 'Test Archetype' ],
            'score' => 85.5,
            'scores' => [ '1' => 20, '2' => 10 ],
        ];
        $prospect_id = 123;
        
        // Mock wpdb
        global $wpdb;
        $wpdb = $this->createMock( \wpdb::class );
        $wpdb->prefix = 'wp_';
        
        $wpdb->expects( $this->once() )
            ->method( 'insert' )
            ->with(
                'wp_money_quiz_results',
                $this->callback( function( $data ) use ( $quiz_id, $prospect_id ) {
                    return $data['quiz_id'] === $quiz_id
                        && $data['prospect_id'] === $prospect_id
                        && $data['archetype_id'] === '1'
                        && $data['score'] === 85.5
                        && ! empty( $data['answers'] );
                })
            )
            ->willReturn( 1 );
        
        $wpdb->insert_id = 456;
        
        $result_id = $this->service->save_result( $quiz_id, $result, $prospect_id );
        
        $this->assertEquals( 456, $result_id );
    }
    
    /**
     * Test get archetypes
     */
    public function test_get_archetypes() {
        $archetypes = [
            (object) [ 'id' => '1', 'name' => 'Archetype 1' ],
            (object) [ 'id' => '2', 'name' => 'Archetype 2' ],
        ];
        
        $this->cache_service_mock
            ->expects( $this->once() )
            ->method( 'get' )
            ->with( 'archetypes_active' )
            ->willReturn( false );
        
        $this->archetype_repository_mock
            ->expects( $this->once() )
            ->method( 'get_active' )
            ->willReturn( $archetypes );
        
        $this->cache_service_mock
            ->expects( $this->once() )
            ->method( 'set' )
            ->with( 'archetypes_active', $archetypes, 3600 );
        
        $result = $this->service->get_archetypes();
        
        $this->assertEquals( $archetypes, $result );
    }
    
    /**
     * Test clear cache
     */
    public function test_clear_cache() {
        $quiz_id = 1;
        
        $this->cache_service_mock
            ->expects( $this->exactly( 2 ) )
            ->method( 'delete' )
            ->withConsecutive(
                [ "quiz_{$quiz_id}" ],
                [ 'archetypes_active' ]
            );
        
        $this->service->clear_cache( $quiz_id );
    }
    
    /**
     * Test validate answers
     */
    public function test_validate_answers() {
        $quiz = (object) [
            'questions' => [
                (object) [ 'id' => '1', 'is_required' => true ],
                (object) [ 'id' => '2', 'is_required' => true ],
                (object) [ 'id' => '3', 'is_required' => false ],
            ],
        ];
        
        // Valid answers
        $valid_answers = [ '1' => 'a', '2' => 'b' ];
        $this->assertTrue( $this->call_private_method( 
            $this->service, 
            'validate_answers', 
            [ $quiz, $valid_answers ] 
        ) );
        
        // Missing required answer
        $invalid_answers = [ '1' => 'a' ];
        $this->assertFalse( $this->call_private_method( 
            $this->service, 
            'validate_answers', 
            [ $quiz, $invalid_answers ] 
        ) );
        
        // With optional answer
        $valid_with_optional = [ '1' => 'a', '2' => 'b', '3' => 'c' ];
        $this->assertTrue( $this->call_private_method( 
            $this->service, 
            'validate_answers', 
            [ $quiz, $valid_with_optional ] 
        ) );
    }
}