<?php
/**
 * Plugin Name: LINE Login
 * Description: Adds LINE Login capability to WordPress.
 * Version: 1.0.0
 * Author: ChatGPT
 */

if (!defined('ABSPATH')) {
    exit;
}

class Line_Login_Plugin {
    const OPTION_CHANNEL_ID = 'line_login_channel_id';
    const OPTION_CHANNEL_SECRET = 'line_login_channel_secret';

    public function __construct() {
        add_action('login_form', [ $this, 'add_login_button' ]);
        add_action('init', [ $this, 'handle_callback' ]);
        add_action('admin_menu', [ $this, 'add_settings_page' ]);
        add_action('admin_init', [ $this, 'register_settings' ]);
    }

    public function add_login_button() {
        $url = home_url('/?line-login=1');
        $text = esc_html__('Login with LINE', 'line-login');
        echo '<p style="text-align:center;"><a class="button button-primary" href="'
            . esc_url($url) . '">' . $text . '</a></p>';
    }

    public function handle_callback() {
        if (isset($_GET['line-login'])) {
            $this->redirect_to_line();
        } elseif (isset($_GET['line-login-callback'])) {
            $this->process_line_callback();
        }
    }

    protected function redirect_to_line() {
        $channel_id  = get_option(self::OPTION_CHANNEL_ID);
        $redirect_uri = $this->callback_url();
        $state        = wp_create_nonce('line_login');

        $url = add_query_arg([
            'response_type' => 'code',
            'client_id'     => $channel_id,
            'redirect_uri'  => $redirect_uri,
            'state'         => $state,
            'scope'         => 'profile openid',
        ], 'https://access.line.me/oauth2/v2.1/authorize');

        wp_redirect($url);
        exit;
    }

    protected function process_line_callback() {
        $code  = sanitize_text_field($_GET['code'] ?? '');
        $state = sanitize_text_field($_GET['state'] ?? '');

        if (!wp_verify_nonce($state, 'line_login')) {
            wp_die('Invalid state');
        }

        $channel_id     = get_option(self::OPTION_CHANNEL_ID);
        $channel_secret = get_option(self::OPTION_CHANNEL_SECRET);
        $redirect_uri   = $this->callback_url();

        $response = wp_remote_post('https://api.line.me/oauth2/v2.1/token', [
            'body' => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $redirect_uri,
                'client_id'     => $channel_id,
                'client_secret' => $channel_secret,
            ],
        ]);

        if (is_wp_error($response)) {
            wp_die('LINE token request failed');
        }

        $data         = json_decode(wp_remote_retrieve_body($response), true);
        $access_token = $data['access_token'] ?? '';

        if (!$access_token) {
            wp_die('No access token');
        }

        $profile_res = wp_remote_get('https://api.line.me/v2/profile', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);

        if (is_wp_error($profile_res)) {
            wp_die('Unable to fetch profile');
        }

        $profile  = json_decode(wp_remote_retrieve_body($profile_res), true);
        $line_id  = $profile['userId'] ?? '';
        $nickname = $profile['displayName'] ?? '';

        if (!$line_id) {
            wp_die('Invalid profile');
        }

        $username = 'line_' . $line_id;
        $user     = get_user_by('login', $username);

        if (!$user) {
            $user_id = wp_insert_user([
                'user_login'   => $username,
                'user_pass'    => wp_generate_password(),
                'display_name' => $nickname,
            ]);

            if (is_wp_error($user_id)) {
                wp_die('Could not create user');
            }

            $user = get_user_by('id', $user_id);
        }

        wp_set_auth_cookie($user->ID, true);
        wp_redirect(admin_url());
        exit;
    }

    protected function callback_url() {
        return home_url('/?line-login-callback=1');
    }

    public function add_settings_page() {
        add_options_page('LINE Login', 'LINE Login', 'manage_options', 'line-login', [ $this, 'render_settings' ]);
    }

    public function register_settings() {
        register_setting('line-login-settings', self::OPTION_CHANNEL_ID);
        register_setting('line-login-settings', self::OPTION_CHANNEL_SECRET);

        add_settings_section('default', '', '__return_false', 'line-login');
        add_settings_field(self::OPTION_CHANNEL_ID, 'Channel ID', [ $this, 'field_channel_id' ], 'line-login', 'default');
        add_settings_field(self::OPTION_CHANNEL_SECRET, 'Channel Secret', [ $this, 'field_channel_secret' ], 'line-login', 'default');
    }

    public function render_settings() {
        echo '<div class="wrap"><h1>LINE Login</h1><form method="post" action="options.php">';
        settings_fields('line-login-settings');
        do_settings_sections('line-login');
        submit_button();
        echo '</form></div>';
    }

    public function field_channel_id() {
        $val = esc_attr(get_option(self::OPTION_CHANNEL_ID, ''));
        echo '<input type="text" name="' . self::OPTION_CHANNEL_ID . '" value="' . $val . '" class="regular-text" />';
    }

    public function field_channel_secret() {
        $val = esc_attr(get_option(self::OPTION_CHANNEL_SECRET, ''));
        echo '<input type="text" name="' . self::OPTION_CHANNEL_SECRET . '" value="' . $val . '" class="regular-text" />';
    }
}

new Line_Login_Plugin();
