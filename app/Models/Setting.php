<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'buylater_settings';

    protected $fillable = [
        'shop_id',
        'sender_display_name',
        'deposit_percentage',
        'button_text',
        'button_color',
        'button_text_color',
        'reminder_email_subject',
        'reminder_email_template',
        'discount_email_subject',
        'discount_email_template',
        'show_deposit',
        'show_reminders',
        'show_alerts',
        'hold_duration_days',
        'product_targeting_type',
        'targeted_product_ids',
        'selling_plan_group_id',
        'selling_plan_id',
        'use_selling_plan',
    ];

    public function shop()
    {
        return $this->belongsTo(User::class, 'shop_id');
    }
}
