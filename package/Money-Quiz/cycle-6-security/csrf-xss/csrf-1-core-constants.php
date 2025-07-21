<?php
/**
 * CSRF Core Constants and Interfaces
 * 
 * @package MoneyQuiz\Security\CSRF
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\CSRF;

/**
 * CSRF Constants
 */
interface CsrfConstants {
    const TOKEN_LENGTH = 32;
    const TOKEN_LIFETIME = 3600; // 1 hour
    const SESSION_KEY = 'money_quiz_csrf_tokens';
    const HEADER_NAME = 'X-CSRF-Token';
    const META_NAME = 'csrf-token';
    const FIELD_NAME = 'money_quiz_csrf_token';
    const ACTION_FIELD = 'money_quiz_csrf_action';
    const DEFAULT_ACTION = 'money_quiz_action';
}

/**
 * CSRF Token Interface
 */
interface CsrfTokenInterface {
    public function generate($action = CsrfConstants::DEFAULT_ACTION);
    public function validate($token, $action = CsrfConstants::DEFAULT_ACTION);
    public function getField($action = CsrfConstants::DEFAULT_ACTION, $echo = true);
}

/**
 * CSRF Storage Interface
 */
interface CsrfStorageInterface {
    public function store($token, array $data);
    public function retrieve($token);
    public function remove($token);
    public function cleanup();
}

/**
 * CSRF Exception
 */
class CsrfException extends \Exception {
    const INVALID_TOKEN = 100;
    const EXPIRED_TOKEN = 101;
    const MISSING_TOKEN = 102;
    const ACTION_MISMATCH = 103;
    const IP_MISMATCH = 104;
    const AGENT_MISMATCH = 105;
}