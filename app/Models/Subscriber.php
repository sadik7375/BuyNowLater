<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    use HasFactory;

    protected $table = 'buylater_subscribers';

    protected $fillable = [
        'shop_id',
        'product_id',
        'product_title',
        'product_handle',
        'product_image',
        'product_price',
        'email',
        'status',
        'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    public function shop()
    {
        return $this->belongsTo(User::class, 'shop_id');
    }
}
