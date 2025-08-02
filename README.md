# LINE Login Plugin

This WordPress plugin adds a "Login with LINE" button to the login page and allows users to authenticate using their LINE account.

## Setup
1. Copy `line-login.php` into your WordPress `wp-content/plugins` directory.
2. Activate the **LINE Login** plugin from the WordPress admin dashboard.
3. Navigate to **Settings → LINE Login** and enter your LINE Channel ID and Channel Secret.
4. Create a LINE Login channel and set the callback URL to `https://your-site/?line-login-callback=1`.

Once configured, a LINE Login button will appear on the default login screen.
