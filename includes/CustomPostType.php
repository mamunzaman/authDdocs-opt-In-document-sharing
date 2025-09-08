<?php
declare(strict_types=1);

namespace ProtectedDocs;

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
            'name' => __('Documents', 'protecteddocs'),
            'singular_name' => __('Document', 'protecteddocs'),
            'menu_name' => __('Documents', 'protecteddocs'),
            'add_new' => __('Add New Document', 'protecteddocs'),
            'add_new_item' => __('Add New Document', 'protecteddocs'),
            'edit_item' => __('Edit Document', 'protecteddocs'),
            'new_item' => __('New Document', 'protecteddocs'),
            'view_item' => __('View Document', 'protecteddocs'),
            'search_items' => __('Search Documents', 'protecteddocs'),
            'not_found' => __('No documents found', 'protecteddocs'),
            'not_found_in_trash' => __('No documents found in trash', 'protecteddocs'),
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
            __('Document Settings', 'protecteddocs'),
            [$this, 'document_settings_callback'],
            'document',
            'normal',
            'high'
        );

        add_meta_box(
            'authdocs_shortcode_display',
            __('Shortcode', 'protecteddocs'),
            [$this, 'shortcode_display_callback'],
            'document',
            'normal',
            'core'
        );
    }

    public function document_settings_callback($post): void
    {
        wp_nonce_field('authdocs_document_meta', 'authdocs_document_meta_nonce');
        
        $restricted = get_post_meta($post->ID, '_authdocs_restricted', true);
        $file_id = get_post_meta($post->ID, '_authdocs_file_id', true);
        
        ?>
        <div class="authdocs-acf-style-settings">
            <!-- Document File Field -->
            <div class="acf-field acf-field-file">
                <div class="acf-label">
                    <label for="authdocs_file_id"><?php _e('Document File', 'protecteddocs'); ?></label>
                    <p class="description"><?php _e('Upload or select a document file (PDF, DOC, XLS, PPT, etc.) for this post', 'protecteddocs'); ?></p>
                </div>
                <div class="acf-input">
                    <input type="hidden" id="authdocs_file_id" name="authdocs_file_id" value="<?php echo esc_attr($file_id); ?>" />
                    <div class="acf-file-uploader" data-library="uploadedTo">
                        <div class="acf-file-uploader-inner">
                            <?php if ($file_id): ?>
                                <div class="acf-file-uploader-preview">
                                    <div class="acf-file-uploader-preview-inner">
                                        <div class="acf-file-uploader-preview-icon">
                                            <span class="dashicons dashicons-media-document"></span>
                                        </div>
                                        <div class="acf-file-uploader-preview-info">
                                            <div class="acf-file-uploader-preview-name">
                                                <?php echo esc_html(get_the_title($file_id)); ?>
                                            </div>
                                            <div class="acf-file-uploader-preview-meta">
                                                <?php echo esc_html(size_format(filesize(get_attached_file($file_id)))); ?>
                                            </div>
                                        </div>
                                        <div class="acf-file-uploader-preview-actions">
                                            <a href="<?php echo esc_url(wp_get_attachment_url($file_id)); ?>" target="_blank" class="acf-button acf-button-small">
                                                <span class="dashicons dashicons-external"></span>
                                                <?php _e('View', 'protecteddocs'); ?>
                                            </a>
                                            <button type="button" class="acf-button acf-button-small acf-button-remove" id="authdocs_remove_file">
                                                <span class="dashicons dashicons-trash"></span>
                                                <?php _e('Remove', 'protecteddocs'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="acf-file-uploader-empty">
                                    <div class="acf-file-uploader-empty-icon">
                                        <span class="dashicons dashicons-cloud-upload"></span>
                                    </div>
                                    <div class="acf-file-uploader-empty-text">
                                        <p><?php _e('No file selected', 'protecteddocs'); ?></p>
                                        <p class="description"><?php _e('Click the button below to select a document', 'protecteddocs'); ?></p>
                                    </div>
                                    <button type="button" class="acf-button acf-button-primary" id="authdocs_upload_button">
                                        <span class="dashicons dashicons-upload"></span>
                                        <?php _e('Select Document', 'protecteddocs'); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Access Control Field -->
            <div class="acf-field acf-field-true-false">
                <div class="acf-label">
                    <label for="authdocs_restricted"><?php _e('Access Control', 'protecteddocs'); ?></label>
                    <p class="description"><?php _e('Control how users can access this document', 'protecteddocs'); ?></p>
                </div>
                <div class="acf-input">
                    <div class="acf-true-false">
                        <input type="checkbox" id="authdocs_restricted" name="authdocs_restricted" value="yes" <?php checked($restricted, 'yes'); ?> />
                        <label for="authdocs_restricted">
                            <span class="acf-true-false-label"><?php _e('Require Opt-in Form', 'protecteddocs'); ?></span>
                            <span class="acf-true-false-description"><?php _e('Users must fill out an opt-in form before accessing this document', 'protecteddocs'); ?></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function shortcode_display_callback($post): void
    {
        ?>
        <div class="authdocs-acf-style-settings">
            <?php if ($post->post_status === 'publish'): 
                $restricted = get_post_meta($post->ID, '_authdocs_restricted', true);
                $restricted_param = $restricted === 'yes' ? 'yes' : 'no';
                $shortcode = sprintf('[authdocs id="%d" restricted="%s"]', $post->ID, $restricted_param);
            ?>
                <!-- Single Document Shortcode Field -->
                <div class="acf-field acf-field-text">
                    <div class="acf-label">
                        <label><?php _e('Single Document Shortcode', 'protecteddocs'); ?></label>
                        <p class="description"><?php _e('Copy this shortcode to display the document on your pages.', 'protecteddocs'); ?></p>
                    </div>
                    <div class="acf-input">
                        <div class="acf-input-wrap">
                            <input type="text" value="<?php echo esc_attr($shortcode); ?>" readonly class="acf-input-text" onclick="this.select();" />
                            <button type="button" class="acf-button acf-button-small acf-copy-shortcode" data-shortcode="<?php echo esc_attr($shortcode); ?>">
                                <span class="dashicons dashicons-admin-page"></span>
                                <?php _e('Copy', 'protecteddocs'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Grid View Shortcodes Field -->
                <div class="acf-field acf-field-text">
                    <div class="acf-label">
                        <label><?php _e('Grid View Shortcodes', 'protecteddocs'); ?></label>
                        <p class="description"><?php _e('Use these shortcodes to display multiple documents in a grid layout:', 'protecteddocs'); ?></p>
                    </div>
                    <div class="acf-input">
                        <div class="acf-shortcode-examples">
                            <div class="acf-shortcode-example">
                                <label class="acf-shortcode-label"><?php _e('All Documents:', 'protecteddocs'); ?></label>
                                <div class="acf-input-wrap">
                                    <input type="text" value="[authdocs_grid limit=&quot;12&quot; columns=&quot;3&quot;]" readonly class="acf-input-text" onclick="this.select();" />
                                    <button type="button" class="acf-button acf-button-small acf-copy-shortcode" data-shortcode="[authdocs_grid limit=&quot;12&quot; columns=&quot;3&quot;]">
                                        <span class="dashicons dashicons-admin-page"></span>
                                        <?php _e('Copy', 'protecteddocs'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="acf-shortcode-example">
                                <label class="acf-shortcode-label"><?php _e('Restricted Documents Only:', 'protecteddocs'); ?></label>
                                <div class="acf-input-wrap">
                                    <input type="text" value="[authdocs_grid restriction=&quot;restricted&quot; limit=&quot;8&quot; columns=&quot;2&quot;]" readonly class="acf-input-text" onclick="this.select();" />
                                    <button type="button" class="acf-button acf-button-small acf-copy-shortcode" data-shortcode="[authdocs_grid restriction=&quot;restricted&quot; limit=&quot;8&quot; columns=&quot;2&quot;]">
                                        <span class="dashicons dashicons-admin-page"></span>
                                        <?php _e('Copy', 'protecteddocs'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="acf-shortcode-example">
                                <label class="acf-shortcode-label"><?php _e('Unrestricted Documents Only:', 'protecteddocs'); ?></label>
                                <div class="acf-input-wrap">
                                    <input type="text" value="[authdocs_grid restriction=&quot;unrestricted&quot; limit=&quot;6&quot; columns=&quot;4&quot;]" readonly class="acf-input-text" onclick="this.select();" />
                                    <button type="button" class="acf-button acf-button-small acf-copy-shortcode" data-shortcode="[authdocs_grid restriction=&quot;unrestricted&quot; limit=&quot;6&quot; columns=&quot;4&quot;]">
                                        <span class="dashicons dashicons-admin-page"></span>
                                        <?php _e('Copy', 'protecteddocs'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Publish Notice -->
                <div class="acf-field acf-field-message">
                    <div class="acf-label">
                        <label><?php _e('Shortcode Generation', 'protecteddocs'); ?></label>
                    </div>
                    <div class="acf-input">
                        <div class="acf-notice acf-notice-info">
                            <p><?php _e('Publish the document to generate the shortcode.', 'protecteddocs'); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Ensure shortcode meta box is always first and non-draggable
            function fixShortcodePosition() {
                const $shortcodeBox = $('#authdocs_shortcode_display');
                const $normalBoxes = $('#normal-sortables');
                
                if ($shortcodeBox.length && $normalBoxes.length) {
                    // Move shortcode box to the top
                    $normalBoxes.prepend($shortcodeBox);
                    
                    // Remove drag functionality
                    $shortcodeBox.find('.postbox-header .handle').off('mousedown');
                    $shortcodeBox.find('.postbox-header .handle').css('cursor', 'default');
                    
                    // Hide drag handles
                    $shortcodeBox.find('.handle-order-higher, .handle-order-lower').hide();
                }
            }
            
            // Fix position on page load
            fixShortcodePosition();
            
            // Fix position after any meta box sorting
            $(document).on('sortstop', '#normal-sortables', function() {
                setTimeout(fixShortcodePosition, 100);
            });
            
            // Handle copy shortcode buttons
            $(document).on('click', '.acf-copy-shortcode', function(e) {
                e.preventDefault();
                const shortcode = $(this).data('shortcode');
                const $input = $(this).siblings('.acf-input-text');
                
                // Copy to clipboard
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(shortcode).then(function() {
                        showCopySuccess($(e.target).closest('.acf-copy-shortcode'));
                    });
                } else {
                    // Fallback for older browsers
                    $input.select();
                    document.execCommand('copy');
                    showCopySuccess($(e.target).closest('.acf-copy-shortcode'));
                }
            });
            
            function showCopySuccess($btn) {
                const originalText = $btn.html();
                $btn.html('<span class="dashicons dashicons-yes-alt"></span> <?php _e('Copied!', 'protecteddocs'); ?>');
                $btn.addClass('acf-button-success');
                
                setTimeout(function() {
                    $btn.html(originalText);
                    $btn.removeClass('acf-button-success');
                }, 2000);
            }
        });
        </script>
        <?php
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
            // Simple success message without detailed shortcode information
            ?>
            <div class="notice notice-success">
                <p><?php _e('Document published successfully!', 'protecteddocs'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Add custom columns to document list view
     */
    public function add_document_columns(array $columns): array
    {
        // Create custom column order: ID → Featured Image → Title → Document File → Date
        $new_columns = [];
        
        // Add ID column first (if it exists)
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        
        // Add ID column
        $new_columns['id'] = __('ID', 'protecteddocs');
        
        // Add Featured Image column
        $new_columns['featured_image'] = __('Featured Image', 'protecteddocs');
        
        // Add Title column
        if (isset($columns['title'])) {
            $new_columns['title'] = $columns['title'];
        }
        
        // Add Document File column (renamed from pdf_file for clarity)
        $new_columns['pdf_file'] = __('Document File', 'protecteddocs');
        
        // Add Document Status column
        $new_columns['document_status'] = __('Document Status', 'protecteddocs');
        
        // Add Date column
        if (isset($columns['date'])) {
            $new_columns['date'] = $columns['date'];
        }
        
        // Add any remaining columns (like status, author, etc.)
        foreach ($columns as $key => $value) {
            if (!isset($new_columns[$key]) && !in_array($key, ['cb', 'title', 'date'])) {
                $new_columns[$key] = $value;
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
            case 'id':
                echo '<strong>' . esc_html($post_id) . '</strong>';
                break;

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
                        echo '<span style="color: #999;">' . __('No file attached', 'protecteddocs') . '</span>';
                    }
                } else {
                    echo '<span style="color: #999;">' . __('No file attached', 'protecteddocs') . '</span>';
                }
                break;

            case 'document_status':
                $restricted = get_post_meta($post_id, '_authdocs_restricted', true);
                if ($restricted === 'yes') {
                    echo '<span class="authdocs-status-locked" style="display: inline-flex; align-items: center; color: #d63638; font-weight: 500;">';
                    echo '<span class="dashicons dashicons-lock" style="margin-right: 5px; font-size: 16px;"></span>';
                    echo __('Locked', 'protecteddocs');
                    echo '</span>';
                } else {
                    echo '<span class="authdocs-status-unlocked" style="display: inline-flex; align-items: center; color: #00a32a; font-weight: 500;">';
                    echo '<span class="dashicons dashicons-unlock" style="margin-right: 5px; font-size: 16px;"></span>';
                    echo __('Unlocked', 'protecteddocs');
                    echo '</span>';
                }
                break;
        }
    }
}
