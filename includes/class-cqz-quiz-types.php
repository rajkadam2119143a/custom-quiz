<?php
namespace CustomQuiz;

if (!defined('ABSPATH')) {
    exit;
}

class CQZ_Quiz_Types {
    
    public function __construct() {
        add_action('init', array($this, 'register_quiz_types'));
        add_action('add_meta_boxes', array($this, 'add_quiz_type_meta_boxes'));
        add_action('save_post', array($this, 'save_quiz_type_meta'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function register_quiz_types() {
        $labels = array(
            'name'               => __('Quiz Types', 'custom-quiz'),
            'singular_name'      => __('Quiz Type', 'custom-quiz'),
            'menu_name'          => __('Quiz Types', 'custom-quiz'),
            'add_new'            => __('Add New Quiz Type', 'custom-quiz'),
            'add_new_item'       => __('Add New Quiz Type', 'custom-quiz'),
            'edit_item'          => __('Edit Quiz Type', 'custom-quiz'),
            'new_item'           => __('New Quiz Type', 'custom-quiz'),
            'view_item'          => __('View Quiz Type', 'custom-quiz'),
            'search_items'       => __('Search Quiz Types', 'custom-quiz'),
            'not_found'          => __('No quiz types found', 'custom-quiz'),
            'not_found_in_trash' => __('No quiz types found in trash', 'custom-quiz'),
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'rewrite'             => false,
            'supports'            => array('title', 'editor'),
            'menu_icon'           => 'dashicons-clipboard',
            'show_in_rest'        => true,
            'menu_position'       => 26,
        );
        
        register_post_type('quiz_type', $args);
    }
    
    public function add_quiz_type_meta_boxes() {
        add_meta_box(
            'cqz_quiz_type_config',
            __('Quiz Configuration', 'custom-quiz'),
            array($this, 'render_quiz_config_meta_box'),
            'quiz_type',
            'normal',
            'high'
        );
        
        add_meta_box(
            'cqz_quiz_categories',
            __('Quiz Categories', 'custom-quiz'),
            array($this, 'render_categories_meta_box'),
            'quiz_type',
            'side',
            'default'
        );
    }
    
    public function render_quiz_config_meta_box($post) {
        wp_nonce_field('cqz_save_quiz_type', 'cqz_quiz_type_nonce');
        
        $time_limit = get_post_meta($post->ID, '_cqz_time_limit', true) ?: 120;
        $questions_per_category = get_post_meta($post->ID, '_cqz_questions_per_category', true) ?: 5;
        $total_questions = get_post_meta($post->ID, '_cqz_total_questions', true) ?: 35;
        $passing_score = get_post_meta($post->ID, '_cqz_passing_score', true) ?: 70;
        $allow_retake = get_post_meta($post->ID, '_cqz_allow_retake', true);
        $randomize_questions = get_post_meta($post->ID, '_cqz_randomize_questions', true) ?: true;
        $show_results = get_post_meta($post->ID, '_cqz_show_results', true) ?: 'immediate';
        $user_specific = get_post_meta($post->ID, '_cqz_user_specific', true) ?: true;
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="cqz_time_limit"><?php _e('Time Limit (minutes)', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <input type="number" name="cqz_time_limit" id="cqz_time_limit" 
                           value="<?php echo esc_attr($time_limit); ?>" min="1" max="480" />
                    <p class="description"><?php _e('Maximum time allowed for this quiz type', 'custom-quiz'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cqz_questions_per_category"><?php _e('Questions per Category', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <input type="number" name="cqz_questions_per_category" id="cqz_questions_per_category" 
                           value="<?php echo esc_attr($questions_per_category); ?>" min="1" max="50" />
                    <p class="description"><?php _e('Number of questions to select from each category', 'custom-quiz'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cqz_total_questions"><?php _e('Total Questions', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <input type="number" name="cqz_total_questions" id="cqz_total_questions" 
                           value="<?php echo esc_attr($total_questions); ?>" min="1" max="200" />
                    <p class="description"><?php _e('Total number of questions in the quiz', 'custom-quiz'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cqz_passing_score"><?php _e('Passing Score (%)', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <input type="number" name="cqz_passing_score" id="cqz_passing_score" 
                           value="<?php echo esc_attr($passing_score); ?>" min="0" max="100" />
                    <p class="description"><?php _e('Minimum score required to pass', 'custom-quiz'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cqz_allow_retake"><?php _e('Allow Retakes', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <input type="checkbox" name="cqz_allow_retake" id="cqz_allow_retake" value="1" 
                           <?php checked($allow_retake, '1'); ?> />
                    <span class="description"><?php _e('Allow users to retake this quiz', 'custom-quiz'); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cqz_randomize_questions"><?php _e('Randomize Questions', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <input type="checkbox" name="cqz_randomize_questions" id="cqz_randomize_questions" value="1" 
                           <?php checked($randomize_questions, '1'); ?> />
                    <span class="description"><?php _e('Randomize question order for each user', 'custom-quiz'); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cqz_user_specific"><?php _e('User-Specific Questions', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <input type="checkbox" name="cqz_user_specific" id="cqz_user_specific" value="1" 
                           <?php checked($user_specific, '1'); ?> />
                    <span class="description"><?php _e('Assign different questions to each user', 'custom-quiz'); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cqz_show_results"><?php _e('Show Results', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <select name="cqz_show_results" id="cqz_show_results">
                        <option value="immediate" <?php selected($show_results, 'immediate'); ?>>
                            <?php _e('Immediately after submission', 'custom-quiz'); ?>
                        </option>
                        <option value="delayed" <?php selected($show_results, 'delayed'); ?>>
                            <?php _e('After admin review', 'custom-quiz'); ?>
                        </option>
                        <option value="never" <?php selected($show_results, 'never'); ?>>
                            <?php _e('Never show results', 'custom-quiz'); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function render_categories_meta_box($post) {
        $selected_categories = get_post_meta($post->ID, '_cqz_categories', true) ?: array();
        $categories = get_terms(array(
            'taxonomy' => 'quiz_category',
            'hide_empty' => false,
        ));
        ?>
        <p><?php _e('Select categories for this quiz type:', 'custom-quiz'); ?></p>
        <?php foreach ($categories as $category): ?>
        <label style="display: block; margin-bottom: 8px;">
            <input type="checkbox" name="cqz_categories[]" value="<?php echo $category->term_id; ?>" 
                   <?php checked(in_array($category->term_id, $selected_categories)); ?> />
            <?php echo esc_html($category->name); ?>
        </label>
        <?php endforeach; ?>
        <?php
    }
    
    public function save_quiz_type_meta($post_id) {
        if (!isset($_POST['cqz_quiz_type_nonce']) || !wp_verify_nonce($_POST['cqz_quiz_type_nonce'], 'cqz_save_quiz_type')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $fields = array(
            'cqz_time_limit' => 'intval',
            'cqz_questions_per_category' => 'intval',
            'cqz_total_questions' => 'intval',
            'cqz_passing_score' => 'intval',
        );
        
        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_callback, $_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
        
        $boolean_fields = array(
            'cqz_allow_retake',
            'cqz_randomize_questions',
            'cqz_user_specific',
        );
        
        foreach ($boolean_fields as $field) {
            update_post_meta($post_id, '_' . $field, isset($_POST[$field]) ? '1' : '0');
        }
        
        if (isset($_POST['cqz_show_results'])) {
            update_post_meta($post_id, '_cqz_show_results', sanitize_text_field($_POST['cqz_show_results']));
        }
        
        if (isset($_POST['cqz_categories'])) {
            update_post_meta($post_id, '_cqz_categories', array_map('intval', $_POST['cqz_categories']));
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        if ($post_type === 'quiz_type') {
            wp_enqueue_script('cqz-quiz-types', CQZ_PLUGIN_URL . 'assets/js/quiz-types.js', array('jquery'), CQZ_VERSION, true);
        }
    }
    
    public static function get_quiz_types() {
        return get_posts(array(
            'post_type' => 'quiz_type',
            'numberposts' => -1,
            'post_status' => 'publish',
        ));
    }
    
    public static function get_quiz_config($quiz_type_id) {
        return array(
            'time_limit' => get_post_meta($quiz_type_id, '_cqz_time_limit', true) ?: 120,
            'questions_per_category' => get_post_meta($quiz_type_id, '_cqz_questions_per_category', true) ?: 5,
            'total_questions' => get_post_meta($quiz_type_id, '_cqz_total_questions', true) ?: 35,
            'passing_score' => get_post_meta($quiz_type_id, '_cqz_passing_score', true) ?: 70,
            'allow_retake' => get_post_meta($quiz_type_id, '_cqz_allow_retake', true) === '1',
            'randomize_questions' => get_post_meta($quiz_type_id, '_cqz_randomize_questions', true) !== '0',
            'show_results' => get_post_meta($quiz_type_id, '_cqz_show_results', true) ?: 'immediate',
            'user_specific' => get_post_meta($quiz_type_id, '_cqz_user_specific', true) === '1',
            'categories' => get_post_meta($quiz_type_id, '_cqz_categories', true) ?: array(),
        );
    }
} 