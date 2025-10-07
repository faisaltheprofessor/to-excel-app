<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organization_structures', function (Blueprint $table) {
            // SQLite maps json->TEXT. Default to '[]' so old rows are safe.
            if (!Schema::hasColumn('organization_structures', 'tree_json')) {
                $table->json('tree_json')->default('[]')->after('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_structures', function (Blueprint $table) {
            if (Schema::hasColumn('organization_structures', 'tree_json')) {
                $table->dropColumn('tree_json');
            }
        });
    }
};
