<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'bookings';

    protected $fillable = [
        'shop_id',
        'email',
        'product_id',
        'product_title',
        'product_handle',
        'product_image',
        'product_price',
        'deposit_amount',
        'remaining_balance',
        'draft_order_id',
        'checkout_url',
        'status',
        'token',
    ];

    /**
     * Get the shop that owns the booking.
     */
    public function shop()
    {
        return $this->belongsTo(User::class, 'shop_id');
    }
}
