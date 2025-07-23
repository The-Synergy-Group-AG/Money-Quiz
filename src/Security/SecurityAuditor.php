<?php
/**
 * Security Auditor
 *
 * Monitors and logs security-related events for the Money Quiz plugin.
 *
 * @package MoneyQuiz\Security
 * @since   7.0.0
 */

namespace MoneyQuiz\Security;

use MoneyQuiz\Core\Logging\Logger;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Security auditor class.
 *
 * @since 7.0.0
 */
class SecurityAuditor {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Security event types.
	 *
	 * @var array
	 */
	private array $event_types = [
		'auth_failed'       => 'Authentication Failed',
		'auth_success'      => 'Authentication Success',
		'access_denied'     => 'Access Denied',
		'rate_limit'        => 'Rate Limit Exceeded',
		'invalid_nonce'     => 'Invalid Nonce',
		'invalid_input'     => 'Invalid Input Detected',
		'xss_attempt'       => 'XSS Attempt Blocked',
		'sql_injection'     => 'SQL Injection Attempt',
		'file_upload'       => 'File Upload Attempt',
		'privilege_change'  => 'Privilege Change',
		'settings_change'   => 'Security Settings Changed',
		'suspicious_query'  => 'Suspicious Query Detected',
		'brute_force'       => 'Brute Force Attempt',
		'session_hijack'    => 'Session Hijacking Attempt',
	];

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Log security event.
	 *
	 * @since 7.0.0
	 *
	 * @param string $event_type Event type.
	 * @param array  $context    Event context.
	 * @param string $severity   Log level (info, warning, error, critical).
	 * @return void
	 */
	public function log_event( string $event_type, array $context = [], string $severity = 'warning' ): void {
		// Add common context.
		$context = array_merge(
			$context,
			[
				'event_type'  => $event_type,
				'event_label' => $this->event_types[ $event_type ] ?? 'Unknown Event',
				'timestamp'   => current_time( 'mysql' ),
				'user_id'     => get_current_user_id(),
				'user_ip'     => $this->get_client_ip(),
				'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
				'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
				'referer'     => $_SERVER['HTTP_REFERER'] ?? '',
			]
		);

		// Log based on severity.
		switch ( $severity ) {
			case 'info':
				$this->logger->info( "Security Event: {$context['event_label']}", $context );
				break;
			case 'warning':
				$this->logger->warning( "Security Event: {$context['event_label']}", $context );
				break;
			case 'error':
				$this->logger->error( "Security Event: {$context['event_label']}", $context );
				break;
			case 'critical':
				$this->logger->critical( "Security Event: {$context['event_label']}", $context );
				break;
			default:
				$this->logger->warning( "Security Event: {$context['event_label']}", $context );
		}

		// Trigger action for other systems to hook into.
		do_action( 'money_quiz_security_event', $event_type, $context, $severity );
	}

	/**
	 * Log authentication failure.
	 *
	 * @since 7.0.0
	 *
	 * @param string $username Username attempted.
	 * @param array  $context  Additional context.
	 * @return void
	 */
	public function log_auth_failure( string $username, array $context = [] ): void {
		$this->log_event(
			'auth_failed',
			array_merge(
				[ 'username' => $username ],
				$context
			),
			'warning'
		);
	}

	/**
	 * Log authentication success.
	 *
	 * @since 7.0.0
	 *
	 * @param int   $user_id User ID.
	 * @param array $context Additional context.
	 * @return void
	 */
	public function log_auth_success( int $user_id, array $context = [] ): void {
		$this->log_event(
			'auth_success',
			array_merge(
				[
					'user_id' => $user_id,
					'user_login' => get_userdata( $user_id )->user_login ?? 'Unknown',
				],
				$context
			),
			'info'
		);
	}

	/**
	 * Log access denied event.
	 *
	 * @since 7.0.0
	 *
	 * @param string $resource  Resource attempted.
	 * @param string $reason    Denial reason.
	 * @param array  $context   Additional context.
	 * @return void
	 */
	public function log_access_denied( string $resource, string $reason, array $context = [] ): void {
		$this->log_event(
			'access_denied',
			array_merge(
				[
					'resource' => $resource,
					'reason'   => $reason,
				],
				$context
			),
			'warning'
		);
	}

	/**
	 * Log rate limit exceeded.
	 *
	 * @since 7.0.0
	 *
	 * @param string $identifier Rate limit identifier.
	 * @param string $action     Action limited.
	 * @param array  $context    Additional context.
	 * @return void
	 */
	public function log_rate_limit( string $identifier, string $action, array $context = [] ): void {
		$this->log_event(
			'rate_limit',
			array_merge(
				[
					'identifier' => $identifier,
					'action'     => $action,
				],
				$context
			),
			'warning'
		);
	}

	/**
	 * Log invalid nonce.
	 *
	 * @since 7.0.0
	 *
	 * @param string $action  Nonce action.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function log_invalid_nonce( string $action, array $context = [] ): void {
		$this->log_event(
			'invalid_nonce',
			array_merge(
				[ 'nonce_action' => $action ],
				$context
			),
			'error'
		);
	}

	/**
	 * Log suspicious activity.
	 *
	 * @since 7.0.0
	 *
	 * @param string $activity_type Activity type.
	 * @param string $details       Activity details.
	 * @param array  $context       Additional context.
	 * @return void
	 */
	public function log_suspicious_activity( string $activity_type, string $details, array $context = [] ): void {
		$this->log_event(
			$activity_type,
			array_merge(
				[ 'details' => $details ],
				$context
			),
			'critical'
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @since 7.0.0
	 *
	 * @return string Client IP.
	 */
	private function get_client_ip(): string {
		$ip_keys = [ 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR', 'HTTP_CLIENT_IP' ];

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				$ip = filter_var( $_SERVER[ $key ], FILTER_VALIDATE_IP );
				if ( $ip !== false ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Analyze patterns for potential threats.
	 *
	 * @since 7.0.0
	 *
	 * @param string $event_type Event type to analyze.
	 * @param int    $timeframe  Timeframe in seconds.
	 * @return array Analysis results.
	 */
	public function analyze_patterns( string $event_type, int $timeframe = 3600 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'money_quiz_security_events';
		$since = gmdate( 'Y-m-d H:i:s', time() - $timeframe );

		// Get event counts by IP.
		$by_ip = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_ip, COUNT(*) as count 
				FROM {$table} 
				WHERE event_type = %s 
				AND created_at > %s 
				GROUP BY user_ip 
				ORDER BY count DESC 
				LIMIT 10",
				$event_type,
				$since
			),
			ARRAY_A
		);

		// Get event counts by user.
		$by_user = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, COUNT(*) as count 
				FROM {$table} 
				WHERE event_type = %s 
				AND created_at > %s 
				AND user_id > 0 
				GROUP BY user_id 
				ORDER BY count DESC 
				LIMIT 10",
				$event_type,
				$since
			),
			ARRAY_A
		);

		// Get total count.
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
				FROM {$table} 
				WHERE event_type = %s 
				AND created_at > %s",
				$event_type,
				$since
			)
		);

		return [
			'event_type' => $event_type,
			'timeframe'  => $timeframe,
			'total'      => (int) $total,
			'by_ip'      => $by_ip,
			'by_user'    => $by_user,
			'threshold_exceeded' => $this->check_thresholds( $event_type, (int) $total ),
		];
	}

	/**
	 * Check if thresholds are exceeded.
	 *
	 * @since 7.0.0
	 *
	 * @param string $event_type Event type.
	 * @param int    $count      Event count.
	 * @return bool True if threshold exceeded.
	 */
	private function check_thresholds( string $event_type, int $count ): bool {
		$thresholds = [
			'auth_failed'    => 50,
			'rate_limit'     => 100,
			'invalid_nonce'  => 30,
			'xss_attempt'    => 10,
			'sql_injection'  => 5,
			'brute_force'    => 20,
			'session_hijack' => 5,
		];

		$threshold = $thresholds[ $event_type ] ?? 100;
		return $count > $threshold;
	}

	/**
	 * Generate security report.
	 *
	 * @since 7.0.0
	 *
	 * @param int $days Number of days to report.
	 * @return array Security report data.
	 */
	public function generate_report( int $days = 7 ): array {
		$report = [
			'period'     => $days,
			'start_date' => gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) ),
			'end_date'   => gmdate( 'Y-m-d' ),
			'events'     => [],
			'threats'    => [],
			'summary'    => [],
		];

		// Analyze each event type.
		foreach ( $this->event_types as $type => $label ) {
			$analysis = $this->analyze_patterns( $type, $days * DAY_IN_SECONDS );
			if ( $analysis['total'] > 0 ) {
				$report['events'][ $type ] = $analysis;

				if ( $analysis['threshold_exceeded'] ) {
					$report['threats'][] = [
						'type'    => $type,
						'level'   => 'high',
						'message' => sprintf(
							'High volume of %s events detected: %d in the last %d days',
							$label,
							$analysis['total'],
							$days
						),
					];
				}
			}
		}

		// Generate summary.
		$report['summary'] = [
			'total_events'  => array_sum( array_column( $report['events'], 'total' ) ),
			'threat_count'  => count( $report['threats'] ),
			'top_event'     => $this->get_top_event( $report['events'] ),
			'risk_level'    => $this->calculate_risk_level( $report['threats'] ),
		];

		return $report;
	}

	/**
	 * Get top event type.
	 *
	 * @since 7.0.0
	 *
	 * @param array $events Event data.
	 * @return string|null Top event type.
	 */
	private function get_top_event( array $events ): ?string {
		if ( empty( $events ) ) {
			return null;
		}

		$max_count = 0;
		$top_event = null;

		foreach ( $events as $type => $data ) {
			if ( $data['total'] > $max_count ) {
				$max_count = $data['total'];
				$top_event = $type;
			}
		}

		return $top_event;
	}

	/**
	 * Calculate overall risk level.
	 *
	 * @since 7.0.0
	 *
	 * @param array $threats Threat data.
	 * @return string Risk level (low, medium, high, critical).
	 */
	private function calculate_risk_level( array $threats ): string {
		if ( empty( $threats ) ) {
			return 'low';
		}

		$high_threats = array_filter(
			$threats,
			function( $threat ) {
				return $threat['level'] === 'high';
			}
		);

		if ( count( $high_threats ) >= 3 ) {
			return 'critical';
		} elseif ( count( $high_threats ) >= 1 ) {
			return 'high';
		} elseif ( count( $threats ) >= 3 ) {
			return 'medium';
		}

		return 'low';
	}
}