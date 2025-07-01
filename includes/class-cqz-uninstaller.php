<?php
namespace CustomQuiz;

if (!defined('ABSPATH')) {
    exit;
}

class CQZ_Uninstaller {
    
    public static function uninstall() {
        // Only run if user has proper permissions
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Get settings to check if we should clean up data
        $settings = get_option('cqz_settings', array());
        $cleanup_data = isset($settings['cleanup_on_uninstall']) ? $settings['cleanup_on_uninstall'] : false;
        
        if ($cleanup_data) {
            self::remove_database_tables();
            self::remove_post_types_and_taxonomies();
            self::remove_options();
            self::remove_user_meta();
        } else {
            // Only remove plugin-specific options, keep data
            self::remove_plugin_options();
        }
    }
    
    private static function remove_database_tables() {
        global $wpdb;
        
        $tables = array(
            get_option('cqz_table_assignments'),
            get_option('cqz_table_assignment_questions'),
            get_option('cqz_table_results'),
            get_option('cqz_table_categories')
        );
        
        foreach ($tables as $table) {
            if ($table && $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
                $wpdb->query("DROP TABLE IF EXISTS $table");
            }
        }
    }
    
    private static function remove_post_types_and_taxonomies() {
        // Remove all quiz questions
        $questions = get_posts(array(
            'post_type' => 'quiz_question',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($questions as $question) {
            wp_delete_post($question->ID, true);
        }
        
        // Remove quiz categories
        $categories = get_terms(array(
            'taxonomy' => 'quiz_category',
            'hide_empty' => false
        ));
        
        foreach ($categories as $category) {
            wp_delete_term($category->term_id, 'quiz_category');
        }
    }
    
    private static function remove_options() {
        $options = array(
            'cqz_settings',
            'cqz_version',
            'cqz_activated',
            'cqz_table_assignments',
            'cqz_table_assignment_questions',
            'cqz_table_results',
            'cqz_table_categories',
            'cqz_db_version'
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
    }
    
    private static function remove_plugin_options() {
        $options = array(
            'cqz_version',
            'cqz_activated',
            'cqz_table_assignments',
            'cqz_table_assignment_questions',
            'cqz_table_results',
            'cqz_table_categories',
            'cqz_db_version'
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
    }
    
    private static function remove_user_meta() {
        global $wpdb;
        
        $meta_keys = array(
            '_cqz_quiz_completed',
            '_cqz_quiz_in_progress',
            '_cqz_quiz_results'
        );
        
        foreach ($meta_keys as $meta_key) {
            $wpdb->delete($wpdb->usermeta, array('meta_key' => $meta_key));
        }
    }
} 