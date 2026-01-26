<?php

namespace GoogleOAuthPHPMailer\Mailer;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use GoogleOAuthPHPMailer\OAuth\GoogleOAuthClient;
use League\OAuth2\Client\Token\AccessToken;
use Exception;

class MailService
{
    private GoogleOAuthClient $oauthClient;
    private TokenManager $tokenManager;
    private array $config;
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->oauthClient = new GoogleOAuthClient($config);
        $this->tokenManager = new TokenManager($config['tokenPath']);
    }
    public function sendMail(
        array $to,
        string $subject,
        string $body,
        array $cc = [],
        array $bcc = [],
        array $attachments = []
    ): bool {
        $tokenData = $this->tokenManager->getToken();
        if (!$tokenData) {
            throw new Exception("OAuth tokens not found. Run the OAuth flow.");
        }
        if (time() > $tokenData['expires']) {
            $accessToken = $this->oauthClient->refreshAccessToken($tokenData['refresh_token']);
            $this->tokenManager->saveToken($accessToken);
            $tokenData = $this->tokenManager->getToken();
        }
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->Port = 587;
            $mail->SMTPSecure = 'tls';
            $mail->SMTPAuth = true;
            $mail->AuthType = 'XOAUTH2';
            $mail->isHTML(true);
            $mail->setFrom($this->config['fromEmail'], $this->config['fromName']);
            foreach ($to as $recipient) {
                $mail->addAddress($recipient);
            }
            foreach ($cc as $ccRecipient) {
                $mail->addCC($ccRecipient);
            }
            foreach ($bcc as $bccRecipient) {
                $mail->addBCC($bccRecipient);
            }
            $mail->Subject = $subject;
            $mail->Body = $body;
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath);
                } else {
                    throw new Exception("Attachment file not found: $filePath");
                }
            }
            $mail->setOAuth(new \PHPMailer\PHPMailer\OAuth([
                'provider' => $this->oauthClient->getProvider(),
                'clientId' => $this->config['clientId'],
                'clientSecret' => $this->config['clientSecret'],
                'refreshToken' => $tokenData['refresh_token'],
                'userName' => $this->config['fromEmail']
            ]));
            return $mail->send();
        } catch (MailException $e) {
            throw new Exception("Mail sending failed: " . $e->getMessage());
        }
    }
}
