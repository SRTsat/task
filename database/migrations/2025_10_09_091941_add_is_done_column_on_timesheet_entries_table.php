<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('timesheet_entries', 'is_done')) {
            Schema::table('timesheet_entries', function (Blueprint $table) {
                $table->boolean('is_done')->default(false)->after('description');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('timesheet_entries', 'is_done')) {
            Schema::table('timesheet_entries', function (Blueprint $table) {
                $table->dropColumn('is_done');
            });
        }
    }
};
