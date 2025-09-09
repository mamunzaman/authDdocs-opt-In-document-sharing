<?php
/**
 * Main plugin class for ProtectedDocs
 * 
 * @since 1.1.0 Email logic separation; new autoresponder recipient; trigger fixes.
 */
declare(strict_types=1);

namespace ProtectedDocs;

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
        add_action('wp_ajax_protecteddocs_request_access', [$this, 'handle_access_request']);
        add_action('wp_ajax_nopriv_protecteddocs_request_access', [$this, 'handle_access_request']);
        add_action('wp_ajax_protecteddocs_manage_request', [$this, 'handle_request_management']);
        add_action('wp_ajax_protecteddocs_test_email', [$this, 'handle_test_email']);
        add_action('wp_ajax_protecteddocs_test_autoresponder', [$this, 'handle_test_autoresponder']);
        add_action('wp_ajax_protecteddocs_test_access_request', [$this, 'handle_test_access_request']);
        add_action('wp_ajax_protecteddocs_test_auto_response', [$this, 'handle_test_auto_response']);
        add_action('wp_ajax_protecteddocs_test_grant_decline', [$this, 'handle_test_grant_decline']);
        add_action('wp_ajax_protecteddocs_get_request_data', [$this, 'handle_get_request_data']);
        add_action('wp_ajax_protecteddocs_load_more_documents', [$this, 'handle_load_more_documents']);
        add_action('wp_ajax_nopriv_protecteddocs_load_more_documents', [$this, 'handle_load_more_documents']);
        add_action('wp_ajax_protecteddocs_paginate_documents', [$this, 'handle_paginate_documents']);
        add_action('wp_ajax_nopriv_protecteddocs_paginate_documents', [$this, 'handle_paginate_documents']);
        add_action('wp_ajax_protecteddocs_render_shortcode', [$this, 'handle_render_shortcode']);
        add_action('wp_ajax_nopriv_protecteddocs_render_shortcode', [$this, 'handle_render_shortcode']);
        add_action('wp_ajax_protecteddocs_validate_session', [$this, 'handle_validate_session']);
        add_action('wp_ajax_nopriv_protecteddocs_validate_session', [$this, 'handle_validate_session']);
        
        // Email hooks
        add_action('authdocs/request_submitted', [$this, 'handle_request_submitted_hook']);
        add_action('template_redirect', [$this, 'protect_document_files']);
        add_action('init', [$this, 'protect_media_files']);
        add_action('robots_txt', [$this, 'add_robots_txt_rules']);
        add_action('template_redirect', [$this, 'handle_access_request_page']);
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
        new GutenbergBlock();
    }

    public function init(): void
    {
        load_plugin_textdomain('protecteddocs', false, dirname(plugin_basename(PROTECTEDDOCS_PLUGIN_FILE)) . '/languages');
    }

    public function add_admin_menu(): void
    {
        // Documents (Main menu - handled by custom post type)
        // → All Documents (default post type list)
        // → Add New Document (default post type add)
        
        // Access Requests
        add_submenu_page(
            'edit.php?post_type=document',
            __('Access Requests', 'protecteddocs'),
            __('Access Requests', 'protecteddocs'),
            'manage_options',
            'protecteddocs-requests',
            [$this, 'requests_page']
        );
        
        // Email Templates
        add_submenu_page(
            'edit.php?post_type=document',
            __('Email Templates', 'protecteddocs'),
            __('Email Templates', 'protecteddocs'),
            'manage_options',
            'protecteddocs-email-templates',
            [$this, 'email_templates_page']
        );
        
        // Frontend Settings
        add_submenu_page(
            'edit.php?post_type=document',
            __('Frontend Settings', 'protecteddocs'),
            __('Frontend Settings', 'protecteddocs'),
            'manage_options',
            'protecteddocs-frontend-settings',
            [$this, 'frontend_settings_page']
        );
        
        // About ProtectedDocs
        add_submenu_page(
            'edit.php?post_type=document',
            __('About ProtectedDocs', 'protecteddocs'),
            __('About ProtectedDocs', 'protecteddocs'),
            'manage_options',
            'protecteddocs-about',
            [$this, 'about_plugin_page']
        );
        
        // Debug CSS Loading (temporary)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_submenu_page(
                'edit.php?post_type=document',
                __('Debug CSS', 'protecteddocs'),
                __('Debug CSS', 'protecteddocs'),
                'manage_options',
                'protecteddocs-debug-css',
                [$this, 'debug_admin_css_loading']
            );
        }
    }

    public function enqueue_frontend_assets(): void
    {
        // Don't load frontend assets in admin area, even for logged-in users
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            'protecteddocs-frontend-css',
            PROTECTEDDOCS_PLUGIN_URL . 'assets/css/protecteddocs-frontend.css',
            [],
            PROTECTEDDOCS_VERSION
        );

        wp_enqueue_script(
            'protecteddocs-frontend-js',
            PROTECTEDDOCS_PLUGIN_URL . 'assets/js/protecteddocs-frontend.js',
            ['jquery'],
            PROTECTEDDOCS_VERSION,
            true
        );

        wp_localize_script('protecteddocs-frontend-js', 'protecteddocs_frontend', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('protecteddocs_frontend_nonce'),
            'request_access_title' => __('Request Document Access', 'protecteddocs'),
            'name_label' => __('Full Name', 'protecteddocs'),
            'email_label' => __('Email Address', 'protecteddocs'),
            'cancel_label' => __('Cancel', 'protecteddocs'),
            'submit_label' => __('Submit Request', 'protecteddocs'),
            'submitting_label' => __('Submitting...', 'protecteddocs'),
            'loading_label' => __('Loading...', 'protecteddocs'),
            'load_more_label' => __('Load More Documents', 'protecteddocs')
        ]);
    }

    public function enqueue_admin_assets(): void
    {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }
        
        // Debug: Log screen ID for troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ProtectedDocs: Current screen ID: ' . $screen->id);
        }

        // Load admin-document.css and media scripts on individual document post type pages
        if ($screen->id === 'document') {
            wp_enqueue_style(
                'protecteddocs-admin-document',
                PROTECTEDDOCS_PLUGIN_URL . 'assets/css/admin-document.css',
                [],
                PROTECTEDDOCS_VERSION
            );
            
            // Enqueue WordPress media scripts for file uploader
            wp_enqueue_media();
            
            // Enqueue our custom media uploader script
            wp_enqueue_script(
                'protecteddocs-media-uploader',
                PROTECTEDDOCS_PLUGIN_URL . 'assets/js/media-uploader.js',
                ['jquery', 'media-upload', 'media-views'],
                PROTECTEDDOCS_VERSION,
                true
            );
            
            // Localize script with translated strings
            wp_localize_script('protecteddocs-media-uploader', 'protecteddocs_media_uploader', [
                'select_document' => __('Select Document', 'protecteddocs'),
                'use_document' => __('Use this document', 'protecteddocs'),
                'view' => __('View', 'protecteddocs'),
                'remove' => __('Remove', 'protecteddocs'),
                'no_file_selected' => __('No file selected', 'protecteddocs'),
                'click_to_select' => __('Click the button below to select a document', 'protecteddocs'),
            ]);
            
            return;
        }

        // Load main protecteddocs-admin.css on plugin admin pages only
        $allowed_screens = [
            'document_page_protecteddocs-requests', 
            'document_page_protecteddocs-email-templates',
            'document_page_protecteddocs-frontend-settings',
            'document_page_protecteddocs-about',
            'edit-document' // Document post type list page
        ];
        
        // Add debug page if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $allowed_screens[] = 'document_page_protecteddocs-debug-css';
        }
        
        if (!in_array($screen->id, $allowed_screens)) {
            return;
        }

        // Enqueue dashicons for action link icons
        wp_enqueue_style('dashicons');

        wp_enqueue_style(
            'protecteddocs-admin-css',
            PROTECTEDDOCS_PLUGIN_URL . 'assets/css/protecteddocs-admin.css',
            [],
            PROTECTEDDOCS_VERSION
        );
        
        // Debug: Log when admin CSS is enqueued
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ProtectedDocs: Admin CSS enqueued for screen: ' . $screen->id);
            error_log('ProtectedDocs: CSS URL: ' . PROTECTEDDOCS_PLUGIN_URL . 'assets/css/protecteddocs-admin.css');
        }

        wp_enqueue_script(
            'protecteddocs-admin-js',
            PROTECTEDDOCS_PLUGIN_URL . 'assets/js/protecteddocs-admin.js',
            ['jquery'],
            PROTECTEDDOCS_VERSION,
            true
        );

        wp_localize_script('protecteddocs-admin-js', 'protecteddocs_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('protecteddocs_manage_nonce'),
            'site_url' => home_url('/'),
        ]);
    }

    public function handle_access_request(): void
    {
        error_log('ProtectedDocs: handle_access_request() method called');
        try {
            // Debug: Log received data
            error_log('ProtectedDocs: Received POST data: ' . print_r($_POST, true));
            error_log('ProtectedDocs: Received REQUEST data: ' . print_r($_REQUEST, true));
            error_log('ProtectedDocs: Raw input: ' . file_get_contents('php://input'));

            // Check if nonce exists
            $nonce = $_POST['nonce'] ?? '';
            error_log("ProtectedDocs: Nonce received: '{$nonce}'");
            
            if (!wp_verify_nonce($nonce, 'protecteddocs_frontend_nonce')) {
                error_log('ProtectedDocs: Nonce verification failed');
                wp_send_json_error([
                    'message' => __('Security check failed', 'protecteddocs'),
                    'debug' => [
                        'nonce_received' => $nonce,
                        'nonce_valid' => false
                    ]
                ]);
            }

            $document_id = intval($_POST['document_id'] ?? 0);
            $name = sanitize_text_field($_POST['name'] ?? '');
            $email = sanitize_email($_POST['email'] ?? '');

            error_log("ProtectedDocs: Parsed values - document_id: {$document_id}, name: '{$name}', email: '{$email}'");
            error_log("ProtectedDocs: Field validation - document_id empty: " . ($document_id ? 'false' : 'true'));
            error_log("ProtectedDocs: Field validation - name empty: " . (empty($name) ? 'true' : 'false'));
            error_log("ProtectedDocs: Field validation - email empty: " . (empty($email) ? 'true' : 'false'));

            if (!$document_id || !$name || !$email) {
                error_log('ProtectedDocs: Missing required fields detected');
                wp_send_json_error([
                    'message' => __('Missing required fields', 'protecteddocs'),
                    'debug' => [
                        'document_id' => $document_id,
                        'name' => $name,
                        'email' => $email,
                        'document_id_empty' => !$document_id,
                        'name_empty' => !$name,
                        'email_empty' => !$email,
                        'raw_post' => $_POST
                    ]
                ]);
            }

            // Bot protection temporarily disabled for debugging

            // Check for existing requests first (only for the same document)
            $existing_request = Database::check_existing_request($document_id, $email);
            
            if ($existing_request['exists'] && !$existing_request['is_deleted']) {
                // Request already exists for this specific document and is not deleted
                wp_send_json_error([
                    'message' => __('You have already submitted a request for this document. Please wait for a response or contact the administrator.', 'protecteddocs'),
                    'duplicate' => true
                ]);
            }

            // Submit the request (save_access_request will handle its own duplicate checking)
            $result = Database::save_access_request($document_id, $name, $email);
            
            if ($result) {
                // Fire the request submitted hook
                do_action('authdocs/request_submitted', $result);
                
                // Check if document is active for appropriate message
                $document = get_post($document_id);
                $is_document_active = $document && $document->post_status === 'publish';
                
                if (!$is_document_active) {
                    wp_send_json_success([
                        'message' => __('Request submitted successfully. Note: This document is currently inactive.', 'protecteddocs'),
                        'request_id' => $result,
                        'document_inactive' => true
                    ]);
                } else {
                    wp_send_json_success([
                        'message' => __('Request submitted successfully', 'protecteddocs'),
                        'request_id' => $result
                    ]);
                }
            } else {
                wp_send_json_error([
                    'message' => __('Failed to submit request. You may have already submitted a request for this document.', 'protecteddocs')
                ]);
            }
        } catch (\Exception $e) {
            error_log('ProtectedDocs: Error in handle_access_request: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An unexpected error occurred', 'protecteddocs')
            ]);
        }
    }

    public function handle_request_management(): void
    {
        error_log("ProtectedDocs: handle_request_management called");
        error_log("ProtectedDocs: POST data: " . json_encode($_POST));
        
        if (!current_user_can('manage_options')) {
            error_log("ProtectedDocs: Insufficient permissions");
            wp_die(__('Insufficient permissions', 'protecteddocs'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'protecteddocs_manage_nonce')) {
            error_log("ProtectedDocs: Security check failed");
            wp_die(__('Security check failed', 'protecteddocs'));
        }

        $request_id = intval($_POST['request_id'] ?? 0);
        $action = sanitize_text_field($_POST['action_type'] ?? '');

        if (!$request_id || !in_array($action, ['accept', 'decline', 'inactive', 'delete'])) {
            wp_send_json_error(__('Invalid request', 'protecteddocs'));
        }

        // Get current request to determine toggle behavior
        $current_request = Database::get_request_by_id($request_id);
        if (!$current_request) {
            wp_send_json_error(__('Request not found', 'protecteddocs'));
        }

        // Handle delete action separately
        if ($action === 'delete') {
            $result = Database::delete_request($request_id);
            if ($result) {
                wp_send_json_success([
                    'message' => __('Request deleted successfully.', 'protecteddocs'),
                    'deleted' => true
                ]);
            } else {
                wp_send_json_error(__('Failed to delete request', 'protecteddocs'));
            }
        }

        // Map action to database status with toggle logic for inactive
        $status_map = [
            'accept' => 'accepted',
            'decline' => 'declined',
            'inactive' => $current_request->status === 'inactive' ? 'restore' : 'inactive' // 'restore' will trigger status restoration in Database::update_request_status
        ];
        
        $status = $status_map[$action] ?? $action;
        
        error_log("ProtectedDocs: Updating request ID {$request_id} from action '{$action}' to status '{$status}'");
        
        $old_status = Database::get_request_status($request_id);
        $result = Database::update_request_status($request_id, $status);
        
        if ($result) {
            // Get the actual final status after update (in case it was restored from pending)
            $final_status = Database::get_request_status($request_id);
            error_log("ProtectedDocs: Request updated successfully. Old status: {$old_status}, Final status: {$final_status}");
            
            // Fire the status change hook with the final status and capture email result
            error_log("ProtectedDocs: Firing status change hook with final status: {$final_status}");
            $email_sent = $this->handle_status_change_with_email($request_id, $old_status, $final_status);
            
            // Prepare response message based on action and email result
            $message = $this->get_action_response_message($action, $final_status, $email_sent);
            
            wp_send_json_success([
                'message' => $message,
                'email_sent' => $email_sent,
                'status' => $final_status
            ]);
        } else {
            error_log("ProtectedDocs: Failed to update request status");
            wp_send_json_error(__('Failed to update request', 'protecteddocs'));
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
            error_log("ProtectedDocs: Status is 'accepted', sending grant email");
            $email_sent = $email->send_grant_decline_email($request_id, true);
        } elseif ($new_status === 'declined') {
            error_log("ProtectedDocs: Status is 'declined', sending decline email");
            $email_sent = $email->send_grant_decline_email($request_id, false);
        } else {
            error_log("ProtectedDocs: Status is not 'accepted' or 'declined' ({$new_status}), not sending email");
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
                    __('Request accepted successfully.', 'protecteddocs') : 
                    __('Request re-accepted successfully.', 'protecteddocs');
                break;
            case 'decline':
                $base_message = $status === 'declined' ? 
                    __('Request declined successfully.', 'protecteddocs') : 
                    __('Request re-declined successfully.', 'protecteddocs');
                break;
            case 'inactive':
                $base_message = $status === 'inactive' ? 
                    __('Document link hidden successfully.', 'protecteddocs') : 
                    __('Document link restored successfully.', 'protecteddocs');
                break;
            default:
                $base_message = __('Request updated successfully.', 'protecteddocs');
        }
        
        // Email message based on result
        if ($email_sent && in_array($status, ['accepted', 'declined'])) {
            $email_message = $status === 'accepted' ? 
                __(' Grant email sent to requester.', 'protecteddocs') : 
                __(' Decline email sent to requester.', 'protecteddocs');
        } elseif (!$email_sent && in_array($status, ['accepted', 'declined'])) {
            $email_message = __(' Warning: Email could not be sent.', 'protecteddocs');
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
        
        include PROTECTEDDOCS_PLUGIN_DIR . 'templates/admin/requests-page.php';
    }

    public function protect_document_files(): void
    {
        // Check if this is a direct media file access
        if (!isset($_GET['attachment_id'])) {
            return;
        }

        $attachment_id = intval($_GET['attachment_id']);
        
        // Check if this attachment is used by any ProtectedDocs document
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

        // If this file is used by a ProtectedDocs document, block direct access
        if (!empty($document_posts)) {
            wp_die(__('Access denied. This file requires authorization through the document sharing system.', 'protecteddocs'), __('Access Denied', 'protecteddocs'), ['response' => 403]);
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
                // Get all ProtectedDocs documents and check if this file is used
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
                            wp_die(__('Access denied. This file requires authorization through the document sharing system.', 'protecteddocs'), __('Access Denied', 'protecteddocs'), ['response' => 403]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Handle access request page
     */
    public function handle_access_request_page(): void
    {
        // Check if this is an access request page
        if (!isset($_GET['authdocs_access']) || !isset($_GET['document_id'])) {
            return;
        }

        $document_id = intval($_GET['document_id']);
        $hash = sanitize_text_field($_GET['hash'] ?? '');
        $email = sanitize_email($_GET['email'] ?? '');
        $request_id = intval($_GET['request_id'] ?? 0);

        // Get document information
        $document = get_post($document_id);
        if (!$document || $document->post_type !== 'document') {
            LinkHandler::render_error_page(
                __('Document not found', 'protecteddocs'),
                __('Access Denied', 'protecteddocs'),
                404
            );
            return;
        }

        // Check if document is restricted
        $is_restricted = get_post_meta($document_id, '_authdocs_restricted', true) === 'yes';
        
        if (!$is_restricted) {
            // Document is not restricted, redirect to download
            $download_url = $this->get_document_download_url($document_id);
            wp_redirect($download_url);
            exit;
        }

        // Check if user has valid access
        if (!empty($hash) && !empty($email)) {
            $has_access = Database::validate_secure_access($hash, $email, $document_id, $request_id > 0 ? $request_id : null);
            
            if ($has_access) {
                // User has access, redirect to download
                $download_url = $this->get_document_download_url($document_id);
                wp_redirect($download_url);
                exit;
            }
        }

        // User doesn't have access, show access request page
        $this->render_access_request_page($document_id, $document);
        exit;
    }

    /**
     * Get document download URL
     */
    private function get_document_download_url(int $document_id): string
    {
        $token = Tokens::generate_download_token($document_id);
        return add_query_arg([
            'authdocs_download' => $document_id,
            'token' => $token
        ], home_url('/'));
    }

    /**
     * Get access request URL for a document
     */
    private function get_access_request_url(int $document_id): string
    {
        return add_query_arg([
            'authdocs_access' => '1',
            'document_id' => $document_id
        ], home_url('/'));
    }

    /**
     * Render access request page
     */
    private function render_access_request_page(int $document_id, \WP_Post $document): void
    {
        $settings = new Settings();
        $color_palette = $settings->get_color_palette_colors();
        
        // Get document file information
        $file_data = Database::get_document_file($document_id);
        $file_name = $file_data['filename'] ?? __('Unknown file', 'protecteddocs');
        $file_size = $file_data['size'] ?? 0;
        $file_size_formatted = $file_size ? size_format($file_size) : '';

        http_response_code(200);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('Request Access', 'protecteddocs'); ?> - <?php echo esc_html($document->post_title); ?></title>
            
            <!-- SEO Protection -->
            <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
            <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
            <meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
            
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
                
                .request-container {
                    background: <?php echo esc_attr($color_palette['secondary']); ?>;
                    border: 2px solid <?php echo esc_attr($color_palette['border']); ?>;
                    border-radius: 16px;
                    padding: 48px 32px;
                    text-align: center;
                    max-width: 480px;
                    width: 100%;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
                }
                
                .document-icon {
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
                
                .document-title {
                    font-size: 24px;
                    font-weight: 600;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    margin-bottom: 12px;
                    letter-spacing: -0.3px;
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
                
                .request-form {
                    margin-top: 32px;
                }
                
                .form-group {
                    margin-bottom: 20px;
                    text-align: left;
                }
                
                .form-group label {
                    display: block;
                    font-size: 14px;
                    font-weight: 500;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    margin-bottom: 8px;
                }
                
                .form-group input {
                    width: 100%;
                    padding: 12px 16px;
                    border: 2px solid <?php echo esc_attr($color_palette['border']); ?>;
                    border-radius: 8px;
                    font-size: 14px;
                    background: <?php echo esc_attr($color_palette['background']); ?>;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    transition: border-color 0.2s ease;
                }
                
                .form-group input:focus {
                    outline: none;
                    border-color: <?php echo esc_attr($color_palette['primary']); ?>;
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
                    width: 100%;
                    justify-content: center;
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
                
                .btn-primary:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                    transform: none;
                }
                
                .back-link {
                    margin-top: 24px;
                }
                
                .back-link a {
                    color: <?php echo esc_attr($color_palette['primary']); ?>;
                    text-decoration: none;
                    font-size: 14px;
                }
                
                .back-link a:hover {
                    text-decoration: underline;
                }
                
                @media (max-width: 480px) {
                    .request-container {
                        padding: 32px 20px;
                        margin: 10px;
                    }
                    
                    .document-title {
                        font-size: 20px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="request-container">
                <div class="document-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" fill="currentColor"/>
                        <path d="M14 2v6h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                
                <h1 class="document-title"><?php echo esc_html($document->post_title); ?></h1>
                
                <div class="document-info">
                    <h3><?php _e('Document Information', 'protecteddocs'); ?></h3>
                    <p><strong><?php _e('File:', 'protecteddocs'); ?></strong> <?php echo esc_html($file_name); ?></p>
                    <?php if ($file_size_formatted): ?>
                        <p><strong><?php _e('Size:', 'protecteddocs'); ?></strong> <?php echo esc_html($file_size_formatted); ?></p>
                    <?php endif; ?>
                    <p><strong><?php _e('Status:', 'protecteddocs'); ?></strong> <?php _e('Restricted Access', 'protecteddocs'); ?></p>
                </div>
                
                <div class="request-form">
                    <form id="authdocs-request-form" method="post">
                        <div class="form-group">
                            <label for="authdocs-name"><?php _e('Your Name', 'protecteddocs'); ?> *</label>
                            <input type="text" id="authdocs-name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="authdocs-email"><?php _e('Your Email', 'protecteddocs'); ?> *</label>
                            <input type="email" id="authdocs-email" name="email" required>
                        </div>
                        
                        <input type="hidden" name="document_id" value="<?php echo esc_attr($document_id); ?>">
                        <input type="hidden" name="action" value="protecteddocs_request_access">
                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('protecteddocs_frontend_nonce'); ?>">
                        
                        <button type="submit" class="btn btn-primary" id="authdocs-submit-request">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z" fill="currentColor"/>
                            </svg>
                            <?php _e('Request Access', 'protecteddocs'); ?>
                        </button>
                    </form>
                </div>
                
                <div class="back-link">
                    <a href="<?php echo esc_url(home_url('/')); ?>">← <?php _e('Back to Home', 'protecteddocs'); ?></a>
                </div>
            </div>
            
            <script>
                // Add form submission handling
                document.getElementById('authdocs-request-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const submitBtn = document.getElementById('authdocs-submit-request');
                    
                    // Disable button and show loading
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span style="display: inline-block; width: 16px; height: 16px; border: 2px solid transparent; border-top: 2px solid currentColor; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 8px;"></span><?php _e('Submitting...', 'protecteddocs'); ?>';
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.data.message || '<?php _e('Access request submitted successfully!', 'protecteddocs'); ?>');
                            window.location.href = '<?php echo esc_url(home_url('/')); ?>';
                        } else {
                            alert(data.data.message || '<?php _e('Failed to submit request. Please try again.', 'protecteddocs'); ?>');
                        }
                    })
                    .catch(error => {
                        alert('<?php _e('An error occurred. Please try again.', 'protecteddocs'); ?>');
                    })
                    .finally(() => {
                        // Re-enable button
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z" fill="currentColor"/></svg><?php _e('Request Access', 'protecteddocs'); ?>';
                    });
                });
                
                // Add spin animation
                const style = document.createElement('style');
                style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
                document.head.appendChild(style);
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Add robots.txt rules to prevent crawling of access denied pages
     */
    public function add_robots_txt_rules(string $output): string
    {
        $output .= "\n# ProtectedDocs - Block access denied pages\n";
        $output .= "Disallow: /*?authdocs_access=*\n";
        $output .= "Disallow: /*?hash=*\n";
        $output .= "Disallow: /*?document_id=*\n";
        
        return $output;
    }

    
    public function settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'protecteddocs'));
        }
        
        include PROTECTEDDOCS_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }
    
    public function email_templates_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'protecteddocs'));
        }
        
        include PROTECTEDDOCS_PLUGIN_DIR . 'templates/admin/email-templates-page.php';
    }
    
    public function frontend_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'protecteddocs'));
        }
        
        include PROTECTEDDOCS_PLUGIN_DIR . 'templates/admin/frontend-settings-page.php';
    }
    
    public function about_plugin_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'protecteddocs'));
        }
        
        include PROTECTEDDOCS_PLUGIN_DIR . 'templates/admin/about-plugin-page.php';
    }
    
    public function handle_test_email(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'protecteddocs'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'protecteddocs_manage_nonce')) {
            wp_send_json_error(__('Security check failed', 'protecteddocs'));
        }
        
        $email = new Email();
        $result = $email->send_test_email();
        
        if ($result) {
            wp_send_json_success(__('Test email sent successfully! Check your admin email.', 'protecteddocs'));
        } else {
            wp_send_json_error(__('Failed to send test email. Check your WordPress email configuration.', 'protecteddocs'));
        }
    }
    
    public function handle_test_autoresponder(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'protecteddocs'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'protecteddocs_manage_nonce')) {
            wp_send_json_error(__('Security check failed', 'protecteddocs'));
        }
        
        $email = new Email();
        $result = $email->send_test_autoresponder_email();
        
        if ($result) {
            wp_send_json_success(__('Test autoresponder email sent successfully! Check your admin email.', 'protecteddocs'));
        } else {
            wp_send_json_error(__('Failed to send test autoresponder email. Make sure autoresponder is enabled and check your WordPress email configuration.', 'protecteddocs'));
        }
    }
    
    public function handle_test_access_request(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'protecteddocs'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'protecteddocs_manage_nonce')) {
            wp_send_json_error(__('Security check failed', 'protecteddocs'));
        }
        
        $email = new Email();
        $result = $email->send_test_access_request_email();
        
        if ($result) {
            wp_send_json_success(__('Test access request email sent successfully! Check your admin email.', 'protecteddocs'));
        } else {
            wp_send_json_error(__('Failed to send test access request email. Check your WordPress email configuration.', 'protecteddocs'));
        }
    }
    
    public function handle_test_auto_response(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'protecteddocs'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'protecteddocs_manage_nonce')) {
            wp_send_json_error(__('Security check failed', 'protecteddocs'));
        }
        
        $email = new Email();
        $result = $email->send_test_auto_response_email();
        
        if ($result) {
            wp_send_json_success(__('Test auto-response email sent successfully! Check your admin email.', 'protecteddocs'));
        } else {
            wp_send_json_error(__('Failed to send test auto-response email. Make sure auto-response is enabled and check your WordPress email configuration.', 'protecteddocs'));
        }
    }
    
    public function handle_test_grant_decline(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'protecteddocs'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'protecteddocs_manage_nonce')) {
            wp_send_json_error(__('Security check failed', 'protecteddocs'));
        }
        
        $email = new Email();
        $result = $email->send_test_grant_decline_email(true); // Test with granted status
        
        if ($result) {
            wp_send_json_success(__('Test grant/decline email sent successfully! Check your admin email.', 'protecteddocs'));
        } else {
            wp_send_json_error(__('Failed to send test grant/decline email. Check your WordPress email configuration.', 'protecteddocs'));
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
            wp_die(__('Insufficient permissions', 'protecteddocs'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'protecteddocs_manage_nonce')) {
            wp_send_json_error(__('Security check failed', 'protecteddocs'));
        }

        $request_id = intval($_POST['request_id'] ?? 0);
        
        if (!$request_id) {
            wp_send_json_error(__('Request ID required', 'protecteddocs'));
        }

        $request = Database::get_request_by_id($request_id);
        if (!$request) {
            wp_send_json_error(__('Request not found', 'protecteddocs'));
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
                            <button type="button" class="authdocs-request-access-btn" data-document-id="<?php echo esc_attr($document['id']); ?>" title="<?php _e('Request Access', 'protecteddocs'); ?>">
                                <svg class="authdocs-lock-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                </svg>
                            </button>
                        <?php else: ?>
                            <?php 
                            $file_type = $document['file_data']['type'] ?? 'file';
                            $link_behavior = Database::get_file_link_behavior($file_type);
                            $link_attributes = 'target="' . esc_attr($link_behavior['target']) . '" title="' . esc_attr($link_behavior['title']) . '"';
                            if ($link_behavior['download']) {
                                $link_attributes .= ' download';
                            }
                            ?>
                            <a href="<?php echo esc_url($document['file_data']['url']); ?>" class="authdocs-download-btn" <?php echo $link_attributes; ?>>
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
                printf(__('Showing %d-%d of %d documents', 'protecteddocs'), $start, $end, $total_documents);
                ?>
            </div>
            
            <?php if ($pagination_style === 'load_more'): ?>
                <?php if ($page < $total_pages): ?>
                    <button type="button" class="authdocs-load-more-btn" data-current-limit="<?php echo esc_attr($limit); ?>" data-restriction="all" data-featured-image="yes">
                        <?php _e('Load More Documents', 'protecteddocs'); ?>
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <div class="authdocs-pagination-links">
                    <?php if ($page > 1): ?>
                        <button type="button" class="authdocs-pagination-btn authdocs-pagination-prev" data-page="<?php echo esc_attr($page - 1); ?>">
                            <?php _e('Previous', 'protecteddocs'); ?>
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
                            <?php _e('Next', 'protecteddocs'); ?>
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
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'protecteddocs_frontend_nonce')) {
            wp_send_json_error([
                'message' => __('Security check failed', 'protecteddocs')
            ]);
        }

        $current_limit = intval($_POST['limit'] ?? 12);
        $restriction = sanitize_text_field($_POST['restriction'] ?? 'all');
        $load_more_count = intval($_POST['load_more_limit'] ?? 12); // Number of additional documents to load
        $show_featured_image = sanitize_text_field($_POST['featured_image'] ?? 'yes') === 'yes';
        $color_palette = sanitize_text_field($_POST['color_palette'] ?? 'default');
        
        // Validate inputs
        if ($current_limit < 1) $current_limit = 12;
        if (!in_array($restriction, ['all', 'restricted', 'unrestricted'])) $restriction = 'all';
        if ($load_more_count < 1) $load_more_count = 12;
        if (!in_array($color_palette, ['default', 'black_white_blue', 'black_gray'])) $color_palette = 'default';
        
        // Get total documents count for this restriction
        $total_documents = Database::get_published_documents_count($restriction);
        
        // Calculate new limit
        $new_limit = $current_limit + $load_more_count;
        
        // Get documents with the new limit
        $documents = Database::get_published_documents($new_limit, $restriction);
        
        if (empty($documents)) {
            wp_send_json_error([
                'message' => __('No documents found.', 'protecteddocs')
            ]);
        }
        
        // Get only the additional documents (skip the ones already shown)
        $additional_documents = array_slice($documents, $current_limit);
        
        if (empty($additional_documents)) {
            wp_send_json_error([
                'message' => __('No more documents to load.', 'protecteddocs')
            ]);
        }
        
        // Generate HTML for the additional grid items
        $html = $this->generate_grid_items_html_clean($additional_documents, $show_featured_image);
        
        // Generate CSS for the new items
        $settings = new Settings();
        
        // Use custom color palette if specified, otherwise use frontend settings
        if ($color_palette !== 'default') {
            $color_palette = $settings->get_color_palette_data($color_palette);
        } else {
            $color_palette = $settings->get_color_palette_data();
        }
        
        $css = $this->generate_dynamic_css_for_ajax($color_palette);
        
        // Check if there are more documents available
        $has_more = $new_limit < $total_documents;
        
        wp_send_json_success([
            'html' => $html,
            'css' => $css,
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
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'protecteddocs_frontend_nonce')) {
            wp_send_json_error([
                'message' => __('Security check failed', 'protecteddocs')
            ]);
        }

        $page = intval($_POST['page'] ?? 1);
        $limit = intval($_POST['limit'] ?? 12);
        $restriction = sanitize_text_field($_POST['restriction'] ?? 'all');
        $orderby = sanitize_text_field($_POST['orderby'] ?? 'date');
        $order = sanitize_text_field($_POST['order'] ?? 'DESC');
        $show_featured_image = sanitize_text_field($_POST['featured_image'] ?? 'yes') === 'yes';
        $pagination_style = sanitize_text_field($_POST['pagination_style'] ?? 'classic');
        $pagination_type = sanitize_text_field($_POST['pagination_type'] ?? 'ajax');
        $color_palette = sanitize_text_field($_POST['color_palette'] ?? 'default');
        
        // Validate inputs
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 12;
        if (!in_array($restriction, ['all', 'restricted', 'unrestricted'])) $restriction = 'all';
        if (!in_array($orderby, ['date', 'title'])) $orderby = 'date';
        if (!in_array($order, ['ASC', 'DESC'])) $order = 'DESC';
        if (!in_array($color_palette, ['default', 'black_white_blue', 'black_gray'])) $color_palette = 'default';
        
        $documents = Database::get_published_documents($limit, $restriction, $page, $orderby, $order);
        $total_documents = Database::get_published_documents_count($restriction);
        $total_pages = (int) ceil($total_documents / $limit);
        
        if (empty($documents)) {
            wp_send_json_error([
                'message' => __('No documents found.', 'protecteddocs')
            ]);
        }
        
        // Generate HTML for the grid items
        $html = $this->generate_grid_items_html_clean($documents, $show_featured_image);
        
        // Generate pagination HTML
        $pagination_html = $this->generate_pagination_html_clean($page, $total_pages, $limit, $total_documents, $pagination_style, $pagination_type);
        
        // Generate CSS for the items
        $settings = new Settings();
        
        // Use custom color palette if specified, otherwise use frontend settings
        if ($color_palette !== 'default') {
            $color_palette = $settings->get_color_palette_data($color_palette);
        } else {
            $color_palette = $settings->get_color_palette_data();
        }
        
        $css = $this->generate_dynamic_css_for_ajax($color_palette);
        
        wp_send_json_success([
            'html' => $html,
            'pagination_html' => $pagination_html,
            'css' => $css,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_documents' => $total_documents
        ]);
    }
    
    /**
     * Handle AJAX request to render shortcode content
     */
    public function handle_render_shortcode(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'protecteddocs_frontend_nonce')) {
            wp_send_json_error([
                'message' => __('Security check failed', 'protecteddocs')
            ]);
        }

        $shortcode = sanitize_text_field($_POST['shortcode'] ?? '');
        
        if (empty($shortcode)) {
            wp_send_json_error([
                'message' => __('No shortcode provided', 'protecteddocs')
            ]);
        }

        // Execute the shortcode
        $html = do_shortcode($shortcode);
        
        wp_send_json_success([
            'html' => $html
        ]);
    }
    
    /**
     * Handle AJAX request to validate session token
     */
    public function handle_validate_session(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'protecteddocs_frontend_nonce')) {
            wp_send_json_error([
                'message' => __('Security check failed', 'protecteddocs')
            ]);
        }

        $session_token = sanitize_text_field($_POST['session_token'] ?? '');
        
        if (empty($session_token)) {
            wp_send_json_error([
                'message' => __('No session token provided', 'protecteddocs')
            ]);
        }

        // Store the session token for validation
        BotProtection::store_session_token($session_token);
        
        wp_send_json_success([
            'message' => __('Session validated successfully', 'protecteddocs')
        ]);
    }
    
    /**
     * Generate dynamic CSS for AJAX responses (without instance ID)
     */
    private function generate_dynamic_css_for_ajax(array $color_palette): string
    {
        $css = "
        .authdocs-grid-container .card {
            background: {$color_palette['background']} !important;
            border: 1px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
            box-shadow: {$color_palette['shadow']} !important;
        }
        
        .authdocs-grid-container .card-title {
            color: {$color_palette['text']} !important;
        }
        
        .authdocs-grid-container .card-desc {
            color: {$color_palette['text_secondary']} !important;
        }
        
        .authdocs-grid-container .card-date {
            color: {$color_palette['text_secondary']} !important;
        }
        
        .authdocs-grid-container .authdocs-request-access-btn,
        .authdocs-grid-container .authdocs-download-btn {
            background: {$color_palette['primary']} !important;
            color: {$color_palette['secondary']} !important;
            border: 1px solid {$color_palette['primary']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
        .authdocs-grid-container .authdocs-request-access-btn:hover,
        .authdocs-grid-container .authdocs-download-btn:hover {
            background: {$color_palette['text']} !important;
            color: {$color_palette['background']} !important;
            border-color: {$color_palette['text']} !important;
        }
        
        .authdocs-grid-container .authdocs-lock-icon,
        .authdocs-grid-container .authdocs-open-icon {
            color: {$color_palette['secondary']} !important;
        }
        
        .authdocs-grid-container .card:hover .authdocs-request-access-btn .authdocs-lock-icon,
        .authdocs-grid-container .authdocs-download-btn:hover .authdocs-open-icon {
            color: {$color_palette['background']} !important;
        }
        
        .authdocs-grid-container .card:hover .card-overlay {
            background: {$color_palette['primary']}20 !important;
            backdrop-filter: blur(2px);
        }
        
        .authdocs-grid-container .authdocs-pagination-btn,
        .authdocs-grid-container .authdocs-load-more-btn {
            background: {$color_palette['background']} !important;
            color: {$color_palette['text']} !important;
            border: 1px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
        .authdocs-grid-container .authdocs-pagination-btn:hover,
        .authdocs-grid-container .authdocs-load-more-btn:hover {
            background: {$color_palette['primary']} !important;
            color: {$color_palette['secondary']} !important;
            border-color: {$color_palette['primary']} !important;
        }
        
        .authdocs-grid-container .authdocs-pagination-btn.active {
            background: {$color_palette['primary']} !important;
            color: {$color_palette['secondary']} !important;
            border-color: {$color_palette['primary']} !important;
        }
        
        /* Popup styling with color palette */
        .authdocs-modal-card {
            background: {$color_palette['background']} !important;
            border: 1px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
            box-shadow: {$color_palette['shadow']} !important;
        }
        
        .authdocs-modal-header {
            border-bottom: 1px solid {$color_palette['border']} !important;
        }
        
        .authdocs-modal-icon {
            background: {$color_palette['primary']} !important;
        }
        
        .authdocs-modal-title {
            color: {$color_palette['text']} !important;
        }
        
        .authdocs-modal-close {
            background: {$color_palette['background_secondary']} !important;
            color: {$color_palette['text_secondary']} !important;
        }
        
        .authdocs-modal-close:hover {
            background: {$color_palette['border']} !important;
            color: {$color_palette['text']} !important;
        }
        
        .authdocs-modal-description {
            color: {$color_palette['text_secondary']} !important;
        }
        
        .authdocs-form-label {
            color: {$color_palette['text']} !important;
        }
        
        .authdocs-form-label svg {
            color: {$color_palette['text_secondary']} !important;
        }
        
        .authdocs-form-input {
            background: {$color_palette['background']} !important;
            color: {$color_palette['text']} !important;
            border: 2px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
        .authdocs-form-input:focus {
            border-color: {$color_palette['primary']} !important;
            box-shadow: 0 0 0 3px rgba(" . $this->hex_to_rgb($color_palette['primary']) . ", 0.1) !important;
        }
        
        .authdocs-form-input::placeholder {
            color: {$color_palette['text_secondary']} !important;
        }
        
        .authdocs-modal-footer {
            border-top: 1px solid {$color_palette['border']} !important;
        }
        
        .authdocs-btn-primary {
            background: {$color_palette['primary']} !important;
            color: {$color_palette['secondary']} !important;
            border: 1px solid {$color_palette['primary']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
        .authdocs-btn-primary:hover {
            background: {$color_palette['text']} !important;
            color: {$color_palette['background']} !important;
            border-color: {$color_palette['text']} !important;
        }
        
        .authdocs-btn-outline {
            background: transparent !important;
            color: {$color_palette['text_secondary']} !important;
            border: 2px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
        .authdocs-btn-outline:hover {
            background: {$color_palette['background_secondary']} !important;
            border-color: {$color_palette['text_secondary']} !important;
            color: {$color_palette['text']} !important;
        }
        ";
        
        return $css;
    }

    /**
     * Convert hex color to RGB values
     */
    private function hex_to_rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "$r, $g, $b";
    }
    
    /**
     * Generate grid items HTML without CSS (for AJAX responses)
     */
    private function generate_grid_items_html_clean(array $documents, bool $show_featured_image = true): string
    {
        ob_start();
        foreach ($documents as $document): 
            // Get featured image for background (only if enabled)
            $featured_image = '';
            $card_style = '';
            if ($show_featured_image) {
                $featured_image = get_the_post_thumbnail_url($document['id'], 'large');
                if ($featured_image) {
                    $card_style = 'style="background-image: url(' . esc_url($featured_image) . ');"';
                }
            }
        ?>
            <!-- Fresh Clean Card -->
            <article class="authdocs-card" <?php echo $card_style; ?> data-color-palette="<?php echo esc_attr($document['restricted'] ? 'locked' : 'unlocked'); ?>">
                <!-- Card Content Overlay -->
                <div class="authdocs-card-content">
                    <h3 class="authdocs-card-title"><?php echo esc_html($document['title']); ?></h3>
                    <div class="authdocs-card-date"><?php echo esc_html($document['date']); ?></div>
                </div>

                <!-- Status Indicator -->
                <div class="authdocs-card-status">
                    <?php if ($document['restricted']): ?>
                        <div class="authdocs-status-badge authdocs-status-locked">
                            <svg class="authdocs-lock-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                            </svg>
                            <span><?php _e('Locked', 'protecteddocs'); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="authdocs-status-badge authdocs-status-unlocked">
                            <svg class="authdocs-unlock-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h4c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/>
                            </svg>
                            <span><?php _e('Unlocked', 'protecteddocs'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Hover Overlay with Icon -->
                <div class="authdocs-card-hover-overlay">
                    <div class="authdocs-overlay-icon">
                        <?php if ($document['restricted']): ?>
                            <svg class="authdocs-lock-icon" width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                            </svg>
                            <span class="authdocs-overlay-text"><?php _e('Request Access', 'protecteddocs'); ?></span>
                        <?php else: ?>
                            <svg class="authdocs-download-icon" width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                            </svg>
                            <span class="authdocs-overlay-text"><?php _e('Download', 'protecteddocs'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Action Overlay -->
                <div class="authdocs-card-action-overlay">
                    <?php if ($document['restricted']): ?>
                        <button type="button" class="authdocs-request-access-btn" data-document-id="<?php echo esc_attr($document['id']); ?>" title="<?php _e('Request Access', 'protecteddocs'); ?>">
                            <svg class="authdocs-lock-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                            </svg>
                            <span><?php _e('Request Access', 'protecteddocs'); ?></span>
                        </button>
                    <?php else: ?>
                        <?php 
                        $file_type = $document['file_data']['type'] ?? 'file';
                        $link_behavior = Database::get_file_link_behavior($file_type);
                        $link_attributes = 'target="' . esc_attr($link_behavior['target']) . '" title="' . esc_attr($link_behavior['title']) . '"';
                        if ($link_behavior['download']) {
                            $link_attributes .= ' download';
                        }
                        ?>
                        <a href="<?php echo esc_url($document['file_data']['url']); ?>" class="authdocs-download-btn" <?php echo $link_attributes; ?>>
                            <svg class="authdocs-download-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                            </svg>
                            <span><?php _e('Download', 'protecteddocs'); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach;
        return ob_get_clean();
    }
    
    /**
     * Generate pagination HTML without CSS (for AJAX responses)
     */
    private function generate_pagination_html_clean(int $page, int $total_pages, int $limit, int $total_documents, string $pagination_style = 'classic', string $pagination_type = 'ajax'): string
    {
        if ($total_pages <= 1) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="authdocs-pagination <?php echo $pagination_style === 'load_more' ? 'authdocs-load-more-pagination' : 'authdocs-classic-pagination'; ?>" data-pagination-type="<?php echo esc_attr($pagination_type); ?>">
            <div class="authdocs-pagination-info">
                <?php 
                $start = (($page - 1) * $limit) + 1;
                $end = min($page * $limit, $total_documents);
                printf(__('Showing %d-%d of %d documents', 'protecteddocs'), $start, $end, $total_documents);
                ?>
            </div>
            
            <?php if ($pagination_style === 'load_more'): ?>
                <?php if ($page < $total_pages): ?>
                    <button type="button" class="authdocs-load-more-btn" data-current-limit="<?php echo esc_attr($limit); ?>" data-restriction="all" data-featured-image="yes">
                        <?php _e('Load More Documents', 'protecteddocs'); ?>
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <div class="authdocs-pagination-links">
                    <?php if ($pagination_type === 'ajax'): ?>
                        <!-- AJAX Pagination with buttons -->
                        <?php if ($page > 1): ?>
                            <button type="button" class="authdocs-pagination-btn authdocs-pagination-prev" data-page="<?php echo esc_attr($page - 1); ?>">
                                <?php _e('Previous', 'protecteddocs'); ?>
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
                                <?php if ($i === $page): ?>
                                    <span class="authdocs-pagination-btn authdocs-pagination-number active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <button type="button" class="authdocs-pagination-btn authdocs-pagination-number" data-page="<?php echo esc_attr($i); ?>"><?php echo $i; ?></button>
                                <?php endif; ?>
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
                                <?php _e('Next', 'protecteddocs'); ?>
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Classic Pagination with links -->
                        <?php 
                        // Build the base URL for pagination links
                        $current_url = add_query_arg([
                            'authdocs_page' => false,
                            'paged' => false
                        ]);
                        ?>
                        <?php if ($page > 1): ?>
                            <a href="<?php echo esc_url(add_query_arg('authdocs_page', $page - 1, $current_url)); ?>" class="authdocs-pagination-btn authdocs-pagination-prev">
                                <?php _e('Previous', 'protecteddocs'); ?>
                            </a>
                        <?php endif; ?>
                        
                        <div class="authdocs-pagination-numbers">
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1): ?>
                                <a href="<?php echo esc_url(add_query_arg('authdocs_page', 1, $current_url)); ?>" class="authdocs-pagination-btn authdocs-pagination-number">1</a>
                                <?php if ($start_page > 2): ?>
                                    <span class="authdocs-pagination-ellipsis">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <?php if ($i === $page): ?>
                                    <span class="authdocs-pagination-btn authdocs-pagination-number active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo esc_url(add_query_arg('authdocs_page', $i, $current_url)); ?>" class="authdocs-pagination-btn authdocs-pagination-number"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="authdocs-pagination-ellipsis">...</span>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(add_query_arg('authdocs_page', $total_pages, $current_url)); ?>" class="authdocs-pagination-btn authdocs-pagination-number"><?php echo $total_pages; ?></a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo esc_url(add_query_arg('authdocs_page', $page + 1, $current_url)); ?>" class="authdocs-pagination-btn authdocs-pagination-next">
                                <?php _e('Next', 'protecteddocs'); ?>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Debug method to verify admin CSS loading
     * Call this method to check if admin CSS is properly loaded
     */
    public function debug_admin_css_loading(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'protecteddocs'));
        }
        
        $screen = get_current_screen();
        $css_url = PROTECTEDDOCS_PLUGIN_URL . 'assets/css/protecteddocs-admin.css';
        $css_path = PROTECTEDDOCS_PLUGIN_DIR . 'assets/css/protecteddocs-admin.css';
        
        echo '<div class="wrap">';
        echo '<h1>ProtectedDocs Admin CSS Debug</h1>';
        echo '<h2>Current Screen Information</h2>';
        echo '<p><strong>Screen ID:</strong> ' . ($screen ? $screen->id : 'Not available') . '</p>';
        echo '<p><strong>Screen Base:</strong> ' . ($screen ? $screen->base : 'Not available') . '</p>';
        echo '<p><strong>Screen Post Type:</strong> ' . ($screen ? $screen->post_type : 'Not available') . '</p>';
        
        echo '<h2>CSS File Information</h2>';
        echo '<p><strong>CSS URL:</strong> <a href="' . esc_url($css_url) . '" target="_blank">' . esc_html($css_url) . '</a></p>';
        echo '<p><strong>CSS Path:</strong> ' . esc_html($css_path) . '</p>';
        echo '<p><strong>CSS File Exists:</strong> ' . (file_exists($css_path) ? 'Yes' : 'No') . '</p>';
        echo '<p><strong>CSS File Readable:</strong> ' . (is_readable($css_path) ? 'Yes' : 'No') . '</p>';
        
        if (file_exists($css_path)) {
            $file_size = filesize($css_path);
            echo '<p><strong>CSS File Size:</strong> ' . size_format($file_size) . '</p>';
        }
        
        echo '<h2>Enqueued Styles</h2>';
        global $wp_styles;
        $enqueued_styles = [];
        if (isset($wp_styles->registered)) {
            foreach ($wp_styles->registered as $handle => $style) {
                if (strpos($handle, 'protecteddocs') !== false || strpos($handle, 'authdocs') !== false) {
                    $enqueued_styles[$handle] = $style;
                }
            }
        }
        
        if (!empty($enqueued_styles)) {
            echo '<ul>';
            foreach ($enqueued_styles as $handle => $style) {
                echo '<li><strong>' . esc_html($handle) . ':</strong> ' . esc_html($style->src) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>No ProtectedDocs styles found in enqueued styles.</p>';
        }
        
        echo '<h2>Plugin Constants</h2>';
        echo '<p><strong>PROTECTEDDOCS_PLUGIN_URL:</strong> ' . PROTECTEDDOCS_PLUGIN_URL . '</p>';
        echo '<p><strong>PROTECTEDDOCS_PLUGIN_DIR:</strong> ' . PROTECTEDDOCS_PLUGIN_DIR . '</p>';
        echo '<p><strong>PROTECTEDDOCS_VERSION:</strong> ' . PROTECTEDDOCS_VERSION . '</p>';
        
        echo '</div>';
    }
}
