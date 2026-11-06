<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('deposit_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('redemption_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('type');
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('description');
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
