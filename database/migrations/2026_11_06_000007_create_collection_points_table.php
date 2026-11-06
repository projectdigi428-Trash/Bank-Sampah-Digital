<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_points', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address');
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('collection_point_waste_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_point_id')->constrained()->cascadeOnDelete();
            $table->foreignId('waste_type_id')->constrained()->cascadeOnDelete();
            $table->unique(['collection_point_id', 'waste_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_point_waste_type');
        Schema::dropIfExists('collection_points');
    }
};
