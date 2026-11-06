<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('officer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('waste_type_id')->constrained()->restrictOnDelete();
            $table->decimal('weight_kg', 10, 3);
            $table->unsignedBigInteger('price_per_kg');
            $table->unsignedBigInteger('total_points');
            $table->text('note')->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
