<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\ProjectExpense;
use App\Models\TimesheetEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);

        $query = Invoice::with(['project:id,title', 'client:id,name,avatar', 'creator:id,name'])
            ->where('workspace_id', $workspace->id);

        // Apply role-based filtering
        if (in_array($userWorkspaceRole, ['manager', 'member'])) {
            $query->where(function($q) use ($user, $userWorkspaceRole) {
                // Show sent invoices to all members
                $q->where('status', '!=', 'draft')
                  ->whereHas('project', function($projQ) use ($user) {
                      $projQ->where(function($projectQuery) use ($user) {
                          $projectQuery->whereHas('members', function($memberQuery) use ($user) {
                              $memberQuery->where('user_id', $user->id);
                          })->orWhere('created_by', $user->id);
                      });
                  });
                
                // Show draft invoices only to managers
                if ($userWorkspaceRole === 'manager') {
                    $q->orWhere('status', 'draft')
                      ->whereHas('project', function($projQ) use ($user) {
                          $projQ->where(function($projectQuery) use ($user) {
                              $projectQuery->whereHas('members', function($memberQuery) use ($user) {
                                  $memberQuery->where('user_id', $user->id);
                              })->orWhere('created_by', $user->id);
                          });
                      });
                }
            });
        } elseif ($userWorkspaceRole === 'client') {
            // Clients only see sent invoices assigned to them
            $query->where('client_id', $user->id)
                  ->where('status', '!=', 'draft');
        }
        // Owners see all invoices (no additional filtering needed)

        // Apply filters
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('invoice_number', 'like', '%' . $request->search . '%')
                  ->orWhere('title', 'like', '%' . $request->search . '%')
                  ->orWhereHas('project', function($projQ) use ($request) {
                      $projQ->where('title', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->client_id) {
            $query->where('client_id', $request->client_id);
        }

        $perPage = $request->get('per_page', 12);
        $invoices = $query->latest()->paginate($perPage)->withQueryString();
        
        // Debug: Log invoices without projects
        $invoicesWithoutProject = $invoices->getCollection()->filter(function($invoice) {
            return is_null($invoice->project);
        });
        
        if ($invoicesWithoutProject->count() > 0) {
            \Log::warning('Found invoices without projects:', [
                'count' => $invoicesWithoutProject->count(),
                'invoice_ids' => $invoicesWithoutProject->pluck('id')->toArray()
            ]);
        }

        // Get projects for filter dropdown
        $projectsQuery = Project::forWorkspace($workspace->id);
        if (in_array($userWorkspaceRole, ['manager', 'member'])) {
            $projectsQuery->where(function($q) use ($user) {
                $q->whereHas('members', function($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                })->orWhere('created_by', $user->id);
            });
        } elseif ($userWorkspaceRole === 'client') {
            $projectsQuery->whereHas('clients', function($clientQuery) use ($user) {
                $clientQuery->where('user_id', $user->id);
            });
        }
        $projects = $projectsQuery->get(['id', 'title']);

        // Get clients for filter dropdown
        $clients = $workspace->users()
            ->whereHas('roles', function($q) {
                $q->where('name', 'client');
            })
            ->get(['users.id', 'users.name']);

        return Inertia::render('invoices/Index', [
            'invoices' => $invoices,
            'projects' => $projects,
            'clients' => $clients,
            'filters' => $request->only(['search', 'status', 'project_id', 'client_id', 'per_page']),
            'userWorkspaceRole' => $userWorkspaceRole
        ]);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['project', 'client', 'creator', 'items.task', 'items.expense', 'items.timesheetEntry']);
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Check access permissions for draft invoices
        if ($invoice->status === 'draft') {
            // Only managers and owners can view draft invoices
            if (!in_array($userWorkspaceRole, ['owner', 'manager'])) {
                abort(403, 'Access denied. Draft invoices are only visible to managers and owners.');
            }
        } elseif ($userWorkspaceRole === 'client') {
            // Clients can only view invoices assigned to them
            if ($invoice->client_id !== $user->id) {
                abort(403, 'Access denied.');
            }
        }
        
        return Inertia::render('invoices/Show', [
            'invoice' => $invoice,
            'userWorkspaceRole' => $userWorkspaceRole
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'client_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:task',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.task_id' => 'required|exists:tasks,id',
        ]);

        $project = Project::findOrFail($validated['project_id']);

        // Calculate totals
        $subtotal = collect($validated['items'])->sum('amount');
        $taxAmount = ($subtotal * ($validated['tax_rate'] ?? 0)) / 100;
        $totalAmount = $subtotal + $taxAmount - ($validated['discount_amount'] ?? 0);
        
        $invoice = Invoice::create([
            'project_id' => $validated['project_id'],
            'workspace_id' => $project->workspace_id,
            'client_id' => $validated['client_id'],
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'tax_rate' => $validated['tax_rate'] ?? 0,
            'discount_amount' => $validated['discount_amount'] ?? 0,
            'notes' => $validated['notes'],
            'terms' => $validated['terms'],
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);

        // Create invoice items
        foreach ($validated['items'] as $index => $item) {
            $task = Task::find($item['task_id']);
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'type' => 'task',
                'description' => $task ? $task->title : 'Task',
                'rate' => $item['amount'],
                'amount' => $item['amount'],
                'task_id' => $item['task_id'],
                'sort_order' => $index + 1,
            ]);
        }

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice created successfully!'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:custom,task,expense,time',
            'items.*.description' => 'required|string',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.task_id' => 'nullable|exists:tasks,id',
            'items.*.expense_id' => 'nullable|exists:project_expenses,id',
            'items.*.timesheet_entry_id' => 'nullable|exists:timesheet_entries,id',
        ]);

        $invoice->update([
            'client_id' => $validated['client_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'tax_rate' => $validated['tax_rate'] ?? 0,
            'discount_amount' => $validated['discount_amount'] ?? 0,
            'notes' => $validated['notes'],
            'terms' => $validated['terms'],
        ]);

        // Update items
        $invoice->items()->delete();
        foreach ($validated['items'] as $index => $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'type' => $item['type'],
                'description' => $item['description'],
                'rate' => $item['rate'],
                'amount' => $item['rate'],
                'task_id' => $item['task_id'] ?? null,
                'expense_id' => $item['expense_id'] ?? null,
                'timesheet_entry_id' => $item['timesheet_entry_id'] ?? null,
                'sort_order' => $index + 1,
            ]);
        }

        // Recalculate totals after updating items
        $invoice->calculateTotals();
        
        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice updated successfully!'));
    }

    public function create()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $projects = Project::forWorkspace($workspace->id)->get(['id', 'title']);
        $clients = $workspace->users()->whereHas('roles', function($q) {
            $q->where('name', 'client');
        })->get(['users.id', 'users.name']);
        
        return Inertia::render('invoices/Form', [
            'projects' => $projects,
            'clients' => $clients
        ]);
    }
    
    public function getProjectInvoiceData($projectId)
    {
        try {
            $project = Project::findOrFail($projectId);
            
            // Get project tasks
            $tasks = $project->tasks()->get(['id', 'title']);
            
            // Get project clients using the clients relationship
            $clients = $project->clients()->get(['users.id', 'users.name']);
            
            return response()->json([
                'tasks' => $tasks,
                'clients' => $clients
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading project invoice data: ' . $e->getMessage());
            return response()->json([
                'tasks' => [],
                'clients' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load(['items']);
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Only managers and owners can edit invoices
        if (!in_array($userWorkspaceRole, ['owner', 'manager'])) {
            abort(403, 'Access denied. Only managers and owners can edit invoices.');
        }
        
        $projects = Project::forWorkspace($workspace->id)->get(['id', 'title']);
        $clients = $workspace->users()->whereHas('roles', function($q) {
            $q->where('name', 'client');
        })->get(['users.id', 'users.name']);
        
        return Inertia::render('invoices/Form', [
            'invoice' => $invoice,
            'projects' => $projects,
            'clients' => $clients
        ]);
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return back()->with('success', __('Invoice deleted successfully!'));
    }



    public function markAsPaid(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'paid_amount' => 'nullable|numeric|min:0|max:' . $invoice->total_amount,
            'payment_method' => 'nullable|string',
            'payment_reference' => 'nullable|string',
            'payment_details' => 'nullable|array',
        ]);

        $invoice->markAsPaid(
            $validated['paid_amount'] ?? null,
            $validated['payment_method'] ?? null,
            $validated['payment_reference'] ?? null,
            $validated['payment_details'] ?? null
        );
        return back()->with('success', __('Invoice marked as paid successfully!'));
    }

    public function send(Invoice $invoice)
    {
        $invoice->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);
        
        return back()->with('success', __('Invoice sent successfully!'));
    }

    public function getProjectData(Project $project)
    {
        $tasks = $project->tasks()
            ->with('taskStage')
            ->where('status', 'completed')
            ->get(['id', 'title', 'task_stage_id']);

        $expenses = $project->expenses()
            ->with('budgetCategory')
            ->where('status', 'approved')
            ->get(['id', 'title', 'amount', 'currency', 'budget_category_id']);

        $timesheetEntries = TimesheetEntry::whereHas('timesheet', function($q) use ($project) {
                $q->where('project_id', $project->id);
            })
            ->with(['task', 'user'])
            ->get(['id', 'task_id', 'user_id', 'hours', 'description']);

        return response()->json([
            'tasks' => $tasks,
            'expenses' => $expenses,
            'timesheet_entries' => $timesheetEntries
        ]);
    }

    public function processPayment(Request $request, Invoice $invoice)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        // Check if user has client role in workspace
        $userWorkspaceRole = $workspace->getMemberRole($user);
        if ($userWorkspaceRole !== 'client') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'payment_method' => 'required|string|in:stripe,paypal',
            'payment_data' => 'required|array',
        ]);

        // Get workspace owner payment settings
        $workspaceOwner = $workspace->users()->wherePivot('role', 'owner')->first();
        $ownerId = $workspaceOwner ? $workspaceOwner->id : $workspace->created_by;
        
        $paymentSettings = \App\Models\PaymentSetting::getUserSettings(
            $ownerId, 
            $workspace->id
        );

        try {
            $paymentResult = $this->processInvoicePayment(
                $invoice,
                $validated['payment_method'],
                $validated['payment_data'],
                $paymentSettings
            );

            if ($paymentResult['success']) {
                // Mark as paid for immediate payment methods
                $invoice->markAsPaid(
                    $invoice->total_amount,
                    $validated['payment_method'],
                    $paymentResult['reference'],
                    $paymentResult['details']
                );

                return back()->with('success', $paymentResult['message'] ?? __('Payment processed successfully!'));
            } else {
                return back()->with('error', $paymentResult['message']);
            }
        } catch (\Exception $e) {
            \Log::error('Invoice payment error: ' . $e->getMessage());
            return back()->with('error', __('Payment processing failed'));
        }
    }

    private function processInvoicePayment($invoice, $method, $data, $settings)
    {
        switch ($method) {
            case 'stripe':
                return $this->processStripePayment($invoice, $data, $settings);
            case 'paypal':
                return $this->processPayPalPayment($invoice, $data, $settings);
            default:
                return ['success' => false, 'message' => 'Invalid payment method'];
        }
    }

    private function processStripePayment($invoice, $data, $settings)
    {
        if (!isset($settings['stripe_secret']) || !($settings['is_stripe_enabled'] ?? false)) {
            return ['success' => false, 'message' => 'Stripe not configured'];
        }

        // Mock Stripe processing - replace with actual Stripe integration
        // In a real implementation, you would:
        // 1. Create payment intent with Stripe API
        // 2. Confirm payment with the payment method
        // 3. Handle the response
        
        return [
            'success' => true,
            'reference' => 'stripe_' . uniqid(),
            'details' => [
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'cardholder_name' => $data['cardholder_name'] ?? null,
                'status' => 'completed',
            ]
        ];
    }

    private function processPayPalPayment($invoice, $data, $settings)
    {
        if (!isset($settings['paypal_client_id']) || !($settings['is_paypal_enabled'] ?? false)) {
            return ['success' => false, 'message' => 'PayPal not configured'];
        }

        // Mock PayPal processing - replace with actual PayPal integration
        // In a real implementation, you would:
        // 1. Verify the PayPal order/payment
        // 2. Capture the payment
        // 3. Handle the response
        
        return [
            'success' => true,
            'reference' => 'paypal_' . uniqid(),
            'details' => [
                'order_id' => $data['order_id'] ?? null,
                'payment_id' => $data['payment_id'] ?? null,
                'status' => 'completed',
            ]
        ];
    }



    public function getPaymentMethods(Invoice $invoice)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        // Get workspace owner payment settings
        $workspaceOwner = $workspace->users()->wherePivot('role', 'owner')->first();
        $ownerId = $workspaceOwner ? $workspaceOwner->id : $workspace->created_by;
        
        $paymentSettings = \App\Models\PaymentSetting::getUserSettings(
            $ownerId, 
            $workspace->id
        );

        $methods = [];

        if ($paymentSettings['is_stripe_enabled'] ?? false) {
            $methods[] = [
                'id' => 'stripe',
                'name' => 'Credit Card (Stripe)',
                'enabled' => true,
                'config' => [
                    'public_key' => $paymentSettings['stripe_key'] ?? null
                ]
            ];
        }

        if ($paymentSettings['is_paypal_enabled'] ?? false) {
            $methods[] = [
                'id' => 'paypal',
                'name' => 'PayPal',
                'enabled' => true,
                'config' => [
                    'client_id' => $paymentSettings['paypal_client_id'] ?? null
                ]
            ];
        }



        return response()->json($methods);
    }
}