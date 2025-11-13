<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;
use App\Models\Currency;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        // Skip database queries during installation
        if ($request->is('install/*') || $request->is('update/*') || !file_exists(storage_path('installed'))) {
            $globalSettings = [
                'currencySymbol' => '$',
                'currencyNname' => 'US Dollar',
                'base_url' => config('app.url')
            ];
        } else {
            // Get system settings (workspace-specific for company users)
            $user = $request->user();
            if ($user && $user->type === 'company' && $user->current_workspace_id && isSaasMode()) {
                $settings = settings($user->id, $user->current_workspace_id);
            } else {
                $settings = settings();
            }

            // Get currency symbol with error handling
            $currencyCode = $settings['defaultCurrency'] ?? 'USD';
            $currencySettings = [
                'currencySymbol' => '$',
                'currencyNname' => 'US Dollar'
            ];

            try {
                $currency = Currency::where('code', $currencyCode)->first();
                if ($currency) {
                    $currencySettings = [
                        'currencySymbol' => $currency->symbol,
                        'currencyNname' => $currency->name
                    ];
                }
            } catch (\Exception $e) {
                // Log the error but continue with default currency
                \Log::warning('Failed to fetch currency: ' . $e->getMessage());
            }

            // Merge currency settings with other settings
            $globalSettings = array_merge($settings, $currencySettings);
            $globalSettings['base_url'] = config('app.url');
        }

        return [
            ...parent::share($request),
            'name'  => config('app.name'),
            'base_url'  => config('app.url'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'csrf_token' => csrf_token(),
            'auth'  => [
                'user'        => $request->user() ? $request->user()->loadMissing(['currentWorkspace', 'ownedWorkspaces', 'workspaces']) : null,
                'roles'       => fn() => $this->getUserRoles($request),
                'permissions' => fn() => $this->getUserPermissions($request),
            ],
            'workspaceSettings' => fn() => $this->getWorkspaceSettings($request),
            'isImpersonating' => session('impersonated_by') ? true : false,
            'ziggy' => fn(): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
            'globalSettings' => $globalSettings,
            'is_demo' => config('app.is_demo', false),
            'isSaasMode' => isSaasMode()
        ];
    }

    /**
     * Get workspace-specific settings for the current user
     */
    private function getWorkspaceSettings(Request $request): array
    {
        $user = $request->user();

        if (!$user || !$user->current_workspace_id) {
            return [];
        }

        // Check session first for immediate access after workspace switch
        $sessionSettings = session('workspace_settings');
        $sessionPaymentSettings = session('workspace_payment_settings');

        if ($sessionSettings) {
            $settings = $sessionSettings;
            $settings['payment_settings'] = $sessionPaymentSettings ?? [];
            return $settings;
        }

        // Fallback to database query
        $workspace = $user->currentWorkspace;
        if (!$workspace) {
            return [];
        }

        $paymentSettings = \App\Models\PaymentSetting::getUserSettings($user->id, $workspace->id);

        return [
            'timesheet_enabled' => $workspace->timesheet_enabled,
            'timesheet_approval_required' => $workspace->timesheet_approval_required,
            'timesheet_auto_submit' => $workspace->timesheet_auto_submit,
            'timesheet_reminder_days' => $workspace->timesheet_reminder_days,
            'default_work_start' => $workspace->default_work_start?->format('H:i'),
            'default_work_end' => $workspace->default_work_end?->format('H:i'),
            'settings' => $workspace->settings ?? [],
            'payment_settings' => $paymentSettings
        ];
    }

    /**
     * Get user roles based on SaaS mode
     */
    private function getUserRoles(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            return [];
        }

        if (isSaasMode()) {
            return $user->roles->pluck('name')->toArray();
        }

        // Non-SaaS mode: get workspace role
        if ($user->current_workspace_id) {
            $workspaceMember = \App\Models\WorkspaceMember::where('user_id', $user->id)
                ->where('workspace_id', $user->current_workspace_id)
                ->first();

            return $workspaceMember ? [$workspaceMember->role] : [];
        }

        return [];
    }

    /**
     * Get user permissions based on SaaS mode
     */
    private function getUserPermissions(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            return [];
        }

        if (isSaasMode()) {
            return $user->getAllPermissions()->pluck('name')->toArray();
        }

        // Non-SaaS mode: check if user is owner, return company permissions
        if ($user->current_workspace_id) {
            $workspace = $user->currentWorkspace;
            if ($workspace && $workspace->owner_id === $user->id) {
                return $user->getAllPermissions()->pluck('name')->toArray();
            } else {
                $workspaceMember = \App\Models\WorkspaceMember::where('user_id', $user->id)
                    ->where('workspace_id', $user->current_workspace_id)
                    ->first();

                if ($workspaceMember) {
                    $role = Role::findByName($workspaceMember->role); 
                    return $role->permissions->pluck('name')->values()->toArray();
                }
            }
        }
        return [];
    }
}
