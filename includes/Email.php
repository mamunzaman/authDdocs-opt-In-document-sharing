<?php
/**
 * Email handling for AuthDocs plugin
 * 
 * @since 1.1.0 Email logic separation; new autoresponder recipient; trigger fixes.
 */
declare(strict_types=1);

namespace AuthDocs;

/**
 * Email handling for AuthDocs plugin
 */
class Email {
    
    private Settings $settings;
    
    public function __construct() {
        $this->settings = new Settings();
    }
    
    /**
     * Send document access granted email
     */
    public function send_access_granted_email(int $request_id): bool {
        $template = $this->settings->get_email_template();
        $request = Database::get_request_by_id($request_id);
        
        if (!$request) {
            $this->log_email_attempt($request_id, 'access_granted', '', false, 'Request not found');
            return false;
        }
        
        // Resolve recipient
        $recipient_template = $this->settings->get_access_granted_recipient_email();
        $recipient = $this->settings->resolve_recipient_email($recipient_template, $request_id);
        
        if (empty($recipient) || !is_email($recipient)) {
            $this->log_email_attempt($request_id, 'access_granted', $recipient, false, 'Invalid recipient: ' . $recipient);
            return false;
        }
        
        $variables = [
            'name' => $request->requester_name ?? '',
            'email' => $request->requester_email ?? '',
            'link' => $this->generate_secure_link($request_id)
        ];
        
        $processed_template = $this->settings->process_template($template, $variables);
        
        $subject = $processed_template['subject'];
        $body = $processed_template['body'];
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        $result = wp_mail($recipient, $subject, $body, $headers);
        
        $this->log_email_attempt($request_id, 'access_granted', $recipient, $result, $result ? '' : 'wp_mail failed');
        
        return $result;
    }
    
    /**
     * Send autoresponder email
     */
    public function send_autoresponder_email(int $request_id): bool {
        $template = $this->settings->get_autoresponder_template();
        
        if (empty($template['enabled'])) {
            return true; // Not enabled, consider it successful
        }
        
        $request = Database::get_request_by_id($request_id);
        if (!$request) {
            $this->log_email_attempt($request_id, 'autoresponder', '', false, 'Request not found');
            return false;
        }
        
        // Resolve recipient
        $recipient_template = $this->settings->get_autoresponder_recipient_email();
        $recipient = $this->settings->resolve_recipient_email($recipient_template, $request_id);
        
        if (empty($recipient) || !is_email($recipient)) {
            $this->log_email_attempt($request_id, 'autoresponder', $recipient, false, 'Invalid recipient: ' . $recipient);
            return false;
        }
        
        $variables = [
            'name' => $request->requester_name ?? '',
            'email' => $request->requester_email ?? '',
            'document_title' => get_the_title($request->document_id) ?: '',
            'site_name' => get_bloginfo('name')
        ];
        
        $processed_template = $this->settings->process_autoresponder_template($template, $variables);
        
        $subject = $processed_template['subject'];
        $body = $processed_template['body'];
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        $result = wp_mail($recipient, $subject, $body, $headers);
        
        $this->log_email_attempt($request_id, 'autoresponder', $recipient, $result, $result ? '' : 'wp_mail failed');
        
        return $result;
    }
    
    /**
     * Send admin notification email
     */
    public function send_admin_notification_email(string $requester_name, string $requester_email, string $document_title, string $document_id): bool {
        $recipients = $this->settings->get_recipient_emails();
        
        if (empty($recipients)) {
            return true; // No recipients configured
        }
        
        $subject = sprintf(__('New Document Access Request: %s', 'authdocs'), $document_title);
        
        $body = $this->get_admin_notification_body($requester_name, $requester_email, $document_title, $document_id);
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        $success = true;
        foreach ($recipients as $recipient) {
            if (!wp_mail($recipient, $subject, $body, $headers)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get admin notification email body
     */
    private function get_admin_notification_body(string $requester_name, string $requester_email, string $document_title, string $document_id): string {
        $admin_url = admin_url('edit.php?post_type=document&page=authdocs-requests');
        
        // Generate action links
        $request_id = $this->get_request_id_by_document_and_email($document_id, $requester_email);
        $accept_link = '';
        $reaccept_link = '';
        
        if ($request_id) {
            $accept_token = Tokens::create($request_id, 'accept');
            $reaccept_token = Tokens::create($request_id, 'reaccept');
            
            $accept_link = $accept_token['url'];
            $reaccept_link = $reaccept_token['url'];
        }
        
        $action_links_html = '';
        if ($accept_link && $reaccept_link) {
            $action_links_html = '
            <div style="background: #fff; padding: 20px; border-radius: 6px; border: 1px solid #dee2e6; margin: 20px 0;">
                <h3 style="margin-top: 0; color: #007cba;">Quick Actions</h3>
                <p style="margin-bottom: 15px;">Click one of the links below to immediately grant access:</p>
                <div style="text-align: center;">
                    <a href="' . esc_url($accept_link) . '" style="display: inline-block; background: #28a745; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 500; margin: 0 10px;">Accept Request</a>
                    <a href="' . esc_url($reaccept_link) . '" style="display: inline-block; background: #17a2b8; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 500; margin: 0 10px;">Re-accept Request</a>
                </div>
                <p style="margin-top: 15px; font-size: 12px; color: #6c757d;">These links are secure and will expire in 48 hours. Each link can only be used once.</p>
            </div>';
        }
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Access Request</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fa; padding: 30px; border-radius: 8px; border-left: 4px solid #007cba;">
        <h1 style="color: #007cba; margin-top: 0; font-size: 24px;">New Document Access Request</h1>
        
        <p>A new request for document access has been submitted:</p>
        
        <div style="background: #fff; padding: 20px; border-radius: 6px; border: 1px solid #dee2e6; margin: 20px 0;">
            <p><strong>Requester Name:</strong> ' . esc_html($requester_name) . '</p>
            <p><strong>Requester Email:</strong> ' . esc_html($requester_email) . '</p>
            <p><strong>Document:</strong> ' . esc_html($document_title) . '</p>
            <p><strong>Document ID:</strong> ' . esc_html($document_id) . '</p>
            <p><strong>Request Date:</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . '</p>
        </div>
        
        ' . $action_links_html . '
        
        <p><a href="' . esc_url($admin_url) . '" style="display: inline-block; background: #007cba; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 500;">Review Request</a></p>
        
        <p>You can review and manage this request from your WordPress admin panel.</p>
    </div>
</body>
</html>';
    }
    
    /**
     * Generate secure download link for a request
     */
    private function generate_secure_link(int $request_id): string {
        $request = Database::get_request_by_id($request_id);
        if (!$request || empty($request->secure_hash)) {
            return '';
        }
        
        return add_query_arg([
            'authdocs_download' => $request->document_id,
            'hash' => $request->secure_hash,
            'filename' => $this->get_document_filename($request->document_id)
        ], home_url());
    }
    
    /**
     * Get document filename for download link
     */
    private function get_document_filename(int $document_id): string {
        $file_id = get_post_meta($document_id, '_authdocs_file_id', true);
        if (!$file_id) {
            return '';
        }
        
        $file_path = get_attached_file($file_id);
        return basename($file_path);
    }
    
    /**
     * Log email attempt
     */
    private function log_email_attempt(int $request_id, string $template_key, string $recipient, bool $success, string $error_message = ''): void {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'template_key' => $template_key,
            'recipient' => $recipient,
            'success' => $success,
            'error_message' => $error_message
        ];
        
        // Store in request meta
        update_post_meta($request_id, '_authdocs_email_log', $log_entry);
        
        // Also log to WordPress error log for debugging
        if (!$success) {
            error_log(sprintf(
                'AuthDocs Email Error: Request %d, Template %s, Recipient %s, Error: %s',
                $request_id,
                $template_key,
                $recipient,
                $error_message
            ));
        }
    }
    
    /**
     * Get request ID by document ID and requester email
     */
    private function get_request_id_by_document_and_email(string $document_id, string $requester_email): ?int {
        global $wpdb;
        $table_name = $wpdb->prefix . 'authdocs_requests';
        
        $request_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE document_id = %s AND requester_email = %s ORDER BY id DESC LIMIT 1",
            $document_id,
            $requester_email
        ));
        
        return $request_id ? (int) $request_id : null;
    }
    
    /**
     * Send test email to admin
     */
    public function send_test_email(): bool {
        $template = $this->settings->get_email_template();
        
        $variables = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'link' => 'https://example.com/authdocs/download?hash=test123&file=sample.pdf'
        ];
        
        $processed_template = $this->settings->process_template($template, $variables);
        
        $subject = $processed_template['subject'];
        $body = $processed_template['body'];
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        return wp_mail(get_option('admin_email'), 'TEST: ' . $subject, $body, $headers);
    }
    
    /**
     * Send test autoresponder email to admin
     */
    public function send_test_autoresponder_email(): bool {
        $template = $this->settings->get_autoresponder_template();
        
        if (empty($template['enabled'])) {
            return false; // Not enabled
        }
        
        $variables = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'document_title' => 'Sample Document.pdf',
            'site_name' => get_bloginfo('name')
        ];
        
        $processed_template = $this->settings->process_autoresponder_template($template, $variables);
        
        $subject = $processed_template['subject'];
        $body = $processed_template['body'];
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        return wp_mail(get_option('admin_email'), 'TEST AUTORESPONDER: ' . $subject, $body, $headers);
    }
}
