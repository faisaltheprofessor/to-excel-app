<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organization_structures', function (Blueprint $table) {
            // Creator + soft deletes
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('id');
            $table->softDeletes();

            // Versioning: group the versions and number them
            $table->uuid('version_group_id')->nullable()->after('created_by'); // same for all versions of a tree
            $table->unsignedInteger('version')->default(1)->after('version_group_id');

            // State
            $table->enum('status', ['draft', 'in_progress', 'abgeschlossen'])->default('draft')->after('version');

            // Locking (single editor)
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete()->after('status');
            $table->timestamp('locked_at')->nullable()->after('locked_by');

            // When finalized
            $table->timestamp('closed_at')->nullable()->after('locked_at');

            // Optional: show which version it was branched from
            $table->unsignedBigInteger('branched_from_id')->nullable()->after('closed_at');
            $table->foreign('branched_from_id')->references('id')->on('organization_structures')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organization_structures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropSoftDeletes();
            $table->dropColumn(['version_group_id', 'version', 'status', 'locked_by', 'locked_at', 'closed_at']);
            $table->dropForeign(['branched_from_id']);
            $table->dropColumn('branched_from_id');
        });
    }
};
