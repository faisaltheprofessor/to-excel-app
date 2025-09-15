<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // MySQL/Postgres: just set JSON defaults safely.
            Schema::table('feedback_edits', function (Blueprint $table) {
                $table->json('changes')->default(DB::raw("'{}'"))->change();
                $table->json('snapshot')->default(DB::raw("'{}'"))->change();
            });
            return;
        }

        DB::beginTransaction();
        try {
            // normalize nulls first
            DB::statement("UPDATE feedback_edits SET changes = '{}' WHERE changes IS NULL");
            DB::statement("UPDATE feedback_edits SET snapshot = '{}' WHERE snapshot IS NULL");

            // kill any leftover temp table/index from a previous failed attempt
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement('DROP TABLE IF EXISTS _tmp_feedback_edits');

            // rename current to temp
            DB::statement('ALTER TABLE feedback_edits RENAME TO _tmp_feedback_edits');

            // recreate with defaults
            Schema::create('feedback_edits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('changes')->default(DB::raw("'{}'"));
                $table->json('snapshot')->default(DB::raw("'{}'"));
                $table->timestamps();
            });

            // copy rows, filtering orphans so FK checks won’t explode later
            DB::statement("
                INSERT INTO feedback_edits (id, feedback_id, user_id, changes, snapshot, created_at, updated_at)
                SELECT t.id, t.feedback_id, t.user_id,
                       COALESCE(t.changes,  '{}'),
                       COALESCE(t.snapshot, '{}'),
                       t.created_at, t.updated_at
                FROM _tmp_feedback_edits t
                WHERE EXISTS (SELECT 1 FROM feedback f WHERE f.id = t.feedback_id)
                  AND (t.user_id IS NULL OR EXISTS (SELECT 1 FROM users u WHERE u.id = t.user_id))
            ");

            // drop temp
            DB::statement('DROP TABLE IF EXISTS _tmp_feedback_edits');
            DB::statement('PRAGMA foreign_keys = ON');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            try { DB::statement('PRAGMA foreign_keys = ON'); } catch (\Throwable $ignore) {}
            throw $e;
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // remove defaults (keep NOT NULL if that was your prior state)
            Schema::table('feedback_edits', function (Blueprint $table) {
                $table->json('changes')->nullable(false)->change();
                $table->json('snapshot')->nullable(false)->change();
            });
            return;
        }

        DB::beginTransaction();
        try {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement('DROP TABLE IF EXISTS _tmp_feedback_edits');
            DB::statement('ALTER TABLE feedback_edits RENAME TO _tmp_feedback_edits');

            Schema::create('feedback_edits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('changes');
                $table->json('snapshot');
                $table->timestamps();
            });

            DB::statement("
                INSERT INTO feedback_edits (id, feedback_id, user_id, changes, snapshot, created_at, updated_at)
                SELECT t.id, t.feedback_id, t.user_id, t.changes, t.snapshot, t.created_at, t.updated_at
                FROM _tmp_feedback_edits t
                WHERE EXISTS (SELECT 1 FROM feedback f WHERE f.id = t.feedback_id)
                  AND (t.user_id IS NULL OR EXISTS (SELECT 1 FROM users u WHERE u.id = t.user_id))
            ");

            DB::statement('DROP TABLE IF EXISTS _tmp_feedback_edits');
            DB::statement('PRAGMA foreign_keys = ON');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            try { DB::statement('PRAGMA foreign_keys = ON'); } catch (\Throwable $ignore) {}
            throw $e;
        }
    }
};
