<?php

require __DIR__ . '/../vendor/autoload.php';
use GoogleOAuthPHPMailer\Config\Config;
use GoogleOAuthPHPMailer\OAuth\GoogleOAuthClient;
use GoogleOAuthPHPMailer\Mailer\TokenManager;

$config = Config::get();
if (!isset($_GET['code'])) {
    die('Authorization code not found.');
}
try {
    $oauthClient = new GoogleOAuthClient($config);
    $accessToken = $oauthClient->getAccessToken($_GET['code']);
    $tokenManager = new TokenManager($config['tokenPath']);
    $tokenManager->saveToken($accessToken);

    echo 'Tokens saved successfully. You can now send emails.';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
