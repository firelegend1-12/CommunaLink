<?php
/**
 * Input Validation & Sanitization Helper
 * 
 * Provides comprehensive input validation, sanitization, and security
 * functions for forms, file uploads, and data processing.
 */

class InputValidator {
    
    /**
     * Validation rules and patterns
     */
    private static $patterns = [
        'email' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
        'phone' => '/^(\+?63|0)?[9]\d{9}$/', // Philippine mobile format
        'url' => '/^https?:\/\/[^\s\/$.?#].[^\s]*$/i',
        'date' => '/^\d{4}-\d{2}-\d{2}$/',
        'time' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/',
        'zipcode' => '/^\d{4}$/', // Philippine ZIP code format
        'username' => '/^[a-zA-Z0-9_-]{3,20}$/',
        'password' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
        'name' => '/^[a-zA-Z\s\'-]+$/',
        'numeric' => '/^\d+$/',
        'decimal' => '/^\d+(\.\d+)?$/',
        'alpha' => '/^[a-zA-Z]+$/',
        'alphanumeric' => '/^[a-zA-Z0-9]+$/'
    ];
    
    /**
     * File upload security settings
     */
    private static $file_security = [
        'max_size' => 10485760, // 10MB
        'allowed_types' => [
            'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'document' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'spreadsheet' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        ],
        'dangerous_extensions' => ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'cmd', 'com', 'scr', 'vbs', 'js']
    ];
    
    /**
     * Validate and sanitize input data
     * 
     * @param mixed $input Input data to validate
     * @param string $type Validation type
     * @param array $options Additional validation options
     * @return array Validation result with sanitized data
     */
    public static function validate($input, $type = 'string', $options = []) {
        $result = [
            'valid' => false,
            'value' => null,
            'errors' => [],
            'sanitized' => null
        ];
        
        // Handle null/empty input
        if ($input === null || $input === '') {
            if (isset($options['required']) && $options['required']) {
                $result['errors'][] = "This field is required.";
                return $result;
            }
            $result['valid'] = true;
            $result['value'] = $input;
            $result['sanitized'] = $input;
            return $result;
        }
        
        // Validate based on type
        switch ($type) {
            case 'email':
                $result = self::validateEmail($input, $options);
                break;
            case 'phone':
                $result = self::validatePhone($input, $options);
                break;
            case 'url':
                $result = self::validateUrl($input, $options);
                break;
            case 'date':
                $result = self::validateDate($input, $options);
                break;
            case 'time':
                $result = self::validateTime($input, $options);
                break;
            case 'file':
                $result = self::validateFile($input, $options);
                break;
            case 'numeric':
                $result = self::validateNumeric($input, $options);
                break;
            case 'decimal':
                $result = self::validateDecimal($input, $options);
                break;
            case 'alpha':
                $result = self::validateAlpha($input, $options);
                break;
            case 'alphanumeric':
                $result = self::validateAlphanumeric($input, $options);
                break;
            case 'username':
                $result = self::validateUsername($input, $options);
                break;
            case 'password':
                $result = self::validatePassword($input, $options);
                break;
            case 'name':
                $result = self::validateName($input, $options);
                break;
            default:
                $result = self::validateString($input, $options);
                break;
        }
        
        return $result;
    }
    
    /**
     * Validate email address
     */
    private static function validateEmail($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        // Check pattern
        if (!preg_match(self::$patterns['email'], $input)) {
            $result['errors'][] = "Invalid email format.";
            return $result;
        }
        
        // Check length
        if (strlen($input) > 254) {
            $result['errors'][] = "Email address is too long.";
            return $result;
        }
        
        // Check for suspicious patterns
        if (self::containsSuspiciousPatterns($input)) {
            $result['errors'][] = "Email contains suspicious content.";
            return $result;
        }
        
        $result['valid'] = true;
        $result['sanitized'] = filter_var($input, FILTER_SANITIZE_EMAIL);
        return $result;
    }
    
    /**
     * Validate phone number
     */
    private static function validatePhone($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        // Remove all non-digit characters except +
        $clean = preg_replace('/[^\d+]/', '', $input);
        
        if (!preg_match(self::$patterns['phone'], $clean)) {
            $result['errors'][] = "Invalid phone number format. Use Philippine mobile format (e.g., 09123456789).";
            return $result;
        }
        
        $result['valid'] = true;
        $result['sanitized'] = $clean;
        return $result;
    }
    
    /**
     * Validate URL
     */
    private static function validateUrl($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        if (!preg_match(self::$patterns['url'], $input)) {
            $result['errors'][] = "Invalid URL format.";
            return $result;
        }
        
        // Check for suspicious protocols
        $parsed = parse_url($input);
        if (isset($parsed['scheme']) && !in_array($parsed['scheme'], ['http', 'https'])) {
            $result['errors'][] = "Invalid URL protocol. Only HTTP and HTTPS are allowed.";
            return $result;
        }
        
        $result['valid'] = true;
        $result['sanitized'] = filter_var($input, FILTER_SANITIZE_URL);
        return $result;
    }
    
    /**
     * Validate date
     */
    private static function validateDate($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        if (!preg_match(self::$patterns['date'], $input)) {
            $result['errors'][] = "Invalid date format. Use YYYY-MM-DD format.";
            return $result;
        }
        
        $date = DateTime::createFromFormat('Y-m-d', $input);
        if (!$date || $date->format('Y-m-d') !== $input) {
            $result['errors'][] = "Invalid date value.";
            return $result;
        }
        
        // Check date range if specified
        if (isset($options['min_date'])) {
            $min_date = new DateTime($options['min_date']);
            if ($date < $min_date) {
                $result['errors'][] = "Date must be after " . $min_date->format('Y-m-d') . ".";
                return $result;
            }
        }
        
        if (isset($options['max_date'])) {
            $max_date = new DateTime($options['max_date']);
            if ($date > $max_date) {
                $result['errors'][] = "Date must be before " . $max_date->format('Y-m-d') . ".";
                return $result;
            }
        }
        
        $result['valid'] = true;
        $result['sanitized'] = $input;
        return $result;
    }
    
    /**
     * Validate time
     */
    private static function validateTime($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        if (!preg_match(self::$patterns['time'], $input)) {
            $result['errors'][] = "Invalid time format. Use HH:MM or HH:MM:SS format.";
            return $result;
        }
        
        $result['valid'] = true;
        $result['sanitized'] = $input;
        return $result;
    }
    
    /**
     * Validate file upload
     */
    private static function validateFile($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        if (!is_array($input) || !isset($input['tmp_name']) || !isset($input['error'])) {
            $result['errors'][] = "Invalid file upload data.";
            return $result;
        }
        
        // Check for upload errors
        if ($input['error'] !== UPLOAD_ERR_OK) {
            $result['errors'][] = self::getUploadErrorMessage($input['error']);
            return $result;
        }
        
        // Check file size
        if ($input['size'] > self::$file_security['max_size']) {
            $result['errors'][] = "File size exceeds maximum limit of " . self::formatBytes(self::$file_security['max_size']) . ".";
            return $result;
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $input['tmp_name']);
        finfo_close($finfo);
        
        $allowed_types = isset($options['allowed_types']) ? $options['allowed_types'] : self::$file_security['allowed_types']['image'];
        if (!in_array($mime_type, $allowed_types)) {
            $result['errors'][] = "File type not allowed. Allowed types: " . implode(', ', $allowed_types);
            return $result;
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($input['name'], PATHINFO_EXTENSION));
        if (in_array($extension, self::$file_security['dangerous_extensions'])) {
            $result['errors'][] = "File extension not allowed for security reasons.";
            return $result;
        }
        
        // Additional security checks
        if (self::isFileSuspicious($input['tmp_name'])) {
            $result['errors'][] = "File appears to be suspicious and was rejected.";
            return $result;
        }
        
        $result['valid'] = true;
        $result['sanitized'] = [
            'name' => self::sanitizeFilename($input['name']),
            'tmp_name' => $input['tmp_name'],
            'size' => $input['size'],
            'type' => $mime_type,
            'extension' => $extension
        ];
        return $result;
    }
    
    /**
     * Validate numeric input
     */
    private static function validateNumeric($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        if (!preg_match(self::$patterns['numeric'], $input)) {
            $result['errors'][] = "Value must be a whole number.";
            return $result;
        }
        
        $value = intval($input);
        
        // Check range if specified
        if (isset($options['min']) && $value < $options['min']) {
            $result['errors'][] = "Value must be at least " . $options['min'] . ".";
            return $result;
        }
        
        if (isset($options['max']) && $value > $options['max']) {
            $result['errors'][] = "Value must be no more than " . $options['max'] . ".";
            return $result;
        }
        
        $result['valid'] = true;
        $result['sanitized'] = $value;
        return $result;
    }
    
    /**
     * Validate decimal input
     */
    private static function validateDecimal($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        if (!preg_match(self::$patterns['decimal'], $input)) {
            $result['errors'][] = "Value must be a valid number.";
            return $result;
        }
        
        $value = floatval($input);
        
        // Check range if specified
        if (isset($options['min']) && $value < $options['min']) {
            $result['errors'][] = "Value must be at least " . $options['min'] . ".";
            return $result;
        }
        
        if (isset($options['max']) && $value > $options['max']) {
            $result['errors'][] = "Value must be no more than " . $options['max'] . ".";
            return $result;
        }
        
        $result['valid'] = true;
        $result['sanitized'] = $value;
        return $result;
    }
    
    /**
     * Validate alphabetic input
     */
    private static function validateAlpha($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        if (!preg_match(self::$patterns['alpha'], $input)) {
            $result['errors'][] = "Value must contain only letters.";
            return $result;
        }
        
        // Check length if specified
        if (isset($options['min_length']) && strlen($input) < $options['min_length']) {
            $result['errors'][] = "Value must be at least " . $options['min_length'] . " characters long.";
            return $result;
        }
        
        if (isset($options['max_length']) && strlen($input) > $options['max_length']) {
            $result['errors'][] = "Value must be no more than " . $options['max_length'] . " characters long.";
            return $result;
        }
        
        $result['valid'] = true;
        $result['sanitized'] = $input;
        return $result;
    }
    
    /**
     * Validate alphanumeric input
     */
    private static function validateAlphanumeric($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        if (!preg_match(self::$patterns['alphanumeric'], $input)) {
            $result['errors'][] = "Value must contain only letters and numbers.";
            return $result;
        }
        
        // Check length if specified
        if (isset($options['min_length']) && strlen($input) < $options['min_length']) {
            $result['errors'][] = "Value must be at least " . $options['min_length'] . " characters long.";
            return $result;
        }
        
        if (isset($options['max_length']) && strlen($input) > $options['max_length']) {
            $result['errors'][] = "Value must be no more than " . $options['max_length'] . " characters long.";
            return $result;
        }
        
        $result['valid'] = true;
        $result['sanitized'] = $input;
        return $result;
    }
    
    /**
     * Validate username
     */
    private static function validateUsername($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        if (!preg_match(self::$patterns['username'], $input)) {
            $result['errors'][] = "Username must be 3-20 characters long and contain only letters, numbers, underscores, and hyphens.";
            return $result;
        }
        
        $result['valid'] = true;
        $result['sanitized'] = $input;
        return $result;
    }
    
    /**
     * Validate password
     */
    private static function validatePassword($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        if (!preg_match(self::$patterns['password'], $input)) {
            $result['errors'][] = "Password must be at least 8 characters long and contain uppercase, lowercase, number, and special character.";
            return $result;
        }
        
        $result['valid'] = true;
        $result['sanitized'] = $input;
        return $result;
    }
    
    /**
     * Validate name
     */
    private static function validateName($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        if (!preg_match(self::$patterns['name'], $input)) {
            $result['errors'][] = "Name must contain only letters, spaces, hyphens, and apostrophes.";
            return $result;
        }
        
        // Check length if specified
        if (isset($options['min_length']) && strlen($input) < $options['min_length']) {
            $result['errors'][] = "Name must be at least " . $options['min_length'] . " characters long.";
            return $result;
        }
        
        if (isset($options['max_length']) && strlen($input) > $options['max_length']) {
            $result['errors'][] = "Name must be no more than " . $options['max_length'] . " characters long.";
            return $result;
        }
        
        $result['valid'] = true;
        $result['sanitized'] = $input;
        return $result;
    }
    
    /**
     * Validate string input
     */
    private static function validateString($input, $options) {
        $result = ['valid' => false, 'value' => $input, 'errors' => [], 'sanitized' => null];
        
        // Check length if specified
        if (isset($options['min_length']) && strlen($input) < $options['min_length']) {
            $result['errors'][] = "Value must be at least " . $options['min_length'] . " characters long.";
            return $result;
        }
        
        if (isset($options['max_length']) && strlen($input) > $options['max_length']) {
            $result['errors'][] = "Value must be no more than " . $options['max_length'] . " characters long.";
            return $result;
        }
        
        // Check for suspicious patterns
        if (self::containsSuspiciousPatterns($input)) {
            $result['errors'][] = "Value contains suspicious content.";
            return $result;
        }
        
        $result['valid'] = true;
        $result['sanitized'] = self::sanitizeString($input);
        return $result;
    }
    
    /**
     * Check for suspicious patterns in input
     */
    private static function containsSuspiciousPatterns($input) {
        $suspicious_patterns = [
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload/i',
            '/onerror/i',
            '/onclick/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/<applet/i',
            '/<meta/i',
            '/<link/i',
            '/<base/i',
            '/<form/i',
            '/<input/i',
            '/<textarea/i',
            '/<select/i',
            '/<button/i'
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if file is suspicious
     */
    private static function isFileSuspicious($file_path) {
        // Check file header for suspicious content
        $handle = fopen($file_path, 'rb');
        if (!$handle) {
            return true;
        }
        
        $header = fread($handle, 1024);
        fclose($handle);
        
        // Check for PHP tags
        if (strpos($header, '<?php') !== false || strpos($header, '<?=') !== false) {
            return true;
        }
        
        // Check for executable headers
        if (strpos($header, 'MZ') === 0) { // Windows executable
            return true;
        }
        
        return false;
    }
    
    /**
     * Sanitize filename
     */
    private static function sanitizeFilename($filename) {
        // Remove path traversal attempts
        $filename = basename($filename);
        
        // Remove or replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Ensure it's not empty
        if (empty($filename)) {
            $filename = 'file_' . uniqid();
        }
        
        return $filename;
    }
    
    /**
     * Sanitize string input
     */
    private static function sanitizeString($input) {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Convert special characters to HTML entities
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove excessive whitespace
        $input = preg_replace('/\s+/', ' ', trim($input));
        
        return $input;
    }
    
    /**
     * Get upload error message
     */
    private static function getUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return "File exceeds upload_max_filesize directive.";
            case UPLOAD_ERR_FORM_SIZE:
                return "File exceeds MAX_FILE_SIZE directive.";
            case UPLOAD_ERR_PARTIAL:
                return "File was only partially uploaded.";
            case UPLOAD_ERR_NO_FILE:
                return "No file was uploaded.";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Missing temporary folder.";
            case UPLOAD_ERR_CANT_WRITE:
                return "Failed to write file to disk.";
            case UPLOAD_ERR_EXTENSION:
                return "File upload stopped by extension.";
            default:
                return "Unknown upload error.";
        }
    }
    
    /**
     * Format bytes to human readable format
     */
    private static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Validate form data
     */
    public static function validateForm($data, $rules) {
        $result = [
            'valid' => true,
            'data' => [],
            'errors' => []
        ];
        
        foreach ($rules as $field => $rule) {
            $input = $data[$field] ?? null;
            $validation = self::validate($input, $rule['type'], $rule['options'] ?? []);
            
            if ($validation['valid']) {
                $result['data'][$field] = $validation['sanitized'];
            } else {
                $result['valid'] = false;
                $result['errors'][$field] = $validation['errors'];
            }
        }
        
        return $result;
    }
    
    /**
     * Sanitize array of inputs
     */
    public static function sanitizeArray($array) {
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } else {
                $sanitized[$key] = self::sanitizeString($value);
            }
        }
        
        return $sanitized;
    }
}

/**
 * Helper functions for easier usage
 */

/**
 * Validate input (shorthand)
 */
function validate_input($input, $type = 'string', $options = []) {
    return InputValidator::validate($input, $type, $options);
}

/**
 * Validate form data (shorthand)
 */
function validate_form($data, $rules) {
    return InputValidator::validateForm($data, $rules);
}

/**
 * Sanitize string (shorthand)
 */
function sanitize_string($input) {
    return InputValidator::sanitizeString($input);
}

/**
 * Sanitize array (shorthand)
 */
function sanitize_array($array) {
    return InputValidator::sanitizeArray($array);
}

/**
 * Check if input is valid (shorthand)
 */
function is_valid_input($input, $type = 'string', $options = []) {
    $validation = InputValidator::validate($input, $type, $options);
    return $validation['valid'];
}







