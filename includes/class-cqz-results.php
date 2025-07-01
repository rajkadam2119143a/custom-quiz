<?php
namespace CustomQuiz;

if (!defined('ABSPATH')) {
    exit;
}

class CQZ_Results {
    
    public function __construct() {
        add_action('wp_ajax_cqz_submit_quiz', array($this, 'handle_quiz_submission'));
        add_action('wp_ajax_nopriv_cqz_submit_quiz', array($this, 'handle_quiz_submission'));
        add_action('wp_enqueue_scripts', function() {
            wp_enqueue_style('dashicons');
        });
    }
    
    public function handle_quiz_submission() {
        check_ajax_referer('cqz_frontend_nonce', 'nonce');
        
        $quiz_data = $this->process_quiz_submission();
        
        if (is_wp_error($quiz_data)) {
            wp_send_json_error($quiz_data->get_error_message());
        }
        // Check if result_id is valid (not false or 0)
        if (empty($quiz_data['result_id']) || $quiz_data['result_id'] === false) {
            error_log('Custom Quiz: Failed to save quiz result for user ' . get_current_user_id());
            wp_send_json_error(__('Failed to save your result. Please wait, your result is being saved. If this persists, contact support.', 'custom-quiz'));
        }
        $result_html = $this->render_results($quiz_data);
        wp_send_json_success(array(
            'html' => $result_html,
            'score' => $quiz_data['score'],
            'total' => $quiz_data['total'],
            'percentage' => $quiz_data['percentage'],
        ));
    }
    
    private function process_quiz_submission() {


        $answers = isset($_POST['answers']) ? $_POST['answers'] : array();
        $text_answers = isset($_POST['text_answers']) ? $_POST['text_answers'] : array();
        $start_time = isset($_POST['start_time']) ? intval($_POST['start_time']) : time();
        $end_time = time();
        
        if (empty($answers) && empty($text_answers)) {
            return new \WP_Error('no_answers', __('No answers provided', 'custom-quiz'));
        }
        
        $score = 0;
        $total_points = 0;
        $settings = get_option('cqz_settings', array());
        $total_questions = $settings['questions_per_quiz'];
        $results = array();
        
        // Process multiple choice and single choice answers
        foreach ($answers as $question_id => $user_answers) {
            $question = get_post($question_id);
            if (!$question || $question->post_type !== 'quiz_question') {
                continue;
            }
            
            $type = get_post_meta($question_id, '_cqz_type', true);
            $correct_answers = get_post_meta($question_id, '_cqz_correct', true);
            $points = get_post_meta($question_id, '_cqz_points', true) ?: 1;
            $explanation = get_post_meta($question_id, '_cqz_explanation', true);
            
            $correct_array = array_map('trim', explode(',', $correct_answers));
            $user_array = is_array($user_answers) ? array_map('trim', $user_answers) : array(trim($user_answers));
            
            $is_correct = $this->check_answer($type, $user_array, $correct_array);
            
            if ($is_correct) {
                $score += $points;
            }
            
            $total_points += $points;
            
            $results[$question_id] = array(
                'question' => $question->post_title,
                'type' => $type,
                'user_answer' => $user_array,
                'correct_answer' => $correct_array,
                'is_correct' => $is_correct,
                'points' => $points,
                'explanation' => $explanation,
            );
        }
        
        // Process text answers
        foreach ($text_answers as $question_id => $user_answer) {
            $question = get_post($question_id);
            if (!$question || $question->post_type !== 'quiz_question') {
                continue;
            }
            
            $correct_answer = get_post_meta($question_id, '_cqz_correct', true);
            $points = get_post_meta($question_id, '_cqz_points', true) ?: 1;
            $explanation = get_post_meta($question_id, '_cqz_explanation', true);
            
            $is_correct = $this->check_text_answer(trim($user_answer), trim($correct_answer));
            
            if ($is_correct) {
                $score += $points;
            }
            
            $total_points += $points;
            
            $results[$question_id] = array(
                'question' => $question->post_title,
                'type' => 'text',
                'user_answer' => array($user_answer),
                'correct_answer' => array($correct_answer),
                'is_correct' => $is_correct,
                'points' => $points,
                'explanation' => $explanation,
            );
        }
        
        $percentage = $total_points > 0 ? round(($score / $total_points) * 100, 2) : 0;
        
        // Save result to database
        $result_id = $this->save_quiz_result($score, $total_points, $percentage, $results, $start_time, $end_time);
        
        // Mark quiz as completed
        $this->mark_quiz_completed();
        
        return array(
            'result_id' => $result_id,
            'score' => $score,
            'total' => $total_points,
            'percentage' => $percentage,
            'total_questions' => $total_questions,
            'results' => $results,
            'time_taken' => $end_time - $start_time,
        );
    }
    
    private function check_answer($type, $user_answers, $correct_answers) {
        if (empty($user_answers) || empty($correct_answers)) {
            return false;
        }
        
        switch ($type) {
            case 'single':
                return count($user_answers) === 1 && in_array($user_answers[0], $correct_answers);
            
            case 'multiple':
                sort($user_answers);
                sort($correct_answers);
                return $user_answers === $correct_answers;
            
            default:
                return false;
        }
    }
    
    private function check_text_answer($user_answer, $correct_answer) {
        if (empty($user_answer) || empty($correct_answer)) {
            return false;
        }
        
        // Case-insensitive comparison
        return strtolower(trim($user_answer)) === strtolower(trim($correct_answer));
    }
    
    private function save_quiz_result($score, $total_points, $percentage, $results, $start_time, $end_time) {
        $user_id = get_current_user_id();
        $user_data = $user_id ? get_userdata($user_id) : null;
        
        $result_data = array(
            'user_id' => $user_id,
            'user_name' => $user_data ? $user_data->display_name : __('Guest User', 'custom-quiz'),
            'user_email' => $user_data ? $user_data->user_email : '',
            'score' => $score,
            'total_points' => $total_points,
            'percentage' => $percentage,
            'results' => $results,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'time_taken' => $end_time - $start_time,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        );
        
        // For now, save to user meta (in a real implementation, you'd use a custom table)
        if ($user_id) {
            $existing_results = get_user_meta($user_id, '_cqz_quiz_results', true) ?: array();
            $existing_results[] = $result_data;
            update_user_meta($user_id, '_cqz_quiz_results', $existing_results);
        } else {
            if (!session_id()) {
                session_start();
            }
            $existing_results = isset($_SESSION['cqz_quiz_results']) ? $_SESSION['cqz_quiz_results'] : array();
            $existing_results[] = $result_data;
            $_SESSION['cqz_quiz_results'] = $existing_results;
        }
        
        return count($existing_results);
    }
    
    private function mark_quiz_completed() {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            delete_user_meta($user_id, '_cqz_quiz_in_progress');
            update_user_meta($user_id, '_cqz_quiz_completed', time());
        } else {
            if (!session_id()) {
                session_start();
            }
            unset($_SESSION['cqz_quiz_in_progress']);
            $_SESSION['cqz_quiz_completed'] = time();
        }
    }
    
    private function render_results($quiz_data) {
        $settings = get_option('cqz_settings', array());
        $show_results = isset($settings['show_results']) ? $settings['show_results'] : 'immediate';
        if ($show_results === 'delayed') {
            return $this->render_delayed_results();
        }
        $user = wp_get_current_user();
        // Build category stats and attempted questions
        $category_stats = array();
        $attempted_questions = 0;
        foreach ($quiz_data['results'] as $qid => $result) {
            $categories = get_the_terms($qid, 'quiz_category');
            $cat_name = ($categories && !is_wp_error($categories)) ? $categories[0]->name : __('Uncategorized', 'custom-quiz');
            if (!isset($category_stats[$cat_name])) {
                $category_stats[$cat_name] = array('correct' => 0, 'total' => 0);
            }
            $category_stats[$cat_name]['total']++;
            if ($result['is_correct']) {
                $category_stats[$cat_name]['correct']++;
            }
            // Count attempted questions
            $user_ans = is_array($result['user_answer']) ? $result['user_answer'] : [$result['user_answer']];
            $user_provided = ($result['user_answer'] !== null && $result['user_answer'] !== '' && $result['user_answer'] !== 'null' && $result['user_answer'] !== [] && $user_ans[0] !== '');
            if ($user_provided) {
                $attempted_questions++;
            }
        }
        $score_pct = $quiz_data['percentage'];
        $score_color = $score_pct >= 80 ? '#40c057' : ($score_pct >= 50 ? '#ffa500' : '#e74c3c');
        $total_questions = $quiz_data['total_questions'] ?? count($quiz_data['results']);
        $not_attempted = $total_questions - $attempted_questions;
        ob_start();
        ?>
        <div class="cqz-results-modern cqz-results-redesign">
            <div class="cqz-user-card">
                <div class="cqz-user-avatar">
                    <?php echo get_avatar($user->ID, 64); ?>
                </div>
                <div class="cqz-user-info">
                    <div class="cqz-user-name"><?php echo esc_html($user->display_name ?? ''); ?></div>
                    <div class="cqz-user-email"><?php echo esc_html($user->user_email ?? ''); ?></div>
                </div>
            </div>
            <div class="cqz-results-score-modern cqz-score-redesign">
                <div class="cqz-trophy-icon"><span class="dashicons dashicons-awards"></span></div>
                <div class="cqz-score-main" style="color:<?php echo $score_color; ?>;"> <?php echo $quiz_data['score']; ?>/<span><?php echo $total_questions; ?></span> </div>
                <div class="cqz-score-label">Assessment Completed</div>
                <div class="cqz-score-pct"> <?php echo $score_pct; ?>% Score Achieved</div>
                <div class="cqz-score-attempts">Correct: <b><?php echo $quiz_data['score']; ?></b> &nbsp; Attempted: <b><?php echo $attempted_questions; ?></b> &nbsp; Not Attempted: <b><?php echo $not_attempted; ?></b> &nbsp; Total: <b><?php echo $total_questions; ?></b></div>
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
                <?php $qnum = 1; foreach ($quiz_data['results'] as $qid => $result): ?>
                    <?php
                    $user_ans = is_array($result['user_answer']) ? $result['user_answer'] : [$result['user_answer']];
                    $user_provided = ($result['user_answer'] !== null && $result['user_answer'] !== '' && $result['user_answer'] !== 'null' && $result['user_answer'] !== [] && $user_ans[0] !== '');
                    $is_correct = $result['is_correct'];
                    $card_bg = !$user_provided ? '#f8f9fa' : ($is_correct ? '#e6f9ed' : '#ffeaea');
                    $border_color = !$user_provided ? '#e9ecef' : ($is_correct ? '#40c057' : '#e74c3c');
                    ?>
                    <div class="cqz-result-question-modern" style="background:<?php echo $card_bg; ?>;border:2px solid <?php echo $border_color; ?>;border-radius:14px;padding:1.5rem 1.5rem 1rem 1.5rem;margin-bottom:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                            <div style="font-weight:600;font-size:1rem;display:flex;align-items:center;gap:0.7rem;">
                                <span style="color:#888;font-weight:500;font-size:0.98rem;">Q<?php echo $qnum; ?></span>
                                <?php $categories = get_the_terms($qid, 'quiz_category'); if ($categories && !is_wp_error($categories)): ?>
                                    <span style="background:#f1f3f4;color:#228be6;font-size:0.95rem;padding:2px 10px;border-radius:8px;"> <?php echo esc_html($categories[0]->name); ?> </span>
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
                        <div style="font-size:1.13rem;font-weight:600;margin-bottom:0.7rem;color:#222;"> <?php echo esc_html($result['question']); ?> </div>
                        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:0.5rem;">
                            <?php
                            $choices_string = get_post_meta($qid, '_cqz_choices', true);
                            $type = $result['type'];
                            $all_choices = array();
                            if ($choices_string && is_string($choices_string)) {
                                $decoded = json_decode($choices_string, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    $all_choices = $decoded;
                                } else {
                                    $all_choices = preg_split('/\r\n|\r|\n|\\n/', $choices_string);
                                }
                            }
                            $correct_ans = is_array($result['correct_answer']) ? $result['correct_answer'] : [$result['correct_answer']];
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
                                $ca = array_map('trim', $correct_ans);
                                echo '<div style="background:#fff;border:1.5px solid #40c057;color:#228b22;padding:8px 16px;border-radius:8px;font-weight:600;">'.esc_html($ca[0]).'</div>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <?php if (!empty($result['explanation'])): ?>
                        <div class="cqz-explanation-modern"><strong>Explanation:</strong> <?php echo esc_html($result['explanation']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php $qnum++; endforeach; ?>
            </div>
            <div class="cqz-results-actions-modern">
                <button type="button" class="cqz-btn cqz-btn-secondary" onclick="window.print()">Print Results</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_delayed_results() {
        return '<div class="cqz-delayed-results">
            <h2>' . __('Quiz Submitted Successfully', 'custom-quiz') . '</h2>
            <p>' . __('Your quiz has been submitted and is under review. You will receive your results soon.', 'custom-quiz') . '</p>
        </div>';
    }
    
    private function format_time_taken($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        } else {
            return sprintf('%d:%02d', $minutes, $secs);
        }
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
} 