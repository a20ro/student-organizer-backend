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
        // Drop notebooks table if it exists
        Schema::dropIfExists('notebooks');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate notebooks table if needed (optional)
        Schema::create('notebooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }
};
