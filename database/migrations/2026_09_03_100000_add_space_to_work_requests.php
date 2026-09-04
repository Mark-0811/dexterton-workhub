<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('work_requests', function (Blueprint $table) {
            $table->foreignUuid('space_id')->nullable()->after('service_flow_id')->constrained()->nullOnDelete();
        });

        $defaultSpaceId = DB::table('spaces')->orderBy('created_at')->value('id');
        if ($defaultSpaceId) {
            DB::table('work_requests')->whereNull('space_id')->update(['space_id' => $defaultSpaceId]);
        }
    }

    public function down(): void
    {
        Schema::table('work_requests', function (Blueprint $table) {
            $table->dropForeign(['space_id']);
            $table->dropColumn('space_id');
        });
    }
};
