<?php
/**
 * Security Headers Helper
 * 
 * Provides comprehensive security headers implementation
 * to protect against various web attacks and vulnerabilities.
 */

class SecurityHeaders {

    const CSP_REPORT_PAYLOAD_MAX_BYTES = 65535;
    
    /**
     * Security header configurations
     */
    private static $headers = [
        'content_security_policy' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com'],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com', 'https://fonts.googleapis.com'],
            'font-src' => ["'self'", 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com', 'https://fonts.gstatic.com', 'data:'],
            'img-src' => ["'self'", 'data:', 'https:'],
            'connect-src' => ["'self'"],
            'frame-src' => ["'none'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'upgrade-insecure-requests' => true
        ],
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => 'geolocation=(), microphone=(), camera=()',
        'strict_transport_security' => 'max-age=31536000; includeSubDomains; preload',
        'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'pragma' => 'no-cache'
    ];
    
    /**
     * Apply all security headers
     */
    public static function applyAll() {
        self::applyContentSecurityPolicy();
        self::applyXFrameOptions();
        self::applyXContentTypeOptions();
        self::applyXXSSProtection();
        self::applyReferrerPolicy();
        self::applyPermissionsPolicy();
        self::applyStrictTransportSecurity();
        self::applyCacheControl();
        self::applyPragma();
    }
    
    /**
     * Apply Content Security Policy header
     */
    public static function applyContentSecurityPolicy() {
        $csp = self::buildCSPString(self::$headers['content_security_policy']);
        header("Content-Security-Policy: " . $csp);

        // No-downtime hardening: emit stricter policy in report-only mode first.
        if (self::isCSPReportOnlyEnabled()) {
            $strict_csp = self::buildStrictReportOnlyPolicy();
            header("Content-Security-Policy-Report-Only: " . self::buildCSPString($strict_csp));
        }
    }
    
    /**
     * Apply X-Frame-Options header
     */
    public static function applyXFrameOptions() {
        header("X-Frame-Options: " . self::$headers['x_frame_options']);
    }
    
    /**
     * Apply X-Content-Type-Options header
     */
    public static function applyXContentTypeOptions() {
        header("X-Content-Type-Options: " . self::$headers['x_content_type_options']);
    }
    
    /**
     * Apply X-XSS-Protection header
     */
    public static function applyXXSSProtection() {
        header("X-XSS-Protection: " . self::$headers['x_xss_protection']);
    }
    
    /**
     * Apply Referrer-Policy header
     */
    public static function applyReferrerPolicy() {
        header("Referrer-Policy: " . self::$headers['referrer_policy']);
    }
    
    /**
     * Apply Permissions-Policy header
     */
    public static function applyPermissionsPolicy() {
        header("Permissions-Policy: " . self::$headers['permissions_policy']);
    }
    
    /**
     * Apply Strict-Transport-Security header
     */
    public static function applyStrictTransportSecurity() {
        if (self::isHTTPS()) {
            header("Strict-Transport-Security: " . self::$headers['strict_transport_security']);
        }
    }
    
    /**
     * Apply Cache-Control header
     */
    public static function applyCacheControl() {
        header("Cache-Control: " . self::$headers['cache_control']);
    }
    
    /**
     * Apply Pragma header
     */
    public static function applyPragma() {
        header("Pragma: " . self::$headers['pragma']);
    }
    
    /**
     * Build Content Security Policy string
     */
    private static function buildCSPString($policy) {
        $csp_parts = [];

        foreach ($policy as $directive => $values) {
            if (is_array($values)) {
                $csp_parts[] = $directive . " " . implode(' ', $values);
            } elseif (is_bool($values)) {
                if ($values) {
                    $csp_parts[] = $directive;
                }
            } else {
                $csp_parts[] = $directive . " " . $values;
            }
        }

        return implode('; ', $csp_parts);
    }

    /**
     * Build stricter CSP policy for report-only rollout.
     */
    private static function buildStrictReportOnlyPolicy() {
        $strict_policy = self::$headers['content_security_policy'];

        // Remove unsafe-inline from script/style to surface inline execution debt without breaking traffic.
        if (isset($strict_policy['script-src']) && is_array($strict_policy['script-src'])) {
            $strict_policy['script-src'] = array_values(array_filter(
                $strict_policy['script-src'],
                function ($value) {
                    return $value !== "'unsafe-inline'";
                }
            ));
        }

        if (isset($strict_policy['style-src']) && is_array($strict_policy['style-src'])) {
            $strict_policy['style-src'] = array_values(array_filter(
                $strict_policy['style-src'],
                function ($value) {
                    return $value !== "'unsafe-inline'";
                }
            ));
        }

        $report_uri = self::getCSPReportUri();
        if ($report_uri !== '') {
            $strict_policy['report-uri'] = [$report_uri];
        }

        return $strict_policy;
    }

    /**
     * Determine if CSP report-only mode should be enabled.
     */
    private static function isCSPReportOnlyEnabled() {
        return self::envToBool(self::getEnvValue('CSP_REPORT_ONLY', 'false'));
    }

    /**
     * Get report URI for CSP violations.
     */
    private static function getCSPReportUri() {
        $report_uri = trim((string) self::getEnvValue('CSP_REPORT_URI', ''));
        return $report_uri;
    }

    /**
     * Read environment value safely with fallback support.
     */
    private static function getEnvValue($key, $default = null) {
        if (function_exists('env')) {
            return env($key, $default);
        }

        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return $value;
    }

    /**
     * Convert environment values to strict booleans.
     */
    private static function envToBool($value, $default = false) {
        if ($value === null || $value === false) {
            return $default;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }
    
    /**
     * Check if current connection is HTTPS
     */
    private static function isHTTPS() {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
               (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    }
    
    /**
     * Update CSP directive
     */
    public static function updateCSPDirective($directive, $values) {
        if (isset(self::$headers['content_security_policy'][$directive])) {
            self::$headers['content_security_policy'][$directive] = $values;
        }
    }
    
    /**
     * Add CSP directive
     */
    public static function addCSPDirective($directive, $values) {
        self::$headers['content_security_policy'][$directive] = $values;
    }
    
    /**
     * Remove CSP directive
     */
    public static function removeCSPDirective($directive) {
        if (isset(self::$headers['content_security_policy'][$directive])) {
            unset(self::$headers['content_security_policy'][$directive]);
        }
    }
    
    /**
     * Update header value
     */
    public static function updateHeader($header, $value) {
        if (isset(self::$headers[$header])) {
            self::$headers[$header] = $value;
        }
    }
    
    /**
     * Get current header configuration
     */
    public static function getHeaders() {
        return self::$headers;
    }
    
    /**
     * Apply headers for specific page type
     */
    public static function applyForPage($page_type) {
        switch ($page_type) {
            case 'login':
                self::applyLoginHeaders();
                break;
            case 'admin':
                self::applyAdminHeaders();
                break;
            case 'api':
                self::applyAPIHeaders();
                break;
            case 'public':
                self::applyPublicHeaders();
                break;
            default:
                self::applyAll();
                break;
        }
    }
    
    /**
     * Apply headers for login pages
     */
    private static function applyLoginHeaders() {
        // Stricter CSP for login pages
        $login_csp = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'"],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com', 'https://fonts.googleapis.com'],
            'font-src' => ["'self'", 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com', 'https://fonts.gstatic.com', 'data:'],
            'img-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'"],
            'frame-src' => ["'none'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"]
        ];
        
        $original_csp = self::$headers['content_security_policy'];
        self::$headers['content_security_policy'] = $login_csp;
        
        self::applyAll();
        
        // Restore original CSP
        self::$headers['content_security_policy'] = $original_csp;
    }
    
    /**
     * Apply headers for admin pages
     */
    private static function applyAdminHeaders() {
        // Enhanced security for admin pages
        self::applyAll();
        
        // Additional admin-specific headers
        header("X-Admin-Access: true");
        header("X-Content-Type-Options: nosniff");
    }
    
    /**
     * Apply headers for API endpoints
     */
    private static function applyAPIHeaders() {
        // API-specific headers
        header("Content-Type: application/json");
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
    }
    
    /**
     * Apply headers for public pages
     */
    private static function applyPublicHeaders() {
        // Relaxed CSP for public pages
        $public_csp = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com'],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com', 'https://fonts.googleapis.com'],
            'font-src' => ["'self'", 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com', 'https://fonts.gstatic.com', 'data:'],
            'img-src' => ["'self'", 'data:', 'https:'],
            'connect-src' => ["'self'"],
            'frame-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"]
        ];
        
        $original_csp = self::$headers['content_security_policy'];
        self::$headers['content_security_policy'] = $public_csp;
        
        self::applyAll();
        
        // Restore original CSP
        self::$headers['content_security_policy'] = $original_csp;
    }
    
    /**
     * Generate nonce for CSP
     */
    public static function generateNonce() {
        if (!isset($_SESSION['csp_nonce'])) {
            $_SESSION['csp_nonce'] = base64_encode(random_bytes(16));
        }
        return $_SESSION['csp_nonce'];
    }
    
    /**
     * Add nonce to CSP
     */
    public static function addNonceToCSP() {
        $nonce = self::generateNonce();
        self::updateCSPDirective('script-src', ["'self'", "'unsafe-inline'", "'nonce-" . $nonce . "'"]);
        self::updateCSPDirective('style-src', ["'self'", "'unsafe-inline'", "'nonce-" . $nonce . "'"]);
    }
    
    /**
     * Get nonce attribute for HTML
     */
    public static function getNonceAttribute() {
        $nonce = self::generateNonce();
        return 'nonce="' . $nonce . '"';
    }
    
    /**
     * Remove security headers (for testing purposes)
     */
    public static function removeHeaders() {
        // Note: This is for testing only and should not be used in production
        if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
            // Remove headers by setting empty values
            header_remove("Content-Security-Policy");
            header_remove("X-Frame-Options");
            header_remove("X-Content-Type-Options");
            header_remove("X-XSS-Protection");
            header_remove("Referrer-Policy");
            header_remove("Permissions-Policy");
            header_remove("Strict-Transport-Security");
            header_remove("Cache-Control");
            header_remove("Pragma");
        }
    }
}

/**
 * Helper functions for easier usage
 */

/**
 * Apply all security headers (shorthand)
 */
function apply_security_headers() {
    SecurityHeaders::applyAll();
}

/**
 * Apply security headers for specific page type (shorthand)
 */
function apply_page_security_headers($page_type) {
    SecurityHeaders::applyForPage($page_type);
}

/**
 * Get nonce attribute for HTML (shorthand)
 */
function get_csp_nonce() {
    return SecurityHeaders::getNonceAttribute();
}

/**
 * Generate nonce for CSP (shorthand)
 */
function generate_csp_nonce() {
    return SecurityHeaders::generateNonce();
}







