<?php

/**
 * Send OTP email using SendGrid
 * @param string $toEmail
 * @param string $otp
 * @param string $purpose (signup | reset)
 * @return bool
 */
function sendOTP(string $toEmail, string $otp, string $purpose = 'signup'): bool
{
    // ❗ NEVER hardcode API keys in files
    $SENDGRID_API_KEY = getenv('SENDGRID_API_KEY');

    if (!$SENDGRID_API_KEY) {
        error_log("SendGrid API key missing");
        return false;
    }

    $fromEmail = "yagneshbattu07@gmail.com"; // must be verified in SendGrid
    $fromName  = "Pet Pulse";

    $subject = ($purpose === 'reset')
        ? "Pet Pulse Password Reset OTP"
        : "Pet Pulse Account Verification OTP";

    $message = "
Hello,

Your One-Time Password (OTP) for Pet Pulse is:

OTP: {$otp}

This OTP is valid for 10 minutes.
Do not share this OTP with anyone.

If you did not request this, please ignore this email.

— Pet Pulse Team
";

    $payload = [
        "personalizations" => [[
            "to" => [[ "email" => $toEmail ]]
        ]],
        "from" => [
            "email" => $fromEmail,
            "name"  => $fromName
        ],
        "subject" => $subject,
        "content" => [[
            "type" => "text/plain",
            "value" => $message
        ]]
    ];

    $ch = curl_init("https://api.sendgrid.com/v3/mail/send");

    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_HTTPHEADER      => [
            "Authorization: Bearer {$SENDGRID_API_KEY}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS      => json_encode($payload),
        CURLOPT_TIMEOUT         => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

   

    if (curl_errno($ch)) {
        error_log("SendGrid cURL error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    return $httpCode === 202;
}
