<?php
namespace CustomQuiz;

if (!defined('ABSPATH')) {
    exit;
}

class CQZ_Post_Types {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function register_post_types() {
        $labels = array(
            'name'               => __('Quiz Questions', 'custom-quiz'),
            'singular_name'      => __('Quiz Question', 'custom-quiz'),
            'menu_name'          => __('Quiz Questions', 'custom-quiz'),
            'add_new'            => __('Add New Question', 'custom-quiz'),
            'add_new_item'       => __('Add New Quiz Question', 'custom-quiz'),
            'edit_item'          => __('Edit Quiz Question', 'custom-quiz'),
            'new_item'           => __('New Quiz Question', 'custom-quiz'),
            'view_item'          => __('View Quiz Question', 'custom-quiz'),
            'search_items'       => __('Search Quiz Questions', 'custom-quiz'),
            'not_found'          => __('No quiz questions found', 'custom-quiz'),
            'not_found_in_trash' => __('No quiz questions found in trash', 'custom-quiz'),
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'capability_type'     => 'post',
            'capabilities'        => array(
                'read'              => 'read',
                'read_post'         => 'read',
                'edit_post'         => 'edit_posts',
                'delete_post'       => 'delete_posts',
                'edit_posts'        => 'edit_posts',
                'delete_posts'      => 'delete_posts',
                'publish_posts'     => 'publish_posts',
            ),
            'hierarchical'        => false,
            'rewrite'             => false,
            'supports'            => array('title', 'editor'),
            'menu_icon'           => 'dashicons-editor-help',
            'show_in_rest'        => true,
            'menu_position'       => 25,
        );
        
        register_post_type('quiz_question', $args);
    }
    
    public function register_taxonomies() {
        $labels = array(
            'name'              => __('Quiz Categories', 'custom-quiz'),
            'singular_name'     => __('Quiz Category', 'custom-quiz'),
            'search_items'      => __('Search Categories', 'custom-quiz'),
            'all_items'         => __('All Categories', 'custom-quiz'),
            'parent_item'       => __('Parent Category', 'custom-quiz'),
            'parent_item_colon' => __('Parent Category:', 'custom-quiz'),
            'edit_item'         => __('Edit Category', 'custom-quiz'),
            'update_item'       => __('Update Category', 'custom-quiz'),
            'add_new_item'      => __('Add New Category', 'custom-quiz'),
            'new_item_name'     => __('New Category Name', 'custom-quiz'),
            'menu_name'         => __('Categories', 'custom-quiz'),
        );
        
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => false,
            'show_in_rest'      => true,
        );
        
        register_taxonomy('quiz_category', array('quiz_question'), $args);
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'cqz_question_details',
            __('Question Details', 'custom-quiz'),
            array($this, 'render_question_meta_box'),
            'quiz_question',
            'normal',
            'high'
        );
        
        add_meta_box(
            'cqz_question_preview',
            __('Question Preview', 'custom-quiz'),
            array($this, 'render_preview_meta_box'),
            'quiz_question',
            'side',
            'default'
        );
    }
    
    public function render_question_meta_box($post) {
        wp_nonce_field('cqz_save_question', 'cqz_question_nonce');
        
        $type = get_post_meta($post->ID, '_cqz_type', true) ?: 'single';
        $choices = get_post_meta($post->ID, '_cqz_choices', true);
        // Decode JSON for display in textarea
        if ($choices && is_string($choices)) {
            $decoded_choices = json_decode($choices, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_choices)) {
                $choices = implode("\n", $decoded_choices);
            }
        }
        $correct = get_post_meta($post->ID, '_cqz_correct', true);
        $text_input = get_post_meta($post->ID, '_cqz_text_input', true);
        $explanation = get_post_meta($post->ID, '_cqz_explanation', true);
        $points = get_post_meta($post->ID, '_cqz_points', true) ?: 1;
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="cqz_type"><?php _e('Question Type', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <select name="cqz_type" id="cqz_type">
                        <option value="single" <?php selected($type, 'single'); ?>><?php _e('Single Choice', 'custom-quiz'); ?></option>
                        <option value="multiple" <?php selected($type, 'multiple'); ?>><?php _e('Multiple Choice', 'custom-quiz'); ?></option>
                        <option value="text" <?php selected($type, 'text'); ?>><?php _e('Text Input', 'custom-quiz'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cqz_points"><?php _e('Points', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <input type="number" name="cqz_points" id="cqz_points" value="<?php echo esc_attr($points); ?>" min="1" max="10" />
                </td>
            </tr>
            <tr class="cqz-choices-row">
                <th scope="row">
                    <label for="cqz_choices"><?php _e('Answer Choices', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <textarea name="cqz_choices" id="cqz_choices" rows="6" cols="50" placeholder="<?php _e('Enter each choice on a new line', 'custom-quiz'); ?>"><?php echo esc_textarea($choices); ?></textarea>
                    <p class="description"><?php _e('Enter each choice on a separate line', 'custom-quiz'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cqz_correct"><?php _e('Correct Answer(s)', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <input type="text" name="cqz_correct" id="cqz_correct" value="<?php echo esc_attr($correct); ?>" class="regular-text" />
                    <p class="description"><?php _e('For multiple choice, separate answers with commas', 'custom-quiz'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cqz_explanation"><?php _e('Explanation', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <textarea name="cqz_explanation" id="cqz_explanation" rows="3" cols="50"><?php echo esc_textarea($explanation); ?></textarea>
                    <p class="description"><?php _e('Optional explanation shown after quiz completion', 'custom-quiz'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cqz_text_input"><?php _e('Allow Text Input', 'custom-quiz'); ?></label>
                </th>
                <td>
                    <input type="checkbox" name="cqz_text_input" id="cqz_text_input" value="1" <?php checked($text_input, '1'); ?> />
                    <span class="description"><?php _e('Allow additional text input for this question', 'custom-quiz'); ?></span>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function render_preview_meta_box($post) {
        $type = get_post_meta($post->ID, '_cqz_type', true);
        $choices = get_post_meta($post->ID, '_cqz_choices', true);
        $text_input = get_post_meta($post->ID, '_cqz_text_input', true);
        // Decode JSON for display
        $choices_array = array();
        if ($choices && is_string($choices)) {
            $decoded = json_decode($choices, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $choices_array = $decoded;
            } else {
                $choices_array = explode("\n", $choices);
            }
        }
        if (!$type) {
            echo '<p>' . __('Save the question to see preview', 'custom-quiz') . '</p>';
            return;
        }
        ?>
        <div class="cqz-preview">
            <p><strong><?php echo esc_html($post->post_title); ?></strong></p>
            <?php if ($type === 'single' && $choices_array): ?>
                <?php foreach ($choices_array as $choice): ?>
                    <label><input type="radio" disabled> <?php echo esc_html(trim($choice)); ?></label><br>
                <?php endforeach; ?>
            <?php elseif ($type === 'multiple' && $choices_array): ?>
                <?php foreach ($choices_array as $choice): ?>
                    <label><input type="checkbox" disabled> <?php echo esc_html(trim($choice)); ?></label><br>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($text_input === '1' || $type === 'text'): ?>
                <input type="text" placeholder="<?php _e('Your answer...', 'custom-quiz'); ?>" disabled style="width: 100%;" />
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function save_meta_boxes($post_id) {
        if (!isset($_POST['cqz_question_nonce']) || !wp_verify_nonce($_POST['cqz_question_nonce'], 'cqz_save_question')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $fields = array(
            'cqz_type' => 'sanitize_text_field',
            'cqz_points' => 'intval',
            'cqz_choices' => null, // custom handling below
            'cqz_correct' => 'sanitize_text_field',
            'cqz_explanation' => 'sanitize_textarea_field',
        );
        
        foreach ($fields as $field => $sanitize_callback) {
            if ($field === 'cqz_choices') {
                if (isset($_POST['cqz_choices'])) {
                    $lines = preg_split('/\r\n|\r|\n/', $_POST['cqz_choices']);
                    $lines = array_filter(array_map('trim', $lines));
                    $json = json_encode($lines, JSON_UNESCAPED_UNICODE);
                    update_post_meta($post_id, '_cqz_choices', $json);
                }
            } elseif (isset($_POST[$field])) {
                $value = call_user_func($sanitize_callback, $_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
        
        update_post_meta($post_id, '_cqz_text_input', isset($_POST['cqz_text_input']) ? '1' : '0');
    }
    
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        if ($post_type === 'quiz_question') {
            wp_enqueue_script('cqz-admin', CQZ_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), CQZ_VERSION, true);
            wp_enqueue_style('cqz-admin', CQZ_PLUGIN_URL . 'assets/css/admin.css', array(), CQZ_VERSION);
        }
    }
} 