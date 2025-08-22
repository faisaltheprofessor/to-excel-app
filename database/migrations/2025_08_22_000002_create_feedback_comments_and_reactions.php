<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feedback_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');             // supports @mentions
            $table->foreignId('parent_id')->nullable()->constrained('feedback_comments')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('feedback_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('feedback_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16); // e.g. 👍 ❤️ 👀
            $table->timestamps();

            // one reaction per emoji per user per target
            $table->unique(['feedback_id', 'comment_id', 'user_id', 'emoji'], 'feedback_reactions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_reactions');
        Schema::dropIfExists('feedback_comments');
    }
};
