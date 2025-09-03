<?php
/**
 * Logs management for AuthDocs plugin
 * 
 * @since 1.3.0 Email send and autorespond log list below settings.
 */
declare(strict_types=1);

namespace AuthDocs;

class Logs
{
    private const LOG_META_PREFIX = '_authdocs_email_log';
    private const LINK_LOG_META_PREFIX = '_authdocs_email_link_log';
    
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_logs_submenu']);
    }
    
    /**
     * Add logs submenu page
     */
    public function add_logs_submenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=document',
            __('Email Logs', 'authdocs'),
            __('Email Logs', 'authdocs'),
            'manage_options',
            'authdocs-logs',
            [$this, 'render_logs_page']
        );
    }
    
    /**
     * Render logs page
     */
    public function render_logs_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'authdocs'));
        }
        
        $logs = $this->get_all_logs();
        $pagination = $this->get_pagination($logs);
        $filtered_logs = $this->filter_logs($logs);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Email Logs', 'authdocs'); ?></h1>
            <p class="description"><?php _e('View all email send attempts and autorespond activities.', 'authdocs'); ?></p>
            
            <?php $this->render_filters(); ?>
            
            <div class="authdocs-logs-container">
                <?php if (empty($filtered_logs)): ?>
                    <div class="notice notice-info">
                        <p><?php _e('No email logs found.', 'authdocs'); ?></p>
                    </div>
                <?php else: ?>
                    <?php $this->render_logs_table($filtered_logs, $pagination); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get all logs from database
     */
    private function get_all_logs(): array
    {
        global $wpdb;
        $logs = [];
        
        // Get email logs from request meta
        $meta_results = $wpdb->get_results("
            SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_type
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key LIKE '{$this->get_log_meta_pattern()}'
            AND p.post_type = 'document'
            ORDER BY pm.meta_id DESC
        ");
        
        foreach ($meta_results as $meta) {
            $log_data = maybe_unserialize($meta->meta_value);
            if (is_array($log_data)) {
                $logs[] = [
                    'id' => $meta->post_id,
                    'type' => 'email',
                    'data' => $log_data,
                    'timestamp' => $log_data['timestamp'] ?? ''
                ];
            }
        }
        
        // Get email link action logs
        $link_meta_results = $wpdb->get_results("
            SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_type
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key LIKE '{$this->get_link_log_meta_pattern()}'
            AND p.post_type = 'document'
            ORDER BY pm.meta_id DESC
        ");
        
        foreach ($link_meta_results as $meta) {
            $log_data = maybe_unserialize($meta->meta_value);
            if (is_array($log_data)) {
                $logs[] = [
                    'id' => $meta->post_id,
                    'type' => 'link_action',
                    'data' => $log_data,
                    'timestamp' => $log_data['timestamp'] ?? ''
                ];
            }
        }
        
        // Sort by timestamp (newest first)
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return $logs;
    }
    
    /**
     * Get log meta pattern for email logs
     */
    private function get_log_meta_pattern(): string
    {
        return str_replace('_', '\\_', self::LOG_META_PREFIX) . '%';
    }
    
    /**
     * Get link log meta pattern
     */
    private function get_link_log_meta_pattern(): string
    {
        return str_replace('_', '\\_', self::LINK_LOG_META_PREFIX) . '%';
    }
    
    /**
     * Filter logs based on user selection
     */
    private function filter_logs(array $logs): array
    {
        $type_filter = $_GET['type'] ?? '';
        $status_filter = $_GET['status'] ?? '';
        $date_filter = $_GET['date'] ?? '';
        
        if ($type_filter) {
            $logs = array_filter($logs, function($log) use ($type_filter) {
                return $log['type'] === $type_filter;
            });
        }
        
        if ($status_filter) {
            $logs = array_filter($logs, function($log) use ($status_filter) {
                $success = $log['data']['success'] ?? false;
                return ($status_filter === 'success' && $success) || 
                       ($status_filter === 'error' && !$success);
            });
        }
        
        if ($date_filter) {
            $logs = array_filter($logs, function($log) use ($date_filter) {
                $log_date = date('Y-m-d', strtotime($log['timestamp']));
                return $log_date === $date_filter;
            });
        }
        
        return $logs;
    }
    
    /**
     * Get pagination data
     */
    private function get_pagination(array $logs): array
    {
        $per_page = 50;
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        $total_items = count($logs);
        $total_pages = ceil($total_items / $per_page);
        
        return [
            'per_page' => $per_page,
            'current_page' => $current_page,
            'total_items' => $total_items,
            'total_pages' => $total_pages
        ];
    }
    
    /**
     * Render filters
     */
    private function render_filters(): void
    {
        $current_type = $_GET['type'] ?? '';
        $current_status = $_GET['status'] ?? '';
        $current_date = $_GET['date'] ?? '';
        
        ?>
        <div class="authdocs-logs-filters">
            <form method="get" class="authdocs-filter-form">
                <input type="hidden" name="post_type" value="document">
                <input type="hidden" name="page" value="authdocs-logs">
                
                <select name="type" class="authdocs-filter-select">
                    <option value=""><?php _e('All Types', 'authdocs'); ?></option>
                    <option value="email" <?php selected($current_type, 'email'); ?>><?php _e('Email Logs', 'authdocs'); ?></option>
                    <option value="link_action" <?php selected($current_type, 'link_action'); ?>><?php _e('Link Action Logs', 'authdocs'); ?></option>
                </select>
                
                <select name="status" class="authdocs-filter-select">
                    <option value=""><?php _e('All Statuses', 'authdocs'); ?></option>
                    <option value="success" <?php selected($current_status, 'success'); ?>><?php _e('Success', 'authdocs'); ?></option>
                    <option value="error" <?php selected($current_status, 'error'); ?>><?php _e('Error', 'authdocs'); ?></option>
                </select>
                
                <input type="date" name="date" value="<?php echo esc_attr($current_date); ?>" class="authdocs-filter-date">
                
                <button type="submit" class="button"><?php _e('Filter', 'authdocs'); ?></button>
                <a href="<?php echo admin_url('edit.php?post_type=document&page=authdocs-logs'); ?>" class="button"><?php _e('Clear', 'authdocs'); ?></a>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render logs table
     */
    private function render_logs_table(array $logs, array $pagination): void
    {
        $start = ($pagination['current_page'] - 1) * $pagination['per_page'];
        $paginated_logs = array_slice($logs, $start, $pagination['per_page']);
        
        ?>
        <table class="wp-list-table widefat fixed striped authdocs-logs-table">
            <thead>
                <tr>
                    <th><?php _e('Date/Time', 'authdocs'); ?></th>
                    <th><?php _e('Type', 'authdocs'); ?></th>
                    <th><?php _e('Request ID', 'authdocs'); ?></th>
                    <th><?php _e('Template', 'authdocs'); ?></th>
                    <th><?php _e('Recipient', 'authdocs'); ?></th>
                    <th><?php _e('Status', 'authdocs'); ?></th>
                    <th><?php _e('Details', 'authdocs'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paginated_logs as $log): ?>
                    <tr>
                        <td>
                            <?php 
                            $timestamp = $log['timestamp'];
                            echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($timestamp)));
                            ?>
                        </td>
                        <td>
                            <?php 
                            $type_label = $log['type'] === 'email' ? __('Email', 'authdocs') : __('Link Action', 'authdocs');
                            echo '<span class="authdocs-log-type authdocs-log-type-' . esc_attr($log['type']) . '">' . esc_html($type_label) . '</span>';
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('edit.php?post_type=document&page=authdocs-requests'); ?>" class="authdocs-request-link">
                                #<?php echo esc_html($log['id']); ?>
                            </a>
                        </td>
                        <td>
                            <?php 
                            $template = $log['data']['template_key'] ?? $log['data']['action'] ?? '';
                            echo esc_html($template);
                            ?>
                        </td>
                        <td>
                            <?php 
                            $recipient = $log['data']['recipient'] ?? '';
                            echo esc_html($recipient);
                            ?>
                        </td>
                        <td>
                            <?php 
                            $success = $log['data']['success'] ?? false;
                            $status_class = $success ? 'success' : 'error';
                            $status_text = $success ? __('Success', 'authdocs') : __('Error', 'authdocs');
                            echo '<span class="authdocs-log-status authdocs-log-status-' . $status_class . '">' . esc_html($status_text) . '</span>';
                            ?>
                        </td>
                        <td>
                            <?php 
                            $message = $log['data']['error_message'] ?? $log['data']['message'] ?? '';
                            if ($message) {
                                echo '<span class="authdocs-log-message">' . esc_html($message) . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php $this->render_pagination($pagination); ?>
        <?php
    }
    
    /**
     * Render pagination
     */
    private function render_pagination(array $pagination): void
    {
        if ($pagination['total_pages'] <= 1) {
            return;
        }
        
        $current_url = add_query_arg([
            'post_type' => 'document',
            'page' => 'authdocs-logs',
            'type' => $_GET['type'] ?? '',
            'status' => $_GET['status'] ?? '',
            'date' => $_GET['date'] ?? ''
        ], admin_url('edit.php'));
        
        ?>
        <div class="authdocs-pagination">
            <span class="authdocs-pagination-info">
                <?php 
                printf(
                    __('Showing %1$d-%2$d of %3$d items', 'authdocs'),
                    ($pagination['current_page'] - 1) * $pagination['per_page'] + 1,
                    min($pagination['current_page'] * $pagination['per_page'], $pagination['total_items']),
                    $pagination['total_items']
                );
                ?>
            </span>
            
            <div class="authdocs-pagination-links">
                <?php if ($pagination['current_page'] > 1): ?>
                    <a href="<?php echo add_query_arg('paged', $pagination['current_page'] - 1, $current_url); ?>" class="button">&laquo; <?php _e('Previous', 'authdocs'); ?></a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                    <?php if ($i === $pagination['current_page']): ?>
                        <span class="authdocs-pagination-current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo add_query_arg('paged', $i, $current_url); ?>" class="button"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                    <a href="<?php echo add_query_arg('paged', $pagination['current_page'] + 1, $current_url); ?>" class="button"><?php _e('Next', 'authdocs'); ?> &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
