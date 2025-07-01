<?php
namespace CustomQuiz;

if (!defined('ABSPATH')) {
    exit;
}

class CQZ_Import {
    
    public function __construct() {
        add_action('admin_post_cqz_import_questions', array($this, 'handle_import'));
        add_action('wp_ajax_cqz_validate_csv', array($this, 'validate_csv'));
    }
    
    public function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'custom-quiz'));
        }
        
        check_admin_referer('cqz_import_questions', 'cqz_import_nonce');
        
        if (!isset($_FILES['cqz_csv']) || $_FILES['cqz_csv']['error'] !== UPLOAD_ERR_OK) {
            $this->redirect_with_error(__('No file uploaded or upload error occurred.', 'custom-quiz'));
        }
        
        $file = $_FILES['cqz_csv'];
        $file_path = $file['tmp_name'];
        
        // Validate file type
        $file_info = pathinfo($file['name']);
        if (strtolower($file_info['extension']) !== 'csv') {
            $this->redirect_with_error(__('Only CSV files are allowed.', 'custom-quiz'));
        }
        
        // Process the import
        $result = $this->process_csv_import($file_path);
        
        if (is_wp_error($result)) {
            $this->redirect_with_error($result->get_error_message());
        }
        
        $this->redirect_with_success(sprintf(
            __('Import completed successfully. %d questions imported, %d errors.', 'custom-quiz'),
            $result['imported'],
            $result['errors']
        ));
    }
    
    private function process_csv_import($file_path) {
        $required_columns = ['question', 'type', 'correct'];
        $optional_columns = ['choices', 'category', 'points', 'explanation'];
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new \WP_Error('file_open', 'Could not open CSV file.');
        }
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return new \WP_Error('empty_csv', 'CSV file is empty.');
        }
        // Normalize header
        $header = array_map('trim', array_map('strtolower', $header));
        // Map column indexes
        $col_map = array();
        foreach ($required_columns as $col) {
            $idx = array_search($col, $header);
            if ($idx === false) {
                fclose($handle);
                return new \WP_Error('missing_column', 'Missing required column: ' . $col);
            }
            $col_map[$col] = $idx;
        }
        foreach ($optional_columns as $col) {
            $idx = array_search($col, $header);
            if ($idx !== false) {
                $col_map[$col] = $idx;
            }
        }
        $imported = 0;
        $errors = 0;
        $error_messages = array();
        $row_number = 1; // Start from 1 since we already read the header
        
        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;
            
            if (count($row) !== count($header)) {
                $errors++;
                $error_messages[] = sprintf(__('Row %d: Column count mismatch', 'custom-quiz'), $row_number);
                continue;
            }
            
            $data = array_combine($header, $row);
            // --- Custom mapping for user's CSV format (case-insensitive, flexible options) ---
            $is_custom_format = isset($col_map['choices']) && isset($col_map['correct']);
            if ($is_custom_format) {
                $question = $data[$header[$col_map['question']]];
                $category = isset($header[$col_map['category']]) ? $data[$header[$col_map['category']]] : '';
                $options = [];
                foreach (['a', 'b', 'c', 'd'] as $letter) {
                    $key = 'option ' . $letter;
                    if (isset($header[$col_map[$key]])) {
                        $opt = trim($data[$header[$col_map[$key]]]);
                        if ($opt !== '') $options[strtoupper($letter)] = $opt;
                    }
                }
                $choices = array_values($options);
                $correct_letters = array_map('trim', explode(',', $data[$header[$col_map['correct']]]));
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
            $result = $this->import_single_question($import_data, $row_number);
            
            if (is_wp_error($result)) {
                $errors++;
                $error_messages[] = sprintf(__('Row %d: %s', 'custom-quiz'), $row_number, $result->get_error_message());
            } else {
                $imported++;
            }
        }
        
        fclose($handle);
        
        // Store error messages in transient for display
        if (!empty($error_messages)) {
            set_transient('cqz_import_errors', $error_messages, 60);
        }
        
        return array(
            'imported' => $imported,
            'errors' => $errors,
        );
    }
    
    private function import_single_question($data, $row_number) {
        // Validate required fields
        if (empty($data['question'])) {
            return new \WP_Error('missing_question', __('Question text is required', 'custom-quiz'));
        }
        
        if (empty($data['type'])) {
            return new \WP_Error('missing_type', __('Question type is required', 'custom-quiz'));
        }
        
        if (empty($data['correct'])) {
            return new \WP_Error('missing_correct', __('Correct answer is required', 'custom-quiz'));
        }
        
        // Validate question type
        $valid_types = array('single', 'multiple', 'text');
        if (!in_array(strtolower($data['type']), $valid_types)) {
            return new \WP_Error('invalid_type', sprintf(
                __('Invalid question type. Must be one of: %s', 'custom-quiz'),
                implode(', ', $valid_types)
            ));
        }
        
        // Create the question post
        $post_data = array(
            'post_type' => 'quiz_question',
            'post_title' => sanitize_text_field($data['question']),
            'post_content' => isset($data['content']) ? sanitize_textarea_field($data['content']) : '',
            'post_status' => 'publish',
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Save question meta
        $meta_fields = array(
            '_cqz_type' => sanitize_text_field($data['type']),
            '_cqz_choices' => '', // will set below
            '_cqz_correct' => sanitize_text_field($data['correct']),
            '_cqz_points' => isset($data['points']) ? intval($data['points']) : 1,
            '_cqz_explanation' => isset($data['explanation']) ? sanitize_textarea_field($data['explanation']) : '',
            '_cqz_text_input' => isset($data['text_input']) && $data['text_input'] === '1' ? '1' : '0',
        );
        // Handle choices as JSON
        if (isset($data['choices'])) {
            $lines = preg_split('/\r\n|\r|\n|\\n/', $data['choices']);
            $lines = array_filter(array_map('trim', $lines));
            $meta_fields['_cqz_choices'] = json_encode($lines, JSON_UNESCAPED_UNICODE);
        }
        
        foreach ($meta_fields as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
        
        // Assign category if provided
        if (!empty($data['category'])) {
            $category_name = sanitize_text_field($data['category']);
            $term = term_exists($category_name, 'quiz_category');
            
            if (!$term) {
                $term = wp_insert_term($category_name, 'quiz_category');
            }
            
            if (!is_wp_error($term)) {
                wp_set_object_terms($post_id, $term['term_id'], 'quiz_category');
            }
        }
        
        return $post_id;
    }
    
    public function validate_csv() {
        check_ajax_referer('cqz_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'custom-quiz'));
        }
        
        if (!isset($_FILES['csv_file'])) {
            wp_send_json_error(__('No file uploaded.', 'custom-quiz'));
        }
        
        $file = $_FILES['csv_file'];
        $file_path = $file['tmp_name'];
        
        if (!is_readable($file_path)) {
            wp_send_json_error(__('Could not read uploaded file.', 'custom-quiz'));
        }
        
        $validation_result = $this->validate_csv_structure($file_path);
        
        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('CSV file is valid and ready for import.', 'custom-quiz'),
            'preview' => $validation_result['preview'],
        ));
    }
    
    private function validate_csv_structure($file_path) {
        $file_handle = fopen($file_path, 'r');
        if (!$file_handle) {
            return new \WP_Error('file_error', __('Could not open CSV file.', 'custom-quiz'));
        }
        
        // Read header
        $header = fgetcsv($file_handle);
        if (!$header) {
            fclose($file_handle);
            return new \WP_Error('header_error', __('Could not read CSV header.', 'custom-quiz'));
        }
        
        // Check required columns
        $required_columns = array('question', 'type', 'correct');
        $missing_columns = array_diff($required_columns, $header);
        if (!empty($missing_columns)) {
            fclose($file_handle);
            return new \WP_Error('missing_columns', sprintf(
                __('Missing required columns: %s', 'custom-quiz'),
                implode(', ', $missing_columns)
            ));
        }
        
        // Read first few rows for preview
        $preview = array();
        $row_count = 0;
        $max_preview_rows = 5;
        
        while (($row = fgetcsv($file_handle)) !== false && $row_count < $max_preview_rows) {
            if (count($row) === count($header)) {
                $preview[] = array_combine($header, $row);
            }
            $row_count++;
        }
        
        fclose($file_handle);
        
        return array(
            'header' => $header,
            'preview' => $preview,
        );
    }
    
    private function redirect_with_error($message) {
        set_transient('cqz_import_error', $message, 60);
        wp_redirect(admin_url('admin.php?page=quiz-import'));
        exit;
    }
    
    private function redirect_with_success($message) {
        set_transient('cqz_import_success', $message, 60);
        wp_redirect(admin_url('admin.php?page=quiz-import'));
        exit;
    }
    
    public function get_import_template() {
        $template = array(
            'question' => 'What is the capital of France?',
            'type' => 'single',
            'choices' => "Paris\nLondon\nBerlin\nMadrid",
            'correct' => 'Paris',
            'category' => 'Geography',
            'points' => '1',
            'explanation' => 'Paris is the capital and largest city of France.',
            'content' => 'Additional question content or context (optional)',
        );
        
        return $template;
    }
    
    public function generate_sample_csv() {
        $template = $this->get_import_template();
        
        $csv_content = implode(',', array_keys($template)) . "\n";
        $csv_content .= implode(',', array_map(function($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $template));
        
        return $csv_content;
    }
} 