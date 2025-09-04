<?php
/**
 * Database operations for AuthDocs plugin
 * 
 * @since 1.1.0 Email logic separation; new autoresponder recipient; trigger fixes.
 */
declare(strict_types=1);

namespace AuthDocs;

class Database
{
    private static string $table_name = 'authdocs_requests';

    public static function create_tables(): void
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            document_id bigint(20) NOT NULL,
            requester_name varchar(255) NOT NULL,
            requester_email varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            secure_hash varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY document_id (document_id),
            KEY requester_email (requester_email),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function create_document_post_type(): void
    {
        // This will be handled by the CustomPostType class
    }

    public static function save_access_request(int $document_id, string $name, string $email): int|false
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        // Check if request already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE document_id = %d AND requester_email = %s",
            $document_id,
            $email
        ));
        
        if ($existing) {
            return false; // Request already exists
        }
        
        $result = $wpdb->insert(
            $table_name,
            [
                'document_id' => $document_id,
                'requester_name' => $name,
                'requester_email' => $email,
                'status' => 'inactive'
            ],
            ['%d', '%s', '%s', '%s']
        );
        
        if ($result !== false) {
            return $wpdb->insert_id; // Return the actual request ID
        }
        
        return false;
    }

    public static function get_all_requests(): array
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, p.post_title as document_title 
             FROM $table_name r 
             LEFT JOIN {$wpdb->posts} p ON r.document_id = p.ID
             ORDER BY r.created_at DESC"
        ));
        
        return $results ?: [];
    }

    public static function get_paginated_requests(int $page = 1, int $per_page = 20): array
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $offset = ($page - 1) * $per_page;
        
        $query = $wpdb->prepare(
            "SELECT r.*, p.post_title as document_title 
             FROM $table_name r 
             LEFT JOIN {$wpdb->posts} p ON r.document_id = p.ID
             ORDER BY r.created_at DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );
        
        error_log("AuthDocs Debug - Paginated requests query: " . $query);
        $results = $wpdb->get_results($query);
        
        // Debug: Log the results
        if ($results) {
            foreach ($results as $result) {
                error_log("AuthDocs Debug - Result: ID={$result->id}, Document_ID={$result->document_id}, Document_Title=" . ($result->document_title ?? 'NULL'));
            }
        }
        
        return $results ?: [];
    }

    public static function get_total_requests_count(): int
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        return (int) $count;
    }

    public static function update_request_status(int $request_id, string $status): bool
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $data = ['status' => $status];
        
        if ($status === 'accepted') {
            // Always generate a new hash when accepting
            $data['secure_hash'] = self::generate_secure_hash($request_id);
        } elseif ($status === 'declined') {
            // Clear secure hash when revoking access
            $data['secure_hash'] = null;
        } elseif ($status === 'inactive') {
            // Store the current status before deactivating
            $current_status = self::get_request_status($request_id);
            error_log("AuthDocs: Deactivating request {$request_id}, current status: {$current_status}");
            if ($current_status && $current_status !== 'inactive') {
                $meta_result = update_post_meta($request_id, '_authdocs_previous_status', $current_status);
                error_log("AuthDocs: Saved previous status '{$current_status}' for request {$request_id}, meta result: " . ($meta_result ? 'success' : 'failed'));
            } else {
                error_log("AuthDocs: No previous status to save for request {$request_id} (current status: {$current_status})");
            }
        } elseif ($status === 'restore') {
            // Check if this is a reactivation and restore previous status
            $previous_status = get_post_meta($request_id, '_authdocs_previous_status', true);
            error_log("AuthDocs: Status is 'restore', checking for previous status. Previous status: {$previous_status}");
            
            if ($previous_status && in_array($previous_status, ['accepted', 'declined', 'pending'])) {
                error_log("AuthDocs: Restoring previous status: {$previous_status}");
                $data['status'] = $previous_status;
                // Clear the stored previous status
                delete_post_meta($request_id, '_authdocs_previous_status');
                
                // For reactivation, preserve the existing hash instead of generating a new one
                // The hash should already exist in the database from before deactivation
                // No need to modify secure_hash - it should remain unchanged
                
                // Note: The status change hook will be fired from the calling method
                // since we're changing the status to the previous status
            } else {
                error_log("AuthDocs: No valid previous status found, checking current request status");
                // If no previous status meta found, check the current request status
                // This handles cases where the meta might not have been saved properly
                $current_request = self::get_request_by_id($request_id);
                if ($current_request && $current_request->status === 'inactive') {
                    // If currently inactive, try to determine the most likely previous status
                    // Check if there's a secure_hash, which indicates it was likely 'accepted'
                    if (!empty($current_request->secure_hash)) {
                        error_log("AuthDocs: Found secure_hash, assuming previous status was 'accepted'");
                        $data['status'] = 'accepted';
                    } else {
                        error_log("AuthDocs: No secure_hash found, defaulting to 'pending'");
                        $data['status'] = 'pending';
                    }
                } else {
                    error_log("AuthDocs: Request not found or not inactive, defaulting to 'pending'");
                    $data['status'] = 'pending';
                }
            }
        }
        // Note: For 'inactive' status, hash is preserved to maintain link consistency
        
        // Debug logging for data being updated
        error_log("AuthDocs: Updating request {$request_id} with data: " . json_encode($data));
        
        // Prepare format array based on what fields we're updating
        $formats = ['%s']; // status is always a string
        if (isset($data['secure_hash'])) {
            $formats[] = '%s'; // secure_hash is also a string
        }
        
        error_log("AuthDocs: Using formats: " . json_encode($formats));
        
        $result = $wpdb->update(
            $table_name,
            $data,
            ['id' => $request_id],
            $formats,
            ['%d']
        );
        
        // Debug logging
        if ($result === false) {
            error_log("AuthDocs: Failed to update request status - " . $wpdb->last_error);
        } else {
            error_log("AuthDocs: Successfully updated request ID {$request_id} to status '{$status}'" . (isset($data['secure_hash']) ? " with hash" : ""));
        }
        
        return $result !== false;
    }

    public static function generate_secure_hash(int $request_id): string
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $request_id
        ));
        
        if (!$request) {
            error_log("AuthDocs: Failed to generate hash - request ID {$request_id} not found");
            return '';
        }
        
        // Create unique hash using request ID, email, document ID, and timestamp
        $hash_data = $request_id . $request->requester_email . $request->document_id . time() . wp_salt();
        $hash = hash('sha256', $hash_data);
        
        error_log("AuthDocs: Generated hash for request ID {$request_id}: " . substr($hash, 0, 10) . "...");
        
        return $hash;
    }

    public static function validate_secure_access(string $hash, string $email, int $document_id, int $request_id = null): bool
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $where_conditions = [
            "secure_hash = %s",
            "requester_email = %s", 
            "document_id = %d",
            "status IN ('accepted', 'pending')"
        ];
        
        $where_values = [$hash, $email, $document_id];
        
        // If request_id is provided, also validate against it for extra security
        if ($request_id !== null) {
            $where_conditions[] = "id = %d";
            $where_values[] = $request_id;
        }
        
        $sql = "SELECT * FROM $table_name WHERE " . implode(' AND ', $where_conditions);
        
        error_log("AuthDocs: validate_secure_access SQL: " . $sql);
        error_log("AuthDocs: validate_secure_access values: " . json_encode($where_values));
        
        $request = $wpdb->get_row($wpdb->prepare($sql, $where_values));
        
        error_log("AuthDocs: validate_secure_access result: " . ($request ? 'found' : 'not found'));
        if ($request) {
            error_log("AuthDocs: Found request - ID: {$request->id}, Status: {$request->status}, Hash: {$request->secure_hash}");
        }
        
        return !empty($request);
    }

    public static function get_request_by_hash(string $hash): ?object
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE secure_hash = %s AND status IN ('accepted', 'pending')",
            $hash
        ));
        
        return $request ?: null;
    }
    
    /**
     * Check if a request is accessible (not inactive)
     */
    public static function is_request_accessible(int $request_id): bool
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table_name WHERE id = %d",
            $request_id
        ));
        
        return $status !== 'inactive';
    }
    
    /**
     * Get document title with fallback
     */
    public static function get_document_title(int $document_id): string {
        if ($document_id <= 0) {
            return '';
        }
        
        $title = get_the_title($document_id);
        if (empty($title)) {
            // Try to get from database directly
            global $wpdb;
            $title = $wpdb->get_var($wpdb->prepare(
                "SELECT post_title FROM {$wpdb->posts} WHERE ID = %d",
                $document_id
            ));
        }
        
        return $title ?: '';
    }
    
    /**
     * Get request by ID
     */
    public static function get_request_by_id(int $request_id): ?object {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_name;
        
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, p.post_title as document_title 
             FROM $table_name r 
             LEFT JOIN {$wpdb->posts} p ON r.document_id = p.ID
             WHERE r.id = %d",
            $request_id
        ));
        
        return $request ?: null;
    }
    
    /**
     * Get request status by ID
     */
    public static function get_request_status(int $request_id): string {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_name;
        
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table_name WHERE id = %d",
            $request_id
        ));
        
        return $status ?: '';
    }

    public static function test_hash_generation(int $request_id): array
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        // Get the request
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $request_id
        ));
        
        if (!$request) {
            return ['error' => 'Request not found'];
        }
        
        // Generate hash
        $hash = self::generate_secure_hash($request_id);
        
        // Try to update with the hash
        $result = $wpdb->update(
            $table_name,
            ['secure_hash' => $hash],
            ['id' => $request_id],
            ['%s'],
            ['%d']
        );
        
        return [
            'request_id' => $request_id,
            'generated_hash' => $hash,
            'update_result' => $result,
            'wpdb_error' => $wpdb->last_error,
            'request_data' => $request
        ];
    }

    public static function check_table_structure(): array
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            return ['error' => 'Table does not exist'];
        }
        
        // Get table structure
        $columns = $wpdb->get_results("DESCRIBE {$table_name}");
        
        return [
            'table_name' => $table_name,
            'table_exists' => $table_exists,
            'columns' => $columns
        ];
    }

    public static function get_document_file(int $document_id): ?array
    {
        // Ensure we have a valid integer
        $document_id = intval($document_id);
        
        if ($document_id <= 0) {
            return null;
        }
        
        $file_id = get_post_meta($document_id, '_authdocs_file_id', true);
        
        if (!$file_id) {
            return null;
        }
        
        $file_url = wp_get_attachment_url($file_id);
        $file_path = get_attached_file($file_id);
        
        if (!$file_url || !$file_path) {
            return null;
        }
        
        return [
            'id' => $file_id,
            'url' => $file_url,
            'path' => $file_path,
            'filename' => basename($file_path),
            'title' => get_the_title($document_id) ?: basename($file_path)
        ];
    }
    
    /**
     * Get published documents for grid display with pagination support
     */
    public static function get_published_documents(int $limit = -1, string $restriction_filter = 'all', int $page = 1, string $orderby = 'date', string $order = 'DESC'): array
    {
        $args = [
            'post_type' => 'document',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'paged' => $page,
            'orderby' => $orderby,
            'order' => $order,
            'meta_query' => []
        ];
        
        // Filter by restriction status if specified
        if ($restriction_filter === 'restricted') {
            $args['meta_query'][] = [
                'key' => '_authdocs_restricted',
                'value' => 'yes',
                'compare' => '='
            ];
        } elseif ($restriction_filter === 'unrestricted') {
            $args['meta_query'][] = [
                'key' => '_authdocs_restricted',
                'value' => 'yes',
                'compare' => '!='
            ];
        }
        
        $query = new \WP_Query($args);
        $documents = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $file_data = self::get_document_file($post_id);
                
                if ($file_data) {
                    $documents[] = [
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'description' => get_the_excerpt() ?: get_the_content(),
                        'date' => get_the_date(),
                        'file_data' => $file_data,
                        'restricted' => get_post_meta($post_id, '_authdocs_restricted', true) === 'yes'
                    ];
                }
            }
            wp_reset_postdata();
        }
        
        return $documents;
    }
    
    /**
     * Get total count of published documents with restriction filter
     */
    public static function get_published_documents_count(string $restriction_filter = 'all'): int
    {
        $args = [
            'post_type' => 'document',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => []
        ];
        
        // Filter by restriction status if specified
        if ($restriction_filter === 'restricted') {
            $args['meta_query'][] = [
                'key' => '_authdocs_restricted',
                'value' => 'yes',
                'compare' => '='
            ];
        } elseif ($restriction_filter === 'unrestricted') {
            $args['meta_query'][] = [
                'key' => '_authdocs_restricted',
                'value' => 'yes',
                'compare' => '!='
            ];
        }
        
        $query = new \WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Delete a request by ID
     */
    public static function delete_request(int $request_id): bool
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $result = $wpdb->delete(
            $table_name,
            ['id' => $request_id],
            ['%d']
        );
        
        if ($result === false) {
            error_log("AuthDocs: Failed to delete request ID {$request_id}");
            return false;
        }
        
        // Clean up any associated post meta
        delete_post_meta($request_id, '_authdocs_previous_status');
        
        error_log("AuthDocs: Successfully deleted request ID {$request_id}");
        return true;
    }
}