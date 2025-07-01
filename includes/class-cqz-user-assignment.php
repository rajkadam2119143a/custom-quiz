<?php
namespace CustomQuiz;

if (!defined('ABSPATH')) {
    exit;
}

class CQZ_User_Assignment {
    
    private $wpdb;
    private $table_assignments;
    private $table_assignment_questions;
    private $table_results;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_assignments = get_option('cqz_table_assignments');
        $this->table_assignment_questions = get_option('cqz_table_assignment_questions');
        $this->table_results = get_option('cqz_table_results');
        
        // AJAX handlers for logged-in users only
        add_action('wp_ajax_cqz_start_assessment', array($this, 'start_assessment'));
        add_action('wp_ajax_cqz_get_assignment', array($this, 'get_assignment'));
        add_action('wp_ajax_cqz_save_answer', array($this, 'save_answer'));
        add_action('wp_ajax_cqz_submit_quiz', array($this, 'submit_quiz'));
        
        // Auto-submit cron job
        add_action('cqz_auto_submit_quiz', array($this, 'auto_submit_expired_quizzes'));
        add_action('init', array($this, 'schedule_auto_submit'));
    }
    
    public function schedule_auto_submit() {
        if (!wp_next_scheduled('cqz_auto_submit_quiz')) {
            wp_schedule_event(time(), 'every_5_minutes', 'cqz_auto_submit_quiz');
        }
    }
    
    public function start_assessment() {
        check_ajax_referer('cqz_start_assessment', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User must be logged in to take the quiz.');
        }
        
        // Debug information
        $current_user = wp_get_current_user();
        $debug_info = array(
            'user_id' => $user_id,
            'user_role' => implode(', ', $current_user->roles),
            'can_read' => current_user_can('read'),
            'can_read_posts' => current_user_can('read_post', 1),
        );
        
        // Check if user already has an active assignment
        $existing_assignment = $this->get_user_active_assignment($user_id);
        if ($existing_assignment) {
            wp_send_json_error('You already have an active quiz assignment.');
        }
        
        // Check if user has already completed a quiz
        $settings = get_option('cqz_settings', array());
        if (!$settings['allow_retake']) {
            $completed_assignment = $this->get_user_completed_assignment($user_id);
            if ($completed_assignment) {
                wp_send_json_error('You have already completed the quiz and retakes are not allowed.');
            }
        }
        
        // Create new assignment
        $assignment_id = $this->create_assignment($user_id);
        if (!$assignment_id) {
            wp_send_json_error('Failed to create quiz assignment.');
        }
        
        // Generate questions for the assignment
        $questions = $this->generate_questions_for_assignment($assignment_id);
        if (empty($questions)) {
            // Add debug info to error
            $debug_info['questions_found'] = 0;
            $debug_info['total_questions_in_db'] = wp_count_posts('quiz_question')->publish;
            $debug_info['categories_found'] = wp_count_terms('quiz_category');
            wp_send_json_error('No questions available for the quiz. Debug: ' . json_encode($debug_info));
        }
        
        // Return the quiz HTML
        $quiz_html = $this->render_quiz_interface($assignment_id, $questions);
        
        wp_send_json_success(array(
            'html' => $quiz_html,
            'assignment_id' => $assignment_id,
            'total_questions' => count($questions),
            'time_limit' => $settings['time_limit'] * 60, // Convert to seconds
            'debug' => $debug_info
        ));
		die;
    }
    
    public function get_assignment() {
        check_ajax_referer('cqz_frontend_nonce', 'nonce');
        
        $assignment_id = intval($_POST['assignment_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('User must be logged in.');
        }
        
        $assignment = $this->get_assignment_by_id($assignment_id, $user_id);
        if (!$assignment) {
            wp_send_json_error('Assignment not found or access denied.');
        }
        
        if ($assignment->is_completed) {
            wp_send_json_error('This assignment has already been completed.');
        }
        
        $questions = $this->get_assignment_questions($assignment_id);
        $quiz_html = $this->render_quiz_interface($assignment_id, $questions);
        
        wp_send_json_success(array(
            'html' => $quiz_html,
            'assignment_id' => $assignment_id,
            'total_questions' => count($questions),
            'time_limit' => $assignment->time_limit
        ));
    }
    
    public function save_answer() {
        check_ajax_referer('cqz_frontend_nonce', 'nonce');
        
        $assignment_id = intval($_POST['assignment_id']);
        $question_id = intval($_POST['question_id']);
        $answer = $_POST['answer'];
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('User must be logged in.');
        }
        
        $result = $this->save_question_answer($assignment_id, $question_id, $answer, $user_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Only return a success status, no need to expose points or correctness here.
        wp_send_json_success(array('saved' => true));
    }
    
    public function submit_quiz() {
        $start_time = microtime(true);
        error_log('Custom Quiz: submit_quiz started at ' . $start_time);
        check_ajax_referer('cqz_frontend_nonce', 'nonce');
        
        $assignment_id = intval($_POST['assignment_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('User must be logged in.');
        }
        
        // Verify assignment belongs to user and is not completed
        $assignment = $this->get_assignment_by_id($assignment_id, $user_id);
        if (!$assignment) {
            wp_send_json_error('Assignment not found or access denied.');
        }
        
        if ($assignment->is_completed) {
            wp_send_json_error('Assignment has already been completed.');
        }

        // Save all answers sent in the request (ensure all are saved before completion)
        $answers = isset($_POST['answers']) ? $_POST['answers'] : array();
        $text_answers = isset($_POST['text_answers']) && is_array($_POST['text_answers']) ? $_POST['text_answers'] : array();
        // Get all question IDs for this assignment
        $all_questions = $this->get_assignment_questions($assignment_id);
        foreach ($all_questions as $question) {
            $qid = $question->ID;
            if (array_key_exists($qid, $answers)) {
                $this->save_question_answer($assignment_id, $qid, $answers[$qid], $user_id);
            } elseif (array_key_exists($qid, $text_answers)) {
                $this->save_question_answer($assignment_id, $qid, $text_answers[$qid], $user_id);
            } else {
                // Mark as not attempted
                $this->save_question_answer($assignment_id, $qid, '', $user_id);
            }
        }
        $after_save_time = microtime(true);
        error_log('Custom Quiz: All answers saved at ' . $after_save_time . ' (elapsed: ' . ($after_save_time - $start_time) . 's)');
        
        // Complete the assignment
        $complete_result = $this->complete_assignment($assignment_id, false);
        $after_complete_time = microtime(true);
        error_log('Custom Quiz: Assignment completed at ' . $after_complete_time . ' (elapsed: ' . ($after_complete_time - $start_time) . 's)');
        if ($complete_result !== true) {
            $db_error = isset($GLOBALS['cqz_last_db_error']) ? $GLOBALS['cqz_last_db_error'] : 'Failed to save quiz results. Please try again.';
            wp_send_json_error($db_error);
        }
        
        // Get the completed assignment data
        $completed_assignment = $this->get_assignment_by_id($assignment_id, $user_id);
        
        // Render results
        $result_html = $this->render_assignment_results($assignment_id);
        $end_time = microtime(true);
        error_log('Custom Quiz: Results rendered at ' . $end_time . ' (total elapsed: ' . ($end_time - $start_time) . 's)');
        
        $settings = get_option('cqz_settings', array());
        $total_questions = $settings['questions_per_quiz'];

        
        wp_send_json_success(array(
            'html' => $result_html,
            'score' => $completed_assignment->score,
            'total' => $completed_assignment->total_questions ?? $total_questions,
            'percentage' => $completed_assignment->percentage,
        ));
    }
    
    private function create_assignment($user_id) {
        $settings = get_option('cqz_settings', array());
        $time_limit = $settings['time_limit'] * 60; // Convert to seconds
        
        $data = array(
            'user_id' => $user_id,
            'assigned_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', time() + $time_limit),
            'is_completed' => 0,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        $result = $this->wpdb->insert($this->table_assignments, $data);
        
        if ($result === false) {
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    private function generate_questions_for_assignment($assignment_id) {
        $settings = get_option('cqz_settings', array());
        $total_questions = $settings['questions_per_quiz'];
        
        // Get all categories with question counts
        $categories = $this->get_categories_with_question_counts();
        
        if (empty($categories)) {
            return array();
        }
        
        $selected_questions = array();
        
        if ($settings['proportional_distribution']) {
            // Proportional distribution based on available questions per category
            $total_available = array_sum(array_column($categories, 'question_count'));
            
            foreach ($categories as $category) {
                $category_ratio = $category['question_count'] / $total_available;
                $questions_for_category = max(1, round($total_questions * $category_ratio));
                
                $category_questions = $this->get_random_questions_by_category(
                    $category['term_id'], 
                    $questions_for_category
                );
                
                $selected_questions = array_merge($selected_questions, $category_questions);
            }
        } else {
            // Simple random selection
            $selected_questions = $this->get_random_questions($total_questions);
        }
        
        // Limit to the required number of questions
        $selected_questions = array_slice($selected_questions, 0, $total_questions);
        
        // Assign questions to the assignment
        foreach ($selected_questions as $question) {
            $this->assign_question_to_assignment($assignment_id, $question);
        }
        
        return $selected_questions;
    }
    
    private function get_categories_with_question_counts() {
        $categories = get_terms(array(
            'taxonomy' => 'quiz_category',
            'hide_empty' => false,
        ));
        
        $categories_with_counts = array();
        
        foreach ($categories as $category) {
            $question_count = $this->get_question_count_by_category($category->term_id);
            if ($question_count > 0) {
                $categories_with_counts[] = array(
                    'term_id' => $category->term_id,
                    'name' => $category->name,
                    'question_count' => $question_count
                );
            }
        }
        
        return $categories_with_counts;
    }
    
    private function get_question_count_by_category($category_id) {
        $posts = get_posts(array(
            'post_type' => 'quiz_question',
            'tax_query' => array(
                array(
                    'taxonomy' => 'quiz_category',
                    'field' => 'term_id',
                    'terms' => $category_id,
                ),
            ),
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        ));
        return count($posts);
    }
    
    private function get_random_questions_by_category($category_id, $limit) {
        return get_posts(array(
            'post_type' => 'quiz_question',
            'tax_query' => array(
                array(
                    'taxonomy' => 'quiz_category',
                    'field' => 'term_id',
                    'terms' => $category_id,
                ),
            ),
            'numberposts' => $limit,
            'orderby' => 'rand',
            'post_status' => 'publish',
        ));
    }
    
    private function get_random_questions($limit) {
        $questions = get_posts(array(
            'post_type' => 'quiz_question',
            'numberposts' => $limit,
            'orderby' => 'rand',
            'post_status' => 'publish',
        ));
        
        // Debug logging
        if (empty($questions)) {
            error_log('Custom Quiz Debug: No questions found in get_random_questions. Limit: ' . $limit);
            error_log('Custom Quiz Debug: Total questions in DB: ' . wp_count_posts('quiz_question')->publish);
            error_log('Custom Quiz Debug: User can read: ' . (current_user_can('read') ? 'Yes' : 'No'));
        }
        
        return $questions;
    }
    
    private function assign_question_to_assignment($assignment_id, $question) {
        $categories = get_the_terms($question->ID, 'quiz_category');
        $category_id = $categories && !is_wp_error($categories) ? $categories[0]->term_id : 0;
        
        $data = array(
            'assignment_id' => $assignment_id,
            'question_id' => $question->ID,
            'category_id' => $category_id,
            'points_possible' => get_post_meta($question->ID, '_cqz_points', true) ?: 1
        );
        
        return $this->wpdb->insert($this->table_assignment_questions, $data);
    }
    
    public function render_quiz_interface($assignment_id, $questions) {
        $settings = get_option('cqz_settings', array());
        // Fetch assignment expiration and calculate remaining time
        $assignment = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT expires_at FROM {$this->table_assignments} WHERE id = %d",
            $assignment_id
        ));
        $expires_at = $assignment ? strtotime($assignment->expires_at) : (time() + ($settings['time_limit'] * 60));
        $current_time = time();
        $remaining_time = max(0, $expires_at - $current_time);
        ob_start();
        ?>
        <div class="cqz-sticky-header">
            <div id="cqz-progress" class="cqz-progress cqz-progress-inline">
                <div class="cqz-progress-bar">
                    <div class="cqz-progress-fill"></div>
                </div>
                <span class="cqz-progress-text">0 / <?php echo count($questions); ?></span>
            </div>
            <div id="cqz-timer" class="cqz-timer" data-time-limit="<?php echo $remaining_time; ?>">
                <span class="cqz-timer-display"></span>
            </div>
        </div>
        <div id="cqz-quiz-container" class="cqz-quiz-container" data-assignment-id="<?php echo $assignment_id; ?>">
            <form id="cqz-quiz-form" class="cqz-quiz-form">
                <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                <input type="hidden" name="start_time" value="<?php echo time(); ?>">
                <div id="cqz-questions" class="cqz-questions">
                    <?php foreach (
                        $questions as $index => $question): ?>
                        <?php echo $this->render_single_question($question, $index + 1, $assignment_id); ?>
                    <?php endforeach; ?>
                </div>
                <div class="cqz-quiz-actions">
                    <!-- <button type="button" id="cqz-save-progress" class="cqz-btn cqz-btn-secondary">
                        Save Progress
                    </button> -->
                    <button type="submit" id="cqz-submit-quiz" class="cqz-btn cqz-btn-primary">
                        Submit Quiz
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_single_question($question, $question_number, $assignment_id) {
        $type = get_post_meta($question->ID, '_cqz_type', true);
        $choices_string = get_post_meta($question->ID, '_cqz_choices', true);
        $points = get_post_meta($question->ID, '_cqz_points', true) ?: 1;
        $categories = get_the_terms($question->ID, 'quiz_category');
        $category_name = $categories && !is_wp_error($categories) ? esc_html($categories[0]->name) : '';
        
        // Decode JSON for choices
        $choices = array();
        if ($choices_string && is_string($choices_string)) {
            $decoded = json_decode($choices_string, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $choices = $decoded;
            } else {
                $choices = preg_split('/\r\n|\r|\n|\\n/', $choices_string);
            }
        }

        // Fetch user's previous answer for this question (if any)
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT selected_answer FROM {$this->table_assignment_questions} WHERE assignment_id = %d AND question_id = %d",
            $assignment_id, $question->ID
        ));
        $user_answer = $row ? $row->selected_answer : null;
        if ($type === 'multiple' && $user_answer) {
            $user_answer = json_decode($user_answer, true);
        }

        $is_answered = ($user_answer !== null && $user_answer !== '' && $user_answer !== 'null' && $user_answer !== []);
        $status_text = $is_answered ? 'Answered' : 'Not answered';
        $status_data = $is_answered ? 'answered' : 'unanswered';
        
        ob_start();
        ?>
        <div class="cqz-question" data-question-id="<?php echo $question->ID; ?>" data-type="<?php echo esc_attr($type); ?>">
            <div class="cqz-question-header">
                <h3 class="cqz-question-title">
                    Question <?php echo $question_number; ?>
                    <span class="cqz-question-points">(<?php echo $points; ?> point)</span>
                </h3>
                <?php if ($category_name): ?>
                    <div class="cqz-question-category">
                        Category: <?php echo $category_name; ?>
                        <?php
                        $type_label = '';
                        if ($type === 'single') {
                            $type_label = 'Single Choice';
                        } elseif ($type === 'multiple') {
                            $type_label = 'Multiple Choice';
                        } elseif ($type === 'text') {
                            $type_label = 'Text';
                        }
                        if ($type_label) {
                            echo ' <span class="cqz-question-type-badge">' . esc_html($type_label) . '</span>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="cqz-question-content">
                <p class="cqz-question-text"><?php echo esc_html($question->post_title); ?></p>
                
                <?php if ($question->post_content): ?>
                    <div class="cqz-question-description">
                        <?php echo wp_kses_post($question->post_content); ?>
                    </div>
                <?php endif; ?>
                
                <div class="cqz-question-answers">
                    <?php if ($type === 'single' && $choices): ?>
                        <div class="cqz-radio-group">
                            <?php foreach ($choices as $choice): ?>
                                <?php $choice = trim($choice); ?>
                                <?php if (!empty($choice)): ?>
                                    <label class="cqz-radio-label">
                                        <input type="radio" name="cqz_answer[<?php echo $question->ID; ?>]" 
                                               value="<?php echo esc_attr($choice); ?>" class="cqz-radio-input" <?php echo ($user_answer == $choice) ? 'checked' : ''; ?> />
                                        <span class="cqz-radio-text"><?php echo esc_html($choice); ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($type === 'multiple' && $choices): ?>
                        <div class="cqz-checkbox-group">
                            <?php foreach ($choices as $choice): ?>
                                <?php $choice = trim($choice); ?>
                                <?php if (!empty($choice)): ?>
                                    <label class="cqz-checkbox-label">
                                        <input type="checkbox" name="cqz_answer[<?php echo $question->ID; ?>][]" 
                                               value="<?php echo esc_attr($choice); ?>" class="cqz-checkbox-input" <?php echo (is_array($user_answer) && in_array($choice, $user_answer)) ? 'checked' : ''; ?> />
                                        <span class="cqz-checkbox-text"><?php echo esc_html($choice); ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($type === 'text'): ?>
                        <div class="cqz-text-input">
                            <textarea name="cqz_text_answer[<?php echo $question->ID; ?>]" 
                                      placeholder="Enter your answer here..." 
                                      class="cqz-textarea"><?php echo ($user_answer && $type === 'text') ? esc_textarea($user_answer) : ''; ?></textarea>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="cqz-question-footer">
                <span class="cqz-question-status" data-status="<?php echo $status_data; ?>">
                    <?php echo $status_text; ?>
                </span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function get_user_active_assignment($user_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_assignments} 
             WHERE user_id = %d AND is_completed = 0 AND expires_at > NOW()",
            $user_id
        ));
    }
    
    public function get_user_completed_assignment($user_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_assignments} 
             WHERE user_id = %d AND is_completed = 1",
            $user_id
        ));
    }
    
    public function get_assignment_by_id($assignment_id, $user_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_assignments} 
             WHERE id = %d AND user_id = %d",
            $assignment_id, $user_id
        ));
    }
    
    public function get_assignment_questions($assignment_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT q.* FROM {$this->wpdb->posts} q
             INNER JOIN {$this->table_assignment_questions} aq ON q.ID = aq.question_id
             WHERE aq.assignment_id = %d AND q.post_status = 'publish'
             ORDER BY aq.id",
            $assignment_id
        ));
    }
    
    private function save_question_answer($assignment_id, $question_id, $answer, $user_id) {
        // Verify assignment belongs to user
        $assignment = $this->get_assignment_by_id($assignment_id, $user_id);
        if (!$assignment) {
            return new \WP_Error('invalid_assignment', 'Assignment not found or access denied.');
        }
        
        if ($assignment->is_completed) {
            return new \WP_Error('completed_assignment', 'Assignment has already been completed.');
        }
        
        // Get question details
        $question = get_post($question_id);
        if (!$question || $question->post_type !== 'quiz_question') {
            return new \WP_Error('invalid_question', 'Question not found.');
        }
        
        $type = get_post_meta($question_id, '_cqz_type', true);
        $correct_answer = get_post_meta($question_id, '_cqz_correct', true);
        $points = get_post_meta($question_id, '_cqz_points', true) ?: 1;

        // Check for empty answer and skip saving if empty
        $is_empty = false;
        if ($type === 'text') {
            $is_empty = (is_null($answer) || (is_string($answer) && trim($answer) === '') || (is_array($answer) && count($answer) === 0));
        } else if ($type === 'single') {
            $is_empty = (is_null($answer) || (is_string($answer) && trim($answer) === '') || (is_array($answer) && count($answer) === 0));
        } else if ($type === 'multiple') {
            $is_empty = (empty($answer) || (is_array($answer) && count(array_filter($answer, function($v){return is_string($v) ? trim($v) !== '' : !empty($v);})) === 0));
        }
        if ($is_empty) {
            // Do not save empty answers
            return array('skipped' => true);
        }
        
        // Check if answer is correct
        $is_correct = $this->check_answer($type, $answer, $correct_answer);
        $points_earned = $is_correct ? $points : 0;
        
        // Save answer
        $data = array(
            'selected_answer' => is_array($answer) ? json_encode($answer) : $answer,
            'is_correct' => $is_correct ? 1 : 0,
            'points_earned' => $points_earned,
            'answered_at' => current_time('mysql')
        );
        
        $result = $this->wpdb->update(
            $this->table_assignment_questions,
            $data,
            array(
                'assignment_id' => $assignment_id,
                'question_id' => $question_id
            )
        );
        
        if ($result === false) {
            return new \WP_Error('save_failed', 'Failed to save answer.');
        }
        
        return array(
            'is_correct' => $is_correct,
            'points_earned' => $points_earned
        );
    }
    
    private function check_answer($type, $user_answer, $correct_answer) {
        if (empty($user_answer) || empty($correct_answer)) {
            return false;
        }
        
        switch ($type) {
            case 'single':
                return trim($user_answer) === trim($correct_answer);
            
            case 'multiple':
                $user_array = is_array($user_answer) ? $user_answer : array($user_answer);
                $correct_array = array_map('trim', explode(',', $correct_answer));
                
                sort($user_array);
                sort($correct_array);
                return $user_array === $correct_array;
            
            case 'text':
                return strtolower(trim($user_answer)) === strtolower(trim($correct_answer));
            
            default:
                return false;
        }
    }
    
    public function auto_submit_expired_quizzes() {
        $expired_assignments = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_assignments} 
             WHERE is_completed = 0 AND expires_at <= NOW()"
        );
        
        foreach ($expired_assignments as $assignment) {
            $this->complete_assignment($assignment->id, true); // true = auto-submitted
        }
    }
    
    private function complete_assignment($assignment_id, $auto_submitted = false) {
        // Ensure assignment exists
        $assignment = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_assignments} WHERE id = %d",
            $assignment_id
        ));
        if (!$assignment) {
            error_log('Custom Quiz: Assignment not found in complete_assignment for ID ' . $assignment_id);
            return false;
        }
        // Calculate final score
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_assignment_questions} 
             WHERE assignment_id = %d",
            $assignment_id
        ));
        
        $total_score = 0;
        $total_points = 0;
        $correct_answers = 0;
        
        foreach ($results as $result) {
            $total_score += $result->points_earned;
            $total_points += $result->points_possible;
            if ($result->is_correct) {
                $correct_answers++;
            }
        }
        
        $percentage = $total_points > 0 ? round(($total_score / $total_points) * 100, 2) : 0;
        
        // Update assignment
        $update_result = $this->wpdb->update(
            $this->table_assignments,
            array(
                'is_completed' => 1,
                'completed_at' => current_time('mysql'),
                'score' => $total_score,
                'total_points' => $total_points,
                'percentage' => $percentage,
                'time_taken' => time() - strtotime($assignment->assigned_at)
            ),
            array('id' => $assignment_id)
        );
        
        // Save detailed result
        $save_result = $this->save_detailed_result($assignment_id, $total_score, $total_points, $percentage, $correct_answers, count($results));
        if ($save_result !== true) {
            error_log('Custom Quiz: Failed to save detailed result for assignment ' . $assignment_id);
            return false;
        }
        return true;
    }
    
    private function save_detailed_result($assignment_id, $score, $total_points, $percentage, $correct_answers, $total_questions) {
        $assignment = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_assignments} WHERE id = %d",
            $assignment_id
        ));
        
        if (!$assignment) {
            error_log('Custom Quiz: Assignment not found for save_detailed_result. Assignment ID: ' . $assignment_id);
            $GLOBALS['cqz_last_db_error'] = 'Assignment not found for result save.';
            return false;
        }
        
        $user_data = get_userdata($assignment->user_id);
        
        $data = array(
            'assignment_id' => $assignment_id,
            'user_id' => $assignment->user_id,
            'user_name' => $user_data ? $user_data->display_name : 'Guest User',
            'user_email' => $user_data ? $user_data->user_email : '',
            'score' => $score,
            'total_points' => $total_points,
            'percentage' => $percentage,
            'total_questions' => $total_questions,
            'correct_answers' => $correct_answers,
            'time_taken' => $assignment->time_taken,
            'start_time' => $assignment->assigned_at,
            'end_time' => $assignment->completed_at,
            'ip_address' => $assignment->ip_address,
            'user_agent' => $assignment->user_agent,
            'category_breakdown' => $this->get_category_breakdown($assignment_id)
        );
        // Strict null check for NOT NULL columns
        $not_null_fields = ['assignment_id','user_id','user_name','user_email','score','total_points','percentage','total_questions','correct_answers','time_taken','start_time','end_time'];
        foreach ($not_null_fields as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                error_log('Custom Quiz: NULL or empty value for NOT NULL column ' . $field . ' in save_detailed_result. Data: ' . print_r($data, true));
                $GLOBALS['cqz_last_db_error'] = 'Result save failed: Required field ' . $field . ' is missing or empty.';
                return false;
            }
        }
        error_log('Custom Quiz: Attempting to insert result data: ' . print_r($data, true));
        $insert_result = $this->wpdb->insert($this->table_results, $data);
        if ($insert_result === false) {
            error_log('Custom Quiz: DB insert failed in save_detailed_result for assignment ' . $assignment_id . ' - ' . $this->wpdb->last_error);
            error_log('Custom Quiz: Data attempted to insert: ' . print_r($data, true));
            $GLOBALS['cqz_last_db_error'] = 'DB insert failed: ' . $this->wpdb->last_error;
            return false;
        }
        return true;
    }
    
    private function get_category_breakdown($assignment_id) {
        $breakdown = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT c.name as category_name, 
                    COUNT(aq.id) as total_questions,
                    SUM(aq.is_correct) as correct_answers,
                    SUM(aq.points_earned) as points_earned,
                    SUM(aq.points_possible) as total_points
             FROM {$this->table_assignment_questions} aq
             INNER JOIN {$this->wpdb->terms} c ON aq.category_id = c.term_id
             WHERE aq.assignment_id = %d
             GROUP BY aq.category_id",
            $assignment_id
        ));
        
        return json_encode($breakdown);
    }
    
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    public function render_assignment_results($assignment_id) {
        // Get assignment data
        $assignment = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_assignments} WHERE id = %d",
            $assignment_id
        ));
        
        if (!$assignment) {
            return '<div class="cqz-error">Assignment not found.</div>';
        }
        
        // Fetch result row for this assignment (for correct/total count)
        $result_row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT correct_answers, total_questions FROM {$this->table_results} WHERE assignment_id = %d ORDER BY id DESC LIMIT 1",
            $assignment_id
        ));
        $correct_answers = $result_row ? intval($result_row->correct_answers) : 0;
        $total_questions = $result_row ? intval($result_row->total_questions) : 0;
        
        // Fallback: If missing or zero, calculate live from assignment_questions
        if ($total_questions === 0) {
            $stats = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT COUNT(*) as total, SUM(is_correct) as correct FROM {$this->table_assignment_questions} WHERE assignment_id = %d",
                $assignment_id
            ));
            $total_questions = $stats ? intval($stats->total) : 0;
            $correct_answers = $stats && $stats->correct !== null ? intval($stats->correct) : 0;
        }
        
        // Get detailed results
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT aq.*, q.post_title as question_text, q.post_content as question_content,
                    c.name as category_name
             FROM {$this->table_assignment_questions} aq
             INNER JOIN {$this->wpdb->posts} q ON aq.question_id = q.ID
             LEFT JOIN {$this->wpdb->terms} c ON aq.category_id = c.term_id
             WHERE aq.assignment_id = %d
             ORDER BY aq.id",
            $assignment_id
        ));
        // Sanitize question text and content to remove replacement characters
        foreach ($results as $result) {
            $result->question_text = preg_replace('/[\xC2\xA0\xE2\x80\x8B\xEF\xBB\xBF]/u', '', $result->question_text);
            $result->question_content = preg_replace('/[\xC2\xA0\xE2\x80\x8B\xEF\xBB\xBF]/u', '', $result->question_content);
        }
        
        // Calculate category stats and attempted questions
        $category_stats = array();
        $attempted_questions = 0;
        foreach ($results as $result) {
            $cat_name = $result->category_name ?: 'Uncategorized';
            if (!isset($category_stats[$cat_name])) {
                $category_stats[$cat_name] = array('correct' => 0, 'total' => 0);
            }
            $category_stats[$cat_name]['total']++;
            if ($result->is_correct) {
                $category_stats[$cat_name]['correct']++;
            }
            // Count attempted questions
            $user_answer = $result->selected_answer;
            $user_ans = is_array($user_answer) ? $user_answer : [$user_answer];
            $user_provided = ($user_answer !== null && $user_answer !== '' && $user_answer !== 'null' && $user_answer !== [] && $user_ans[0] !== '');
            if ($user_provided) {
                $attempted_questions++;
            }
        }
        
        $score_pct = $assignment->percentage;
        $score_color = $score_pct >= 80 ? '#40c057' : ($score_pct >= 50 ? '#ffa500' : '#e74c3c');
        
        $not_attempted = $total_questions - $attempted_questions;
        
        ob_start();
        ?>
        <div class="cqz-results-modern cqz-results-redesign">
            <div class="cqz-user-card">
                <div class="cqz-user-avatar">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="cqz-user-info">
                    <div class="cqz-user-name"><?php echo esc_html(wp_get_current_user()->display_name ?? ''); ?></div>
                    <div class="cqz-user-email"><?php echo esc_html(wp_get_current_user()->user_email ?? ''); ?></div>
                </div>
<!--                 <div class="cqz-signout-btn"><a href="<?php echo esc_url(wp_logout_url()); ?>" class="cqz-btn cqz-btn-secondary">Sign Out</a></div> -->
            </div>
            <div class="cqz-results-score-modern cqz-score-redesign">
                <div class="cqz-trophy-icon"><span class="dashicons dashicons-awards"></span></div>
                <div class="cqz-score-main" style="color:<?php echo $score_color; ?>;"> <?php echo $correct_answers; ?>/<span><?php echo $total_questions; ?></span> </div>
                <div class="cqz-score-label">Assessment Completed</div>
                <div class="cqz-score-pct"> <?php echo $score_pct; ?>% Score Achieved</div>
                <div class="cqz-score-attempts">Correct: <b><?php echo $correct_answers; ?></b> &nbsp; Attempted: <b><?php echo $attempted_questions; ?></b> &nbsp; Not Attempted: <b><?php echo $not_attempted; ?></b> &nbsp; Total: <b><?php echo $total_questions; ?></b></div>
            </div>
            <div class="cqz-category-cards">
                <div class="cqz-category-cards-title">Performance by Category</div>
                <div class="cqz-category-cards-list cqz-category-cards-grid">
                <?php foreach ($category_stats as $cat => $stat): $pct = $stat['total'] ? round(($stat['correct']/$stat['total'])*100, 1) : 0; ?>
                    <div class="cqz-category-card">
                        <div class="cqz-category-card-title"><?php echo esc_html($cat); ?></div>
                        <div class="cqz-category-card-score"><?php echo $stat['correct']; ?>/<?php echo $stat['total']; ?></div>
                        <div class="cqz-category-card-bar-bg">
                            <div class="cqz-category-card-bar-fill" style="width:<?php echo $pct; ?>%;"></div>
                        </div>
                        <div class="cqz-category-card-pct"><?php echo $pct; ?>% Accuracy</div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <div class="cqz-results-detail-modern">
                <h3>Detailed Review</h3>
                <?php $qnum = 1; foreach ($results as $result): ?>
                    <?php
                    $user_answer = $result->selected_answer;
                    $user_ans = is_array($user_answer) ? $user_answer : [$user_answer];
                    $user_provided = ($user_answer !== null && $user_answer !== '' && $user_answer !== 'null' && $user_answer !== [] && $user_ans[0] !== '');
                    $is_correct = $result->is_correct;
                    $card_bg = !$user_provided ? '#f8f9fa' : ($is_correct ? '#e6f9ed' : '#ffeaea');
                    $border_color = !$user_provided ? '#e9ecef' : ($is_correct ? '#40c057' : '#e74c3c');
                    ?>
                    <div class="cqz-result-question-modern" style="background:<?php echo $card_bg; ?>;border:2px solid <?php echo $border_color; ?>;border-radius:14px;padding:1.5rem 1.5rem 1rem 1.5rem;margin-bottom:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                            <div style="font-weight:600;font-size:1rem;display:flex;align-items:center;gap:0.7rem;">
                                <span style="color:#888;font-weight:500;font-size:0.98rem;">Q<?php echo $qnum; ?></span>
                                <?php if ($result->category_name): ?>
                                    <span style="background:#f1f3f4;color:#228be6;font-size:0.95rem;padding:2px 10px;border-radius:8px;"> <?php echo esc_html($result->category_name); ?> </span>
                                <?php endif; ?>
                                <?php if (!$user_provided): ?>
                                    <span style="color:#e67e22;font-size:1.1rem;">&#9888;</span>
                                <?php elseif ($is_correct): ?>
                                    <span style="color:#40c057;font-size:1.2rem;">&#10004;</span>
                                <?php else: ?>
                                    <span style="color:#e74c3c;font-size:1.2rem;">&#10008;</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="font-size:1.13rem;font-weight:600;margin-bottom:0.7rem;color:#222;"> <?php echo esc_html($result->question_text); ?> </div>
                        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:0.5rem;">
                            <?php
                            $question = get_post($result->question_id);
                            $correct_answer = get_post_meta($result->question_id, '_cqz_correct', true);
                            $choices_string = get_post_meta($result->question_id, '_cqz_choices', true);
                            $type = get_post_meta($result->question_id, '_cqz_type', true);
                            $all_choices = array();
                            if ($choices_string && is_string($choices_string)) {
                                $decoded = json_decode($choices_string, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    $all_choices = $decoded;
                                } else {
                                    $all_choices = preg_split('/\r\n|\r|\n|\\n/', $choices_string);
                                }
                            }
                            $user_ans = is_array($user_answer) ? $user_answer : [$user_answer];
                            $correct_ans = is_array($correct_answer) ? $correct_answer : [$correct_answer];
                            $user_provided = ($user_answer !== null && $user_answer !== '' && $user_answer !== 'null' && $user_answer !== [] && $user_ans[0] !== '');
                            if ($type === 'single' || $type === 'multiple') {
                                echo '<div style="flex:1;min-width:180px;">';
                                echo '<div style="font-size:0.98rem;color:#888;margin-bottom:0.2rem;">Your Answer:</div>';
                                if ($user_provided) {
                                    $ua = array_map('trim', $user_ans);
                                    echo '<div style="background:#fff;border:1.5px solid #e74c3c;color:#e74c3c;padding:8px 16px;border-radius:8px;font-weight:600;">'.esc_html(implode(', ', $ua)).'</div>';
                                } else {
                                    echo '<div style="background:#fff;border:1.5px solid #e9ecef;color:#888;padding:8px 16px;border-radius:8px;font-weight:500;">Not Attempted</div>';
                                }
                                echo '</div>';
                                echo '<div style="flex:1;min-width:180px;">';
                                echo '<div style="font-size:0.98rem;color:#888;margin-bottom:0.2rem;">Correct Answer:</div>';
                                $ca = array_map('trim', $correct_ans);
                                echo '<div style="background:#fff;border:1.5px solid #40c057;color:#228b22;padding:8px 16px;border-radius:8px;font-weight:600;">'.esc_html(implode(', ', $ca)).'</div>';
                                echo '</div>';
                            } else if ($type === 'text') {
                                echo '<div style="flex:1;min-width:180px;">';
                                echo '<div style="font-size:0.98rem;color:#888;margin-bottom:0.2rem;">Your Answer:</div>';
                                if ($user_provided) {
                                    echo '<div style="background:#fff;border:1.5px solid #e74c3c;color:#e74c3c;padding:8px 16px;border-radius:8px;font-weight:600;">'.esc_html($user_ans[0]).'</div>';
                                } else {
                                    echo '<div style="background:#fff;border:1.5px solid #e9ecef;color:#888;padding:8px 16px;border-radius:8px;font-weight:500;">Not Attempted</div>';
                                }
                                echo '</div>';
                                echo '<div style="flex:1;min-width:180px;">';
                                echo '<div style="font-size:0.98rem;color:#888;margin-bottom:0.2rem;">Correct Answer:</div>';
                                echo '<div style="background:#fff;border:1.5px solid #40c057;color:#228b22;padding:8px 16px;border-radius:8px;font-weight:600;">'.esc_html($correct_ans[0]).'</div>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <?php 
                        $explanation = get_post_meta($result->question_id, '_cqz_explanation', true);
                        if (!empty($explanation)): 
                        ?>
                        <div class="cqz-explanation-modern"><strong>Explanation:</strong> <?php echo esc_html($explanation); ?></div>
                        <?php endif; ?>
                    </div>
                <?php $qnum++; endforeach; ?>
            </div>
            <div class="cqz-results-actions-modern">
                <button type="button" class="cqz-btn cqz-btn-secondary" onclick="window.print()">Print Results</button>
                <?php 
                $settings = get_option('cqz_settings', array());
                if (!empty($settings['allow_retake'])): ?>
                <button type="button" id="cqz-take-new-quiz" class="cqz-btn cqz-btn-primary">
                    <?php _e('Take New Quiz', 'custom-quiz'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
} 