<?php
/**
 * Public link handler for AuthDocs plugin
 * 
 * @since 1.2.0 Email link actions for accept/re-accept.
 */
declare(strict_types=1);

namespace ProtectedDocs;

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
    public static function render_error_page(string $message, string $title = '', int $status_code = 403, array $document_info = []): void
    {
        ErrorPageRenderer::render($message, $title, $status_code, $document_info);
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
    
}
