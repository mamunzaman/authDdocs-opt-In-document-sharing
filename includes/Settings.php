<?php
/**
 * Settings management for AuthDocs plugin
 * 
 * @since 1.2.0 Three separate email templates with dynamic placeholders
 */
declare(strict_types=1);

namespace AuthDocs;

/**
 * Settings management for AuthDocs plugin
 */
class Settings {
    
    private const OPTION_GROUP = 'authdocs_options';
    private const ACCESS_REQUEST_TEMPLATE_NAME = 'authdocs_access_request_template';
    private const AUTO_RESPONSE_TEMPLATE_NAME = 'authdocs_auto_response_template';
    private const GRANT_DECLINE_TEMPLATE_NAME = 'authdocs_grant_decline_template';
    private const ACCESS_REQUEST_RECIPIENTS_NAME = 'authdocs_access_request_recipients';
    private const GRANT_DECLINE_RECIPIENTS_NAME = 'authdocs_grant_decline_recipients';
    private const SECRET_KEY_OPTION_NAME = 'authdocs_secret_key';
    
    public function __construct() {
        add_action('admin_init', [$this, 'init_settings']);
    }
    
    /**
     * Initialize settings
     */
    public function init_settings(): void {
        register_setting(
            self::OPTION_GROUP,
            self::ACCESS_REQUEST_TEMPLATE_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_email_template'],
                'default' => $this->get_default_access_request_template()
            ]
        );
        
        register_setting(
            self::OPTION_GROUP,
            self::AUTO_RESPONSE_TEMPLATE_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_email_template'],
                'default' => $this->get_default_auto_response_template()
            ]
        );
        
        register_setting(
            self::OPTION_GROUP,
            self::GRANT_DECLINE_TEMPLATE_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_email_template'],
                'default' => $this->get_default_grant_decline_template()
            ]
        );
        
        register_setting(
            self::OPTION_GROUP,
            self::ACCESS_REQUEST_RECIPIENTS_NAME,
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_recipient_emails'],
                'default' => get_option('admin_email')
            ]
        );
        
        register_setting(
            self::OPTION_GROUP,
            self::GRANT_DECLINE_RECIPIENTS_NAME,
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_recipient_emails'],
                'default' => '{{email}}'
            ]
        );
        
        // Register secret key for token signing
        $secret_key = get_option(self::SECRET_KEY_OPTION_NAME);
        if (empty($secret_key)) {
            $secret_key = wp_generate_password(64, false);
            update_option(self::SECRET_KEY_OPTION_NAME, $secret_key);
        }
        
        // Access Request Email Section
        add_settings_section(
            'authdocs_access_request_section',
            __('Access Request Email Template', 'authdocs'),
            [$this, 'render_access_request_section_description'],
            'authdocs-settings'
        );
        
        add_settings_field(
            'access_request_subject',
            __('Email Subject', 'authdocs'),
            [$this, 'render_access_request_subject_field'],
            'authdocs-settings',
            'authdocs_access_request_section'
        );
        
        add_settings_field(
            'access_request_body',
            __('Email Body (HTML)', 'authdocs'),
            [$this, 'render_access_request_body_field'],
            'authdocs-settings',
            'authdocs_access_request_section'
        );
        
        add_settings_field(
            'access_request_recipients',
            __('Recipient Email Addresses', 'authdocs'),
            [$this, 'render_access_request_recipients_field'],
            'authdocs-settings',
            'authdocs_access_request_section'
        );
        
        add_settings_field(
            'access_request_variables',
            __('Available Variables', 'authdocs'),
            [$this, 'render_access_request_variables_help'],
            'authdocs-settings',
            'authdocs_access_request_section'
        );
        
        // Auto-Response Email Section
        add_settings_section(
            'authdocs_auto_response_section',
            __('Auto-Response Email Template', 'authdocs'),
            [$this, 'render_auto_response_section_description'],
            'authdocs-settings'
        );
        
        add_settings_field(
            'auto_response_enable',
            __('Enable Auto-Response', 'authdocs'),
            [$this, 'render_auto_response_enable_field'],
            'authdocs-settings',
            'authdocs_auto_response_section'
        );
        
        add_settings_field(
            'auto_response_subject',
            __('Email Subject', 'authdocs'),
            [$this, 'render_auto_response_subject_field'],
            'authdocs-settings',
            'authdocs_auto_response_section'
        );
        
        add_settings_field(
            'auto_response_body',
            __('Email Body (HTML)', 'authdocs'),
            [$this, 'render_auto_response_body_field'],
            'authdocs-settings',
            'authdocs_auto_response_section'
        );
        
        add_settings_field(
            'auto_response_variables',
            __('Available Variables', 'authdocs'),
            [$this, 'render_auto_response_variables_help'],
            'authdocs-settings',
            'authdocs_auto_response_section'
        );
        
        // Grant/Decline Email Section
        add_settings_section(
            'authdocs_grant_decline_section',
            __('Grant/Decline Email Template', 'authdocs'),
            [$this, 'render_grant_decline_section_description'],
            'authdocs-settings'
        );
        
        add_settings_field(
            'grant_decline_subject',
            __('Email Subject', 'authdocs'),
            [$this, 'render_grant_decline_subject_field'],
            'authdocs-settings',
            'authdocs_grant_decline_section'
        );
        
        add_settings_field(
            'grant_decline_body',
            __('Email Body (HTML)', 'authdocs'),
            [$this, 'render_grant_decline_body_field'],
            'authdocs-settings',
            'authdocs_grant_decline_section'
        );
        
        add_settings_field(
            'grant_decline_recipients',
            __('Recipient Email Addresses', 'authdocs'),
            [$this, 'render_grant_decline_recipients_field'],
            'authdocs-settings',
            'authdocs_grant_decline_section'
        );
        
        add_settings_field(
            'grant_decline_variables',
            __('Available Variables', 'authdocs'),
            [$this, 'render_grant_decline_variables_help'],
            'authdocs-settings',
            'authdocs_grant_decline_section'
        );
    }
    
    /**
     * Get default access request template
     */
    private function get_default_access_request_template(): array {
        return [
            'subject' => __('New Document Access Request: {{file_name}}', 'authdocs'),
            'body' => $this->get_default_access_request_body_html()
        ];
    }
    
    /**
     * Get default auto-response template
     */
    private function get_default_auto_response_template(): array {
        return [
            'enabled' => false,
            'subject' => __('Document Access Request Received - {{site_name}}', 'authdocs'),
            'body' => $this->get_default_auto_response_body_html()
        ];
    }
    
    /**
     * Get default grant/decline template
     */
    private function get_default_grant_decline_template(): array {
        return [
            'subject' => __('Document Access {{status}} - {{file_name}}', 'authdocs'),
            'body' => $this->get_default_grant_decline_body_html()
        ];
    }
    
    /**
     * Get default access request body HTML
     */
    private function get_default_access_request_body_html(): string {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Document Access Request</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fa; padding: 30px; border-radius: 8px; border-left: 4px solid #007cba;">
        <h1 style="color: #007cba; margin-top: 0; font-size: 24px;">New Document Access Request</h1>
        
        <p>A new request for document access has been submitted:</p>
        
        <div style="background: #fff; padding: 20px; border-radius: 6px; border: 1px solid #dee2e6; margin: 20px 0;">
            <p><strong>Requester Name:</strong> {{name}}</p>
            <p><strong>Requester Email:</strong> {{email}}</p>
            <p><strong>Document:</strong> {{file_name}}</p>
            <p><strong>Request Date:</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . '</p>
        </div>
        
        <p><a href="' . admin_url('edit.php?post_type=document&page=authdocs-requests') . '" style="display: inline-block; background: #007cba; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 500;">Review Request</a></p>
        
        <p>You can review and manage this request from your WordPress admin panel.</p>
    </div>
</body>
</html>';
    }
    
    /**
     * Get default auto-response body HTML
     */
    private function get_default_auto_response_body_html(): string {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Access Request Received</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fa; padding: 30px; border-radius: 8px; border-left: 4px solid #007cba;">
        <h1 style="color: #007cba; margin-top: 0; font-size: 24px;">Document Access Request Received</h1>
        
        <p>Hello {{name}},</p>
        
        <p>Thank you for your request to access the document: <strong>{{file_name}}</strong></p>
        
        <p>We have received your request and it is currently being reviewed by our team. You will receive another email once your access has been approved or declined.</p>
        
        <p><strong>Request Details:</strong></p>
        <ul style="background: #fff; padding: 20px; border-radius: 6px; border: 1px solid #dee2e6; margin: 20px 0;">
            <li><strong>Requester:</strong> {{name}}</li>
            <li><strong>Email:</strong> {{email}}</li>
            <li><strong>Document:</strong> {{file_name}}</li>
            <li><strong>Request Date:</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . '</li>
        </ul>
        
        <p>We typically process requests within 24-48 hours. If you have any urgent questions, please contact us directly.</p>
        
        <p>Best regards,<br>{{site_name}} Team</p>
    </div>
    
    <div style="text-align: center; margin-top: 20px; padding: 20px; color: #6c757d; font-size: 12px;">
        <p>This is an automated response to your document access request.</p>
    </div>
</body>
</html>';
    }
    
    /**
     * Get default grant/decline body HTML
     */
    private function get_default_grant_decline_body_html(): string {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Access {{status}}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fa; padding: 30px; border-radius: 8px; border-left: 4px solid {{status_color}};">
        <h1 style="color: {{status_color}}; margin-top: 0; font-size: 24px;">Document Access {{status}}</h1>
        
        <p>Hello {{name}},</p>
        
        <p>Your request for document access has been {{status}}. {{#if granted}}You can now view or download the document using the secure link below:{{else}}We regret to inform you that your request has been declined.{{/if}}</p>
        
        {{#if granted}}
        <div style="background: #fff; padding: 20px; border-radius: 6px; border: 1px solid #dee2e6; margin: 20px 0;">
            <p style="margin: 0 0 15px 0; font-weight: bold; color: #495057;">Secure Download Link:</p>
            <a href="{{link}}" style="display: inline-block; background: #007cba; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 500;">Access Document</a>
            <p style="margin: 10px 0 0 0; font-size: 12px; color: #6c757d;">Or copy this link: <span style="word-break: break-all;">{{link}}</span></p>
        </div>
        
        <p><strong>Important:</strong> This link is unique to your email address and should not be shared with others.</p>
        {{else}}
        <p><strong>Document:</strong> {{file_name}}</p>
        
        <p>If you believe this is an error or have any questions, please contact us directly.</p>
        {{/if}}
        
        <p>If you have any questions, please contact us.</p>
        
        <p>Best regards,<br>{{site_name}} Team</p>
    </div>
    
    <div style="text-align: center; margin-top: 20px; padding: 20px; color: #6c757d; font-size: 12px;">
        <p>This email was sent to {{email}}</p>
    </div>
</body>
</html>';
    }
    
    /**
     * Sanitize email template data
     */
    public function sanitize_email_template(array $input): array {
        return [
            'subject' => sanitize_text_field($input['subject'] ?? ''),
            'body' => wp_kses_post($input['body'] ?? ''),
            'enabled' => !empty($input['enabled'])
        ];
    }
    
    /**
     * Sanitize recipient emails
     */
    public function sanitize_recipient_emails(string $input): string {
        $emails = array_map('trim', explode(',', $input));
        $valid_emails = [];
        
        foreach ($emails as $email) {
            if (!empty($email) && (is_email($email) || preg_match('/^\{\{[a-zA-Z_]+\}\}$/', $email))) {
                $valid_emails[] = sanitize_text_field($email);
            }
        }
        
        return implode(', ', $valid_emails);
    }
    
    // Render methods for Access Request Email
    public function render_access_request_section_description(): void {
        echo '<p>' . __('Configure the email template that will be sent to website owners when a document access request is submitted.', 'authdocs') . '</p>';
    }
    
    public function render_access_request_subject_field(): void {
        $template = $this->get_access_request_template();
        $subject = $template['subject'] ?? '';
        
        echo '<input type="text" id="access_request_subject" name="' . self::ACCESS_REQUEST_TEMPLATE_NAME . '[subject]" value="' . esc_attr($subject) . '" class="regular-text" />';
        echo '<p class="description">' . __('Subject line for the access request notification email.', 'authdocs') . '</p>';
    }
    
    public function render_access_request_body_field(): void {
        $template = $this->get_access_request_template();
        $body = $template['body'] ?? '';
        
        $editor_settings = [
            'textarea_name' => self::ACCESS_REQUEST_TEMPLATE_NAME . '[body]',
            'textarea_rows' => 15,
            'media_buttons' => false,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,backcolor,removeformat',
                'toolbar2' => '',
                'toolbar3' => '',
                'height' => 300,
                'content_css' => 'default',
                'paste_as_text' => false,
                'verify_html' => true,
                'cleanup' => true,
                'forced_root_block' => 'p',
                'keep_styles' => false,
                'remove_redundant_brs' => true,
                'remove_linebreaks' => false,
                'convert_newlines_to_brs' => false,
                'remove_trailing_nbsp' => true
            ],
            'quicktags' => [
                'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close'
            ],
            'drag_drop_upload' => false,
            'wpautop' => false,
            'editor_height' => 300
        ];
        
        wp_editor($body, 'access_request_body', $editor_settings);
        echo '<p class="description">' . __('HTML email body for access request notifications.', 'authdocs') . '</p>';
    }
    
    public function render_access_request_recipients_field(): void {
        $recipients = get_option(self::ACCESS_REQUEST_RECIPIENTS_NAME, get_option('admin_email'));
        
        echo '<input type="text" id="access_request_recipients" name="' . self::ACCESS_REQUEST_RECIPIENTS_NAME . '" value="' . esc_attr($recipients) . '" class="regular-text" placeholder="admin@example.com, manager@example.com" />';
        echo '<p class="description">' . __('Enter email addresses separated by commas. These will receive notifications when access is requested.', 'authdocs') . '</p>';
    }
    
    public function render_access_request_variables_help(): void {
        echo '<div class="authdocs-variables-help">';
        echo '<h4>' . __('Available Variables:', 'authdocs') . '</h4>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>{{name}}</code> - ' . __('Requester\'s name', 'authdocs') . '</li>';
        echo '<li><code>{{email}}</code> - ' . __('Requester\'s email address', 'authdocs') . '</li>';
        echo '<li><code>{{file_name}}</code> - ' . __('Name of the requested file', 'authdocs') . '</li>';
        echo '<li><code>{{site_name}}</code> - ' . __('Name of your website', 'authdocs') . '</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    // Render methods for Auto-Response Email
    public function render_auto_response_section_description(): void {
        echo '<p>' . __('Configure the automatic response email that will be sent to users when they request document access.', 'authdocs') . '</p>';
    }
    
    public function render_auto_response_enable_field(): void {
        $template = $this->get_auto_response_template();
        $enabled = $template['enabled'] ?? false;
        
        echo '<label><input type="checkbox" name="' . self::AUTO_RESPONSE_TEMPLATE_NAME . '[enabled]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo ' ' . __('Enable auto-response emails', 'authdocs') . '</label>';
        echo '<p class="description">' . __('When enabled, users will automatically receive a confirmation email after submitting a document access request.', 'authdocs') . '</p>';
    }
    
    public function render_auto_response_subject_field(): void {
        $template = $this->get_auto_response_template();
        $subject = $template['subject'] ?? '';
        
        echo '<input type="text" id="auto_response_subject" name="' . self::AUTO_RESPONSE_TEMPLATE_NAME . '[subject]" value="' . esc_attr($subject) . '" class="regular-text" />';
        echo '<p class="description">' . __('Subject line for the auto-response email.', 'authdocs') . '</p>';
    }
    
    public function render_auto_response_body_field(): void {
        $template = $this->get_auto_response_template();
        $body = $template['body'] ?? '';
        
        $editor_settings = [
            'textarea_name' => self::AUTO_RESPONSE_TEMPLATE_NAME . '[body]',
            'textarea_rows' => 15,
            'media_buttons' => false,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,backcolor,removeformat',
                'toolbar2' => '',
                'toolbar3' => '',
                'height' => 300,
                'content_css' => 'default',
                'paste_as_text' => false,
                'verify_html' => true,
                'cleanup' => true,
                'forced_root_block' => 'p',
                'keep_styles' => false,
                'remove_redundant_brs' => true,
                'remove_linebreaks' => false,
                'convert_newlines_to_brs' => false,
                'remove_trailing_nbsp' => true
            ],
            'quicktags' => [
                'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close'
            ],
            'drag_drop_upload' => false,
            'wpautop' => false,
            'editor_height' => 300
        ];
        
        wp_editor($body, 'auto_response_body', $editor_settings);
        echo '<p class="description">' . __('HTML email body for auto-response emails.', 'authdocs') . '</p>';
    }
    
    public function render_auto_response_variables_help(): void {
        echo '<div class="authdocs-variables-help">';
        echo '<h4>' . __('Available Variables:', 'authdocs') . '</h4>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>{{name}}</code> - ' . __('Requester\'s name', 'authdocs') . '</li>';
        echo '<li><code>{{email}}</code> - ' . __('Requester\'s email address', 'authdocs') . '</li>';
        echo '<li><code>{{file_name}}</code> - ' . __('Name of the requested file', 'authdocs') . '</li>';
        echo '<li><code>{{site_name}}</code> - ' . __('Name of your website', 'authdocs') . '</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    // Render methods for Grant/Decline Email
    public function render_grant_decline_section_description(): void {
        echo '<p>' . __('Configure the email template that will be sent when document access is granted or declined.', 'authdocs') . '</p>';
    }
    
    public function render_grant_decline_subject_field(): void {
        $template = $this->get_grant_decline_template();
        $subject = $template['subject'] ?? '';
        
        echo '<input type="text" id="grant_decline_subject" name="' . self::GRANT_DECLINE_TEMPLATE_NAME . '[subject]" value="' . esc_attr($subject) . '" class="regular-text" />';
        echo '<p class="description">' . __('Subject line for grant/decline emails. Use {{status}} for "Granted" or "Declined".', 'authdocs') . '</p>';
    }
    
    public function render_grant_decline_body_field(): void {
        $template = $this->get_grant_decline_template();
        $body = $template['body'] ?? '';
        
        $editor_settings = [
            'textarea_name' => self::GRANT_DECLINE_TEMPLATE_NAME . '[body]',
            'textarea_rows' => 15,
            'media_buttons' => false,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,backcolor,removeformat',
                'toolbar2' => '',
                'toolbar3' => '',
                'height' => 300,
                'content_css' => 'default',
                'paste_as_text' => false,
                'verify_html' => true,
                'cleanup' => true,
                'forced_root_block' => 'p',
                'keep_styles' => false,
                'remove_redundant_brs' => true,
                'remove_linebreaks' => false,
                'convert_newlines_to_brs' => false,
                'remove_trailing_nbsp' => true
            ],
            'quicktags' => [
                'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close'
            ],
            'drag_drop_upload' => false,
            'wpautop' => false,
            'editor_height' => 300
        ];
        
        wp_editor($body, 'grant_decline_body', $editor_settings);
        echo '<p class="description">' . __('HTML email body for grant/decline notifications. Use {{link}} for the document access link when granted.', 'authdocs') . '</p>';
    }
    
    public function render_grant_decline_recipients_field(): void {
        $recipients = get_option(self::GRANT_DECLINE_RECIPIENTS_NAME, '{{email}}');
        
        echo '<input type="text" id="grant_decline_recipients" name="' . self::GRANT_DECLINE_RECIPIENTS_NAME . '" value="' . esc_attr($recipients) . '" class="regular-text" placeholder="{{email}}" />';
        echo '<p class="description">' . __('Enter email addresses separated by commas, or use {{email}} to send to the requester.', 'authdocs') . '</p>';
    }
    
    public function render_grant_decline_variables_help(): void {
        echo '<div class="authdocs-variables-help">';
        echo '<h4>' . __('Available Variables:', 'authdocs') . '</h4>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>{{name}}</code> - ' . __('Requester\'s name', 'authdocs') . '</li>';
        echo '<li><code>{{email}}</code> - ' . __('Requester\'s email address', 'authdocs') . '</li>';
        echo '<li><code>{{file_name}}</code> - ' . __('Name of the requested file', 'authdocs') . '</li>';
        echo '<li><code>{{site_name}}</code> - ' . __('Name of your website', 'authdocs') . '</li>';
        echo '<li><code>{{status}}</code> - ' . __('"Granted" or "Declined"', 'authdocs') . '</li>';
        echo '<li><code>{{status_color}}</code> - ' . __('Color code for granted (#28a745) or declined (#dc3545)', 'authdocs') . '</li>';
        echo '<li><code>{{link}}</code> - ' . __('Secure document access link (only available when granted)', 'authdocs') . '</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    // Getter methods
    public function get_access_request_template(): array {
        $template = get_option(self::ACCESS_REQUEST_TEMPLATE_NAME, []);
        
        if (empty($template)) {
            $template = $this->get_default_access_request_template();
        }
        
        return $template;
    }
    
    public function get_auto_response_template(): array {
        $template = get_option(self::AUTO_RESPONSE_TEMPLATE_NAME, []);
        
        if (empty($template)) {
            $template = $this->get_default_auto_response_template();
        }
        
        return $template;
    }
    
    public function get_grant_decline_template(): array {
        $template = get_option(self::GRANT_DECLINE_TEMPLATE_NAME, []);
        
        if (empty($template)) {
            $template = $this->get_default_grant_decline_template();
        }
        
        return $template;
    }
    
    public function get_access_request_recipients(): array {
        $recipients = get_option(self::ACCESS_REQUEST_RECIPIENTS_NAME, get_option('admin_email'));
        
        if (empty($recipients)) {
            return [get_option('admin_email')];
        }
        
        $emails = array_map('trim', explode(',', $recipients));
        return array_filter($emails, function($email) {
            return !empty($email) && (is_email($email) || preg_match('/^\{\{[a-zA-Z_]+\}\}$/', $email));
        });
    }
    
    public function get_grant_decline_recipients(): array {
        $recipients = get_option(self::GRANT_DECLINE_RECIPIENTS_NAME, '{{email}}');
        
        if (empty($recipients)) {
            return ['{{email}}'];
        }
        
        $emails = array_map('trim', explode(',', $recipients));
        return array_filter($emails, function($email) {
            return !empty($email) && (is_email($email) || preg_match('/^\{\{[a-zA-Z_]+\}\}$/', $email));
        });
    }
    
    /**
     * Process email template with dynamic variables
     */
    public function process_template(array $template, array $variables): array {
        $subject = $template['subject'] ?? '';
        $body = $template['body'] ?? '';
        
        // Replace variables in subject
        $subject = $this->replace_variables($subject, $variables);
        
        // Replace variables in body
        $body = $this->replace_variables($body, $variables);
        
        return [
            'subject' => $subject,
            'body' => $body
        ];
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
            '{{link}}' => $variables['link'] ?? ''
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
    
    /**
     * Resolve placeholders in recipient email
     */
    public function resolve_recipient_email(string $recipient_template, int $request_id): string {
        if (empty($recipient_template) || !preg_match('/^\{\{[a-zA-Z_]+\}\}$/', $recipient_template)) {
            return $recipient_template; // Not a placeholder, return as-is
        }
        
        // Extract placeholder name
        $placeholder = trim($recipient_template, '{}');
        
        // Get request data
        $request = Database::get_request_by_id($request_id);
        if (!$request) {
            return '';
        }
        
        // Resolve placeholders
        switch ($placeholder) {
            case 'email':
            case 'requester_email':
                return $request->requester_email ?? '';
            case 'name':
            case 'requester_name':
                return $request->requester_name ?? '';
            case 'file_name':
            case 'document_title':
                $document_title = get_the_title($request->document_id);
                return $document_title ?: '';
            default:
                return ''; // Unknown placeholder
        }
    }
}