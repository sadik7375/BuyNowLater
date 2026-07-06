<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendGridService
{
    /**
     * Send an email via SendGrid API, or fallback to Laravel Mail if credentials are missing.
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
            Log::info("SendGridService: Missing SendGrid credentials. Falling back to Laravel Mail (configured SMTP/Brevo)...");
            
            try {
                Mail::html($htmlContent, function ($message) use ($toEmail, $subject) {
                    $message->to($toEmail)
                            ->subject($subject);
                });
                Log::info("SendGridService Fallback: Email sent successfully via Laravel Mail to {$toEmail}");
                return true;
            } catch (\Exception $e) {
                Log::error("SendGridService Fallback failed: " . $e->getMessage());
                return false;
            }
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
            Log::info("SendGridService: Email sent successfully via SendGrid API to {$toEmail}");
            return true;
        }

        Log::error('SendGridService: Failed to send email via SendGrid API.', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        // Fallback if API fails
        Log::info("SendGridService: Retrying fallback to Laravel Mail...");
        try {
            Mail::html($htmlContent, function ($message) use ($toEmail, $subject) {
                $message->to($toEmail)
                        ->subject($subject);
            });
            return true;
        } catch (\Exception $e) {
            Log::error("SendGridService Retry Fallback failed: " . $e->getMessage());
            return false;
        }
    }
}
