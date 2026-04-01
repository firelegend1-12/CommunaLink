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
                
                // .env should be the source of truth for this app runtime.
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
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

    /**
     * Resolve the first non-empty environment variable in order.
     *
     * @param array $keys Environment variable keys ordered by priority
     * @param mixed $default Fallback value when none are set
     * @return mixed
     */
    function env_first(array $keys, $default = null) {
        foreach ($keys as $key) {
            $value = env($key, null);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Canonical resolver for Google Maps API key.
     * Prefers GOOGLE_MAPS_API_KEY and supports MAPS_API_KEY for backward compatibility.
     *
     * @param mixed $default
     * @return mixed
     */
    function maps_api_key($default = '') {
        return env_first(['GOOGLE_MAPS_API_KEY', 'MAPS_API_KEY'], $default);
    }
}

// Auto-load .env file when this file is included
load_env();

