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
    private const FRONTEND_COLOR_PALETTE_NAME = 'authdocs_frontend_color_palette';
    private const PAGINATION_STYLE_NAME = 'authdocs_pagination_style';
    private const PAGINATION_TYPE_NAME = 'authdocs_pagination_type';
    
    public function __construct() {
        add_action('admin_init', [$this, 'init_settings']);
        add_action('admin_init', [$this, 'ensure_default_templates']);
        add_action('admin_init', [$this, 'handle_tab_preservation']);
        add_filter('wp_redirect', [$this, 'preserve_tab_in_redirect'], 10, 2);
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
                'default' => 'black_white'
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
            __('Access Request Email Template', 'authdocs'),
            [$this, 'render_access_request_section_description'],
            'authdocs-settings'
        );
        
        add_settings_field(
            'access_request_subject',
            '<span class="authdocs-email-subject-label">' . __('Email Subject', 'authdocs') . '</span>',
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
            '<span class="authdocs-email-subject-label">' . __('Email Subject', 'authdocs') . '</span>',
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
            '<span class="authdocs-email-subject-label">' . __('Email Subject', 'authdocs') . '</span>',
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
        
        // Frontend Color Palette Section
        add_settings_section(
            'authdocs_frontend_colors_section',
            __('Frontend Color Palette', 'authdocs'),
            [$this, 'render_frontend_colors_section_description'],
            'authdocs-settings'
        );
        
        add_settings_field(
            'frontend_color_palette',
            '<span class="authdocs-color-palette-label">' . __('Color Palette', 'authdocs') . '</span>',
            [$this, 'render_frontend_color_palette_field'],
            'authdocs-settings',
            'authdocs_frontend_colors_section'
        );
        
        // Pagination Section
        add_settings_section(
            'authdocs_pagination_section',
            __('Pagination Settings', 'authdocs'),
            [$this, 'render_pagination_section_description'],
            'authdocs-settings'
        );
        
        add_settings_field(
            'pagination_type',
            '<span class="authdocs-pagination-type-label">' . __('Pagination Type', 'authdocs') . '</span>',
            [$this, 'render_pagination_type_field'],
            'authdocs-settings',
            'authdocs_pagination_section'
        );
        
        add_settings_field(
            'pagination_style',
            '<span class="authdocs-pagination-style-label">' . __('Pagination Style', 'authdocs') . '</span>',
            [$this, 'render_pagination_style_field'],
            'authdocs-settings',
            'authdocs_pagination_section'
        );
        
        // General Section
        add_settings_section(
            'authdocs_general_section',
            __('General Settings', 'authdocs'),
            [$this, 'render_general_section_description'],
            'authdocs-settings'
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
            'enabled' => true,
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
            return 'black_white';
        }
        
        $allowed_palettes = ['black_white', 'blue_gray'];
        return in_array($input, $allowed_palettes) ? $input : 'black_white';
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
    
    public function render_pagination_section_description(): void
    {
        echo '<p>' . __('Configure how pagination is displayed on the frontend.', 'authdocs') . '</p>';
    }
    
    public function render_pagination_type_field(): void
    {
        $current_type = $this->get_pagination_type();
        ?>
        <fieldset>
            <legend class="screen-reader-text"><?php _e('Pagination Type', 'authdocs'); ?></legend>
            
            <div class="authdocs-pagination-type-options">
                <label class="authdocs-pagination-type-option">
                    <input type="radio" name="<?php echo self::PAGINATION_TYPE_NAME; ?>" value="ajax" 
                           <?php checked($current_type, 'ajax'); ?> />
                    <div class="authdocs-option-content">
                        <strong class="authdocs-option-title"><?php _e('AJAX Pagination', 'authdocs'); ?></strong>
                        <p class="authdocs-option-description"><?php _e('Fast, seamless pagination without page reloads.', 'authdocs'); ?></p>
                    </div>
                </label>
                
                <label class="authdocs-pagination-type-option">
                    <input type="radio" name="<?php echo self::PAGINATION_TYPE_NAME; ?>" value="classic" 
                           <?php checked($current_type, 'classic'); ?> 
                           onchange="authdocsConfirmClassicPagination(this)" />
                    <div class="authdocs-option-content">
                        <strong class="authdocs-option-title"><?php _e('Classic Pagination', 'authdocs'); ?></strong>
                        <p class="authdocs-option-description"><?php _e('Traditional pagination with full page reloads.', 'authdocs'); ?></p>
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
            <legend class="screen-reader-text"><?php _e('Pagination Style', 'authdocs'); ?></legend>
            
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
                        <strong class="authdocs-option-title"><?php _e('Classic Pagination (AJAX)', 'authdocs'); ?></strong>
                        <p class="authdocs-option-description"><?php _e('Traditional page numbers with Previous/Next buttons for easy navigation.', 'authdocs'); ?></p>
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
                        <strong class="authdocs-option-title"><?php _e('Load More (AJAX)', 'authdocs'); ?></strong>
                        <p class="authdocs-option-description"><?php _e('Progressive loading with a "Load More" button for seamless browsing.', 'authdocs'); ?></p>
                    </div>
                </label>
            </div>
        </fieldset>
        <?php
    }
    
    public function render_general_section_description(): void
    {
        echo '<p>' . __('General plugin settings and information.', 'authdocs') . '</p>';
    }
    
    // Render methods for Access Request Email
    public function render_access_request_section_description(): void {
        echo '<p>' . __('Configure the email template that will be sent to website owners when a document access request is submitted.', 'authdocs') . '</p>';
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
        echo '<p class="description">' . __('Subject line for the access request notification email.', 'authdocs') . '</p>';
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
        echo '<p class="description">' . __('HTML email body for access request notifications.', 'authdocs') . '</p>';
    }
    
    public function render_access_request_recipients_field(): void {
        $recipients = get_option(self::ACCESS_REQUEST_RECIPIENTS_NAME, get_option('admin_email'));
        
        echo '<input type="text" id="access_request_recipients" name="' . self::ACCESS_REQUEST_RECIPIENTS_NAME . '" value="' . esc_attr($recipients) . '" class="regular-text authdocs-text-input" placeholder="admin@example.com, manager@example.com" />';
        echo '<p class="description">' . __('Enter email addresses separated by commas. These will receive notifications when access is requested.', 'authdocs') . '</p>';
    }
    
    public function render_access_request_variables_help(): void {
        echo '<div class="authdocs-variables-help">';
        echo '<h4>' . __('Available Variables:', 'authdocs') . '</h4>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>{{name}}</code> - <span>' . __('Requester\'s name', 'authdocs') . '</span></li>';
        echo '<li><code>{{email}}</code> - <span>' . __('Requester\'s email address', 'authdocs') . '</span></li>';
        echo '<li><code>{{file_name}}</code> - <span>' . __('Name of the requested file', 'authdocs') . '</span></li>';
        echo '<li><code>{{site_name}}</code> - <span>' . __('Name of your website', 'authdocs') . '</span></li>';
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
        echo ' <span>' . __('Enable auto-response emails', 'authdocs') . '</span></label>';
        echo '<p class="description">' . __('When enabled, users will automatically receive a confirmation email after submitting a document access request.', 'authdocs') . '</p>';
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
        echo '<p class="description">' . __('Subject line for the auto-response email.', 'authdocs') . '</p>';
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
        echo '<p class="description">' . __('HTML email body for auto-response emails.', 'authdocs') . '</p>';
    }
    
    public function render_auto_response_variables_help(): void {
        echo '<div class="authdocs-variables-help">';
        echo '<h4>' . __('Available Variables:', 'authdocs') . '</h4>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>{{name}}</code> - <span>' . __('Requester\'s name', 'authdocs') . '</span></li>';
        echo '<li><code>{{email}}</code> - <span>' . __('Requester\'s email address', 'authdocs') . '</span></li>';
        echo '<li><code>{{file_name}}</code> - <span>' . __('Name of the requested file', 'authdocs') . '</span></li>';
        echo '<li><code>{{site_name}}</code> - <span>' . __('Name of your website', 'authdocs') . '</span></li>';
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
        
        // If subject is empty, use default
        if (empty($subject)) {
            $default_template = $this->get_default_grant_decline_template();
            $subject = $default_template['subject'];
        }
        
        echo '<input type="text" id="grant_decline_subject" name="' . self::GRANT_DECLINE_TEMPLATE_NAME . '[subject]" value="' . esc_attr($subject) . '" class="regular-text authdocs-text-input" />';
        echo '<p class="description">' . __('Subject line for grant/decline emails. Use {{status}} for "Granted" or "Declined".', 'authdocs') . '</p>';
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
        echo '<p class="description">' . __('HTML email body for grant/decline notifications. Use {{link}} for the document access link when granted.', 'authdocs') . '</p>';
    }
    
    public function render_grant_decline_recipients_field(): void {
        $recipients = get_option(self::GRANT_DECLINE_RECIPIENTS_NAME, '{{email}}');
        
        echo '<input type="text" id="grant_decline_recipients" name="' . self::GRANT_DECLINE_RECIPIENTS_NAME . '" value="' . esc_attr($recipients) . '" class="regular-text authdocs-text-input" placeholder="{{email}}" />';
        echo '<p class="description">' . __('Enter email addresses separated by commas, or use {{email}} to send to the requester.', 'authdocs') . '</p>';
    }
    
    public function render_grant_decline_variables_help(): void {
        echo '<div class="authdocs-variables-help">';
        echo '<h4>' . __('Available Variables:', 'authdocs') . '</h4>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li><code>{{name}}</code> - <span>' . __('Requester\'s name', 'authdocs') . '</span></li>';
        echo '<li><code>{{email}}</code> - <span>' . __('Requester\'s email address', 'authdocs') . '</span></li>';
        echo '<li><code>{{file_name}}</code> - <span>' . __('Name of the requested file', 'authdocs') . '</span></li>';
        echo '<li><code>{{site_name}}</code> - <span>' . __('Name of your website', 'authdocs') . '</span></li>';
        echo '<li><code>{{status}}</code> - <span>' . __('"Granted" or "Declined"', 'authdocs') . '</span></li>';
        echo '<li><code>{{status_color}}</code> - <span>' . __('Color code for granted (#28a745) or declined (#dc3545)', 'authdocs') . '</span></li>';
        echo '<li><code>{{link}}</code> - <span>' . __('Secure document access link (only available when granted)', 'authdocs') . '</span></li>';
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
    
    // Render methods for Frontend Color Palette
    public function render_frontend_colors_section_description(): void {
        echo '<p>' . __('Choose a color palette for the frontend document display. Changes will apply to all shortcodes and document grids.', 'authdocs') . '</p>';
    }
    
    public function render_frontend_color_palette_field(): void {
        $current_palette = get_option(self::FRONTEND_COLOR_PALETTE_NAME, 'black_white');
        
        $palettes = [
            'black_white' => [
                'name' => __('Black & White', 'authdocs'),
                'description' => __('Clean black and white theme with minimal styling', 'authdocs'),
                'colors' => [
                    'primary' => '#000000',
                    'secondary' => '#ffffff',
                    'text' => '#000000',
                    'background' => '#ffffff',
                    'border' => '#e5e5e5'
                ]
            ],
            'blue_gray' => [
                'name' => __('Blue & Gray', 'authdocs'),
                'description' => __('Professional blue and gray color scheme', 'authdocs'),
                'colors' => [
                    'primary' => '#2563eb',
                    'secondary' => '#f8fafc',
                    'text' => '#1e293b',
                    'background' => '#ffffff',
                    'border' => '#e2e8f0'
                ]
            ]
        ];
        
        echo '<div class="authdocs-color-palettes">';
        
        foreach ($palettes as $palette_key => $palette_data) {
            $checked = checked($palette_key, $current_palette, false);
            
            echo '<div class="authdocs-palette-option" style="margin-bottom: 20px; padding: 20px; border: 2px solid ' . ($palette_key === $current_palette ? $palette_data['colors']['primary'] : '#e5e5e5') . '; border-radius: 8px; background: ' . $palette_data['colors']['background'] . ';">';
            echo '<label style="display: flex; align-items: center; cursor: pointer;">';
            echo '<input type="radio" name="' . self::FRONTEND_COLOR_PALETTE_NAME . '" value="' . esc_attr($palette_key) . '" ' . $checked . ' style="margin-right: 12px;" />';
            echo '<div class="authdocs-palette-content">';
            echo '<h4 class="authdocs-palette-title" style="margin: 0 0 5px 0; color: ' . $palette_data['colors']['text'] . ';">' . esc_html($palette_data['name']) . '</h4>';
            echo '<p class="authdocs-palette-description" style="margin: 0 0 10px 0; color: ' . $palette_data['colors']['text'] . '; opacity: 0.7;">' . esc_html($palette_data['description']) . '</p>';
            
            // Color preview
            echo '<div style="display: flex; gap: 8px;">';
            foreach ($palette_data['colors'] as $color_name => $color_value) {
                echo '<div style="width: 24px; height: 24px; background: ' . $color_value . '; border: 1px solid #ddd; border-radius: 4px;" title="' . esc_attr($color_name) . '"></div>';
            }
            echo '</div>';
            
            echo '</div>';
            echo '</label>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '<p class="description">' . __('Select a color palette to customize the appearance of your document displays.', 'authdocs') . '</p>';
    }
    
    /**
     * Get current frontend color palette
     */
    public function get_frontend_color_palette(): string {
        return get_option(self::FRONTEND_COLOR_PALETTE_NAME, 'black_white');
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
     * Handle tab preservation after form submission
     */
    public function handle_tab_preservation(): void {
        // Only handle POST requests from our settings page
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['authdocs_current_tab'])) {
            $current_tab = sanitize_text_field($_POST['authdocs_current_tab']);
            $allowed_tabs = ['email-templates', 'frontend-settings', 'about-plugin'];
            
            if (in_array($current_tab, $allowed_tabs)) {
                // Store the current tab in a transient for the next page load
                set_transient('authdocs_preserve_tab', $current_tab, 30); // 30 seconds
            }
        }
    }
    
    /**
     * Get preserved tab from transient
     */
    public function get_preserved_tab(): string {
        $preserved_tab = get_transient('authdocs_preserve_tab');
        if ($preserved_tab) {
            // Clear the transient after use
            delete_transient('authdocs_preserve_tab');
            return $preserved_tab;
        }
        return '';
    }
    
    /**
     * Preserve tab parameter in settings redirect
     */
    public function preserve_tab_in_redirect($location, $status) {
        // Only handle redirects from our settings page that are settings updates
        if (strpos($location, 'page=authdocs-settings') !== false && strpos($location, 'settings-updated=true') !== false) {
            // Get the preserved tab from transient
            $preserved_tab = get_transient('authdocs_preserve_tab');
            if ($preserved_tab) {
                $allowed_tabs = ['email-templates', 'frontend-settings', 'about-plugin'];
                if (in_array($preserved_tab, $allowed_tabs)) {
                    // Add tab parameter to the redirect URL
                    $separator = strpos($location, '?') !== false ? '&' : '?';
                    $location .= $separator . 'tab=' . urlencode($preserved_tab);
                }
            }
        }
        return $location;
    }
    
    /**
     * Get color palette data
     */
    public function get_color_palette_data(string $palette_key = null): array {
        if (!$palette_key) {
            $palette_key = $this->get_frontend_color_palette();
        }
        
        $palettes = [
            'black_white' => [
                'primary' => '#000000',
                'secondary' => '#ffffff',
                'text' => '#000000',
                'text_secondary' => '#666666',
                'background' => '#ffffff',
                'background_secondary' => '#f9f9f9',
                'border' => '#e5e5e5',
                'border_radius' => '4px',
                'shadow' => '0 2px 4px rgba(0, 0, 0, 0.1)'
            ],
            'blue_gray' => [
                'primary' => '#2563eb',
                'secondary' => '#f8fafc',
                'text' => '#1e293b',
                'text_secondary' => '#64748b',
                'background' => '#ffffff',
                'background_secondary' => '#f1f5f9',
                'border' => '#e2e8f0',
                'border_radius' => '4px',
                'shadow' => '0 2px 4px rgba(0, 0, 0, 0.1)'
            ]
        ];
        
        return $palettes[$palette_key] ?? $palettes['black_white'];
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