<?php
namespace CustomQuiz;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class CQZ_Results_List_Table extends \WP_List_Table {

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'username' => __('Username', 'custom-quiz'),
            'score' => __('Score', 'custom-quiz'),
            'percentage' => __('Percentage', 'custom-quiz'),
            'created_at' => __('Completed At', 'custom-quiz'),
            'categories' => __('Categories', 'custom-quiz'),
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $results_table = $wpdb->prefix . 'cqz_results';

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        $this->items = $wpdb->get_results("SELECT * FROM $results_table ORDER BY created_at DESC", ARRAY_A);
    }

    public function column_default($item, $column_name) {
        return $item[$column_name] ?? '—';
    }

    public function column_username($item) {
        return sprintf(
            '<strong>%s</strong><br><small>%s</small>',
            esc_html($item['user_name']),
            esc_html($item['user_email'])
        );
    }

    public function column_score($item) {
        return sprintf('%d / %d', $item['score'], $item['total_questions']);
    }

    public function column_percentage($item) {
        return sprintf('<strong style="color: %s;">%s%%</strong>', $item['percentage'] > 50 ? 'green' : 'red', $item['percentage']);
    }

    public function column_created_at($item) {
        return date('d/m/Y H:i:s', strtotime($item['created_at']));
    }

    public function column_categories($item) {
        $breakdown = json_decode($item['category_breakdown'], true);
        if (empty($breakdown) || !is_array($breakdown)) {
            return '—';
        }

        $output = '<ul>';
        foreach ($breakdown as $cat) {
            $output .= sprintf(
                '<li>%s: %d / %d</li>',
                esc_html($cat['category_name']),
                $cat['correct_answers'],
                $cat['total_questions']
            );
        }
        $output .= '</ul>';
        return $output;
    }

    public function get_sortable_columns() {
        return [
            'username' => ['user_name', false],
            'percentage' => ['percentage', false],
            'created_at' => ['created_at', false],
        ];
    }

     public function column_cb($item) {
        return sprintf('<input type="checkbox" name="result[]" value="%s" />', $item['id']);
    }
} 