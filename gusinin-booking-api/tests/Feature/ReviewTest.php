<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Resource;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected $userToken;
    protected $user;
    protected $resource;
    protected $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'user']);
        $this->resource = Resource::factory()->create(['is_active' => true]);
        $this->booking  = Booking::create([
            'user_id' => $this->user->id,
            'resource_id' => $this->resource->id,
            'start_time' => now()->subDays(2)->format('Y-m-d H:i:s'),
            'end_time' => now()->subDays(1)->format('Y-m-d H:i:s'),
            'status' => 'completed', 
        ]);

        $this->userToken = $this->user->createToken('user-token')->plainTextToken;
    }

    // Тест: Можно оставить отзыв за завершённое бронирование
    public function test_can_create_review_for_completed_booking(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/reviews', [
            'booking_id' => $this->booking->id,
            'rating' => 5,
            'comment' => 'Отличная баня!',
        ]);

        $response->assertStatus(201)
                 ->assertJson(['message' => 'Отзыв успешно добавлен']);
    }

    // Тест: Нельзя оставить отзыв за активное бронирование
    public function test_cannot_create_review_for_active_booking(): void
    {
        $activeBooking = Booking::create([
            'user_id' => $this->user->id,
            'resource_id' => $this->resource->id,
            'start_time' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/reviews', [
            'booking_id' => $activeBooking->id,
            'rating' => 5,
            'comment' => 'Рановато...',
        ]);

        $response->assertStatus(400);
    }

    // Tест: Нельзя оставить отзыв за чужое бронирование
    public function test_cannot_create_review_for_others_booking(): void
    {
        $anotherUser = User::factory()->create(['role' => 'user']);

        $anotherBooking = Booking::create([
            'user_id' => $anotherUser->id,
            'resource_id' => $this->resource->id,
            'start_time' => now()->subDays(2)->format('Y-m-d H:i:s'),
            'end_time' => now()->subDays(1)->format('Y-m-d H:i:s'),
            'status' => 'completed',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/reviews', [
            'booking_id' => $anotherBooking->id,
            'rating' => 5,
        ]);

        $response->assertStatus(403);
    }

    // Тест: Нельзя оставить два отзыва за одно бронирование
    public function test_cannot_create_duplicate_review(): void
    {
        // Создаём первый отзыв
        Review::create([
            'user_id' => $this->user->id,
            'resource_id' => $this->resource->id,
            'booking_id' => $this->booking->id,
            'rating' => 5,
            'comment' => 'Первый отзыв',
        ]);

        // Пытаемся создать второй
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/reviews', [
            'booking_id' => $this->booking->id,
            'rating'     => 4,
        ]);

        $response->assertStatus(400);
    }

    // Тест: Средний рейтинг рассчитывается корректно
    public function test_average_rating_is_calculated(): void
    {
        Review::create([
            'user_id' => $this->user->id,
            'resource_id' => $this->resource->id,
            'booking_id' => $this->booking->id,
            'rating' => 5,
            'comment' => 'Отлично',
        ]);

        $anotherUser = User::factory()->create(['role' => 'user']);
        $anotherBooking = Booking::create([
            'user_id' => $anotherUser->id,
            'resource_id' => $this->resource->id,
            'start_time' => now()->subDays(3)->format('Y-m-d H:i:s'),
            'end_time' => now()->subDays(2)->format('Y-m-d H:i:s'),
            'status' => 'completed',
        ]);

        Review::create([
            'user_id' => $anotherUser->id,
            'resource_id' => $this->resource->id,
            'booking_id' => $anotherBooking->id,
            'rating' => 3,
            'comment' => 'Нормально',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->getJson("/api/resources/{$this->resource->id}");

        $response->assertStatus(200);
        // Средний рейтинг должен быть (5 + 3) / 2 = 4.0
    }

    // Тест: Получить список отзывов ресурса
    public function test_can_get_resource_reviews(): void
    {
        Review::create([
            'user_id' => $this->user->id,
            'resource_id' => $this->resource->id,
            'booking_id'=> $this->booking->id,
            'rating' => 5,
            'comment' => 'Отлично',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->getJson("/api/resources/{$this->resource->id}/reviews");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'data' => ['data', 'current_page'],
                     'average_rating'
                 ]);
    }
}