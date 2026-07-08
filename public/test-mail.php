<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Mail;

header('Content-Type: text/plain');
echo "Testing Laravel Mailer / Brevo SMTP configuration...\n\n";

echo "MAIL_MAILER: " . env('MAIL_MAILER') . "\n";
echo "MAIL_HOST: " . env('MAIL_HOST') . "\n";
echo "MAIL_PORT: " . env('MAIL_PORT') . "\n";
echo "MAIL_USERNAME: " . env('MAIL_USERNAME') . "\n";
echo "MAIL_FROM_ADDRESS: " . env('MAIL_FROM_ADDRESS') . "\n\n";

try {
    echo "Sending test email to wahidsadik38@gmail.com...\n";
    
    Mail::raw('This is a diagnostic test email from Buy Now Later Shopify App to verify SMTP connection.', function ($message) {
        $message->to('wahidsadik38@gmail.com')
                ->subject('Diagnostic SMTP Test - Buy Now Later');
    });

    echo "SUCCESS: Test email sent successfully! Please check your inbox/spam folder.\n";
} catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
    echo "SMTP ERROR (Transport Exception):\n";
    echo $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "GENERAL ERROR:\n";
    echo $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n";
}
