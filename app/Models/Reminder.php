<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use HasFactory;

    protected $table = 'buylater_reminders';

    protected $fillable = [
        'shop_id',
        'product_id',
        'product_title',
        'product_handle',
        'product_image',
        'product_price',
        'email',
        'scheduled_at',
        'token',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function shop()
    {
        return $this->belongsTo(User::class, 'shop_id');
    }
}
