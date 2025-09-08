<?php
/**
 * Frontend Settings page template for ProtectedDocs plugin
 * 
 * @package ProtectedDocs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'protecteddocs'));
}

// Create Settings instance for helper methods
$settings = new \ProtectedDocs\Settings();

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
                <span class="dashicons dashicons-admin-appearance"></span>
            </span>
            <?php _e('Frontend Settings', 'protecteddocs'); ?>
        </h1>
        <p class="authdocs-page-description">
            <?php _e('Customize how your documents appear on the frontend', 'protecteddocs'); ?>
        </p>
    </div>
    
    <?php if ($settings_updated): ?>
        <div class="authdocs-settings-notice notice notice-success">
            <div class="authdocs-notice-content">
                <div class="authdocs-notice-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="authdocs-notice-text">
                    <strong><?php _e('Settings Saved Successfully!', 'protecteddocs'); ?></strong>
                    <p><?php _e('Your frontend settings have been saved and are now active.', 'protecteddocs'); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="authdocs-settings-container">
        <div class="authdocs-settings-layout">
            <div class="authdocs-settings-main authdocs-frontend-settings-main">
                <div class="authdocs-frontend-settings-header">
                    <h2 class="authdocs-main-title">
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <?php _e('Frontend Display Configuration', 'protecteddocs'); ?>
                    </h2>
                    <p class="authdocs-main-description">
                        <?php _e('Customize how your documents appear on the frontend. Configure color schemes, pagination styles, and visual elements to match your website\'s design.', 'protecteddocs'); ?>
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
                            <?php _e('Save Frontend Settings', 'protecteddocs'); ?>
                        </button>
                    </div>
                    
                    <div class="authdocs-frontend-sections">
                        <!-- Display & Navigation Section (Combined) -->
                        <div class="authdocs-frontend-section authdocs-section-display-navigation">
                            <div class="authdocs-section-header">
                                <div class="authdocs-section-header-left">
                                    <div class="authdocs-section-icon">
                                        <span class="dashicons dashicons-admin-customizer"></span>
                                    </div>
                                    <div class="authdocs-section-title">
                                        <h3><?php _e('Display & Navigation', 'protecteddocs'); ?></h3>
                                        <p><?php _e('Configure visual appearance and navigation behavior for your document displays', 'protecteddocs'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="authdocs-section-content">
                                <div class="authdocs-two-column-layout authdocs-pagination-color-layout">
                                    <!-- Left Column: Pagination Settings (2/3 width) -->
                                    <div class="authdocs-column authdocs-column-left authdocs-pagination-column">
                                        <div class="authdocs-column-header">
                                            <h4 class="authdocs-column-title">
                                                <span class="dashicons dashicons-admin-page"></span>
                                                <?php _e('Pagination Settings', 'protecteddocs'); ?>
                                            </h4>
                                            <p class="authdocs-column-description">
                                                <?php _e('Configure how multiple documents are displayed and navigated', 'protecteddocs'); ?>
                                            </p>
                                        </div>
                                        <div class="authdocs-column-content">
                                            <?php 
                                            // Render pagination fields
                                            $settings->render_pagination_type_field();
                                            $settings->render_pagination_style_field();
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Right Column: Color Palette (1/3 width) -->
                                    <div class="authdocs-column authdocs-column-right authdocs-color-column">
                                        <div class="authdocs-column-header">
                                            <h4 class="authdocs-column-title">
                                                <span class="dashicons dashicons-admin-customizer"></span>
                                                <?php _e('Color Palette', 'protecteddocs'); ?>
                                            </h4>
                                            <p class="authdocs-column-description">
                                                <?php _e('Choose the visual theme for your document displays', 'protecteddocs'); ?>
                                            </p>
                                        </div>
                                        <div class="authdocs-column-content">
                                            <?php 
                                            // Render only the color palette field
                                            $settings->render_frontend_color_palette_field();
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="authdocs-form-actions authdocs-form-actions-bottom">
                        <button type="submit" class="button button-primary authdocs-save-button">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Save Frontend Settings', 'protecteddocs'); ?>
                        </button>
                        <div class="authdocs-save-status">
                            <span class="authdocs-save-indicator"></span>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="authdocs-settings-sidebar authdocs-frontend-sidebar">
                <div class="authdocs-sidebar-header">
                    <h3 class="authdocs-sidebar-title">
                        <span class="dashicons dashicons-desktop"></span>
                        <?php _e('Frontend Tools', 'protecteddocs'); ?>
                    </h3>
                    <p class="authdocs-sidebar-description">
                        <?php _e('Preview and customize your frontend display', 'protecteddocs'); ?>
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
                                <h4><?php _e('Live Preview', 'protecteddocs'); ?></h4>
                                <p><?php _e('See changes in real-time', 'protecteddocs'); ?></p>
                            </div>
                        </div>
                        <div class="authdocs-card-content">
                            <p class="authdocs-card-description">
                                <?php _e('Preview how your documents will appear with the current settings:', 'protecteddocs'); ?>
                            </p>
                            <div class="authdocs-preview-container">
                                <div class="authdocs-preview-grid">
                                    <div class="authdocs-preview-item">
                                        <h4><?php _e('Sample Document', 'protecteddocs'); ?></h4>
                                        <p><?php _e('This is a sample document description.', 'protecteddocs'); ?></p>
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
                                <h4><?php _e('Color Palettes', 'protecteddocs'); ?></h4>
                                <p><?php _e('Available theme options', 'protecteddocs'); ?></p>
                            </div>
                        </div>
                        <div class="authdocs-card-content">
                            <div class="authdocs-palette-info">
                                <div class="authdocs-palette-item">
                                    <div class="authdocs-palette-preview black-white"></div>
                                    <div class="authdocs-palette-details">
                                        <h5><?php _e('Black & White', 'protecteddocs'); ?></h5>
                                        <p><?php _e('Clean, professional look', 'protecteddocs'); ?></p>
                                    </div>
                                </div>
                                <div class="authdocs-palette-item">
                                    <div class="authdocs-palette-preview blue-gray"></div>
                                    <div class="authdocs-palette-details">
                                        <h5><?php _e('Blue & Gray', 'protecteddocs'); ?></h5>
                                        <p><?php _e('Modern, corporate style', 'protecteddocs'); ?></p>
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
                                <h4><?php _e('Pagination Styles', 'protecteddocs'); ?></h4>
                                <p><?php _e('Navigation options', 'protecteddocs'); ?></p>
                            </div>
                        </div>
                        <div class="authdocs-card-content">
                            <div class="authdocs-pagination-info">
                                <div class="authdocs-pagination-item">
                                    <span class="dashicons dashicons-admin-page"></span>
                                    <div class="authdocs-pagination-details">
                                        <h5><?php _e('Classic Pagination', 'protecteddocs'); ?></h5>
                                        <p><?php _e('Traditional page numbers with Previous/Next', 'protecteddocs'); ?></p>
                                    </div>
                                </div>
                                <div class="authdocs-pagination-item">
                                    <span class="dashicons dashicons-plus-alt"></span>
                                    <div class="authdocs-pagination-details">
                                        <h5><?php _e('Load More Button', 'protecteddocs'); ?></h5>
                                        <p><?php _e('Progressive loading with AJAX', 'protecteddocs'); ?></p>
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
