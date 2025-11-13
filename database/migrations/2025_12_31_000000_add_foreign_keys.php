<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add foreign keys that depend on other tables
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_workspace_id')->references('id')->on('workspaces')->onDelete('set null');
            if (isSaasMode() && Schema::hasColumn('users', 'plan_id')) {
                $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
            }
            $table->foreign('timer_project_id')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('timer_task_id')->references('id')->on('tasks')->onDelete('set null');
            $table->foreign('timer_entry_id')->references('id')->on('timesheet_entries')->onDelete('set null');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('description');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            // Fix unique constraint
            $table->dropUnique(['guard_name']);
            $table->unique(['name', 'guard_name']);
        });

        Schema::table('webhooks', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_workspace_id']);
            if (isSaasMode() && Schema::hasColumn('users', 'plan_id')) {
                $table->dropForeign(['plan_id']);
            }
            $table->dropForeign(['timer_project_id']);
            $table->dropForeign(['timer_task_id']);
            $table->dropForeign(['timer_entry_id']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
            $table->dropUnique(['name', 'guard_name']);
            $table->unique(['guard_name']);
        });

        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
        });
    }
};