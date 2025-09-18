<?php
/**
 * Email handling for AuthDocs plugin
 * 
 * @since 1.2.0 Three separate email templates with dynamic placeholders
 */
declare(strict_types=1);

namespace ProtectedDocs;

/**
 * Email handling for AuthDocs plugin
 */
class Email {
    
    private Settings $settings;
    
    public function __construct() {
        $this->settings = new Settings();
    }
    
    /**
     * Send access request email to website owners
     */
    public function send_access_request_email(int $request_id): bool {
        error_log("AuthDocs: Starting send_access_request_email for request ID: {$request_id}");
        
        $template = $this->settings->get_access_request_template();
        error_log("AuthDocs: Access request template retrieved: " . json_encode($template));
        
        $request = Database::get_request_by_id($request_id);
        if (!$request) {
            error_log("AuthDocs: Request not found for ID: {$request_id}");
            $this->log_email_attempt($request_id, 'access_request', '', false, 'Request not found');
            return false;
        }
        
        error_log("AuthDocs: Request found: " . json_encode($request));
        
        $recipients = $this->settings->get_access_request_recipients();
        error_log("AuthDocs: Recipients: " . json_encode($recipients));
        
        if (empty($recipients)) {
            error_log("AuthDocs: No recipients configured");
            $this->log_email_attempt($request_id, 'access_request', '', false, 'No recipients configured');
            return false;
        }
        
        $variables = [
            'name' => $request->requester_name ?? '',
            'email' => $request->requester_email ?? '',
            'file_name' => get_the_title($request->document_id) ?: '',
            'site_name' => get_bloginfo('name'),
            'document_id' => $request->document_id,
            'document_edit_url' => admin_url('post.php?post=' . $request->document_id . '&action=edit')
        ];
        
        error_log("AuthDocs: Email variables: " . json_encode($variables));
        
        $processed_template = $this->settings->process_template($template, $variables);
        error_log("AuthDocs: Processed template: " . json_encode($processed_template));
        
        $subject = $processed_template['subject'];
        $body = $processed_template['body'];
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        $success = true;
        foreach ($recipients as $recipient) {
            // Resolve placeholder recipients
            $resolved_recipient = $this->settings->resolve_recipient_email($recipient, $request_id);
            if (empty($resolved_recipient) || !is_email($resolved_recipient)) {
                error_log("AuthDocs: Invalid recipient: {$recipient} -> {$resolved_recipient}");
                $this->log_email_attempt($request_id, 'access_request', $resolved_recipient, false, 'Invalid recipient: ' . $resolved_recipient);
                $success = false;
                continue;
            }
            
            error_log("AuthDocs: Attempting to send access request email to: {$resolved_recipient}");
            $result = wp_mail($resolved_recipient, $subject, $body, $headers);
            
            error_log("AuthDocs: wp_mail result: " . ($result ? 'true' : 'false'));
            
            $this->log_email_attempt($request_id, 'access_request', $resolved_recipient, $result, $result ? '' : 'wp_mail failed');
            
            if (!$result) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Send auto-response email to requester
     */
    public function send_auto_response_email(int $request_id): bool {
        $template = $this->settings->get_auto_response_template();
        
        if (empty($template['enabled'])) {
            return true; // Not enabled, consider it successful
        }
        
        $request = Database::get_request_by_id($request_id);
        if (!$request) {
            $this->log_email_attempt($request_id, 'auto_response', '', false, 'Request not found');
            return false;
        }
        
        $recipient = $request->requester_email;
        
        if (empty($recipient) || !is_email($recipient)) {
            $this->log_email_attempt($request_id, 'auto_response', $recipient, false, 'Invalid recipient: ' . $recipient);
            return false;
        }
        
        $variables = [
            'name' => $request->requester_name ?? '',
            'email' => $request->requester_email ?? '',
            'file_name' => get_the_title($request->document_id) ?: '',
            'site_name' => get_bloginfo('name')
        ];
        
        $processed_template = $this->settings->process_template($template, $variables);
        
        $subject = $processed_template['subject'];
        $body = $processed_template['body'];
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        $result = wp_mail($recipient, $subject, $body, $headers);
        
        $this->log_email_attempt($request_id, 'auto_response', $recipient, $result, $result ? '' : 'wp_mail failed');
        
        return $result;
    }
    
    /**
     * Send grant/decline email to requester
     */
    public function send_grant_decline_email(int $request_id, bool $granted): bool {
        error_log("AuthDocs: Starting send_grant_decline_email for request ID: {$request_id}, granted: " . ($granted ? 'true' : 'false'));
        
        $template = $this->settings->get_grant_decline_template();
        error_log("AuthDocs: Grant/decline template retrieved: " . json_encode($template));
        
        $request = Database::get_request_by_id($request_id);
        if (!$request) {
            error_log("AuthDocs: Request not found for ID: {$request_id}");
            $this->log_email_attempt($request_id, 'grant_decline', '', false, 'Request not found');
            return false;
        }
        
        error_log("AuthDocs: Request found: " . json_encode($request));
        
        $recipients = $this->settings->get_grant_decline_recipients();
        error_log("AuthDocs: Recipients: " . json_encode($recipients));
        
        if (empty($recipients)) {
            error_log("AuthDocs: No recipients configured");
            $this->log_email_attempt($request_id, 'grant_decline', '', false, 'No recipients configured');
            return false;
        }
        
        $status = $granted ? 'Granted' : 'Declined';
        $status_color = $granted ? '#28a745' : '#dc3545';
        
        $variables = [
            'name' => $request->requester_name ?? '',
            'email' => $request->requester_email ?? '',
            'file_name' => get_the_title($request->document_id) ?: '',
            'site_name' => get_bloginfo('name'),
            'status' => $status,
            'status_color' => $status_color,
            'link' => $granted ? $this->generate_secure_link($request_id) : ''
        ];
        
        error_log("AuthDocs: Email variables: " . json_encode($variables));
        
        $processed_template = $this->process_grant_decline_template($template, $variables, $granted);
        error_log("AuthDocs: Processed template: " . json_encode($processed_template));
        
        $subject = $processed_template['subject'];
        $body = $processed_template['body'];
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        $success = true;
        foreach ($recipients as $recipient) {
            // Resolve placeholder recipients
            $resolved_recipient = $this->settings->resolve_recipient_email($recipient, $request_id);
            if (empty($resolved_recipient) || !is_email($resolved_recipient)) {
                error_log("AuthDocs: Invalid recipient: {$recipient} -> {$resolved_recipient}");
                $this->log_email_attempt($request_id, 'grant_decline', $resolved_recipient, false, 'Invalid recipient: ' . $resolved_recipient);
                $success = false;
                continue;
            }
            
            error_log("AuthDocs: Attempting to send grant/decline email to: {$resolved_recipient}");
            error_log("AuthDocs: Email subject: {$subject}");
            error_log("AuthDocs: Email body length: " . strlen($body));
            
            $result = wp_mail($resolved_recipient, $subject, $body, $headers);
            
            error_log("AuthDocs: wp_mail result: " . ($result ? 'true' : 'false'));
            if (!$result) {
                error_log("AuthDocs: wp_mail failed. Last error: " . (function_exists('wp_mail') ? 'wp_mail function exists' : 'wp_mail function not available'));
            }
            
            $this->log_email_attempt($request_id, 'grant_decline', $resolved_recipient, $result, $result ? '' : 'wp_mail failed');
            
            if (!$result) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Process grant/decline template with conditional logic
     */
    private function process_grant_decline_template(array $template, array $variables, bool $granted): array {
        $subject = $template['subject'] ?? '';
        $body = $template['body'] ?? '';
        
        // Replace variables in subject
        $subject = $this->replace_variables($subject, $variables);
        
        // Process conditional logic in body
        $body = $this->process_conditional_template($body, $variables, $granted);
        
        return [
            'subject' => $subject,
            'body' => $body
        ];
    }
    
    /**
     * Process conditional template logic
     */
    private function process_conditional_template(string $template, array $variables, bool $granted): string {
        // Replace simple variables first
        $template = $this->replace_variables($template, $variables);
        
        // Process {{#if granted}} blocks
        $template = preg_replace_callback(
            '/\{\{#if granted\}\}(.*?)\{\{else\}\}(.*?)\{\{\/if\}\}/s',
            function($matches) use ($granted) {
                return $granted ? $matches[1] : $matches[2];
            },
            $template
        );
        
        // Process {{#if granted}} blocks without else
        $template = preg_replace_callback(
            '/\{\{#if granted\}\}(.*?)\{\{\/if\}\}/s',
            function($matches) use ($granted) {
                return $granted ? $matches[1] : '';
            },
            $template
        );
        
        return $template;
    }
    
    /**
     * Replace variables in text
     */
    private function replace_variables(string $text, array $variables): string {
        $replacements = [
            '{{name}}' => $variables['name'] ?? '',
            '{{email}}' => $variables['email'] ?? '',
            '{{file_name}}' => $variables['file_name'] ?? '',
            '{{site_name}}' => $variables['site_name'] ?? get_bloginfo('name'),
            '{{status}}' => $variables['status'] ?? '',
            '{{status_color}}' => $variables['status_color'] ?? '',
            '{{link}}' => $variables['link'] ?? '',
            '{{document_edit_url}}' => $variables['document_edit_url'] ?? ''
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
    
    /**
     * Generate fresh secure viewer link for a request
     */
    private function generate_secure_link(int $request_id): string {
        $request = Database::get_request_by_id($request_id);
        if (!$request) {
            error_log("AuthDocs: generate_secure_link - Request not found for ID: {$request_id}");
            return '';
        }
        
        // If no secure_hash exists, generate one
        if (empty($request->secure_hash)) {
            error_log("AuthDocs: generate_secure_link - No secure_hash found for request ID: {$request_id}, generating new one");
            $secure_hash = Database::generate_secure_hash($request_id);
            
            // Update the request with the new hash
            global $wpdb;
            $table_name = $wpdb->prefix . 'authdocs_requests';
            $wpdb->update(
                $table_name,
                ['secure_hash' => $secure_hash],
                ['id' => $request_id],
                ['%s'],
                ['%d']
            );
            
            $request->secure_hash = $secure_hash;
            error_log("AuthDocs: generate_secure_link - Generated and saved new hash for request ID: {$request_id}");
        }
        
        // Generate fresh token for the viewer
        $token = Tokens::generate_download_token(intval($request->document_id));
        
        $link = add_query_arg([
            'authdocs_viewer' => '1',
            'document_id' => $request->document_id,
            'token' => $token,
            'hash' => $request->secure_hash,
            'email' => $request->requester_email,
            'request_id' => $request->id
        ], home_url());
        
        error_log("AuthDocs: generate_secure_link - Generated link for request ID: {$request_id}: " . substr($link, 0, 100) . "...");
        
        return $link;
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
     * Send test email for access request template
     */
    public function send_test_access_request_email(): bool {
        $template = $this->settings->get_access_request_template();
        
        $variables = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'file_name' => 'Sample Document.pdf',
            'site_name' => get_bloginfo('name')
        ];
        
        $processed_template = $this->settings->process_template($template, $variables);
        
        $subject = $processed_template['subject'];
        $body = $processed_template['body'];
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        return wp_mail(get_option('admin_email'), 'TEST ACCESS REQUEST: ' . $subject, $body, $headers);
    }
    
    /**
     * Send test email for auto-response template
     */
    public function send_test_auto_response_email(): bool {
        $template = $this->settings->get_auto_response_template();
        
        if (empty($template['enabled'])) {
            return false; // Not enabled
        }
        
        $variables = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'file_name' => 'Sample Document.pdf',
            'site_name' => get_bloginfo('name')
        ];
        
        $processed_template = $this->settings->process_template($template, $variables);
        
        $subject = $processed_template['subject'];
        $body = $processed_template['body'];
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        return wp_mail(get_option('admin_email'), 'TEST AUTO-RESPONSE: ' . $subject, $body, $headers);
    }
    
    /**
     * Send test email for grant/decline template
     */
    public function send_test_grant_decline_email(bool $granted = true): bool {
        $template = $this->settings->get_grant_decline_template();
        
        $status = $granted ? 'Granted' : 'Declined';
        $status_color = $granted ? '#28a745' : '#dc3545';
        
        $variables = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'file_name' => 'Sample Document.pdf',
            'site_name' => get_bloginfo('name'),
            'status' => $status,
            'status_color' => $status_color,
            'link' => $granted ? 'https://example.com/authdocs/download?hash=test123&file=sample.pdf' : ''
        ];
        
        $processed_template = $this->process_grant_decline_template($template, $variables, $granted);
        
        $subject = $processed_template['subject'];
        $body = $processed_template['body'];
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        $test_type = $granted ? 'GRANT' : 'DECLINE';
        return wp_mail(get_option('admin_email'), 'TEST ' . $test_type . ': ' . $subject, $body, $headers);
    }
    
    // Legacy methods for backward compatibility
    public function send_access_granted_email(int $request_id): bool {
        return $this->send_grant_decline_email($request_id, true);
    }
    
    public function send_autoresponder_email(int $request_id): bool {
        return $this->send_auto_response_email($request_id);
    }
    
    public function send_admin_notification_email(string $requester_name, string $requester_email, string $document_title, string $document_id): bool {
        // This method is now handled by send_access_request_email
        // Find the request ID and call the new method
        global $wpdb;
        $table_name = $wpdb->prefix . 'authdocs_requests';
        
        $request_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE document_id = %s AND requester_email = %s ORDER BY id DESC LIMIT 1",
            $document_id,
            $requester_email
        ));
        
        if ($request_id) {
            return $this->send_access_request_email((int) $request_id);
        }
        
        return false;
    }
    
    public function send_test_email(): bool {
        return $this->send_test_grant_decline_email(true);
    }
    
    public function send_test_autoresponder_email(): bool {
        return $this->send_test_auto_response_email();
    }
}