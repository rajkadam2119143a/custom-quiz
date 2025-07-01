<?php
namespace CustomQuiz;

if (!defined('ABSPATH')) {
    exit;
}

class CQZ_Activator {
    
    public static function activate() {
        self::create_database_tables();
        self::create_default_categories();
        self::set_default_options();
    }
    
    private static function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Quiz assignments table
        $table_assignments = $wpdb->prefix . 'cqz_assignments';
        $sql_assignments = "CREATE TABLE $table_assignments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            assigned_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            is_completed tinyint(1) DEFAULT 0,
            completed_at datetime NULL,
            total_questions int(11) DEFAULT 0,
            score int(11) DEFAULT 0,
            total_points int(11) DEFAULT 0,
            percentage decimal(5,2) DEFAULT 0.00,
            time_taken int(11) DEFAULT 0,
            ip_address varchar(45) DEFAULT '',
            user_agent text,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY assigned_at (assigned_at),
            KEY expires_at (expires_at),
            KEY is_completed (is_completed)
        ) $charset_collate;";
        
        // Quiz assignment questions table
        $table_assignment_questions = $wpdb->prefix . 'cqz_assignment_questions';
        $sql_assignment_questions = "CREATE TABLE $table_assignment_questions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            assignment_id bigint(20) NOT NULL,
            question_id bigint(20) NOT NULL,
            category_id bigint(20) NOT NULL,
            selected_answer text,
            is_correct tinyint(1) DEFAULT 0,
            points_earned int(11) DEFAULT 0,
            points_possible int(11) DEFAULT 0,
            answered_at datetime NULL,
            PRIMARY KEY (id),
            KEY assignment_id (assignment_id),
            KEY question_id (question_id),
            KEY category_id (category_id)
        ) $charset_collate;";
        
        // Quiz results table for detailed tracking
        $table_results = $wpdb->prefix . 'cqz_results';
        $sql_results = "CREATE TABLE $table_results (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            assignment_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            user_name varchar(255) NOT NULL,
            user_email varchar(255) NOT NULL,
            score int(11) NOT NULL,
            total_points int(11) NOT NULL,
            percentage decimal(5,2) NOT NULL,
            total_questions int(11) NOT NULL,
            correct_answers int(11) NOT NULL,
            time_taken int(11) NOT NULL,
            start_time datetime NOT NULL,
            end_time datetime NOT NULL,
            ip_address varchar(45) DEFAULT '',
            user_agent text,
            category_breakdown text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY assignment_id (assignment_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Quiz categories table for better management
        $table_categories = $wpdb->prefix . 'cqz_categories';
        $sql_categories = "CREATE TABLE $table_categories (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            question_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_assignments);
        dbDelta($sql_assignment_questions);
        dbDelta($sql_results);
        dbDelta($sql_categories);
        
        // Store table names in options for easy access
        update_option('cqz_table_assignments', $table_assignments);
        update_option('cqz_table_assignment_questions', $table_assignment_questions);
        update_option('cqz_table_results', $table_results);
        update_option('cqz_table_categories', $table_categories);
    }
    
    private static function create_default_categories() {
        $default_categories = array(
            'Basic Product Knowledge : Languages',
            'Basic Product Knowledge : English',
            'Basic Product Knowledge : Digital',
            'About Content / Initiatives : Languages',
            'About Content / Initiatives : English',
            'IRS Based Questions',
            'Media Concepts',
            'Objection Handling / Media Strategy / Upsell',
            'Others'
        );
        
        foreach ($default_categories as $category_name) {
            $term = term_exists($category_name, 'quiz_category');
            if (!$term) {
                wp_insert_term($category_name, 'quiz_category');
            }
        }
    }
    
    private static function set_default_options() {
        $default_settings = array(
            'time_limit' => 120, // 2 hours in minutes
            'questions_per_quiz' => 40,
            'questions_per_category' => 5,
            'show_results' => 'immediate',
            'allow_retake' => false,
            'require_login' => true,
            'auto_submit' => true,
            'show_timer' => true,
            'show_progress' => true,
            'randomize_questions' => true,
            'proportional_distribution' => true,
            'email_results' => false,
            'export_results' => true,
            'leaderboard_enabled' => false,
            'max_attempts' => 1,
            'quiz_title' => 'Comprehensive Assessment Quiz',
            'quiz_description' => 'Test your knowledge across multiple categories with our comprehensive assessment.',
            'welcome_message' => 'Welcome to your assessment! This quiz contains 40 randomly selected questions with a 2-hour time limit.',
            'completion_message' => 'Thank you for completing the assessment! Your results will be displayed below.',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        update_option('cqz_settings', $default_settings);
        update_option('cqz_version', CQZ_VERSION);
        update_option('cqz_activated', current_time('mysql'));
    }
} 