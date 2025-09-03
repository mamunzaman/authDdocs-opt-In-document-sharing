<?php
/**
 * Settings management for AuthDocs plugin
 * 
 * @since 1.1.0 Email logic separation; new autoresponder recipient; trigger fixes.
 */
declare(strict_types=1);

namespace AuthDocs;

/**
 * Settings management for AuthDocs plugin
 */
class Settings {
    
    private const OPTION_GROUP = 'authdocs_options';
    private const OPTION_NAME = 'authdocs_email_template';
    private const RECIPIENT_OPTION_NAME = 'authdocs_recipient_emails';
    private const AUTORESPONDER_OPTION_NAME = 'authdocs_autoresponder_template';
    private const AUTORESPONDER_RECIPIENT_OPTION_NAME = 'authdocs_autoresponder_recipient_email';
    private const ACCESS_GRANTED_RECIPIENT_OPTION_NAME = 'authdocs_access_granted_recipient_email';
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
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_email_template'],
                'default' => $this->get_default_template()
            ]
        );
        
        register_setting(
            self::OPTION_GROUP,
            self::RECIPIENT_OPTION_NAME,
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_recipient_emails'],
                'default' => ''
            ]
        );
        
        register_setting(
            self::OPTION_GROUP,
            self::AUTORESPONDER_OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_autoresponder_template'],
                'default' => $this->get_default_autoresponder_template()
            ]
        );
        
        register_setting(
            self::OPTION_GROUP,
            self::AUTORESPONDER_RECIPIENT_OPTION_NAME,
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_recipient_email'],
                'default' => '{{requester_email}}'
            ]
        );
        
        register_setting(
            self::OPTION_GROUP,
            self::ACCESS_GRANTED_RECIPIENT_OPTION_NAME,
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_recipient_email'],
                'default' => get_option('admin_email')
            ]
        );
        
        // Register secret key for token signing
        $secret_key = get_option(self::SECRET_KEY_OPTION_NAME);
        if (empty($secret_key)) {
            $secret_key = wp_generate_password(64, false);
            update_option(self::SECRET_KEY_OPTION_NAME, $secret_key);
        }
        
        // Main Email Template Section
        add_settings_section(
            'authdocs_email_section',
            __('Email Template Settings', 'authdocs'),
            [$this, 'render_section_description'],
            'authdocs-settings'
        );
        
        add_settings_field(
            'email_subject',
            __('Email Subject', 'authdocs'),
            [$this, 'render_subject_field'],
            'authdocs-settings',
            'authdocs_email_section'
        );
        
        add_settings_field(
            'email_body',
            __('Email Body (HTML)', 'authdocs'),
            [$this, 'render_body_field'],
            'authdocs-settings',
            'authdocs_email_section'
        );
        
        add_settings_field(
            'email_variables',
            __('Available Variables', 'authdocs'),
            [$this, 'render_variables_help'],
            'authdocs-settings',
            'authdocs_email_section'
        );
        
        // Recipient Emails Section
        add_settings_section(
            'authdocs_recipient_section',
            __('Recipient Settings', 'authdocs'),
            [$this, 'render_recipient_section_description'],
            'authdocs-settings'
        );
        
        add_settings_field(
            'recipient_emails',
            __('Recipient Email Addresses', 'authdocs'),
            [$this, 'render_recipient_field'],
            'authdocs-settings',
            'authdocs_recipient_section'
        );
        
        // Autoresponder Section
        add_settings_section(
            'authdocs_autoresponder_section',
            __('Autoresponder Template', 'authdocs'),
            [$this, 'render_autoresponder_section_description'],
            'authdocs-settings'
        );
        
        add_settings_field(
            'autoresponder_enable',
            __('Enable Autoresponder', 'authdocs'),
            [$this, 'render_autoresponder_enable_field'],
            'authdocs-settings',
            'authdocs_autoresponder_section'
        );
        
        add_settings_field(
            'autoresponder_subject',
            __('Autoresponder Subject', 'authdocs'),
            [$this, 'render_autoresponder_subject_field'],
            'authdocs-settings',
            'authdocs_autoresponder_section'
        );
        
        add_settings_field(
            'autoresponder_body',
            __('Autoresponder Body (HTML)', 'authdocs'),
            [$this, 'render_autoresponder_body_field'],
            'authdocs-settings',
            'authdocs_autoresponder_section'
        );
        
        add_settings_field(
            'autoresponder_variables',
            __('Available Variables', 'authdocs'),
            [$this, 'render_autoresponder_variables_help'],
            'authdocs-settings',
            'authdocs_autoresponder_section'
        );
        
        add_settings_field(
            'autoresponder_recipient',
            __('Recipient Email', 'authdocs'),
            [$this, 'render_autoresponder_recipient_field'],
            'authdocs-settings',
            'authdocs_autoresponder_section'
        );
        
        add_settings_field(
            'access_granted_recipient',
            __('Recipient Email', 'authdocs'),
            [$this, 'render_access_granted_recipient_field'],
            'authdocs-settings',
            'authdocs_email_section'
        );
    }
    
    /**
     * Get default email template
     */
    private function get_default_template(): array {
        return [
            'subject' => __('Your document access has been granted', 'authdocs'),
            'body' => $this->get_default_body_html()
        ];
    }
    
    /**
     * Get default autoresponder template
     */
    private function get_default_autoresponder_template(): array {
        return [
            'enabled' => false,
            'subject' => __('Document Access Request Received', 'authdocs'),
            'body' => $this->get_default_autoresponder_body_html()
        ];
    }
    
    /**
     * Get default email body HTML
     */
    private function get_default_body_html(): string {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Access Granted</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fa; padding: 30px; border-radius: 8px; border-left: 4px solid #28a745;">
        <h1 style="color: #28a745; margin-top: 0; font-size: 24px;">Document Access Granted</h1>
        
        <p>Hello {{name}},</p>
        
        <p>Your request for document access has been approved. You can now view or download the document using the secure link below:</p>
        
        <div style="background: #fff; padding: 20px; border-radius: 6px; border: 1px solid #dee2e6; margin: 20px 0;">
            <p style="margin: 0 0 15px 0; font-weight: bold; color: #495057;">Secure Download Link:</p>
            <a href="{{link}}" style="display: inline-block; background: #007cba; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 500;">Access Document</a>
            <p style="margin: 10px 0 0 0; font-size: 12px; color: #6c757d;">Or copy this link: <span style="word-break: break-all;">{{link}}</span></p>
        </div>
        
        <p><strong>Important:</strong> This link is unique to your email address and should not be shared with others.</p>
        
        <p>If you have any questions, please contact us.</p>
        
        <p>Best regards,<br>Your Team</p>
    </div>
    
    <div style="text-align: center; margin-top: 20px; padding: 20px; color: #6c757d; font-size: 12px;">
        <p>This email was sent to {{email}}</p>
    </div>
</body>
</html>';
    }
    
    /**
     * Get default autoresponder body HTML
     */
    private function get_default_autoresponder_body_html(): string {
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
        
        <p>Hello {name},</p>
        
        <p>Thank you for your request to access the document: <strong>{document_title}</strong></p>
        
        <p>We have received your request and it is currently being reviewed by our team. You will receive another email once your access has been approved or declined.</p>
        
        <p><strong>Request Details:</strong></p>
        <ul style="background: #fff; padding: 20px; border-radius: 6px; border: 1px solid #dee2e6; margin: 20px 0;">
            <li><strong>Requester:</strong> {name}</li>
            <li><strong>Email:</strong> {email}</li>
            <li><strong>Document:</strong> {document_title}</li>
            <li><strong>Request Date:</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . '</li>
        </ul>
        
        <p>We typically process requests within 24-48 hours. If you have any urgent questions, please contact us directly.</p>
        
        <p>Best regards,<br>{site_name} Team</p>
    </div>
    
    <div style="text-align: center; margin-top: 20px; padding: 20px; color: #6c757d; font-size: 12px;">
        <p>This is an automated response to your document access request.</p>
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
            'body' => wp_kses_post($input['body'] ?? '')
        ];
    }
    
    /**
     * Sanitize recipient emails
     */
    public function sanitize_recipient_emails(string $input): string {
        $emails = array_map('trim', explode(',', $input));
        $valid_emails = [];
        
        foreach ($emails as $email) {
            if (!empty($email) && is_email($email)) {
                $valid_emails[] = sanitize_email($email);
            }
        }
        
        return implode(', ', $valid_emails);
    }
    
    /**
     * Sanitize autoresponder template data
     */
    public function sanitize_autoresponder_template(array $input): array {
        return [
            'enabled' => !empty($input['enabled']),
            'subject' => sanitize_text_field($input['subject'] ?? ''),
            'body' => wp_kses_post($input['body'] ?? '')
        ];
    }
    
    /**
     * Sanitize recipient email (supports placeholders)
     */
    public function sanitize_recipient_email(string $input): string {
        $input = trim($input);
        
        // Check if it's a valid email
        if (is_email($input)) {
            return sanitize_email($input);
        }
        
        // Check if it's a valid placeholder pattern
        if (preg_match('/^\{\{[a-zA-Z_]+\}\}$/', $input)) {
            return $input;
        }
        
        // Invalid input - return empty string and add admin notice
        add_settings_error(
            'authdocs_recipient_email',
            'invalid_recipient',
            sprintf(__('Invalid recipient email "%s". Must be a valid email address or a supported placeholder (e.g., {{requester_email}}).', 'authdocs'), $input)
        );
        
        return '';
    }
    
    /**
     * Render section description
     */
    public function render_section_description(): void {
        echo '<p>' . __('Configure the email template that will be sent when document access is granted. Use the variables below to personalize your emails.', 'authdocs') . '</p>';
    }
    
    /**
     * Render subject field
     */
    public function render_subject_field(): void {
        $template = $this->get_email_template();
        $subject = $template['subject'] ?? '';
        
        echo '<input type="text" id="email_subject" name="' . self::OPTION_NAME . '[subject]" value="' . esc_attr($subject) . '" class="regular-text" />';
        echo '<p class="description">' . __('Subject line for the access granted email. You can use variables like {{name}}.', 'authdocs') . '</p>';
    }
    
    /**
     * Render body field
     */
    public function render_body_field(): void {
        $template = $this->get_email_template();
        $body = $template['body'] ?? '';
        
        $editor_settings = [
            'textarea_name' => self::OPTION_NAME . '[body]',
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
        
        wp_editor($body, 'email_body', $editor_settings);
        echo '<p class="description">' . __('HTML email body. Use the variables below to personalize your emails. You can use the rich editor above or switch to HTML mode for advanced customization.', 'authdocs') . '</p>';
    }
    
    /**
     * Render variables help
     */
    public function render_variables_help(): void {
        echo '<div class="authdocs-variables-help">';
        echo '<h4>' . __('Available Variables:', 'authdocs') . '</h4>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>{{name}}</code> - ' . __('Requester\'s name', 'authdocs') . '</li>';
        echo '<li><code>{{email}}</code> - ' . __('Requester\'s email address', 'authdocs') . '</li>';
        echo '<li><code>{{link}}</code> - ' . __('Generated secure download/view link', 'authdocs') . '</li>';
        echo '</ul>';
        echo '<p><strong>' . __('Note:', 'authdocs') . '</strong> ' . __('If a variable is missing, it will be replaced with an empty string.', 'authdocs') . '</p>';
        echo '</div>';
    }
    
    /**
     * Render recipient section description
     */
    public function render_recipient_section_description(): void {
        echo '<p>' . __('Configure email addresses that will receive notifications when document access is requested or granted. Leave empty to use the site admin email.', 'authdocs') . '</p>';
    }
    
    /**
     * Render recipient field
     */
    public function render_recipient_field(): void {
        $recipients = get_option(self::RECIPIENT_OPTION_NAME, '');
        
        echo '<input type="text" id="recipient_emails" name="' . self::RECIPIENT_OPTION_NAME . '" value="' . esc_attr($recipients) . '" class="regular-text" placeholder="admin@example.com, manager@example.com" />';
        echo '<p class="description">' . __('Enter email addresses separated by commas or semicolons. Invalid addresses will be automatically removed.', 'authdocs') . '</p>';
        echo '<div id="recipient-validation" class="authdocs-validation-message"></div>';
    }
    
    /**
     * Render autoresponder section description
     */
    public function render_autoresponder_section_description(): void {
        echo '<p>' . __('Configure an automatic response email that will be sent to users when they request document access. This email is sent immediately upon request submission.', 'authdocs') . '</p>';
    }
    
    /**
     * Render autoresponder enable field
     */
    public function render_autoresponder_enable_field(): void {
        $template = $this->get_autoresponder_template();
        $enabled = $template['enabled'] ?? false;
        
        echo '<label><input type="checkbox" name="' . self::AUTORESPONDER_OPTION_NAME . '[enabled]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo ' ' . __('Enable autoresponder emails', 'authdocs') . '</label>';
        echo '<p class="description">' . __('When enabled, users will automatically receive a confirmation email after submitting a document access request.', 'authdocs') . '</p>';
    }
    
    /**
     * Render autoresponder subject field
     */
    public function render_autoresponder_subject_field(): void {
        $template = $this->get_autoresponder_template();
        $subject = $template['subject'] ?? '';
        
        echo '<input type="text" id="autoresponder_subject" name="' . self::AUTORESPONDER_OPTION_NAME . '[subject]" value="' . esc_attr($subject) . '" class="regular-text" />';
        echo '<p class="description">' . __('Subject line for the autoresponder email. You can use variables like {name} and {document_title}.', 'authdocs') . '</p>';
    }
    
    /**
     * Render autoresponder body field
     */
    public function render_autoresponder_body_field(): void {
        $template = $this->get_autoresponder_template();
        $body = $template['body'] ?? '';
        
        $editor_settings = [
            'textarea_name' => self::AUTORESPONDER_OPTION_NAME . '[body]',
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
        
        wp_editor($body, 'autoresponder_body', $editor_settings);
        echo '<p class="description">' . __('HTML email body for the autoresponder. Use the variables below to personalize your emails. You can use the rich editor above or switch to HTML mode for advanced customization.', 'authdocs') . '</p>';
    }
    
    /**
     * Render autoresponder variables help
     */
    public function render_autoresponder_variables_help(): void {
        echo '<div class="authdocs-variables-help">';
        echo '<h4>' . __('Available Variables:', 'authdocs') . '</h4>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>{name}</code> - ' . __('Requester\'s name', 'authdocs') . '</li>';
        echo '<li><code>{email}</code> - ' . __('Requester\'s email address', 'authdocs') . '</li>';
        echo '<li><code>{document_title}</code> - ' . __('Title of the requested document', 'authdocs') . '</li>';
        echo '<li><code>{site_name}</code> - ' . __('Name of your website', 'authdocs') . '</li>';
        echo '</ul>';
        echo '<p><strong>' . __('Note:', 'authdocs') . '</strong> ' . __('Unknown placeholders will remain unchanged in the email.', 'authdocs') . '</p>';
        echo '</div>';
    }
    
    /**
     * Render autoresponder recipient field
     */
    public function render_autoresponder_recipient_field(): void {
        $recipient = get_option(self::AUTORESPONDER_RECIPIENT_OPTION_NAME, '{{requester_email}}');
        
        echo '<input type="text" id="autoresponder_recipient" name="' . self::AUTORESPONDER_RECIPIENT_OPTION_NAME . '" value="' . esc_attr($recipient) . '" class="regular-text" placeholder="{{requester_email}}" />';
        echo '<p class="description">' . __('Email address or placeholder for autoresponder recipient. Use {{requester_email}} to send to the person requesting access.', 'authdocs') . '</p>';
        echo '<div id="autoresponder-recipient-validation" class="authdocs-validation-message"></div>';
    }
    
    /**
     * Render access granted recipient field
     */
    public function render_access_granted_recipient_field(): void {
        $recipient = get_option(self::ACCESS_GRANTED_RECIPIENT_OPTION_NAME, get_option('admin_email'));
        
        echo '<input type="text" id="access_granted_recipient" name="' . self::ACCESS_GRANTED_RECIPIENT_OPTION_NAME . '" value="' . esc_attr($recipient) . '" class="regular-text" placeholder="admin@example.com" />';
        echo '<p class="description">' . __('Email address or placeholder for access granted notifications. Use {{requester_email}} to send to the person who requested access.', 'authdocs') . '</p>';
        echo '<div id="access-granted-recipient-validation" class="authdocs-validation-message"></div>';
    }
    
    /**
     * Get email template from options
     */
    public function get_email_template(): array {
        $template = get_option(self::OPTION_NAME, []);
        
        if (empty($template)) {
            $template = $this->get_default_template();
        }
        
        return $template;
    }
    
    /**
     * Get autoresponder template from options
     */
    public function get_autoresponder_template(): array {
        $template = get_option(self::AUTORESPONDER_OPTION_NAME, []);
        
        if (empty($template)) {
            $template = $this->get_default_autoresponder_template();
        }
        
        return $template;
    }
    
    /**
     * Get recipient emails from options
     */
    public function get_recipient_emails(): array {
        $recipients = get_option(self::RECIPIENT_OPTION_NAME, '');
        
        if (empty($recipients)) {
            return [get_option('admin_email')];
        }
        
        $emails = array_map('trim', explode(',', $recipients));
        return array_filter($emails, 'is_email');
    }
    
    /**
     * Get autoresponder recipient email
     */
    public function get_autoresponder_recipient_email(): string {
        return get_option(self::AUTORESPONDER_RECIPIENT_OPTION_NAME, '{{requester_email}}');
    }
    
    /**
     * Get access granted recipient email
     */
    public function get_access_granted_recipient_email(): string {
        return get_option(self::ACCESS_GRANTED_RECIPIENT_OPTION_NAME, get_option('admin_email'));
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
            case 'requester_email':
                return $request->requester_email ?? '';
            case 'requester_name':
                return $request->requester_name ?? '';
            case 'document_title':
                $document_title = get_the_title($request->document_id);
                return $document_title ?: '';
            default:
                return ''; // Unknown placeholder
        }
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
            '{{link}}' => $variables['link'] ?? ''
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
    
    /**
     * Process autoresponder template with dynamic variables
     */
    public function process_autoresponder_template(array $template, array $variables): array {
        $subject = $template['subject'] ?? '';
        $body = $template['body'] ?? '';
        
        // Replace variables in subject
        $subject = $this->replace_autoresponder_variables($subject, $variables);
        
        // Replace variables in body
        $body = $this->replace_autoresponder_variables($body, $variables);
        
        return [
            'subject' => $subject,
            'body' => $body
        ];
    }
    
    /**
     * Replace autoresponder variables in text
     */
    private function replace_autoresponder_variables(string $text, array $variables): string {
        $replacements = [
            '{name}' => $variables['name'] ?? '',
            '{email}' => $variables['email'] ?? '',
            '{document_title}' => $variables['document_title'] ?? '',
            '{site_name}' => $variables['site_name'] ?? get_bloginfo('name')
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
