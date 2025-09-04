<?php
/**
 * Public link handler for AuthDocs plugin
 * 
 * @since 1.2.0 Email link actions for accept/re-accept.
 */
declare(strict_types=1);

namespace AuthDocs;

class LinkHandler
{
    public function __construct()
    {
        // Hook with priority 20 to ensure other plugins have initialized
        // Only processes action links when specific query parameters are present
        add_action('init', [$this, 'handle_action_links'], 20);
    }
    
    /**
     * Public method to render error pages (can be called from other classes)
     */
    public static function render_error_page(string $message, string $title = '', int $status_code = 403): void
    {
        $error_handler = new self();
        $error_handler->render_error($message, $status_code);
    }
    
    /**
     * Handle email action links
     */
    public function handle_action_links(): void
    {
        // Only process on frontend, not admin pages
        if (is_admin()) {
            return;
        }
        
        // Only process when all required query parameters are present
        if (!isset($_GET['authdocs_action'], $_GET['rid'], $_GET['token'])) {
            return;
        }
        
        $action = $_GET['authdocs_action'];
        $request_id = $_GET['rid'];
        $token = $_GET['token'];
        
        if (!in_array($action, ['accept', 'reaccept']) || empty($request_id) || empty($token)) {
            return;
        }
        
        // Sanitize inputs
        $request_id = intval($request_id);
        $action = sanitize_text_field($action);
        $token = sanitize_text_field($token);
        
        if ($request_id <= 0) {
            $this->render_error('Invalid request ID', 400);
            return;
        }
        
        // Verify token
        $verification = Tokens::verify($request_id, $action, $token);
        if (is_wp_error($verification)) {
            $this->render_error($verification->get_error_message(), 403);
            return;
        }
        
        // Get request and store old status
        $request = Database::get_request_by_id($request_id);
        if (!$request) {
            $this->render_error('Request not found', 404);
            return;
        }
        
        $old_status = $request->status ?? '';
        
        // Update status to accepted
        $result = Database::update_request_status($request_id, 'accepted');
        if (!$result) {
            $this->render_error('Failed to update request status', 500);
            return;
        }
        
        // Fire status change hook
        do_action('authdocs/request_status_changed', $request_id, $old_status, 'accepted');
        
        // Send grant email
        $email = new Email();
        $email_result = $email->send_grant_decline_email($request_id, true);
        
        // Log the action
        $this->log_email_link_action($request_id, $action, $email_result);
        
        // Render success page
        $this->render_success($request_id, $action);
        exit;
    }
    
    /**
     * Log email link action
     */
    private function log_email_link_action(int $request_id, string $action, bool $email_sent): void
    {
        $log_entry = [
            'event' => 'email_link_action',
            'action' => $action,
            'sent' => $email_sent ? 'success' : 'error',
            'recipient' => '', // Will be filled by email logging
            'timestamp' => current_time('mysql'),
            'message' => sprintf('Request %s via email link action %s', $email_sent ? 'accepted' : 'failed to send email', $action)
        ];
        
        // Store in request meta
        update_post_meta($request_id, '_authdocs_email_link_log', $log_entry);
    }
    
    /**
     * Render success page
     */
    private function render_success(int $request_id, string $action): void
    {
        $action_text = $action === 'reaccept' ? 're-accepted' : 'accepted';
        
        http_response_code(200);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Access Granted - AuthDocs</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 50px auto; padding: 20px; }
                .success-box { background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 30px; text-align: center; }
                .success-icon { font-size: 48px; color: #155724; margin-bottom: 20px; }
                h1 { color: #155724; margin: 0 0 20px 0; }
                p { margin: 0 0 15px 0; }
                .back-link { margin-top: 30px; }
                .back-link a { color: #007cba; text-decoration: none; }
                .back-link a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class="success-box">
                <div class="success-icon">✓</div>
                <h1>Access Granted</h1>
                <p>The document access request has been <strong><?php echo esc_html($action_text); ?></strong> successfully.</p>
                <p>The access granted email has been sent to the requester.</p>
                <div class="back-link">
                    <a href="<?php echo esc_url(home_url('/')); ?>">← Back to Home</a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Render error page
     */
    private function render_error(string $message, int $status_code): void
    {
        http_response_code($status_code);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('Access Denied', 'authdocs'); ?> - AuthDocs</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    line-height: 1.6;
                }
                
                .error-container {
                    background: rgba(255, 255, 255, 0.95);
                    backdrop-filter: blur(10px);
                    border-radius: 20px;
                    padding: 60px 40px;
                    text-align: center;
                    max-width: 500px;
                    width: 100%;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                }
                
                .error-icon {
                    width: 80px;
                    height: 80px;
                    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 30px;
                    font-size: 36px;
                    color: white;
                    box-shadow: 0 10px 20px rgba(255, 107, 107, 0.3);
                }
                
                .error-title {
                    font-size: 28px;
                    font-weight: 700;
                    color: #2c3e50;
                    margin-bottom: 15px;
                    letter-spacing: -0.5px;
                }
                
                .error-message {
                    font-size: 16px;
                    color: #7f8c8d;
                    margin-bottom: 40px;
                    line-height: 1.7;
                }
                
                .action-buttons {
                    display: flex;
                    gap: 15px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                
                .btn {
                    padding: 12px 24px;
                    border-radius: 50px;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 14px;
                    transition: all 0.3s ease;
                    border: none;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .btn-primary {
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                }
                
                .btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
                }
                
                .btn-secondary {
                    background: rgba(255, 255, 255, 0.8);
                    color: #667eea;
                    border: 2px solid rgba(102, 126, 234, 0.2);
                }
                
                .btn-secondary:hover {
                    background: rgba(102, 126, 234, 0.1);
                    transform: translateY(-2px);
                }
                
                .help-text {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid rgba(0, 0, 0, 0.1);
                    font-size: 14px;
                    color: #95a5a6;
                }
                
                @media (max-width: 480px) {
                    .error-container {
                        padding: 40px 20px;
                    }
                    
                    .error-title {
                        font-size: 24px;
                    }
                    
                    .action-buttons {
                        flex-direction: column;
                        align-items: center;
                    }
                    
                    .btn {
                        width: 100%;
                        max-width: 200px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/>
                    </svg>
                </div>
                
                <h1 class="error-title"><?php _e('Access Denied', 'authdocs'); ?></h1>
                <p class="error-message"><?php echo esc_html($message); ?></p>
                
                <div class="action-buttons">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" fill="currentColor"/>
                        </svg>
                        <?php _e('Go Home', 'authdocs'); ?>
                    </a>
                </div>
                
                <div class="help-text">
                    <?php _e('If you believe this is an error, please contact the administrator.', 'authdocs'); ?>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
