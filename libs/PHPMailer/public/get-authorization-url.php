<?php

require __DIR__ . '/../vendor/autoload.php';
use GoogleOAuthPHPMailer\Config\Config;
use GoogleOAuthPHPMailer\OAuth\GoogleOAuthClient;

$config = GoogleOAuthPHPMailer\Config\Config::get();
$oauthClient = new GoogleOAuthPHPMailer\OAuth\GoogleOAuthClient($config);
echo '<a href="' . $oauthClient->getAuthorizationUrl() . '">Connect to Gmail</a>';

?>