<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Resource;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected $adminToken;
    protected $userToken;
    protected $admin;
    protected $user;
    protected $resource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin   = User::factory()->create(['role' => 'admin']);
        $this->user    = User::factory()->create(['role' => 'user']);
        $this->resource = Resource::factory()->create(['is_active' => true]);

        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;
        $this->userToken  = $this->user->createToken('user-token')->plainTextToken;
    }

    // Тест: Успешное создание бронирования
    public function test_can_create_booking(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/bookings', [
            'resource_id' => $this->resource->id,
            'start_time' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(201)
                 ->assertJson(['message' => 'Бронирование успешно создано']);
    }


    // Тест: Нельзя забронировать пересекающееся время (КЛЮЧЕВОЙ ТЕСТ!)
    public function test_cannot_book_overlapping_time(): void
    {
        // Создаём первое бронирование
        Booking::create([
            'user_id' => $this->user->id,
            'resource_id' => $this->resource->id,
            'start_time' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        // Пытаемся создать пересекающееся бронирование
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/bookings', [
            'resource_id' => $this->resource->id,
            'start_time' => now()->addDays(1)->addHour()->format('Y-m-d H:i:s'), // Пересекается!
            'end_time' => now()->addDays(1)->addHours(3)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(409)
                 ->assertJson(['error' => 'time_conflict']);
    }

    // Тест: Можно забронировать непересекающееся время
    public function test_can_book_non_overlapping_time(): void
    {
        // Создаём первое бронирование 10:00-12:00
        Booking::create([
            'user_id' => $this->user->id,
            'resource_id' => $this->resource->id,
            'start_time' => now()->addDays(1)->setTime(10, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(1)->setTime(12, 0)->format('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        // Бронируем 14:00-16:00 (не пересекается)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type'  => 'application/json',
        ])->postJson('/api/bookings', [
            'resource_id' => $this->resource->id,
            'start_time' => now()->addDays(1)->setTime(14, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(1)->setTime(16, 0)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(201);
    }

    // Тест: Пользователь видит только свои бронирования
    public function test_user_sees_only_own_bookings(): void
    {
        $anotherUser = User::factory()->create(['role' => 'user']);

        Booking::create([
            'user_id'  => $this->user->id,
            'resource_id' => $this->resource->id,
            'start_time' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        Booking::create([
            'user_id' => $anotherUser->id,
            'resource_id' => $this->resource->id,
            'start_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(2)->addHours(2)->format('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->getJson('/api/bookings');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data.data');
    }

    // Тест: Админ видит все бронирования
    public function test_admin_sees_all_bookings(): void
    {
        $anotherUser = User::factory()->create(['role' => 'user']);

        Booking::create([
            'user_id' => $this->user->id,
            'resource_id' => $this->resource->id,
            'start_time' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s'),
            'status'  => 'active',
        ]);

        Booking::create([
            'user_id' => $anotherUser->id,
            'resource_id' => $this->resource->id,
            'start_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(2)->addHours(2)->format('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/bookings');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data.data');
    }

    // Тест: Пользователь может отменить своё бронирование
    public function test_user_can_cancel_own_booking(): void
    {
        $booking = Booking::create([
            'user_id' => $this->user->id,
            'resource_id' => $this->resource->id,
            'start_time' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Бронирование отменено']);

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => 'cancelled',
        ]);
    }

    // Тест: Пользователь НЕ может отменить чужое бронирование
    public function test_user_cannot_cancel_others_booking(): void
    {
        $anotherUser = User::factory()->create(['role' => 'user']);

        $booking = Booking::create([
            'user_id' => $anotherUser->id,
            'resource_id' => $this->resource->id,
            'start_time' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(403);
    }

    // Тест: Админ может отменить любое бронирование
    public function test_admin_can_cancel_any_booking(): void
    {
        $anotherUser = User::factory()->create(['role' => 'user']);

        $booking = Booking::create([
            'user_id' => $anotherUser->id,
            'resource_id' => $this->resource->id,
            'start_time' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer' . $this->adminToken,
        ])->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(401);
    }
}