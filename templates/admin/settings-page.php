<?php
/**
 * Settings page template for AuthDocs plugin
 * 
 * @package AuthDocs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'authdocs'));
}

// Build URLs for navigation
$base_args = [];
if (isset($_GET['post_type']) && $_GET['post_type'] === 'document') {
    $base_args['post_type'] = 'document';
}

$email_templates_url = add_query_arg(array_merge($base_args, ['page' => 'authdocs-email-templates']), admin_url('admin.php'));
$frontend_settings_url = add_query_arg(array_merge($base_args, ['page' => 'authdocs-frontend-settings']), admin_url('admin.php'));
$about_plugin_url = add_query_arg(array_merge($base_args, ['page' => 'authdocs-about-plugin']), admin_url('admin.php'));

// Check if settings were just saved
$settings_updated = false;
if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
    $settings_updated = true;
}
?>

<div class="wrap">
    <div class="authdocs-page-header">
        <h1 class="authdocs-page-title">
            <span class="authdocs-page-icon">
                <span class="dashicons dashicons-admin-settings"></span>
            </span>
            <?php _e('Settings', 'authdocs'); ?>
        </h1>
        <p class="authdocs-page-description">
            <?php _e('Configure email templates, frontend settings, and general plugin options', 'authdocs'); ?>
        </p>
    </div>
    
    <?php if ($settings_updated): ?>
        <div class="authdocs-settings-notice notice notice-success">
            <div class="authdocs-notice-content">
                <div class="authdocs-notice-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="authdocs-notice-text">
                    <strong><?php _e('Settings Saved Successfully!', 'authdocs'); ?></strong>
                    <p><?php _e('Your settings have been saved and are now active.', 'authdocs'); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Settings Navigation -->
    <div class="authdocs-settings-navigation">
        <div class="authdocs-nav-cards">
            <a href="<?php echo esc_url($email_templates_url); ?>" class="authdocs-nav-card">
                <div class="authdocs-nav-card-icon">
                            <span class="dashicons dashicons-email-alt"></span>
                    </div>
                <div class="authdocs-nav-card-content">
                    <h3><?php _e('Email Settings', 'authdocs'); ?></h3>
                    <p><?php _e('Configure email notifications for access requests, auto-responses, and decisions', 'authdocs'); ?></p>
                </div>
                <div class="authdocs-nav-card-arrow">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </div>
            </a>
            
            <a href="<?php echo esc_url($frontend_settings_url); ?>" class="authdocs-nav-card">
                <div class="authdocs-nav-card-icon">
                            <span class="dashicons dashicons-admin-appearance"></span>
                </div>
                <div class="authdocs-nav-card-content">
                    <h3><?php _e('Frontend Settings', 'authdocs'); ?></h3>
                    <p><?php _e('Customize color schemes, pagination styles, and document display options', 'authdocs'); ?></p>
                </div>
                <div class="authdocs-nav-card-arrow">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                            </div>
                                        </a>
                                        
            <a href="<?php echo esc_url($about_plugin_url); ?>" class="authdocs-nav-card">
                <div class="authdocs-nav-card-icon">
                    <span class="dashicons dashicons-info"></span>
                </div>
                <div class="authdocs-nav-card-content">
                    <h3><?php _e('About Plugin', 'authdocs'); ?></h3>
                    <p><?php _e('Learn about AuthDocs features, get help, and view system information', 'authdocs'); ?></p>
                </div>
                <div class="authdocs-nav-card-arrow">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </div>
            </a>
        </div>
    </div>
</div>