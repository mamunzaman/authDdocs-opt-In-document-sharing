<?php
/**
 * About Plugin page template for AuthDocs plugin
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
?>

<div class="wrap">
    <div class="authdocs-page-header">
        <h1 class="authdocs-page-title">
            <span class="authdocs-page-icon">
                <span class="dashicons dashicons-info"></span>
            </span>
            <?php _e('About Plugin', 'authdocs'); ?>
        </h1>
        <p class="authdocs-page-description">
            <?php _e('Learn about the AuthDocs plugin, its features, and how to get the most out of your document sharing system', 'authdocs'); ?>
        </p>
    </div>
    
    <div class="authdocs-settings-container">
        <div class="authdocs-settings-layout">
            <div class="authdocs-settings-main authdocs-about-plugin-main">
                <div class="authdocs-about-plugin-header">
                    <h2 class="authdocs-main-title">
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('About AuthDocs Plugin', 'authdocs'); ?>
                    </h2>
                    <p class="authdocs-main-description">
                        <?php _e('Learn about the AuthDocs plugin, its features, and how to get the most out of your document sharing system.', 'authdocs'); ?>
                    </p>
                </div>
                
                <div class="authdocs-about-sections">
                    <!-- Plugin Overview Section -->
                    <div class="authdocs-about-section authdocs-section-overview">
                        <div class="authdocs-section-header">
                            <div class="authdocs-section-icon">
                                <span class="dashicons dashicons-admin-plugins"></span>
                            </div>
                            <div class="authdocs-section-title">
                                <h3><?php _e('Plugin Overview', 'authdocs'); ?></h3>
                                <p><?php _e('Secure document sharing with access control', 'authdocs'); ?></p>
                            </div>
                        </div>
                        <div class="authdocs-section-content">
                            <div class="authdocs-about-content">
                                <p><?php _e('AuthDocs is a powerful WordPress plugin that enables secure document sharing with comprehensive access control. It allows you to protect sensitive documents while providing a seamless user experience for requesting and managing access.', 'authdocs'); ?></p>
                                
                                <h4><?php _e('Key Features:', 'authdocs'); ?></h4>
                                <ul class="authdocs-feature-list">
                                    <li><span class="dashicons dashicons-lock"></span> <?php _e('Secure document protection with access control', 'authdocs'); ?></li>
                                    <li><span class="dashicons dashicons-email-alt"></span> <?php _e('Automated email notifications and confirmations', 'authdocs'); ?></li>
                                    <li><span class="dashicons dashicons-admin-appearance"></span> <?php _e('Customizable frontend display and color schemes', 'authdocs'); ?></li>
                                    <li><span class="dashicons dashicons-admin-users"></span> <?php _e('User-friendly access request system', 'authdocs'); ?></li>
                                    <li><span class="dashicons dashicons-admin-tools"></span> <?php _e('Comprehensive admin dashboard and settings', 'authdocs'); ?></li>
                                    <li><span class="dashicons dashicons-shortcode"></span> <?php _e('Flexible shortcode system for easy integration', 'authdocs'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Getting Started Section -->
                    <div class="authdocs-about-section authdocs-section-getting-started">
                        <div class="authdocs-section-header">
                            <div class="authdocs-section-icon">
                                <span class="dashicons dashicons-controls-play"></span>
                            </div>
                            <div class="authdocs-section-title">
                                <h3><?php _e('Getting Started', 'authdocs'); ?></h3>
                                <p><?php _e('Quick setup guide for new users', 'authdocs'); ?></p>
                            </div>
                        </div>
                        <div class="authdocs-section-content">
                            <div class="authdocs-about-content">
                                <h4><?php _e('Step 1: Configure Email Templates', 'authdocs'); ?></h4>
                                <p><?php _e('Go to the Email Templates page and customize your email notifications. Set up templates for access requests, auto-responses, and decision notifications.', 'authdocs'); ?></p>
                                
                                <h4><?php _e('Step 2: Customize Frontend Display', 'authdocs'); ?></h4>
                                <p><?php _e('Visit the Frontend Settings page to choose your color scheme and pagination style. Preview how your documents will appear to users.', 'authdocs'); ?></p>
                                
                                <h4><?php _e('Step 3: Add Documents', 'authdocs'); ?></h4>
                                <p><?php _e('Create new documents using the "Documents" menu. Upload your files and configure access settings for each document.', 'authdocs'); ?></p>
                                
                                <h4><?php _e('Step 4: Display Documents', 'authdocs'); ?></h4>
                                <p><?php _e('Use the shortcode [authdocs_grid] on any page or post to display your protected documents with the configured styling.', 'authdocs'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Documentation Section -->
                    <div class="authdocs-about-section authdocs-section-documentation">
                        <div class="authdocs-section-header">
                            <div class="authdocs-section-icon">
                                <span class="dashicons dashicons-book-alt"></span>
                            </div>
                            <div class="authdocs-section-title">
                                <h3><?php _e('Documentation & Support', 'authdocs'); ?></h3>
                                <p><?php _e('Resources to help you succeed', 'authdocs'); ?></p>
                            </div>
                        </div>
                        <div class="authdocs-section-content">
                            <div class="authdocs-about-content">
                                <p><?php _e('Need help getting started or have questions about advanced features? We\'ve got you covered with comprehensive documentation and support resources.', 'authdocs'); ?></p>
                                
                                <div class="authdocs-documentation-links">
                                    <a href="#" class="authdocs-doc-link">
                                        <span class="dashicons dashicons-book-alt"></span>
                                        <div class="authdocs-doc-content">
                                            <h4><?php _e('User Guide', 'authdocs'); ?></h4>
                                            <p><?php _e('Complete guide to using AuthDocs', 'authdocs'); ?></p>
                                        </div>
                                    </a>
                                    
                                    <a href="#" class="authdocs-doc-link">
                                        <span class="dashicons dashicons-shortcode"></span>
                                        <div class="authdocs-doc-content">
                                            <h4><?php _e('Shortcode Reference', 'authdocs'); ?></h4>
                                            <p><?php _e('All available shortcodes and parameters', 'authdocs'); ?></p>
                                        </div>
                                    </a>
                                    
                                    <a href="#" class="authdocs-doc-link">
                                        <span class="dashicons dashicons-email-alt"></span>
                                        <div class="authdocs-doc-content">
                                            <h4><?php _e('Email Templates', 'authdocs'); ?></h4>
                                            <p><?php _e('Customizing email notifications', 'authdocs'); ?></p>
                                        </div>
                                    </a>
                                    
                                    <a href="#" class="authdocs-doc-link">
                                        <span class="dashicons dashicons-sos"></span>
                                        <div class="authdocs-doc-content">
                                            <h4><?php _e('Support Forum', 'authdocs'); ?></h4>
                                            <p><?php _e('Get help from the community', 'authdocs'); ?></p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="authdocs-settings-sidebar authdocs-about-sidebar">
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
