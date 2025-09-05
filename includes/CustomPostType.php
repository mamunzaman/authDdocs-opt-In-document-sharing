<?php
declare(strict_types=1);

namespace AuthDocs;

class CustomPostType
{
    public function __construct()
    {
        add_action('init', [$this, 'register_document_post_type']);
        add_action('add_meta_boxes', [$this, 'add_document_meta_boxes']);
        add_action('save_post', [$this, 'save_document_meta']);
        add_action('admin_notices', [$this, 'show_shortcode_notice']);
        add_filter('manage_document_posts_columns', [$this, 'add_document_columns']);
        add_action('manage_document_posts_custom_column', [$this, 'display_document_columns'], 10, 2);
    }

    public function register_document_post_type(): void
    {
        $labels = [
            'name' => __('Documents', 'authdocs'),
            'singular_name' => __('Document', 'authdocs'),
            'menu_name' => __('Documents', 'authdocs'),
            'add_new' => __('Add New Document', 'authdocs'),
            'add_new_item' => __('Add New Document', 'authdocs'),
            'edit_item' => __('Edit Document', 'authdocs'),
            'new_item' => __('New Document', 'authdocs'),
            'view_item' => __('View Document', 'authdocs'),
            'search_items' => __('Search Documents', 'authdocs'),
            'not_found' => __('No documents found', 'authdocs'),
            'not_found_in_trash' => __('No documents found in trash', 'authdocs'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-media-document',
            'supports' => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => false,
        ];

        register_post_type('document', $args);
    }

    public function add_document_meta_boxes(): void
    {
        add_meta_box(
            'authdocs_document_settings',
            __('Document Settings', 'authdocs'),
            [$this, 'document_settings_callback'],
            'document',
            'normal',
            'high'
        );

        add_meta_box(
            'authdocs_shortcode_display',
            __('Shortcode', 'authdocs'),
            [$this, 'shortcode_display_callback'],
            'document',
            'side',
            'high'
        );
    }

    public function document_settings_callback($post): void
    {
        wp_nonce_field('authdocs_document_meta', 'authdocs_document_meta_nonce');
        
        $restricted = get_post_meta($post->ID, '_authdocs_restricted', true);
        $file_id = get_post_meta($post->ID, '_authdocs_file_id', true);
        
        ?>
        <div class="authdocs-document-settings">
            <!-- Document File Section -->
            <div class="authdocs-settings-card">
                <div class="authdocs-card-header">
                    <div class="authdocs-card-icon">
                        <span class="dashicons dashicons-media-document"></span>
                    </div>
                    <div class="authdocs-card-title">
                        <h3><?php _e('Document File', 'authdocs'); ?></h3>
                        <p><?php _e('Upload or select a PDF document for this post', 'authdocs'); ?></p>
                    </div>
                </div>
                <div class="authdocs-card-content">
                    <input type="hidden" id="authdocs_file_id" name="authdocs_file_id" value="<?php echo esc_attr($file_id); ?>" />
                    <div class="authdocs-file-upload">
                        <button type="button" class="button button-primary authdocs-upload-btn" id="authdocs_upload_button">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('Select Document', 'authdocs'); ?>
                        </button>
                        <button type="button" class="button authdocs-remove-btn" id="authdocs_remove_file" style="<?php echo $file_id ? '' : 'display:none;'; ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Remove', 'authdocs'); ?>
                        </button>
                    </div>
                    <div id="authdocs_file_preview" class="authdocs-file-preview">
                        <?php if ($file_id): ?>
                            <div class="authdocs-file-item">
                                <?php echo wp_get_attachment_link($file_id, 'thumbnail', false, true, false); ?>
                            </div>
                        <?php else: ?>
                            <div class="authdocs-no-file">
                                <span class="dashicons dashicons-media-document"></span>
                                <p><?php _e('No document selected', 'authdocs'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Access Settings Section -->
            <div class="authdocs-settings-card">
                <div class="authdocs-card-header">
                    <div class="authdocs-card-icon">
                        <span class="dashicons dashicons-lock"></span>
                    </div>
                    <div class="authdocs-card-title">
                        <h3><?php _e('Access Settings', 'authdocs'); ?></h3>
                        <p><?php _e('Configure how users can access this document', 'authdocs'); ?></p>
                    </div>
                </div>
                <div class="authdocs-card-content">
                    <div class="authdocs-toggle-field">
                        <label class="authdocs-toggle">
                            <input type="checkbox" id="authdocs_restricted" name="authdocs_restricted" value="yes" <?php checked($restricted, 'yes'); ?> />
                            <span class="authdocs-toggle-slider"></span>
                        </label>
                        <div class="authdocs-toggle-content">
                            <h4><?php _e('Require Opt-in Form', 'authdocs'); ?></h4>
                            <p><?php _e('Users must fill out an opt-in form before accessing this document', 'authdocs'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var file_frame;
            
            $('#authdocs_upload_button').on('click', function(e) {
                e.preventDefault();
                
                if (file_frame) {
                    file_frame.open();
                    return;
                }
                
                file_frame = wp.media.frames.file_frame = wp.media({
                    title: '<?php _e('Select Document', 'authdocs'); ?>',
                    button: {
                        text: '<?php _e('Use this document', 'authdocs'); ?>',
                    },
                    multiple: false,
                    library: {
                        type: ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
                    }
                });
                
                file_frame.on('select', function() {
                    var attachment = file_frame.state().get('selection').first().toJSON();
                    $('#authdocs_file_id').val(attachment.id);
                    $('#authdocs_file_preview').html('<div class="authdocs-file-item"><a href="' + attachment.url + '" target="_blank">' + attachment.filename + '</a></div>');
                    $('#authdocs_remove_file').show();
                });
                
                file_frame.open();
            });
            
            // Handle remove file button
            $('#authdocs_remove_file').on('click', function(e) {
                e.preventDefault();
                $('#authdocs_file_id').val('');
                $('#authdocs_file_preview').html('<div class="authdocs-no-file"><span class="dashicons dashicons-media-document"></span><p><?php _e('No document selected', 'authdocs'); ?></p></div>');
                $(this).hide();
            });
        });
        </script>
        <?php
    }

    public function shortcode_display_callback($post): void
    {
        if ($post->post_status === 'publish') {
            $restricted = get_post_meta($post->ID, '_authdocs_restricted', true);
            $restricted_param = $restricted === 'yes' ? 'yes' : 'no';
            $shortcode = sprintf('[authdocs id="%d" restricted="%s"]', $post->ID, $restricted_param);
            ?>
            <p><strong><?php _e('Single Document Shortcode:', 'authdocs'); ?></strong></p>
            <input type="text" value="<?php echo esc_attr($shortcode); ?>" readonly style="width: 100%;" onclick="this.select();" />
            <p class="description"><?php _e('Copy this shortcode to display the document on your pages.', 'authdocs'); ?></p>
            
            <hr style="margin: 20px 0;">
            
            <p><strong><?php _e('Grid View Shortcodes:', 'authdocs'); ?></strong></p>
            <p class="description"><?php _e('Use these shortcodes to display multiple documents in a grid layout:', 'authdocs'); ?></p>
            
            <p><strong><?php _e('All Documents:', 'authdocs'); ?></strong></p>
            <input type="text" value="[authdocs_grid limit=&quot;12&quot; columns=&quot;3&quot;]" readonly style="width: 100%; margin-bottom: 10px;" onclick="this.select();" />
            
            <p><strong><?php _e('Restricted Documents Only:', 'authdocs'); ?></strong></p>
            <input type="text" value="[authdocs_grid restriction=&quot;restricted&quot; limit=&quot;8&quot; columns=&quot;2&quot;]" readonly style="width: 100%; margin-bottom: 10px;" onclick="this.select();" />
            
            <p><strong><?php _e('Unrestricted Documents Only:', 'authdocs'); ?></strong></p>
            <input type="text" value="[authdocs_grid restriction=&quot;unrestricted&quot; limit=&quot;6&quot; columns=&quot;4&quot;]" readonly style="width: 100%;" onclick="this.select();" />
            <?php
        } else {
            ?>
            <p><?php _e('Publish the document to generate the shortcode.', 'authdocs'); ?></p>
            <?php
        }
    }

    public function save_document_meta($post_id): void
    {
        if (!isset($_POST['authdocs_document_meta_nonce']) || 
            !wp_verify_nonce($_POST['authdocs_document_meta_nonce'], 'authdocs_document_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['authdocs_file_id'])) {
            update_post_meta($post_id, '_authdocs_file_id', intval($_POST['authdocs_file_id']));
        }

        if (isset($_POST['authdocs_restricted'])) {
            update_post_meta($post_id, '_authdocs_restricted', 'yes');
        } else {
            update_post_meta($post_id, '_authdocs_restricted', 'no');
        }
    }

    public function show_shortcode_notice(): void
    {
        global $post_type;
        
        if ($post_type === 'document' && isset($_GET['post']) && get_post_status($_GET['post']) === 'publish') {
            $restricted = get_post_meta($_GET['post'], '_authdocs_restricted', true);
            $restricted_param = $restricted === 'yes' ? 'yes' : 'no';
            $shortcode = sprintf('[authdocs id="%d" restricted="%s"]', $_GET['post'], $restricted_param);
            ?>
            <div class="notice notice-info">
                <p><strong><?php _e('Document Shortcode:', 'authdocs'); ?></strong> <code><?php echo esc_html($shortcode); ?></code></p>
                <p><strong><?php _e('Grid View Shortcode:', 'authdocs'); ?></strong> <code>[authdocs_grid limit="12" columns="3"]</code></p>
            </div>
            <?php
        }
    }

    /**
     * Add custom columns to document list view
     */
    public function add_document_columns(array $columns): array
    {
        // Insert featured image column after title
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['featured_image'] = __('Featured Image', 'authdocs');
                $new_columns['pdf_file'] = __('PDF File', 'authdocs');
            }
        }
        return $new_columns;
    }

    /**
     * Display content for custom columns
     */
    public function display_document_columns(string $column, int $post_id): void
    {
        switch ($column) {
            case 'featured_image':
                if (has_post_thumbnail($post_id)) {
                    $thumbnail = get_the_post_thumbnail($post_id, [50, 50], ['style' => 'max-width: 50px; height: auto;']);
                    echo $thumbnail;
                } else {
                    echo '<span class="dashicons dashicons-format-image" style="color: #ccc; font-size: 20px;"></span>';
                }
                break;

            case 'pdf_file':
                $file_id = get_post_meta($post_id, '_authdocs_file_id', true);
                if ($file_id) {
                    $file_url = wp_get_attachment_url($file_id);
                    $file_name = get_the_title($file_id);
                    if ($file_url && $file_name) {
                        echo '<a href="' . esc_url($file_url) . '" target="_blank" class="authdocs-pdf-link">';
                        echo '<span class="dashicons dashicons-media-document" style="margin-right: 5px; color: #d63638;"></span>';
                        echo esc_html($file_name);
                        echo '</a>';
                    } else {
                        echo '<span style="color: #999;">' . __('No file attached', 'authdocs') . '</span>';
                    }
                } else {
                    echo '<span style="color: #999;">' . __('No file attached', 'authdocs') . '</span>';
                }
                break;
        }
    }
}
