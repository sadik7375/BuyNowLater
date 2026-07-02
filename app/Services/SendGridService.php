<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendGridService
{
    /**
     * Send an email via SendGrid API.
     *
     * @param string $apiKey
     * @param string $fromEmail
     * @param string $toEmail
     * @param string $subject
     * @param string $htmlContent
     * @return bool
     */
    public static function send($apiKey, $fromEmail, $toEmail, $subject, $htmlContent)
    {
        if (empty($apiKey) || empty($fromEmail)) {
            Log::error('SendGridService: Missing API Key or From Email.');
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.sendgrid.com/v3/mail/send', [
            'personalizations' => [
                [
                    'to' => [
                        ['email' => $toEmail]
                    ]
                ]
            ],
            'from' => [
                'email' => $fromEmail,
                'name' => 'Buy Later'
            ],
            'subject' => $subject,
            'content' => [
                [
                    'type' => 'text/html',
                    'value' => $htmlContent
                ]
            ]
        ]);

        if ($response->successful()) {
            Log::info("SendGridService: Email sent successfully to {$toEmail}");
            return true;
        }

        Log::error('SendGridService: Failed to send email.', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return false;
    }
}
