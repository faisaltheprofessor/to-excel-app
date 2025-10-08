<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organization_structures', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->boolean('is_finalized')->default(false)->after('data');
            $table->timestamp('finalized_at')->nullable()->after('is_finalized');
            $table->string('lock_token')->nullable()->after('locked_by_user_id')->index();
            // optimistic/explicit locking
            $table->foreignId('locked_by_user_id')->nullable()->after('finalized_at')->constrained('users')->nullOnDelete();
            $table->timestamp('lock_expires_at')->nullable()->after('locked_by_user_id');

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('organization_structures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['is_finalized','finalized_at']);
            $table->dropConstrainedForeignId('locked_by_user_id');
            $table->dropColumn(['lock_expires_at']);
            $table->dropSoftDeletes();
        });
    }
};
