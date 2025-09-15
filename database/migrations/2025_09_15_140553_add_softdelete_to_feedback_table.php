<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            if (!Schema::hasColumn('feedback', 'deleted_at')) {
                $table->softDeletes();
            }
            // ensure new statuses exist logically; no schema change needed for them
        });

        Schema::table('feedback_comments', function (Blueprint $table) {
            if (!Schema::hasColumn('feedback_comments', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('feedback_reactions', function (Blueprint $table) {
            if (!Schema::hasColumn('feedback_reactions', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('feedback', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('feedback_comments', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('feedback_reactions', fn (Blueprint $t) => $t->dropSoftDeletes());
    }
};
