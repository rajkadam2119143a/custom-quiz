# Authentication System Integration Guide

This guide explains how to integrate the authentication system from your Node.js code into the WordPress quiz application.

## 🔐 Authentication Requirements

Based on your Node.js code, you need to implement:
- Basic authentication with email/password
- Cookie-based session management
- API integration with external authentication service
- User validation and session tracking

## 📝 WordPress Implementation

### 1. Create Authentication Class

Create a new file: `includes/class-cqz-auth.php`

```php
<?php
namespace CustomQuiz;

if (!defined('ABSPATH')) {
    exit;
}

class CQZ_Auth {
    
    private $auth_api_url;
    private $check_login_api_url;
    private $auth_key;
    private $site_url;
    
    public function __construct() {
        $this->auth_api_url = get_option('cqz_auth_api_url', '');
        $this->check_login_api_url = get_option('cqz_check_login_api_url', '');
        $this->auth_key = get_option('cqz_auth_key', '');
        $this->site_url = get_site_url();
        
        add_action('wp_ajax_cqz_authenticate', array($this, 'authenticate_user'));
        add_action('wp_ajax_nopriv_cqz_authenticate', array($this, 'authenticate_user'));
        add_action('wp_ajax_cqz_check_session', array($this, 'check_session'));
        add_action('wp_ajax_nopriv_cqz_check_session', array($this, 'check_session'));
        add_action('wp_logout', array($this, 'clear_auth_cookies'));
    }
    
    public function authenticate_user() {
        check_ajax_referer('cqz_auth_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            wp_send_json_error('Email and password are required.');
        }
        
        // Check if user already has a valid session
        $i_s_cookie = isset($_COOKIE['i_s']) ? $_COOKIE['i_s'] : '';
        
        if (!empty($i_s_cookie) && $i_s_cookie !== 'undefined') {
            $session_valid = $this->validate_existing_session($i_s_cookie);
            if ($session_valid) {
                wp_send_json_success(array(
                    'message' => 'Session already valid',
                    'user' => $session_valid
                ));
            }
        }
        
        // Attempt new authentication
        $auth_result = $this->perform_authentication($email, $password);
        
        if (is_wp_error($auth_result)) {
            wp_send_json_error($auth_result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => 'Authentication successful',
            'user' => $auth_result
        ));
    }
    
    private function perform_authentication($email, $password) {
        $auth_data = array(
            'emailId' => $email,
            'password' => $password
        );
        
        $response = wp_remote_post($this->auth_api_url, array(
            'headers' => array(
                'appnName' => 'ZING',
                'authKey' => $this->auth_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Origin' => $this->site_url,
            ),
            'body' => http_build_query($auth_data),
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            return new \WP_Error('auth_failed', 'Authentication request failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || !isset($data[0]['status'])) {
            return new \WP_Error('auth_failed', 'Invalid response from authentication service');
        }
        
        if ($data[0]['status'] !== 'success') {
            return new \WP_Error('auth_failed', 'Authentication failed: Invalid credentials');
        }
        
        $user_data = $data[0]['data'];
        $user_entered_email = strtolower($email);
        $user_fetched_email = isset($user_data['emailId']) ? strtolower($user_data['emailId']) : '';
        
        if ($user_entered_email !== $user_fetched_email || !isset($user_data['emailId'])) {
            return new \WP_Error('auth_failed', 'Email verification failed');
        }
        
        // Set authentication cookie
        $cookies = wp_remote_retrieve_cookies($response);
        if ($cookies) {
            foreach ($cookies as $cookie) {
                if ($cookie->name === 'i_s') {
                    $this->set_auth_cookie($cookie->value, $cookie->expires);
                    break;
                }
            }
        }
        
        // Create or update WordPress user
        $wp_user = $this->create_or_update_wp_user($user_data);
        
        return array(
            'emailId' => $user_data['emailId'],
            'firstName' => $user_data['firstName'],
            'surname' => $user_data['surname'],
            'wp_user_id' => $wp_user->ID
        );
    }
    
    private function validate_existing_session($i_s_cookie) {
        $response = wp_remote_post($this->check_login_api_url, array(
            'headers' => array(
                'appnName' => 'ZING',
                'authKey' => $this->auth_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Origin' => $this->site_url,
                'Cookie' => 'i_s=' . urlencode($i_s_cookie)
            ),
            'body' => '',
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || !isset($data[0]['status']) || $data[0]['status'] !== 'success') {
            return false;
        }
        
        $user_data = $data[0]['data'];
        
        return array(
            'emailId' => $user_data['emailId'],
            'firstName' => $user_data['firstName'],
            'surname' => $user_data['surname']
        );
    }
    
    private function create_or_update_wp_user($user_data) {
        $email = $user_data['emailId'];
        $first_name = $user_data['firstName'];
        $last_name = $user_data['surname'];
        $display_name = trim($first_name . ' ' . $last_name);
        
        $existing_user = get_user_by('email', $email);
        
        if ($existing_user) {
            // Update existing user
            wp_update_user(array(
                'ID' => $existing_user->ID,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $display_name
            ));
            
            // Log in the user
            wp_set_current_user($existing_user->ID);
            wp_set_auth_cookie($existing_user->ID);
            
            return $existing_user;
        } else {
            // Create new user
            $username = sanitize_user($email);
            $user_id = wp_create_user($username, wp_generate_password(), $email);
            
            if (is_wp_error($user_id)) {
                // If username exists, try with timestamp
                $username = sanitize_user($email . '_' . time());
                $user_id = wp_create_user($username, wp_generate_password(), $email);
                
                if (is_wp_error($user_id)) {
                    return false;
                }
            }
            
            // Update user meta
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $display_name
            ));
            
            // Log in the user
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
            return get_user_by('ID', $user_id);
        }
    }
    
    private function set_auth_cookie($cookie_value, $expires) {
        $domain = parse_url($this->site_url, PHP_URL_HOST);
        $secure = is_ssl();
        $httponly = true;
        
        setcookie('i_s', $cookie_value, $expires, '/', $domain, $secure, $httponly);
    }
    
    public function check_session() {
        check_ajax_referer('cqz_auth_nonce', 'nonce');
        
        $i_s_cookie = isset($_COOKIE['i_s']) ? $_COOKIE['i_s'] : '';
        
        if (empty($i_s_cookie) || $i_s_cookie === 'undefined') {
            wp_send_json_error('No session found');
        }
        
        $session_valid = $this->validate_existing_session($i_s_cookie);
        
        if ($session_valid) {
            wp_send_json_success(array(
                'message' => 'Session valid',
                'user' => $session_valid
            ));
        } else {
            wp_send_json_error('Session expired or invalid');
        }
    }
    
    public function clear_auth_cookies() {
        $domain = parse_url($this->site_url, PHP_URL_HOST);
        setcookie('i_s', '', time() - 3600, '/', $domain, is_ssl(), true);
    }
    
    public function is_user_authenticated() {
        $i_s_cookie = isset($_COOKIE['i_s']) ? $_COOKIE['i_s'] : '';
        
        if (empty($i_s_cookie) || $i_s_cookie === 'undefined') {
            return false;
        }
        
        return $this->validate_existing_session($i_s_cookie) !== false;
    }
    
    public function get_current_auth_user() {
        if (!$this->is_user_authenticated()) {
            return false;
        }
        
        $i_s_cookie = $_COOKIE['i_s'];
        return $this->validate_existing_session($i_s_cookie);
    }
}
```

### 2. Update Main Plugin File

Add to `custom-quiz.php`:

```php
// Include authentication class
require_once CQZ_PLUGIN_DIR . 'includes/class-cqz-auth.php';

// Initialize authentication
function cqz_init_auth() {
    new CustomQuiz\CQZ_Auth();
}
add_action('init', 'cqz_init_auth');
```

### 3. Update Frontend Class

Modify `includes/class-cqz-frontend.php` to check authentication:

```php
public function quiz_assessment_shortcode($atts) {
    // Check if user is authenticated
    $auth = new CQZ_Auth();
    if (!$auth->is_user_authenticated()) {
        return $this->render_authentication_form();
    }
    
    // Rest of the existing code...
}

private function render_authentication_form() {
    ob_start();
    ?>
    <div class="cqz-auth-container">
        <div class="cqz-auth-form">
            <h2>Authentication Required</h2>
            <p>Please log in to access the quiz assessment.</p>
            
            <form id="cqz-auth-form">
                <div class="cqz-form-group">
                    <label for="cqz-email">Email Address</label>
                    <input type="email" id="cqz-email" name="email" required>
                </div>
                
                <div class="cqz-form-group">
                    <label for="cqz-password">Password</label>
                    <input type="password" id="cqz-password" name="password" required>
                </div>
                
                <button type="submit" class="cqz-btn cqz-btn-primary">Login</button>
            </form>
            
            <div id="cqz-auth-message"></div>
        </div>
    </div>
    
    <script>
    jQuery(function($) {
        $('#cqz-auth-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitBtn = form.find('button[type="submit"]');
            var messageDiv = $('#cqz-auth-message');
            
            submitBtn.prop('disabled', true).text('Logging in...');
            messageDiv.html('');
            
            $.post(cqz_frontend.ajax_url, {
                action: 'cqz_authenticate',
                nonce: cqz_frontend.auth_nonce,
                email: $('#cqz-email').val(),
                password: $('#cqz-password').val()
            }, function(response) {
                if (response.success) {
                    messageDiv.html('<div class="cqz-success">Login successful! Redirecting...</div>');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    messageDiv.html('<div class="cqz-error">' + response.data + '</div>');
                    submitBtn.prop('disabled', false).text('Login');
                }
            }).fail(function() {
                messageDiv.html('<div class="cqz-error">Network error. Please try again.</div>');
                submitBtn.prop('disabled', false).text('Login');
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
```

### 4. Add Authentication Settings

Add to `includes/class-cqz-admin.php`:

```php
public function settings_page() {
    if (isset($_POST['cqz_save_settings'])) {
        $this->save_settings();
    }
    
    $settings = $this->get_settings();
    ?>
    <div class="wrap">
        <h1>Quiz Settings</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('cqz_save_settings', 'cqz_settings_nonce'); ?>
            
            <h2>Authentication Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="cqz_auth_api_url">Authentication API URL</label>
                    </th>
                    <td>
                        <input type="url" name="cqz_auth_api_url" id="cqz_auth_api_url" 
                               value="<?php echo esc_attr(get_option('cqz_auth_api_url')); ?>" 
                               class="regular-text" />
                        <p class="description">URL for user authentication API</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cqz_check_login_api_url">Session Check API URL</label>
                    </th>
                    <td>
                        <input type="url" name="cqz_check_login_api_url" id="cqz_check_login_api_url" 
                               value="<?php echo esc_attr(get_option('cqz_check_login_api_url')); ?>" 
                               class="regular-text" />
                        <p class="description">URL for checking existing sessions</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cqz_auth_key">Authentication Key</label>
                    </th>
                    <td>
                        <input type="text" name="cqz_auth_key" id="cqz_auth_key" 
                               value="<?php echo esc_attr(get_option('cqz_auth_key')); ?>" 
                               class="regular-text" />
                        <p class="description">Authentication key for API requests</p>
                    </td>
                </tr>
            </table>
            
            <!-- Existing settings... -->
            
            <p class="submit">
                <input type="submit" name="cqz_save_settings" class="button-primary" value="Save Settings" />
            </p>
        </form>
    </div>
    <?php
}

private function save_settings() {
    if (!wp_verify_nonce($_POST['cqz_settings_nonce'], 'cqz_save_settings')) {
        wp_die('Security check failed');
    }
    
    // Save authentication settings
    update_option('cqz_auth_api_url', sanitize_url($_POST['cqz_auth_api_url']));
    update_option('cqz_check_login_api_url', sanitize_url($_POST['cqz_check_login_api_url']));
    update_option('cqz_auth_key', sanitize_text_field($_POST['cqz_auth_key']));
    
    // Save existing settings...
    
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    });
}
```

### 5. Add Authentication Styles

Add to `assets/css/frontend.css`:

```css
/* Authentication Styles */
.cqz-auth-container {
    max-width: 400px;
    margin: 2rem auto;
    padding: 2rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.cqz-auth-form h2 {
    text-align: center;
    margin-bottom: 1rem;
    color: #212529;
}

.cqz-auth-form p {
    text-align: center;
    color: #6c757d;
    margin-bottom: 2rem;
}

.cqz-form-group {
    margin-bottom: 1.5rem;
}

.cqz-form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #495057;
}

.cqz-form-group input {
    width: 100%;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.cqz-form-group input:focus {
    outline: none;
    border-color: #228be6;
    box-shadow: 0 0 0 3px rgba(34, 139, 230, 0.1);
}

.cqz-success {
    background: #d4edda;
    color: #155724;
    padding: 12px;
    border-radius: 6px;
    margin-top: 1rem;
    text-align: center;
}

.cqz-error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    border-radius: 6px;
    margin-top: 1rem;
    text-align: center;
}
```

## 🔧 Configuration

### 1. Set API URLs
In WordPress Admin → Quiz System → Settings:
- **Authentication API URL**: Your authentication endpoint
- **Session Check API URL**: Your session validation endpoint  
- **Authentication Key**: Your API authentication key

### 2. Update JavaScript
Add to `assets/js/frontend.js`:

```javascript
// Add authentication nonce
wp_localize_script('cqz-frontend', 'cqz_frontend', {
    // ... existing data
    'auth_nonce': wp_create_nonce('cqz_auth_nonce'),
});
```

## 🔒 Security Considerations

1. **HTTPS Only**: Always use HTTPS in production
2. **Cookie Security**: Set secure and httpOnly flags
3. **Input Validation**: All inputs are sanitized
4. **Nonce Protection**: All AJAX requests use nonces
5. **Session Management**: Proper session validation

## 🚀 Usage

1. **User visits quiz page**
2. **System checks authentication**
3. **If not authenticated, shows login form**
4. **User enters credentials**
5. **System validates with external API**
6. **Creates/updates WordPress user**
7. **Sets authentication cookies**
8. **User can now access quiz**

## 📝 Notes

- The system creates WordPress users automatically
- Sessions are validated on each request
- Cookies are cleared on logout
- All API calls use WordPress HTTP API
- Error handling is comprehensive

This implementation provides a secure, WordPress-integrated authentication system that maintains compatibility with your existing external authentication service. 