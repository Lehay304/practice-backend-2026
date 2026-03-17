<?php

namespace Database\Seeders;

use App\Models\Resource;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        Resource::create([
            'name'        => 'Парная VIP',
            'description' => 'Роскошная русская парная на 6 человек с дубовыми полками',
            'location'    => 'Корпус A, 1 этаж',
            'capacity'    => 6,
            'features'    => 'Пар, берёзовые и дубовые веники, душ, температура до 110°C, ароматерапия',
            'is_active'   => true,
        ]);

        Resource::create([
            'name'        => 'Финская сауна',
            'description' => 'Классическая сухая сауна с температурой до 100°C',
            'location'    => 'Корпус B, 2 этаж',
            'capacity'    => 8,
            'features'    => 'Сухой жар, каменка, температура до 100°C, освещение RGB',
            'is_active'   => true,
        ]);

        Resource::create([
            'name'        => 'Инфракрасная кабина',
            'description' => 'Современная ИК-кабина на 4 человека',
            'location'    => 'Зона SPA',
            'capacity'    => 4,
            'features'    => 'Инфракрасный прогрев, музыка, ароматерапия, низкая влажность',
            'is_active'   => true,
        ]);
    }
}