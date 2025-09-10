<?php
/**
 * Settings management for AuthDocs plugin
 * 
 * @since 1.2.0 Three separate email templates with dynamic placeholders
 */
declare(strict_types=1);

namespace ProtectedDocs;

/**
 * Settings management for AuthDocs plugin
 */
class Settings {
    
    public const OPTION_GROUP = 'authdocs_options';
    public const ACCESS_REQUEST_TEMPLATE_NAME = 'authdocs_access_request_template';
    public const AUTO_RESPONSE_TEMPLATE_NAME = 'authdocs_auto_response_template';
    public const GRANT_DECLINE_TEMPLATE_NAME = 'authdocs_grant_decline_template';
    public const ACCESS_REQUEST_RECIPIENTS_NAME = 'authdocs_access_request_recipients';
    public const GRANT_DECLINE_RECIPIENTS_NAME = 'authdocs_grant_decline_recipients';
    public const SECRET_KEY_OPTION_NAME = 'authdocs_secret_key';
    public const FRONTEND_COLOR_PALETTE_NAME = 'authdocs_frontend_color_palette';
    public const PAGINATION_STYLE_NAME = 'authdocs_pagination_style';
    public const PAGINATION_TYPE_NAME = 'authdocs_pagination_type';
    
    public function __construct() {
        add_action('admin_init', [$this, 'init_settings']);
        add_action('admin_init', [$this, 'ensure_default_templates']);
    }
    
    /**
     * Ensure default templates are set
     */
    public function ensure_default_templates(): void {
        // Check and set default access request template
        $access_template = get_option(self::ACCESS_REQUEST_TEMPLATE_NAME, []);
        if (empty($access_template) || !isset($access_template['subject']) || !isset($access_template['body'])) {
            update_option(self::ACCESS_REQUEST_TEMPLATE_NAME, $this->get_default_access_request_template());
        }
        
        // Check and set default auto-response template
        $auto_template = get_option(self::AUTO_RESPONSE_TEMPLATE_NAME, []);
        if (empty($auto_template) || !isset($auto_template['subject']) || !isset($auto_template['body'])) {
            update_option(self::AUTO_RESPONSE_TEMPLATE_NAME, $this->get_default_auto_response_template());
        } elseif (!isset($auto_template['enabled'])) {
            // For existing installations, enable auto-response by default
            $auto_template['enabled'] = true;
            update_option(self::AUTO_RESPONSE_TEMPLATE_NAME, $auto_template);
        }
        
        // Check and set default grant/decline template
        $grant_template = get_option(self::GRANT_DECLINE_TEMPLATE_NAME, []);
        if (empty($grant_template) || !isset($grant_template['subject']) || !isset($grant_template['body'])) {
            update_option(self::GRANT_DECLINE_TEMPLATE_NAME, $this->get_default_grant_decline_template());
        }
        
        // Check and set default recipients
        if (empty(get_option(self::ACCESS_REQUEST_RECIPIENTS_NAME, ''))) {
            update_option(self::ACCESS_REQUEST_RECIPIENTS_NAME, get_option('admin_email'));
        }
        
        if (empty(get_option(self::GRANT_DECLINE_RECIPIENTS_NAME, ''))) {
            update_option(self::GRANT_DECLINE_RECIPIENTS_NAME, '{{email}}');
        }
        
        // Check and set default color palette
        if (empty(get_option(self::FRONTEND_COLOR_PALETTE_NAME, ''))) {
            update_option(self::FRONTEND_COLOR_PALETTE_NAME, 'black_white');
        }
        
        // Check and set default pagination style
        if (empty(get_option(self::PAGINATION_STYLE_NAME, ''))) {
            update_option(self::PAGINATION_STYLE_NAME, 'classic');
        }
        
        // Check and set default pagination type
        if (empty(get_option(self::PAGINATION_TYPE_NAME, ''))) {
            update_option(self::PAGINATION_TYPE_NAME, 'ajax');
        }
        
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
        
        // Register frontend color palette setting
        register_setting(
            self::OPTION_GROUP,
            self::FRONTEND_COLOR_PALETTE_NAME,
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_color_palette'],
                'default' => 'black_white_blue'
            ]
        );
        
        register_setting(
            self::OPTION_GROUP,
            self::PAGINATION_STYLE_NAME,
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_pagination_style'],
                'default' => 'classic'
            ]
        );
        
        register_setting(
            self::OPTION_GROUP,
            self::PAGINATION_TYPE_NAME,
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_pagination_type'],
                'default' => 'ajax'
            ]
        );
        
        
        // Access Request Email Section
        add_settings_section(
            'authdocs_access_request_section',
            __('Access Request Email Template', 'protecteddocs'),
            [$this, 'render_access_request_section_description'],
            'authdocs-settings'
        );
        
        add_settings_field(
            'access_request_subject',
            '<span class="authdocs-email-subject-label">' . __('Email Subject', 'protecteddocs') . '</span>',
            [$this, 'render_access_request_subject_field'],
            'authdocs-settings',
            'authdocs_access_request_section'
        );
        
        add_settings_field(
            'access_request_body',
            __('Email Body (HTML)', 'protecteddocs'),
            [$this, 'render_access_request_body_field'],
            'authdocs-settings',
            'authdocs_access_request_section'
        );
        
        add_settings_field(
            'access_request_recipients',
            __('Recipient Email Addresses', 'protecteddocs'),
            [$this, 'render_access_request_recipients_field'],
            'authdocs-settings',
            'authdocs_access_request_section'
        );
        
        add_settings_field(
            'access_request_variables',
            __('Available Variables', 'protecteddocs'),
            [$this, 'render_access_request_variables_help'],
            'authdocs-settings',
            'authdocs_access_request_section'
        );
        
        // Auto-Response Email Section
        add_settings_section(
            'authdocs_auto_response_section',
            __('Auto-Response Email Template', 'protecteddocs'),
            [$this, 'render_auto_response_section_description'],
            'authdocs-settings'
        );
        
        add_settings_field(
            'auto_response_enable',
            __('Enable Auto-Response', 'protecteddocs'),
            [$this, 'render_auto_response_enable_field'],
            'authdocs-settings',
            'authdocs_auto_response_section'
        );
        
        add_settings_field(
            'auto_response_subject',
            '<span class="authdocs-email-subject-label">' . __('Email Subject', 'protecteddocs') . '</span>',
            [$this, 'render_auto_response_subject_field'],
            'authdocs-settings',
            'authdocs_auto_response_section'
        );
        
        add_settings_field(
            'auto_response_body',
            __('Email Body (HTML)', 'protecteddocs'),
            [$this, 'render_auto_response_body_field'],
            'authdocs-settings',
            'authdocs_auto_response_section'
        );
        
        add_settings_field(
            'auto_response_variables',
            __('Available Variables', 'protecteddocs'),
            [$this, 'render_auto_response_variables_help'],
            'authdocs-settings',
            'authdocs_auto_response_section'
        );
        
        // Grant/Decline Email Section
        add_settings_section(
            'authdocs_grant_decline_section',
            __('Grant/Decline Email Template', 'protecteddocs'),
            [$this, 'render_grant_decline_section_description'],
            'authdocs-settings'
        );
        
        add_settings_field(
            'grant_decline_subject',
            '<span class="authdocs-email-subject-label">' . __('Email Subject', 'protecteddocs') . '</span>',
            [$this, 'render_grant_decline_subject_field'],
            'authdocs-settings',
            'authdocs_grant_decline_section'
        );
        
        add_settings_field(
            'grant_decline_body',
            __('Email Body (HTML)', 'protecteddocs'),
            [$this, 'render_grant_decline_body_field'],
            'authdocs-settings',
            'authdocs_grant_decline_section'
        );
        
        add_settings_field(
            'grant_decline_recipients',
            __('Recipient Email Addresses', 'protecteddocs'),
            [$this, 'render_grant_decline_recipients_field'],
            'authdocs-settings',
            'authdocs_grant_decline_section'
        );
        
        add_settings_field(
            'grant_decline_variables',
            __('Available Variables', 'protecteddocs'),
            [$this, 'render_grant_decline_variables_help'],
            'authdocs-settings',
            'authdocs_grant_decline_section'
        );
        
        // Display & Navigation Section (Combined Color Palette and Pagination)
        add_settings_section(
            'authdocs_display_navigation_section',
            __('Display & Navigation Settings', 'protecteddocs'),
            [$this, 'render_display_navigation_section_description'],
            'authdocs-settings'
        );
        
        add_settings_field(
            'frontend_color_palette',
            '<span class="authdocs-color-palette-label">' . __('Color Palette', 'protecteddocs') . '</span>',
            [$this, 'render_frontend_color_palette_field'],
            'authdocs-settings',
            'authdocs_display_navigation_section'
        );
        
        add_settings_field(
            'pagination_type',
            '<span class="authdocs-pagination-type-label">' . __('Pagination Type', 'protecteddocs') . '</span>',
            [$this, 'render_pagination_type_field'],
            'authdocs-settings',
            'authdocs_display_navigation_section'
        );
        
        add_settings_field(
            'pagination_style',
            '<span class="authdocs-pagination-style-label">' . __('Pagination Style', 'protecteddocs') . '</span>',
            [$this, 'render_pagination_style_field'],
            'authdocs-settings',
            'authdocs_display_navigation_section'
        );
        
        
        // General Section
        add_settings_section(
            'authdocs_general_section',
            __('General Settings', 'protecteddocs'),
            [$this, 'render_general_section_description'],
            'authdocs-settings'
        );
    }
    
    /**
     * Get default access request template
     */
    private function get_default_access_request_template(): array {
        return [
            'subject' => __('New Document Access Request: {{file_name}}', 'protecteddocs'),
            'body' => $this->get_default_access_request_body_html()
        ];
    }
    
    /**
     * Get default auto-response template
     */
    private function get_default_auto_response_template(): array {
        return [
            'enabled' => true,
            'subject' => __('Document Access Request Received - {{site_name}}', 'protecteddocs'),
            'body' => $this->get_default_auto_response_body_html()
        ];
    }
    
    /**
     * Get default grant/decline template
     */
    private function get_default_grant_decline_template(): array {
        return [
            'subject' => __('Document Access {{status}} - {{file_name}}', 'protecteddocs'),
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
        
        <p><a href="{{document_edit_url}}" style="display: inline-block; background: #007cba; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 500;">Review Request</a></p>
        
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
    public function sanitize_email_template($input): array {
        // Handle null or non-array input
        if (!is_array($input)) {
            return [
                'subject' => '',
                'body' => '',
                'enabled' => false
            ];
        }
        
        return [
            'subject' => sanitize_text_field($input['subject'] ?? ''),
            'body' => wp_kses_post($input['body'] ?? ''),
            'enabled' => isset($input['enabled']) && ($input['enabled'] === '1' || $input['enabled'] === 1 || $input['enabled'] === true)
        ];
    }
    
    /**
     * Sanitize recipient emails
     */
    public function sanitize_recipient_emails($input): string {
        // Handle null or non-string input
        if (!is_string($input)) {
            return '';
        }
        
        $emails = array_map('trim', explode(',', $input));
        $valid_emails = [];
        
        foreach ($emails as $email) {
            if (!empty($email) && (is_email($email) || preg_match('/^\{\{[a-zA-Z_]+\}\}$/', $email))) {
                $valid_emails[] = sanitize_text_field($email);
            }
        }
        
        return implode(', ', $valid_emails);
    }
    
    /**
     * Sanitize color palette selection
     */
    public function sanitize_color_palette($input): string {
        // Handle null or non-string input
        if (!is_string($input)) {
            return 'black_white_blue';
        }
        
        $allowed_palettes = ['black_white_blue', 'black_gray'];
        return in_array($input, $allowed_palettes) ? $input : 'black_white_blue';
    }
    
    /**
     * Sanitize pagination style input
     */
    public function sanitize_pagination_style($input): string {
        // Handle null or non-string input
        if (!is_string($input)) {
            return 'classic';
        }
        
        $allowed_styles = ['classic', 'load_more'];
        return in_array($input, $allowed_styles) ? $input : 'classic';
    }
    
    /**
     * Sanitize pagination type input
     */
    public function sanitize_pagination_type($input): string {
        // Handle null or non-string input
        if (!is_string($input)) {
            return 'ajax';
        }
        
        $allowed_types = ['ajax', 'classic'];
        return in_array($input, $allowed_types) ? $input : 'ajax';
    }
    
    
    
    public function render_pagination_type_field(): void
    {
        $current_type = $this->get_pagination_type();
        ?>
        <fieldset>
            <legend class="screen-reader-text"><?php _e('Pagination Type', 'protecteddocs'); ?></legend>
            
            <div class="authdocs-pagination-type-options">
                <label class="authdocs-pagination-type-option">
                    <input type="radio" name="<?php echo self::PAGINATION_TYPE_NAME; ?>" value="ajax" 
                           <?php checked($current_type, 'ajax'); ?> />
                    <div class="authdocs-option-content">
                        <strong class="authdocs-option-title"><?php _e('AJAX Pagination', 'protecteddocs'); ?></strong>
                        <p class="authdocs-option-description"><?php _e('Fast, seamless pagination without page reloads.', 'protecteddocs'); ?></p>
                    </div>
                </label>
                
                <label class="authdocs-pagination-type-option">
                    <input type="radio" name="<?php echo self::PAGINATION_TYPE_NAME; ?>" value="classic" 
                           <?php checked($current_type, 'classic'); ?> 
                           onchange="authdocsConfirmClassicPagination(this)" />
                    <div class="authdocs-option-content">
                        <strong class="authdocs-option-title"><?php _e('Classic Pagination', 'protecteddocs'); ?></strong>
                        <p class="authdocs-option-description"><?php _e('Traditional pagination with full page reloads.', 'protecteddocs'); ?></p>
                    </div>
                </label>
            </div>
        </fieldset>
        <?php
    }
    
    public function render_pagination_style_field(): void
    {
        $current_style = $this->get_pagination_style();
        ?>
        <fieldset>
            <legend class="screen-reader-text"><?php _e('Pagination Style', 'protecteddocs'); ?></legend>
            
            <div class="authdocs-pagination-options">
                <label class="authdocs-pagination-option">
                    <input type="radio" name="<?php echo self::PAGINATION_STYLE_NAME; ?>" value="classic" 
                           <?php checked($current_style, 'classic'); ?> />
                    <div class="authdocs-option-preview">
                        <div class="authdocs-preview-image">
                            <div class="authdocs-preview-classic">
                                <div class="authdocs-preview-docs">
                                    <div class="authdocs-preview-doc"></div>
                                    <div class="authdocs-preview-doc"></div>
                                    <div class="authdocs-preview-doc"></div>
                                </div>
                                <div class="authdocs-preview-pagination">
                                    <span class="authdocs-preview-page">‹</span>
                                    <span class="authdocs-preview-page active">1</span>
                                    <span class="authdocs-preview-page">2</span>
                                    <span class="authdocs-preview-page">3</span>
                                    <span class="authdocs-preview-page">›</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="authdocs-option-content">
                        <strong class="authdocs-option-title"><?php _e('Classic Pagination (AJAX)', 'protecteddocs'); ?></strong>
                        <p class="authdocs-option-description"><?php _e('Traditional page numbers with Previous/Next buttons for easy navigation.', 'protecteddocs'); ?></p>
                    </div>
                </label>
                
                <label class="authdocs-pagination-option">
                    <input type="radio" name="<?php echo self::PAGINATION_STYLE_NAME; ?>" value="load_more" 
                           <?php checked($current_style, 'load_more'); ?> />
                    <div class="authdocs-option-preview">
                        <div class="authdocs-preview-image">
                            <div class="authdocs-preview-loadmore">
                                <div class="authdocs-preview-docs">
                                    <div class="authdocs-preview-doc"></div>
                                    <div class="authdocs-preview-doc"></div>
                                    <div class="authdocs-preview-doc"></div>
                                </div>
                                <div class="authdocs-preview-button">
                                    <span class="authdocs-preview-btn-text">Load More</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="authdocs-option-content">
                        <strong class="authdocs-option-title"><?php _e('Load More (AJAX)', 'protecteddocs'); ?></strong>
                        <p class="authdocs-option-description"><?php _e('Progressive loading with a "Load More" button for seamless browsing.', 'protecteddocs'); ?></p>
                    </div>
                </label>
            </div>
        </fieldset>
        <?php
    }
    
    
    public function render_general_section_description(): void
    {
        echo '<p>' . __('General plugin settings and information.', 'protecteddocs') . '</p>';
    }
    
    // Render methods for Access Request Email
    public function render_access_request_section_description(): void {
        echo '<p>' . __('Configure the email template that will be sent to website owners when a document access request is submitted.', 'protecteddocs') . '</p>';
    }
    
    public function render_access_request_subject_field(): void {
        $template = $this->get_access_request_template();
        $subject = $template['subject'] ?? '';
        
        // If subject is empty, use default
        if (empty($subject)) {
            $default_template = $this->get_default_access_request_template();
            $subject = $default_template['subject'];
        }
        
        echo '<input type="text" id="access_request_subject" name="' . self::ACCESS_REQUEST_TEMPLATE_NAME . '[subject]" value="' . esc_attr($subject) . '" class="regular-text authdocs-text-input" />';
        echo '<p class="description">' . __('Subject line for the access request notification email.', 'protecteddocs') . '</p>';
    }
    
    public function render_access_request_body_field(): void {
        $template = $this->get_access_request_template();
        $body = $template['body'] ?? '';
        
        // If body is empty, use default
        if (empty($body)) {
            $default_template = $this->get_default_access_request_template();
            $body = $default_template['body'];
        }
        
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
        echo '<p class="description">' . __('HTML email body for access request notifications.', 'protecteddocs') . '</p>';
    }
    
    public function render_access_request_recipients_field(): void {
        $recipients = get_option(self::ACCESS_REQUEST_RECIPIENTS_NAME, get_option('admin_email'));
        
        echo '<input type="text" id="access_request_recipients" name="' . self::ACCESS_REQUEST_RECIPIENTS_NAME . '" value="' . esc_attr($recipients) . '" class="regular-text authdocs-text-input" placeholder="admin@example.com, manager@example.com" />';
        echo '<p class="description">' . __('Enter email addresses separated by commas. These will receive notifications when access is requested.', 'protecteddocs') . '</p>';
    }
    
    public function render_access_request_variables_help(): void {
        echo '<div class="authdocs-variables-help">';
        echo '<h4>' . __('Available Variables:', 'protecteddocs') . '</h4>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>{{name}}</code> - <span>' . __('Requester\'s name', 'protecteddocs') . '</span></li>';
        echo '<li><code>{{email}}</code> - <span>' . __('Requester\'s email address', 'protecteddocs') . '</span></li>';
        echo '<li><code>{{file_name}}</code> - <span>' . __('Name of the requested file', 'protecteddocs') . '</span></li>';
        echo '<li><code>{{site_name}}</code> - <span>' . __('Name of your website', 'protecteddocs') . '</span></li>';
        echo '</ul>';
        echo '</div>';
    }
    
    // Render methods for Auto-Response Email
    public function render_auto_response_section_description(): void {
        echo '<p>' . __('Configure the automatic response email that will be sent to users when they request document access.', 'protecteddocs') . '</p>';
    }
    
    public function render_auto_response_enable_field(): void {
        $template = $this->get_auto_response_template();
        $enabled = $template['enabled'] ?? false;
        
        echo '<label><input type="checkbox" name="' . self::AUTO_RESPONSE_TEMPLATE_NAME . '[enabled]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo ' <span>' . __('Enable auto-response emails', 'protecteddocs') . '</span></label>';
        echo '<p class="description">' . __('When enabled, users will automatically receive a confirmation email after submitting a document access request.', 'protecteddocs') . '</p>';
    }
    
    public function render_auto_response_subject_field(): void {
        $template = $this->get_auto_response_template();
        $subject = $template['subject'] ?? '';
        
        // If subject is empty, use default
        if (empty($subject)) {
            $default_template = $this->get_default_auto_response_template();
            $subject = $default_template['subject'];
        }
        
        echo '<input type="text" id="auto_response_subject" name="' . self::AUTO_RESPONSE_TEMPLATE_NAME . '[subject]" value="' . esc_attr($subject) . '" class="regular-text authdocs-text-input" />';
        echo '<p class="description">' . __('Subject line for the auto-response email.', 'protecteddocs') . '</p>';
    }
    
    public function render_auto_response_body_field(): void {
        $template = $this->get_auto_response_template();
        $body = $template['body'] ?? '';
        
        // If body is empty, use default
        if (empty($body)) {
            $default_template = $this->get_default_auto_response_template();
            $body = $default_template['body'];
        }
        
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
        echo '<p class="description">' . __('HTML email body for auto-response emails.', 'protecteddocs') . '</p>';
    }
    
    public function render_auto_response_variables_help(): void {
        echo '<div class="authdocs-variables-help">';
        echo '<h4>' . __('Available Variables:', 'protecteddocs') . '</h4>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>{{name}}</code> - <span>' . __('Requester\'s name', 'protecteddocs') . '</span></li>';
        echo '<li><code>{{email}}</code> - <span>' . __('Requester\'s email address', 'protecteddocs') . '</span></li>';
        echo '<li><code>{{file_name}}</code> - <span>' . __('Name of the requested file', 'protecteddocs') . '</span></li>';
        echo '<li><code>{{site_name}}</code> - <span>' . __('Name of your website', 'protecteddocs') . '</span></li>';
        echo '</ul>';
        echo '</div>';
    }
    
    // Render methods for Grant/Decline Email
    public function render_grant_decline_section_description(): void {
        echo '<p>' . __('Configure the email template that will be sent when document access is granted or declined.', 'protecteddocs') . '</p>';
    }
    
    public function render_grant_decline_subject_field(): void {
        $template = $this->get_grant_decline_template();
        $subject = $template['subject'] ?? '';
        
        // If subject is empty, use default
        if (empty($subject)) {
            $default_template = $this->get_default_grant_decline_template();
            $subject = $default_template['subject'];
        }
        
        echo '<input type="text" id="grant_decline_subject" name="' . self::GRANT_DECLINE_TEMPLATE_NAME . '[subject]" value="' . esc_attr($subject) . '" class="regular-text authdocs-text-input" />';
        echo '<p class="description">' . __('Subject line for grant/decline emails. Use {{status}} for "Granted" or "Declined".', 'protecteddocs') . '</p>';
    }
    
    public function render_grant_decline_body_field(): void {
        $template = $this->get_grant_decline_template();
        $body = $template['body'] ?? '';
        
        // If body is empty, use default
        if (empty($body)) {
            $default_template = $this->get_default_grant_decline_template();
            $body = $default_template['body'];
        }
        
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
        echo '<p class="description">' . __('HTML email body for grant/decline notifications. Use {{link}} for the document access link when granted.', 'protecteddocs') . '</p>';
    }
    
    public function render_grant_decline_recipients_field(): void {
        $recipients = get_option(self::GRANT_DECLINE_RECIPIENTS_NAME, '{{email}}');
        
        echo '<input type="text" id="grant_decline_recipients" name="' . self::GRANT_DECLINE_RECIPIENTS_NAME . '" value="' . esc_attr($recipients) . '" class="regular-text authdocs-text-input" placeholder="{{email}}" />';
        echo '<p class="description">' . __('Enter email addresses separated by commas, or use {{email}} to send to the requester.', 'protecteddocs') . '</p>';
    }
    
    public function render_grant_decline_variables_help(): void {
        echo '<div class="authdocs-variables-help">';
        echo '<h4>' . __('Available Variables:', 'protecteddocs') . '</h4>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>{{name}}</code> - <span>' . __('Requester\'s name', 'protecteddocs') . '</span></li>';
        echo '<li><code>{{email}}</code> - <span>' . __('Requester\'s email address', 'protecteddocs') . '</span></li>';
        echo '<li><code>{{file_name}}</code> - <span>' . __('Name of the requested file', 'protecteddocs') . '</span></li>';
        echo '<li><code>{{site_name}}</code> - <span>' . __('Name of your website', 'protecteddocs') . '</span></li>';
        echo '<li><code>{{status}}</code> - <span>' . __('"Granted" or "Declined"', 'protecteddocs') . '</span></li>';
        echo '<li><code>{{status_color}}</code> - <span>' . __('Color code for granted (#28a745) or declined (#dc3545)', 'protecteddocs') . '</span></li>';
        echo '<li><code>{{link}}</code> - <span>' . __('Secure document access link (only available when granted)', 'protecteddocs') . '</span></li>';
        echo '</ul>';
        echo '</div>';
    }
    
    // Getter methods
    public function get_access_request_template(): array {
        $template = get_option(self::ACCESS_REQUEST_TEMPLATE_NAME, []);
        
        if (empty($template) || !isset($template['subject']) || !isset($template['body'])) {
            $template = $this->get_default_access_request_template();
            // Save the default template if it doesn't exist
            if (empty(get_option(self::ACCESS_REQUEST_TEMPLATE_NAME, []))) {
                update_option(self::ACCESS_REQUEST_TEMPLATE_NAME, $template);
            }
        }
        
        return $template;
    }
    
    public function get_auto_response_template(): array {
        $template = get_option(self::AUTO_RESPONSE_TEMPLATE_NAME, []);
        
        if (empty($template) || !isset($template['subject']) || !isset($template['body'])) {
            $template = $this->get_default_auto_response_template();
            // Save the default template if it doesn't exist
            if (empty(get_option(self::AUTO_RESPONSE_TEMPLATE_NAME, []))) {
                update_option(self::AUTO_RESPONSE_TEMPLATE_NAME, $template);
            }
        }
        
        return $template;
    }
    
    public function get_grant_decline_template(): array {
        $template = get_option(self::GRANT_DECLINE_TEMPLATE_NAME, []);
        
        if (empty($template) || !isset($template['subject']) || !isset($template['body'])) {
            $template = $this->get_default_grant_decline_template();
            // Save the default template if it doesn't exist
            if (empty(get_option(self::GRANT_DECLINE_TEMPLATE_NAME, []))) {
                update_option(self::GRANT_DECLINE_TEMPLATE_NAME, $template);
            }
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
            '{{link}}' => $variables['link'] ?? '',
            '{{document_edit_url}}' => $variables['document_edit_url'] ?? ''
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
    
    // Render methods for Display & Navigation Section
    public function render_display_navigation_section_description(): void {
        echo '<p>' . __('Configure the visual appearance and navigation behavior of your document displays. Choose color themes and pagination styles that match your website design.', 'protecteddocs') . '</p>';
    }
    
    public function render_frontend_color_palette_field(): void {
        $current_palette = get_option(self::FRONTEND_COLOR_PALETTE_NAME, 'black_white_blue');
        
        $palettes = [
            'black_white_blue' => [
                'name' => __('Black & White + Blue', 'protecteddocs'),
                'description' => __('Clean black and white theme with blue accents for buttons and highlights', 'protecteddocs'),
                'colors' => [
                    'primary' => '#2563eb',
                    'secondary' => '#ffffff',
                    'text' => '#000000',
                    'background' => '#ffffff',
                    'border' => '#e5e5e5'
                ]
            ],
            'black_gray' => [
                'name' => __('Black & Gray', 'protecteddocs'),
                'description' => __('Professional black and gray color scheme with subtle styling', 'protecteddocs'),
                'colors' => [
                    'primary' => '#374151',
                    'secondary' => '#f9fafb',
                    'text' => '#111827',
                    'background' => '#ffffff',
                    'border' => '#d1d5db'
                ]
            ]
        ];
        
        echo '<div class="authdocs-color-palettes" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-top: 16px;">';
        
        foreach ($palettes as $palette_key => $palette_data) {
            $checked = checked($palette_key, $current_palette, false);
            $is_selected = $palette_key === $current_palette;
            
            echo '<div class="authdocs-palette-option" style="
                padding: 16px; 
                border: 2px solid ' . ($is_selected ? $palette_data['colors']['primary'] : '#e5e5e5') . '; 
                border-radius: 8px; 
                background: ' . $palette_data['colors']['background'] . ';
                transition: all 0.2s ease;
                cursor: pointer;
                position: relative;
            " onclick="document.querySelector(\'input[name=\\\'' . self::FRONTEND_COLOR_PALETTE_NAME . '\\\'][value=\\\'' . esc_attr($palette_key) . '\\\']\').checked = true; this.style.borderColor = \'' . $palette_data['colors']['primary'] . '\';">
                <label style="display: block; cursor: pointer; margin: 0;">
                    <input type="radio" name="' . self::FRONTEND_COLOR_PALETTE_NAME . '" value="' . esc_attr($palette_key) . '" ' . $checked . ' style="position: absolute; opacity: 0; pointer-events: none;" />
                    
                    <div class="authdocs-palette-header" style="display: flex; align-items: center; margin-bottom: 12px;">
                        <div style="
                            width: 20px; 
                            height: 20px; 
                            border-radius: 50%; 
                            background: ' . $palette_data['colors']['primary'] . '; 
                            margin-right: 12px;
                            border: 2px solid ' . ($is_selected ? $palette_data['colors']['primary'] : '#e5e5e5') . ';
                        "></div>
                        <h4 class="authdocs-palette-title" style="margin: 0; color: ' . $palette_data['colors']['text'] . '; font-size: 16px; font-weight: 600;">' . esc_html($palette_data['name']) . '</h4>
                    </div>
                    
                    <p class="authdocs-palette-description" style="
                        margin: 0 0 12px 0; 
                        color: ' . $palette_data['colors']['text'] . '; 
                        opacity: 0.7; 
                        font-size: 14px; 
                        line-height: 1.4;
                    ">' . esc_html($palette_data['description']) . '</p>
                    
                    <div class="authdocs-palette-colors" style="display: flex; gap: 6px; align-items: center;">
                        <span style="font-size: 12px; color: ' . $palette_data['colors']['text'] . '; opacity: 0.6; margin-right: 8px;">Colors:</span>';
            
            foreach ($palette_data['colors'] as $color_name => $color_value) {
                echo '<div style="
                    width: 20px; 
                    height: 20px; 
                    background: ' . $color_value . '; 
                    border: 1px solid rgba(0,0,0,0.1); 
                    border-radius: 4px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                " title="' . esc_attr(ucfirst(str_replace('_', ' ', $color_name))) . ': ' . $color_value . '"></div>';
            }
            
            echo '</div>';
            echo '</label>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '<p class="description">' . __('Select a color palette to customize the appearance of your document displays.', 'protecteddocs') . '</p>';
    }
    
    /**
     * Get current frontend color palette
     */
    public function get_frontend_color_palette(): string {
        return get_option(self::FRONTEND_COLOR_PALETTE_NAME, 'black_white_blue');
    }
    
    /**
     * Get the selected pagination style
     */
    public function get_pagination_style(): string
    {
        return get_option(self::PAGINATION_STYLE_NAME, 'classic');
    }
    
    /**
     * Get the selected pagination type
     */
    public function get_pagination_type(): string
    {
        return get_option(self::PAGINATION_TYPE_NAME, 'ajax');
    }
    
    
    
    /**
     * Get color palette colors (simplified version for error pages)
     */
    public function get_color_palette_colors(string $palette_key = null): array {
        if (!$palette_key) {
            $palette_key = $this->get_frontend_color_palette();
        }
        
        $palettes = [
            'black_white_blue' => [
                'primary' => '#2563eb',
                'secondary' => '#ffffff',
                'text' => '#000000',
                'background' => '#ffffff',
                'border' => '#e5e5e5'
            ],
            'black_gray' => [
                'primary' => '#374151',
                'secondary' => '#f9fafb',
                'text' => '#111827',
                'background' => '#ffffff',
                'border' => '#d1d5db'
            ]
        ];
        
        return $palettes[$palette_key] ?? $palettes['black_white_blue'];
    }
    
    /**
     * Get color palette data
     */
    public function get_color_palette_data(string $palette_key = null): array {
        if (!$palette_key) {
            $palette_key = $this->get_frontend_color_palette();
        }
        
        $palettes = [
            'black_white_blue' => [
                'primary' => '#2563eb',
                'secondary' => '#ffffff',
                'text' => '#000000',
                'text_secondary' => '#666666',
                'background' => '#ffffff',
                'background_secondary' => '#f9f9f9',
                'border' => '#e5e5e5',
                'border_radius' => '4px',
                'shadow' => '0 2px 4px rgba(0, 0, 0, 0.1)'
            ],
            'black_gray' => [
                'primary' => '#374151',
                'secondary' => '#f9fafb',
                'text' => '#111827',
                'text_secondary' => '#6b7280',
                'background' => '#ffffff',
                'background_secondary' => '#f3f4f6',
                'border' => '#d1d5db',
                'border_radius' => '4px',
                'shadow' => '0 2px 4px rgba(0, 0, 0, 0.1)'
            ]
        ];
        
        return $palettes[$palette_key] ?? $palettes['black_white_blue'];
    }
    
    /**
     * Render hidden fields for email templates to preserve values when saving from other tabs
     */
    public function render_hidden_email_template_fields(): void {
        // Access Request Template
        $access_template = $this->get_access_request_template();
        echo '<input type="hidden" name="' . self::ACCESS_REQUEST_TEMPLATE_NAME . '[subject]" value="' . esc_attr($access_template['subject'] ?? '') . '" />';
        echo '<input type="hidden" name="' . self::ACCESS_REQUEST_TEMPLATE_NAME . '[body]" value="' . esc_attr($access_template['body'] ?? '') . '" />';
        
        // Auto-Response Template
        $auto_template = $this->get_auto_response_template();
        echo '<input type="hidden" name="' . self::AUTO_RESPONSE_TEMPLATE_NAME . '[enabled]" value="' . ($auto_template['enabled'] ? '1' : '0') . '" />';
        echo '<input type="hidden" name="' . self::AUTO_RESPONSE_TEMPLATE_NAME . '[subject]" value="' . esc_attr($auto_template['subject'] ?? '') . '" />';
        echo '<input type="hidden" name="' . self::AUTO_RESPONSE_TEMPLATE_NAME . '[body]" value="' . esc_attr($auto_template['body'] ?? '') . '" />';
        
        // Grant/Decline Template
        $grant_template = $this->get_grant_decline_template();
        echo '<input type="hidden" name="' . self::GRANT_DECLINE_TEMPLATE_NAME . '[subject]" value="' . esc_attr($grant_template['subject'] ?? '') . '" />';
        echo '<input type="hidden" name="' . self::GRANT_DECLINE_TEMPLATE_NAME . '[body]" value="' . esc_attr($grant_template['body'] ?? '') . '" />';
        
        // Recipients
        echo '<input type="hidden" name="' . self::ACCESS_REQUEST_RECIPIENTS_NAME . '" value="' . esc_attr(get_option(self::ACCESS_REQUEST_RECIPIENTS_NAME, '')) . '" />';
        echo '<input type="hidden" name="' . self::GRANT_DECLINE_RECIPIENTS_NAME . '" value="' . esc_attr(get_option(self::GRANT_DECLINE_RECIPIENTS_NAME, '')) . '" />';
    }
}