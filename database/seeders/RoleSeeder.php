<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (isSaasMode()) {
            // Create super admin role (SaaS platform admin)
            $superAdminRole = Role::firstOrCreate(
                ['name' => 'superadmin', 'guard_name' => 'web'],
                [
                    'label' => 'Super Admin',
                    'description' => 'Super Admin has full access to all SaaS platform features',
                ]
            );
            // Super Admin gets permissions for specific modules only
            $superAdminPermissions = Permission::whereIn('module', [
                'dashboards',
                'company',
                'plan',
                'coupon',
                'currency',
                'landing_page',
                'custom_page',
                'newsletter',
                'contact',
                'language',
                'media',
                'settings'
            ])->get();
            $superAdminRole->syncPermissions($superAdminPermissions);


            // Company role gets full access to specific modules (excluding role/permission management)
            $companyModulePermissions = Permission::whereIn('module', [
                'dashboards',
                'workspace',
                'projects',
                'tasks',
                'bugs',
                'timesheet',
                'budget',
                'expense',
                'expense_approval',
                'invoice',
                'media',
                'language',
                'report',
            ])->get();
            
            $companyLimitedPermissions = Permission::whereIn('name', [
                'plan_view_any',
                'plan_request',
                'plan_trial',
                'plan_subscribe',
                'plan_view_my_requests',
                'plan_view_my_orders',
                'settings_view',
                'settings_update',
                'settings_brand',
                'settings_currency',
                'settings_email',
                'settings_payment',
            ])->get();
            
            $companyPermissions = $companyModulePermissions->merge($companyLimitedPermissions);
        } else {
            $companyPermissions = Permission::whereIn('module', [
                'dashboards',
                'workspace',
                'currency',
                'projects',
                'tasks',
                'bugs',
                'timesheet',
                'budget',
                'expense',
                'expense_approval',
                'invoice',
                'media',
                'language',
                'landing_page',
                'custom_page',
                'newsletter',
                'contact',
                'settings',
                'report',
            ])->get();
        }


        // Create company role (SaaS tenant/customer)
        $companyRole = Role::firstOrCreate(
            ['name' => 'company', 'guard_name' => 'web'],
            [
                'label' => 'Company',
                'description' => 'Company has access to manage their business workspace',
            ]
        );
        $companyRole->syncPermissions($companyPermissions);


        // Create manager role (company child)
        $managerRole = Role::firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'web'],
            ['label' => 'Manager', 'description' => 'Manager with full workspace management']
        );

        $managerPermissions = Permission::whereIn('module', ['dashboards', 'projects', 'tasks', 'bugs', 'timesheet', 'budget', 'expense', 'expense_approval', 'invoice', 'media', 'report'])
            ->orWhereIn('name', ['workspace_switch', 'workspace_leave'])
            ->get();
        $managerRole->syncPermissions($managerPermissions);

        // Create member role (company child)
        $memberRole = Role::firstOrCreate(
            ['name' => 'member', 'guard_name' => 'web'],
            ['label' => 'Member', 'description' => 'Member with limited workspace access']
        );

        $memberPermissions = Permission::whereIn('name', [
            'dashboard_view',
            // 'workspace_view_any',
            // 'workspace_view',
            'workspace_switch',
            'workspace_leave',
            'project_view_any',
            'project_view',
            'task_view_any',
            'task_create',
            'task_update',
            'task_view',
            'task_add_comments',
            'bug_view_any',
            'bug_create',
            'bug_update',
            'bug_view',
            'bug_add_comments',
            'timesheet_view_any',
            'timesheet_view',
            'timesheet_create',
            'timesheet_update',
            'timesheet_delete',
            'timesheet_assign',
            'timesheet_submit',
            'timesheet_use_timer',
            'timesheet_bulk_operations',
            'expense_view_any',
            'expense_create',
            'expense_view'
        ])->get();
        $memberRole->syncPermissions($memberPermissions);

        // Create client role (company child)
        $clientRole = Role::firstOrCreate(
            ['name' => 'client', 'guard_name' => 'web'],
            ['label' => 'Client', 'description' => 'Client with read-only access']
        );

        $clientPermissions = Permission::whereIn('name', [
            'dashboard_view',
            // 'workspace_view_any',
            // 'workspace_view',
            'workspace_switch',
            'workspace_leave',
            'project_view_any',
            'project_view',
            'task_view_any',
            'task_view',
            'bug_view_any',
            'bug_view',
            'timesheet_view_any',
            'timesheet_view',
            'invoice_view_any',
            'invoice_view',

            'budget_view_any',
            'budget_view',
            'budget_create',
            'budget_update',
            'budget_delete',
        ])->get();

        $clientRole->syncPermissions($clientPermissions);
    }
}
