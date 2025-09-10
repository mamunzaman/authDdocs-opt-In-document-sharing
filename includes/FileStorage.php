<?php
declare(strict_types=1);

namespace ProtectedDocs;

/**
 * File Storage Class
 * Handles dedicated file storage for AuthDocs plugin
 */
class FileStorage
{
    public const FOLDER_NAME = 'authdocs-files';
    private const HTACCESS_FILE = '.htaccess';
    private static $filter_active = false;
    
    /**
     * Initialize file storage
     */
    public function __construct()
    {
        add_action('init', [$this, 'init_file_storage']);
        add_filter('upload_dir', [$this, 'custom_upload_dir']);
        add_action('wp_handle_upload_prefilter', [$this, 'handle_upload_prefilter']);
        add_action('wp_handle_upload', [$this, 'handle_upload_postfilter']);
    }
    
    /**
     * Initialize file storage system
     */
    public function init_file_storage(): void
    {
        $this->create_dedicated_folder();
        $this->create_htaccess_protection();
    }
    
    /**
     * Get the dedicated upload directory
     */
    public static function get_dedicated_upload_dir(): array
    {
        // Temporarily disable our filter to prevent infinite loop
        self::$filter_active = true;
        $upload_dir = wp_upload_dir();
        self::$filter_active = false;
        
        $authdocs_dir = rtrim($upload_dir['basedir'], '/') . '/' . self::FOLDER_NAME;
        $authdocs_url = rtrim($upload_dir['baseurl'], '/') . '/' . self::FOLDER_NAME;
        
        return [
            'path' => $authdocs_dir,
            'url' => $authdocs_url,
            'subdir' => '/' . self::FOLDER_NAME,
            'basedir' => $upload_dir['basedir'],
            'baseurl' => $upload_dir['baseurl'],
            'error' => false
        ];
    }
    
    /**
     * Create dedicated folder if it doesn't exist
     */
    private function create_dedicated_folder(): void
    {
        $upload_dir = self::get_dedicated_upload_dir();
        $folder_path = $upload_dir['path'];
        
        if (!file_exists($folder_path)) {
            wp_mkdir_p($folder_path);
            
            // Create index.php to prevent directory listing
            $index_content = "<?php\n// Silence is golden.\n";
            file_put_contents($folder_path . '/index.php', $index_content);
        }
    }
    
    /**
     * Create .htaccess protection for the dedicated folder
     */
    private function create_htaccess_protection(): void
    {
        $upload_dir = self::get_dedicated_upload_dir();
        $htaccess_path = rtrim($upload_dir['path'], '/') . '/' . self::HTACCESS_FILE;
        
        if (!file_exists($htaccess_path)) {
            $htaccess_content = "# AuthDocs File Protection\n";
            $htaccess_content .= "# Deny direct access to all files\n";
            $htaccess_content .= "<Files \"*\">\n";
            $htaccess_content .= "    Order Allow,Deny\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</Files>\n";
            $htaccess_content .= "\n";
            $htaccess_content .= "# Allow access only through WordPress\n";
            $htaccess_content .= "<FilesMatch \"\\.(php)$\">\n";
            $htaccess_content .= "    Order Allow,Deny\n";
            $htaccess_content .= "    Allow from all\n";
            $htaccess_content .= "</FilesMatch>\n";
            
            file_put_contents($htaccess_path, $htaccess_content);
        }
    }
    
    /**
     * Custom upload directory for AuthDocs files
     */
    public function custom_upload_dir(array $upload_dir): array
    {
        // Prevent infinite loop
        if (self::$filter_active) {
            return $upload_dir;
        }
        
        // Only apply to AuthDocs document uploads
        if (!$this->is_authdocs_upload()) {
            return $upload_dir;
        }
        
        $authdocs_dir = self::get_dedicated_upload_dir();
        
        return [
            'path' => $authdocs_dir['path'],
            'url' => $authdocs_dir['url'],
            'subdir' => $authdocs_dir['subdir'],
            'basedir' => $authdocs_dir['basedir'],
            'baseurl' => $authdocs_dir['baseurl'],
            'error' => false
        ];
    }
    
    /**
     * Check if this is an AuthDocs upload
     */
    private function is_authdocs_upload(): bool
    {
        // Check if we're in the document post type admin
        if (isset($_POST['post_type']) && $_POST['post_type'] === 'document') {
            return true;
        }
        
        // Check if we're uploading via the AuthDocs media uploader
        if (isset($_POST['action']) && strpos($_POST['action'], 'authdocs') !== false) {
            return true;
        }
        
        // Check if we're in the document edit screen
        if (isset($_GET['post_type']) && $_GET['post_type'] === 'document') {
            return true;
        }
        
        // Check if we're editing a document
        if (isset($_GET['post']) && get_post_type($_GET['post']) === 'document') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle upload prefilter
     */
    public function handle_upload_prefilter(array $file): array
    {
        if (!$this->is_authdocs_upload()) {
            return $file;
        }
        
        // Add AuthDocs prefix to filename for identification
        $file['name'] = 'authdocs_' . time() . '_' . $file['name'];
        
        return $file;
    }
    
    /**
     * Handle upload postfilter
     */
    public function handle_upload_postfilter(array $upload): array
    {
        if (!$this->is_authdocs_upload()) {
            return $upload;
        }
        
        // Log the upload for tracking
        error_log('AuthDocs: File uploaded to dedicated folder: ' . $upload['file']);
        
        return $upload;
    }
    
    /**
     * Move existing file to dedicated folder
     */
    public static function move_file_to_dedicated_folder(int $attachment_id): bool
    {
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            return false;
        }
        
        $upload_dir = self::get_dedicated_upload_dir();
        $filename = basename($file_path);
        $new_path = rtrim($upload_dir['path'], '/') . '/' . $filename;
        
        // Don't move if already in the right location
        if (strpos($file_path, $upload_dir['path']) !== false) {
            return true;
        }
        
        // Create the dedicated folder if it doesn't exist
        if (!file_exists($upload_dir['path'])) {
            wp_mkdir_p($upload_dir['path']);
        }
        
        // Move the file
        if (rename($file_path, $new_path)) {
            // Update the file URL
            $file_url = rtrim($upload_dir['url'], '/') . '/' . $filename;
            // Update _wp_attached_file with the correct relative path
            $relative_path = self::FOLDER_NAME . '/' . $filename;
            
            error_log("AuthDocs: Migration - Updating _wp_attached_file to: {$relative_path}");
            update_post_meta($attachment_id, '_wp_attached_file', $relative_path);
            
            // Update the attachment metadata with the new path
            error_log("AuthDocs: Migration - Updating attached_file to: {$relative_path}");
            update_attached_file($attachment_id, $relative_path);
            
            error_log('AuthDocs: File moved to dedicated folder: ' . $new_path);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get file path in dedicated folder
     */
    public static function get_file_path(int $attachment_id): ?string
    {
        $file_path = get_attached_file($attachment_id);
        
        error_log("AuthDocs: get_file_path - Attachment ID: {$attachment_id}, Path from get_attached_file: {$file_path}");
        
        if (!$file_path) {
            error_log("AuthDocs: get_file_path - No file path found for attachment ID: {$attachment_id}");
            return null;
        }
        
        // If file exists in original location, return it
        if (file_exists($file_path)) {
            error_log("AuthDocs: get_file_path - File exists in original location: {$file_path}");
            return $file_path;
        }
        
        // If not found in original location, check dedicated folder
        $upload_dir = self::get_dedicated_upload_dir();
        $dedicated_path = rtrim($upload_dir['path'], '/') . '/' . basename($file_path);
        
        error_log("AuthDocs: get_file_path - Checking dedicated path: {$dedicated_path}");
        
        if (file_exists($dedicated_path)) {
            error_log("AuthDocs: get_file_path - File found in dedicated folder: {$dedicated_path}");
            return $dedicated_path;
        }
        
        error_log("AuthDocs: get_file_path - File not found in original or dedicated location for attachment ID: {$attachment_id}");
        return null;
    }
    
    /**
     * Check if file is in dedicated folder
     */
    public static function is_file_in_dedicated_folder(string $file_path): bool
    {
        $upload_dir = self::get_dedicated_upload_dir();
        return strpos($file_path, $upload_dir['path']) !== false;
    }
    
    /**
     * Migrate existing files to dedicated folder
     */
    public static function migrate_existing_files(): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'already_moved' => 0,
            'errors' => []
        ];
        
        // Get all documents with files
        $documents = get_posts([
            'post_type' => 'document',
            'meta_key' => '_authdocs_file_id',
            'posts_per_page' => -1
        ]);
        
        foreach ($documents as $document) {
            $file_id = get_post_meta($document->ID, '_authdocs_file_id', true);
            
            if (!$file_id) {
                continue;
            }
            
            $file_path = get_attached_file($file_id);
            
            if (!$file_path || !file_exists($file_path)) {
                $results['failed']++;
                $results['errors'][] = "File not found for document ID {$document->ID}";
                continue;
            }
            
            // Check if already in dedicated folder
            if (self::is_file_in_dedicated_folder($file_path)) {
                $results['already_moved']++;
                continue;
            }
            
            // Move the file
            if (self::move_file_to_dedicated_folder(intval($file_id))) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to move file for document ID {$document->ID}";
            }
        }
        
        return $results;
    }
    
    /**
     * Get folder protection status
     */
    public static function get_protection_status(): array
    {
        $upload_dir = self::get_dedicated_upload_dir();
        $folder_path = $upload_dir['path'];
        $htaccess_path = rtrim($folder_path, '/') . '/' . self::HTACCESS_FILE;
        $index_path = $folder_path . '/index.php';
        
        return [
            'folder_exists' => file_exists($folder_path),
            'htaccess_exists' => file_exists($htaccess_path),
            'index_exists' => file_exists($index_path),
            'folder_writable' => is_writable($folder_path),
            'folder_path' => $folder_path,
            'htaccess_path' => $htaccess_path
        ];
    }
}
