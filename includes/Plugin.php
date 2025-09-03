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
        
        // Email hooks
        add_action('authdocs/request_submitted', [$this, 'handle_request_submitted_hook']);
        add_action('authdocs/request_status_changed', [$this, 'handle_request_status_changed_hook']);
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

        wp_localize_script('authdocs-frontend', 'authdocs_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('authdocs_nonce'),
            'strings' => [
                'request_sent' => __('Request sent successfully!', 'authdocs'),
                'error' => __('An error occurred. Please try again.', 'authdocs'),
            ]
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
        ]);
    }

    public function handle_access_request(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'authdocs_nonce')) {
            wp_die(__('Security check failed', 'authdocs'));
        }

        $document_id = intval($_POST['document_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');

        if (!$document_id || !$name || !$email) {
            wp_send_json_error(__('Missing required fields', 'authdocs'));
        }

        $result = Database::save_access_request($document_id, $name, $email);
        
        if ($result) {
            // Fire the request submitted hook
            do_action('authdocs/request_submitted', $result);
            
            wp_send_json_success(__('Request submitted successfully', 'authdocs'));
        } else {
            wp_send_json_error(__('Failed to submit request', 'authdocs'));
        }
    }

    public function handle_request_management(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'authdocs'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'authdocs_manage_nonce')) {
            wp_die(__('Security check failed', 'authdocs'));
        }

        $request_id = intval($_POST['request_id'] ?? 0);
        $action = sanitize_text_field($_POST['action_type'] ?? '');

        if (!$request_id || !in_array($action, ['accept', 'decline', 'inactive'])) {
            wp_send_json_error(__('Invalid request', 'authdocs'));
        }

        // Map action to database status
        $status_map = [
            'accept' => 'accepted',
            'decline' => 'declined',
            'inactive' => 'inactive'
        ];
        
        $status = $status_map[$action] ?? $action;
        
        error_log("AuthDocs: Updating request ID {$request_id} from action '{$action}' to status '{$status}'");
        
        $old_status = Database::get_request_status($request_id);
        $result = Database::update_request_status($request_id, $status);
        
        if ($result) {
            // Fire the status change hook
            do_action('authdocs/request_status_changed', $request_id, $old_status, $status);
            
            wp_send_json_success(__('Request updated successfully', 'authdocs'));
        } else {
            wp_send_json_error(__('Failed to update request', 'authdocs'));
        }
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
    
    /**
     * Handle request submitted hook
     */
    public function handle_request_submitted(int $request_id): void
    {
        $email = new Email();
        $email->send_autoresponder_email($request_id);
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
        $email->send_autoresponder_email($request_id);
    }
    
    /**
     * Handle request status changed hook
     */
    public function handle_request_status_changed(int $request_id, string $old_status, string $new_status): void
    {
        // Only send access granted email when status changes to 'accepted'
        if ($new_status === 'accepted') {
            $email = new Email();
            $email->send_access_granted_email($request_id);
        }
    }
    
    /**
     * Handle request status changed hook (WordPress hook compatibility)
     */
    public function handle_request_status_changed_hook($request_id, $old_status = '', $new_status = ''): void
    {
        // Ensure we have the required parameters
        if (!is_numeric($request_id)) {
            return;
        }
        
        $request_id = (int) $request_id;
        $old_status = (string) $old_status;
        $new_status = (string) $new_status;
        
        // Only send access granted email when status changes to 'accepted'
        if ($new_status === 'accepted') {
            $email = new Email();
            $email->send_access_granted_email($request_id);
        }
    }
}
