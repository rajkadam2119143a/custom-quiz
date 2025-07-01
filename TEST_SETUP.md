# Quiz Assessment System - Test Setup Guide

This guide will help you verify that all components of the quiz_assessment system are working correctly.

## Pre-Test Checklist

### 1. Plugin Activation
- [ ] Plugin is activated in WordPress admin
- [ ] No PHP errors in error log
- [ ] Database tables are created (check wp_cqz_* tables)

### 2. Database Tables Verification
Run these SQL queries to verify tables exist:
```sql
SHOW TABLES LIKE 'wp_cqz_%';
```

Expected tables:
- wp_cqz_assignments
- wp_cqz_assignment_questions  
- wp_cqz_results
- wp_cqz_categories

### 3. Settings Configuration
Go to Quiz System > Settings and verify:
- [ ] Time Limit is set (default: 120 minutes)
- [ ] Questions per Quiz is set (default: 40)
- [ ] Quiz Title is configured
- [ ] Welcome Message is set

### 4. Questions Import
- [ ] Import questions using Quiz System > Import
- [ ] Verify questions appear in Quiz Questions admin
- [ ] Check that categories are properly assigned

## Test Scenarios

### Test 1: Basic Shortcode Display
1. Create a new page
2. Add shortcode: `[quiz_assessment]`
3. View the page
4. **Expected Result**: Welcome page displays with quiz information

### Test 2: Quiz Start Functionality
1. On the welcome page, click "Start Quiz Assessment"
2. **Expected Result**: 
   - AJAX request succeeds
   - Quiz interface loads
   - Timer starts
   - Questions are displayed

### Test 3: Answer Saving
1. Answer a few questions
2. **Expected Result**:
   - Status badges update
   - Progress bar updates
   - Answers are saved via AJAX

### Test 4: Quiz Submission
1. Complete the quiz or let time expire
2. **Expected Result**:
   - Quiz submits successfully
   - Results page displays
   - URL updates to show results

### Test 5: URL State Management
1. Start a quiz
2. Copy the URL with ?action=quizstart&assignment_id=X
3. Open in new tab
4. **Expected Result**: Quiz interface loads with same state

## Debug Information

### Check JavaScript Console
Open browser developer tools and check for:
- JavaScript errors
- AJAX request failures
- Console.log messages

### Check Network Tab
Monitor AJAX requests for:
- `cqz_start_assessment`
- `cqz_save_answer`
- `cqz_submit_quiz`

### Check WordPress Debug Log
Enable debug logging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for PHP errors.

## Common Issues and Solutions

### Issue: Questions not loading
**Solution**: 
1. Check if questions are imported
2. Verify categories exist
3. Check database connectivity

### Issue: Timer not working
**Solution**:
1. Check JavaScript console for errors
2. Verify frontend.js is loaded
3. Check for jQuery conflicts

### Issue: AJAX requests failing
**Solution**:
1. Check nonce verification
2. Verify AJAX URL is correct
3. Check WordPress permalink settings

### Issue: Results not showing
**Solution**:
1. Check database for saved results
2. Verify result rendering method
3. Check for JavaScript errors

## Performance Testing

### Load Testing
1. Create multiple user accounts
2. Start quizzes simultaneously
3. Monitor database performance
4. Check for memory leaks

### Browser Compatibility
Test in:
- [ ] Chrome
- [ ] Firefox
- [ ] Safari
- [ ] Edge
- [ ] Mobile browsers

## Security Testing

### Input Validation
1. Try submitting malformed data
2. Test XSS prevention
3. Verify nonce protection
4. Check SQL injection prevention

### User Permissions
1. Test with different user roles
2. Verify access controls
3. Check data isolation between users

## Success Criteria

The system is working correctly if:
- [ ] Welcome page displays properly
- [ ] Quiz starts without errors
- [ ] Questions load and display correctly
- [ ] Answers save successfully
- [ ] Timer works accurately
- [ ] Quiz submits properly
- [ ] Results display correctly
- [ ] URL state management works
- [ ] No JavaScript errors
- [ ] No PHP errors in logs
- [ ] Database operations succeed
- [ ] AJAX requests complete successfully

## Support

If you encounter issues:
1. Check the debug log
2. Review browser console
3. Verify database connectivity
4. Test with default WordPress theme
5. Disable other plugins temporarily 