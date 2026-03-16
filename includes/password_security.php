<?php
/**
 * Password Security Helper
 * 
 * Provides comprehensive password validation, strength checking,
 * and security functions for the system.
 */

class PasswordSecurity {
    
    /**
     * Minimum password length
     */
    const MIN_LENGTH = 8;
    
    /**
     * Maximum password length
     */
    const MAX_LENGTH = 128;
    
    /**
     * Password strength levels
     */
    const STRENGTH_WEAK = 1;
    const STRENGTH_FAIR = 2;
    const STRENGTH_GOOD = 3;
    const STRENGTH_STRONG = 4;
    const STRENGTH_VERY_STRONG = 5;
    
    /**
     * Check if password meets minimum security requirements
     * 
     * @param string $password The password to validate
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public static function validatePassword($password) {
        $errors = [];
        
        // Check length
        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = "Password must be at least " . self::MIN_LENGTH . " characters long.";
        }
        
        if (strlen($password) > self::MAX_LENGTH) {
            $errors[] = "Password cannot exceed " . self::MAX_LENGTH . " characters.";
        }
        
        // Check for common weak patterns
        if (self::isCommonPassword($password)) {
            $errors[] = "This password is too common. Please choose a more unique password.";
        }
        
        if (self::isSequential($password)) {
            $errors[] = "Password contains sequential characters (e.g., 123, abc).";
        }
        
        if (self::isRepeating($password)) {
            $errors[] = "Password contains repeating characters (e.g., aaa, 111).";
        }
        
        // Check character variety
        $hasUppercase = preg_match('/[A-Z]/', $password);
        $hasLowercase = preg_match('/[a-z]/', $password);
        $hasNumbers = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password);
        
        if (!$hasUppercase) {
            $errors[] = "Password must contain at least one uppercase letter.";
        }
        
        if (!$hasLowercase) {
            $errors[] = "Password must contain at least one lowercase letter.";
        }
        
        if (!$hasNumbers) {
            $errors[] = "Password must contain at least one number.";
        }
        
        if (!$hasSpecial) {
            $errors[] = "Password must contain at least one special character (!@#$%^&*()_+-=[]{}|;:,.<>?).";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => self::calculateStrength($password),
            'suggestions' => self::getSuggestions($password)
        ];
    }
    
    /**
     * Calculate password strength score
     * 
     * @param string $password The password to evaluate
     * @return int Strength level (1-5)
     */
    public static function calculateStrength($password) {
        $score = 0;
        $length = strlen($password);
        
        // Length bonus
        if ($length >= 8) $score += 1;
        if ($length >= 12) $score += 1;
        if ($length >= 16) $score += 1;
        
        // Character variety bonus
        if (preg_match('/[A-Z]/', $password)) $score += 1;
        if (preg_match('/[a-z]/', $password)) $score += 1;
        if (preg_match('/[0-9]/', $password)) $score += 1;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 1;
        
        // Complexity bonus
        if (preg_match('/[A-Z].*[a-z]|[a-z].*[A-Z]/', $password)) $score += 1;
        if (preg_match('/[a-zA-Z].*[0-9]|[0-9].*[a-zA-Z]/', $password)) $score += 1;
        if (preg_match('/[a-zA-Z0-9].*[^A-Za-z0-9]|[^A-Za-z0-9].*[a-zA-Z0-9]/', $password)) $score += 1;
        
        // Penalties
        if (self::isCommonPassword($password)) $score -= 2;
        if (self::isSequential($password)) $score -= 1;
        if (self::isRepeating($password)) $score -= 1;
        
        // Ensure score is within bounds
        return max(1, min(5, $score));
    }
    
    /**
     * Get password strength description
     * 
     * @param int $strength Strength level
     * @return string Human-readable strength description
     */
    public static function getStrengthDescription($strength) {
        switch ($strength) {
            case self::STRENGTH_WEAK:
                return 'Very Weak';
            case self::STRENGTH_FAIR:
                return 'Weak';
            case self::STRENGTH_GOOD:
                return 'Fair';
            case self::STRENGTH_STRONG:
                return 'Strong';
            case self::STRENGTH_VERY_STRONG:
                return 'Very Strong';
            default:
                return 'Unknown';
        }
    }
    
    /**
     * Get password strength color class for UI
     * 
     * @param int $strength Strength level
     * @return string CSS color class
     */
    public static function getStrengthColor($strength) {
        switch ($strength) {
            case self::STRENGTH_WEAK:
                return 'text-red-600';
            case self::STRENGTH_FAIR:
                return 'text-orange-600';
            case self::STRENGTH_GOOD:
                return 'text-yellow-600';
            case self::STRENGTH_STRONG:
                return 'text-blue-600';
            case self::STRENGTH_VERY_STRONG:
                return 'text-green-600';
            default:
                return 'text-gray-600';
        }
    }
    
    /**
     * Get password improvement suggestions
     * 
     * @param string $password The password to analyze
     * @return array Array of improvement suggestions
     */
    public static function getSuggestions($password) {
        $suggestions = [];
        
        if (strlen($password) < 12) {
            $suggestions[] = "Make your password at least 12 characters long.";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $suggestions[] = "Add uppercase letters to make your password stronger.";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $suggestions[] = "Include numbers to increase complexity.";
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $suggestions[] = "Add special characters like !@#$%^&*() for better security.";
        }
        
        if (self::isCommonPassword($password)) {
            $suggestions[] = "Avoid common words and phrases. Use random combinations instead.";
        }
        
        if (self::isSequential($password)) {
            $suggestions[] = "Avoid sequential characters like '123' or 'abc'.";
        }
        
        if (self::isRepeating($password)) {
            $suggestions[] = "Avoid repeating characters like 'aaa' or '111'.";
        }
        
        return $suggestions;
    }
    
    /**
     * Check if password is a common weak password
     * 
     * @param string $password The password to check
     * @return bool True if common password
     */
    private static function isCommonPassword($password) {
        $commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', 'monkey',
            'dragon', 'master', 'hello', 'freedom', 'whatever',
            'qazwsx', 'trustno1', 'jordan', 'joshua', 'michael',
            'michelle', 'charlie', 'andrew', 'matthew', 'jennifer',
            'jessica', 'joshua', 'amanda', 'daniel', 'loves',
            'shadow', 'michael', 'jordan', 'harley', 'hunter',
            '2000', '2001', '2002', '2003', '2004', '2005',
            '2006', '2007', '2008', '2009', '2010', '2011',
            '2012', '2013', '2014', '2015', '2016', '2017',
            '2018', '2019', '2020', '2021', '2022', '2023',
            'admin123', 'root', 'toor', 'guest', 'user',
            'test', 'demo', 'sample', 'example', 'temp',
            'password1', 'password2', 'password3', 'password4',
            '12345678', '1234567890', 'qwertyuiop', 'asdfghjkl',
            'zxcvbnm', 'qwerty123', '123qwe', 'qwe123',
            'admin123', 'administrator', 'superuser', 'superadmin'
        ];
        
        return in_array(strtolower($password), $commonPasswords);
    }
    
    /**
     * Check if password contains sequential characters
     * 
     * @param string $password The password to check
     * @return bool True if contains sequences
     */
    private static function isSequential($password) {
        $sequences = [
            'abcdefghijklmnopqrstuvwxyz',
            'zyxwvutsrqponmlkjihgfedcba',
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'ZYXWVUTSRQPONMLKJIHGFEDCBA',
            '0123456789',
            '9876543210',
            'qwertyuiop',
            'asdfghjkl',
            'zxcvbnm'
        ];
        
        foreach ($sequences as $sequence) {
            for ($i = 0; $i <= strlen($sequence) - 3; $i++) {
                $seq = substr($sequence, $i, 3);
                if (stripos($password, $seq) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if password contains repeating characters
     * 
     * @param string $password The password to check
     * @return bool True if contains repetitions
     */
    private static function isRepeating($password) {
        // Check for 3 or more consecutive identical characters
        for ($i = 0; $i < strlen($password) - 2; $i++) {
            if ($password[$i] === $password[$i + 1] && $password[$i] === $password[$i + 2]) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate a secure random password
     * 
     * @param int $length Password length (default: 12)
     * @param bool $includeSpecial Include special characters (default: true)
     * @return string Generated password
     */
    public static function generateSecurePassword($length = 12, $includeSpecial = true) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        
        if ($includeSpecial) {
            $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
        }
        
        $password = '';
        $charArray = str_split($chars);
        
        // Ensure at least one character from each required category
        $password .= $charArray[array_rand(array_slice($charArray, 0, 26))]; // lowercase
        $password .= $charArray[array_rand(array_slice($charArray, 26, 26))]; // uppercase
        $password .= $charArray[array_rand(array_slice($charArray, 52, 10))]; // number
        
        if ($includeSpecial) {
            $password .= $charArray[array_rand(array_slice($charArray, 62))]; // special
        }
        
        // Fill remaining length with random characters
        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $charArray[array_rand($charArray)];
        }
        
        // Shuffle the password to avoid predictable patterns
        return str_shuffle($password);
    }
    
    /**
     * Validate password change (prevent reuse of recent passwords)
     * 
     * @param string $newPassword New password
     * @param string $oldPassword Old password
     * @return array Validation result
     */
    public static function validatePasswordChange($newPassword, $oldPassword) {
        $errors = [];
        
        // Check if new password is same as old
        if ($newPassword === $oldPassword) {
            $errors[] = "New password must be different from your current password.";
        }
        
        // Check if new password is too similar to old
        if (self::calculateSimilarity($newPassword, $oldPassword) > 0.7) {
            $errors[] = "New password is too similar to your current password.";
        }
        
        // Validate new password strength
        $validation = self::validatePassword($newPassword);
        if (!$validation['valid']) {
            $errors = array_merge($errors, $validation['errors']);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Calculate similarity between two passwords
     * 
     * @param string $password1 First password
     * @param string $password2 Second password
     * @return float Similarity score (0-1, higher = more similar)
     */
    private static function calculateSimilarity($password1, $password2) {
        $len1 = strlen($password1);
        $len2 = strlen($password2);
        
        if ($len1 === 0 || $len2 === 0) {
            return 0;
        }
        
        // Use Levenshtein distance for similarity
        $distance = levenshtein($password1, $password2);
        $maxLength = max($len1, $len2);
        
        return 1 - ($distance / $maxLength);
    }
}

/**
 * Helper functions for easier usage
 */

/**
 * Validate password (shorthand)
 */
function validate_password($password) {
    return PasswordSecurity::validatePassword($password);
}

/**
 * Generate secure password (shorthand)
 */
function generate_secure_password($length = 12, $includeSpecial = true) {
    return PasswordSecurity::generateSecurePassword($length, $includeSpecial);
}

/**
 * Get password strength description (shorthand)
 */
function get_password_strength($strength) {
    return PasswordSecurity::getStrengthDescription($strength);
}

/**
 * Get password strength color (shorthand)
 */
function get_password_strength_color($strength) {
    return PasswordSecurity::getStrengthColor($strength);
}







