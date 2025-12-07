<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('parameter_name'); // Название параметра (например: "Gyártó", "Márka")
            $table->string('parameter_type')->default('text'); // Тип параметра
            $table->text('parameter_value')->nullable(); // Значение параметра
            $table->timestamps();
            
            $table->index(['product_id', 'parameter_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_parameters');
    }
};