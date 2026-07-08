<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Reminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReminderMail;
use App\Models\Setting;

header('Content-Type: text/plain');
echo "=== BUY NOW LATER REMINDER DIAGNOSTIC SYSTEM ===\n\n";

$now = Carbon::now();
echo "Current server time (Carbon::now()): " . $now->toDateTimeString() . " (" . $now->timezoneName . ")\n";
echo "Configured app.timezone: " . config('app.timezone') . "\n\n";

$allPending = Reminder::where('status', 'pending')->get();
echo "Total pending reminders in DB: " . $allPending->count() . "\n\n";

foreach ($allPending as $index => $reminder) {
    echo "--- Reminder #" . ($index + 1) . " (ID: {$reminder->id}) ---\n";
    echo "Email: {$reminder->email}\n";
    echo "Product: {$reminder->product_title}\n";
    echo "Scheduled At: " . $reminder->scheduled_at->toDateTimeString() . " (" . $reminder->scheduled_at->timezoneName . ")\n";
    
    // Compare scheduled_at with now
    $isDue = $reminder->scheduled_at->lte($now);
    echo "Is Due (scheduled_at <= now)? " . ($isDue ? "YES" : "NO") . "\n";
    
    if ($isDue) {
        echo "Attempting to send email via Laravel Mailer...\n";
        try {
            $settings = Setting::where('shop_id', $reminder->shop_id)->first();
            $senderName = $settings?->sender_display_name;
            if (empty($senderName)) {
                $shopDomain = $reminder->shop->name ?? 'Store';
                $cleanName = str_replace('.myshopify.com', '', $shopDomain);
                $cleanName = ucwords(str_replace(['-', '_'], ' ', $cleanName));
                $senderName = $cleanName;
            }
            
            Mail::to($reminder->email)->send(new ReminderMail($reminder, $senderName));
            echo "SUCCESS: Email sent successfully!\n";
            
            // Update status
            $reminder->update([
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);
            echo "Status updated to 'sent' in database.\n";
        } catch (\Exception $e) {
            echo "FAILED to send email: " . $e->getMessage() . "\n";
        }
    } else {
        $diff = $reminder->scheduled_at->diffInSeconds($now);
        echo "Time remaining: " . gmdate("H:i:s", $diff) . "\n";
    }
    echo "\n";
}
