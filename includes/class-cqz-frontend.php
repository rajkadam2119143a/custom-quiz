<?php
namespace CustomQuiz;

if (!defined('ABSPATH')) {
    exit;
}

class CQZ_Frontend {
    
    public function __construct() {
        add_shortcode('quiz_assessment', array($this, 'quiz_assessment_shortcode'));
        add_shortcode('quiz_test', array($this, 'quiz_test_shortcode'));
        add_action('wp_ajax_cqz_get_nonce', array($this, 'ajax_get_nonce'));
    }
    
    public function enqueue_styles() {
        wp_enqueue_style('cqz-frontend', CQZ_PLUGIN_URL . 'assets/css/frontend.css', array(), CQZ_VERSION);
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('cqz-frontend', CQZ_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), CQZ_VERSION, true);
        wp_localize_script('cqz-frontend', 'cqz_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cqz_frontend_nonce'),
            'start_assessment_nonce' => wp_create_nonce('cqz_start_assessment'),
            'strings' => array(
                'time_up' => __('Time is up! Submitting quiz...', 'custom-quiz'),
                'confirm_submit' => __('Are you sure you want to submit the quiz?', 'custom-quiz'),
                'confirm_leave' => __('You have unsaved answers. Are you sure you want to leave?', 'custom-quiz'),
                'saving_progress' => __('Saving progress...', 'custom-quiz'),
                'progress_saved' => __('Progress saved successfully!', 'custom-quiz'),
                'starting_assessment' => __('Starting assessment...', 'custom-quiz'),
                'assessment_started' => __('Assessment started successfully!', 'custom-quiz'),
                'error_occurred' => __('An error occurred. Please try again.', 'custom-quiz'),
            ),
        ));
    }
    
    public function quiz_assessment_shortcode($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_login_required_message();
        }
        
        // Check for URL parameters first
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;
        
        // Check for active or completed assignment
        $user_id = get_current_user_id();
        $user_assignment = null;
        $user_assignment_class = class_exists('CustomQuiz\CQZ_User_Assignment') ? new \CustomQuiz\CQZ_User_Assignment() : null;
        
        if ($user_assignment_class && $user_id) {
            $active_assignment = (new \CustomQuiz\CQZ_User_Assignment())->get_user_active_assignment($user_id);
            $completed_assignment = (new \CustomQuiz\CQZ_User_Assignment())->get_user_completed_assignment($user_id);
            
            // Handle URL parameters
            if ($action === 'quizstart' && $assignment_id) {
                // Check if this assignment belongs to the user and is active
                $assignment = $user_assignment_class->get_assignment_by_id($assignment_id, $user_id);
                if ($assignment && !$assignment->is_completed) {
                    // Render quiz interface for active assignment
                    $questions = $user_assignment_class->get_assignment_questions($assignment_id);
                    return $user_assignment_class->render_quiz_interface($assignment_id, $questions);
                }
            } elseif ($action === 'quiz_result' && $assignment_id) {
                // Check if this assignment belongs to the user and is completed
                $assignment = $user_assignment_class->get_assignment_by_id($assignment_id, $user_id);
                if ($assignment && $assignment->is_completed) {
                    // Render results
                    return $user_assignment_class->render_assignment_results($assignment_id);
                } else {
                    // If assignment is not completed or not found, show error
                    return '<div class="cqz-error">Quiz result not available for this assignment.</div>';
                }
            }
            
            // Fallback to normal flow
            if ($active_assignment) {
                // Render quiz interface for active assignment
                $questions = $user_assignment_class->get_assignment_questions($active_assignment->id);
                return $user_assignment_class->render_quiz_interface($active_assignment->id, $questions);
            } elseif ($completed_assignment) {
                $settings = get_option('cqz_settings', array());
                if (!empty($settings['allow_retake'])) {
                    // Only show welcome/start screen if NOT viewing a specific quiz_result
                    if ($action !== 'quiz_result') {
                        // Fall through to welcome/start screen below
                    } else {
                        // If quiz_result is requested, show results for the completed assignment
                        return $user_assignment_class->render_assignment_results($completed_assignment->id);
                    }
                } else {
                    // Render results for the completed assignment
                    return $user_assignment_class->render_assignment_results($completed_assignment->id);
                }
            }
        }
        
        // Default: render welcome/start screen
        return $this->render_welcome_screen();
    }
    
    private function render_login_required_message() {
        ob_start();
        ?>
        <div class="cqz-assessment-landing">
            <div class="cqz-assessment-header">
                <img src="https://img.icons8.com/ios-filled/50/228be6/brain.png" alt="Quiz" class="cqz-assessment-logo">
                <div class="cqz-assessment-title-block">
                    <h2 class="cqz-assessment-title">Quiz Assessment Platform</h2>
                </div>
            </div>
            <div class="cqz-assessment-welcome">
                <div class="cqz-assessment-welcome-icon">🔒</div>
                <h1 class="cqz-assessment-welcome-title">Login Required</h1>
                <div class="cqz-assessment-welcome-message">
                    You must be logged in to access the quiz assessment. Please log in to your account to continue.
                </div>
                <div class="cqz-assessment-login-actions">
                    <a href="<?php echo wp_login_url(get_permalink()); ?>" class="cqz-btn cqz-btn-primary">Login to Continue</a>
                    <?php if (get_option('users_can_register')): ?>
                        <a href="<?php echo wp_registration_url(); ?>" class="cqz-btn cqz-btn-secondary">Create Account</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_welcome_screen() {
        $settings = get_option('cqz_settings', array());
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $user_name = esc_html($current_user->display_name);
            $user_email = esc_html($current_user->user_email);
        } else {
            $user_name = 'Guest';
            $user_email = '';
        }
        
        $question_count = $settings['questions_per_quiz'] ?? 40;
        $time_limit = $settings['time_limit'] ?? 120; // minutes
        $time_limit_hours = $time_limit / 60;
        $question_types = [
            ['label' => 'Single Choice', 'color' => 'blue'],
            ['label' => 'Multiple Choice', 'color' => 'green'],
            ['label' => 'True/False', 'color' => 'indigo'],
        ];
        ob_start();
        ?>
        <div class="cqz-assessment-landing">
            <div class="cqz-assessment-header">
                <img src="https://img.icons8.com/ios-filled/50/228be6/brain.png" alt="Quiz" class="cqz-assessment-logo">
                <div class="cqz-assessment-title-block">
                    <h2 class="cqz-assessment-title"><?php echo esc_html($settings['quiz_title'] ?? 'Quiz Assessment Platform'); ?></h2>
                    <div class="cqz-assessment-email"><?php echo $user_email; ?></div>
                </div>
                <div class="cqz-assessment-username"> <?php echo $user_name; ?> </div>
            </div>
            <div class="cqz-assessment-welcome">
                <div class="cqz-assessment-welcome-icon">📝</div>
                <h1 class="cqz-assessment-welcome-title">Welcome to Your Assessment</h1>
                <div class="cqz-assessment-welcome-message">
                    <?php echo esc_html($settings['welcome_message'] ?? "You are about to begin a comprehensive quiz covering multiple categories. The assessment consists of {$question_count} randomly selected questions with a {$time_limit_hours}-hour time limit."); ?>
                </div>
            </div>
            <div class="cqz-assessment-info-row">
                <div class="cqz-assessment-info cqz-info-questions">
                    <div class="cqz-info-icon">📝</div>
                    <div class="cqz-info-title"> <?php echo $question_count; ?> Questions </div>
                    <div class="cqz-info-desc">Randomly selected from database</div>
                </div>
                <div class="cqz-assessment-info cqz-info-timer">
                    <div class="cqz-info-icon">⏰</div>
                    <div class="cqz-info-title"> <?php echo $time_limit_hours; ?> Hours </div>
                    <div class="cqz-info-desc">Auto-submit when time expires</div>
                </div>
                <div class="cqz-assessment-info cqz-info-results">
                    <div class="cqz-info-icon">📊</div>
                    <div class="cqz-info-title">Detailed Results</div>
                    <div class="cqz-info-desc">Category-wise performance analysis</div>
                </div>
            </div>
            <div class="cqz-assessment-types">
                <div class="cqz-types-title">✔️ Question Types You'll Encounter</div>
                <div class="cqz-types-list">
                    <?php foreach ($question_types as $qt): ?>
                        <div class="cqz-type-item">
                            <span class="cqz-type-dot" style="background:<?php echo $qt['color']; ?>;"></span>
                            <span><?php echo $qt['label']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="cqz-assessment-start">
                <button id="cqz-start-assessment" class="cqz-btn cqz-btn-primary">Start Quiz Assessment 📝</button>
                <div class="cqz-assessment-start-desc">Click to generate your <?php echo $question_count; ?> random questions</div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function start_assessment() {
        if (!isset($_POST['nonce'])) {
            error_log('Custom Quiz: Nonce missing in start_assessment');
            wp_send_json_error('Security check failed (nonce missing).');
        }
        if (!check_ajax_referer('cqz_start_assessment', 'nonce', false)) {
            error_log('Custom Quiz: Nonce failed in start_assessment. Nonce sent: ' . $_POST['nonce']);
            wp_send_json_error('Security check failed (invalid nonce).');
        }
        // ... rest of your code ...
    }
    
    /**
     * Test function to diagnose quiz system issues
     * Add this shortcode: [quiz_test]
     */
    public function quiz_test_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div style="background:#ffe6e6;padding:15px;border:1px solid #e74c3c;border-radius:5px;">
                <strong>Test Result:</strong> User not logged in
            </div>';
        }
        
        $current_user = wp_get_current_user();
        $test_results = array();
        
        // Test 1: User capabilities
        $test_results[] = array(
            'test' => 'User Login',
            'result' => 'PASS',
            'details' => 'User ID: ' . $current_user->ID . ', Role: ' . implode(', ', $current_user->roles)
        );
        
        // Test 2: Read capability
        $can_read = current_user_can('read');
        $test_results[] = array(
            'test' => 'Read Capability',
            'result' => $can_read ? 'PASS' : 'FAIL',
            'details' => $can_read ? 'User can read content' : 'User cannot read content'
        );
        
        // Test 3: Quiz questions access
        $questions_count = wp_count_posts('quiz_question')->publish;
        $test_results[] = array(
            'test' => 'Quiz Questions Count',
            'result' => $questions_count > 0 ? 'PASS' : 'FAIL',
            'details' => 'Found ' . $questions_count . ' published questions'
        );
        
        // Test 4: Categories access
        $categories_count = wp_count_terms('quiz_category');
        $test_results[] = array(
            'test' => 'Quiz Categories Count',
            'result' => $categories_count > 0 ? 'PASS' : 'FAIL',
            'details' => 'Found ' . $categories_count . ' categories'
        );
        
        // Test 5: Get actual questions
        $sample_questions = get_posts(array(
            'post_type' => 'quiz_question',
            'numberposts' => 3,
            'post_status' => 'publish'
        ));
        $test_results[] = array(
            'test' => 'Sample Questions Access',
            'result' => count($sample_questions) > 0 ? 'PASS' : 'FAIL',
            'details' => 'Can access ' . count($sample_questions) . ' sample questions'
        );
        
        // Test 6: Settings access
        $settings = get_option('cqz_settings', array());
        $test_results[] = array(
            'test' => 'Quiz Settings',
            'result' => !empty($settings) ? 'PASS' : 'FAIL',
            'details' => 'Settings found: ' . (empty($settings) ? 'No' : 'Yes')
        );
        
        // Render results
        ob_start();
        ?>
        <div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:20px 0;">
            <h3>Quiz System Diagnostic Test</h3>
            <p><strong>User:</strong> <?php echo esc_html($current_user->display_name); ?> (<?php echo esc_html(implode(', ', $current_user->roles)); ?>)</p>
            
            <?php foreach ($test_results as $test): ?>
                <div style="margin:10px 0;padding:10px;background:white;border-radius:5px;border-left:4px solid <?php echo $test['result'] === 'PASS' ? '#40c057' : '#e74c3c'; ?>;">
                    <strong><?php echo esc_html($test['test']); ?>:</strong> 
                    <span style="color:<?php echo $test['result'] === 'PASS' ? '#40c057' : '#e74c3c'; ?>;font-weight:bold;">
                        <?php echo $test['result']; ?>
                    </span>
                    <br>
                    <small><?php echo esc_html($test['details']); ?></small>
                </div>
            <?php endforeach; ?>
            
            <div style="margin-top:20px;padding:10px;background:#e3f2fd;border-radius:5px;">
                <strong>Recommendation:</strong>
                <?php
                $failed_tests = array_filter($test_results, function($test) {
                    return $test['result'] === 'FAIL';
                });
                
                if (empty($failed_tests)) {
                    echo 'All tests passed! The quiz system should work for this user.';
                } else {
                    echo 'Some tests failed. Check the failed tests above for issues.';
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_get_nonce() {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        wp_send_json_success(array(
            'start_assessment_nonce' => wp_create_nonce('cqz_start_assessment')
        ));
    }

    /**
     * Output a Sign Out button that logs the user out and redirects to the home page
     */
    public static function render_signout_button() {
        if (is_user_logged_in()) {
            echo '<a href="' . esc_url(wp_logout_url(home_url())) . '" class="cqz-signout-btn">Sign Out</a>';
        }
    }
}

function cqz_reset_quiz_session_for_user($user_id) {
    global $wpdb;
    $assignments = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}cqz_assignments WHERE user_id = %d",
        $user_id
    ));
    if ($assignments) {
        $in = implode(',', array_map('intval', $assignments));
        $wpdb->query("DELETE FROM {$wpdb->prefix}cqz_assignment_questions WHERE assignment_id IN ($in)");
        $wpdb->query("DELETE FROM {$wpdb->prefix}cqz_results WHERE assignment_id IN ($in)");
        $wpdb->query("DELETE FROM {$wpdb->prefix}cqz_assignments WHERE id IN ($in)");
    }
} 