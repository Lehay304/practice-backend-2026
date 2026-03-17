<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    // Список брони текущего пользователя
    // Но если админ то видно все брони
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Booking::with('banya');

        if (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }
        
        $bookings = $query->orderBy('start_time', 'desc')->paginate(10);

        return response()->json([
            'message' => 'Список бронирований',
            'data'    => $bookings
        ]);
    }

    // Просмотр одной брони
    public function show(Booking $booking)
    {
        $user = request()->user();

        if (!$user->isAdmin() && $booking->user_id !== $user->id) {
            return response()->json([
                'message' => 'Доступ запрещён'
            ], 403);
        }

        return response()->json([
            'message' => 'Информация о бронировании',
            'data'    => $booking->load(['banya', 'user'])
        ]);
    }

    // Тута создание бронирования
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'resource_id' => 'required|exists:resources,id',
            'start_time'  => 'required|date|after:now',
            'end_time'    => 'required|date|after:start_time',
        ]);

        $resource = Resource::find($validated['resource_id']);
        if (!$resource || !$resource->is_active) {
            return response()->json([
                'message' => 'Ресурс недоступен для бронирования'
            ], 400);
        }

        $hasOverlap = Booking::overlapping(
            $validated['resource_id'],
            $validated['start_time'],
            $validated['end_time']
        )->exists();

        if ($hasOverlap) {
            return response()->json([
                'message' => 'Выбранное время уже забронировано. Пожалуйста, выберите другое время.',
                'error'   => 'time_conflict'
            ], 409);
        }

        // здесь создание бронирования
        $booking = Booking::create([
            'user_id'      => $user->id,
            'resource_id'  => $validated['resource_id'],
            'start_time'   => $validated['start_time'],
            'end_time'     => $validated['end_time'],
            'status'       => 'active',
        ]);

        return response()->json([
            'message' => 'Бронирование успешно создано',
            'data'    => $booking->load('banya')
        ], 201);
    }


    //  Отмена брони
    public function cancel(Booking $booking)
    {
        $user = request()->user();

        if (!$user->isAdmin() && $booking->user_id !== $user->id) {
            return response()->json([
                'message' => 'Вы можете отменить только своё бронирование'
            ], 403);
        }

        if ($booking->status !== 'active') {
            return response()->json([
                'message' => 'Это бронирование уже отменено или завершено'
            ], 400);
        }

        $booking->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Бронирование отменено',
            'data'    => $booking
        ]);
    }

    // Завершение брони только для админа
    public function complete(Booking $booking)
    {
        $user = request()->user();

        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Только администратор может завершать бронирования'
            ], 403);
        }

        if ($booking->status !== 'active') {
            return response()->json([
                'message' => 'Нельзя завершить неактивное бронирование'
            ], 400);
        }

        $booking->update(['status' => 'completed']);

        return response()->json([
            'message' => 'Бронирование завершено',
            'data'    => $booking
        ]);
    }
}