# LINE + Google Login Plugin

This WordPress plugin adds both **Login with LINE** and **Login with Google (Gmail account)** buttons to the WordPress login page.

## Features
- LINE OAuth login
- Google OAuth login (users can sign in with their Gmail/Google account)
- Auto-create WordPress users on first social login
- Reuse existing WordPress users by email for Google logins

## Setup
1. Copy `line-login.php` into your WordPress `wp-content/plugins` directory.
2. Activate the **LINE + Google Login** plugin in wp-admin.
3. Go to **Settings → LINE + Google Login**.
4. Configure LINE:
   - LINE Channel ID
   - LINE Channel Secret
5. Configure Google:
   - Google Client ID
   - Google Client Secret

## OAuth Callback URLs
Use these callback URLs in each provider console:

- LINE callback URL: `https://your-site/?line-login-callback=1`
- Google callback URL: `https://your-site/?google-login-callback=1`

After configuration, both login buttons will appear on the default WordPress login page.
