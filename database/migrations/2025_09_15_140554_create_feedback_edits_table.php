<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feedback_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // small, readable diff + a full snapshot for audit
            $table->json('changes');   // e.g. {"status":["open","in_review"],"priority":["normal","high"],"tags":[["UI"],["UI","Importer"]]}
            $table->json('snapshot');  // full current state after the change
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_edits');
    }
};
