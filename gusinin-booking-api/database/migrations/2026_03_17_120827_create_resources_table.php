<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // названия напр. 'русская баня, 'Финская сауна'
            $table->text('description')->nullable();
            $table->string('location'); // это адрес или номер комнаты в бане
            $table->unsignedInteger('capacity'); // вместимость это вместимость
            $table->text('features')->nullable(); // это всякие приблуды по типу: 'пар', 'веники', 'душ', 'температура_110']
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
