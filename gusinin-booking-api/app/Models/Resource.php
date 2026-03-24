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
        'features'  => 'array',
    ];

    protected $appends = ['average_rating'];

    public function bronirovaniya()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getAverageRatingAttribute()
    {
        if (isset($this->attributes['reviews_avg_rating'])) {
            return round($this->attributes['reviews_avg_rating'], 2);
        }
        return round($this->reviews()->avg('rating') ?? 0, 2);
    }

}