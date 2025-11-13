<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlanOrderController;
use App\Http\Controllers\PlanRequestController;
use App\Http\Controllers\RoleController;

use App\Http\Controllers\ReferralController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceInvitationController;
use App\Http\Controllers\CouponController;

use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\LandingPageController;

use App\Http\Controllers\LandingPage\CustomPageController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\RazorpayController;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\PayPalPaymentController;
use App\Http\Controllers\BankPaymentController;
use App\Http\Controllers\PaystackPaymentController;
use App\Http\Controllers\FlutterwavePaymentController;
use App\Http\Controllers\PayTabsPaymentController;
use App\Http\Controllers\SkrillPaymentController;
use App\Http\Controllers\CoinGatePaymentController;
use App\Http\Controllers\PayfastPaymentController;
use App\Http\Controllers\TapPaymentController;
use App\Http\Controllers\XenditPaymentController;
use App\Http\Controllers\PayTRPaymentController;
use App\Http\Controllers\MolliePaymentController;
use App\Http\Controllers\ToyyibPayPaymentController;
use App\Http\Controllers\CashfreeController;
use App\Http\Controllers\IyzipayPaymentController;
use App\Http\Controllers\BenefitPaymentController;
use App\Http\Controllers\OzowPaymentController;
use App\Http\Controllers\EasebuzzPaymentController;
use App\Http\Controllers\KhaltiPaymentController;
use App\Http\Controllers\AuthorizeNetPaymentController;
use App\Http\Controllers\FedaPayPaymentController;
use App\Http\Controllers\PayHerePaymentController;
use App\Http\Controllers\CinetPayPaymentController;
use App\Http\Controllers\PaiementPaymentController;
use App\Http\Controllers\NepalstePaymentController;
use App\Http\Controllers\YooKassaPaymentController;
use App\Http\Controllers\AamarpayPaymentController;
use App\Http\Controllers\MidtransPaymentController;
use App\Http\Controllers\PaymentWallPaymentController;
use App\Http\Controllers\SSPayPaymentController;

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [LandingPageController::class, 'show'])->name('home');

// Invitation routes (public access for accepting invitations)
Route::get('invitations/{token}', [WorkspaceInvitationController::class, 'show'])
    ->name('invitations.show');
Route::post('invitations/{token}/accept', [WorkspaceInvitationController::class, 'accept'])
    ->name('invitations.accept');

// Public form submission routes
// Route::post('/api/public/verify-password', [PublicVCardController::class, 'verifyPassword'])->name('public.vcard.verify-password');

// Cashfree webhook (public route)
Route::post('cashfree/webhook', [CashfreeController::class, 'webhook'])->name('cashfree.webhook');

// Benefit webhook (public route)
Route::post('benefit/webhook', [BenefitPaymentController::class, 'webhook'])->name('benefit.webhook');
Route::get('payments/benefit/success', [BenefitPaymentController::class, 'success'])->name('benefit.success');
Route::post('payments/benefit/callback', [BenefitPaymentController::class, 'callback'])->name('benefit.callback');

// FedaPay callback (public route)
Route::match(['GET', 'POST'], 'payments/fedapay/callback', [FedaPayPaymentController::class, 'callback'])->name('fedapay.callback');

// YooKassa success/callback (public routes)
Route::get('payments/yookassa/success', [YooKassaPaymentController::class, 'success'])->name('yookassa.success');
Route::post('payments/yookassa/callback', [YooKassaPaymentController::class, 'callback'])->name('yookassa.callback');

// Nepalste success/callback (public routes)
Route::get('payments/nepalste/success', [NepalstePaymentController::class, 'success'])->name('nepalste.success');
Route::post('payments/nepalste/callback', [NepalstePaymentController::class, 'callback'])->name('nepalste.callback');

// PayTR callback (public route)
Route::post('payments/paytr/callback', [PayTRPaymentController::class, 'callback'])->name('paytr.callback');

// PayTabs callback (public route)
Route::match(['GET', 'POST'], 'payments/paytabs/callback', [PayTabsPaymentController::class, 'callback'])->name('paytabs.callback');
Route::get('payments/paytabs/success', [PayTabsPaymentController::class, 'success'])->name('paytabs.success');

// Tap payment routes (public routes)
Route::get('payments/tap/success', [TapPaymentController::class, 'success'])->name('tap.success');
Route::post('payments/tap/callback', [TapPaymentController::class, 'callback'])->name('tap.callback');

// Aamarpay payment routes (public routes)
Route::match(['GET', 'POST'], 'payments/aamarpay/success', [AamarpayPaymentController::class, 'success'])->name('aamarpay.success');
Route::post('payments/aamarpay/callback', [AamarpayPaymentController::class, 'callback'])->name('aamarpay.callback');

// PaymentWall callback (public route)
Route::match(['GET', 'POST'], 'payments/paymentwall/callback', [PaymentWallPaymentController::class, 'callback'])->name('paymentwall.callback');
Route::get('payments/paymentwall/success', [PaymentWallPaymentController::class, 'success'])->name('paymentwall.success');

// PayFast payment routes (public routes)
Route::get('payments/payfast/success', [PayfastPaymentController::class, 'success'])->name('payfast.success');
Route::post('payments/payfast/callback', [PayfastPaymentController::class, 'callback'])->name('payfast.callback');

// CoinGate callback (public route)
Route::match(['GET', 'POST'], 'payments/coingate/callback', [CoinGatePaymentController::class, 'callback'])->name('coingate.callback');

// Xendit payment routes (public routes)
Route::get('payments/xendit/success', [XenditPaymentController::class, 'success'])->name('xendit.success');
Route::post('payments/xendit/callback', [XenditPaymentController::class, 'callback'])->name('xendit.callback');





Route::get('/landing-page', [LandingPageController::class, 'settings'])->name('landing-page');

Route::post('/landing-page/subscribe', [LandingPageController::class, 'subscribe'])->name('landing-page.subscribe');
Route::post('/landing-page/contact', [LandingPageController::class, 'submitContact'])->name('landing-page.contact');
Route::get('/page/{slug}', [CustomPageController::class, 'show'])->name('custom-page.show');

Route::get('/translations/{locale}', [TranslationController::class, 'getTranslations'])->name('translations');



// Email Templates routes (no middleware for testing)
// Route::get('email-templates', [\App\Http\Controllers\EmailTemplateController::class, 'index'])->name('email-templates.index');
// Route::get('email-templates/{emailTemplate}', [\App\Http\Controllers\EmailTemplateController::class, 'show'])->name('email-templates.show');
// Route::put('email-templates/{emailTemplate}/settings', [\App\Http\Controllers\EmailTemplateController::class, 'updateSettings'])->name('email-templates.update-settings');
// Route::put('email-templates/{emailTemplate}/content', [\App\Http\Controllers\EmailTemplateController::class, 'updateContent'])->name('email-templates.update-content');

Route::middleware(['auth', 'verified'])->group(function () {
    // SaaS-only routes
    Route::middleware('saas.only')->group(function () {
        // Plans routes - accessible without plan check
        Route::get('plans', [PlanController::class, 'index'])->middleware('permission:plan_view_any')->name('plans.index');
        Route::post('plans/request', [PlanController::class, 'requestPlan'])->middleware('permission:plan_request')->name('plans.request');
        Route::post('plans/cancel-request', [PlanController::class, 'cancelRequest'])->middleware('permission:plan_request')->name('plans.cancel-request');
        Route::post('plans/trial', [PlanController::class, 'startTrial'])->middleware('permission:plan_trial')->name('plans.trial');
        Route::post('plans/subscribe', [PlanController::class, 'subscribe'])->middleware('permission:plan_subscribe')->name('plans.subscribe');
        Route::post('plans/coupons/validate', [CouponController::class, 'validate'])->name('coupons.validate');

        // My Plan routes - for company users to view their own data
        Route::get('my-plan-requests', [PlanRequestController::class, 'myRequests'])->name('my-plan-requests.index');
        Route::delete('my-plan-requests/{planRequest}', [PlanRequestController::class, 'cancelMyRequest'])->name('my-plan-requests.cancel');
        Route::get('my-plan-orders', [PlanOrderController::class, 'myOrders'])->name('my-plan-orders.index');

        // Payment routes - accessible without plan check
        Route::post('payments/stripe', [StripePaymentController::class, 'processPayment'])->name('stripe.payment');
        Route::post('payments/paypal', [PayPalPaymentController::class, 'processPayment'])->name('paypal.payment');
        Route::post('payments/bank', [BankPaymentController::class, 'processPayment'])->name('bank.payment');
        Route::post('payments/paystack', [PaystackPaymentController::class, 'processPayment'])->name('paystack.payment');
        Route::post('payments/flutterwave', [FlutterwavePaymentController::class, 'processPayment'])->name('flutterwave.payment');
        Route::post('payments/paytabs', [PayTabsPaymentController::class, 'processPayment'])->name('paytabs.payment');
        Route::post('payments/skrill', [SkrillPaymentController::class, 'processPayment'])->name('skrill.payment');
        Route::post('payments/coingate', [CoinGatePaymentController::class, 'processPayment'])->name('coingate.payment');
        Route::post('payments/payfast', [PayfastPaymentController::class, 'processPayment'])->name('payfast.payment');
        Route::post('payments/mollie', [MolliePaymentController::class, 'processPayment'])->name('mollie.payment');
        Route::post('payments/toyyibpay', [ToyyibPayPaymentController::class, 'processPayment'])->name('toyyibpay.payment');
        Route::post('payments/iyzipay', [IyzipayPaymentController::class, 'processPayment'])->name('iyzipay.payment');
        Route::post('payments/benefit', [BenefitPaymentController::class, 'processPayment'])->name('benefit.payment');
        Route::post('payments/ozow', [OzowPaymentController::class, 'processPayment'])->name('ozow.payment');
        Route::post('payments/easebuzz', [EasebuzzPaymentController::class, 'processPayment'])->name('easebuzz.payment');
        Route::post('payments/khalti', [KhaltiPaymentController::class, 'processPayment'])->name('khalti.payment');
        Route::post('payments/authorizenet', [AuthorizeNetPaymentController::class, 'processPayment'])->name('authorizenet.payment');
        Route::post('payments/fedapay', [FedaPayPaymentController::class, 'processPayment'])->name('fedapay.payment');
        Route::post('payments/payhere', [PayHerePaymentController::class, 'processPayment'])->name('payhere.payment');
        Route::post('payments/cinetpay', [CinetPayPaymentController::class, 'processPayment'])->name('cinetpay.payment');
        Route::post('payments/paiement', [PaiementPaymentController::class, 'processPayment'])->name('paiement.payment');
        Route::post('payments/nepalste', [NepalstePaymentController::class, 'processPayment'])->name('nepalste.payment');
        Route::post('payments/yookassa', [YooKassaPaymentController::class, 'processPayment'])->name('yookassa.payment');
        Route::post('payments/aamarpay', [AamarpayPaymentController::class, 'processPayment'])->name('aamarpay.payment');
        Route::post('payments/midtrans', [MidtransPaymentController::class, 'processPayment'])->name('midtrans.payment');
        Route::post('payments/paymentwall', [PaymentWallPaymentController::class, 'processPayment'])->name('paymentwall.payment');
        Route::post('payments/sspay', [SSPayPaymentController::class, 'processPayment'])->name('sspay.payment');

        // Payment gateway specific routes
        Route::post('razorpay/create-order', [RazorpayController::class, 'createOrder'])->name('razorpay.create-order');
        Route::post('razorpay/verify-payment', [RazorpayController::class, 'verifyPayment'])->name('razorpay.verify-payment');
        Route::post('cashfree/create-session', [CashfreeController::class, 'createPaymentSession'])->name('cashfree.create-session');
        Route::post('cashfree/verify-payment', [CashfreeController::class, 'verifyPayment'])->name('cashfree.verify-payment');
        Route::post('mercadopago/create-preference', [MercadoPagoController::class, 'createPreference'])->name('mercadopago.create-preference');
        Route::post('mercadopago/process-payment', [MercadoPagoController::class, 'processPayment'])->name('mercadopago.process-payment');

        // Other payment creation routes
        Route::post('tap/create-payment', [TapPaymentController::class, 'createPayment'])->name('tap.create-payment');
        Route::post('xendit/create-payment', [XenditPaymentController::class, 'createPayment'])->name('xendit.create-payment');
        Route::post('payments/paytr/create-token', [PayTRPaymentController::class, 'createPaymentToken'])->name('paytr.create-token');
        Route::post('iyzipay/create-form', [IyzipayPaymentController::class, 'createPaymentForm'])->name('iyzipay.create-form');
        Route::post('benefit/create-session', [BenefitPaymentController::class, 'createPaymentSession'])->name('benefit.create-session');
        Route::post('ozow/create-payment', [OzowPaymentController::class, 'createPayment'])->name('ozow.create-payment');
        Route::post('easebuzz/create-payment', [EasebuzzPaymentController::class, 'createPayment'])->name('easebuzz.create-payment');
        Route::post('khalti/create-payment', [KhaltiPaymentController::class, 'createPayment'])->name('khalti.create-payment');
        Route::post('authorizenet/create-form', [AuthorizeNetPaymentController::class, 'createPaymentForm'])->name('authorizenet.create-form');
        Route::post('fedapay/create-payment', [FedaPayPaymentController::class, 'createPayment'])->name('fedapay.create-payment');
        Route::post('payhere/create-payment', [PayHerePaymentController::class, 'createPayment'])->name('payhere.create-payment');
        Route::post('cinetpay/create-payment', [CinetPayPaymentController::class, 'createPayment'])->name('cinetpay.create-payment');
        Route::post('paiement/create-payment', [PaiementPaymentController::class, 'createPayment'])->name('paiement.create-payment');
        Route::post('nepalste/create-payment', [NepalstePaymentController::class, 'createPayment'])->name('nepalste.create-payment');
        Route::post('yookassa/create-payment', [YooKassaPaymentController::class, 'createPayment'])->name('yookassa.create-payment');
        Route::post('aamarpay/create-payment', [AamarpayPaymentController::class, 'createPayment'])->name('aamarpay.create-payment');
        Route::post('midtrans/create-payment', [MidtransPaymentController::class, 'createPayment'])->name('midtrans.create-payment');
        Route::post('paymentwall/create-payment', [PaymentWallPaymentController::class, 'createPayment'])->name('paymentwall.create-payment');
        Route::post('sspay/create-payment', [SSPayPaymentController::class, 'createPayment'])->name('sspay.create-payment');

        // Payment success/callback routes
        Route::post('payments/skrill/callback', [SkrillPaymentController::class, 'callback'])->name('skrill.callback');
        Route::get('payments/paytr/success', [PayTRPaymentController::class, 'success'])->name('paytr.success');
        Route::get('payments/paytr/failure', [PayTRPaymentController::class, 'failure'])->name('paytr.failure');
        Route::get('payments/mollie/success', [MolliePaymentController::class, 'success'])->name('mollie.success');
        Route::post('payments/mollie/callback', [MolliePaymentController::class, 'callback'])->name('mollie.callback');
        Route::match(['GET', 'POST'], 'payments/toyyibpay/success', [ToyyibPayPaymentController::class, 'success'])->name('toyyibpay.success');
        Route::post('payments/toyyibpay/callback', [ToyyibPayPaymentController::class, 'callback'])->name('toyyibpay.callback');
        Route::post('payments/iyzipay/callback', [IyzipayPaymentController::class, 'callback'])->name('iyzipay.callback');
        Route::get('payments/ozow/success', [OzowPaymentController::class, 'success'])->name('ozow.success');
        Route::post('payments/ozow/callback', [OzowPaymentController::class, 'callback'])->name('ozow.callback');
        Route::get('payments/payhere/success', [PayHerePaymentController::class, 'success'])->name('payhere.success');
        Route::post('payments/payhere/callback', [PayHerePaymentController::class, 'callback'])->name('payhere.callback');
        Route::get('payments/cinetpay/success', [CinetPayPaymentController::class, 'success'])->name('cinetpay.success');
        Route::post('payments/cinetpay/callback', [CinetPayPaymentController::class, 'callback'])->name('cinetpay.callback');
        Route::get('payments/paiement/success', [PaiementPaymentController::class, 'success'])->name('paiement.success');
        Route::post('payments/paiement/callback', [PaiementPaymentController::class, 'callback'])->name('paiement.callback');
        Route::post('payments/midtrans/callback', [MidtransPaymentController::class, 'callback'])->name('midtrans.callback');
        Route::post('paymentwall/process', [PaymentWallPaymentController::class, 'processPayment'])->name('paymentwall.process');
        Route::get('payments/sspay/success', [SSPayPaymentController::class, 'success'])->name('sspay.success');
        Route::post('payments/sspay/callback', [SSPayPaymentController::class, 'callback'])->name('sspay.callback');
        Route::get('mercadopago/success', [MercadoPagoController::class, 'success'])->name('mercadopago.success');
        Route::get('mercadopago/failure', [MercadoPagoController::class, 'failure'])->name('mercadopago.failure');
        Route::get('mercadopago/pending', [MercadoPagoController::class, 'pending'])->name('mercadopago.pending');
        Route::post('mercadopago/webhook', [MercadoPagoController::class, 'webhook'])->name('mercadopago.webhook');
        Route::post('authorizenet/test-connection', [AuthorizeNetPaymentController::class, 'testConnection'])->name('authorizenet.test-connection');
    }); // End SaaS-only routes

    // All other routes require plan access check and plan limits monitoring
    Route::middleware(['plan.access', 'plan.limits'])->group(function () {
        // Workspace routes
        Route::get('workspaces', [WorkspaceController::class, 'index'])->middleware('permission:workspace_view_any')->name('workspaces.index');
        Route::get('workspaces/create', [WorkspaceController::class, 'create'])->middleware('permission:workspace_create')->name('workspaces.create');
        Route::post('workspaces', [WorkspaceController::class, 'store'])->middleware('permission:workspace_create')->name('workspaces.store');
        Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show'])->middleware('permission:workspace_view')->name('workspaces.show');
        Route::get('workspaces/{workspace}/edit', [WorkspaceController::class, 'edit'])->middleware('permission:workspace_update')->name('workspaces.edit');
        Route::put('workspaces/{workspace}', [WorkspaceController::class, 'update'])->middleware('permission:workspace_update')->name('workspaces.update');
        Route::patch('workspaces/{workspace}', [WorkspaceController::class, 'update'])->middleware('permission:workspace_update');
        Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->middleware('permission:workspace_delete')->name('workspaces.destroy');

        Route::get('workspaces/check-limits', [WorkspaceController::class, 'checkLimits'])->middleware('permission:workspace_view_any')->name('workspaces.check-limits');
        Route::post('workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->middleware('permission:workspace_switch')->name('workspaces.switch');
        Route::post('workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store'])->middleware('permission:workspace_invite_members')->name('workspace.invitations.store');
        Route::delete('workspaces/{workspace}/members/{user}', [WorkspaceController::class, 'removeMember'])->middleware('permission:workspace_manage_members')->name('workspace.remove-member');
        Route::post('workspaces/{workspace}/leave', [WorkspaceController::class, 'leaveWorkspace'])->middleware('permission:workspace_leave')->name('workspaces.leave');
        Route::get('workspaces/user-workspace-count', [WorkspaceController::class, 'getUserWorkspaceCount'])->name('workspaces.user-workspace-count');
        Route::post('invitations/{invitation}/resend', [WorkspaceInvitationController::class, 'resend'])->middleware('permission:workspace_invite_members')->name('invitations.resend');
        Route::delete('invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy'])->middleware('permission:workspace_invite_members')->name('invitations.destroy');
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/redirect', [DashboardController::class, 'redirectToFirstAvailablePage'])->name('dashboard.redirect');

        Route::get('media-library', function () {
            return Inertia::render('media-library-demo');
        })->name('media-library');

        Route::get('chatgpt', function () {
            return Inertia::render('examples/chatgpt-demo');
        })->name('chatgpt');

        // Media Library API routes
        Route::get('api/media', [MediaController::class, 'index'])->middleware('permission:media_view_any')->name('api.media.index');
        Route::post('api/media/batch', [MediaController::class, 'batchStore'])->middleware('permission:media_upload')->name('api.media.batch');
        Route::get('api/media/{id}/download', [MediaController::class, 'download'])->middleware('permission:media_download')->name('api.media.download');
        Route::delete('api/media/{id}', [MediaController::class, 'destroy'])->middleware('permission:media_delete')->name('api.media.destroy');

        // Bug API routes
        Route::get('api/bugs/project-data', [\App\Http\Controllers\BugController::class, 'getProjectData'])->middleware('permission:bug_view_any')->name('api.bugs.project-data');

        // Permissions routes with granular permissions
        Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:permission_view_any')->name('permissions.index');
        Route::post('permissions/assign', [PermissionController::class, 'assign'])->middleware('permission:permission_assign')->name('permissions.assign');
        Route::post('permissions/manage', [PermissionController::class, 'manage'])->middleware('permission:permission_manage')->name('permissions.manage');


        // SaaS-only admin routes
        Route::middleware('saas.only')->group(function () {
            // Plans management routes (admin only)
            Route::get('plans/create', [PlanController::class, 'create'])->middleware('permission:plan_create')->name('plans.create');
            Route::post('plans', [PlanController::class, 'store'])->middleware('permission:plan_create')->name('plans.store');
            Route::get('plans/{plan}/edit', [PlanController::class, 'edit'])->middleware('permission:plan_update')->name('plans.edit');
            Route::put('plans/{plan}', [PlanController::class, 'update'])->middleware('permission:plan_update')->name('plans.update');
            Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->middleware('permission:plan_delete')->name('plans.destroy');
            Route::post('plans/{plan}/toggle-status', [PlanController::class, 'toggleStatus'])->middleware('permission:plan_update')->name('plans.toggle-status');

            // Plan Orders routes
            Route::get('plan-orders', [PlanOrderController::class, 'index'])->middleware('permission:plan_manage_orders')->name('plan-orders.index');
            Route::post('plan-orders/{planOrder}/approve', [PlanOrderController::class, 'approve'])->middleware('permission:plan_approve_orders')->name('plan-orders.approve');
            Route::post('plan-orders/{planOrder}/reject', [PlanOrderController::class, 'reject'])->middleware('permission:plan_reject_orders')->name('plan-orders.reject');

            // Plan Requests routes
            Route::get('plan-requests', [PlanRequestController::class, 'index'])->middleware('permission:plan_manage_requests')->name('plan-requests.index');
            Route::post('plan-requests/{planRequest}/approve', [PlanRequestController::class, 'approve'])->middleware('permission:plan_manage_requests')->name('plan-requests.approve');
            Route::post('plan-requests/{planRequest}/reject', [PlanRequestController::class, 'reject'])->middleware('permission:plan_manage_requests')->name('plan-requests.reject');
            Route::delete('plan-requests/{planRequest}', [PlanRequestController::class, 'destroy'])->middleware('permission:plan_manage_requests')->name('plan-requests.destroy');

            // Coupons routes
            Route::get('coupons', [CouponController::class, 'index'])->middleware('permission:coupon_view_any')->name('coupons.index');
            Route::post('coupons', [CouponController::class, 'store'])->middleware('permission:coupon_create')->name('coupons.store');
            Route::put('coupons/{coupon}', [CouponController::class, 'update'])->middleware('permission:coupon_update')->name('coupons.update');
            Route::put('coupons/{coupon}/toggle-status', [CouponController::class, 'toggleStatus'])->middleware('permission:coupon_toggle_status')->name('coupons.toggle-status');
            Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('permission:coupon_delete')->name('coupons.destroy');

            // Companies routes
            Route::get('companies', [CompanyController::class, 'index'])->middleware('permission:company_view_any')->name('companies.index');
            Route::post('companies', [CompanyController::class, 'store'])->middleware('permission:company_create')->name('companies.store');
            Route::put('companies/{company}', [CompanyController::class, 'update'])->middleware('permission:company_update')->name('companies.update');
            Route::delete('companies/{company}', [CompanyController::class, 'destroy'])->middleware('permission:company_delete')->name('companies.destroy');
            Route::put('companies/{company}/reset-password', [CompanyController::class, 'resetPassword'])->middleware('permission:company_reset_password')->name('companies.reset-password');
            Route::put('companies/{company}/toggle-status', [CompanyController::class, 'toggleStatus'])->middleware('permission:company_toggle_status')->name('companies.toggle-status');
            Route::get('companies/{company}/plans', [CompanyController::class, 'getPlans'])->middleware('permission:company_manage_plans')->name('companies.plans');
            Route::put('companies/{company}/upgrade-plan', [CompanyController::class, 'upgradePlan'])->middleware('permission:company_upgrade_plan')->name('companies.upgrade-plan');
        });

        // Newsletter routes
        Route::get('newsletters', [\App\Http\Controllers\NewsletterController::class, 'index'])->middleware('permission:newsletter_view_any')->name('newsletters.index');
        Route::post('newsletters', [\App\Http\Controllers\NewsletterController::class, 'store'])->middleware('permission:newsletter_create')->name('newsletters.store');
        Route::put('newsletters/{newsletter}', [\App\Http\Controllers\NewsletterController::class, 'update'])->middleware('permission:newsletter_update')->name('newsletters.update');
        Route::delete('newsletters/{newsletter}', [\App\Http\Controllers\NewsletterController::class, 'destroy'])->middleware('permission:newsletter_delete')->name('newsletters.destroy');
        Route::put('newsletters/{newsletter}/toggle-status', [\App\Http\Controllers\NewsletterController::class, 'toggleStatus'])->middleware('permission:newsletter_toggle_status')->name('newsletters.toggle-status');
        Route::delete('newsletters/bulk-delete', [\App\Http\Controllers\NewsletterController::class, 'bulkDelete'])->middleware('permission:newsletter_bulk_operations')->name('newsletters.bulk-delete');
        Route::get('newsletters/export', [\App\Http\Controllers\NewsletterController::class, 'export'])->middleware('permission:newsletter_export')->name('newsletters.export');

        // Contact routes
        Route::get('contacts', [\App\Http\Controllers\ContactController::class, 'index'])->middleware('permission:contact_view_any')->name('contacts.index');
        Route::get('contacts/{contact}', [\App\Http\Controllers\ContactController::class, 'show'])->middleware('permission:contact_view')->name('contacts.show');
        Route::post('contacts', [\App\Http\Controllers\ContactController::class, 'store'])->middleware('permission:contact_create')->name('contacts.store');
        Route::put('contacts/{contact}', [\App\Http\Controllers\ContactController::class, 'update'])->middleware('permission:contact_update')->name('contacts.update');
        Route::delete('contacts/{contact}', [\App\Http\Controllers\ContactController::class, 'destroy'])->middleware('permission:contact_delete')->name('contacts.destroy');
        Route::put('contacts/{contact}/status', [\App\Http\Controllers\ContactController::class, 'updateStatus'])->middleware('permission:contact_update_status')->name('contacts.update-status');
        Route::put('contacts/bulk-status', [\App\Http\Controllers\ContactController::class, 'bulkUpdateStatus'])->middleware('permission:contact_bulk_operations')->name('contacts.bulk-status');
        Route::delete('contacts/bulk-delete', [\App\Http\Controllers\ContactController::class, 'bulkDelete'])->middleware('permission:contact_bulk_operations')->name('contacts.bulk-delete');
        Route::get('contacts/export', [\App\Http\Controllers\ContactController::class, 'export'])->middleware('permission:contact_export')->name('contacts.export');


        // Referral routes
        // Route::middleware('permission:manage-referral')->group(function () {
        //     Route::get('referral', [ReferralController::class, 'index'])->middleware('permission:manage-referral')->name('referral.index');
        //     Route::post('referral/settings', [ReferralController::class, 'updateSettings'])->middleware('permission:manage-setting-referral')->name('referral.settings.update');
        //     Route::post('referral/payout-request', [ReferralController::class, 'createPayoutRequest'])->middleware('permission:manage-payout-referral')->name('referral.payout-request.create');
        //     Route::post('referral/payout-request/{payoutRequest}/approve', [ReferralController::class, 'approvePayoutRequest'])->middleware('permission:approve-payout-referral')->name('referral.payout-request.approve');
        //     Route::post('referral/payout-request/{payoutRequest}/reject', [ReferralController::class, 'rejectPayoutRequest'])->middleware('permission:reject-payout-referral')->name('referral.payout-request.reject');
        // });



        // Currencies routes
        Route::get('currencies', [CurrencyController::class, 'index'])->middleware('permission:currency_view_any')->name('currencies.index');
        Route::post('currencies', [CurrencyController::class, 'store'])->middleware('permission:currency_create')->name('currencies.store');
        Route::put('currencies/{currency}', [CurrencyController::class, 'update'])->middleware('permission:currency_update')->name('currencies.update');
        Route::delete('currencies/{currency}', [CurrencyController::class, 'destroy'])->middleware('permission:currency_delete')->name('currencies.destroy');

        // ChatGPT routes
        Route::post('api/chatgpt/generate', [\App\Http\Controllers\ChatGptController::class, 'generate'])->name('chatgpt.generate');

        // Language management
        Route::get('manage-language/{lang?}', [LanguageController::class, 'managePage'])->middleware('permission:language_manage')->name('manage-language');
        Route::get('language/load', [LanguageController::class, 'load'])->middleware('permission:language_view')->name('language.load');
        Route::match(['POST', 'PATCH'], 'language/save', [LanguageController::class, 'save'])->middleware('permission:language_update')->name('language.save');

        // Landing Page content management
        Route::get('landing-page/settings', [LandingPageController::class, 'settings'])->middleware('permission:landing_page_manage')->name('landing-page.settings');
        Route::post('landing-page/settings', [LandingPageController::class, 'updateSettings'])->middleware('permission:landing_page_update')->name('landing-page.settings.update');

        Route::get('landing-page/custom-pages', [CustomPageController::class, 'index'])->middleware('permission:custom_page_view_any')->name('landing-page.custom-pages.index');
        Route::post('landing-page/custom-pages', [CustomPageController::class, 'store'])->middleware('permission:custom_page_create')->name('landing-page.custom-pages.store');
        Route::put('landing-page/custom-pages/{customPage}', [CustomPageController::class, 'update'])->middleware('permission:custom_page_update')->name('landing-page.custom-pages.update');
        Route::delete('landing-page/custom-pages/{customPage}', [CustomPageController::class, 'destroy'])->middleware('permission:custom_page_delete')->name('landing-page.custom-pages.destroy');

        // Project routes
        Route::get('projects', [\App\Http\Controllers\ProjectController::class, 'index'])->middleware('permission:project_view_any')->name('projects.index');
        Route::get('projects/create', [\App\Http\Controllers\ProjectController::class, 'create'])->middleware('permission:project_create')->name('projects.create');
        Route::post('projects', [\App\Http\Controllers\ProjectController::class, 'store'])->middleware('permission:project_create')->name('projects.store');
        Route::get('projects/{project}', [\App\Http\Controllers\ProjectController::class, 'show'])->middleware('permission:project_view')->name('projects.show');
        Route::get('projects/{project}/edit', [\App\Http\Controllers\ProjectController::class, 'edit'])->middleware('permission:project_update')->name('projects.edit');
        Route::put('projects/{project}', [\App\Http\Controllers\ProjectController::class, 'update'])->middleware('permission:project_update')->name('projects.update');
        Route::patch('projects/{project}', [\App\Http\Controllers\ProjectController::class, 'update'])->middleware('permission:project_update');
        Route::delete('projects/{project}', [\App\Http\Controllers\ProjectController::class, 'destroy'])->middleware('permission:project_delete')->name('projects.destroy');

        Route::post('projects/{project}/members', [\App\Http\Controllers\ProjectController::class, 'assignMember'])->middleware('permission:project_assign_members')->name('projects.assign-member');
        Route::delete('projects/{project}/members/{user}', [\App\Http\Controllers\ProjectController::class, 'removeMember'])->middleware('permission:project_assign_members')->name('projects.remove-member');
        Route::post('projects/{project}/clients', [\App\Http\Controllers\ProjectController::class, 'assignClient'])->middleware('permission:project_assign_clients')->name('projects.assign-client');
        Route::post('projects/{project}/assign-clients', [\App\Http\Controllers\ProjectController::class, 'assignClients'])->middleware('permission:project_assign_clients')->name('projects.assign-clients');
        Route::delete('projects/{project}/clients/{user}', [\App\Http\Controllers\ProjectController::class, 'removeClient'])->middleware('permission:project_assign_clients')->name('projects.remove-client');
        Route::post('projects/{project}/assign-members', [\App\Http\Controllers\ProjectController::class, 'assignMembers'])->middleware('permission:project_assign_members')->name('projects.assign-members');
        Route::post('projects/{project}/assign-managers', [\App\Http\Controllers\ProjectController::class, 'assignManagers'])->middleware('permission:project_assign_members')->name('projects.assign-managers');
        Route::put('projects/{project}/progress', [\App\Http\Controllers\ProjectController::class, 'updateProgress'])->middleware('permission:project_track_progress')->name('projects.update-progress');
        Route::post('projects/{project}/recalculate-progress', [\App\Http\Controllers\ProjectController::class, 'recalculateProgress'])->middleware('permission:project_track_progress')->name('projects.recalculate-progress');
        Route::post('projects/{project}/budget', [\App\Http\Controllers\ProjectController::class, 'createBudget'])->middleware('permission:project_manage_budget')->name('projects.create-budget');


        // Project milestones
        Route::post('projects/{project}/milestones', [\App\Http\Controllers\ProjectMilestoneController::class, 'store'])->middleware('permission:project_manage_milestones')->name('project-milestones.store');
        Route::put('projects/{project}/milestones/{milestone}', [\App\Http\Controllers\ProjectMilestoneController::class, 'update'])->middleware('permission:project_manage_milestones')->name('project-milestones.update');
        Route::delete('projects/{project}/milestones/{milestone}', [\App\Http\Controllers\ProjectMilestoneController::class, 'destroy'])->middleware('permission:project_manage_milestones')->name('project-milestones.destroy');
        Route::put('projects/{project}/milestones/{milestone}/status', [\App\Http\Controllers\ProjectMilestoneController::class, 'updateStatus'])->middleware('permission:project_manage_milestones')->name('project-milestones.update-status');
        Route::post('projects/{project}/milestones/reorder', [\App\Http\Controllers\ProjectMilestoneController::class, 'reorder'])->middleware('permission:project_manage_milestones')->name('project-milestones.reorder');

        // Project notes
        Route::post('projects/{project}/notes', [\App\Http\Controllers\ProjectNoteController::class, 'store'])->middleware('permission:project_manage_notes')->name('project-notes.store');
        Route::put('projects/{project}/notes/{note}', [\App\Http\Controllers\ProjectNoteController::class, 'update'])->middleware('permission:project_manage_notes')->name('project-notes.update');
        Route::delete('projects/{project}/notes/{note}', [\App\Http\Controllers\ProjectNoteController::class, 'destroy'])->middleware('permission:project_manage_notes')->name('project-notes.destroy');
        Route::put('projects/{project}/notes/{note}/pin', [\App\Http\Controllers\ProjectNoteController::class, 'togglePin'])->middleware('permission:project_manage_notes')->name('project-notes.toggle-pin');

        // Project invitations
        Route::post('projects/{project}/invite-client', [\App\Http\Controllers\ProjectInvitationController::class, 'inviteClient'])->middleware('permission:project_assign_clients')->name('projects.invite-client');
        Route::post('projects/{project}/invite-member', [\App\Http\Controllers\ProjectInvitationController::class, 'inviteMember'])->middleware('permission:project_assign_members')->name('projects.invite-member');

        // Project attachments
        Route::post('projects/{project}/attachments', [\App\Http\Controllers\ProjectAttachmentController::class, 'store'])->middleware('permission:project_manage_attachments')->name('project-attachments.store');
        Route::delete('project-attachments/{projectAttachment}', [\App\Http\Controllers\ProjectAttachmentController::class, 'destroy'])->middleware('permission:project_manage_attachments')->name('project-attachments.destroy');
        Route::get('project-attachments/{projectAttachment}/download', [\App\Http\Controllers\ProjectAttachmentController::class, 'download'])->middleware('permission:project_manage_attachments')->name('project-attachments.download');

        // Task routes
        Route::get('tasks', [\App\Http\Controllers\TaskController::class, 'index'])->middleware('permission:task_view_any')->name('tasks.index');
        Route::get('tasks/create', [\App\Http\Controllers\TaskController::class, 'create'])->middleware('permission:task_create')->name('tasks.create');
        Route::post('tasks', [\App\Http\Controllers\TaskController::class, 'store'])->middleware('permission:task_create')->name('tasks.store');
        Route::get('tasks/{task}', [\App\Http\Controllers\TaskController::class, 'show'])->middleware('permission:task_view')->name('tasks.show');
        Route::get('tasks/{task}/edit', [\App\Http\Controllers\TaskController::class, 'edit'])->middleware('permission:task_update')->name('tasks.edit');
        Route::put('tasks/{task}', [\App\Http\Controllers\TaskController::class, 'update'])->middleware('permission:task_update')->name('tasks.update');
        Route::patch('tasks/{task}', [\App\Http\Controllers\TaskController::class, 'update'])->middleware('permission:task_update');
        Route::delete('tasks/{task}', [\App\Http\Controllers\TaskController::class, 'destroy'])->middleware('permission:task_delete')->name('tasks.destroy');

        Route::post('tasks/{task}/duplicate', [\App\Http\Controllers\TaskController::class, 'duplicate'])->middleware('permission:task_duplicate')->name('tasks.duplicate');
        Route::put('tasks/{task}/stage', [\App\Http\Controllers\TaskController::class, 'changeStage'])->middleware('permission:task_change_status')->name('tasks.change-stage');

        // Task stages
        Route::get('task-stages', [\App\Http\Controllers\TaskStageController::class, 'index'])->middleware('permission:task_manage_stages')->name('task-stages.index');
        Route::post('task-stages', [\App\Http\Controllers\TaskStageController::class, 'store'])->middleware('permission:task_manage_stages')->name('task-stages.store');
        Route::put('task-stages/{taskStage}', [\App\Http\Controllers\TaskStageController::class, 'update'])->middleware('permission:task_manage_stages')->name('task-stages.update');
        Route::patch('task-stages/{taskStage}', [\App\Http\Controllers\TaskStageController::class, 'update'])->middleware('permission:task_manage_stages');
        Route::delete('task-stages/{taskStage}', [\App\Http\Controllers\TaskStageController::class, 'destroy'])->middleware('permission:task_manage_stages')->name('task-stages.destroy');
        Route::post('task-stages/reorder', [\App\Http\Controllers\TaskStageController::class, 'reorder'])->middleware('permission:task_manage_stages')->name('task-stages.reorder');
        Route::put('task-stages/{taskStage}/set-default', [\App\Http\Controllers\TaskStageController::class, 'setDefault'])->middleware('permission:task_manage_stages')->name('task-stages.set-default');

        // Task comments
        Route::post('tasks/{task}/comments', [\App\Http\Controllers\TaskCommentController::class, 'store'])->middleware('permission:task_add_comments')->name('task-comments.store');
        Route::put('task-comments/{taskComment}', [\App\Http\Controllers\TaskCommentController::class, 'update'])->middleware('permission:task_add_comments')->name('task-comments.update');
        Route::delete('task-comments/{taskComment}', [\App\Http\Controllers\TaskCommentController::class, 'destroy'])->middleware('permission:task_add_comments')->name('task-comments.destroy');

        // Task checklists
        Route::post('tasks/{task}/checklists', [\App\Http\Controllers\TaskChecklistController::class, 'store'])->middleware('permission:task_manage_checklists')->name('task-checklists.store');
        Route::put('task-checklists/{taskChecklist}', [\App\Http\Controllers\TaskChecklistController::class, 'update'])->middleware('permission:task_manage_checklists')->name('task-checklists.update');
        Route::delete('task-checklists/{taskChecklist}', [\App\Http\Controllers\TaskChecklistController::class, 'destroy'])->middleware('permission:task_manage_checklists')->name('task-checklists.destroy');
        Route::post('task-checklists/{taskChecklist}/toggle', [\App\Http\Controllers\TaskChecklistController::class, 'toggle'])->middleware('permission:task_manage_checklists')->name('task-checklists.toggle');

        // Task attachments
        Route::post('tasks/{task}/attachments', [\App\Http\Controllers\TaskAttachmentController::class, 'store'])->middleware('permission:task_add_attachments')->name('task-attachments.store');
        Route::delete('task-attachments/{taskAttachment}', [\App\Http\Controllers\TaskAttachmentController::class, 'destroy'])->middleware('permission:task_add_attachments')->name('task-attachments.destroy');
        Route::get('task-attachments/{taskAttachment}/download', [\App\Http\Controllers\TaskAttachmentController::class, 'download'])->middleware('permission:task_add_attachments')->name('task-attachments.download');

        // Bug routes
        Route::get('bugs', [\App\Http\Controllers\BugController::class, 'index'])->middleware('permission:bug_view_any')->name('bugs.index');
        Route::get('bugs/create', [\App\Http\Controllers\BugController::class, 'create'])->middleware('permission:bug_create')->name('bugs.create');
        Route::post('bugs', [\App\Http\Controllers\BugController::class, 'store'])->middleware('permission:bug_create')->name('bugs.store');
        Route::get('bugs/{bug}', [\App\Http\Controllers\BugController::class, 'show'])->middleware('permission:bug_view')->name('bugs.show');
        Route::get('bugs/{bug}/edit', [\App\Http\Controllers\BugController::class, 'edit'])->middleware('permission:bug_update')->name('bugs.edit');
        Route::put('bugs/{bug}', [\App\Http\Controllers\BugController::class, 'update'])->middleware('permission:bug_update')->name('bugs.update');
        Route::patch('bugs/{bug}', [\App\Http\Controllers\BugController::class, 'update'])->middleware('permission:bug_update');
        Route::delete('bugs/{bug}', [\App\Http\Controllers\BugController::class, 'destroy'])->middleware('permission:bug_delete')->name('bugs.destroy');

        Route::put('bugs/{bug}/status', [\App\Http\Controllers\BugController::class, 'changeStatus'])->middleware('permission:bug_change_status')->name('bugs.change-status');

        // Bug statuses
        Route::get('bug-statuses', [\App\Http\Controllers\BugStatusController::class, 'index'])->middleware('permission:bug_manage_statuses')->name('bug-statuses.index');
        Route::post('bug-statuses', [\App\Http\Controllers\BugStatusController::class, 'store'])->middleware('permission:bug_manage_statuses')->name('bug-statuses.store');
        Route::put('bug-statuses/{bugStatus}', [\App\Http\Controllers\BugStatusController::class, 'update'])->middleware('permission:bug_manage_statuses')->name('bug-statuses.update');
        Route::patch('bug-statuses/{bugStatus}', [\App\Http\Controllers\BugStatusController::class, 'update'])->middleware('permission:bug_manage_statuses');
        Route::delete('bug-statuses/{bugStatus}', [\App\Http\Controllers\BugStatusController::class, 'destroy'])->middleware('permission:bug_manage_statuses')->name('bug-statuses.destroy');
        Route::post('bug-statuses/reorder', [\App\Http\Controllers\BugStatusController::class, 'reorder'])->middleware('permission:bug_manage_statuses')->name('bug-statuses.reorder');
        Route::put('bug-statuses/{bugStatus}/set-default', [\App\Http\Controllers\BugStatusController::class, 'setDefault'])->middleware('permission:bug_manage_statuses')->name('bug-statuses.set-default');

        // Bug comments
        Route::post('bugs/{bug}/comments', [\App\Http\Controllers\BugCommentController::class, 'store'])->middleware('permission:bug_add_comments')->name('bug-comments.store');
        Route::put('bug-comments/{bugComment}', [\App\Http\Controllers\BugCommentController::class, 'update'])->middleware('permission:bug_add_comments')->name('bug-comments.update');
        Route::delete('bug-comments/{bugComment}', [\App\Http\Controllers\BugCommentController::class, 'destroy'])->middleware('permission:bug_add_comments')->name('bug-comments.destroy');

        // Bug attachments
        Route::post('bugs/{bug}/attachments', [\App\Http\Controllers\BugAttachmentController::class, 'store'])->middleware('permission:bug_add_attachments')->name('bug-attachments.store');
        Route::delete('bug-attachments/{bugAttachment}', [\App\Http\Controllers\BugAttachmentController::class, 'destroy'])->middleware('permission:bug_add_attachments')->name('bug-attachments.destroy');
        Route::get('bug-attachments/{bugAttachment}/download', [\App\Http\Controllers\BugAttachmentController::class, 'download'])->middleware('permission:bug_add_attachments')->name('bug-attachments.download');

        // Timesheet routes
        Route::get('timesheets/daily-view', [\App\Http\Controllers\TimesheetController::class, 'dailyView'])->middleware('permission:timesheet_view_any')->name('timesheets.daily-view');
        Route::get('timesheets/weekly-view', [\App\Http\Controllers\TimesheetController::class, 'weeklyView'])->middleware('permission:timesheet_view_any')->name('timesheets.weekly-view');
        Route::get('timesheets/monthly-view', [\App\Http\Controllers\TimesheetController::class, 'monthlyView'])->middleware('permission:timesheet_view_any')->name('timesheets.monthly-view');
        Route::get('timesheets/calendar-view', [\App\Http\Controllers\TimesheetController::class, 'calendarView'])->middleware('permission:timesheet_view_any')->name('timesheets.calendar-view');
        Route::get('timesheets/approvals', [\App\Http\Controllers\TimesheetController::class, 'approvals'])->middleware('permission:timesheet_approve')->name('timesheets.approvals');
        Route::get('timesheets/reports', [\App\Http\Controllers\TimesheetController::class, 'reports'])->middleware('permission:report_timesheet')->name('timesheets.reports');

        Route::get('timesheets', [\App\Http\Controllers\TimesheetController::class, 'index'])->middleware('permission:timesheet_view_any')->name('timesheets.index');
        Route::get('timesheets/create', [\App\Http\Controllers\TimesheetController::class, 'create'])->middleware('permission:timesheet_create')->name('timesheets.create');
        Route::post('timesheets', [\App\Http\Controllers\TimesheetController::class, 'store'])->middleware('permission:timesheet_create')->name('timesheets.store');
        Route::get('timesheets/{timesheet}', [\App\Http\Controllers\TimesheetController::class, 'show'])->middleware('permission:timesheet_view')->name('timesheets.show');
        Route::get('timesheets/{timesheet}/edit', [\App\Http\Controllers\TimesheetController::class, 'edit'])->middleware('permission:timesheet_update')->name('timesheets.edit');
        Route::put('timesheets/{timesheet}', [\App\Http\Controllers\TimesheetController::class, 'update'])->middleware('permission:timesheet_update')->name('timesheets.update');
        Route::patch('timesheets/{timesheet}', [\App\Http\Controllers\TimesheetController::class, 'update'])->middleware('permission:timesheet_update');
        Route::delete('timesheets/{timesheet}', [\App\Http\Controllers\TimesheetController::class, 'destroy'])->middleware('permission:timesheet_delete')->name('timesheets.destroy');

        Route::post('timesheets/{timesheet}/submit', [\App\Http\Controllers\TimesheetController::class, 'submit'])->middleware('permission:timesheet_submit')->name('timesheets.submit');
        Route::post('timesheets/{timesheet}/approve', [\App\Http\Controllers\TimesheetController::class, 'approve'])->middleware('permission:timesheet_approve')->name('timesheets.approve');
        Route::post('timesheets/{timesheet}/reject', [\App\Http\Controllers\TimesheetController::class, 'reject'])->middleware('permission:timesheet_approve')->name('timesheets.reject');

        // Timesheet entries
        Route::get('timesheet-entries', [\App\Http\Controllers\TimesheetEntryController::class, 'index'])->middleware('permission:timesheet_view_any')->name('timesheet-entries.index');
        Route::post('timesheet-entries', [\App\Http\Controllers\TimesheetEntryController::class, 'store'])->middleware('permission:timesheet_create')->name('timesheet-entries.store');
        Route::put('timesheet-entries/{timesheetEntry}', [\App\Http\Controllers\TimesheetEntryController::class, 'update'])->middleware('permission:timesheet_update')->name('timesheet-entries.update');
        Route::patch('timesheet-entries/{timesheetEntry}', [\App\Http\Controllers\TimesheetEntryController::class, 'update'])->middleware('permission:timesheet_update');
        Route::delete('timesheet-entries/{timesheetEntry}', [\App\Http\Controllers\TimesheetEntryController::class, 'destroy'])->middleware('permission:timesheet_delete')->name('timesheet-entries.destroy');
        Route::post('timesheet-entries/bulk-update', [\App\Http\Controllers\TimesheetEntryController::class, 'bulkUpdate'])->middleware('permission:timesheet_bulk_operations')->name('timesheet-entries.bulk-update');
        Route::delete('timesheet-entries/bulk-delete', [\App\Http\Controllers\TimesheetEntryController::class, 'bulkDelete'])->middleware('permission:timesheet_bulk_operations')->name('timesheet-entries.bulk-delete');

        // Timer functionality
        Route::post('timer/start', [\App\Http\Controllers\TimerController::class, 'start'])->middleware('permission:timesheet_use_timer')->name('timer.start');
        Route::post('timer/stop', [\App\Http\Controllers\TimerController::class, 'stop'])->middleware('permission:timesheet_use_timer')->name('timer.stop');
        Route::post('timer/pause', [\App\Http\Controllers\TimerController::class, 'pause'])->middleware('permission:timesheet_use_timer')->name('timer.pause');
        Route::post('timer/resume', [\App\Http\Controllers\TimerController::class, 'resume'])->middleware('permission:timesheet_use_timer')->name('timer.resume');
        Route::get('timer/status', [\App\Http\Controllers\TimerController::class, 'status'])->middleware('permission:timesheet_use_timer')->name('timer.status');

        // Timesheet approvals
        Route::get('timesheet-approvals', [\App\Http\Controllers\TimesheetApprovalController::class, 'index'])->middleware('permission:timesheet_approve')->name('timesheet-approvals.index');
        Route::post('timesheet-approvals/{approval}/approve', [\App\Http\Controllers\TimesheetApprovalController::class, 'approve'])->middleware('permission:timesheet_approve')->name('timesheet-approvals.approve');
        Route::post('timesheet-approvals/{approval}/reject', [\App\Http\Controllers\TimesheetApprovalController::class, 'reject'])->middleware('permission:timesheet_approve')->name('timesheet-approvals.reject');
        Route::post('timesheet-approvals/bulk-approve', [\App\Http\Controllers\TimesheetApprovalController::class, 'bulkApprove'])->middleware('permission:timesheet_approve')->name('timesheet-approvals.bulk-approve');
        Route::post('timesheet-approvals/bulk-reject', [\App\Http\Controllers\TimesheetApprovalController::class, 'bulkReject'])->middleware('permission:timesheet_approve')->name('timesheet-approvals.bulk-reject');

        // Timesheet reports
        Route::get('timesheet-reports', [\App\Http\Controllers\TimesheetReportController::class, 'index'])->middleware('permission:report_timesheet')->name('timesheet-reports.index');
        Route::post('timesheet-reports/generate', [\App\Http\Controllers\TimesheetReportController::class, 'generate'])->middleware('permission:report_timesheet')->name('timesheet-reports.generate');
        Route::get('timesheet-reports/dashboard-widgets', [\App\Http\Controllers\TimesheetReportController::class, 'dashboardWidgets'])->middleware('permission:report_dashboard_widgets')->name('timesheet-reports.dashboard-widgets');

        // Customer reports
        Route::get('customer-reports', [\App\Http\Controllers\CustomerReportController::class, 'index'])->middleware('permission:report_customer')->name('customer-reports.index');
        Route::post('customer-reports/generate', [\App\Http\Controllers\CustomerReportController::class, 'generate'])->middleware('permission:report_customer')->name('customer-reports.generate');

        // Budget & Expense routes
        Route::get('budgets/dashboard', [\App\Http\Controllers\BudgetDashboardController::class, 'index'])->middleware('permission:budget_dashboard_view')->name('budgets.dashboard');
        Route::get('budgets', [\App\Http\Controllers\ProjectBudgetController::class, 'index'])->middleware('permission:budget_view_any')->name('budgets.index');
        Route::post('budgets', [\App\Http\Controllers\ProjectBudgetController::class, 'store'])->middleware('permission:budget_create')->name('budgets.store');
        Route::get('budgets/{budget}', [\App\Http\Controllers\ProjectBudgetController::class, 'show'])->middleware('permission:budget_view')->name('budgets.show');
        Route::put('budgets/{budget}', [\App\Http\Controllers\ProjectBudgetController::class, 'update'])->middleware('permission:budget_update')->name('budgets.update');
        Route::patch('budgets/{budget}', [\App\Http\Controllers\ProjectBudgetController::class, 'update'])->middleware('permission:budget_update');
        Route::delete('budgets/{budget}', [\App\Http\Controllers\ProjectBudgetController::class, 'destroy'])->middleware('permission:budget_delete')->name('budgets.destroy');
        Route::get('budgets/default-categories', [\App\Http\Controllers\ProjectBudgetController::class, 'getDefaultCategories'])->middleware('permission:budget_manage_categories')->name('budgets.default-categories');

        // Budget categories
        Route::get('budgets/{budget}/categories', [\App\Http\Controllers\BudgetCategoryController::class, 'index'])->middleware('permission:budget_manage_categories')->name('budget-categories.index');
        Route::post('budgets/{budget}/categories', [\App\Http\Controllers\BudgetCategoryController::class, 'store'])->middleware('permission:budget_manage_categories')->name('budget-categories.store');
        Route::put('budget-categories/{category}', [\App\Http\Controllers\BudgetCategoryController::class, 'update'])->middleware('permission:budget_manage_categories')->name('budget-categories.update');
        Route::delete('budget-categories/{category}', [\App\Http\Controllers\BudgetCategoryController::class, 'destroy'])->middleware('permission:budget_manage_categories')->name('budget-categories.destroy');
        Route::post('budgets/{budget}/categories/reorder', [\App\Http\Controllers\BudgetCategoryController::class, 'reorder'])->middleware('permission:budget_manage_categories')->name('budget-categories.reorder');

        // Budget revisions
        Route::post('budgets/{budget}/revisions', [\App\Http\Controllers\BudgetRevisionController::class, 'store'])->middleware('permission:budget_manage_workflows')->name('budget-revisions.store');
        Route::post('budget-revisions/{revision}/approve', [\App\Http\Controllers\BudgetRevisionController::class, 'approve'])->middleware('permission:budget_approve')->name('budget-revisions.approve');
        Route::post('budget-revisions/{revision}/reject', [\App\Http\Controllers\BudgetRevisionController::class, 'reject'])->middleware('permission:budget_approve')->name('budget-revisions.reject');

        // Expense routes
        Route::get('expenses', [\App\Http\Controllers\ProjectExpenseController::class, 'index'])->middleware('permission:expense_view_any')->name('expenses.index');
        Route::get('expenses/create', [\App\Http\Controllers\ProjectExpenseController::class, 'create'])->middleware('permission:expense_create')->name('expenses.create');
        Route::post('expenses', [\App\Http\Controllers\ProjectExpenseController::class, 'store'])->middleware('permission:expense_create')->name('expenses.store');
        Route::get('expenses/{expense}', [\App\Http\Controllers\ProjectExpenseController::class, 'show'])->middleware('permission:expense_view')->name('expenses.show');
        Route::get('expenses/{expense}/edit', [\App\Http\Controllers\ProjectExpenseController::class, 'edit'])->middleware('permission:expense_update')->name('expenses.edit');
        Route::put('expenses/{expense}', [\App\Http\Controllers\ProjectExpenseController::class, 'update'])->middleware('permission:expense_update')->name('expenses.update');
        Route::patch('expenses/{expense}', [\App\Http\Controllers\ProjectExpenseController::class, 'update'])->middleware('permission:expense_update');
        Route::delete('expenses/{expense}', [\App\Http\Controllers\ProjectExpenseController::class, 'destroy'])->middleware('permission:expense_delete')->name('expenses.destroy');

        Route::post('expenses/{expense}/duplicate', [\App\Http\Controllers\ProjectExpenseController::class, 'duplicate'])->middleware('permission:expense_create')->name('expenses.duplicate');
        Route::get('api/projects/{project}/tasks', [\App\Http\Controllers\ProjectExpenseController::class, 'getProjectTasks'])->middleware('permission:expense_view_any')->name('api.projects.tasks');

        // Expense approvals
        Route::get('expense-approvals', [\App\Http\Controllers\ExpenseApprovalController::class, 'index'])->middleware('permission:expense_approval_view_any')->name('expense-approvals.index');
        Route::post('expenses/{expense}/approve', [\App\Http\Controllers\ExpenseApprovalController::class, 'approve'])->middleware('permission:expense_approval_approve')->name('expense-approvals.approve');
        Route::post('expenses/{expense}/reject', [\App\Http\Controllers\ExpenseApprovalController::class, 'reject'])->middleware('permission:expense_approval_reject')->name('expense-approvals.reject');
        Route::post('expenses/{expense}/request-info', [\App\Http\Controllers\ExpenseApprovalController::class, 'requestInfo'])->middleware('permission:expense_approval_request_info')->name('expense-approvals.request-info');
        Route::post('expenses/bulk-approve', [\App\Http\Controllers\ExpenseApprovalController::class, 'bulkApprove'])->middleware('permission:expense_approval_bulk_approve')->name('expense-approvals.bulk-approve');
        Route::get('expenses/pending-approvals', [\App\Http\Controllers\ExpenseApprovalController::class, 'pendingApprovals'])->middleware('permission:expense_approval_view_any')->name('expense-approvals.pending');
        Route::get('expense-approvals/stats', [\App\Http\Controllers\ExpenseApprovalController::class, 'getApprovalStats'])->middleware('permission:expense_approval_view_stats')->name('expense-approvals.stats');
        Route::get('expense-approvals/budget-summary', [\App\Http\Controllers\ExpenseApprovalController::class, 'getBudgetSummary'])->middleware('permission:expense_approval_budget_summary')->name('expense-approvals.budget-summary');

        // Enhanced expense management
        Route::get('expenses/management', [\App\Http\Controllers\ExpenseManagementController::class, 'submittedExpenses'])->middleware('permission:expense_manage_workflows')->name('expenses.management');
        Route::post('expenses/{expense}/process-approval', [\App\Http\Controllers\ExpenseManagementController::class, 'processApproval'])->middleware('permission:expense_manage_workflows')->name('expenses.process-approval');
        Route::post('expenses/bulk-process', [\App\Http\Controllers\ExpenseManagementController::class, 'bulkProcess'])->middleware('permission:expense_manage_workflows')->name('expenses.bulk-process');
        Route::get('expenses/export', [\App\Http\Controllers\ExpenseManagementController::class, 'export'])->middleware('permission:expense_generate_reports')->name('expenses.export');

        // Expense dashboard analytics
        Route::get('api/expense-dashboard/overview', [\App\Http\Controllers\ExpenseDashboardController::class, 'overview'])->middleware('permission:expense_generate_reports')->name('expense-dashboard.overview');
        Route::get('api/expense-dashboard/budget-utilization', [\App\Http\Controllers\ExpenseDashboardController::class, 'budgetUtilization'])->middleware('permission:expense_generate_reports')->name('expense-dashboard.budget-utilization');
        Route::get('api/expense-dashboard/trends', [\App\Http\Controllers\ExpenseDashboardController::class, 'trends'])->middleware('permission:expense_generate_reports')->name('expense-dashboard.trends');
        Route::get('api/expense-dashboard/alerts', [\App\Http\Controllers\ExpenseDashboardController::class, 'alerts'])->middleware('permission:expense_generate_reports')->name('expense-dashboard.alerts');

        // Expense workflows
        Route::post('expense-workflows/{workflow}/process', [\App\Http\Controllers\ExpenseWorkflowController::class, 'processStep'])->middleware('permission:expense_manage_workflows')->name('expense-workflows.process');
        Route::post('expense-workflows/bulk-process', [\App\Http\Controllers\ExpenseWorkflowController::class, 'bulkProcess'])->middleware('permission:expense_manage_workflows')->name('expense-workflows.bulk-process');
        Route::get('my-approvals', function() {
            return \Inertia\Inertia::render('expenses/MyApprovals');
        })->middleware('permission:expense_approval_view_any')->name('expense-workflows.my-approvals');
        Route::get('api/my-approvals', [\App\Http\Controllers\ExpenseWorkflowController::class, 'myApprovals'])->middleware('permission:expense_approval_view_any')->name('api.expense-workflows.my-approvals');

        // Receipt management
        Route::post('expenses/{expense}/receipts', [\App\Http\Controllers\ExpenseReceiptController::class, 'upload'])->middleware('permission:expense_add_attachments')->name('expense-receipts.upload');
        Route::delete('expense-attachments/{attachment}', [\App\Http\Controllers\ExpenseReceiptController::class, 'destroy'])->middleware('permission:expense_add_attachments')->name('expense-receipts.destroy');
        Route::get('expense-attachments/{attachment}/download', [\App\Http\Controllers\ExpenseReceiptController::class, 'download'])->middleware('permission:expense_add_attachments')->name('expense-receipts.download');

        // Budget Reports & Dashboard
        Route::get('reports/budget-vs-actual', [\App\Http\Controllers\ExpenseReportController::class, 'budgetVsActual'])->middleware('permission:report_budget_vs_actual')->name('reports.budget-vs-actual');
        Route::get('reports/category-report', [\App\Http\Controllers\ExpenseReportController::class, 'categoryReport'])->middleware('permission:report_category')->name('reports.category');
        Route::get('reports/team-report', [\App\Http\Controllers\ExpenseReportController::class, 'teamReport'])->middleware('permission:report_team')->name('reports.team');
        Route::post('reports/export', [\App\Http\Controllers\ExpenseReportController::class, 'export'])->middleware('permission:report_export')->name('reports.export');

        Route::get('api/budget-dashboard/overview', [\App\Http\Controllers\BudgetDashboardController::class, 'overview'])->middleware('permission:budget_generate_reports')->name('budget-dashboard.overview');
        Route::get('budget-dashboard/alerts', [\App\Http\Controllers\BudgetDashboardController::class, 'alerts'])->middleware('permission:budget_manage_alerts')->name('budget-dashboard.alerts');
        Route::get('budget-dashboard/trends', [\App\Http\Controllers\BudgetDashboardController::class, 'trends'])->middleware('permission:budget_generate_reports')->name('budget-dashboard.trends');

        // Invoice routes
        Route::get('invoices', [\App\Http\Controllers\InvoiceController::class, 'index'])->middleware('permission:invoice_view_any')->name('invoices.index');
        Route::get('invoices/create', [\App\Http\Controllers\InvoiceController::class, 'create'])->middleware('permission:invoice_create')->name('invoices.create');
        Route::post('invoices', [\App\Http\Controllers\InvoiceController::class, 'store'])->middleware('permission:invoice_create')->name('invoices.store');
        Route::get('invoices/{invoice}', [\App\Http\Controllers\InvoiceController::class, 'show'])->middleware('permission:invoice_view')->name('invoices.show');
        Route::get('invoices/{invoice}/edit', [\App\Http\Controllers\InvoiceController::class, 'edit'])->middleware('permission:invoice_update')->name('invoices.edit');
        Route::put('invoices/{invoice}', [\App\Http\Controllers\InvoiceController::class, 'update'])->middleware('permission:invoice_update')->name('invoices.update');
        Route::patch('invoices/{invoice}', [\App\Http\Controllers\InvoiceController::class, 'update'])->middleware('permission:invoice_update');
        Route::delete('invoices/{invoice}', [\App\Http\Controllers\InvoiceController::class, 'destroy'])->middleware('permission:invoice_delete')->name('invoices.destroy');

        Route::post('invoices/{invoice}/mark-paid', [\App\Http\Controllers\InvoiceController::class, 'markAsPaid'])->middleware('permission:invoice_manage_payments')->name('invoices.mark-paid');
        Route::post('invoices/{invoice}/send', [\App\Http\Controllers\InvoiceController::class, 'send'])->middleware('permission:invoice_send')->name('invoices.send');
        Route::get('api/projects/{project}/invoice-data', [\App\Http\Controllers\InvoiceController::class, 'getProjectInvoiceData'])->middleware('permission:invoice_view_any')->name('api.projects.invoice-data');

        // Invoice payment routes
        Route::get('invoices/{invoice}/payment-methods', [\App\Http\Controllers\InvoiceController::class, 'getPaymentMethods'])->middleware('permission:invoice_manage_payments')->name('invoices.payment-methods');
        Route::post('invoices/{invoice}/process-payment', [\App\Http\Controllers\InvoiceController::class, 'processPayment'])->middleware('permission:invoice_manage_payments')->name('invoices.process-payment');

        Route::middleware('auth')->group(function () {
            Route::get('impersonate/{userId}', [ImpersonateController::class, 'start'])->name('impersonate.start');
        });

        Route::post('impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');
    }); // End plan.access middleware group
});


require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

Route::match(['GET', 'POST'], 'payments/easebuzz/success', [EasebuzzPaymentController::class, 'success'])->name('easebuzz.success');
Route::post('payments/easebuzz/callback', [EasebuzzPaymentController::class, 'callback'])->name('easebuzz.callback');
