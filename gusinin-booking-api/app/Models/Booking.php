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

    // Проверка пересечения времени (ключевой метод для чекпоинта 3)
    public function scopeOverlapping($query, $resourceId, $start, $end)
    {
        return $query->where('resource_id', $resourceId)
                     ->where('status', 'active')
                     ->where(function ($q) use ($start, $end) {
                         $q->whereBetween('start_time', [$start, $end])
                           ->orWhereBetween('end_time', [$start, $end])
                           ->orWhere(function ($q) use ($start, $end) {
                               $q->where('start_time', '<', $start)
                                 ->where('end_time', '>', $end);
                           });
                     });
    }
}