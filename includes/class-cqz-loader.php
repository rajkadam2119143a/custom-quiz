<?php
namespace CustomQuiz;

if (!defined('ABSPATH')) {
    exit;
}

class CQZ_Loader {
    
    private $post_types;
    private $admin;
    private $frontend;
    private $results;
    private $import;
    private $settings;
    private $user_assignment;
    
    public function init() {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
    
    private function load_dependencies() {
        $this->post_types = new CQZ_Post_Types();
        $this->admin = new CQZ_Admin();
        $this->frontend = new CQZ_Frontend();
        $this->results = new CQZ_Results();
        $this->import = new CQZ_Import();
        $this->settings = new CQZ_Settings();
        $this->user_assignment = new CQZ_User_Assignment();
    }
    
    private function set_locale() {
        load_plugin_textdomain('custom-quiz', false, dirname(CQZ_PLUGIN_BASENAME) . '/languages');
    }
    
    private function define_admin_hooks() {
        // Admin hooks
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_scripts'));
    }
    
    private function define_public_hooks() {
        // Public hooks
        add_action('wp_enqueue_scripts', array($this->frontend, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this->frontend, 'enqueue_scripts'));
        
        // User assignment AJAX handlers (primary quiz system) - only for logged-in users
        add_action('wp_ajax_cqz_start_assessment', array($this->user_assignment, 'start_assessment'));
        add_action('wp_ajax_cqz_get_assignment', array($this->user_assignment, 'get_assignment'));
        add_action('wp_ajax_cqz_save_answer', array($this->user_assignment, 'save_answer'));
        add_action('wp_ajax_cqz_submit_quiz', array($this->user_assignment, 'submit_quiz'));
        
        // Legacy results handler (fallback) - only for logged-in users
        add_action('wp_ajax_cqz_submit_quiz_legacy', array($this->results, 'handle_quiz_submission'));
    }
} 