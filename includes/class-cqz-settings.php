<?php
namespace CustomQuiz;

if (!defined('ABSPATH')) {
    exit;
}

class CQZ_Settings {
    
    private $option_group = 'cqz_settings';
    private $option_name = 'cqz_settings';
    
    public function __construct() {
        add_action('admin_init', array($this, 'init_settings'));
    }
    
    public function init_settings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            array($this, 'sanitize_settings')
        );
        
        // General Settings Section
        add_settings_section(
            'cqz_general_settings',
            __('General Settings', 'custom-quiz'),
            array($this, 'render_general_section'),
            'quiz-settings'
        );
        
        // Quiz Behavior Section
        add_settings_section(
            'cqz_quiz_behavior',
            __('Quiz Behavior', 'custom-quiz'),
            array($this, 'render_quiz_behavior_section'),
            'quiz-settings'
        );
        
        // Results Section
        add_settings_section(
            'cqz_results_settings',
            __('Results Settings', 'custom-quiz'),
            array($this, 'render_results_section'),
            'quiz-settings'
        );
        
        // Add settings fields
        $this->add_settings_fields();
    }
    
    private function add_settings_fields() {
        // General Settings Fields
        add_settings_field(
            'cqz_time_limit',
            __('Default Time Limit (minutes)', 'custom-quiz'),
            array($this, 'render_number_field'),
            'quiz-settings',
            'cqz_general_settings',
            array(
                'name' => 'time_limit',
                'min' => 1,
                'max' => 480,
                'default' => 120,
                'description' => __('Default time limit for quizzes in minutes', 'custom-quiz'),
            )
        );
        
        add_settings_field(
            'cqz_questions_per_quiz',
            __('Questions per Quiz', 'custom-quiz'),
            array($this, 'render_number_field'),
            'quiz-settings',
            'cqz_general_settings',
            array(
                'name' => 'questions_per_quiz',
                'min' => 1,
                'max' => 100,
                'default' => 40,
                'description' => __('Total number of questions per quiz assessment', 'custom-quiz'),
            )
        );
        
        add_settings_field(
            'cqz_proportional_distribution',
            __('Proportional Question Distribution', 'custom-quiz'),
            array($this, 'render_checkbox_field'),
            'quiz-settings',
            'cqz_general_settings',
            array(
                'name' => 'proportional_distribution',
                'default' => true,
                'description' => __('Distribute questions proportionally based on available questions per category', 'custom-quiz'),
            )
        );
        
        add_settings_field(
            'cqz_quiz_title',
            __('Quiz Title', 'custom-quiz'),
            array($this, 'render_text_field'),
            'quiz-settings',
            'cqz_general_settings',
            array(
                'name' => 'quiz_title',
                'default' => 'Quiz Assessment Platform',
                'description' => __('Title displayed on the quiz welcome page', 'custom-quiz'),
            )
        );
        
        add_settings_field(
            'cqz_welcome_message',
            __('Welcome Message', 'custom-quiz'),
            array($this, 'render_textarea_field'),
            'quiz-settings',
            'cqz_general_settings',
            array(
                'name' => 'welcome_message',
                'default' => 'You are about to begin a comprehensive quiz covering multiple categories. The assessment consists of 40 randomly selected questions with a 2-hour time limit.',
                'description' => __('Welcome message displayed on the quiz landing page', 'custom-quiz'),
            )
        );
        
        // Quiz Behavior Fields
        add_settings_field(
            'cqz_allow_retake',
            __('Allow Quiz Retakes', 'custom-quiz'),
            array($this, 'render_checkbox_field'),
            'quiz-settings',
            'cqz_quiz_behavior',
            array(
                'name' => 'allow_retake',
                'description' => __('Allow users to retake the quiz multiple times', 'custom-quiz'),
            )
        );
        
        add_settings_field(
            'cqz_randomize_questions',
            __('Randomize Questions', 'custom-quiz'),
            array($this, 'render_checkbox_field'),
            'quiz-settings',
            'cqz_quiz_behavior',
            array(
                'name' => 'randomize_questions',
                'default' => true,
                'description' => __('Randomize the order of questions in each category', 'custom-quiz'),
            )
        );
        
        add_settings_field(
            'cqz_randomize_choices',
            __('Randomize Answer Choices', 'custom-quiz'),
            array($this, 'render_checkbox_field'),
            'quiz-settings',
            'cqz_quiz_behavior',
            array(
                'name' => 'randomize_choices',
                'description' => __('Randomize the order of answer choices', 'custom-quiz'),
            )
        );
        
        add_settings_field(
            'cqz_show_progress',
            __('Show Progress Bar', 'custom-quiz'),
            array($this, 'render_checkbox_field'),
            'quiz-settings',
            'cqz_quiz_behavior',
            array(
                'name' => 'show_progress',
                'default' => true,
                'description' => __('Show a progress bar during the quiz', 'custom-quiz'),
            )
        );
        
        // Results Fields
        add_settings_field(
            'cqz_show_results',
            __('Show Results', 'custom-quiz'),
            array($this, 'render_select_field'),
            'quiz-settings',
            'cqz_results_settings',
            array(
                'name' => 'show_results',
                'options' => array(
                    'immediate' => __('Immediately after submission', 'custom-quiz'),
                    'delayed' => __('After admin review', 'custom-quiz'),
                    'never' => __('Never show results', 'custom-quiz'),
                ),
                'default' => 'immediate',
                'description' => __('When to show quiz results to users', 'custom-quiz'),
            )
        );
        
        add_settings_field(
            'cqz_show_correct_answers',
            __('Show Correct Answers', 'custom-quiz'),
            array($this, 'render_checkbox_field'),
            'quiz-settings',
            'cqz_results_settings',
            array(
                'name' => 'show_correct_answers',
                'default' => true,
                'description' => __('Show correct answers in results', 'custom-quiz'),
            )
        );
        
        add_settings_field(
            'cqz_show_explanations',
            __('Show Explanations', 'custom-quiz'),
            array($this, 'render_checkbox_field'),
            'quiz-settings',
            'cqz_results_settings',
            array(
                'name' => 'show_explanations',
                'description' => __('Show explanations for questions in results', 'custom-quiz'),
            )
        );
        
        add_settings_field(
            'cqz_save_results',
            __('Save Quiz Results', 'custom-quiz'),
            array($this, 'render_checkbox_field'),
            'quiz-settings',
            'cqz_results_settings',
            array(
                'name' => 'save_results',
                'default' => true,
                'description' => __('Save quiz results for admin review', 'custom-quiz'),
            )
        );
        
        add_settings_field(
            'cqz_email_results',
            __('Email Results to Admin', 'custom-quiz'),
            array($this, 'render_checkbox_field'),
            'quiz-settings',
            'cqz_results_settings',
            array(
                'name' => 'email_results',
                'description' => __('Send quiz results to admin email', 'custom-quiz'),
            )
        );
        
        add_settings_field(
            'cqz_admin_email',
            __('Admin Email', 'custom-quiz'),
            array($this, 'render_email_field'),
            'quiz-settings',
            'cqz_results_settings',
            array(
                'name' => 'admin_email',
                'default' => get_option('admin_email'),
                'description' => __('Email address to receive quiz results', 'custom-quiz'),
            )
        );
    }
    
    public function render_general_section() {
        echo '<p>' . __('Configure general quiz settings and defaults.', 'custom-quiz') . '</p>';
    }
    
    public function render_quiz_behavior_section() {
        echo '<p>' . __('Configure how the quiz behaves during user interaction.', 'custom-quiz') . '</p>';
    }
    
    public function render_results_section() {
        echo '<p>' . __('Configure how quiz results are displayed and handled.', 'custom-quiz') . '</p>';
    }
    
    public function render_number_field($args) {
        $settings = $this->get_settings();
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : $args['default'];
        ?>
        <input type="number" 
               name="<?php echo $this->option_name; ?>[<?php echo $args['name']; ?>]" 
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo $args['min']; ?>"
               max="<?php echo $args['max']; ?>"
               class="regular-text" />
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo $args['description']; ?></p>
        <?php endif; ?>
        <?php
    }
    
    public function render_checkbox_field($args) {
        $settings = $this->get_settings();
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : (isset($args['default']) ? $args['default'] : false);
        ?>
        <label>
            <input type="checkbox" 
                   name="<?php echo $this->option_name; ?>[<?php echo $args['name']; ?>]" 
                   value="1" 
                   <?php checked($value, true); ?> />
            <?php if (isset($args['description'])): ?>
                <span class="description"><?php echo $args['description']; ?></span>
            <?php endif; ?>
        </label>
        <?php
    }
    
    public function render_select_field($args) {
        $settings = $this->get_settings();
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : $args['default'];
        ?>
        <select name="<?php echo $this->option_name; ?>[<?php echo $args['name']; ?>]">
            <?php foreach ($args['options'] as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php selected($value, $key); ?>>
                    <?php echo $label; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo $args['description']; ?></p>
        <?php endif; ?>
        <?php
    }
    
    public function render_email_field($args) {
        $settings = $this->get_settings();
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : $args['default'];
        ?>
        <input type="email" 
               name="<?php echo $this->option_name; ?>[<?php echo $args['name']; ?>]" 
               value="<?php echo esc_attr($value); ?>"
               class="regular-text" />
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo $args['description']; ?></p>
        <?php endif; ?>
        <?php
    }
    
    public function render_text_field($args) {
        $settings = $this->get_settings();
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : $args['default'];
        ?>
        <input type="text" 
               name="<?php echo $this->option_name; ?>[<?php echo $args['name']; ?>]" 
               value="<?php echo esc_attr($value); ?>"
               class="regular-text" />
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo $args['description']; ?></p>
        <?php endif; ?>
        <?php
    }
    
    public function render_textarea_field($args) {
        $settings = $this->get_settings();
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : $args['default'];
        ?>
        <textarea name="<?php echo $this->option_name; ?>[<?php echo $args['name']; ?>]" 
                  class="large-text" 
                  rows="5"><?php echo esc_textarea($value); ?></textarea>
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo $args['description']; ?></p>
        <?php endif; ?>
        <?php
    }
    
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // General settings
        if (isset($input['time_limit'])) {
            $sanitized['time_limit'] = intval($input['time_limit']);
            if ($sanitized['time_limit'] < 1) $sanitized['time_limit'] = 120;
            if ($sanitized['time_limit'] > 480) $sanitized['time_limit'] = 480;
        }
        
        if (isset($input['questions_per_quiz'])) {
            $sanitized['questions_per_quiz'] = intval($input['questions_per_quiz']);
            if ($sanitized['questions_per_quiz'] < 1) $sanitized['questions_per_quiz'] = 40;
            if ($sanitized['questions_per_quiz'] > 100) $sanitized['questions_per_quiz'] = 100;
        }
        
        if (isset($input['proportional_distribution'])) {
            $sanitized['proportional_distribution'] = isset($input['proportional_distribution']) ? true : false;
        }
        
        if (isset($input['quiz_title'])) {
            $sanitized['quiz_title'] = sanitize_text_field($input['quiz_title']);
        }
        
        if (isset($input['welcome_message'])) {
            $sanitized['welcome_message'] = sanitize_textarea_field($input['welcome_message']);
        }
        
        // Quiz behavior
        $boolean_fields = array(
            'allow_retake', 'randomize_questions', 'randomize_choices', 
            'show_progress', 'show_correct_answers', 'show_explanations',
            'save_results', 'email_results'
        );
        
        foreach ($boolean_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? true : false;
        }
        
        // Results settings
        if (isset($input['show_results'])) {
            $valid_results = array('immediate', 'delayed', 'never');
            $sanitized['show_results'] = in_array($input['show_results'], $valid_results) 
                ? $input['show_results'] : 'immediate';
        }
        
        if (isset($input['admin_email'])) {
            $sanitized['admin_email'] = sanitize_email($input['admin_email']);
            if (!is_email($sanitized['admin_email'])) {
                $sanitized['admin_email'] = get_option('admin_email');
            }
        }
        
        return $sanitized;
    }
    
    public function get_settings() {
        $defaults = array(
            'time_limit' => 120,
            'questions_per_quiz' => 40,
            'proportional_distribution' => true,
            'quiz_title' => 'Quiz Assessment Platform',
            'welcome_message' => 'You are about to begin a comprehensive quiz covering multiple categories. The assessment consists of 40 randomly selected questions with a 2-hour time limit.',
            'allow_retake' => false,
            'randomize_questions' => true,
            'randomize_choices' => false,
            'show_progress' => true,
            'show_results' => 'immediate',
            'show_correct_answers' => true,
            'show_explanations' => false,
            'save_results' => true,
            'email_results' => false,
            'admin_email' => get_option('admin_email'),
        );
        
        $settings = get_option($this->option_name, array());
        return wp_parse_args($settings, $defaults);
    }
    
    public function get_setting($key, $default = null) {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    public function update_setting($key, $value) {
        $settings = $this->get_settings();
        $settings[$key] = $value;
        return update_option($this->option_name, $settings);
    }
} 