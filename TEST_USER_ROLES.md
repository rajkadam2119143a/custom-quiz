# User Role Testing Guide

## Test Steps to Verify Quiz Works for All User Roles

### 1. Prerequisites
- Ensure you have quiz questions imported in the database
- Ensure you have quiz categories set up
- Ensure the quiz settings are configured

### 2. Test Each User Role

#### Test Subscriber Role
1. Create a subscriber user account
2. Log in as subscriber
3. Navigate to a page with `[quiz_assessment]` shortcode
4. **Expected**: Should see welcome screen
5. Click "Start Quiz Assessment"
6. **Expected**: Should start quiz and show questions
7. Answer questions and submit
8. **Expected**: Should see results

#### Test Author Role
1. Create an author user account
2. Log in as author
3. Follow same steps as subscriber
4. **Expected**: Should work exactly the same

#### Test Editor Role
1. Create an editor user account
2. Log in as editor
3. Follow same steps as subscriber
4. **Expected**: Should work exactly the same

#### Test Administrator Role
1. Log in as administrator
2. Follow same steps as subscriber
3. **Expected**: Should work exactly the same

### 3. Debugging Steps

If the quiz is not working for non-admin users:

#### Check Database
```sql
-- Check if questions exist
SELECT COUNT(*) FROM wp_posts WHERE post_type = 'quiz_question' AND post_status = 'publish';

-- Check if categories exist
SELECT COUNT(*) FROM wp_terms WHERE term_id IN (SELECT term_id FROM wp_term_taxonomy WHERE taxonomy = 'quiz_category');

-- Check if questions are categorized
SELECT COUNT(*) FROM wp_term_relationships tr 
JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
JOIN wp_posts p ON tr.object_id = p.ID 
WHERE tt.taxonomy = 'quiz_category' AND p.post_type = 'quiz_question' AND p.post_status = 'publish';
```

#### Check User Capabilities
```php
// Add this to your theme's functions.php temporarily
add_action('wp_footer', function() {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        echo '<div style="background:white;padding:10px;margin:10px;border:1px solid #ccc;">';
        echo 'User ID: ' . $user->ID . '<br>';
        echo 'User Role: ' . implode(', ', $user->roles) . '<br>';
        echo 'Can Read: ' . (current_user_can('read') ? 'Yes' : 'No') . '<br>';
        echo 'Can Read Posts: ' . (current_user_can('read_post', 1) ? 'Yes' : 'No') . '<br>';
        echo '</div>';
    }
});
```

#### Check Quiz Questions Access
```php
// Add this to your theme's functions.php temporarily
add_action('wp_footer', function() {
    $questions = get_posts(array(
        'post_type' => 'quiz_question',
        'numberposts' => 5,
        'post_status' => 'publish'
    ));
    
    echo '<div style="background:white;padding:10px;margin:10px;border:1px solid #ccc;">';
    echo 'Available Questions: ' . count($questions) . '<br>';
    foreach ($questions as $question) {
        echo 'Question ID: ' . $question->ID . ' - Title: ' . $question->post_title . '<br>';
    }
    echo '</div>';
});
```

### 4. Common Issues and Solutions

#### Issue: No Questions Found
**Cause**: Questions not imported or not published
**Solution**: 
1. Import questions via admin panel
2. Ensure questions are published
3. Ensure questions are categorized

#### Issue: Permission Denied
**Cause**: Post type not publicly readable
**Solution**: 
1. Check post type registration
2. Ensure `public` is set to `true`
3. Ensure proper capabilities are set

#### Issue: AJAX Errors
**Cause**: Nonce issues or capability problems
**Solution**:
1. Check browser console for errors
2. Verify nonce is being generated correctly
3. Ensure AJAX handlers are registered for logged-in users

#### Issue: Database Errors
**Cause**: Tables not created or permissions
**Solution**:
1. Deactivate and reactivate plugin
2. Check database table existence
3. Verify database permissions

### 5. Expected Behavior for Each Role

| Role | Can Access Quiz | Can Take Quiz | Can See Results | Admin Access |
|------|----------------|---------------|-----------------|--------------|
| Subscriber | ✅ Yes | ✅ Yes | ✅ Yes | ❌ No |
| Author | ✅ Yes | ✅ Yes | ✅ Yes | ❌ No |
| Editor | ✅ Yes | ✅ Yes | ✅ Yes | ❌ No |
| Administrator | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Yes |

### 6. Troubleshooting Commands

#### Check Plugin Status
```bash
# Check if plugin is active
wp plugin list --status=active | grep custom-quiz

# Check plugin files
ls -la wp-content/plugins/custom-quiz/
```

#### Check Database Tables
```sql
-- Check if tables exist
SHOW TABLES LIKE 'wp_cqz_%';

-- Check table structure
DESCRIBE wp_cqz_assignments;
DESCRIBE wp_cqz_assignment_questions;
DESCRIBE wp_cqz_results;
```

#### Check WordPress Settings
```php
// Check if settings are saved
$settings = get_option('cqz_settings');
var_dump($settings);

// Check if tables are registered
echo get_option('cqz_table_assignments');
echo get_option('cqz_table_assignment_questions');
echo get_option('cqz_table_results');
```

### 7. Final Verification

After fixing any issues, test the complete flow:

1. **Subscriber User**:
   - Login → Welcome Screen → Start Quiz → Answer Questions → Submit → Results

2. **Author User**:
   - Same flow as subscriber

3. **Editor User**:
   - Same flow as subscriber

4. **Admin User**:
   - Same flow as other users + admin panel access

If all roles can complete this flow successfully, the quiz system is working correctly for all user roles. 