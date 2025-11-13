import React, { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { CheckCircle, XCircle, AlertCircle, Clock, Search, Filter, LayoutGrid, List } from 'lucide-react';
import { router, usePage } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

interface Props {
    expenses: any;
    stats: any;
    projects: any[];
    filters: any;
    permissions?: any;
}

export default function Approvals({ expenses, stats, projects, filters, permissions }: Props) {
    const { t } = useTranslation();
    const { flash, permissions: pagePermissions } = usePage().props as any;
    const approvalPermissions = permissions || pagePermissions;
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters?.status || 'all');
    const [selectedProject, setSelectedProject] = useState(filters?.project_id || 'all');
    const [showFilters, setShowFilters] = useState(false);
    const [viewMode, setViewMode] = useState('cards');

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);
    
    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };
    
    const applyFilters = () => {
        const params: any = { page: 1 };
        
        if (searchTerm) params.search = searchTerm;
        if (selectedStatus !== 'all') params.status = selectedStatus;
        if (selectedProject !== 'all') params.project_id = selectedProject;
        
        router.get(route('expense-approvals.index'), params, { preserveState: true });
    };
    
    const resetFilters = () => {
        setSearchTerm('');
        setSelectedStatus('all');
        setSelectedProject('all');
        router.get(route('expense-approvals.index'), { page: 1 });
    };
    
    const hasActiveFilters = () => {
        return searchTerm || selectedStatus !== 'all' || selectedProject !== 'all';
    };
    const processApproval = (expenseId: number, action: string) => {
        const actionText = action === 'approve' ? 'Approving' : action === 'reject' ? 'Rejecting' : 'Processing';
        toast.loading(`${actionText} expense...`);
        
        const routeName = action === 'approve' ? 'expense-approvals.approve' : 
                         action === 'reject' ? 'expense-approvals.reject' : 
                         'expense-approvals.request-info';
        
        const data: any = {};
        
        // For rejection, we can provide a default note or leave it empty
        if (action === 'reject') {
            data.notes = 'Expense rejected by approver';
        } else if (action === 'request_info') {
            data.notes = 'Additional information required';
        } else {
            data.notes = '';
        }
        
        router.post(route(routeName, expenseId), data, {
            onSuccess: () => {
                toast.dismiss();
            },
            onError: (errors) => {
                toast.dismiss();
                console.error('Expense approval error:', errors);
                
                // Show specific error message if available
                const errorMessage = errors?.message || `Failed to ${action} expense`;
                toast.error(errorMessage);
            }
        });
    };



    const breadcrumbs = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: t('Budget & Expenses') },
        { title: 'Expense Approvals' }
    ];

    return (
        <PageTemplate title={t('Expense Approvals')} breadcrumbs={breadcrumbs} noPadding>
            <div className="space-y-6">
                {/* Overview Row */}
                <div className="bg-white rounded-lg shadow p-4">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div className="text-center">
                            <div className="text-2xl font-bold text-yellow-600">{stats.pending_count}</div>
                            <div className="text-sm text-gray-600">Pending Approval</div>
                        </div>
                        <div className="text-center">
                            <div className="text-2xl font-bold text-blue-600">{stats.requires_info_count}</div>
                            <div className="text-sm text-gray-600">Requires Info</div>
                        </div>
                        <div className="text-center">
                            <div className="text-2xl font-bold text-green-600">{stats.approved_today}</div>
                            <div className="text-sm text-gray-600">Approved Today</div>
                        </div>
                        <div className="text-center">
                            <div className="text-2xl font-bold text-gray-800">{formatCurrency(stats.pending_amount || 0)}</div>
                            <div className="text-sm text-gray-600">Pending Amount</div>
                        </div>
                    </div>
                </div>

                {/* Filters Row */}
                <div className="bg-white rounded-lg shadow">
                    <div className="p-4">
                        <div className="flex items-center justify-between mb-3">
                            <div className="flex items-center gap-2">
                                <form onSubmit={handleSearch} className="flex gap-2">
                                    <div className="relative w-64">
                                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" />
                                        <Input
                                            placeholder="Search expenses..."
                                            value={searchTerm}
                                            onChange={(e) => {
                                                setSearchTerm(e.target.value);
                                                clearTimeout(window.searchTimeout);
                                                window.searchTimeout = setTimeout(() => {
                                                    const params: any = { page: 1 };
                                                    if (e.target.value) params.search = e.target.value;
                                                    if (selectedStatus !== 'all') params.status = selectedStatus;
                                                    if (selectedProject !== 'all') params.project_id = selectedProject;
                                                    router.get(route('expense-approvals.index'), params, { preserveState: true });
                                                }, 500);
                                            }}
                                            className="pl-9"
                                        />
                                    </div>
                                    <Button type="submit" size="sm">
                                        <Search className="h-4 w-4 mr-1.5" />
                                        Search
                                    </Button>
                                </form>
                                
                                <Button 
                                    variant={hasActiveFilters() ? "default" : "outline"}
                                    size="sm"
                                    onClick={() => setShowFilters(!showFilters)}
                                >
                                    <Filter className="h-4 w-4 mr-1.5" />
                                    Filters
                                    {hasActiveFilters() && (
                                        <span className="ml-1 bg-primary-foreground text-primary rounded-full w-5 h-5 flex items-center justify-center text-xs">
                                            {(searchTerm ? 1 : 0) + (selectedStatus !== 'all' ? 1 : 0) + (selectedProject !== 'all' ? 1 : 0)}
                                        </span>
                                    )}
                                </Button>
                            </div>
                            
                            <div className="flex items-center gap-2">
                                <Select value={expenses.per_page?.toString() || '20'} onValueChange={(value) => {
                                    const params: any = { page: 1, per_page: parseInt(value) };
                                    if (searchTerm) params.search = searchTerm;
                                    if (selectedStatus !== 'all') params.status = selectedStatus;
                                    if (selectedProject !== 'all') params.project_id = selectedProject;
                                    router.get(route('expense-approvals.index'), params, { preserveState: true });
                                }}>
                                    <SelectTrigger className="w-20">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="10">10</SelectItem>
                                        <SelectItem value="20">20</SelectItem>
                                        <SelectItem value="50">50</SelectItem>
                                        <SelectItem value="100">100</SelectItem>
                                    </SelectContent>
                                </Select>
                                
                                <div className="border rounded-md p-0.5">
                                    <Button 
                                        size="sm" 
                                        variant={viewMode === 'cards' ? "default" : "ghost"}
                                        className="h-7 px-2"
                                        onClick={() => setViewMode('cards')}
                                    >
                                        <LayoutGrid className="h-4 w-4" />
                                    </Button>
                                    <Button 
                                        size="sm" 
                                        variant={viewMode === 'table' ? "default" : "ghost"}
                                        className="h-7 px-2"
                                        onClick={() => setViewMode('table')}
                                    >
                                        <List className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                        
                        {showFilters && (
                            <div className="p-4 bg-gray-50 rounded-md">
                                <div className="flex gap-4 items-end">
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">Status</label>
                                        <Select value={selectedStatus} onValueChange={(value) => {
                                            setSelectedStatus(value);
                                            const params: any = { page: 1 };
                                            if (searchTerm) params.search = searchTerm;
                                            if (value !== 'all') params.status = value;
                                            if (selectedProject !== 'all') params.project_id = selectedProject;
                                            router.get(route('expense-approvals.index'), params, { preserveState: true });
                                        }}>
                                            <SelectTrigger className="w-40">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All</SelectItem>
                                                <SelectItem value="pending">Pending</SelectItem>
                                                <SelectItem value="approved">Approved</SelectItem>
                                                <SelectItem value="rejected">Rejected</SelectItem>
                                                <SelectItem value="requires_info">Requires Info</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">Project</label>
                                        <Select value={selectedProject} onValueChange={(value) => {
                                            setSelectedProject(value);
                                            const params: any = { page: 1 };
                                            if (searchTerm) params.search = searchTerm;
                                            if (selectedStatus !== 'all') params.status = selectedStatus;
                                            if (value !== 'all') params.project_id = value;
                                            router.get(route('expense-approvals.index'), params, { preserveState: true });
                                        }}>
                                            <SelectTrigger className="w-48">
                                                <SelectValue placeholder="All Projects" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All</SelectItem>
                                                {projects?.map((project: any) => (
                                                    <SelectItem key={project.id} value={project.id.toString()}>
                                                        {project.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    
                                    <Button variant="outline" size="sm" onClick={resetFilters} disabled={!hasActiveFilters()}>
                                        Reset Filters
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
                
                {expenses && expenses.data && expenses.data.length > 0 ? (
                    viewMode === 'cards' ? (
                        <div className="space-y-6">
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                {expenses.data.map((expense: any) => (
                                    <Card key={expense.id} className="hover:shadow-lg transition-shadow">
                                        <CardHeader className="pb-3">
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <CardTitle className="text-lg mb-1">
                                                        {expense.title}
                                                    </CardTitle>
                                                    <div className="text-sm text-gray-500 mb-2">
                                                        <span className="font-medium">{expense.project?.title}</span>
                                                    </div>
                                                    <div className="text-xs text-gray-400">
                                                        Submitted by {expense.submitter?.name} • {new Date(expense.expense_date).toLocaleDateString()}
                                                    </div>
                                                </div>
                                                <div className="text-right ml-4">
                                                    <div className="text-xl font-bold text-gray-900 mb-1">
                                                        {formatCurrency(expense.amount)}
                                                    </div>
                                                    <Badge variant="secondary" className={expense.status === 'requires_info' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'}>
                                                        {expense.status === 'requires_info' ? 'Requires Info' : 'Pending'}
                                                    </Badge>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        
                                        <CardContent className="pt-0">
                                            {expense.description && (
                                                <div className="mb-4">
                                                    <p className="text-sm text-gray-600 line-clamp-2">{expense.description}</p>
                                                </div>
                                            )}
                                            
                                            {expense.budget_category && (
                                                <div className="mb-4">
                                                    <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                        {expense.budget_category.name}
                                                    </span>
                                                </div>
                                            )}

                                            <div className="flex gap-2">
                                                {approvalPermissions?.approve && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() => processApproval(expense.id, 'approve')}
                                                        className="bg-green-600 hover:bg-green-700 flex-1"
                                                    >
                                                        <CheckCircle className="h-4 w-4 mr-1" />
                                                        Approve
                                                    </Button>
                                                )}
                                                {approvalPermissions?.reject && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() => processApproval(expense.id, 'reject')}
                                                        variant="destructive"
                                                        className="flex-1"
                                                    >
                                                        <XCircle className="h-4 w-4 mr-1" />
                                                        Reject
                                                    </Button>
                                                )}
                                                {approvalPermissions?.request_info && expense.status === 'pending' && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() => processApproval(expense.id, 'request_info')}
                                                        variant="outline"
                                                        className="flex-1"
                                                    >
                                                        <AlertCircle className="h-4 w-4 mr-1" />
                                                        Info
                                                    </Button>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                            
                            {/* Pagination for cards */}
                            <div className="bg-white rounded-lg shadow p-4 flex items-center justify-between">
                                <div className="text-sm text-gray-600">
                                    Showing {expenses.from} to {expenses.to} of {expenses.total} expenses
                                </div>
                                
                                <div className="flex gap-1">
                                    {expenses.links?.map((link: any, i: number) => {
                                        const isTextLink = link.label === "&laquo; Previous" || link.label === "Next &raquo;";
                                        const label = link.label.replace("&laquo; ", "").replace(" &raquo;", "");
                                        
                                        return (
                                            <Button
                                                key={i}
                                                variant={link.active ? 'default' : 'outline'}
                                                size={isTextLink ? "sm" : "icon"}
                                                className={isTextLink ? "px-3" : "h-8 w-8"}
                                                disabled={!link.url}
                                                onClick={() => link.url && router.get(link.url)}
                                            >
                                                {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                            </Button>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="bg-white rounded-lg shadow overflow-hidden">
                            <table className="w-full">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expense</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project & Category</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {expenses.data.map((expense: any) => (
                                        <tr key={expense.id}>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div className="text-sm font-medium text-gray-900">{expense.title}</div>
                                                    <div className="text-sm text-gray-500">by {expense.submitter?.name}</div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm font-medium text-gray-900">{expense.project?.title}</div>
                                                {expense.budget_category && (
                                                    <div className="flex items-center gap-1 mt-1">
                                                        <div 
                                                            className="w-2 h-2 rounded-full"
                                                            style={{ backgroundColor: expense.budget_category.color }}
                                                        />
                                                        <span className="text-xs text-gray-500">{expense.budget_category.name}</span>
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {formatCurrency(expense.amount)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <Badge variant="secondary" className={expense.status === 'requires_info' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'}>
                                                    {expense.status === 'requires_info' ? 'Requires Info' : 'Pending'}
                                                </Badge>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {new Date(expense.expense_date).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div className="flex gap-1">
                                                    {approvalPermissions?.approve && (
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    size="sm"
                                                                    onClick={() => processApproval(expense.id, 'approve')}
                                                                    className="bg-green-600 hover:bg-green-700"
                                                                >
                                                                    <CheckCircle className="h-3 w-3" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>Approve</TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {approvalPermissions?.reject && (
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    size="sm"
                                                                    onClick={() => processApproval(expense.id, 'reject')}
                                                                    variant="destructive"
                                                                >
                                                                    <XCircle className="h-3 w-3" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>Reject</TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {approvalPermissions?.request_info && expense.status === 'pending' && (
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    size="sm"
                                                                    onClick={() => processApproval(expense.id, 'request_info')}
                                                                    variant="outline"
                                                                >
                                                                    <AlertCircle className="h-3 w-3" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>Request Info</TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            
                            {/* Pagination for table */}
                            <div className="p-4 border-t flex items-center justify-between">
                                <div className="text-sm text-gray-600">
                                    Showing {expenses.from} to {expenses.to} of {expenses.total} expenses
                                </div>
                                
                                <div className="flex gap-1">
                                    {expenses.links?.map((link: any, i: number) => {
                                        const isTextLink = link.label === "&laquo; Previous" || link.label === "Next &raquo;";
                                        const label = link.label.replace("&laquo; ", "").replace(" &raquo;", "");
                                        
                                        return (
                                            <Button
                                                key={i}
                                                variant={link.active ? 'default' : 'outline'}
                                                size={isTextLink ? "sm" : "icon"}
                                                className={isTextLink ? "px-3" : "h-8 w-8"}
                                                disabled={!link.url}
                                                onClick={() => link.url && router.get(link.url)}
                                            >
                                                {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                            </Button>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    )
                ) : (
                    <div className="bg-white rounded-lg shadow p-8 text-center">
                        <Clock className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                        <p className="text-gray-500 mb-4">No pending approvals</p>
                        <p className="text-sm text-gray-400">All expenses have been processed</p>
                    </div>
                )}
            </div>
        </PageTemplate>
    );
}