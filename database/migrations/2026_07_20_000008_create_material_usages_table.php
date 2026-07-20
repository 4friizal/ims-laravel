<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['raw_material_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_usages');
    }
};
