<?php
/**
 * Standardized Error Page Renderer for AuthDocs plugin
 * 
 * Provides consistent UI design for all error pages across the plugin
 * 
 * @since 1.2.0
 */
declare(strict_types=1);

namespace ProtectedDocs;

class ErrorPageRenderer
{
    /**
     * Render a standardized error page
     * 
     * @param string $message Error message to display
     * @param string $title Error page title (default: 'Access Denied')
     * @param int $status_code HTTP status code (default: 403)
     * @param array $document_info Optional document information to display
     * @param string $icon_type Type of icon to display ('error', 'warning', 'info', 'success')
     * @param array $action_buttons Optional custom action buttons
     */
    public static function render(
        string $message, 
        string $title = '', 
        int $status_code = 403, 
        array $document_info = [], 
        string $icon_type = 'error',
        array $action_buttons = []
    ): void {
        // Set default title if not provided
        if (empty($title)) {
            $title = __('Access Denied', 'protecteddocs');
        }
        
        http_response_code($status_code);
        
        // Set proper security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; font-src \'self\'; connect-src \'self\'; frame-ancestors \'self\';');
        
        // Get the current color palette from settings
        $settings = new Settings();
        $color_palette = $settings->get_color_palette_colors();
        
        // Get recipient email for contact information
        $recipient_emails = $settings->get_access_request_recipients();
        $contact_email = !empty($recipient_emails) ? $recipient_emails[0] : get_option('admin_email');
        
        // Set default action buttons if none provided
        if (empty($action_buttons)) {
            $action_buttons = [
                [
                    'url' => home_url('/'),
                    'text' => __('Go Home', 'protecteddocs'),
                    'class' => 'btn-primary',
                    'icon' => 'home'
                ]
            ];
        }
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($title); ?> - AuthDocs</title>
            
            <!-- SEO Protection: Prevent crawling and indexing -->
            <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
            <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
            <meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
            
            <!-- Additional SEO protection -->
            <link rel="canonical" href="<?php echo esc_url(home_url('/')); ?>">
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
                
                .error-container {
                    background: <?php echo esc_attr($color_palette['secondary']); ?>;
                    border: 2px solid <?php echo esc_attr($color_palette['border']); ?>;
                    border-radius: 16px;
                    padding: 48px 32px;
                    text-align: center;
                    max-width: 480px;
                    width: 100%;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
                }
                
                .error-icon {
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
                
                .error-title {
                    font-size: 24px;
                    font-weight: 600;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    margin-bottom: 12px;
                    letter-spacing: -0.3px;
                }
                
                .error-message {
                    font-size: 15px;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    opacity: 0.8;
                    margin-bottom: 32px;
                    line-height: 1.6;
                }
                
                .action-buttons {
                    display: flex;
                    gap: 12px;
                    justify-content: center;
                    flex-wrap: wrap;
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
                
                .btn-secondary {
                    background: <?php echo esc_attr($color_palette['secondary']); ?>;
                    color: <?php echo esc_attr($color_palette['primary']); ?>;
                    border: 2px solid <?php echo esc_attr($color_palette['border']); ?>;
                }
                
                .btn-secondary:hover {
                    background: <?php echo esc_attr($color_palette['primary']); ?>;
                    color: white;
                    transform: translateY(-1px);
                }
                
                .help-text {
                    margin-top: 24px;
                    padding-top: 16px;
                    border-top: 1px solid <?php echo esc_attr($color_palette['border']); ?>;
                    font-size: 13px;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    opacity: 0.7;
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
                
                .contact-info {
                    background: <?php echo esc_attr($color_palette['secondary']); ?>;
                    border: 1px solid <?php echo esc_attr($color_palette['border']); ?>;
                    border-radius: 8px;
                    padding: 16px;
                    margin: 20px 0;
                    text-align: center;
                }
                
                .contact-info h3 {
                    font-size: 14px;
                    font-weight: 600;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    margin: 0 0 8px 0;
                }
                
                .contact-info a {
                    color: <?php echo esc_attr($color_palette['primary']); ?>;
                    text-decoration: none;
                    font-weight: 500;
                }
                
                .contact-info a:hover {
                    text-decoration: underline;
                }
                
                @media (max-width: 480px) {
                    .error-container {
                        padding: 32px 20px;
                        margin: 10px;
                    }
                    
                    .error-title {
                        font-size: 20px;
                    }
                    
                    .error-message {
                        font-size: 14px;
                    }
                    
                    .action-buttons {
                        flex-direction: column;
                        align-items: center;
                    }
                    
                    .btn {
                        width: 100%;
                        max-width: 200px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">
                    <?php echo self::get_icon_svg($icon_type); ?>
                </div>
                
                <h1 class="error-title"><?php echo esc_html($title); ?></h1>
                <p class="error-message"><?php echo esc_html($message); ?></p>
                
                <?php if (!empty($document_info)): ?>
                    <div class="document-info">
                        <h3><?php _e('Document Information', 'protecteddocs'); ?></h3>
                        <?php if (!empty($document_info['title'])): ?>
                            <p><strong><?php _e('Document:', 'protecteddocs'); ?></strong> <?php echo esc_html($document_info['title']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($document_info['filename'])): ?>
                            <p><strong><?php _e('File:', 'protecteddocs'); ?></strong> <?php echo esc_html($document_info['filename']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="contact-info">
                    <h3><?php _e('Need Help?', 'protecteddocs'); ?></h3>
                    <p><?php _e('Contact us for assistance:', 'protecteddocs'); ?> <a href="mailto:<?php echo esc_attr($contact_email); ?>"><?php echo esc_html($contact_email); ?></a></p>
                </div>
                
                <div class="action-buttons">
                    <?php foreach ($action_buttons as $button): ?>
                        <a href="<?php echo esc_url($button['url']); ?>" class="btn <?php echo esc_attr($button['class']); ?>">
                            <?php if (!empty($button['icon'])): ?>
                                <?php echo self::get_icon_svg($button['icon']); ?>
                            <?php endif; ?>
                            <?php echo esc_html($button['text']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <div class="help-text">
                    <?php _e('If you believe this is an error, please contact the administrator.', 'protecteddocs'); ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Get SVG icon based on type
     */
    private static function get_icon_svg(string $type): string
    {
        switch ($type) {
            case 'error':
                return '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z" fill="currentColor"/>
                    <path d="M12 7v6m0 4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>';
                
            case 'warning':
                return '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 9v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>';
                
            case 'info':
                return '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>';
                
            case 'success':
                return '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>';
                
            case 'home':
                return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" fill="currentColor"/>
                </svg>';
                
            default:
                return '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z" fill="currentColor"/>
                    <path d="M12 7v6m0 4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>';
        }
    }
}
