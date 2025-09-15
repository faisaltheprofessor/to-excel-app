<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('feedback_edits', function (Blueprint $table) {
            // Ensure defaults (SQLite requires raw SQL for JSON default sometimes)
        });

        // For SQLite you need to rebuild the column default via raw SQL
        // For MySQL/Postgres, the Schema builder handles it
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE feedback_edits RENAME TO _tmp_feedback_edits');

            Schema::create('feedback_edits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->json('changes')->default(json_encode([]));
                $table->json('snapshot')->default(json_encode([]));
                $table->timestamps();
            });

            DB::statement('INSERT INTO feedback_edits (id, feedback_id, user_id, changes, snapshot, created_at, updated_at)
                           SELECT id, feedback_id, user_id,
                                  COALESCE(changes, "{}"),
                                  COALESCE(snapshot, "{}"),
                                  created_at, updated_at
                           FROM _tmp_feedback_edits');

            DB::statement('DROP TABLE _tmp_feedback_edits');
        } else {
            Schema::table('feedback_edits', function (Blueprint $table) {
                $table->json('changes')->default(json_encode([]))->change();
                $table->json('snapshot')->default(json_encode([]))->change();
            });
        }
    }

    public function down(): void
    {
        // Reverse: remove defaults (make nullable again)
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE feedback_edits RENAME TO _tmp_feedback_edits');

            Schema::create('feedback_edits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->json('changes');
                $table->json('snapshot');
                $table->timestamps();
            });

            DB::statement('INSERT INTO feedback_edits (id, feedback_id, user_id, changes, snapshot, created_at, updated_at)
                           SELECT id, feedback_id, user_id, changes, snapshot, created_at, updated_at
                           FROM _tmp_feedback_edits');

            DB::statement('DROP TABLE _tmp_feedback_edits');
        } else {
            Schema::table('feedback_edits', function (Blueprint $table) {
                $table->json('changes')->nullable(false)->change();
                $table->json('snapshot')->nullable(false)->change();
            });
        }
    }
};
