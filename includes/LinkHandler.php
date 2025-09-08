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
        $error_handler = new self();
        $error_handler->render_error($message, $status_code, $document_info);
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
    private function render_error(string $message, int $status_code, array $document_info = []): void
    {
        http_response_code($status_code);
        
        // Get the current color palette from settings
        $settings = new \AuthDocs\Settings();
        $color_palette = $settings->get_color_palette_colors();
        
        // Get recipient email for contact information
        $recipient_emails = $settings->get_access_request_recipients();
        $contact_email = !empty($recipient_emails) ? $recipient_emails[0] : get_option('admin_email');
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('Access Denied', 'protecteddocs'); ?> - AuthDocs</title>
            
            <!-- SEO Protection: Prevent crawling and indexing -->
            <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
            <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
            <meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
            
            <!-- Additional SEO protection -->
            <link rel="canonical" href="<?php echo esc_url(home_url('/')); ?>">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    background: <?php echo esc_attr($color_palette['background']); ?>;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    line-height: 1.6;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                }
                
                .error-container {
                    background: <?php echo esc_attr($color_palette['secondary']); ?>;
                    border: 2px solid <?php echo esc_attr($color_palette['border']); ?>;
                    border-radius: 16px;
                    padding: 48px 32px;
                    text-align: center;
                    max-width: 480px;
                    width: 100%;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
                }
                
                .error-icon {
                    width: 72px;
                    height: 72px;
                    background: <?php echo esc_attr($color_palette['primary']); ?>;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 24px;
                    font-size: 32px;
                    color: white;
                    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
                }
                
                .error-title {
                    font-size: 24px;
                    font-weight: 600;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    margin-bottom: 12px;
                    letter-spacing: -0.3px;
                }
                
                .error-message {
                    font-size: 15px;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    opacity: 0.8;
                    margin-bottom: 32px;
                    line-height: 1.6;
                }
                
                .action-buttons {
                    display: flex;
                    gap: 12px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                
                .btn {
                    padding: 12px 20px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 500;
                    font-size: 14px;
                    transition: all 0.2s ease;
                    border: none;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .btn-primary {
                    background: <?php echo esc_attr($color_palette['primary']); ?>;
                    color: white;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                }
                
                .btn-primary:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    opacity: 0.9;
                }
                
                .btn-secondary {
                    background: <?php echo esc_attr($color_palette['secondary']); ?>;
                    color: <?php echo esc_attr($color_palette['primary']); ?>;
                    border: 2px solid <?php echo esc_attr($color_palette['border']); ?>;
                }
                
                .btn-secondary:hover {
                    background: <?php echo esc_attr($color_palette['primary']); ?>;
                    color: white;
                    transform: translateY(-1px);
                }
                
                .help-text {
                    margin-top: 24px;
                    padding-top: 16px;
                    border-top: 1px solid <?php echo esc_attr($color_palette['border']); ?>;
                    font-size: 13px;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    opacity: 0.7;
                }
                
                .document-info {
                    background: <?php echo esc_attr($color_palette['secondary']); ?>;
                    border: 1px solid <?php echo esc_attr($color_palette['border']); ?>;
                    border-radius: 8px;
                    padding: 16px;
                    margin: 20px 0;
                    text-align: left;
                }
                
                .document-info h3 {
                    font-size: 14px;
                    font-weight: 600;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    margin: 0 0 8px 0;
                }
                
                .document-info p {
                    font-size: 13px;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    opacity: 0.8;
                    margin: 4px 0;
                }
                
                .contact-info {
                    background: <?php echo esc_attr($color_palette['secondary']); ?>;
                    border: 1px solid <?php echo esc_attr($color_palette['border']); ?>;
                    border-radius: 8px;
                    padding: 16px;
                    margin: 20px 0;
                    text-align: center;
                }
                
                .contact-info h3 {
                    font-size: 14px;
                    font-weight: 600;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    margin: 0 0 8px 0;
                }
                
                .contact-info a {
                    color: <?php echo esc_attr($color_palette['primary']); ?>;
                    text-decoration: none;
                    font-weight: 500;
                }
                
                .contact-info a:hover {
                    text-decoration: underline;
                }
                
                @media (max-width: 480px) {
                    .error-container {
                        padding: 32px 20px;
                        margin: 10px;
                    }
                    
                    .error-title {
                        font-size: 20px;
                    }
                    
                    .error-message {
                        font-size: 14px;
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
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z" fill="currentColor"/>
                        <path d="M12 7v6m0 4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                
                <h1 class="error-title"><?php _e('Access Denied', 'protecteddocs'); ?></h1>
                <p class="error-message"><?php echo esc_html($message); ?></p>
                
                <?php if (!empty($document_info)): ?>
                    <div class="document-info">
                        <h3><?php _e('Document Information', 'protecteddocs'); ?></h3>
                        <?php if (!empty($document_info['title'])): ?>
                            <p><strong><?php _e('Document:', 'protecteddocs'); ?></strong> <?php echo esc_html($document_info['title']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($document_info['filename'])): ?>
                            <p><strong><?php _e('File:', 'protecteddocs'); ?></strong> <?php echo esc_html($document_info['filename']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="contact-info">
                    <h3><?php _e('Need Help?', 'protecteddocs'); ?></h3>
                    <p><?php _e('Contact us for assistance:', 'protecteddocs'); ?> <a href="mailto:<?php echo esc_attr($contact_email); ?>"><?php echo esc_html($contact_email); ?></a></p>
                </div>
                
                <div class="action-buttons">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" fill="currentColor"/>
                        </svg>
                        <?php _e('Go Home', 'protecteddocs'); ?>
                    </a>
                </div>
                
                <div class="help-text">
                    <?php _e('If you believe this is an error, please contact the administrator.', 'protecteddocs'); ?>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
