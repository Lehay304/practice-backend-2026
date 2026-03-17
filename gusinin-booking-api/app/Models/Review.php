<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'resource_id',
        'booking_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function banya()
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function bronirovanie()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}