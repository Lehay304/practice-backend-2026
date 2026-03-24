<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Booking;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    // Список отзывов для конкретного места
    public function index($resourceId)
    {
        $resource = Resource::find($resourceId);
        if (!$resource) {
            return response()->json(['message' => 'Ресурс не найден'], 404);
        }

        $reviews = Review::with('user')
            ->where('resource_id', $resourceId)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'message' => 'Список отзывов',
            'data'    => $reviews,
            'average_rating' => $resource->average_rating
        ]);
    }

    // Создание отзыва только после завершённого бронирования
    public function store(Request $request)
    {

        $user = $request->user();

        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'nullable|string|max:1000',
        ]);

        $booking = Booking::find($validated['booking_id']);

        // Проверка: это бронирование пользователя
        if ($booking->user_id !== $user->id) {
            return response()->json([
                'message' => 'Вы можете оставить отзыв только за своё бронирование'
            ], 403);
        }

        // Проверка: бронирование должно быть завершено
        if ($booking->status !== 'completed') {
            return response()->json([
                'message' => 'Отзыв можно оставить только после завершённого бронирования'
            ], 400);
        }

        // Проверка: отзыв ещё не оставлен
        $existingReview = Review::where('booking_id', $booking->id)->first();
        if ($existingReview) {
            return response()->json([
                'message' => 'Вы уже оставили отзыв за это бронирование'
            ], 400);
        }

        $review = Review::create([
            'user_id'      => $user->id,
            'resource_id'  => $booking->resource_id,
            'booking_id'   => $booking->id,
            'rating'       => $validated['rating'],
            'comment'      => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Отзыв успешно добавлен',
            'data'    => $review->load(['user', 'banya'])
        ], 201);
    }

    // Просмотр одного отзыва
    public function show(Review $review)
    {
        return response()->json([
            'message' => 'Информация об отзыве',
            'data'    => $review->load(['user', 'banya'])
        ]);
    }

    // Обновление отзыва
    public function update(Request $request, Review $review)
    {
        $user = request()->user();

        if ($review->user_id !== $user->id) {
            return response()->json([
                'message' => 'Вы можете редактировать только свой отзыв'
            ], 403);
        }

        if ($review->created_at->diffInHours(now()) > 24) {
            return response()->json([
                'message' => 'Редактирование отзыва доступно только в течение 24 часов'
            ], 400);
        }

        $validated = $request->validate([
            'rating'  => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review->update($validated);

        return response()->json([
            'message' => 'Отзыв обновлён',
            'data'    => $review
        ]);
    }

    // Удаление отзыва только свого или админ
    public function destroy(Review $review)
    {
        $user = request()->user();

        if (!$user->isAdmin() && $review->user_id !== $user->id) {
            return response()->json([
                'message' => 'Вы можете удалить только свой отзыв'
            ], 403);
        }

        $review->delete();

        return response()->json([
            'message' => 'Отзыв удалён'
        ]);
    }
}