<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feedback_comment_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('feedback_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('old_body');
            $table->text('new_body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_comment_edits');
    }
};
