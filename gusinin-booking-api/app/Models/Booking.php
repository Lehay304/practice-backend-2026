<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'resource_id',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
    ];

    // Связи
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function banya()   // отношение к Resource (бане)
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    // Проверка пересечения времени 
    public function scopeOverlapping($query, $resourceId, $start, $end)
    {
        return $query->where('resource_id', $resourceId)
            ->where('status', 'active') // Только активные бронирования считаем
            ->where(function ($q) use ($start, $end) {
                // Пересечение есть, если:
                // (start1 < end2) AND (start2 < end1)
                $q->where('start_time', '<', $end)
                ->where('end_time', '>', $start);
            });
    }
}