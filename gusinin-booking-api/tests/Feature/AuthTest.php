<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // Тест: Регистрация нового пользователя
    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'user' => ['id', 'name', 'email', 'role'],
                    'token'
                ]);
    }

    // Тест: Регистрация администратора
    public function test_admin_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(201)
                 ->assertJson(['user' => ['role' => 'admin']]);
    }


    // Тест: Логин с правильными данными
    public function test_user_can_login(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'password_confirmation' => 'password123',
            'role' => 'user',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token']);
    }

    // Тест: Логин с неправильным паролем
    public function test_login_fails_with_wrong_password(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'password_confirmation' => 'password123',
            'role' => 'user',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    // Тест: Логаут
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        $response->assertStatus(200)
                ->assertJson(['message' => 'Вы успешно вышли из аккаунта. До новых встреч в бане!']);
    }


    // Тест: Запрос без токена возвращает 401
    public function test_unauthorized_request_returns_401(): void
    {
        $response = $this->getJson('/api/resources');

        $response->assertStatus(401);
    }
}