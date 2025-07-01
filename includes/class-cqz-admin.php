<?php
namespace CustomQuiz;

if (!defined('ABSPATH')) {
    exit;
}

class CQZ_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_filter('manage_quiz_question_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_quiz_question_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);
        add_filter('manage_edit-quiz_question_sortable_columns', array($this, 'make_columns_sortable'));
        add_action('wp_ajax_cqz_clear_all_data', array($this, 'ajax_clear_all_data'));
    }
    
    public function enqueue_styles() {
        wp_enqueue_style('cqz-admin', CQZ_PLUGIN_URL . 'assets/css/admin.css', array(), CQZ_VERSION);
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('cqz-admin', CQZ_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), CQZ_VERSION, true);
        wp_localize_script('cqz-admin', 'cqz_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cqz_admin_nonce'),
        ));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Quiz System', 'custom-quiz'),
            __('Quiz System', 'custom-quiz'),
            'edit_posts',
            'quiz-results',
            array($this, 'results_page'),
            'dashicons-welcome-learn-more',
            20
        );
        
        add_submenu_page(
            'quiz-results',
            __('Quiz Results', 'custom-quiz'),
            __('Results', 'custom-quiz'),
            'edit_posts',
            'quiz-results',
            array($this, 'results_page')
        );
        
        add_submenu_page(
            'quiz-results',
            __('Quiz Settings', 'custom-quiz'),
            __('Settings', 'custom-quiz'),
            'manage_options',
            'quiz-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'quiz-results',
            __('Import Questions', 'custom-quiz'),
            __('Import', 'custom-quiz'),
            'manage_options',
            'quiz-import',
            array($this, 'import_page')
        );
    }
    
    public function results_page() {
        // Show the new analytics dashboard
        $this->render_analytics_dashboard();

        // The original results table remains below
        $results_list_table = new CQZ_Results_List_Table();
        $results_list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Detailed User Results', 'custom-quiz'); ?></h1>
            <hr class="wp-header-end">
            <form id="results-filter" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                <?php
                $results_list_table->display();
                ?>
            </form>
        </div>
        <?php
    }
    
    public function dashboard_page($inline = false) {
        $total_questions = wp_count_posts('quiz_question')->publish;
        $total_categories = wp_count_terms('quiz_category');
        $recent_results = $this->get_recent_results();
        ?>
        <?php if (!$inline): ?><div class="wrap"><?php endif; ?>
        <h1><?php _e('Quiz System Dashboard', 'custom-quiz'); ?></h1>
        <div class="cqz-dashboard-stats">
            <div class="cqz-stat-box">
                <h3><?php echo $total_questions; ?></h3>
                <p><?php _e('Total Questions', 'custom-quiz'); ?></p>
            </div>
            <div class="cqz-stat-box">
                <h3><?php echo $total_categories; ?></h3>
                <p><?php _e('Categories', 'custom-quiz'); ?></p>
            </div>
            <div class="cqz-stat-box">
                <h3><?php echo count($recent_results); ?></h3>
                <p><?php _e('Recent Attempts', 'custom-quiz'); ?></p>
            </div>
        </div>
        <div class="cqz-quick-actions">
            <h2><?php _e('Quick Actions', 'custom-quiz'); ?></h2>
            <a href="<?php echo admin_url('post-new.php?post_type=quiz_question'); ?>" class="button button-primary">
                <?php _e('Add New Question', 'custom-quiz'); ?>
            </a>
            <a href="<?php echo admin_url('edit-tags.php?taxonomy=quiz_category&post_type=quiz_question'); ?>" class="button">
                <?php _e('Manage Categories', 'custom-quiz'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=quiz-import'); ?>" class="button">
                <?php _e('Import Questions', 'custom-quiz'); ?>
            </a>
        </div>
        <?php if (!$inline): ?></div><?php endif; 
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Quiz Settings', 'custom-quiz'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('cqz_settings');
                do_settings_sections('quiz-settings');
                submit_button();
                ?>
            </form>
            <div class="cqz-settings-info">
                <h3><?php _e('Shortcode Usage', 'custom-quiz'); ?></h3>
                <p><?php _e('Use the following shortcode to display the quiz on any page or post:', 'custom-quiz'); ?></p>
                <code>[quiz_assessment]</code>
                <h3><?php _e('Features', 'custom-quiz'); ?></h3>
                <ul>
                    <li><?php _e('Excel/CSV import with proportional question distribution', 'custom-quiz'); ?></li>
                    <li><?php _e('2-hour timed quiz with auto-submission', 'custom-quiz'); ?></li>
                    <li><?php _e('Detailed category-wise result reporting', 'custom-quiz'); ?></li>
                    <li><?php _e('User assignment system with progress tracking', 'custom-quiz'); ?></li>
                    <li><?php _e('Modern responsive UI with real-time progress updates', 'custom-quiz'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    public function import_page() {
        if (isset($_POST['cqz_import']) && !empty($_FILES['cqz_csv']['tmp_name'])) {
            $this->process_import();
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Import Quiz Questions', 'custom-quiz'); ?></h1>
            
            <div class="cqz-import-instructions">
                <h2><?php _e('Import Instructions', 'custom-quiz'); ?></h2>
                <p><?php _e('Upload a CSV file with the following columns:', 'custom-quiz'); ?></p>
                <ul>
                    <li><strong>question</strong> - <?php _e('The question text', 'custom-quiz'); ?></li>
                    <li><strong>type</strong> - <?php _e('Question type (single, multiple, text)', 'custom-quiz'); ?></li>
                    <li><strong>choices</strong> - <?php _e('Answer choices (one per line)', 'custom-quiz'); ?></li>
                    <li><strong>correct</strong> - <?php _e('Correct answer(s)', 'custom-quiz'); ?></li>
                    <li><strong>category</strong> - <?php _e('Category name', 'custom-quiz'); ?></li>
                    <li><strong>points</strong> - <?php _e('Points for this question (optional)', 'custom-quiz'); ?></li>
                    <li><strong>explanation</strong> - <?php _e('Explanation text (optional)', 'custom-quiz'); ?></li>
                </ul>
            </div>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('cqz_import_questions', 'cqz_import_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cqz_csv"><?php _e('CSV File', 'custom-quiz'); ?></label>
                        </th>
                        <td>
                            <input type="file" name="cqz_csv" id="cqz_csv" accept=".csv" required />
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="cqz_import" class="button-primary" 
                           value="<?php _e('Import Questions', 'custom-quiz'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }
    
    public function add_custom_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['cqz_type'] = __('Type', 'custom-quiz');
                $new_columns['cqz_category'] = __('Category', 'custom-quiz');
                $new_columns['cqz_points'] = __('Points', 'custom-quiz');
            }
        }
        return $new_columns;
    }
    
    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'cqz_type':
                $type = get_post_meta($post_id, '_cqz_type', true);
                echo esc_html(ucfirst($type));
                break;
            case 'cqz_category':
                $terms = get_the_terms($post_id, 'quiz_category');
                if ($terms && !is_wp_error($terms)) {
                    $category_names = array();
                    foreach ($terms as $term) {
                        $category_names[] = $term->name;
                    }
                    echo esc_html(implode(', ', $category_names));
                }
                break;
            case 'cqz_points':
                $points = get_post_meta($post_id, '_cqz_points', true) ?: 1;
                echo esc_html($points);
                break;
        }
    }
    
    public function make_columns_sortable($columns) {
        $columns['cqz_type'] = 'cqz_type';
        $columns['cqz_category'] = 'cqz_category';
        $columns['cqz_points'] = 'cqz_points';
        return $columns;
    }
    
    private function save_settings() {
        if (!wp_verify_nonce($_POST['cqz_settings_nonce'], 'cqz_save_settings')) {
            wp_die(__('Security check failed', 'custom-quiz'));
        }
        
        $settings = array(
            'time_limit' => intval($_POST['cqz_time_limit']),
            'questions_per_category' => intval($_POST['cqz_questions_per_category']),
            'show_results' => sanitize_text_field($_POST['cqz_show_results']),
            'allow_retake' => isset($_POST['cqz_allow_retake']) ? '1' : '0',
        );
        
        update_option('cqz_settings', $settings);
        echo '<div class="updated"><p>' . __('Settings saved successfully.', 'custom-quiz') . '</p></div>';
    }
    
    private function get_settings() {
        $defaults = array(
            'time_limit' => 120,
            'questions_per_category' => 5,
            'show_results' => 'immediate',
            'allow_retake' => '0',
        );
        
        $settings = get_option('cqz_settings', array());
        return wp_parse_args($settings, $defaults);
    }
    
    private function process_import() {
        if (!isset($_POST['cqz_import_nonce']) || !wp_verify_nonce($_POST['cqz_import_nonce'], 'cqz_import_questions')) {
            wp_die(__('Security check failed', 'custom-quiz'));
        }

        $file = fopen($_FILES['cqz_csv']['tmp_name'], 'r');
        if (!$file) {
            echo '<div class="error"><p>' . __('Could not open CSV file.', 'custom-quiz') . '</p></div>';
            return;
        }

        $header = fgetcsv($file);
        $imported = 0;
        $errors = array();
        $row_num = 1;
        // Normalize headers and build a mapping
        $header_map = [];
        foreach ($header as $h) {
            $header_map[strtolower(trim($h))] = $h;
        }
        $has_old_format = isset($header_map['question']) && isset($header_map['type']) && isset($header_map['correct']);
        $has_questions = isset($header_map['questions']);
        $has_correct_answer = isset($header_map['correct answer']);
        $option_keys = array_filter(['option a', 'option b', 'option c', 'option d'], function($k) use ($header_map) { return isset($header_map[$k]); });
        $has_new_format = $has_questions && $has_correct_answer && count($option_keys) > 0;
        if (!$has_old_format && !$has_new_format) {
            $errors[] = __('Missing required columns: either (question, type, correct) or (Questions, at least one Option X, Correct Answer)', 'custom-quiz');
            echo '<div class="error"><ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>';
            fclose($file);
            return;
        }
        while (($row = fgetcsv($file)) !== false) {
            $row_num++;
            if (count($row) !== count($header)) {
                $errors[] = __('Row ' . $row_num . ': Column count mismatch', 'custom-quiz');
                continue;
            }
            $data = array_combine($header, $row);
            // --- Custom mapping for user's CSV format (case-insensitive, flexible options) ---
            $is_custom_format = $has_new_format;
            if ($is_custom_format) {
                $question = $data[$header_map['questions']];
                $category = isset($header_map['category']) ? $data[$header_map['category']] : '';
                $options = [];
                foreach (['a', 'b', 'c', 'd'] as $letter) {
                    $key = 'option ' . $letter;
                    if (isset($header_map[$key])) {
                        $opt = trim($data[$header_map[$key]]);
                        if ($opt !== '') $options[strtoupper($letter)] = $opt;
                    }
                }
                $choices = array_values($options);
                $correct_letters = array_map('trim', explode(',', $data[$header_map['correct answer']]));
                $correct_texts = [];
                foreach ($correct_letters as $letter) {
                    $letter = strtoupper($letter);
                    if (isset($options[$letter])) $correct_texts[] = $options[$letter];
                }
                $type = count($correct_texts) > 1 ? 'multiple' : 'single';
                $choices_str = implode("\n", $choices);
                $correct_str = implode("\n", $correct_texts);
                $import_data = [
                    'question' => $question,
                    'type' => $type,
                    'choices' => $choices_str,
                    'correct' => $correct_str,
                    'category' => $category,
                ];
            } else {
                $import_data = $data;
            }
            // --- End custom mapping ---
            if (empty($import_data['question']) || empty($import_data['type']) || empty($import_data['correct'])) {
                $errors[] = __('Row ' . $row_num . ': Missing required fields (question, type, or correct)', 'custom-quiz');
                continue;
            }
            $post_data = array(
                'post_type' => 'quiz_question',
                'post_title' => sanitize_text_field($import_data['question']),
                'post_content' => isset($import_data['content']) ? sanitize_textarea_field($import_data['content']) : '',
                'post_status' => 'publish',
            );
            $post_id = wp_insert_post($post_data);
            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, '_cqz_type', sanitize_text_field($import_data['type']));
                if (isset($import_data['choices'])) {
                    $lines = preg_split('/\r\n|\r|\n|\\n/', $import_data['choices']);
                    $lines = array_filter(array_map('trim', $lines));
                    $json = json_encode($lines, JSON_UNESCAPED_UNICODE);
                    update_post_meta($post_id, '_cqz_choices', $json);
                }
                update_post_meta($post_id, '_cqz_correct', sanitize_text_field($import_data['correct']));
                if (!empty($import_data['category'])) {
                    wp_set_object_terms($post_id, sanitize_text_field($import_data['category']), 'quiz_category');
                }
                $imported++;
            } else {
                $errors[] = __('Row ' . $row_num . ': Failed to create question', 'custom-quiz');
            }
        }
        fclose($file);
        if ($imported > 0) {
            echo '<div class="updated"><p>' . sprintf(__('%d questions imported successfully.', 'custom-quiz'), $imported) . '</p></div>';
        }
        if (!empty($errors)) {
            echo '<div class="error"><ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>';
        }
    }
    
    private function get_recent_results() {
        global $wpdb;
        $table = get_option('cqz_table_results', $wpdb->prefix . 'cqz_results');
        $results = $wpdb->get_results("SELECT user_name, score, total_points, created_at FROM $table ORDER BY created_at DESC LIMIT 10", ARRAY_A);
        $recent = array();
        foreach ($results as $row) {
            $recent[] = array(
                'user_name' => $row['user_name'],
                'score' => $row['score'],
                'total' => $row['total_points'],
                'date' => date('Y-m-d H:i', strtotime($row['created_at'])),
            );
        }
        return $recent;
    }
    
    private function get_all_results() {
        global $wpdb;
        $table = get_option('cqz_table_results', $wpdb->prefix . 'cqz_results');
        $results = $wpdb->get_results("SELECT id, user_name, score, total_points, percentage, time_taken, created_at FROM $table ORDER BY created_at DESC", ARRAY_A);
        $all = array();
        foreach ($results as $row) {
            $all[] = array(
                'id' => $row['id'],
                'user_name' => $row['user_name'],
                'score' => $row['score'],
                'total' => $row['total_points'],
                'percentage' => $row['percentage'],
                'time_taken' => $this->format_time_taken($row['time_taken']),
                'date' => date('Y-m-d H:i', strtotime($row['created_at'])),
            );
        }
        return $all;
    }
    
    public function init_settings() {
        // Register settings if needed
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

    private function get_analytics_data() {
        global $wpdb;
        $results_table = $wpdb->prefix . 'cqz_results';
        $data = [];

        // 1. Total Users
        $data['total_users'] = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $results_table");

        // 2. Average Score
        $data['average_score'] = $wpdb->get_var("SELECT AVG(percentage) FROM $results_table");

        // 3. Completion Rate (assuming all entries in results are completed)
        $data['completion_rate'] = 100;

        // 4. Score Distribution
        $data['score_distribution'] = [
            'below_50' => $wpdb->get_var("SELECT COUNT(*) FROM $results_table WHERE percentage < 50"),
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $results_table")
        ];

        // 5. Category Performance
        $category_performance = [];
        $raw_results = $wpdb->get_results("SELECT category_breakdown, user_id FROM $results_table");

        $category_user_attempts = [];

        foreach ($raw_results as $result) {
            $breakdown = json_decode($result->category_breakdown, true);
            if (is_array($breakdown)) {
                foreach ($breakdown as $cat_data) {
                    $cat_name = $cat_data['category_name'];
                    if (!isset($category_performance[$cat_name])) {
                        $category_performance[$cat_name] = ['correct' => 0, 'total' => 0, 'attempts' => 0, 'user_ids' => []];
                    }
                    $category_performance[$cat_name]['correct'] += $cat_data['correct_answers'];
                    $category_performance[$cat_name]['total'] += $cat_data['total_questions'];
                    
                    if (!in_array($result->user_id, $category_performance[$cat_name]['user_ids'])) {
                         $category_performance[$cat_name]['user_ids'][] = $result->user_id;
                    }
                }
            }
        }
        
        foreach($category_performance as $cat_name => &$perf_data) {
            $perf_data['users_attempted'] = count($perf_data['user_ids']);
            unset($perf_data['user_ids']);
        }

        $data['category_performance'] = $category_performance;
        
        return $data;
    }

    private function render_analytics_dashboard() {
        $data = $this->get_analytics_data();
        $nonce = wp_create_nonce('cqz_clear_data');
        ?>
        <div class="wrap cqz-analytics-dashboard">
            <div style="display:flex;justify-content:flex-end;align-items:center;">
                <button id="cqz-clear-data-btn" class="button button-danger" data-nonce="<?php echo esc_attr($nonce); ?>">Clear All Data</button>
            </div>
            <h1>Quiz Analytics Dashboard</h1>
            <p class="subtitle">Comprehensive analysis of quiz performance and user engagement</p>

            <div class="cqz-stat-cards">
                <div class="card">
                    <div class="card-icon users"></div>
                    <div class="card-value"><?php echo esc_html($data['total_users'] ?? 0); ?></div>
                    <div class="card-label">Total Users</div>
                    <p><?php echo esc_html($data['total_users'] ?? 0); ?> users attempted quiz</p>
                </div>
                <div class="card">
                    <div class="card-icon score"></div>
                    <div class="card-value"><?php echo round($data['average_score'] ?? 0, 0); ?>%</div>
                    <div class="card-label">Average Score</div>
                    <p>Overall performance</p>
                </div>
                <div class="card">
                    <div class="card-icon completion"></div>
                    <div class="card-value"><?php echo round($data['completion_rate'] ?? 0, 0); ?>%</div>
                    <div class="card-label">Completion Rate</div>
                    <p>Quiz completion</p>
                </div>
            </div>

            <div class="cqz-analytics-section">
                <h2>Score Distribution</h2>
                <div class="distribution-bar">
                    <span>Below 50%</span>
                    <strong><?php echo esc_html($data['score_distribution']['below_50']); ?> users</strong>
                    <div class="bar">
                        <div class="fill" style="width: <?php echo $data['score_distribution']['total'] > 0 ? ($data['score_distribution']['below_50'] / $data['score_distribution']['total']) * 100 : 0; ?>%;"></div>
                    </div>
                </div>
            </div>
            
            <div class="cqz-analytics-section">
                <h2>Category Performance</h2>
                <div class="category-grid">
                    <?php if (!empty($data['category_performance'])): ?>
                        <?php foreach ($data['category_performance'] as $name => $perf): ?>
                            <div class="category-card">
                                <h3><?php echo esc_html($name); ?></h3>
                                <div class="cat-score">Correct: <?php echo esc_html($perf['correct']); ?>/<?php echo esc_html($perf['total']); ?></div>
                                <div class="cat-bar">
                                    <div class="fill" style="width: <?php echo $perf['total'] > 0 ? ($perf['correct'] / $perf['total']) * 100 : 0; ?>%;"></div>
                                </div>
                                <div class="cat-percent"><?php echo $perf['total'] > 0 ? round(($perf['correct'] / $perf['total']) * 100) : 0; ?>%</div>
                                <div class="cat-users"><?php echo esc_html($perf['users_attempted']); ?> users attempted</div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No category data available yet.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <script>
        jQuery(document).ready(function($){
            $('#cqz-clear-data-btn').on('click', function(){
                if(confirm('Are you sure you want to delete ALL quiz data? This cannot be undone!')){
                    var btn = $(this);
                    btn.prop('disabled', true).text('Clearing...');
                    $.post(ajaxurl, {
                        action: 'cqz_clear_all_data',
                        nonce: btn.data('nonce')
                    }, function(resp){
                        if(resp.success){
                            alert('All quiz data has been cleared.');
                            location.reload();
                        } else {
                            alert('Error: ' + (resp.data || 'Could not clear data.'));
                            btn.prop('disabled', false).text('Clear All Data');
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    // AJAX handler for clearing all data
    public function ajax_clear_all_data() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        check_ajax_referer('cqz_clear_data', 'nonce');
        global $wpdb;
        $tables = [
            $wpdb->prefix . 'cqz_assignments',
            $wpdb->prefix . 'cqz_assignment_questions',
            $wpdb->prefix . 'cqz_results',
        ];
        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE $table");
        }
        wp_send_json_success();
    }
} 