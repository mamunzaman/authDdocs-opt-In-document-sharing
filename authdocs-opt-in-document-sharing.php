<?php
declare(strict_types=1);

/**
 * Plugin Name: ProtectedDocs Suite
 * Plugin URI: https://mamunzaman.itconsultingfirma.com/
 * Description: A comprehensive WordPress plugin for secure document sharing with advanced access control, Gutenberg blocks, responsive design, and bot protection.
 * Version: 1.5.0
 * Author: Mamun
 * Author URI: https://mamunzaman.itconsultingfirma.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: protecteddocs
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * 
 * @package ProtectedDocs
 * @author Mamun
 * @license GPL-2.0+
 * @link https://mamunzaman.itconsultingfirma.com/
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PROTECTEDDOCS_VERSION', '1.5.0');
define('PROTECTEDDOCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PROTECTEDDOCS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PROTECTEDDOCS_PLUGIN_FILE', __FILE__);

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'ProtectedDocs\\';
    $base_dir = PROTECTEDDOCS_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
add_action('plugins_loaded', function () {
    if (class_exists('ProtectedDocs\\Plugin')) {
        new ProtectedDocs\Plugin();
    }
});

// Activation hook
register_activation_hook(__FILE__, function () {
    ProtectedDocs\Database::create_tables();
    ProtectedDocs\Database::create_document_post_type();
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
