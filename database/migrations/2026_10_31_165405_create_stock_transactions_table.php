<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type');                       // in | out | adjustment
            $table->integer('quantity');                  // signed: +in / -out / delta for adjustment
            $table->unsignedInteger('previous_quantity')->default(0);
            $table->unsignedInteger('new_quantity')->default(0);
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
