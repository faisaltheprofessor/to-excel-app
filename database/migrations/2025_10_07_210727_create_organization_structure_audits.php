<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_structure_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // created|updated|deleted|locked|unlocked|finalized|version_created
            $table->string('path')->nullable();      // e.g. "tree.nodes[3].name"
            $table->string('field')->nullable();     // e.g. "title"
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_structure_audits');
    }
};
