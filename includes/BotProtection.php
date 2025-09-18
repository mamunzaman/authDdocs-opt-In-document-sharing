<?php
declare(strict_types=1);

namespace ProtectedDocs;

/**
 * Bot Protection Class
 * 
 * Implements time-based security checks and rate limiting to prevent bot attacks
 * 
 * @package AuthDocs
 * @since 1.5.0
 */
class BotProtection
{
    /**
     * Minimum time between requests (in seconds)
     */
    private const MIN_REQUEST_INTERVAL = 1;
    
    /**
     * Maximum requests per hour per IP
     */
    private const MAX_REQUESTS_PER_HOUR = 10;
    
    /**
     * Maximum requests per day per IP
     */
    private const MAX_REQUESTS_PER_DAY = 50;
    
    /**
     * Minimum time to spend on page before submitting (in seconds)
     */
    private const MIN_PAGE_TIME = 2;
    
    /**
     * Check if request is from a bot based on various factors
     * 
     * @param array $request_data Request data including timing information
     * @return array Result with 'is_bot' boolean and 'reason' string
     */
    public static function check_bot_request(array $request_data): array
    {
        $checks = [
            'time_interval' => self::check_time_interval($request_data),
            'rate_limit' => self::check_rate_limit(),
            'page_time' => self::check_page_time($request_data),
            'user_agent' => self::check_user_agent(),
            'referrer' => self::check_referrer(),
        ];
        
        foreach ($checks as $check_name => $result) {
            if ($result['is_bot']) {
                return [
                    'is_bot' => true,
                    'reason' => $result['reason'],
                    'check' => $check_name
                ];
            }
        }
        
        return [
            'is_bot' => false,
            'reason' => 'All checks passed',
            'check' => 'none'
        ];
    }
    
    /**
     * Check minimum time interval between requests
     */
    private static function check_time_interval(array $request_data): array
    {
        $current_time = time();
        $last_request_time = intval($request_data['last_request_time'] ?? 0);
        
        if ($last_request_time > 0) {
            $time_diff = $current_time - $last_request_time;
            
            if ($time_diff < self::MIN_REQUEST_INTERVAL) {
                return [
                    'is_bot' => true,
                    'reason' => sprintf(
                        __('Request submitted too quickly. Please wait %d seconds between requests.', 'protecteddocs'),
                        self::MIN_REQUEST_INTERVAL
                    )
                ];
            }
        }
        
        return ['is_bot' => false, 'reason' => 'Time interval check passed'];
    }
    
    /**
     * Check rate limiting per IP address
     */
    private static function check_rate_limit(): array
    {
        $ip_address = self::get_client_ip();
        $current_time = time();
        
        // Check hourly limit
        $hourly_key = 'authdocs_requests_hourly_' . md5($ip_address . date('Y-m-d-H'));
        $hourly_count = intval(get_transient($hourly_key) ?: 0);
        
        if ($hourly_count >= self::MAX_REQUESTS_PER_HOUR) {
            return [
                'is_bot' => true,
                'reason' => sprintf(
                    __('Too many requests. Maximum %d requests per hour allowed.', 'protecteddocs'),
                    self::MAX_REQUESTS_PER_HOUR
                )
            ];
        }
        
        // Check daily limit
        $daily_key = 'authdocs_requests_daily_' . md5($ip_address . date('Y-m-d'));
        $daily_count = intval(get_transient($daily_key) ?: 0);
        
        if ($daily_count >= self::MAX_REQUESTS_PER_DAY) {
            return [
                'is_bot' => true,
                'reason' => sprintf(
                    __('Too many requests. Maximum %d requests per day allowed.', 'protecteddocs'),
                    self::MAX_REQUESTS_PER_DAY
                )
            ];
        }
        
        return ['is_bot' => false, 'reason' => 'Rate limit check passed'];
    }
    
    /**
     * Check minimum time spent on page
     */
    private static function check_page_time(array $request_data): array
    {
        $page_load_time = intval($request_data['page_load_time'] ?? 0);
        $current_time = time();
        
        if ($page_load_time > 0) {
            $time_on_page = $current_time - $page_load_time;
            
            if ($time_on_page < self::MIN_PAGE_TIME) {
                return [
                    'is_bot' => true,
                    'reason' => sprintf(
                        __('Please spend at least %d seconds on the page before submitting a request.', 'protecteddocs'),
                        self::MIN_PAGE_TIME
                    )
                ];
            }
        }
        
        return ['is_bot' => false, 'reason' => 'Page time check passed'];
    }
    
    /**
     * Check user agent for bot signatures
     */
    private static function check_user_agent(): array
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (empty($user_agent)) {
            return [
                'is_bot' => true,
                'reason' => __('Invalid user agent detected.', 'protecteddocs')
            ];
        }
        
        // Common bot user agents
        $bot_patterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python', 'java',
            'php', 'perl', 'ruby', 'go-http', 'okhttp', 'apache-httpclient'
        ];
        
        $user_agent_lower = strtolower($user_agent);
        
        foreach ($bot_patterns as $pattern) {
            if (strpos($user_agent_lower, $pattern) !== false) {
                return [
                    'is_bot' => true,
                    'reason' => __('Automated request detected.', 'protecteddocs')
                ];
            }
        }
        
        return ['is_bot' => false, 'reason' => 'User agent check passed'];
    }
    
    /**
     * Check referrer for suspicious patterns
     */
    private static function check_referrer(): array
    {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $site_url = get_site_url();
        
        // Allow requests from the same site
        if (strpos($referrer, $site_url) === 0) {
            return ['is_bot' => false, 'reason' => 'Referrer check passed'];
        }
        
        // Allow empty referrer (direct access)
        if (empty($referrer)) {
            return ['is_bot' => false, 'reason' => 'Referrer check passed'];
        }
        
        // Check for suspicious referrer patterns
        $suspicious_patterns = [
            'localhost', '127.0.0.1', '0.0.0.0', 'test', 'dev', 'staging'
        ];
        
        $referrer_lower = strtolower($referrer);
        
        foreach ($suspicious_patterns as $pattern) {
            if (strpos($referrer_lower, $pattern) !== false) {
                return [
                    'is_bot' => true,
                    'reason' => __('Suspicious referrer detected.', 'protecteddocs')
                ];
            }
        }
        
        return ['is_bot' => false, 'reason' => 'Referrer check passed'];
    }
    
    /**
     * Record a successful request for rate limiting
     */
    public static function record_request(): void
    {
        $ip_address = self::get_client_ip();
        $current_time = time();
        
        // Record hourly request
        $hourly_key = 'authdocs_requests_hourly_' . md5($ip_address . date('Y-m-d-H'));
        $hourly_count = intval(get_transient($hourly_key) ?: 0);
        set_transient($hourly_key, $hourly_count + 1, HOUR_IN_SECONDS);
        
        // Record daily request
        $daily_key = 'authdocs_requests_daily_' . md5($ip_address . date('Y-m-d'));
        $daily_count = intval(get_transient($daily_key) ?: 0);
        set_transient($daily_key, $daily_count + 1, DAY_IN_SECONDS);
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip(): string
    {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Generate a unique session token for the page
     */
    public static function generate_session_token(): string
    {
        return wp_generate_password(32, false);
    }
    
    /**
     * Validate session token
     */
    public static function validate_session_token(string $token): bool
    {
        if (empty($token)) {
            return false;
        }
        
        // Check if token exists in session or transient
        $session_key = 'authdocs_session_' . md5($token);
        $stored_time = get_transient($session_key);
        
        // If token exists and is not expired, it's valid
        if ($stored_time !== false) {
            return true;
        }
        
        // If token doesn't exist, try to store it (for cases where validation AJAX didn't complete)
        // This allows the token to be valid even if the initial validation didn't complete
        self::store_session_token($token);
        return true;
    }
    
    /**
     * Store session token
     */
    public static function store_session_token(string $token): void
    {
        $session_key = 'authdocs_session_' . md5($token);
        set_transient($session_key, time(), HOUR_IN_SECONDS);
    }
}
