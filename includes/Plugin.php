<?php
/**
 * Main plugin class for AuthDocs
 * 
 * @since 1.1.0 Email logic separation; new autoresponder recipient; trigger fixes.
 */
declare(strict_types=1);

namespace AuthDocs;

class Plugin
{
    public function __construct()
    {
        $this->init_hooks();
        $this->load_dependencies();
    }

    private function init_hooks(): void
    {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_authdocs_request_access', [$this, 'handle_access_request']);
        add_action('wp_ajax_nopriv_authdocs_request_access', [$this, 'handle_access_request']);
        add_action('wp_ajax_authdocs_manage_request', [$this, 'handle_request_management']);
        add_action('wp_ajax_authdocs_test_email', [$this, 'handle_test_email']);
        add_action('wp_ajax_authdocs_test_autoresponder', [$this, 'handle_test_autoresponder']);
        add_action('wp_ajax_authdocs_test_access_request', [$this, 'handle_test_access_request']);
        add_action('wp_ajax_authdocs_test_auto_response', [$this, 'handle_test_auto_response']);
        add_action('wp_ajax_authdocs_test_grant_decline', [$this, 'handle_test_grant_decline']);
        add_action('wp_ajax_authdocs_get_request_data', [$this, 'handle_get_request_data']);
        add_action('wp_ajax_authdocs_load_more_documents', [$this, 'handle_load_more_documents']);
        add_action('wp_ajax_nopriv_authdocs_load_more_documents', [$this, 'handle_load_more_documents']);
        add_action('wp_ajax_authdocs_paginate_documents', [$this, 'handle_paginate_documents']);
        add_action('wp_ajax_nopriv_authdocs_paginate_documents', [$this, 'handle_paginate_documents']);
        
        // Email hooks
        add_action('authdocs/request_submitted', [$this, 'handle_request_submitted_hook']);
        add_action('template_redirect', [$this, 'protect_document_files']);
        add_action('init', [$this, 'protect_media_files']);
    }

    private function load_dependencies(): void
    {
        new CustomPostType();
        new Shortcode();
        new Admin();
        new Database();
        new Settings();
        new Email();
        
        // Initialize new components
        new LinkHandler();
        new Logs();
    }

    public function init(): void
    {
        load_plugin_textdomain('authdocs', false, dirname(plugin_basename(AUTHDOCS_PLUGIN_FILE)) . '/languages');
    }

    public function add_admin_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=document',
            __('Document Requests', 'authdocs'),
            __('Requests', 'authdocs'),
            'manage_options',
            'authdocs-requests',
            [$this, 'requests_page']
        );
        
        
        add_submenu_page(
            'edit.php?post_type=document',
            __('Email Settings', 'authdocs'),
            __('Settings', 'authdocs'),
            'manage_options',
            'authdocs-settings',
            [$this, 'settings_page']
        );
    }

    public function enqueue_frontend_assets(): void
    {
        // Don't load frontend assets in admin area, even for logged-in users
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            'authdocs-frontend',
            AUTHDOCS_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            AUTHDOCS_VERSION
        );

        wp_enqueue_script(
            'authdocs-frontend',
            AUTHDOCS_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            AUTHDOCS_VERSION,
            true
        );

        wp_localize_script('authdocs-frontend', 'authdocs_frontend', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('authdocs_frontend_nonce'),
            'request_access_title' => __('Request Document Access', 'authdocs'),
            'name_label' => __('Full Name', 'authdocs'),
            'email_label' => __('Email Address', 'authdocs'),
            'cancel_label' => __('Cancel', 'authdocs'),
            'submit_label' => __('Submit Request', 'authdocs'),
            'submitting_label' => __('Submitting...', 'authdocs'),
            'loading_label' => __('Loading...', 'authdocs'),
            'load_more_label' => __('Load More Documents', 'authdocs')
        ]);
    }

    public function enqueue_admin_assets(): void
    {
        // Only load admin assets on our specific admin pages
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['document_page_authdocs-requests', 'document_page_authdocs-settings'])) {
            return;
        }

        // Enqueue dashicons for action link icons
        wp_enqueue_style('dashicons');

        wp_enqueue_style(
            'authdocs-admin',
            AUTHDOCS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AUTHDOCS_VERSION
        );

        wp_enqueue_script(
            'authdocs-admin',
            AUTHDOCS_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            AUTHDOCS_VERSION,
            true
        );

        wp_localize_script('authdocs-admin', 'authdocs_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('authdocs_manage_nonce'),
            'site_url' => home_url('/'),
        ]);
    }

    public function handle_access_request(): void
    {
        try {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'authdocs_frontend_nonce')) {
                wp_send_json_error([
                    'message' => __('Security check failed', 'authdocs')
                ]);
            }

            $document_id = intval($_POST['document_id'] ?? 0);
            $name = sanitize_text_field($_POST['name'] ?? '');
            $email = sanitize_email($_POST['email'] ?? '');

            if (!$document_id || !$name || !$email) {
                wp_send_json_error([
                    'message' => __('Missing required fields', 'authdocs')
                ]);
            }

            $result = Database::save_access_request($document_id, $name, $email);
            
            if ($result) {
                // Fire the request submitted hook
                do_action('authdocs/request_submitted', $result);
                
                wp_send_json_success([
                    'message' => __('Request submitted successfully', 'authdocs'),
                    'request_id' => $result
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to submit request', 'authdocs')
                ]);
            }
        } catch (Exception $e) {
            error_log('AuthDocs: Error in handle_access_request: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An unexpected error occurred', 'authdocs')
            ]);
        }
    }

    public function handle_request_management(): void
    {
        error_log("AuthDocs: handle_request_management called");
        error_log("AuthDocs: POST data: " . json_encode($_POST));
        
        if (!current_user_can('manage_options')) {
            error_log("AuthDocs: Insufficient permissions");
            wp_die(__('Insufficient permissions', 'authdocs'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'authdocs_manage_nonce')) {
            error_log("AuthDocs: Security check failed");
            wp_die(__('Security check failed', 'authdocs'));
        }

        $request_id = intval($_POST['request_id'] ?? 0);
        $action = sanitize_text_field($_POST['action_type'] ?? '');

        if (!$request_id || !in_array($action, ['accept', 'decline', 'inactive', 'delete'])) {
            wp_send_json_error(__('Invalid request', 'authdocs'));
        }

        // Get current request to determine toggle behavior
        $current_request = Database::get_request_by_id($request_id);
        if (!$current_request) {
            wp_send_json_error(__('Request not found', 'authdocs'));
        }

        // Handle delete action separately
        if ($action === 'delete') {
            $result = Database::delete_request($request_id);
            if ($result) {
                wp_send_json_success([
                    'message' => __('Request deleted successfully.', 'authdocs'),
                    'deleted' => true
                ]);
            } else {
                wp_send_json_error(__('Failed to delete request', 'authdocs'));
            }
        }

        // Map action to database status with toggle logic for inactive
        $status_map = [
            'accept' => 'accepted',
            'decline' => 'declined',
            'inactive' => $current_request->status === 'inactive' ? 'restore' : 'inactive' // 'restore' will trigger status restoration in Database::update_request_status
        ];
        
        $status = $status_map[$action] ?? $action;
        
        error_log("AuthDocs: Updating request ID {$request_id} from action '{$action}' to status '{$status}'");
        
        $old_status = Database::get_request_status($request_id);
        $result = Database::update_request_status($request_id, $status);
        
        if ($result) {
            // Get the actual final status after update (in case it was restored from pending)
            $final_status = Database::get_request_status($request_id);
            error_log("AuthDocs: Request updated successfully. Old status: {$old_status}, Final status: {$final_status}");
            
            // Fire the status change hook with the final status and capture email result
            error_log("AuthDocs: Firing status change hook with final status: {$final_status}");
            $email_sent = $this->handle_status_change_with_email($request_id, $old_status, $final_status);
            
            // Prepare response message based on action and email result
            $message = $this->get_action_response_message($action, $final_status, $email_sent);
            
            wp_send_json_success([
                'message' => $message,
                'email_sent' => $email_sent,
                'status' => $final_status
            ]);
        } else {
            error_log("AuthDocs: Failed to update request status");
            wp_send_json_error(__('Failed to update request', 'authdocs'));
        }
    }
    
    /**
     * Handle status change with email sending and return result
     */
    private function handle_status_change_with_email(int $request_id, string $old_status, string $new_status): bool {
        $email = new Email();
        $email_sent = false;
        
        // Send grant/decline email based on status
        if ($new_status === 'accepted') {
            error_log("AuthDocs: Status is 'accepted', sending grant email");
            $email_sent = $email->send_grant_decline_email($request_id, true);
        } elseif ($new_status === 'declined') {
            error_log("AuthDocs: Status is 'declined', sending decline email");
            $email_sent = $email->send_grant_decline_email($request_id, false);
        } else {
            error_log("AuthDocs: Status is not 'accepted' or 'declined' ({$new_status}), not sending email");
        }
        
        return $email_sent;
    }
    
    /**
     * Get response message based on action and email result
     */
    private function get_action_response_message(string $action, string $status, bool $email_sent): string {
        $base_message = '';
        $email_message = '';
        
        // Base message based on action
        switch ($action) {
            case 'accept':
                $base_message = $status === 'accepted' ? 
                    __('Request accepted successfully.', 'authdocs') : 
                    __('Request re-accepted successfully.', 'authdocs');
                break;
            case 'decline':
                $base_message = $status === 'declined' ? 
                    __('Request declined successfully.', 'authdocs') : 
                    __('Request re-declined successfully.', 'authdocs');
                break;
            case 'inactive':
                $base_message = $status === 'inactive' ? 
                    __('Document link hidden successfully.', 'authdocs') : 
                    __('Document link restored successfully.', 'authdocs');
                break;
            default:
                $base_message = __('Request updated successfully.', 'authdocs');
        }
        
        // Email message based on result
        if ($email_sent && in_array($status, ['accepted', 'declined'])) {
            $email_message = $status === 'accepted' ? 
                __(' Grant email sent to requester.', 'authdocs') : 
                __(' Decline email sent to requester.', 'authdocs');
        } elseif (!$email_sent && in_array($status, ['accepted', 'declined'])) {
            $email_message = __(' Warning: Email could not be sent.', 'authdocs');
        }
        
        return $base_message . $email_message;
    }
    
    public function requests_page(): void
    {
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 5;
        $total_requests = Database::get_total_requests_count();
        $total_pages = ceil($total_requests / $per_page);
        $requests = Database::get_paginated_requests($current_page, $per_page);
        
        include AUTHDOCS_PLUGIN_DIR . 'templates/admin/requests-page.php';
    }

    public function protect_document_files(): void
    {
        // Check if this is a direct media file access
        if (!isset($_GET['attachment_id'])) {
            return;
        }

        $attachment_id = intval($_GET['attachment_id']);
        
        // Check if this attachment is used by any AuthDocs document
        $document_posts = get_posts([
            'post_type' => 'document',
            'meta_query' => [
                [
                    'key' => '_authdocs_file_id',
                    'value' => $attachment_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);

        // If this file is used by an AuthDocs document, block direct access
        if (!empty($document_posts)) {
            wp_die(__('Access denied. This file requires authorization through the document sharing system.', 'authdocs'), __('Access Denied', 'authdocs'), ['response' => 403]);
        }
    }

    public function protect_media_files(): void
    {
        // Get the current request URI
        $request_uri = $_SERVER['REQUEST_URI'];
        
        // Check if this is a media file request
        if (strpos($request_uri, '/wp-content/uploads/') !== false) {
            // Extract the file path
            $upload_dir = wp_upload_dir();
            $upload_path = str_replace($upload_dir['baseurl'], '', $request_uri);
            $file_path = $upload_dir['basedir'] . $upload_path;
            
            // Check if file exists
            if (file_exists($file_path)) {
                // Get all AuthDocs documents and check if this file is used
                $document_posts = get_posts([
                    'post_type' => 'document',
                    'meta_key' => '_authdocs_file_id',
                    'posts_per_page' => -1
                ]);
                
                foreach ($document_posts as $document) {
                    $file_id = get_post_meta($document->ID, '_authdocs_file_id', true);
                    if ($file_id) {
                        $attachment_path = get_attached_file($file_id);
                        if ($attachment_path && realpath($attachment_path) === realpath($file_path)) {
                            wp_die(__('Access denied. This file requires authorization through the document sharing system.', 'authdocs'), __('Access Denied', 'authdocs'), ['response' => 403]);
                        }
                    }
                }
            }
        }
    }


    
    public function settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'authdocs'));
        }
        
        include AUTHDOCS_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }
    
    public function handle_test_email(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'authdocs'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'authdocs_manage_nonce')) {
            wp_send_json_error(__('Security check failed', 'authdocs'));
        }
        
        $email = new Email();
        $result = $email->send_test_email();
        
        if ($result) {
            wp_send_json_success(__('Test email sent successfully! Check your admin email.', 'authdocs'));
        } else {
            wp_send_json_error(__('Failed to send test email. Check your WordPress email configuration.', 'authdocs'));
        }
    }
    
    public function handle_test_autoresponder(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'authdocs'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'authdocs_manage_nonce')) {
            wp_send_json_error(__('Security check failed', 'authdocs'));
        }
        
        $email = new Email();
        $result = $email->send_test_autoresponder_email();
        
        if ($result) {
            wp_send_json_success(__('Test autoresponder email sent successfully! Check your admin email.', 'authdocs'));
        } else {
            wp_send_json_error(__('Failed to send test autoresponder email. Make sure autoresponder is enabled and check your WordPress email configuration.', 'authdocs'));
        }
    }
    
    public function handle_test_access_request(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'authdocs'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'authdocs_manage_nonce')) {
            wp_send_json_error(__('Security check failed', 'authdocs'));
        }
        
        $email = new Email();
        $result = $email->send_test_access_request_email();
        
        if ($result) {
            wp_send_json_success(__('Test access request email sent successfully! Check your admin email.', 'authdocs'));
        } else {
            wp_send_json_error(__('Failed to send test access request email. Check your WordPress email configuration.', 'authdocs'));
        }
    }
    
    public function handle_test_auto_response(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'authdocs'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'authdocs_manage_nonce')) {
            wp_send_json_error(__('Security check failed', 'authdocs'));
        }
        
        $email = new Email();
        $result = $email->send_test_auto_response_email();
        
        if ($result) {
            wp_send_json_success(__('Test auto-response email sent successfully! Check your admin email.', 'authdocs'));
        } else {
            wp_send_json_error(__('Failed to send test auto-response email. Make sure auto-response is enabled and check your WordPress email configuration.', 'authdocs'));
        }
    }
    
    public function handle_test_grant_decline(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'authdocs'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'authdocs_manage_nonce')) {
            wp_send_json_error(__('Security check failed', 'authdocs'));
        }
        
        $email = new Email();
        $result = $email->send_test_grant_decline_email(true); // Test with granted status
        
        if ($result) {
            wp_send_json_success(__('Test grant/decline email sent successfully! Check your admin email.', 'authdocs'));
        } else {
            wp_send_json_error(__('Failed to send test grant/decline email. Check your WordPress email configuration.', 'authdocs'));
        }
    }
    
    /**
     * Handle request submitted hook
     */
    public function handle_request_submitted(int $request_id): void
    {
        $email = new Email();
        // Send access request notification to website owners
        $email->send_access_request_email($request_id);
        // Send auto-response to requester
        $email->send_auto_response_email($request_id);
    }
    
    /**
     * Handle request submitted hook (WordPress hook compatibility)
     */
    public function handle_request_submitted_hook($request_id): void
    {
        // Ensure we have a valid request ID
        if (!is_numeric($request_id)) {
            return;
        }
        
        $request_id = (int) $request_id;
        $email = new Email();
        // Send access request notification to website owners
        $email->send_access_request_email($request_id);
        // Send auto-response to requester
        $email->send_auto_response_email($request_id);
    }
    
    /**
     * Handle AJAX request to get updated request data
     */
    public function handle_get_request_data(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'authdocs'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'authdocs_manage_nonce')) {
            wp_send_json_error(__('Security check failed', 'authdocs'));
        }

        $request_id = intval($_POST['request_id'] ?? 0);
        
        if (!$request_id) {
            wp_send_json_error(__('Request ID required', 'authdocs'));
        }

        $request = Database::get_request_by_id($request_id);
        if (!$request) {
            wp_send_json_error(__('Request not found', 'authdocs'));
        }

        // Get document file information
        $document_file = Database::get_document_file(intval($request->document_id));
        
        // Get document title using the robust helper method
        $document_title = Database::get_document_title(intval($request->document_id));
        
        // Prepare response data
        $response_data = [
            'id' => $request->id,
            'document_id' => $request->document_id,
            'document_title' => $document_title,
            'requester_name' => $request->requester_name,
            'requester_email' => $request->requester_email,
            'status' => $request->status,
            'secure_hash' => $request->secure_hash,
            'created_at' => $request->created_at,
            'document_file' => $document_file
        ];

        wp_send_json_success($response_data);
    }
    
    /**
     * Generate grid items HTML with color palette styling
     */
    private function generate_grid_items_html(array $documents): string
    {
        $settings = new Settings();
        $color_palette = $settings->get_color_palette_data();
        
        ob_start();
        foreach ($documents as $document): ?>
            <!-- Card -->
            <article class="card">
                <div class="card-body">
                    <?php if (has_post_thumbnail($document['id'])): ?>
                        <div class="card-featured-image">
                            <?php echo get_the_post_thumbnail($document['id'], 'medium', ['class' => 'authdocs-card-thumbnail']); ?>
                        </div>
                    <?php else: ?>
                        <span class="card-icon" aria-hidden="true">📄</span>
                    <?php endif; ?>
                    <h3 class="card-title"><?php echo esc_html($document['title']); ?></h3>
                    <?php if (!empty($document['description'])): ?>
                        <p class="card-desc"><?php echo wp_kses_post(wp_trim_words($document['description'], 15)); ?></p>
                    <?php endif; ?>
                    <div class="card-date"><?php echo esc_html($document['date']); ?></div>
                </div>

                <!-- Overlay -->
                <div class="card-overlay" aria-hidden="true">
                    <div class="overlay-content">
                        <?php if ($document['restricted']): ?>
                            <button type="button" class="authdocs-request-access-btn" data-document-id="<?php echo esc_attr($document['id']); ?>" title="<?php _e('Request Access', 'authdocs'); ?>">
                                <svg class="authdocs-lock-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                </svg>
                            </button>
                        <?php else: ?>
                            <a href="<?php echo esc_url($document['file_data']['url']); ?>" class="authdocs-download-btn" download title="<?php _e('Open Document', 'authdocs'); ?>">
                                <svg class="authdocs-open-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M14,3V5H17.59L7.76,14.83L9.17,16.24L19,6.41V10H21V3M19,19H5V5H12V3H5C3.89,3 3,3.9 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V12H19V19Z"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach;
        $html = ob_get_clean();
        
        // Add inline styles for color palette
        $css = $this->generate_ajax_color_css($color_palette);
        
        return $css . $html;
    }
    
    /**
     * Generate CSS for AJAX-loaded content
     */
    private function generate_ajax_color_css(array $color_palette): string
    {
        return "<style>
        .card {
            background: {$color_palette['background']} !important;
            border: 1px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
            box-shadow: {$color_palette['shadow']} !important;
            position: relative !important;
            overflow: hidden !important;
            transition: transform 0.3s ease, box-shadow 0.3s ease !important;
        }
        
        .card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        }
        
        .card-body {
            padding: 20px !important;
            position: relative !important;
            z-index: 2 !important;
        }
        
        .card-icon {
            font-size: 24px !important;
            display: block !important;
            margin-bottom: 12px !important;
        }
        
        .card-title {
            color: {$color_palette['text']} !important;
            margin: 0 0 8px 0 !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            line-height: 1.3 !important;
        }
        
        .card-desc {
            color: {$color_palette['text_secondary']} !important;
            margin: 0 0 8px 0 !important;
            font-size: 14px !important;
            line-height: 1.4 !important;
        }
        
        .card-date {
            color: {$color_palette['text_secondary']} !important;
            font-size: 12px !important;
            margin-top: 8px !important;
        }
        
        .card-overlay {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            background: rgba(0,0,0,0.8) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            opacity: 0 !important;
            transition: opacity 0.3s ease !important;
            z-index: 3 !important;
        }
        
        .card:hover .card-overlay {
            opacity: 1 !important;
        }
        
        .overlay-content {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        .card-overlay .authdocs-request-access-btn,
        .card-overlay .authdocs-download-btn {
            background: transparent !important;
            border: 2px solid {$color_palette['secondary']} !important;
            color: {$color_palette['secondary']} !important;
            padding: 12px 16px !important;
            border-radius: {$color_palette['border_radius']} !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.3s ease !important;
            text-decoration: none !important;
        }
        
        .card-overlay .authdocs-request-access-btn:hover,
        .card-overlay .authdocs-download-btn:hover {
            background: {$color_palette['secondary']} !important;
            color: {$color_palette['primary']} !important;
            transform: scale(1.05) !important;
        }
        </style>";
    }
    
    /**
     * Generate pagination HTML with color palette
     */
    private function generate_pagination_html(int $page, int $total_pages, int $limit, int $total_documents): string
    {
        $settings = new Settings();
        $color_palette = $settings->get_color_palette_data();
        $pagination_style = $settings->get_pagination_style();
        
        if ($total_pages <= 1) {
            return '';
        }
        
        ob_start();
        ?>
        <style>
        .authdocs-pagination {
            background: <?php echo $color_palette['background_secondary']; ?> !important;
            border-radius: <?php echo $color_palette['border_radius']; ?> !important;
        }
        
        .authdocs-pagination-info {
            color: <?php echo $color_palette['text_secondary']; ?> !important;
        }
        
        .authdocs-pagination-btn {
            background: <?php echo $color_palette['background']; ?> !important;
            color: <?php echo $color_palette['text']; ?> !important;
            border: 1px solid <?php echo $color_palette['border']; ?> !important;
            border-radius: <?php echo $color_palette['border_radius']; ?> !important;
        }
        
        .authdocs-pagination-btn:hover {
            background: <?php echo $color_palette['background_secondary']; ?> !important;
            color: <?php echo $color_palette['primary']; ?> !important;
        }
        
        .authdocs-pagination-btn.active {
            background: <?php echo $color_palette['primary']; ?> !important;
            color: <?php echo $color_palette['secondary']; ?> !important;
        }
        </style>
        
        <div class="authdocs-pagination <?php echo $pagination_style === 'load_more' ? 'authdocs-load-more-pagination' : 'authdocs-classic-pagination'; ?>">
            <div class="authdocs-pagination-info">
                <?php 
                $start = (($page - 1) * $limit) + 1;
                $end = min($page * $limit, $total_documents);
                printf(__('Showing %d-%d of %d documents', 'authdocs'), $start, $end, $total_documents);
                ?>
            </div>
            
            <?php if ($pagination_style === 'load_more'): ?>
                <?php if ($page < $total_pages): ?>
                    <button type="button" class="authdocs-load-more-btn" data-current-limit="<?php echo esc_attr($limit); ?>" data-restriction="all">
                        <?php _e('Load More Documents', 'authdocs'); ?>
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <div class="authdocs-pagination-links">
                    <?php if ($page > 1): ?>
                        <button type="button" class="authdocs-pagination-btn authdocs-pagination-prev" data-page="<?php echo esc_attr($page - 1); ?>">
                            <?php _e('Previous', 'authdocs'); ?>
                        </button>
                    <?php endif; ?>
                    
                    <div class="authdocs-pagination-numbers">
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <button type="button" class="authdocs-pagination-btn authdocs-pagination-number" data-page="1">1</button>
                            <?php if ($start_page > 2): ?>
                                <span class="authdocs-pagination-ellipsis">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <button type="button" class="authdocs-pagination-btn authdocs-pagination-number <?php echo $i === $page ? 'active' : ''; ?>" data-page="<?php echo esc_attr($i); ?>">
                                <?php echo $i; ?>
                            </button>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="authdocs-pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <button type="button" class="authdocs-pagination-btn authdocs-pagination-number" data-page="<?php echo esc_attr($total_pages); ?>"><?php echo $total_pages; ?></button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($page < $total_pages): ?>
                        <button type="button" class="authdocs-pagination-btn authdocs-pagination-next" data-page="<?php echo esc_attr($page + 1); ?>">
                            <?php _e('Next', 'authdocs'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle AJAX request to load more documents for grid view
     */
    public function handle_load_more_documents(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'authdocs_frontend_nonce')) {
            wp_send_json_error([
                'message' => __('Security check failed', 'authdocs')
            ]);
        }

        $current_limit = intval($_POST['limit'] ?? 12);
        $restriction = sanitize_text_field($_POST['restriction'] ?? 'all');
        $load_more_count = intval($_POST['load_more_limit'] ?? 12); // Number of additional documents to load
        
        // Validate inputs
        if ($current_limit < 1) $current_limit = 12;
        if (!in_array($restriction, ['all', 'restricted', 'unrestricted'])) $restriction = 'all';
        if ($load_more_count < 1) $load_more_count = 12;
        
        // Get total documents count for this restriction
        $total_documents = Database::get_published_documents_count($restriction);
        
        // Calculate new limit
        $new_limit = $current_limit + $load_more_count;
        
        // Get documents with the new limit
        $documents = Database::get_published_documents($new_limit, $restriction);
        
        if (empty($documents)) {
            wp_send_json_error([
                'message' => __('No documents found.', 'authdocs')
            ]);
        }
        
        // Get only the additional documents (skip the ones already shown)
        $additional_documents = array_slice($documents, $current_limit);
        
        if (empty($additional_documents)) {
            wp_send_json_error([
                'message' => __('No more documents to load.', 'authdocs')
            ]);
        }
        
        // Generate HTML for the additional grid items with color palette
        $html = $this->generate_grid_items_html($additional_documents);
        
        // Check if there are more documents available
        $has_more = $new_limit < $total_documents;
        
        wp_send_json_success([
            'html' => $html,
            'has_more' => $has_more,
            'total' => $total_documents,
            'current_limit' => $new_limit
        ]);
    }
    
    /**
     * Handle AJAX request to paginate documents for grid view
     */
    public function handle_paginate_documents(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'authdocs_frontend_nonce')) {
            wp_send_json_error([
                'message' => __('Security check failed', 'authdocs')
            ]);
        }

        $page = intval($_POST['page'] ?? 1);
        $limit = intval($_POST['limit'] ?? 12);
        $restriction = sanitize_text_field($_POST['restriction'] ?? 'all');
        $orderby = sanitize_text_field($_POST['orderby'] ?? 'date');
        $order = sanitize_text_field($_POST['order'] ?? 'DESC');
        
        // Validate inputs
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 12;
        if (!in_array($restriction, ['all', 'restricted', 'unrestricted'])) $restriction = 'all';
        if (!in_array($orderby, ['date', 'title'])) $orderby = 'date';
        if (!in_array($order, ['ASC', 'DESC'])) $order = 'DESC';
        
        $documents = Database::get_published_documents($limit, $restriction, $page, $orderby, $order);
        $total_documents = Database::get_published_documents_count($restriction);
        $total_pages = (int) ceil($total_documents / $limit);
        
        if (empty($documents)) {
            wp_send_json_error([
                'message' => __('No documents found.', 'authdocs')
            ]);
        }
        
        // Generate HTML for the grid items with color palette
        $html = $this->generate_grid_items_html($documents);
        
        // Generate pagination HTML with color palette
        $pagination_html = $this->generate_pagination_html($page, $total_pages, $limit, $total_documents);
        
        wp_send_json_success([
            'html' => $html,
            'pagination_html' => $pagination_html,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_documents' => $total_documents
        ]);
    }
}
