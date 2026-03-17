<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'location',
        'capacity',
        'features',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Связи
    public function bronirovaniya()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Scope для активных бань
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Средний рейтинг (будет полезно позже)
    public function getAverageRatingAttribute()
    {
        return $this->reviews()->avg('rating') ?? 0;
    }
}