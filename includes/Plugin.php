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
        add_action('wp_ajax_authdocs_debug_hash', [$this, 'debug_hash_generation']);
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
            __('Debug Info', 'authdocs'),
            __('Debug', 'authdocs'),
            'manage_options',
            'authdocs-debug',
            [$this, 'debug_page']
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
        if (!$screen || !in_array($screen->id, ['document_page_authdocs-requests', 'document_page_authdocs-debug', 'document_page_authdocs-settings'])) {
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

        if (!$request_id || !in_array($action, ['accept', 'decline', 'inactive'])) {
            wp_send_json_error(__('Invalid request', 'authdocs'));
        }

        // Get current request to determine toggle behavior
        $current_request = Database::get_request_by_id($request_id);
        if (!$current_request) {
            wp_send_json_error(__('Request not found', 'authdocs'));
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
                    __('Request deactivated successfully.', 'authdocs') : 
                    __('Request activated successfully.', 'authdocs');
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
        $per_page = 20;
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

    public function debug_hash_generation(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'authdocs'));
        }

        $request_id = intval($_GET['request_id'] ?? 0);
        
        if (!$request_id) {
            wp_send_json_error(__('Request ID required', 'authdocs'));
        }

        $result = Database::test_hash_generation($request_id);
        
        wp_send_json_success($result);
    }

    public function debug_page(): void
    {
        $table_info = Database::check_table_structure();
        $requests = Database::get_all_requests();
        
        ?>
        <div class="wrap">
            <h1><?php _e('AuthDocs Debug Information', 'authdocs'); ?></h1>
            
            <h2><?php _e('Database Table Information', 'authdocs'); ?></h2>
            <pre><?php print_r($table_info); ?></pre>
            
            <h2><?php _e('Recent Requests', 'authdocs'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Document ID</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Hash</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo esc_html($request->id); ?></td>
                            <td><?php echo esc_html($request->document_id); ?></td>
                            <td><?php echo esc_html($request->requester_email); ?></td>
                            <td><?php echo esc_html($request->status); ?></td>
                            <td><?php echo esc_html($request->secure_hash ? substr($request->secure_hash, 0, 20) . '...' : 'NULL'); ?></td>
                            <td><?php echo esc_html($request->created_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2><?php _e('Test Hash Generation', 'authdocs'); ?></h2>
            <p><?php _e('Enter a request ID to test hash generation:', 'authdocs'); ?></p>
            <input type="number" id="test-request-id" placeholder="Request ID" />
            <button type="button" id="test-hash-btn"><?php _e('Test Hash Generation', 'authdocs'); ?></button>
            <div id="test-results"></div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#test-hash-btn').on('click', function() {
                    var requestId = $('#test-request-id').val();
                    if (!requestId) {
                        alert('Please enter a request ID');
                        return;
                    }
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'GET',
                        data: {
                            action: 'authdocs_debug_hash',
                            request_id: requestId
                        },
                        success: function(response) {
                            $('#test-results').html('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
                        },
                        error: function() {
                            $('#test-results').html('<p>Error testing hash generation</p>');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
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
        
        // Prepare response data
        $response_data = [
            'id' => $request->id,
            'document_id' => $request->document_id,
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
     * Handle AJAX request to load more documents for grid view
     */
    public function handle_load_more_documents(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'authdocs_frontend_nonce')) {
            wp_send_json_error([
                'message' => __('Security check failed', 'authdocs')
            ]);
        }

        $limit = intval($_POST['limit'] ?? 12);
        $restriction = sanitize_text_field($_POST['restriction'] ?? 'all');
        
        // Validate inputs
        if ($limit < 1) $limit = 12;
        if (!in_array($restriction, ['all', 'restricted', 'unrestricted'])) $restriction = 'all';
        
        $documents = Database::get_published_documents($limit, $restriction);
        
        if (empty($documents)) {
            wp_send_json_error([
                'message' => __('No documents found.', 'authdocs')
            ]);
        }
        
        // Generate HTML for the grid items
        ob_start();
        foreach ($documents as $document): ?>
            <div class="authdocs-grid-item">
                <div class="authdocs-grid-item-content">
                    <div class="authdocs-grid-item-header">
                        <h3 class="authdocs-grid-item-title">
                            <?php echo esc_html($document['title']); ?>
                        </h3>
                        <div class="authdocs-grid-item-date">
                            <?php echo esc_html($document['date']); ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($document['description'])): ?>
                        <div class="authdocs-grid-item-description">
                            <?php echo wp_kses_post(wp_trim_words($document['description'], 20)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="authdocs-grid-item-actions">
                        <?php if ($document['restricted']): ?>
                            <button type="button" class="authdocs-request-access-btn" data-document-id="<?php echo esc_attr($document['id']); ?>">
                                <?php _e('Request Access', 'authdocs'); ?>
                            </button>
                        <?php else: ?>
                            <a href="<?php echo esc_url($document['file_data']['url']); ?>" class="authdocs-download-btn" download>
                                <?php _e('Download', 'authdocs'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach;
        $html = ob_get_clean();
        
        // Check if there are more documents available
        $total_documents = Database::get_total_requests_count();
        $has_more = $total_documents > $limit;
        
        wp_send_json_success([
            'html' => $html,
            'has_more' => $has_more,
            'total' => $total_documents,
            'current_limit' => $limit
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
        $total_pages = ceil($total_documents / $limit);
        
        if (empty($documents)) {
            wp_send_json_error([
                'message' => __('No documents found.', 'authdocs')
            ]);
        }
        
        // Generate HTML for the grid items
        ob_start();
        foreach ($documents as $document): ?>
            <div class="authdocs-grid-item">
                <div class="authdocs-grid-item-content">
                    <div class="authdocs-grid-item-header">
                        <h3 class="authdocs-grid-item-title">
                            <?php echo esc_html($document['title']); ?>
                        </h3>
                        <div class="authdocs-grid-item-date">
                            <?php echo esc_html($document['date']); ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($document['description'])): ?>
                        <div class="authdocs-grid-item-description">
                            <?php echo wp_kses_post(wp_trim_words($document['description'], 20)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="authdocs-grid-item-actions">
                        <?php if ($document['restricted']): ?>
                            <button type="button" class="authdocs-request-access-btn" data-document-id="<?php echo esc_attr($document['id']); ?>">
                                <?php _e('Request Access', 'authdocs'); ?>
                            </button>
                        <?php else: ?>
                            <a href="<?php echo esc_url($document['file_data']['url']); ?>" class="authdocs-download-btn" download>
                                <?php _e('Download', 'authdocs'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach;
        $html = ob_get_clean();
        
        // Generate pagination HTML
        ob_start();
        if ($total_pages > 1): ?>
            <div class="authdocs-pagination">
                <div class="authdocs-pagination-info">
                    <?php 
                    $start = (($page - 1) * $limit) + 1;
                    $end = min($page * $limit, $total_documents);
                    printf(__('Showing %d-%d of %d documents', 'authdocs'), $start, $end, $total_documents);
                    ?>
                </div>
                
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
            </div>
        <?php endif;
        $pagination_html = ob_get_clean();
        
        wp_send_json_success([
            'html' => $html,
            'pagination_html' => $pagination_html,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_documents' => $total_documents
        ]);
    }
}
