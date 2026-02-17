<?php
/**
 * Plugin Name: LINE + Google Login
 * Description: Adds LINE and Google login capability to WordPress.
 * Version: 1.1.0
 * Author: ChatGPT
 */

if (!defined('ABSPATH')) {
    exit;
}

class Line_Login_Plugin {
    const OPTION_LINE_CHANNEL_ID = 'line_login_channel_id';
    const OPTION_LINE_CHANNEL_SECRET = 'line_login_channel_secret';
    const OPTION_GOOGLE_CLIENT_ID = 'line_login_google_client_id';
    const OPTION_GOOGLE_CLIENT_SECRET = 'line_login_google_client_secret';
    const STATE_TRANSIENT_PREFIX = 'line_login_state_';

    public function __construct() {
        add_action('login_form', [ $this, 'add_login_buttons' ]);
        add_action('init', [ $this, 'handle_callback' ]);
        add_action('admin_menu', [ $this, 'add_settings_page' ]);
        add_action('admin_init', [ $this, 'register_settings' ]);
    }

    public function add_login_buttons() {
        echo '<p style="text-align:center;"><a class="button button-primary" href="' . esc_url(home_url('/?line-login=1')) . '">' . esc_html__('Login with LINE', 'line-login') . '</a></p>';
        echo '<p style="text-align:center;"><a class="button" href="' . esc_url(home_url('/?google-login=1')) . '">' . esc_html__('Login with Google', 'line-login') . '</a></p>';
    }

    public function handle_callback() {
        if (isset($_GET['line-login'])) {
            $this->redirect_to_line();
        } elseif (isset($_GET['line-login-callback'])) {
            $this->process_line_callback();
        } elseif (isset($_GET['google-login'])) {
            $this->redirect_to_google();
        } elseif (isset($_GET['google-login-callback'])) {
            $this->process_google_callback();
        }
    }

    protected function create_oauth_state($provider) {
        $state = wp_generate_password(24, false, false);
        set_transient(self::STATE_TRANSIENT_PREFIX . $state, $provider, 10 * MINUTE_IN_SECONDS);

        return $state;
    }

    protected function verify_oauth_state($state, $provider) {
        $saved_provider = get_transient(self::STATE_TRANSIENT_PREFIX . $state);
        delete_transient(self::STATE_TRANSIENT_PREFIX . $state);

        return $saved_provider === $provider;
    }

    protected function redirect_to_line() {
        $channel_id = get_option(self::OPTION_LINE_CHANNEL_ID);

        if (empty($channel_id)) {
            wp_die('LINE login is not configured.');
        }

        $url = add_query_arg([
            'response_type' => 'code',
            'client_id'     => $channel_id,
            'redirect_uri'  => $this->line_callback_url(),
            'state'         => $this->create_oauth_state('line'),
            'scope'         => 'profile openid',
        ], 'https://access.line.me/oauth2/v2.1/authorize');

        wp_safe_redirect($url);
        exit;
    }

    protected function process_line_callback() {
        $code = sanitize_text_field($_GET['code'] ?? '');
        $state = sanitize_text_field($_GET['state'] ?? '');

        if (empty($code) || empty($state) || !$this->verify_oauth_state($state, 'line')) {
            wp_die('Invalid LINE callback state.');
        }

        $token_response = wp_remote_post('https://api.line.me/oauth2/v2.1/token', [
            'body' => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $this->line_callback_url(),
                'client_id'     => get_option(self::OPTION_LINE_CHANNEL_ID),
                'client_secret' => get_option(self::OPTION_LINE_CHANNEL_SECRET),
            ],
        ]);

        if (is_wp_error($token_response)) {
            wp_die('LINE token request failed.');
        }

        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
        $access_token = sanitize_text_field($token_data['access_token'] ?? '');

        if (empty($access_token)) {
            wp_die('No LINE access token returned.');
        }

        $profile_response = wp_remote_get('https://api.line.me/v2/profile', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);

        if (is_wp_error($profile_response)) {
            wp_die('Unable to fetch LINE profile.');
        }

        $profile = json_decode(wp_remote_retrieve_body($profile_response), true);

        $line_id = sanitize_text_field($profile['userId'] ?? '');
        $display_name = sanitize_text_field($profile['displayName'] ?? 'LINE User');

        if (empty($line_id)) {
            wp_die('Invalid LINE profile response.');
        }

        $this->login_or_create_user(
            'line_' . $line_id,
            '',
            $display_name,
            [
                'line_user_id' => $line_id,
            ]
        );
    }

    protected function redirect_to_google() {
        $client_id = get_option(self::OPTION_GOOGLE_CLIENT_ID);

        if (empty($client_id)) {
            wp_die('Google login is not configured.');
        }

        $url = add_query_arg([
            'response_type' => 'code',
            'client_id'     => $client_id,
            'redirect_uri'  => $this->google_callback_url(),
            'scope'         => 'openid email profile',
            'state'         => $this->create_oauth_state('google'),
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ], 'https://accounts.google.com/o/oauth2/v2/auth');

        wp_safe_redirect($url);
        exit;
    }

    protected function process_google_callback() {
        $code = sanitize_text_field($_GET['code'] ?? '');
        $state = sanitize_text_field($_GET['state'] ?? '');

        if (empty($code) || empty($state) || !$this->verify_oauth_state($state, 'google')) {
            wp_die('Invalid Google callback state.');
        }

        $token_response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $this->google_callback_url(),
                'client_id'     => get_option(self::OPTION_GOOGLE_CLIENT_ID),
                'client_secret' => get_option(self::OPTION_GOOGLE_CLIENT_SECRET),
            ],
        ]);

        if (is_wp_error($token_response)) {
            wp_die('Google token request failed.');
        }

        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
        $access_token = sanitize_text_field($token_data['access_token'] ?? '');

        if (empty($access_token)) {
            wp_die('No Google access token returned.');
        }

        $userinfo_response = wp_remote_get('https://openidconnect.googleapis.com/v1/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);

        if (is_wp_error($userinfo_response)) {
            wp_die('Unable to fetch Google profile.');
        }

        $userinfo = json_decode(wp_remote_retrieve_body($userinfo_response), true);

        $google_id = sanitize_text_field($userinfo['sub'] ?? '');
        $email = sanitize_email($userinfo['email'] ?? '');
        $display_name = sanitize_text_field($userinfo['name'] ?? 'Google User');

        if (empty($google_id)) {
            wp_die('Invalid Google profile response.');
        }

        $preferred_username = $email ? $email : 'google_' . $google_id;

        $this->login_or_create_user(
            $preferred_username,
            $email,
            $display_name,
            [
                'google_sub' => $google_id,
            ]
        );
    }

    protected function login_or_create_user($preferred_login, $email, $display_name, $meta = []) {
        $user = false;

        if (!empty($email)) {
            $user = get_user_by('email', $email);
        }

        if (!$user) {
            $login = sanitize_user($preferred_login, true);

            if (empty($login)) {
                $login = 'social_' . wp_generate_password(8, false, false);
            }

            $base_login = $login;
            $suffix = 1;
            while (username_exists($login)) {
                $login = $base_login . '_' . $suffix;
                $suffix++;
            }

            $user_id = wp_insert_user([
                'user_login'   => $login,
                'user_email'   => $email,
                'user_pass'    => wp_generate_password(24, true, true),
                'display_name' => $display_name,
            ]);

            if (is_wp_error($user_id)) {
                wp_die('Could not create user account.');
            }

            $user = get_user_by('id', $user_id);
        }

        foreach ($meta as $meta_key => $meta_value) {
            update_user_meta($user->ID, $meta_key, $meta_value);
        }

        wp_set_auth_cookie($user->ID, true);
        wp_safe_redirect(admin_url());
        exit;
    }

    protected function line_callback_url() {
        return home_url('/?line-login-callback=1');
    }

    protected function google_callback_url() {
        return home_url('/?google-login-callback=1');
    }

    public function add_settings_page() {
        add_options_page('LINE + Google Login', 'LINE + Google Login', 'manage_options', 'line-login', [ $this, 'render_settings' ]);
    }

    public function register_settings() {
        register_setting('line-login-settings', self::OPTION_LINE_CHANNEL_ID);
        register_setting('line-login-settings', self::OPTION_LINE_CHANNEL_SECRET);
        register_setting('line-login-settings', self::OPTION_GOOGLE_CLIENT_ID);
        register_setting('line-login-settings', self::OPTION_GOOGLE_CLIENT_SECRET);

        add_settings_section('line_settings', 'LINE Settings', '__return_false', 'line-login');
        add_settings_field(self::OPTION_LINE_CHANNEL_ID, 'LINE Channel ID', [ $this, 'field_line_channel_id' ], 'line-login', 'line_settings');
        add_settings_field(self::OPTION_LINE_CHANNEL_SECRET, 'LINE Channel Secret', [ $this, 'field_line_channel_secret' ], 'line-login', 'line_settings');

        add_settings_section('google_settings', 'Google Settings', '__return_false', 'line-login');
        add_settings_field(self::OPTION_GOOGLE_CLIENT_ID, 'Google Client ID', [ $this, 'field_google_client_id' ], 'line-login', 'google_settings');
        add_settings_field(self::OPTION_GOOGLE_CLIENT_SECRET, 'Google Client Secret', [ $this, 'field_google_client_secret' ], 'line-login', 'google_settings');
    }

    public function render_settings() {
        echo '<div class="wrap"><h1>LINE + Google Login</h1><form method="post" action="options.php">';
        settings_fields('line-login-settings');
        do_settings_sections('line-login');
        submit_button();
        echo '</form></div>';
    }

    public function field_line_channel_id() {
        $val = esc_attr(get_option(self::OPTION_LINE_CHANNEL_ID, ''));
        echo '<input type="text" name="' . esc_attr(self::OPTION_LINE_CHANNEL_ID) . '" value="' . $val . '" class="regular-text" />';
    }

    public function field_line_channel_secret() {
        $val = esc_attr(get_option(self::OPTION_LINE_CHANNEL_SECRET, ''));
        echo '<input type="text" name="' . esc_attr(self::OPTION_LINE_CHANNEL_SECRET) . '" value="' . $val . '" class="regular-text" />';
    }

    public function field_google_client_id() {
        $val = esc_attr(get_option(self::OPTION_GOOGLE_CLIENT_ID, ''));
        echo '<input type="text" name="' . esc_attr(self::OPTION_GOOGLE_CLIENT_ID) . '" value="' . $val . '" class="regular-text" />';
    }

    public function field_google_client_secret() {
        $val = esc_attr(get_option(self::OPTION_GOOGLE_CLIENT_SECRET, ''));
        echo '<input type="text" name="' . esc_attr(self::OPTION_GOOGLE_CLIENT_SECRET) . '" value="' . $val . '" class="regular-text" />';
    }
}

new Line_Login_Plugin();
