<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Resource;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceTest extends TestCase
{
    use RefreshDatabase;

    protected $adminToken;
    protected $userToken;
    protected $admin;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаём админа и пользователя
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user  = User::factory()->create(['role' => 'user']);

        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;
        $this->userToken  = $this->user->createToken('user-token')->plainTextToken;
    }

    // Тест: Получить список ресурсов (авторизованный)
    public function test_can_get_resources_list(): void
    {
        Resource::factory()->count(3)->create(['is_active' => true]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->getJson('/api/resources');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'data' => ['data', 'current_page', 'total']
                 ]);
    }

    /**
     * Тест: Получить один ресурс
     */
    public function test_can_get_single_resource(): void
    {
        $resource = Resource::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->getJson("/api/resources/{$resource->id}");

        $response->assertStatus(200)
                 ->assertJson(['data' => ['id' => $resource->id]]);
    }

    // Тест: Админ может создать ресурс
    public function test_admin_can_create_resource(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->postJson('/api/resources', [
            'name' => 'Русская баня',
            'description' => 'Традиционная баня',
            'location' => 'Корпус А',
            'capacity' => 8,
            'features' => ['пар', 'веники'],
            'is_active' => true,
        ]);

        $response->assertStatus(201)
                 ->assertJson(['message' => 'Место успешно создано']);
    }

    // Тест: Обычный пользователь НЕ может создать ресурс
    public function test_user_cannot_create_resource(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
            'Content-Type'  => 'application/json',
        ])->postJson('/api/resources', [
            'name' => 'Русская баня',
            'description' => 'Традиционная баня',
            'location' => 'Корпус А',
            'capacity' => 8,
            'is_active' => true,
        ]);

        $response->assertStatus(403);
    }

    // Тест: Админ может обновить ресурс
    public function test_admin_can_update_resource(): void
    {
        $resource = Resource::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->putJson("/api/resources/{$resource->id}", [
            'name'     => 'Обновлённая баня',
            'capacity' => 10,
        ]);

        $response->assertStatus(200)
                 ->assertJson(['data' => ['name' => 'Обновлённая баня']]);
    }

    // Тест: Админ может удалить ресурс
    public function test_admin_can_delete_resource(): void
    {
        $resource = Resource::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson("/api/resources/{$resource->id}");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Место удалёно']);

        $this->assertDatabaseMissing('resources', ['id' => $resource->id]);
    }

    // Тест: Неактивные ресурсы не показываются в списке
    public function test_inactive_resources_not_in_list(): void
    {
        Resource::factory()->create(['is_active' => true]);
        Resource::factory()->create(['is_active' => false]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->getJson('/api/resources');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data.data');
    }
}