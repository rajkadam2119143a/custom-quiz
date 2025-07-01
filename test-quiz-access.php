<?php
/**
 * Test file to verify quiz question access for different user roles
 * Place this in your WordPress root directory and access via browser
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is logged in
if (!is_user_logged_in()) {
    echo '<h2>User not logged in</h2>';
    echo '<p>Please log in to test quiz access.</p>';
    exit;
}

$current_user = wp_get_current_user();
echo '<h2>Current User: ' . esc_html($current_user->display_name) . '</h2>';
echo '<p>User ID: ' . $current_user->ID . '</p>';
echo '<p>User Roles: ' . implode(', ', $current_user->roles) . '</p>';

// Test 1: Check if user can read posts
echo '<h3>Test 1: Basic Read Capability</h3>';
echo '<p>Can read: ' . (current_user_can('read') ? 'Yes' : 'No') . '</p>';

// Test 2: Check if quiz questions exist
echo '<h3>Test 2: Quiz Questions Count</h3>';
$questions = get_posts(array(
    'post_type' => 'quiz_question',
    'numberposts' => -1,
    'post_status' => 'publish',
    'suppress_filters' => true,
));
echo '<p>Total quiz questions: ' . count($questions) . '</p>';

// Test 3: Check if user can access quiz questions
echo '<h3>Test 3: Quiz Question Access</h3>';
if (!empty($questions)) {
    $first_question = $questions[0];
    echo '<p>First question ID: ' . $first_question->ID . '</p>';
    echo '<p>Question title: ' . esc_html($first_question->post_title) . '</p>';
} else {
    echo '<p>No quiz questions found!</p>';
}

// Test 4: Check categories
echo '<h3>Test 4: Quiz Categories</h3>';
$categories = get_terms(array(
    'taxonomy' => 'quiz_category',
    'hide_empty' => false,
));
echo '<p>Total categories: ' . count($categories) . '</p>';
foreach ($categories as $category) {
    echo '<p>- ' . esc_html($category->name) . ' (ID: ' . $category->term_id . ')</p>';
}

// Test 5: Check questions by category
echo '<h3>Test 5: Questions by Category</h3>';
if (!empty($categories)) {
    $first_category = $categories[0];
    $category_questions = get_posts(array(
        'post_type' => 'quiz_question',
        'tax_query' => array(
            array(
                'taxonomy' => 'quiz_category',
                'field' => 'term_id',
                'terms' => $first_category->term_id,
            ),
        ),
        'numberposts' => 5,
        'post_status' => 'publish',
        'suppress_filters' => true,
    ));
    echo '<p>Questions in "' . esc_html($first_category->name) . '": ' . count($category_questions) . '</p>';
}

echo '<hr>';
echo '<p><strong>Test completed.</strong></p>';
echo '<p>If you can see quiz questions and categories above, the access issue has been resolved.</p>';

// Test script to check quiz database tables and AJAX functionality
// Place this in your WordPress root directory and access via browser

// Load WordPress
require_once('wp-load.php');

echo "<h2>Custom Quiz Database Tables Test</h2>";

// Check if tables exist
global $wpdb;

$tables_to_check = array(
    'cqz_assignments' => get_option('cqz_table_assignments', $wpdb->prefix . 'cqz_assignments'),
    'cqz_assignment_questions' => get_option('cqz_table_assignment_questions', $wpdb->prefix . 'cqz_assignment_questions'),
    'cqz_results' => get_option('cqz_table_results', $wpdb->prefix . 'cqz_results'),
    'cqz_categories' => get_option('cqz_table_categories', $wpdb->prefix . 'cqz_categories')
);

echo "<h3>Database Tables Status:</h3>";
foreach ($tables_to_check as $table_name => $full_table_name) {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") == $full_table_name;
    $status = $table_exists ? "✅ EXISTS" : "❌ MISSING";
    echo "<p><strong>$table_name</strong> ($full_table_name): $status</p>";
    
    if ($table_exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
        echo "<p>Records: $count</p>";
    }
}

// Check AJAX actions
echo "<h3>AJAX Actions Registered:</h3>";
global $wp_actions;
$cqz_actions = array();
foreach ($wp_actions as $action => $callbacks) {
    if (strpos($action, 'cqz_') === 0) {
        $cqz_actions[$action] = $callbacks;
    }
}

if (empty($cqz_actions)) {
    echo "<p>❌ No CQZ AJAX actions found</p>";
} else {
    foreach ($cqz_actions as $action => $callbacks) {
        echo "<p><strong>$action</strong>: " . count($callbacks) . " callback(s)</p>";
    }
}

// Check if user is logged in
echo "<h3>User Status:</h3>";
if (is_user_logged_in()) {
    $user = wp_get_current_user();
    echo "<p>✅ Logged in as: " . $user->display_name . " (ID: " . $user->ID . ")</p>";
} else {
    echo "<p>❌ Not logged in</p>";
}

// Check plugin settings
echo "<h3>Plugin Settings:</h3>";
$settings = get_option('cqz_settings', array());
if (empty($settings)) {
    echo "<p>❌ No settings found</p>";
} else {
    echo "<p>✅ Settings found:</p>";
    echo "<ul>";
    foreach ($settings as $key => $value) {
        echo "<li><strong>$key</strong>: " . (is_array($value) ? 'Array' : $value) . "</li>";
    }
    echo "</ul>";
}

// Test AJAX URL
echo "<h3>AJAX URL Test:</h3>";
$ajax_url = admin_url('admin-ajax.php');
echo "<p>AJAX URL: $ajax_url</p>";

// Check for any recent errors
echo "<h3>Recent Errors (if any):</h3>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $recent_errors = array();
    $lines = file($error_log);
    $recent_lines = array_slice($lines, -20); // Last 20 lines
    
    foreach ($recent_lines as $line) {
        if (strpos($line, 'cqz') !== false || strpos($line, 'Custom Quiz') !== false) {
            $recent_errors[] = $line;
        }
    }
    
    if (empty($recent_errors)) {
        echo "<p>✅ No recent CQZ errors found</p>";
    } else {
        echo "<p>❌ Recent CQZ errors:</p>";
        echo "<pre>";
        foreach ($recent_errors as $error) {
            echo htmlspecialchars($error);
        }
        echo "</pre>";
    }
} else {
    echo "<p>⚠️ Error log not accessible</p>";
}

echo "<hr>";
echo "<p><strong>Test completed.</strong> If you see any ❌ marks, those indicate potential issues.</p>";
?> 