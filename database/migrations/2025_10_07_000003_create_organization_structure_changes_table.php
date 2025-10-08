<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_structure_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('diff');         // minimal diff JSON
            $table->string('reason')->nullable(); // e.g., "auto-save"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_structure_changes');
    }
};
