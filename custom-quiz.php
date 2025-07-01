<?php
/*
Plugin Name: Custom Quiz System
Description: A comprehensive quiz system with Excel import, proportional distribution, timed quiz, auto-submission & detailed result reporting.
Version: 2.0
Author: Your coder6319
Text Domain: custom-quiz
Domain Path: /languages
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('CQZ_VERSION', rand());
define('CQZ_PLUGIN_FILE', __FILE__);
define('CQZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CQZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CQZ_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once CQZ_PLUGIN_DIR . 'includes/class-cqz-loader.php';
require_once CQZ_PLUGIN_DIR . 'includes/class-cqz-post-types.php';
require_once CQZ_PLUGIN_DIR . 'includes/class-cqz-admin.php';
require_once CQZ_PLUGIN_DIR . 'includes/class-cqz-frontend.php';
require_once CQZ_PLUGIN_DIR . 'includes/class-cqz-results.php';
require_once CQZ_PLUGIN_DIR . 'includes/class-cqz-import.php';
require_once CQZ_PLUGIN_DIR . 'includes/class-cqz-settings.php';
require_once CQZ_PLUGIN_DIR . 'includes/class-cqz-user-assignment.php';
require_once CQZ_PLUGIN_DIR . 'includes/class-cqz-results-list-table.php';

// Initialize the plugin
function cqz_init() {
    // Initialize main plugin class
    $plugin = new CustomQuiz\CQZ_Loader();
    $plugin->init();
}
add_action('plugins_loaded', 'cqz_init');

// Activation hook
register_activation_hook(__FILE__, 'cqz_activate');
function cqz_activate() {
    // Create database tables if needed
    require_once CQZ_PLUGIN_DIR . 'includes/class-cqz-activator.php';
    CustomQuiz\CQZ_Activator::activate();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'cqz_deactivate');
function cqz_deactivate() {
    // Clear scheduled cron jobs
    wp_clear_scheduled_hook('cqz_auto_submit_quiz');
    flush_rewrite_rules();
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'cqz_uninstall');
function cqz_uninstall() {
    // Clean up data if needed
    require_once CQZ_PLUGIN_DIR . 'includes/class-cqz-uninstaller.php';
    CustomQuiz\CQZ_Uninstaller::uninstall();
}

// Add custom cron interval
add_filter('cron_schedules', 'cqz_add_cron_interval');
function cqz_add_cron_interval($schedules) {
    $schedules['every_5_minutes'] = array(
        'interval' => 300,
        'display'  => __('Every 5 Minutes', 'custom-quiz')
    );
    return $schedules;
} 