<?php
/**
 * CSRF (Cross-Site Request Forgery) Protection
 * 
 * This class provides CSRF token generation and validation
 * to protect against CSRF attacks on forms.
 */

class CSRFProtection {
    
    /**
     * Generate a CSRF token and store it in the session
     * 
     * @return string The generated CSRF token
     */
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Generate a cryptographically secure random token
        $token = bin2hex(random_bytes(32));
        
        // Store token in session
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * Get the current CSRF token from session
     * 
     * @return string|null The current CSRF token or null if not set
     */
    public static function getToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['csrf_token'] ?? null;
    }
    
    /**
     * Validate a CSRF token
     * 
     * @param string $token The token to validate
     * @param int $maxAge Maximum age of token in seconds (default: 3600 = 1 hour)
     * @return bool True if token is valid, false otherwise
     */
    public static function validateToken($token, $maxAge = 3600) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token exists in session
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Check token age
        if ((time() - $_SESSION['csrf_token_time']) > $maxAge) {
            self::clearToken();
            return false;
        }
        
        // Use hash_equals to prevent timing attacks
        $isValid = hash_equals($_SESSION['csrf_token'], $token);
        
        // Optionally clear token after use (one-time use)
        // Uncomment the next line if you want one-time use tokens
        // if ($isValid) self::clearToken();
        
        return $isValid;
    }
    
    /**
     * Clear the CSRF token from session
     */
    public static function clearToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
    }
    
    /**
     * Generate HTML hidden input field with CSRF token
     * 
     * @return string HTML input field
     */
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Validate CSRF token from POST data
     * 
     * @return bool True if token is valid, false otherwise
     */
    public static function validateFromPost() {
        $token = $_POST['csrf_token'] ?? '';
        return self::validateToken($token);
    }
    
    /**
     * Require valid CSRF token or die with error
     * 
     * @param string $errorMessage Custom error message
     */
    public static function requireValidToken($errorMessage = 'Invalid CSRF token. Please refresh the page and try again.') {
        if (!self::validateFromPost()) {
            // Log the CSRF attack attempt
            error_log('CSRF attack attempt detected from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
            // Set error message and redirect or die
            if (isset($_SESSION)) {
                $_SESSION['error_message'] = $errorMessage;
                if (isset($_SERVER['HTTP_REFERER'])) {
                    header('Location: ' . $_SERVER['HTTP_REFERER']);
                } else {
                    header('Location: ../index.php');
                }
                exit;
            } else {
                die($errorMessage);
            }
        }
    }
    
    /**
     * Generate CSRF token for AJAX requests
     * 
     * @return array Array with token data for JSON response
     */
    public static function getTokenForAjax() {
        return [
            'csrf_token' => self::generateToken(),
            'expires_at' => time() + 3600
        ];
    }
}

/**
 * Helper functions for easier usage
 */

/**
 * Generate CSRF token field (shorthand)
 */
function csrf_field() {
    return CSRFProtection::getTokenField();
}

/**
 * Generate CSRF token (shorthand)
 */
function csrf_token() {
    return CSRFProtection::generateToken();
}

/**
 * Validate CSRF token (shorthand)
 */
function csrf_validate() {
    return CSRFProtection::validateFromPost();
}

/**
 * Require valid CSRF token (shorthand)
 */
function csrf_require() {
    CSRFProtection::requireValidToken();
}







