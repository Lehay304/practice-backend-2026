<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Resource;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceSearchTest extends TestCase
{
    use RefreshDatabase;

    protected $userToken;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'user']);
        $this->userToken = $this->user->createToken('user-token')->plainTextToken;
    }

    // Тест: Поиск ресурсов без фильтров (базовый)
    public function test_search_returns_active_resources(): void
    {
        Resource::factory()->count(3)->create(['is_active' => true]);
        Resource::factory()->create(['is_active' => false]); // Неактивный

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/resources/search?start_time=' . now()->addDays(1)->format('Y-m-d H:i:s') . '&end_time=' . now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s'));

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'search_params',
                     'data' => ['data', 'current_page', 'total']
                 ]);

        // Должны вернуться только активные ресурсы 3 из 4
        $response->assertJsonCount(3, 'data.data');
    }

    // Тест: Поиск без обязательных параметров возвращает ошибку
    public function test_search_requires_start_and_end_time(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->getJson('/api/resources/search');

        $response->assertStatus(422); // Ошибка валидации
    }

    // Тест: Фильтрация по вместимости (capacity)
    public function test_search_filters_by_capacity(): void
    {
        Resource::factory()->create(['capacity' => 4, 'is_active' => true]);
        Resource::factory()->create(['capacity' => 8, 'is_active' => true]);
        Resource::factory()->create(['capacity' => 10, 'is_active' => true]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/resources/search?start_time=' . now()->addDays(1)->format('Y-m-d H:i:s') . '&end_time=' . now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s') . '&capacity=8');

        $response->assertStatus(200);

        // Должны вернуться ресурсы с capacity >= 8 (два ресурса: 8 и 10)
        $this->assertCount(2, $response->json('data.data'));

        // Все returned ресурсы должны иметь capacity >= 8
        foreach ($response->json('data.data') as $resource) {
            $this->assertGreaterThanOrEqual(8, $resource['capacity']);
        }
    }

    // Тест: Фильтрация по одной характеристике (features)
    public function test_search_filters_by_single_feature(): void
    {
        // Ресурс с характеристиками ["пар", "веники", "душ"]
        Resource::factory()->create([
            'name' => 'Русская баня',
            'features' => ['пар', 'веники', 'душ'],
            'is_active' => true,
        ]);

        // Ресурс с характеристиками ["сауна", "бассейн"]
        Resource::factory()->create([
            'name' => 'Финская сауна',
            'features' => ['сауна', 'бассейн'],
            'is_active' => true,
        ]);

        // Ресурс с характеристиками ["пар", "бассейн"]
        Resource::factory()->create([
            'name' => 'Комплекс',
            'features' => ['пар', 'бассейн'],
            'is_active' => true,
        ]);

        // Ищем ресурсы с характеристикой "пар"
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type'  => 'application/json',
        ])->getJson('/api/resources/search?start_time=' . now()->addDays(1)->format('Y-m-d H:i:s') . '&end_time=' . now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s') . '&features=пар');

        $response->assertStatus(200);

        // Должны вернуться 2 ресурса с "пар" (Русская баня и Комплекс)
        $this->assertCount(2, $response->json('data.data'));

        // Проверяем, что все returned ресурсы содержат "пар" в features
        foreach ($response->json('data.data') as $resource) {
            $this->assertContains('пар', $resource['features']);
        }
    }

    // Тест: Фильтрация по нескольким характеристикам
    public function test_search_filters_by_multiple_features(): void
    {
        Resource::factory()->create([
            'name' => 'Русская баня',
            'features' => ['пар', 'веники', 'душ'],
            'is_active' => true,
        ]);

        Resource::factory()->create([
            'name' => 'Комплекс с бассейном',
            'features' => ['пар', 'бассейн', 'душ'],
            'is_active' => true,
        ]);

        Resource::factory()->create([
            'name' => 'Сауна',
            'features' => ['сауна', 'бассейн'],
            'is_active' => true,
        ]);

        // Ищем ресурсы с характеристиками "пар" И "бассейн"
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/resources/search?start_time=' . now()->addDays(1)->format('Y-m-d H:i:s') . '&end_time=' . now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s') . '&features=пар,бассейн');

        $response->assertStatus(200);

        // Должен вернуться 1 ресурс с обеими характеристиками
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Комплекс с бассейном', $response->json('data.data.0.name'));
    }

    // Тест: Поиск исключает забронированные ресурсы (пересечение времени)
    public function test_search_excludes_booked_resources(): void
    {
        $resource1 = Resource::factory()->create(['name' => 'Баня 1', 'is_active' => true]);
        $resource2 = Resource::factory()->create(['name' => 'Баня 2', 'is_active' => true]);
        $resource3 = Resource::factory()->create(['name' => 'Баня 3', 'is_active' => true]);

        // Бронируем resource1 на 10:00-12:00
        Booking::create([
            'user_id' => $this->user->id,
            'resource_id' => $resource1->id,
            'start_time' => now()->addDays(1)->setTime(10, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(1)->setTime(12, 0)->format('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        // Ищем на 11:00-13:00 (пересекается с resource1)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/resources/search?start_time=' . now()->addDays(1)->setTime(11, 0)->format('Y-m-d H:i:s') . '&end_time=' . now()->addDays(1)->setTime(13, 0)->format('Y-m-d H:i:s'));

        $response->assertStatus(200);

        // resource1 должен быть исключён (останется 2 ресурса)
        $this->assertCount(2, $response->json('data.data'));

        // resource1 не должен быть в результатах
        $resourceIds = array_column($response->json('data.data'), 'id');
        $this->assertNotContains($resource1->id, $resourceIds);
    }


    // Тест: Поиск не исключает ресурсы с непересекающимся временем

    public function test_search_includes_non_overlapping_resources(): void
    {
        $resource1 = Resource::factory()->create(['name' => 'Баня 1', 'is_active' => true]);

        // Бронируем на 10:00-12:00
        Booking::create([
            'user_id' => $this->user->id,
            'resource_id' => $resource1->id,
            'start_time' => now()->addDays(1)->setTime(10, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(1)->setTime(12, 0)->format('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        // Ищем на 14:00-16:00 (НЕ пересекается)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/resources/search?start_time=' . now()->addDays(1)->setTime(14, 0)->format('Y-m-d H:i:s') . '&end_time=' . now()->addDays(1)->setTime(16, 0)->format('Y-m-d H:i:s'));

        $response->assertStatus(200);

        // resource1 должен быть в результатах (не пересекается)
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals($resource1->id, $response->json('data.data.0.id'));
    }

    // Тест: Поиск исключает отменённые бронирования
    public function test_search_does_not_exclude_cancelled_bookings(): void
    {
        $resource1 = Resource::factory()->create(['name' => 'Баня 1', 'is_active' => true]);

        // Создаём ОТМЕНЁННОЕ бронирование на 10:00-12:00
        Booking::create([
            'user_id' => $this->user->id,
            'resource_id' => $resource1->id,
            'start_time' => now()->addDays(1)->setTime(10, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(1)->setTime(12, 0)->format('Y-m-d H:i:s'),
            'status' => 'cancelled', // Отменено!
        ]);

        // Ищем на 11:00-13:00
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type'  => 'application/json',
        ])->getJson('/api/resources/search?start_time=' . now()->addDays(1)->setTime(11, 0)->format('Y-m-d H:i:s') . '&end_time=' . now()->addDays(1)->setTime(13, 0)->format('Y-m-d H:i:s'));

        $response->assertStatus(200);

        // resource1 должен быть в результатах (бронь отменена)
        $this->assertCount(1, $response->json('data.data'));
    }

    // Тест: Комбинированный поиск (capacity + features + время)
    public function test_combined_search_filters(): void
    {
        Resource::factory()->create([
            'name' => 'Маленькая баня',
            'capacity' => 4,
            'features' => ['пар', 'веники'],
            'is_active' => true,
        ]);

        Resource::factory()->create([
            'name' => 'Большая баня с бассейном',
            'capacity' => 10,
            'features' => ['пар', 'бассейн', 'душ'],
            'is_active' => true,
        ]);

        Resource::factory()->create([
            'name' => 'Сауна',
            'capacity' => 6,
            'features' => ['сауна'],
            'is_active' => true,
        ]);

        // Ищем: capacity >= 8, features = пар, свободно на 10:00-12:00
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/resources/search?start_time=' . now()->addDays(1)->setTime(10, 0)->format('Y-m-d H:i:s') . '&end_time=' . now()->addDays(1)->setTime(12, 0)->format('Y-m-d H:i:s') . '&capacity=8&features=пар');

        $response->assertStatus(200);

        // Должен вернуться 1 ресурс (Большая баня с бассейном)
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Большая баня с бассейном', $response->json('data.data.0.name'));
    }

    // Тест: Поиск возвращает средний рейтинг ресурсов
    public function test_search_returns_average_rating(): void
    {
        $resource = Resource::factory()->create(['is_active' => true]);

        \App\Models\Review::create([
            'user_id' => $this->user->id,
            'resource_id' => $resource->id,
            'booking_id' => \App\Models\Booking::create([
                'user_id' => $this->user->id,
                'resource_id' => $resource->id,
                'start_time' => now()->subDays(2)->format('Y-m-d H:i:s'),
                'end_time' => now()->subDays(1)->format('Y-m-d H:i:s'),
                'status' => 'completed',
            ])->id,
            'rating' => 5,
            'comment' => 'Отлично',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/resources/search?start_time=' . now()->addDays(1)->format('Y-m-d H:i:s') . '&end_time=' . now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s'));

        $response->assertStatus(200);

        $this->assertArrayHasKey('average_rating', $response->json('data.data.0'));

        $this->assertEquals(5.0, $response->json('data.data.0.average_rating'));
    }

    // Тест: Поиск без авторизации возвращает 401
    public function test_search_requires_authentication(): void
    {
        $response = $this->getJson('/api/resources/search?start_time=' . now()->addDays(1)->format('Y-m-d H:i:s') . '&end_time=' . now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s'));

        $response->assertStatus(401);
    }
}