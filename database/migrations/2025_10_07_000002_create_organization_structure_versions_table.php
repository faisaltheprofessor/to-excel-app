<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_structure_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_structure_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->index(); // 1,2,3...
            $table->string('title');
            $table->json('data');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['organization_structure_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_structure_versions');
    }
};
