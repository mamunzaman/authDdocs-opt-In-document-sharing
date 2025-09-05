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

// Get current tab
$current_tab = $_GET['tab'] ?? 'email-templates';
$allowed_tabs = ['email-templates', 'frontend-settings', 'general'];
if (!in_array($current_tab, $allowed_tabs)) {
    $current_tab = 'email-templates';
}

// Create Settings instance for helper methods
$settings = new \AuthDocs\Settings();

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
            <?php echo esc_html(get_admin_page_title()); ?>
        </h1>
        <p class="authdocs-page-description">
            <?php _e('Configure email templates, frontend settings, and general plugin options', 'authdocs'); ?>
        </p>
    </div>
    
    <?php if ($settings_updated): ?>
        <div class="authdocs-settings-notice notice notice-success is-dismissible">
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
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="?post_type=document&page=authdocs-settings&tab=email-templates" 
           class="nav-tab <?php echo $current_tab === 'email-templates' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Email Templates', 'authdocs'); ?>
        </a>
        <a href="?post_type=document&page=authdocs-settings&tab=frontend-settings" 
           class="nav-tab <?php echo $current_tab === 'frontend-settings' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Frontend Settings', 'authdocs'); ?>
        </a>
        <a href="?post_type=document&page=authdocs-settings&tab=general" 
           class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('General', 'authdocs'); ?>
        </a>
    </nav>
    
    <div class="authdocs-settings-container">
        <!-- Email Templates Tab -->
        <div class="authdocs-tab-content" id="email-templates-tab" style="<?php echo $current_tab === 'email-templates' ? 'display: block;' : 'display: none;'; ?>">
            <div class="authdocs-settings-layout">
                <div class="authdocs-settings-main authdocs-email-templates-main">
                    <div class="authdocs-email-templates-header">
                        <h2 class="authdocs-main-title">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php _e('Email Templates Configuration', 'authdocs'); ?>
                        </h2>
                        <p class="authdocs-main-description">
                            <?php _e('Customize your email templates for different stages of the document access workflow. Each template supports dynamic variables and HTML formatting.', 'authdocs'); ?>
                        </p>
                    </div>
                    
                    <form method="post" action="options.php" class="authdocs-email-templates-form">
                        <?php settings_fields('authdocs_options'); ?>
                        
                        <!-- Top Save Button -->
                        <div class="authdocs-form-actions authdocs-form-actions-top">
                            <button type="submit" class="button button-primary authdocs-save-button">
                                <span class="dashicons dashicons-saved"></span>
                                <?php _e('Save Email Templates', 'authdocs'); ?>
                            </button>
                        </div>
                        
                        <div class="authdocs-email-sections">
                            <!-- Access Request Email Section -->
                            <div class="authdocs-email-section authdocs-section-access-request">
                                <div class="authdocs-section-header">
                                    <div class="authdocs-section-icon">
                                        <span class="dashicons dashicons-admin-users"></span>
                                    </div>
                                    <div class="authdocs-section-title">
                                        <h3><?php _e('Access Request Notification', 'authdocs'); ?></h3>
                                        <p><?php _e('Sent to website owners when someone requests document access', 'authdocs'); ?></p>
                                    </div>
                                </div>
                                <div class="authdocs-section-content">
                                    <?php do_settings_fields('authdocs-settings', 'authdocs_access_request_section'); ?>
                                </div>
                            </div>
                            
                            <!-- Middle Save Button -->
                            <div class="authdocs-form-actions authdocs-form-actions-middle">
                                <button type="submit" class="button button-primary authdocs-save-button">
                                    <span class="dashicons dashicons-saved"></span>
                                    <?php _e('Save Email Templates', 'authdocs'); ?>
                                </button>
                            </div>
                            
                            <!-- Auto-Response Email Section -->
                            <div class="authdocs-email-section authdocs-section-auto-response">
                                <div class="authdocs-section-header">
                                    <div class="authdocs-section-icon">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                    </div>
                                    <div class="authdocs-section-title">
                                        <h3><?php _e('Auto-Response Confirmation', 'authdocs'); ?></h3>
                                        <p><?php _e('Automatically sent to users when they submit an access request', 'authdocs'); ?></p>
                                    </div>
                                </div>
                                <div class="authdocs-section-content">
                                    <?php do_settings_fields('authdocs-settings', 'authdocs_auto_response_section'); ?>
                                </div>
                            </div>
                            
                            <!-- Grant/Decline Email Section -->
                            <div class="authdocs-email-section authdocs-section-grant-decline">
                                <div class="authdocs-section-header">
                                    <div class="authdocs-section-icon">
                                        <span class="dashicons dashicons-awards"></span>
                                    </div>
                                    <div class="authdocs-section-title">
                                        <h3><?php _e('Access Decision Notification', 'authdocs'); ?></h3>
                                        <p><?php _e('Sent to users when their access request is approved or declined', 'authdocs'); ?></p>
                                    </div>
                                </div>
                                <div class="authdocs-section-content">
                                    <?php do_settings_fields('authdocs-settings', 'authdocs_grant_decline_section'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="authdocs-form-actions authdocs-form-actions-bottom">
                            <button type="submit" class="button button-primary authdocs-save-button">
                                <span class="dashicons dashicons-saved"></span>
                                <?php _e('Save Email Templates', 'authdocs'); ?>
                            </button>
                            <div class="authdocs-save-status">
                                <span class="authdocs-save-indicator"></span>
                                <span class="authdocs-save-text"><?php _e('All changes are saved automatically', 'authdocs'); ?></span>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="authdocs-settings-sidebar authdocs-email-sidebar">
                    <div class="authdocs-sidebar-header">
                        <h3 class="authdocs-sidebar-title">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php _e('Tools & Resources', 'authdocs'); ?>
                        </h3>
                        <p class="authdocs-sidebar-description">
                            <?php _e('Preview, test, and optimize your email templates', 'authdocs'); ?>
                        </p>
                    </div>
                    
                    <div class="authdocs-sidebar-content">
                        <!-- Email Preview Section -->
                        <div class="authdocs-sidebar-card authdocs-preview-card">
                            <div class="authdocs-card-header">
                                <div class="authdocs-card-icon">
                                    <span class="dashicons dashicons-visibility"></span>
                                </div>
                                <div class="authdocs-card-title">
                                    <h4><?php _e('Email Preview', 'authdocs'); ?></h4>
                                    <p><?php _e('See how your emails will look', 'authdocs'); ?></p>
                                </div>
                            </div>
                            <div class="authdocs-card-content">
                                <p class="authdocs-card-description">
                                    <?php _e('Preview your email templates with sample data to ensure they look perfect:', 'authdocs'); ?>
                                </p>
                                <div class="authdocs-button-group">
                                    <button type="button" id="preview-access-request" class="authdocs-sidebar-button authdocs-button-preview">
                                        <span class="dashicons dashicons-admin-users"></span>
                                        <?php _e('Access Request', 'authdocs'); ?>
                        </button>
                                    <button type="button" id="preview-auto-response" class="authdocs-sidebar-button authdocs-button-preview">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php _e('Auto-Response', 'authdocs'); ?>
                        </button>
                                    <button type="button" id="preview-grant-decline" class="authdocs-sidebar-button authdocs-button-preview">
                                        <span class="dashicons dashicons-awards"></span>
                                        <?php _e('Grant/Decline', 'authdocs'); ?>
                        </button>
                                </div>
                            </div>
                    </div>
                    
                        <!-- Template Tips Section -->
                        <div class="authdocs-sidebar-card authdocs-tips-card">
                            <div class="authdocs-card-header">
                                <div class="authdocs-card-icon">
                                    <span class="dashicons dashicons-lightbulb"></span>
                                </div>
                                <div class="authdocs-card-title">
                                    <h4><?php _e('Template Tips', 'authdocs'); ?></h4>
                                    <p><?php _e('Best practices for email design', 'authdocs'); ?></p>
                                </div>
                            </div>
                            <div class="authdocs-card-content">
                                <div class="authdocs-tips-list">
                                    <div class="authdocs-tip-item">
                                        <span class="dashicons dashicons-editor-code"></span>
                                        <span><?php _e('Use HTML for rich formatting', 'authdocs'); ?></span>
                                    </div>
                                    <div class="authdocs-tip-item">
                                        <span class="dashicons dashicons-admin-links"></span>
                                        <span><?php _e('Variables are automatically replaced', 'authdocs'); ?></span>
                                    </div>
                                    <div class="authdocs-tip-item">
                                        <span class="dashicons dashicons-email-alt"></span>
                                        <span><?php _e('Test with different email clients', 'authdocs'); ?></span>
                                    </div>
                                    <div class="authdocs-tip-item">
                                        <span class="dashicons dashicons-text"></span>
                                        <span><?php _e('Keep subject lines under 60 characters', 'authdocs'); ?></span>
                                    </div>
                                </div>
                            </div>
                    </div>
                    
                        <!-- Test Emails Section -->
                        <div class="authdocs-sidebar-card authdocs-test-card">
                            <div class="authdocs-card-header">
                                <div class="authdocs-card-icon">
                                    <span class="dashicons dashicons-email"></span>
                                </div>
                                <div class="authdocs-card-title">
                                    <h4><?php _e('Test Emails', 'authdocs'); ?></h4>
                                    <p><?php _e('Send test emails to verify functionality', 'authdocs'); ?></p>
                                </div>
                            </div>
                            <div class="authdocs-card-content">
                                <p class="authdocs-card-description">
                                    <?php _e('Send test emails to verify your templates work correctly:', 'authdocs'); ?>
                                </p>
                                <div class="authdocs-button-group">
                                    <button type="button" id="test-access-request" class="authdocs-sidebar-button authdocs-button-test">
                                        <span class="dashicons dashicons-admin-users"></span>
                            <?php _e('Test Access Request', 'authdocs'); ?>
                        </button>
                                    <button type="button" id="test-auto-response" class="authdocs-sidebar-button authdocs-button-test">
                                        <span class="dashicons dashicons-yes-alt"></span>
                            <?php _e('Test Auto-Response', 'authdocs'); ?>
                        </button>
                                    <button type="button" id="test-grant-decline" class="authdocs-sidebar-button authdocs-button-test">
                                        <span class="dashicons dashicons-awards"></span>
                            <?php _e('Test Grant/Decline', 'authdocs'); ?>
                        </button>
                                </div>
                                <div id="test-email-results" class="authdocs-test-results"></div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions Section -->
                        <div class="authdocs-sidebar-card authdocs-actions-card">
                            <div class="authdocs-card-header">
                                <div class="authdocs-card-icon">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                </div>
                                <div class="authdocs-card-title">
                                    <h4><?php _e('Quick Actions', 'authdocs'); ?></h4>
                                    <p><?php _e('Common tasks and shortcuts', 'authdocs'); ?></p>
                                </div>
                            </div>
                            <div class="authdocs-card-content">
                                <div class="authdocs-quick-actions">
                                    <button type="button" class="authdocs-quick-action" onclick="document.querySelector('.authdocs-save-button').click();">
                                        <span class="dashicons dashicons-saved"></span>
                                        <span><?php _e('Save All Templates', 'authdocs'); ?></span>
                                    </button>
                                    <button type="button" class="authdocs-quick-action" onclick="document.querySelectorAll('.authdocs-button-preview').forEach(btn => btn.click());">
                                        <span class="dashicons dashicons-visibility"></span>
                                        <span><?php _e('Preview All', 'authdocs'); ?></span>
                                    </button>
                                    <button type="button" class="authdocs-quick-action" onclick="document.querySelectorAll('.authdocs-button-test').forEach(btn => btn.click());">
                                        <span class="dashicons dashicons-email"></span>
                                        <span><?php _e('Test All', 'authdocs'); ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Frontend Settings Tab -->
        <div class="authdocs-tab-content" id="frontend-settings-tab" style="<?php echo $current_tab === 'frontend-settings' ? 'display: block;' : 'display: none;'; ?>">
            <div class="authdocs-settings-layout">
                <div class="authdocs-settings-main authdocs-frontend-settings-main">
                    <div class="authdocs-frontend-settings-header">
                        <h2 class="authdocs-main-title">
                            <span class="dashicons dashicons-admin-appearance"></span>
                            <?php _e('Frontend Display Configuration', 'authdocs'); ?>
                        </h2>
                        <p class="authdocs-main-description">
                            <?php _e('Customize how your documents appear on the frontend. Configure color schemes, pagination styles, and visual elements to match your website\'s design.', 'authdocs'); ?>
                        </p>
                    </div>
                    
                    <form method="post" action="options.php" class="authdocs-frontend-settings-form">
                        <?php
                        settings_fields('authdocs_options');
                        
                        // Include hidden fields for all email templates to preserve their values
                        $settings->render_hidden_email_template_fields();
                        ?>
                        
                        <!-- Top Save Button -->
                        <div class="authdocs-form-actions authdocs-form-actions-top">
                            <button type="submit" class="button button-primary authdocs-save-button">
                                <span class="dashicons dashicons-saved"></span>
                                <?php _e('Save Frontend Settings', 'authdocs'); ?>
                            </button>
                        </div>
                        
                        <div class="authdocs-frontend-sections">
                            <!-- Color Palette Section -->
                            <div class="authdocs-frontend-section authdocs-section-color-palette">
                                <div class="authdocs-section-header">
                                    <div class="authdocs-section-icon">
                                        <span class="dashicons dashicons-admin-customizer"></span>
                                    </div>
                                    <div class="authdocs-section-title">
                                        <h3><?php _e('Color Palette', 'authdocs'); ?></h3>
                                        <p><?php _e('Choose the visual theme for your document displays', 'authdocs'); ?></p>
                                    </div>
                                </div>
                                <div class="authdocs-section-content">
                                    <?php do_settings_fields('authdocs-settings', 'authdocs_frontend_colors_section'); ?>
                                </div>
                            </div>
                            
                            <!-- Middle Save Button -->
                            <div class="authdocs-form-actions authdocs-form-actions-middle">
                                <button type="submit" class="button button-primary authdocs-save-button">
                                    <span class="dashicons dashicons-saved"></span>
                                    <?php _e('Save Frontend Settings', 'authdocs'); ?>
                                </button>
                            </div>
                            
                            <!-- Pagination Section -->
                            <div class="authdocs-frontend-section authdocs-section-pagination">
                                <div class="authdocs-section-header">
                                    <div class="authdocs-section-icon">
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </div>
                                    <div class="authdocs-section-title">
                                        <h3><?php _e('Pagination Style', 'authdocs'); ?></h3>
                                        <p><?php _e('Configure how multiple documents are displayed and navigated', 'authdocs'); ?></p>
                                    </div>
                                </div>
                                <div class="authdocs-section-content">
                                    <?php do_settings_fields('authdocs-settings', 'authdocs_pagination_section'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="authdocs-form-actions authdocs-form-actions-bottom">
                            <button type="submit" class="button button-primary authdocs-save-button">
                                <span class="dashicons dashicons-saved"></span>
                                <?php _e('Save Frontend Settings', 'authdocs'); ?>
                            </button>
                            <div class="authdocs-save-status">
                                <span class="authdocs-save-indicator"></span>
                                <span class="authdocs-save-text"><?php _e('All changes are saved automatically', 'authdocs'); ?></span>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="authdocs-settings-sidebar authdocs-frontend-sidebar">
                    <div class="authdocs-sidebar-header">
                        <h3 class="authdocs-sidebar-title">
                            <span class="dashicons dashicons-desktop"></span>
                            <?php _e('Frontend Tools', 'authdocs'); ?>
                        </h3>
                        <p class="authdocs-sidebar-description">
                            <?php _e('Preview and customize your frontend display', 'authdocs'); ?>
                        </p>
                    </div>
                    
                    <div class="authdocs-sidebar-content">
                        <!-- Live Preview Section -->
                        <div class="authdocs-sidebar-card authdocs-preview-card">
                            <div class="authdocs-card-header">
                                <div class="authdocs-card-icon">
                                    <span class="dashicons dashicons-visibility"></span>
                                </div>
                                <div class="authdocs-card-title">
                                    <h4><?php _e('Live Preview', 'authdocs'); ?></h4>
                                    <p><?php _e('See changes in real-time', 'authdocs'); ?></p>
                                </div>
                            </div>
                            <div class="authdocs-card-content">
                                <p class="authdocs-card-description">
                                    <?php _e('Preview how your documents will appear with the current settings:', 'authdocs'); ?>
                                </p>
                        <div class="authdocs-preview-container">
                            <div class="authdocs-preview-grid">
                                <div class="authdocs-preview-item">
                                    <h4><?php _e('Sample Document', 'authdocs'); ?></h4>
                                    <p><?php _e('This is a sample document description.', 'authdocs'); ?></p>
                                    <button class="authdocs-preview-btn authdocs-preview-lock">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                        </svg>
                                    </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Color Palette Info Section -->
                        <div class="authdocs-sidebar-card authdocs-info-card">
                            <div class="authdocs-card-header">
                                <div class="authdocs-card-icon">
                                    <span class="dashicons dashicons-admin-customizer"></span>
                                </div>
                                <div class="authdocs-card-title">
                                    <h4><?php _e('Color Palettes', 'authdocs'); ?></h4>
                                    <p><?php _e('Available theme options', 'authdocs'); ?></p>
                                </div>
                            </div>
                            <div class="authdocs-card-content">
                                <div class="authdocs-palette-info">
                                    <div class="authdocs-palette-item">
                                        <div class="authdocs-palette-preview black-white"></div>
                                        <div class="authdocs-palette-details">
                                            <h5><?php _e('Black & White', 'authdocs'); ?></h5>
                                            <p><?php _e('Clean, professional look', 'authdocs'); ?></p>
                                        </div>
                                    </div>
                                    <div class="authdocs-palette-item">
                                        <div class="authdocs-palette-preview blue-gray"></div>
                                        <div class="authdocs-palette-details">
                                            <h5><?php _e('Blue & Gray', 'authdocs'); ?></h5>
                                            <p><?php _e('Modern, corporate style', 'authdocs'); ?></p>
                                        </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                        <!-- Pagination Styles Section -->
                        <div class="authdocs-sidebar-card authdocs-pagination-card">
                            <div class="authdocs-card-header">
                                <div class="authdocs-card-icon">
                                    <span class="dashicons dashicons-admin-page"></span>
                                </div>
                                <div class="authdocs-card-title">
                                    <h4><?php _e('Pagination Styles', 'authdocs'); ?></h4>
                                    <p><?php _e('Navigation options', 'authdocs'); ?></p>
                                </div>
                            </div>
                            <div class="authdocs-card-content">
                                <div class="authdocs-pagination-info">
                                    <div class="authdocs-pagination-item">
                                        <span class="dashicons dashicons-admin-page"></span>
                                        <div class="authdocs-pagination-details">
                                            <h5><?php _e('Classic Pagination', 'authdocs'); ?></h5>
                                            <p><?php _e('Traditional page numbers with Previous/Next', 'authdocs'); ?></p>
                                        </div>
                                    </div>
                                    <div class="authdocs-pagination-item">
                                        <span class="dashicons dashicons-plus-alt"></span>
                                        <div class="authdocs-pagination-details">
                                            <h5><?php _e('Load More Button', 'authdocs'); ?></h5>
                                            <p><?php _e('Progressive loading with AJAX', 'authdocs'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    </div>
                    
                        <!-- Quick Actions Section -->
                        <div class="authdocs-sidebar-card authdocs-actions-card">
                            <div class="authdocs-card-header">
                                <div class="authdocs-card-icon">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                </div>
                                <div class="authdocs-card-title">
                                    <h4><?php _e('Quick Actions', 'authdocs'); ?></h4>
                                    <p><?php _e('Common tasks and shortcuts', 'authdocs'); ?></p>
                                </div>
                            </div>
                            <div class="authdocs-card-content">
                                <div class="authdocs-quick-actions">
                                    <button type="button" class="authdocs-quick-action" onclick="document.querySelector('.authdocs-save-button').click();">
                                        <span class="dashicons dashicons-saved"></span>
                                        <span><?php _e('Save Settings', 'authdocs'); ?></span>
                                    </button>
                                    <button type="button" class="authdocs-quick-action" onclick="window.open('<?php echo home_url(); ?>', '_blank');">
                                        <span class="dashicons dashicons-external"></span>
                                        <span><?php _e('View Frontend', 'authdocs'); ?></span>
                                    </button>
                                    <button type="button" class="authdocs-quick-action" onclick="document.querySelectorAll('input[type=\"radio\"]').forEach(radio => radio.checked = false);">
                                        <span class="dashicons dashicons-undo"></span>
                                        <span><?php _e('Reset to Defaults', 'authdocs'); ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- General Settings Tab -->
        <div class="authdocs-tab-content" id="general-tab" style="<?php echo $current_tab === 'general' ? 'display: block;' : 'display: none;'; ?>">
            <div class="authdocs-settings-layout">
                <div class="authdocs-settings-main authdocs-general-settings-main">
                    <div class="authdocs-general-settings-header">
                        <h2 class="authdocs-main-title">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php _e('General Plugin Settings', 'authdocs'); ?>
                        </h2>
                        <p class="authdocs-main-description">
                            <?php _e('Configure general plugin settings, view system information, and manage plugin preferences. These settings affect the overall behavior of the AuthDocs plugin.', 'authdocs'); ?>
                        </p>
                    </div>
                    
                    <form method="post" action="options.php" class="authdocs-general-settings-form">
                        <?php
                        settings_fields('authdocs_options');
                        
                        // Include hidden fields for all email templates to preserve their values
                        $settings->render_hidden_email_template_fields();
                        ?>
                        
                        <!-- Top Save Button -->
                        <div class="authdocs-form-actions authdocs-form-actions-top">
                            <button type="submit" class="button button-primary authdocs-save-button">
                                <span class="dashicons dashicons-saved"></span>
                                <?php _e('Save General Settings', 'authdocs'); ?>
                            </button>
                        </div>
                        
                        <div class="authdocs-general-sections">
                            <!-- General Settings Section -->
                            <div class="authdocs-general-section authdocs-section-general">
                                <div class="authdocs-section-header">
                                    <div class="authdocs-section-icon">
                                        <span class="dashicons dashicons-admin-generic"></span>
                                    </div>
                                    <div class="authdocs-section-title">
                                        <h3><?php _e('Plugin Configuration', 'authdocs'); ?></h3>
                                        <p><?php _e('Basic plugin settings and preferences', 'authdocs'); ?></p>
                                    </div>
                                </div>
                                <div class="authdocs-section-content">
                                    <?php do_settings_fields('authdocs-settings', 'authdocs_general_section'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Middle Save Button -->
                        <div class="authdocs-form-actions authdocs-form-actions-middle">
                            <button type="submit" class="button button-primary authdocs-save-button">
                                <span class="dashicons dashicons-saved"></span>
                                <?php _e('Save General Settings', 'authdocs'); ?>
                            </button>
                        </div>
                        
                        <div class="authdocs-form-actions authdocs-form-actions-bottom">
                            <button type="submit" class="button button-primary authdocs-save-button">
                                <span class="dashicons dashicons-saved"></span>
                                <?php _e('Save General Settings', 'authdocs'); ?>
                            </button>
                            <div class="authdocs-save-status">
                                <span class="authdocs-save-indicator"></span>
                                <span class="authdocs-save-text"><?php _e('All changes are saved automatically', 'authdocs'); ?></span>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="authdocs-settings-sidebar authdocs-general-sidebar">
                    <div class="authdocs-sidebar-header">
                        <h3 class="authdocs-sidebar-title">
                            <span class="dashicons dashicons-info"></span>
                            <?php _e('Plugin Information', 'authdocs'); ?>
                        </h3>
                        <p class="authdocs-sidebar-description">
                            <?php _e('System information and support resources', 'authdocs'); ?>
                        </p>
                    </div>
                    
                    <div class="authdocs-sidebar-content">
                        <!-- System Information Section -->
                        <div class="authdocs-sidebar-card authdocs-info-card">
                            <div class="authdocs-card-header">
                                <div class="authdocs-card-icon">
                                    <span class="dashicons dashicons-desktop"></span>
                                </div>
                                <div class="authdocs-card-title">
                                    <h4><?php _e('System Information', 'authdocs'); ?></h4>
                                    <p><?php _e('Current environment details', 'authdocs'); ?></p>
                                </div>
                            </div>
                            <div class="authdocs-card-content">
                                <div class="authdocs-system-info">
                                    <div class="authdocs-info-item">
                                        <span class="authdocs-info-label"><?php _e('Plugin Version:', 'authdocs'); ?></span>
                                        <span class="authdocs-info-value"><?php echo AUTHDOCS_VERSION; ?></span>
                                    </div>
                                    <div class="authdocs-info-item">
                                        <span class="authdocs-info-label"><?php _e('WordPress Version:', 'authdocs'); ?></span>
                                        <span class="authdocs-info-value"><?php echo get_bloginfo('version'); ?></span>
                                    </div>
                                    <div class="authdocs-info-item">
                                        <span class="authdocs-info-label"><?php _e('PHP Version:', 'authdocs'); ?></span>
                                        <span class="authdocs-info-value"><?php echo PHP_VERSION; ?></span>
                                    </div>
                                    <div class="authdocs-info-item">
                                        <span class="authdocs-info-label"><?php _e('Site URL:', 'authdocs'); ?></span>
                                        <span class="authdocs-info-value"><?php echo get_site_url(); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Support Section -->
                        <div class="authdocs-sidebar-card authdocs-support-card">
                            <div class="authdocs-card-header">
                                <div class="authdocs-card-icon">
                                    <span class="dashicons dashicons-sos"></span>
                                </div>
                                <div class="authdocs-card-title">
                                    <h4><?php _e('Support & Resources', 'authdocs'); ?></h4>
                                    <p><?php _e('Get help and documentation', 'authdocs'); ?></p>
                                </div>
                            </div>
                            <div class="authdocs-card-content">
                                <p class="authdocs-card-description">
                                    <?php _e('Need help? Check out our resources and support options:', 'authdocs'); ?>
                                </p>
                                <div class="authdocs-support-actions">
                                    <button type="button" class="authdocs-sidebar-button authdocs-button-support">
                                        <span class="dashicons dashicons-book-alt"></span>
                                        <?php _e('Documentation', 'authdocs'); ?>
                                    </button>
                                    <button type="button" class="authdocs-sidebar-button authdocs-button-support">
                                        <span class="dashicons dashicons-email-alt"></span>
                                        <?php _e('Contact Support', 'authdocs'); ?>
                                    </button>
                                    <button type="button" class="authdocs-sidebar-button authdocs-button-support">
                                        <span class="dashicons dashicons-star-filled"></span>
                                        <?php _e('Rate Plugin', 'authdocs'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions Section -->
                        <div class="authdocs-sidebar-card authdocs-actions-card">
                            <div class="authdocs-card-header">
                                <div class="authdocs-card-icon">
                                    <span class="dashicons dashicons-admin-tools"></span>
                                </div>
                                <div class="authdocs-card-title">
                                    <h4><?php _e('Quick Actions', 'authdocs'); ?></h4>
                                    <p><?php _e('Common administrative tasks', 'authdocs'); ?></p>
                                </div>
                            </div>
                            <div class="authdocs-card-content">
                                <div class="authdocs-quick-actions">
                                    <button type="button" class="authdocs-quick-action" onclick="document.querySelector('.authdocs-save-button').click();">
                                        <span class="dashicons dashicons-saved"></span>
                                        <span><?php _e('Save All Settings', 'authdocs'); ?></span>
                                    </button>
                                    <button type="button" class="authdocs-quick-action" onclick="window.open('<?php echo admin_url('edit.php?post_type=document&page=authdocs-requests'); ?>', '_blank');">
                                        <span class="dashicons dashicons-list-view"></span>
                                        <span><?php _e('View Requests', 'authdocs'); ?></span>
                                    </button>
                                    <button type="button" class="authdocs-quick-action" onclick="window.open('<?php echo admin_url('edit.php?post_type=document'); ?>', '_blank');">
                                        <span class="dashicons dashicons-media-document"></span>
                                        <span><?php _e('Manage Documents', 'authdocs'); ?></span>
                                    </button>
                                </div>
                            </div>
                    </div>
                    
                        <!-- Plugin Status Section -->
                        <div class="authdocs-sidebar-card authdocs-status-card">
                            <div class="authdocs-card-header">
                                <div class="authdocs-card-icon">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                </div>
                                <div class="authdocs-card-title">
                                    <h4><?php _e('Plugin Status', 'authdocs'); ?></h4>
                                    <p><?php _e('Current plugin health', 'authdocs'); ?></p>
                                </div>
                            </div>
                            <div class="authdocs-card-content">
                                <div class="authdocs-status-items">
                                    <div class="authdocs-status-item">
                                        <span class="dashicons dashicons-yes-alt authdocs-status-ok"></span>
                                        <span><?php _e('Plugin Active', 'authdocs'); ?></span>
                                    </div>
                                    <div class="authdocs-status-item">
                                        <span class="dashicons dashicons-yes-alt authdocs-status-ok"></span>
                                        <span><?php _e('Database Connected', 'authdocs'); ?></span>
                                    </div>
                                    <div class="authdocs-status-item">
                                        <span class="dashicons dashicons-yes-alt authdocs-status-ok"></span>
                                        <span><?php _e('Email Templates Ready', 'authdocs'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email Preview Modal -->
<div id="authdocs-email-preview-modal" class="authdocs-modal" style="display: none;">
    <div class="authdocs-modal-content">
        <div class="authdocs-modal-header">
            <h3 id="preview-modal-title"><?php _e('Email Preview', 'authdocs'); ?></h3>
            <button type="button" class="authdocs-modal-close">&times;</button>
        </div>
        <div class="authdocs-modal-body">
            <div class="authdocs-preview-tabs">
                <button type="button" class="authdocs-tab-button active" data-tab="html"><?php _e('HTML', 'authdocs'); ?></button>
                <button type="button" class="authdocs-tab-button" data-tab="preview"><?php _e('Preview', 'authdocs'); ?></button>
            </div>
            
            <div class="authdocs-tab-content active" id="html-tab">
                <div class="authdocs-preview-subject">
                    <strong><?php _e('Subject:', 'authdocs'); ?></strong>
                    <span id="preview-subject"></span>
                </div>
                <textarea id="preview-body-html" readonly rows="20" class="large-text code"></textarea>
            </div>
            
            <div class="authdocs-tab-content" id="preview-tab">
                <div class="authdocs-preview-subject">
                    <strong><?php _e('Subject:', 'authdocs'); ?></strong>
                    <span id="preview-subject-display"></span>
                </div>
                <div id="preview-body-display"></div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var targetTab = $(this).attr('href').split('tab=')[1];
        if (!targetTab) return;
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Hide all tab content
        $('.authdocs-tab-content').hide();
        
        // Show target tab content
        $('#' + targetTab + '-tab').show();
        
        // Update URL without page reload
        var newUrl = window.location.pathname + '?post_type=document&page=authdocs-settings&tab=' + targetTab;
        window.history.pushState({path: newUrl}, '', newUrl);
    });
    
    // Sample data for previews
    var sampleData = {
        name: 'John Doe',
        email: 'john.doe@example.com',
        file_name: 'Sample Document.pdf',
        site_name: '<?php echo esc_js(get_bloginfo('name')); ?>',
        status: 'Granted',
        status_color: '#28a745',
        link: 'https://example.com/authdocs/download?hash=abc123&file=document.pdf'
    };
    
    // Preview functions
    function previewEmail(templateType, title) {
        var subjectField, bodyField;
        
        switch(templateType) {
            case 'access_request':
                subjectField = '#access_request_subject';
                bodyField = '#access_request_body';
                break;
            case 'auto_response':
                subjectField = '#auto_response_subject';
                bodyField = '#auto_response_body';
                break;
            case 'grant_decline':
                subjectField = '#grant_decline_subject';
                bodyField = '#grant_decline_body';
                break;
        }
        
        var subject = $(subjectField).val();
        var body = $(bodyField).val();
        
        // Replace variables in preview
        var previewSubject = replaceVariables(subject, sampleData);
        var previewBody = replaceVariables(body, sampleData);
        
        // Update preview content
        $('#preview-modal-title').text(title);
        $('#preview-subject').text(previewSubject);
        $('#preview-subject-display').text(previewSubject);
        $('#preview-body-html').val(previewBody);
        $('#preview-body-display').html(previewBody);
        
        // Show modal
        $('#authdocs-email-preview-modal').show();
    }
    
    function replaceVariables(text, data) {
        return text.replace(/\{\{name\}\}/g, data.name)
                   .replace(/\{\{email\}\}/g, data.email)
                   .replace(/\{\{file_name\}\}/g, data.file_name)
                   .replace(/\{\{site_name\}\}/g, data.site_name)
                   .replace(/\{\{status\}\}/g, data.status)
                   .replace(/\{\{status_color\}\}/g, data.status_color)
                   .replace(/\{\{link\}\}/g, data.link);
    }
    
    // Preview button handlers
    $('#preview-access-request').on('click', function() {
        previewEmail('access_request', '<?php _e('Access Request Email Preview', 'authdocs'); ?>');
    });
    
    $('#preview-auto-response').on('click', function() {
        previewEmail('auto_response', '<?php _e('Auto-Response Email Preview', 'authdocs'); ?>');
    });
    
    $('#preview-grant-decline').on('click', function() {
        previewEmail('grant_decline', '<?php _e('Grant/Decline Email Preview', 'authdocs'); ?>');
    });
    
    // Close modal
    $('.authdocs-modal-close').on('click', function() {
        $('#authdocs-email-preview-modal').hide();
    });
    
    // Close modal on outside click
    $(window).on('click', function(e) {
        if ($(e.target).is('#authdocs-email-preview-modal')) {
            $('#authdocs-email-preview-modal').hide();
        }
    });
    
    // Tab functionality
    $('.authdocs-tab-button').on('click', function() {
        var tab = $(this).data('tab');
        
        // Update active tab button
        $('.authdocs-tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Update active tab content
        $('.authdocs-tab-content').removeClass('active');
        $('#' + tab + '-tab').addClass('active');
    });
    
    // Test email functions
    function testEmail(action, buttonText, successMessage) {
        var $btn = $('#' + action);
        var $result = $('#test-email-results');
        
        $btn.prop('disabled', true).text('Sending...');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'authdocs_' + action,
                nonce: authdocs_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + successMessage + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Error sending test email</p></div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text(buttonText);
            }
        });
    }
    
    // Test email button handlers
    $('#test-access-request').on('click', function() {
        testEmail('test_access_request', 'Test Access Request', 'Access request test email sent successfully!');
    });
    
    $('#test-auto-response').on('click', function() {
        testEmail('test_auto_response', 'Test Auto-Response', 'Auto-response test email sent successfully!');
    });
    
    $('#test-grant-decline').on('click', function() {
        testEmail('test_grant_decline', 'Test Grant/Decline', 'Grant/decline test email sent successfully!');
    });
});
</script>