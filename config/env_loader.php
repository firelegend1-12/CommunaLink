<?php
/**
 * Environment Variable Loader
 * Loads environment variables from .env file
 */

if (!function_exists('load_env')) {
    /**
     * Load environment variables from .env file
     * 
     * @param string $env_file Path to .env file
     * @return void
     */
    function load_env($env_file = null) {
        if ($env_file === null) {
            $env_file = __DIR__ . '/../.env';
        }
        
        if (!file_exists($env_file)) {
            // .env file doesn't exist, use defaults
            return;
        }
        
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE format
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                // Set environment variable if not already set
                if (!getenv($key)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
    
    /**
     * Get environment variable with optional default
     * 
     * @param string $key Environment variable key
     * @param mixed $default Default value if not set
     * @return mixed Environment variable value or default
     */
    function env($key, $default = null) {
        $value = getenv($key);
        
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }
        
        return $value;
    }
}

// Auto-load .env file when this file is included
load_env();

