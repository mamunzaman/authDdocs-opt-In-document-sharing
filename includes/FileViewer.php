<?php
declare(strict_types=1);

namespace ProtectedDocs;

/**
 * File Viewer Class
 * Handles dedicated file viewing pages for PPT, PDF, and other document types
 */
class FileViewer
{
    /**
     * Initialize the file viewer
     */
    public function __construct()
    {
        add_action('init', [$this, 'handle_file_viewer_request']);
    }

    /**
     * Handle file viewer page requests
     */
    public function handle_file_viewer_request(): void
    {
        // Check if this is a file viewer request
        if (!isset($_GET['authdocs_viewer']) || !isset($_GET['document_id'])) {
            return;
        }

        $document_id = intval($_GET['document_id']);
        $token = sanitize_text_field($_GET['token'] ?? '');
        $hash = sanitize_text_field($_GET['hash'] ?? '');
        $email = sanitize_email($_GET['email'] ?? '');
        $request_id = intval($_GET['request_id'] ?? 0);

        // Verify access token
        if (!Tokens::verify_download_token($document_id, $token)) {
            $this->render_error_page(
                __('Invalid or expired access link', 'protecteddocs'),
                __('Access Denied', 'protecteddocs'),
                403
            );
            return;
        }

        // Additional validation: Check if request is still valid (not declined)
        if (!empty($hash) && !empty($email)) {
            $has_valid_access = Database::validate_secure_access($hash, $email, $document_id, $request_id > 0 ? $request_id : null);
            
            if (!$has_valid_access) {
                $this->render_error_page(
                    __('Access has been revoked', 'protecteddocs'),
                    __('Access Denied', 'protecteddocs'),
                    403
                );
                return;
            }
        }

        // Get document information
        $document = get_post($document_id);
        if (!$document || $document->post_type !== 'document') {
            $this->render_error_page(
                __('Document not found', 'protecteddocs'),
                __('Access Denied', 'protecteddocs'),
                404
            );
            return;
        }

        // Get file data
        $file_data = Database::get_document_file($document_id);
        if (!$file_data) {
            $this->render_error_page(
                __('File not found', 'protecteddocs'),
                __('Access Denied', 'protecteddocs'),
                404
            );
            return;
        }

        $file_path = $file_data['path'] ?? null;
        if (!$file_path || !file_exists($file_path)) {
            $this->render_error_page(
                __('File not found on server', 'protecteddocs'),
                __('Access Denied', 'protecteddocs'),
                404
            );
            return;
        }

        // Log the view
        Logs::log_download($document_id, get_current_user_id());

        // Render the file viewer page
        $this->render_file_viewer_page($document, $file_data, $file_path);
        exit;
    }

    /**
     * Render the dedicated file viewer page
     */
    private function render_file_viewer_page(\WP_Post $document, array $file_data, string $file_path): void
    {
        $settings = new Settings();
        $color_palette = $settings->get_color_palette_colors();
        
        $file_name = $file_data['filename'] ?? __('Unknown file', 'protecteddocs');
        $file_size = $file_data['size'] ?? 0;
        $file_size_formatted = $file_size ? size_format($file_size) : '';
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $file_type = Database::get_file_type($file_extension);

        // Set proper security headers
        http_response_code(200);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; font-src \'self\'; connect-src \'self\' https://view.officeapps.live.com; frame-src \'self\' https://view.officeapps.live.com; frame-ancestors \'self\';');
        
        // Additional headers for better browser compatibility
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Generate secure file URL for embedding
        $file_url = $this->get_secure_file_url($document->ID, $file_path, $file_name);

        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($document->post_title); ?> - AuthDocs Viewer</title>
            
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
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    height: 100vh;
                    overflow: hidden;
                }
                
                .viewer-header {
                    background: <?php echo esc_attr($color_palette['secondary']); ?>;
                    border-bottom: 2px solid <?php echo esc_attr($color_palette['border']); ?>;
                    padding: 16px 24px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    flex-shrink: 0;
                }
                
                .document-info {
                    display: flex;
                    align-items: center;
                    gap: 16px;
                }
                
                .document-icon {
                    width: 40px;
                    height: 40px;
                    background: <?php echo esc_attr($color_palette['primary']); ?>;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 20px;
                }
                
                .document-details h1 {
                    font-size: 18px;
                    font-weight: 600;
                    margin: 0 0 4px 0;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                }
                
                .document-details p {
                    font-size: 14px;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    opacity: 0.7;
                    margin: 0;
                }
                
                .viewer-actions {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                
                .btn {
                    padding: 8px 16px;
                    border: none;
                    border-radius: 6px;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    transition: all 0.2s ease;
                }
                
                .btn-primary {
                    background: <?php echo esc_attr($color_palette['primary']); ?>;
                    color: white;
                }
                
                .btn-primary:hover {
                    opacity: 0.9;
                    transform: translateY(-1px);
                }
                
                .btn-secondary {
                    background: <?php echo esc_attr($color_palette['secondary']); ?>;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    border: 1px solid <?php echo esc_attr($color_palette['border']); ?>;
                }
                
                .btn-secondary:hover {
                    background: <?php echo esc_attr($color_palette['border']); ?>;
                }
                
                .viewer-container {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                    height: calc(100vh - 80px);
                    min-height: 500px;
                }
                
                .document-viewer {
                    flex: 1;
                    width: 100%;
                    border: none;
                    background: white;
                }
                
                .pdf-viewer-container {
                    position: relative;
                    width: 100%;
                    height: 100%;
                    overflow: hidden;
                }
                
                .pdf-viewer {
                    width: 100% !important;
                    height: 100% !important;
                    border: none !important;
                    display: block;
                }
                
                .office-viewer-container {
                    position: relative;
                    width: 100%;
                    height: 100%;
                }
                
                .office-viewer {
                    width: 100%;
                    height: 100%;
                    border: none;
                }
                
                .office-viewer-loading {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(255, 255, 255, 0.9);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10;
                }
                
                .loading-message {
                    text-align: center;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                }
                
                .loading-spinner {
                    width: 32px;
                    height: 32px;
                    border: 3px solid <?php echo esc_attr($color_palette['border']); ?>;
                    border-top: 3px solid <?php echo esc_attr($color_palette['primary']); ?>;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 16px;
                }
                
                .office-viewer-fallback {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 5;
                }
                
                .error-message {
                    text-align: center;
                    padding: 40px;
                }
                
                .error-message h3 {
                    font-size: 20px;
                    margin-bottom: 16px;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                }
                
                .error-message p {
                    margin-bottom: 24px;
                    color: <?php echo esc_attr($color_palette['text']); ?>;
                    opacity: 0.7;
                }
                
                .download-btn {
                    background: <?php echo esc_attr($color_palette['primary']); ?>;
                    color: white;
                    padding: 12px 24px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-weight: 500;
                    display: inline-block;
                    transition: all 0.2s ease;
                }
                
                .download-btn:hover {
                    opacity: 0.9;
                    transform: translateY(-1px);
                }
                
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                @media (max-width: 768px) {
                    .viewer-header {
                        padding: 12px 16px;
                        flex-direction: column;
                        gap: 12px;
                        align-items: flex-start;
                    }
                    
                    .document-info {
                        width: 100%;
                    }
                    
                    .viewer-actions {
                        width: 100%;
                        justify-content: space-between;
                    }
                    
                    .document-details h1 {
                        font-size: 16px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="viewer-header">
                <div class="document-info">
                    <div class="document-icon">
                        <?php echo Database::get_file_type_icon($file_type); ?>
                    </div>
                    <div class="document-details">
                        <h1><?php echo esc_html($document->post_title); ?></h1>
                        <p><?php echo esc_html($file_name); ?> <?php if ($file_size_formatted): ?>(<?php echo esc_html($file_size_formatted); ?>)<?php endif; ?></p>
                    </div>
                </div>
                
                <div class="viewer-actions">
                    <a href="<?php echo esc_url($this->get_download_url($document->ID, $file_path, $file_name)); ?>" class="btn btn-primary" download>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                        </svg>
                        Download
                    </a>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                        </svg>
                        Back
                    </a>
                </div>
            </div>
            
            <div class="viewer-container">
                <?php if ($file_extension === 'pdf'): ?>
                    <!-- PDF Viewer -->
                    <div class="pdf-viewer-container">
                        <iframe 
                            src="<?php echo esc_url($file_url); ?>" 
                            class="document-viewer pdf-viewer"
                            id="pdf-viewer-iframe"
                             
                            allowfullscreen
                            style="width: 100%; height: 100%; border: none;">
                        </iframe>
                        <div id="pdf-fallback" class="office-viewer-fallback" style="display: none;">
                            <div class="error-message">
                                <h3>PDF Preview</h3>
                                <p>This PDF cannot be previewed in the browser.</p>
                                <a href="<?php echo esc_url($this->get_download_url($document->ID, $file_path, $file_name)); ?>" class="download-btn" download>Download PDF</a>
                            </div>
                        </div>
                    </div>
                <?php elseif (in_array($file_extension, ['ppt', 'pptx'])): ?>
                    <!-- PowerPoint Viewer -->
                    <div class="office-viewer-container">
                        <iframe 
                            src="https://view.officeapps.live.com/op/embed.aspx?src=<?php echo urlencode($file_url); ?>&wdAr=1.7777777777777777" 
                            class="document-viewer office-viewer" 
                             
                            allowfullscreen>
                        </iframe>
                        <div class="office-viewer-loading">
                            <div class="loading-message">
                                <div class="loading-spinner"></div>
                                Loading presentation preview...
                            </div>
                        </div>
                        <div class="office-viewer-fallback" style="display: none;">
                            <div class="error-message">
                                <h3>Presentation Preview</h3>
                                <p>This presentation cannot be previewed in the browser.</p>
                                <a href="<?php echo esc_url($this->get_download_url($document->ID, $file_path, $file_name)); ?>" class="download-btn" download>Download Presentation</a>
                            </div>
                        </div>
                    </div>
                <?php elseif (in_array($file_extension, ['doc', 'docx'])): ?>
                    <!-- Word Viewer -->
                    <div class="office-viewer-container">
                        <iframe 
                            src="https://view.officeapps.live.com/op/embed.aspx?src=<?php echo urlencode($file_url); ?>&wdAr=1.7777777777777777" 
                            class="document-viewer office-viewer" 
                             
                            allowfullscreen>
                        </iframe>
                        <div class="office-viewer-loading">
                            <div class="loading-message">
                                <div class="loading-spinner"></div>
                                Loading document preview...
                            </div>
                        </div>
                        <div class="office-viewer-fallback" style="display: none;">
                            <div class="error-message">
                                <h3>Document Preview</h3>
                                <p>This document cannot be previewed in the browser.</p>
                                <a href="<?php echo esc_url($this->get_download_url($document->ID, $file_path, $file_name)); ?>" class="download-btn" download>Download Document</a>
                            </div>
                        </div>
                    </div>
                <?php elseif (in_array($file_extension, ['xls', 'xlsx'])): ?>
                    <!-- Excel Viewer -->
                    <div class="office-viewer-container">
                        <iframe 
                            src="https://view.officeapps.live.com/op/embed.aspx?src=<?php echo urlencode($file_url); ?>&wdAr=1.7777777777777777" 
                            class="document-viewer office-viewer" 
                            
                            allowfullscreen>
                        </iframe>
                        <div class="office-viewer-loading">
                            <div class="loading-message">
                                <div class="loading-spinner"></div>
                                Loading spreadsheet preview...
                            </div>
                        </div>
                        <div class="office-viewer-fallback" style="display: none;">
                            <div class="error-message">
                                <h3>Spreadsheet Preview</h3>
                                <p>This spreadsheet cannot be previewed in the browser.</p>
                                <a href="<?php echo esc_url($this->get_download_url($document->ID, $file_path, $file_name)); ?>" class="download-btn" download>Download Spreadsheet</a>
                            </div>
                        </div>
                    </div>
                <?php elseif (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'])): ?>
                    <!-- Image Viewer -->
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: #f5f5f5;">
                        <img src="<?php echo esc_url($file_url); ?>" alt="<?php echo esc_attr($document->post_title); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                    </div>
                <?php else: ?>
                    <!-- Fallback for other file types -->
                    <div class="office-viewer-fallback">
                        <div class="error-message">
                            <h3>Preview not available</h3>
                            <p>This file type cannot be previewed in the browser.</p>
                            <a href="<?php echo esc_url($this->get_download_url($document->ID, $file_path, $file_name)); ?>" class="download-btn" download>Download File</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Load external JavaScript -->
            <script src="<?php echo esc_url(PROTECTEDDOCS_PLUGIN_URL . 'assets/js/file-viewer.js'); ?>" defer></script>
        </body>
        </html>
        <?php
    }

    /**
     * Get secure file URL for embedding
     */
    private function get_secure_file_url(int $document_id, string $file_path, string $file_name): string
    {
        $token = Tokens::generate_download_token($document_id);
        return add_query_arg([
            'authdocs_file' => $document_id,
            'token' => $token,
            'filename' => $file_name
        ], home_url('/'));
    }

    /**
     * Get download URL for the file
     */
    private function get_download_url(int $document_id, string $file_path, string $file_name): string
    {
        $token = Tokens::generate_download_token($document_id);
        return add_query_arg([
            'authdocs_download' => $document_id,
            'token' => $token
        ], home_url('/'));
    }

    /**
     * Render error page
     */
    private function render_error_page(string $message, string $title, int $status_code): void
    {
        ErrorPageRenderer::render($message, $title, $status_code);
    }
}
