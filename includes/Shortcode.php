<?php
declare(strict_types=1);

namespace AuthDocs;

class Shortcode
{
    public function __construct()
    {
        add_shortcode('authdocs', [$this, 'render_shortcode']);
        add_action('init', [$this, 'handle_secure_download']);
    }

    public function render_shortcode(array $atts): string
    {
        $atts = shortcode_atts([
            'id' => 0,
            'restricted' => 'no'
        ], $atts, 'authdocs');

        $document_id = intval($atts['id']);
        $restricted = $atts['restricted'] === 'yes';

        if (!$document_id || get_post_type($document_id) !== 'document') {
            return '<p>' . __('Invalid document ID', 'authdocs') . '</p>';
        }

        $document = get_post($document_id);
        if (!$document || $document->post_status !== 'publish') {
            return '<p>' . __('Document not found', 'authdocs') . '</p>';
        }

        $file_data = Database::get_document_file($document_id);
        if (!$file_data) {
            return '<p>' . __('No file attached to this document', 'authdocs') . '</p>';
        }

        ob_start();
        ?>
        <div class="authdocs-document" data-document-id="<?php echo esc_attr($document_id); ?>" data-restricted="<?php echo esc_attr($restricted ? 'yes' : 'no'); ?>">
            <div class="authdocs-document-header">
                <h3 class="authdocs-document-title"><?php echo esc_html($document->post_title); ?></h3>
                <?php if ($document->post_content): ?>
                    <div class="authdocs-document-description">
                        <?php echo wp_kses_post($document->post_content); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="authdocs-document-actions">
                <?php if ($restricted): ?>
                    <button type="button" class="authdocs-request-access-btn" data-document-id="<?php echo esc_attr($document_id); ?>">
                        <?php _e('Request Access', 'authdocs'); ?>
                    </button>
                <?php else: ?>
                    <a href="<?php echo esc_url($file_data['url']); ?>" class="authdocs-download-btn" download>
                        <?php _e('Download Document', 'authdocs'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_secure_download(): void
    {
        // Check if this is a document download request
        if (!isset($_GET['authdocs_download'])) {
            return;
        }

        $document_id = intval($_GET['authdocs_download']);
        $hash = isset($_GET['hash']) ? sanitize_text_field($_GET['hash']) : '';
        $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
        $request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : null;
        $filename = isset($_GET['filename']) ? sanitize_file_name($_GET['filename']) : '';

        // Block access if no hash is provided (direct file access attempt)
        if (empty($hash)) {
            wp_die(__('Access denied. Valid authorization required.', 'authdocs'), __('Access Denied', 'authdocs'), ['response' => 403]);
        }

        // Validate the secure access
        if (!Database::validate_secure_access($hash, $email, $document_id, $request_id)) {
            wp_die(__('Invalid or expired download link', 'authdocs'), __('Access Denied', 'authdocs'), ['response' => 403]);
        }

        // Get the request details to log access
        $request = Database::get_request_by_hash($hash);
        if ($request) {
            // Log the access attempt (optional - for audit trail)
            error_log("AuthDocs: Document access granted for request ID {$request->id}, document ID {$document_id}, email {$email}");
        }

        $file_data = Database::get_document_file($document_id);
        if (!$file_data) {
            wp_die(__('File not found', 'authdocs'), __('Error', 'authdocs'), ['response' => 404]);
        }

        if (!file_exists($file_data['path'])) {
            wp_die(__('File not found on server', 'authdocs'), __('Error', 'authdocs'), ['response' => 404]);
        }

        // Use filename from URL if provided, otherwise use the original filename
        $download_filename = !empty($filename) ? $filename : $file_data['filename'];
        
        // Get file extension to determine content type
        $file_extension = strtolower(pathinfo($file_data['path'], PATHINFO_EXTENSION));
        
        // Set appropriate headers based on file type
        if ($file_extension === 'pdf') {
            // For PDFs, display in browser instead of downloading
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $download_filename . '"');
            header('Content-Length: ' . filesize($file_data['path']));
            header('Cache-Control: public, max-age=3600');
        } else {
            // For other files, use appropriate content type but don't force download
            $content_type = $this->get_content_type($file_extension);
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: inline; filename="' . $download_filename . '"');
            header('Content-Length: ' . filesize($file_data['path']));
            header('Cache-Control: public, max-age=3600');
        }

        // Output file
        readfile($file_data['path']);
        exit;
    }

    private function get_content_type(string $extension): string
    {
        $content_types = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'tiff' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ];

        return $content_types[$extension] ?? 'application/octet-stream';
    }
}
