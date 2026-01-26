<?php

require __DIR__ . '/../vendor/autoload.php';
use GoogleOAuthPHPMailer\Config\Config;
use GoogleOAuthPHPMailer\Mailer\MailService;

$config = Config::get();
$mailer = new MailService($config);
$htmlBody = '
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Email</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4;">
  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; margin:auto; background-color:#ffffff; border-radius:8px; overflow:hidden; font-family:sans-serif;">
    <tr>
      <td style="background-color:#4CAF50; padding:20px; text-align:center;">
        <h1 style="color:#ffffff; margin:0; font-size:24px;">📬 Your Custom Mail</h1>
      </td>
    </tr>
    <tr>
      <td style="padding:30px; color:#333333; line-height:1.6;">
        <h2 style="color:#4CAF50; margin-top:0;">Hello Akash 👋,</h2>
        <p>
          This is a beautiful, responsive email built with PHPMailer and OAuth2 authentication.  
          You can fully customize this content to suit your application.
        </p>
        <p>
          Here’s a quick reminder: good UI makes a difference, even in your emails!
        </p>
        <div style="text-align:center; margin:30px 0;">
          <a href="https://yourwebsite.com" target="_blank" 
             style="background-color:#4CAF50; color:#ffffff; padding:14px 28px; text-decoration:none; border-radius:5px; display:inline-block;">
             Visit Our Site 🚀
          </a>
        </div>
        <p>Best regards,<br><strong>Your App Team</strong></p>
      </td>
    </tr>
    <tr>
      <td style="background-color:#f4f4f4; text-align:center; padding:20px; font-size:12px; color:#888888;">
        &copy; ' . date("Y") . ' Your App Name. All rights reserved.
      </td>
    </tr>
  </table>
</body>
</html>
';
try {
  $result = $mailer->sendMail(
    ['akash.masal@hridayamsoft.com', 'suraj.rangankar@hridayamsoft.com'],
    'Multi-Recipient Test Email',
    $htmlBody,
    ['ritesh.ghavat@hridayamsoft.com', 'akashsmasal@gmail.com'],
    ['akashmasal64@gmail.com'],
    [
      '../public/assets/dummy.pdf',
      '../public/assets/example.png'
    ]
  );

  echo $result ? 'Mail sent successfully.' : 'Failed to send email.';
} catch (Exception $e) {
  echo 'Error: ' . $e->getMessage();
}

?>