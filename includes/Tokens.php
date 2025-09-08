<?php
/**
 * Token management for AuthDocs plugin
 * 
 * @since 1.2.0 Email link actions for accept/re-accept.
 */
declare(strict_types=1);

namespace ProtectedDocs;

class Tokens
{
    private const TOKEN_TTL = 48 * HOUR_IN_SECONDS; // 48 hours
    private const TOKEN_META_PREFIX = '_authdocs_token_used_';
    
    /**
     * Create a signed token for a request action
     */
    public static function create(int $request_id, string $action): array
    {
        $issued_at = time();
        $data = [
            'request_id' => $request_id,
            'action' => $action,
            'issued_at' => $issued_at,
            'ttl' => self::TOKEN_TTL
        ];
        
        $signature = self::generate_signature($data);
        $token = base64_encode(json_encode($data) . '.' . $signature);
        
        return [
            'url' => self::build_action_url($request_id, $action, $token),
            'token' => $token
        ];
    }
    
    /**
     * Verify a token and mark as used if valid
     */
    public static function verify(int $request_id, string $action, string $token): \WP_Error|true
    {
        // Decode token
        $decoded = base64_decode($token);
        if ($decoded === false) {
            return new \WP_Error('invalid_token', 'Invalid token format');
        }
        
        // Split data and signature
        $parts = explode('.', $decoded);
        if (count($parts) !== 2) {
            return new \WP_Error('invalid_token', 'Invalid token structure');
        }
        
        $data_json = $parts[0];
        $signature = $parts[1];
        
        // Parse data
        $data = json_decode($data_json, true);
        if (!$data || !isset($data['request_id'], $data['action'], $data['issued_at'], $data['ttl'])) {
            return new \WP_Error('invalid_token', 'Invalid token data');
        }
        
        // Verify request ID and action
        if ($data['request_id'] !== $request_id || $data['action'] !== $action) {
            return new \WP_Error('invalid_token', 'Token mismatch');
        }
        
        // Check expiration
        if (time() > $data['issued_at'] + $data['ttl']) {
            return new \WP_Error('expired_token', 'Token has expired');
        }
        
        // Verify signature
        if (!self::verify_signature($data, $signature)) {
            return new \WP_Error('invalid_signature', 'Invalid token signature');
        }
        
        // Check if token has been used
        if (self::is_token_used($token)) {
            return new \WP_Error('used_token', 'Token has already been used');
        }
        
        // Mark token as used
        self::mark_token_used($token);
        
        return true;
    }
    
    /**
     * Generate HMAC signature for token data
     */
    private static function generate_signature(array $data): string
    {
        $message = $data['request_id'] . ':' . $data['action'] . ':' . $data['issued_at'];
        $key = get_option('authdocs_secret_key', 'default_secret_key');
        
        return hash_hmac('sha256', $message, $key);
    }
    
    /**
     * Verify HMAC signature
     */
    private static function verify_signature(array $data, string $signature): bool
    {
        $expected = self::generate_signature($data);
        return hash_equals($expected, $signature);
    }
    
    /**
     * Check if token has been used
     */
    private static function is_token_used(string $token): bool
    {
        $token_hash = hash('sha256', $token);
        $meta_key = self::TOKEN_META_PREFIX . $token_hash;
        
        // Check in request meta first
        $used = get_transient($meta_key);
        if ($used !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Mark token as used
     */
    private static function mark_token_used(string $token): void
    {
        $token_hash = hash('sha256', $token);
        $meta_key = self::TOKEN_META_PREFIX . $token_hash;
        
        // Store as transient for 24 hours (longer than token TTL)
        set_transient($meta_key, true, 24 * HOUR_IN_SECONDS);
    }
    
    /**
     * Build action URL with token
     */
    private static function build_action_url(int $request_id, string $action, string $token): string
    {
        return add_query_arg([
            'authdocs_action' => $action,
            'rid' => $request_id,
            'token' => $token
        ], home_url('/'));
    }
}
