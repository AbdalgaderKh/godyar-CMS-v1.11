<?php

namespace GoogleOAuthPHPMailer\Config;

final class Config
{
    public static function get(): array
    {
        return [
            'clientId'     => getenv('GOOGLE_OAUTH_CLIENT_ID') ?: '',
            'clientSecret' => getenv('GOOGLE_OAUTH_CLIENT_SECRET') ?: '',
            'redirectUri'  => getenv('GOOGLE_OAUTH_REDIRECT_URI') ?: 'http://localhost/oauth-phpmailer-package/public/oauth-callback-url.php',
            'fromEmail'    => getenv('MAIL_FROM_EMAIL') ?: 'example@example.com',
            'fromName'     => getenv('MAIL_FROM_NAME') ?: 'Example',
            'tokenPath'    => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tokens' . DIRECTORY_SEPARATOR . 'tokens.json',
        ];
    }
}
