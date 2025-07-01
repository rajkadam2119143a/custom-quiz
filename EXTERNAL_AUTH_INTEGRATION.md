# External Authentication Integration Guide

This guide explains how to integrate your external authentication system with the WordPress Custom Quiz System.

## 🔐 Overview

The Custom Quiz System supports integration with external authentication systems through a flexible API-based approach. This allows you to:

- Authenticate users against your external system
- Synchronize user data between systems
- Maintain single sign-on (SSO) capabilities
- Preserve existing user management workflows

## 🏗️ Architecture

### Integration Components

1. **Authentication Class**: Handles external API communication
2. **User Synchronization**: Syncs user data between systems
3. **Session Management**: Manages user sessions across platforms
4. **Security Layer**: Ensures secure data transmission

### Data Flow

```
External Auth System ←→ WordPress Quiz System
         ↓                        ↓
    User Validation          Quiz Access
         ↓                        ↓
    Session Creation         Result Storage
         ↓                        ↓
    User Sync               Performance Tracking
```

## 📋 Prerequisites

### External System Requirements

- **REST API Endpoints**: For authentication and user data
- **API Authentication**: Secure token-based authentication
- **User Data Schema**: Consistent user data structure
- **HTTPS Support**: Secure data transmission

### WordPress Requirements

- **Custom Authentication Class**: For external system integration
- **User Meta Storage**: For external user data
- **Session Management**: For cross-platform sessions
- **Security Measures**: For data protection

## 🚀 Implementation

### Step 1: Create Authentication Class

Create a new file `includes/class-cqz-external-auth.php`:

```php
<?php
namespace CustomQuiz;

if (!defined('ABSPATH')) {
    exit;
}

class CQZ_External_Auth {
    
    private $api_url;
    private $api_key;
    private $api_secret;
    private $timeout = 30;
    
    public function __construct() {
        $this->api_url = get_option('cqz_external_auth_api_url');
        $this->api_key = get_option('cqz_external_auth_api_key');
        $this->api_secret = get_option('cqz_external_auth_api_secret');
        
        add_action('wp_ajax_cqz_external_login', array($this, 'handle_external_login'));
        add_action('wp_ajax_nopriv_cqz_external_login', array($this, 'handle_external_login'));
        add_action('wp_ajax_cqz_external_logout', array($this, 'handle_external_logout'));
        add_action('wp_ajax_nopriv_cqz_external_logout', array($this, 'handle_external_logout'));
    }
    
    /**
     * Authenticate user with external system
     */
    public function authenticate_user($username, $password) {
        $response = $this->make_api_request('POST', '/auth/login', array(
            'username' => $username,
            'password' => $password
        ));
        
        if ($response && isset($response['success']) && $response['success']) {
            return $this->process_auth_response($response);
        }
        
        return false;
    }
    
    /**
     * Validate external session token
     */
    public function validate_session($token) {
        $response = $this->make_api_request('POST', '/auth/validate', array(
            'token' => $token
        ));
        
        return $response && isset($response['valid']) && $response['valid'];
    }
    
    /**
     * Get user data from external system
     */
    public function get_user_data($user_id) {
        $response = $this->make_api_request('GET', "/users/{$user_id}");
        
        if ($response && isset($response['user'])) {
            return $response['user'];
        }
        
        return false;
    }
    
    /**
     * Sync user data between systems
     */
    public function sync_user_data($external_user_data) {
        $wp_user_id = $this->get_wp_user_by_external_id($external_user_data['id']);
        
        if (!$wp_user_id) {
            // Create new WordPress user
            $wp_user_id = $this->create_wp_user($external_user_data);
        } else {
            // Update existing WordPress user
            $this->update_wp_user($wp_user_id, $external_user_data);
        }
        
        return $wp_user_id;
    }
    
    /**
     * Handle external login AJAX request
     */
    public function handle_external_login() {
        check_ajax_referer('cqz_external_auth', 'nonce');
        
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            wp_send_json_error('Username and password are required.');
        }
        
        $auth_result = $this->authenticate_user($username, $password);
        
        if ($auth_result) {
            wp_send_json_success(array(
                'message' => 'Login successful',
                'redirect_url' => home_url('/quiz-assessment/')
            ));
        } else {
            wp_send_json_error('Invalid credentials.');
        }
    }
    
    /**
     * Handle external logout AJAX request
     */
    public function handle_external_logout() {
        check_ajax_referer('cqz_external_auth', 'nonce');
        
        $token = sanitize_text_field($_POST['token']);
        
        // Logout from external system
        $this->make_api_request('POST', '/auth/logout', array(
            'token' => $token
        ));
        
        // Clear WordPress session
        wp_logout();
        
        wp_send_json_success(array(
            'message' => 'Logout successful',
            'redirect_url' => home_url('/')
        ));
    }
    
    /**
     * Make API request to external system
     */
    private function make_api_request($method, $endpoint, $data = array()) {
        $url = rtrim($this->api_url, '/') . $endpoint;
        
        $headers = array(
            'Content-Type: application/json',
            'X-API-Key: ' . $this->api_key,
            'X-API-Secret: ' . $this->api_secret
        );
        
        $args = array(
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => $headers,
            'sslverify' => true
        );
        
        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('External Auth API Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            error_log("External Auth API Error: HTTP {$status_code} - {$body}");
            return false;
        }
        
        return json_decode($body, true);
    }
    
    /**
     * Process authentication response
     */
    private function process_auth_response($response) {
        if (!isset($response['user']) || !isset($response['token'])) {
            return false;
        }
        
        $external_user_data = $response['user'];
        $token = $response['token'];
        
        // Sync user data with WordPress
        $wp_user_id = $this->sync_user_data($external_user_data);
        
        if (!$wp_user_id) {
            return false;
        }
        
        // Store external session data
        update_user_meta($wp_user_id, '_cqz_external_token', $token);
        update_user_meta($wp_user_id, '_cqz_external_user_id', $external_user_data['id']);
        update_user_meta($wp_user_id, '_cqz_external_session_expires', time() + 3600);
        
        // Log in the user
        wp_set_current_user($wp_user_id);
        wp_set_auth_cookie($wp_user_id, true);
        
        return array(
            'wp_user_id' => $wp_user_id,
            'external_token' => $token,
            'user_data' => $external_user_data
        );
    }
    
    /**
     * Get WordPress user by external ID
     */
    private function get_wp_user_by_external_id($external_id) {
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = '_cqz_external_user_id' 
             AND meta_value = %s",
            $external_id
        ));
        
        return $user_id ? intval($user_id) : false;
    }
    
    /**
     * Create WordPress user from external data
     */
    private function create_wp_user($external_user_data) {
        $username = sanitize_user($external_user_data['username'] ?? $external_user_data['email']);
        $email = sanitize_email($external_user_data['email']);
        $first_name = sanitize_text_field($external_user_data['first_name'] ?? '');
        $last_name = sanitize_text_field($external_user_data['last_name'] ?? '');
        
        // Generate random password for external users
        $password = wp_generate_password(12, false);
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return false;
        }
        
        // Update user meta
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name)
        ));
        
        // Store external user data
        update_user_meta($user_id, '_cqz_external_user_id', $external_user_data['id']);
        update_user_meta($user_id, '_cqz_external_data', $external_user_data);
        
        return $user_id;
    }
    
    /**
     * Update WordPress user with external data
     */
    private function update_wp_user($wp_user_id, $external_user_data) {
        $first_name = sanitize_text_field($external_user_data['first_name'] ?? '');
        $last_name = sanitize_text_field($external_user_data['last_name'] ?? '');
        
        wp_update_user(array(
            'ID' => $wp_user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name)
        ));
        
        // Update external user data
        update_user_meta($wp_user_id, '_cqz_external_data', $external_user_data);
    }
}
```

### Step 2: Create Frontend Login Form

Create a new file `assets/js/external-auth.js`:

```javascript
/**
 * External Authentication JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // External login form handler
    $('#cqz-external-login-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.text();
        
        // Disable form and show loading
        form.find('input').prop('disabled', true);
        submitBtn.prop('disabled', true).text('Logging in...');
        
        // Get form data
        const formData = {
            action: 'cqz_external_login',
            nonce: cqz_external_auth.nonce,
            username: form.find('input[name="username"]').val(),
            password: form.find('input[name="password"]').val()
        };
        
        // Make AJAX request
        $.post(cqz_external_auth.ajax_url, formData, function(response) {
            if (response.success) {
                showNotification(response.data.message, 'success');
                
                // Redirect after short delay
                setTimeout(function() {
                    window.location.href = response.data.redirect_url;
                }, 1500);
            } else {
                showNotification(response.data, 'error');
                
                // Re-enable form
                form.find('input').prop('disabled', false);
                submitBtn.prop('disabled', false).text(originalText);
            }
        }).fail(function() {
            showNotification('Login failed. Please try again.', 'error');
            
            // Re-enable form
            form.find('input').prop('disabled', false);
            submitBtn.prop('disabled', false).text(originalText);
        });
    });
    
    // External logout handler
    $('.cqz-external-logout').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to logout?')) {
            return;
        }
        
        const token = cqz_external_auth.token;
        
        $.post(cqz_external_auth.ajax_url, {
            action: 'cqz_external_logout',
            nonce: cqz_external_auth.nonce,
            token: token
        }, function(response) {
            if (response.success) {
                showNotification(response.data.message, 'success');
                
                // Redirect after short delay
                setTimeout(function() {
                    window.location.href = response.data.redirect_url;
                }, 1500);
            } else {
                showNotification(response.data, 'error');
            }
        }).fail(function() {
            showNotification('Logout failed. Please try again.', 'error');
        });
    });
    
    // Show notification
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="cqz-notification cqz-notification-${type}">
                <span>${message}</span>
                <button class="cqz-notification-close">&times;</button>
            </div>
        `);
        
        $('body').append(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Manual close
        notification.find('.cqz-notification-close').on('click', function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        });
    }
});
```

### Step 3: Create Admin Settings

Add external authentication settings to the admin panel:

```php
// Add to class-cqz-settings.php

public function add_external_auth_settings($settings) {
    $settings['external_auth'] = array(
        'title' => __('External Authentication', 'custom-quiz'),
        'fields' => array(
            'external_auth_enabled' => array(
                'label' => __('Enable External Authentication', 'custom-quiz'),
                'type' => 'checkbox',
                'default' => false,
                'description' => __('Enable integration with external authentication system', 'custom-quiz')
            ),
            'external_auth_api_url' => array(
                'label' => __('API URL', 'custom-quiz'),
                'type' => 'text',
                'default' => '',
                'description' => __('Base URL for external authentication API', 'custom-quiz'),
                'placeholder' => 'https://api.example.com'
            ),
            'external_auth_api_key' => array(
                'label' => __('API Key', 'custom-quiz'),
                'type' => 'text',
                'default' => '',
                'description' => __('API key for external authentication system', 'custom-quiz')
            ),
            'external_auth_api_secret' => array(
                'label' => __('API Secret', 'custom-quiz'),
                'type' => 'password',
                'default' => '',
                'description' => __('API secret for external authentication system', 'custom-quiz')
            ),
            'external_auth_sync_interval' => array(
                'label' => __('Sync Interval (minutes)', 'custom-quiz'),
                'type' => 'number',
                'default' => 60,
                'description' => __('How often to sync user data with external system', 'custom-quiz')
            ),
            'external_auth_session_timeout' => array(
                'label' => __('Session Timeout (minutes)', 'custom-quiz'),
                'type' => 'number',
                'default' => 120,
                'description' => __('Session timeout for external authentication', 'custom-quiz')
            )
        )
    );
    
    return $settings;
}
```

### Step 4: Create Login Form Template

Create a login form template for external authentication:

```php
// Add to class-cqz-frontend.php

public function render_external_login_form() {
    ob_start();
    ?>
    <div class="cqz-external-login-container">
        <div class="cqz-login-card">
            <h2>Login to Access Quiz</h2>
            <p>Please enter your credentials to access the assessment.</p>
            
            <form id="cqz-external-login-form" class="cqz-login-form">
                <div class="cqz-form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="cqz-form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="cqz-btn cqz-btn-primary">
                    Login
                </button>
            </form>
            
            <div class="cqz-login-footer">
                <p>Having trouble? Contact your administrator.</p>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
```

### Step 5: Add CSS Styles

Add styles for the external login form:

```css
/* Add to assets/css/frontend.css */

.cqz-external-login-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
}

.cqz-login-card {
    background: #fff;
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    max-width: 400px;
    width: 100%;
}

.cqz-login-card h2 {
    text-align: center;
    margin-bottom: 10px;
    color: #333;
    font-size: 1.8rem;
    font-weight: 600;
}

.cqz-login-card p {
    text-align: center;
    color: #666;
    margin-bottom: 30px;
}

.cqz-login-form {
    margin-bottom: 20px;
}

.cqz-form-group {
    margin-bottom: 20px;
}

.cqz-form-group label {
    display: block;
    margin-bottom: 8px;
    color: #333;
    font-weight: 500;
}

.cqz-form-group input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s ease;
}

.cqz-form-group input:focus {
    outline: none;
    border-color: #228be6;
    box-shadow: 0 0 0 3px rgba(34, 139, 230, 0.1);
}

.cqz-login-form .cqz-btn {
    width: 100%;
    padding: 14px;
    font-size: 16px;
    font-weight: 600;
}

.cqz-login-footer {
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}

.cqz-login-footer p {
    margin: 0;
    color: #666;
    font-size: 14px;
}
```

## 🔒 Security Considerations

### API Security

1. **HTTPS Only**: All API communication must use HTTPS
2. **API Key Rotation**: Regularly rotate API keys and secrets
3. **Rate Limiting**: Implement rate limiting on API endpoints
4. **Input Validation**: Validate all inputs on both systems
5. **Token Expiration**: Set appropriate token expiration times

### Data Protection

1. **Encryption**: Encrypt sensitive data in transit and at rest
2. **Access Control**: Implement proper access controls
3. **Audit Logging**: Log all authentication attempts
4. **Session Management**: Properly manage session lifecycle
5. **Data Minimization**: Only store necessary user data

### WordPress Security

1. **Nonce Verification**: Use WordPress nonces for all forms
2. **Capability Checks**: Verify user capabilities
3. **Input Sanitization**: Sanitize all user inputs
4. **Output Escaping**: Escape all output
5. **Database Security**: Use prepared statements

## 🔧 Configuration

### Environment Variables

Set these environment variables for production:

```bash
# External Authentication API
CQZ_EXTERNAL_AUTH_API_URL=https://api.example.com
CQZ_EXTERNAL_AUTH_API_KEY=your_api_key
CQZ_EXTERNAL_AUTH_API_SECRET=your_api_secret

# Security Settings
CQZ_SESSION_TIMEOUT=7200
CQZ_SYNC_INTERVAL=3600
```

### WordPress Configuration

Add to `wp-config.php`:

```php
// External Authentication Settings
define('CQZ_EXTERNAL_AUTH_ENABLED', true);
define('CQZ_EXTERNAL_AUTH_API_URL', 'https://api.example.com');
define('CQZ_EXTERNAL_AUTH_API_KEY', 'your_api_key');
define('CQZ_EXTERNAL_AUTH_API_SECRET', 'your_api_secret');
```

## 🧪 Testing

### Unit Tests

Create tests for authentication functionality:

```php
// tests/test-external-auth.php

class Test_CQZ_External_Auth extends WP_UnitTestCase {
    
    public function test_authenticate_user_success() {
        $auth = new CQZ_External_Auth();
        $result = $auth->authenticate_user('testuser', 'password');
        
        $this->assertNotFalse($result);
        $this->assertArrayHasKey('wp_user_id', $result);
        $this->assertArrayHasKey('external_token', $result);
    }
    
    public function test_authenticate_user_failure() {
        $auth = new CQZ_External_Auth();
        $result = $auth->authenticate_user('invalid', 'wrong');
        
        $this->assertFalse($result);
    }
    
    public function test_validate_session() {
        $auth = new CQZ_External_Auth();
        $result = $auth->validate_session('valid_token');
        
        $this->assertTrue($result);
    }
}
```

### Integration Tests

Test the complete authentication flow:

```php
// tests/test-integration.php

class Test_CQZ_Integration extends WP_UnitTestCase {
    
    public function test_complete_auth_flow() {
        // Test login
        $response = $this->make_login_request('testuser', 'password');
        $this->assertEquals(200, $response['status']);
        
        // Test quiz access
        $quiz_response = $this->make_quiz_request($response['token']);
        $this->assertEquals(200, $quiz_response['status']);
        
        // Test logout
        $logout_response = $this->make_logout_request($response['token']);
        $this->assertEquals(200, $logout_response['status']);
    }
}
```

## 📊 Monitoring

### Logging

Implement comprehensive logging:

```php
// Add to external auth class

private function log_auth_event($event, $data = array()) {
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'event' => $event,
        'user_ip' => $this->get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'data' => $data
    );
    
    error_log('CQZ External Auth: ' . json_encode($log_entry));
}
```

### Metrics

Track authentication metrics:

```php
// Add to external auth class

private function track_auth_metric($metric, $value = 1) {
    $current = get_option("cqz_auth_metric_{$metric}", 0);
    update_option("cqz_auth_metric_{$metric}", $current + $value);
}
```

## 🚨 Troubleshooting

### Common Issues

1. **API Connection Failures**
   - Check API URL and credentials
   - Verify network connectivity
   - Check firewall settings

2. **User Sync Issues**
   - Verify user data format
   - Check database permissions
   - Review error logs

3. **Session Problems**
   - Check session timeout settings
   - Verify token validation
   - Review session storage

### Debug Mode

Enable debug mode for detailed logging:

```php
// Add to wp-config.php
define('CQZ_EXTERNAL_AUTH_DEBUG', true);
```

## 📈 Performance Optimization

### Caching

Implement caching for user data:

```php
// Cache user data for 5 minutes
$cached_user = wp_cache_get("external_user_{$external_id}");
if (!$cached_user) {
    $cached_user = $this->get_user_data($external_id);
    wp_cache_set("external_user_{$external_id}", $cached_user, '', 300);
}
```

### Database Optimization

Optimize database queries:

```php
// Use prepared statements
$stmt = $wpdb->prepare(
    "SELECT user_id FROM {$wpdb->usermeta} 
     WHERE meta_key = %s AND meta_value = %s",
    '_cqz_external_user_id',
    $external_id
);
```

## 🔄 Maintenance

### Regular Tasks

1. **Monitor API Health**: Check API availability regularly
2. **Review Logs**: Monitor authentication logs for issues
3. **Update Credentials**: Rotate API keys periodically
4. **Clean Old Data**: Remove expired sessions and tokens
5. **Backup Configuration**: Backup authentication settings

### Updates

1. **API Changes**: Monitor external API changes
2. **Security Updates**: Apply security patches promptly
3. **Performance Monitoring**: Track authentication performance
4. **User Feedback**: Collect and address user feedback

---

This integration guide provides a comprehensive approach to connecting your external authentication system with the WordPress Custom Quiz System while maintaining security and performance standards. 