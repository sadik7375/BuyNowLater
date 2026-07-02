<?php

namespace App\Console\Commands;

use App\Mail\ReminderMail;
use App\Models\Reminder;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendScheduledReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:send-reminders';

    /**
     * The console command description.
     */
    protected $description = 'Send scheduled reminder emails to customers whose reminder time has arrived.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $now = Carbon::now();

        // Fetch all pending reminders whose scheduled time is now or past
        $dueReminders = Reminder::where('status', 'pending')
            ->where('scheduled_at', '<=', $now)
            ->with('shop') // eager load shop for settings
            ->get();

        if ($dueReminders->isEmpty()) {
            $this->info('No reminders due at this time.');
            return;
        }

        $this->info("Found {$dueReminders->count()} reminder(s) to send.");

        foreach ($dueReminders as $reminder) {
            try {
                // Get sender display name from this shop's settings, fallback to clean shop name
                $settings = Setting::where('shop_id', $reminder->shop_id)->first();
                $senderName = $settings?->sender_display_name;
                if (empty($senderName)) {
                    $shopDomain = $reminder->shop->name ?? 'Store';
                    $cleanName = str_replace('.myshopify.com', '', $shopDomain);
                    $cleanName = ucwords(str_replace(['-', '_'], ' ', $cleanName));
                    $senderName = $cleanName;
                }

                // Send the email
                Mail::to($reminder->email)->send(new ReminderMail($reminder, $senderName));

                // Mark as sent
                $reminder->update([
                    'status' => 'sent',
                    'sent_at' => $now,
                ]);

                $this->info("✅ Reminder sent to {$reminder->email} for \"{$reminder->product_title}\"");
                Log::info("Reminder sent", ['id' => $reminder->id, 'email' => $reminder->email]);

            } catch (\Exception $e) {
                $this->error("❌ Failed to send reminder ID {$reminder->id}: " . $e->getMessage());
                Log::error("Reminder send failed", ['id' => $reminder->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info('Done processing reminders.');
    }
}
