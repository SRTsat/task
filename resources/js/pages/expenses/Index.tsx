import React, { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Plus, Search, Filter, Eye, Edit, Copy, Trash2, LayoutGrid, List, Receipt, Calendar, User as UserIcon, CheckCircle, XCircle, Clock, AlertCircle } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import ExpenseFormModal from '@/components/expenses/ExpenseFormModal';
import { EnhancedDeleteModal } from '@/components/EnhancedDeleteModal';
import { useTranslation } from 'react-i18next';



interface Expense {
    id: number;
    project: {
        id: number;
        title: string;
    };
    budget_category?: {
        id: number;
        name: string;
        color: string;
    };
    submitter: {
        id: number;
        name: string;
        avatar?: string;
    };
    amount: number;
    currency: string;
    expense_date: string;
    title: string;
    description?: string;
    vendor?: string;
    status: 'pending' | 'approved' | 'rejected' | 'requires_info';
    created_at: string;
    can_edit?: boolean;
    can_delete?: boolean;
}

export default function ExpenseIndex() {
    const { t } = useTranslation();
    const { expenses, projects, categories, filters, auth, project_name, userWorkspaceRole, workspace, budget_id, flash, permissions: pagePermissions } = usePage().props as any;
    const expensePermissions = pagePermissions;

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    // Get project name from projects array if not directly provided
    const currentProjectName = project_name || (filters?.project_id ?
        projects?.find((p: any) => p.id.toString() === filters.project_id.toString())?.title
        : null);

    const [activeView, setActiveView] = useState('grid');
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const [selectedProject, setSelectedProject] = useState(filters?.project_id || 'all');
    const [selectedCategory, setSelectedCategory] = useState(filters?.category_id || 'all');
    const [selectedStatus, setSelectedStatus] = useState(filters?.status || 'all');
    const [showFilters, setShowFilters] = useState(false);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [currentExpense, setCurrentExpense] = useState<Expense | null>(null);
    const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');
    const [deleteExpense, setDeleteExpense] = useState<Expense | null>(null);



    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const applyFilters = () => {
        const params: any = { page: 1 };
        if (searchTerm) params.search = searchTerm;
        if (selectedProject !== 'all') params.project_id = selectedProject;
        if (selectedCategory !== 'all') params.category_id = selectedCategory;
        if (selectedStatus !== 'all') params.status = selectedStatus;
        router.get(route('expenses.index'), params, { preserveState: true, preserveScroll: true });
    };

    const handleAction = (action: string, expense: Expense) => {
        switch (action) {
            case 'view':
                router.get(route('expenses.show', expense.id));
                break;
            case 'edit':
                setCurrentExpense(expense);
                setModalMode('edit');
                setIsModalOpen(true);
                break;
            case 'duplicate':
                toast.loading('Duplicating expense...');
                router.post(route('expenses.duplicate', expense.id), {}, {
                    onSuccess: () => {
                        toast.dismiss();
                    },
                    onError: () => {
                        toast.dismiss();
                        toast.error('Failed to duplicate expense');
                    }
                });
                break;
            case 'delete':
                setDeleteExpense(expense);
                break;
        }
    };

    const handleAddNew = () => {
        setCurrentExpense(null);
        setModalMode('create');
        setIsModalOpen(true);
    };

    const getStatusColor = (status: string) => {
        const colors = {
            pending: 'bg-yellow-100 text-yellow-800',
            approved: 'bg-green-100 text-green-800',
            rejected: 'bg-red-100 text-red-800',
            requires_info: 'bg-blue-100 text-blue-800'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'approved': return <CheckCircle className="h-4 w-4 text-green-600" />;
            case 'rejected': return <XCircle className="h-4 w-4 text-red-600" />;
            case 'requires_info': return <AlertCircle className="h-4 w-4 text-blue-600" />;
            default: return <Clock className="h-4 w-4 text-yellow-600" />;
        }
    };

    const formatCurrency = (amount: string | number) => {
        if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
            const numericAmount = typeof amount === 'number' ? amount : parseFloat(amount) || 0;
            return window.appSettings.formatCurrency(numericAmount, { showSymbol: true });
        }
        return amount || 0;
    };

    const pageActions = [];

    if (expensePermissions?.create) {
        pageActions.push({
            label: t('Add Expense'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default',
            onClick: handleAddNew
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Budget & Expenses') },
        ...(currentProjectName ? [
            { title: t('Projects'), href: route('projects.index') },
            { title: t('Budgets'), href: route('budgets.index') }
        ] : []),
        { title: currentProjectName ? `${currentProjectName} - Expenses` : 'Expenses' }
    ];

    return (
        <PageTemplate
            title={currentProjectName ? `${currentProjectName} - Expense Management` : "Expense Management"}
            url="/expenses"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Overview Row */}
            <div className="bg-white rounded-lg shadow mb-4 p-4">
                <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div className="text-center">
                        <div className="text-2xl font-bold text-blue-600">{expenses?.total || 0}</div>
                        <div className="text-sm text-gray-600">{t('Total Expenses')}</div>
                    </div>
                    <div className="text-center">
                        <div className="text-2xl font-bold text-yellow-600">
                            {expenses?.data?.filter((exp: Expense) => exp.status === 'pending').length || 0}
                        </div>
                        <div className="text-sm text-gray-600">{t('Pending')}</div>
                    </div>
                    <div className="text-center">
                        <div className="text-2xl font-bold text-green-600">
                            {expenses?.data?.filter((exp: Expense) => exp.status === 'approved').length || 0}
                        </div>
                        <div className="text-sm text-gray-600">{t('Approved')}</div>
                    </div>
                    <div className="text-center">
                        <div className="text-2xl font-bold text-red-600">
                            {expenses?.data?.filter((exp: Expense) => exp.status === 'rejected').length || 0}
                        </div>
                        <div className="text-sm text-gray-600">{t('Rejected')}</div>
                    </div>
                    <div className="text-center">
                        <div className="text-2xl font-bold text-purple-600">
                            {(() => {
                                if (!expenses?.data || expenses.data.length === 0) {
                                    return formatCurrency(0);
                                }
                                const total = expenses.data.reduce((sum: number, exp: Expense) => {
                                    return sum + (parseFloat(exp.amount?.toString()) || 0);
                                }, 0);
                                const currency = workspace?.currency || expenses.data[0]?.currency || 'USD';
                                return formatCurrency(total);
                            })()
                            }
                        </div>
                        <div className="text-sm text-gray-600">{t('Total Amount')}</div>
                    </div>
                </div>
            </div>

            {/* Filters Row */}
            <div className="bg-white rounded-lg shadow mb-4">
                <div className="p-4">
                    <div className="flex items-center justify-between mb-3">
                        <div className="flex items-center gap-2">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative w-64">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder={t('Search expenses...')}
                                        value={searchTerm}
                                        onChange={(e) => {
                                            setSearchTerm(e.target.value);
                                            clearTimeout(window.searchTimeout);
                                            window.searchTimeout = setTimeout(() => {
                                                const params: any = { page: 1 };
                                                if (e.target.value) params.search = e.target.value;
                                                if (selectedProject !== 'all') params.project_id = selectedProject;
                                                if (selectedCategory !== 'all') params.category_id = selectedCategory;
                                                if (selectedStatus !== 'all') params.status = selectedStatus;
                                                router.get(route('expenses.index'), params, { preserveState: true, preserveScroll: true });
                                            }, 500);
                                        }}
                                        className="w-full pl-9"
                                    />
                                </div>
                                <Button type="submit" size="sm">
                                    <Search className="h-4 w-4 mr-1.5" />
                                    {t('Search')}
                                </Button>
                            </form>

                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setShowFilters(!showFilters)}
                            >
                                <Filter className="h-4 w-4 mr-1.5" />
                                {t('Filters')}
                            </Button>
                        </div>

                        <div className="flex items-center gap-2">
                            <div className="border rounded-md p-0.5">
                                <Button
                                    size="sm"
                                    variant={activeView === 'list' ? "default" : "ghost"}
                                    className="h-7 px-2"
                                    onClick={() => setActiveView('list')}
                                >
                                    <List className="h-4 w-4" />
                                </Button>
                                <Button
                                    size="sm"
                                    variant={activeView === 'grid' ? "default" : "ghost"}
                                    className="h-7 px-2"
                                    onClick={() => setActiveView('grid')}
                                >
                                    <LayoutGrid className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    {showFilters && (
                        <div className="p-4 bg-gray-50 border rounded-md">
                            <div className="flex flex-wrap gap-4 items-end">
                                <div className="space-y-2">
                                    <Label>Project</Label>
                                    <Select value={selectedProject} onValueChange={(value) => {
                                        setSelectedProject(value);
                                        const params: any = { page: 1 };
                                        if (searchTerm) params.search = searchTerm;
                                        if (value !== 'all') params.project_id = value;
                                        if (selectedCategory !== 'all') params.category_id = selectedCategory;
                                        if (selectedStatus !== 'all') params.status = selectedStatus;
                                        router.get(route('expenses.index'), params, { preserveState: true, preserveScroll: true });
                                    }}>
                                        <SelectTrigger className="w-40">
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

                                <div className="space-y-2">
                                    <Label>Category</Label>
                                    <Select value={selectedCategory} onValueChange={(value) => {
                                        setSelectedCategory(value);
                                        const params: any = { page: 1 };
                                        if (searchTerm) params.search = searchTerm;
                                        if (selectedProject !== 'all') params.project_id = selectedProject;
                                        if (value !== 'all') params.category_id = value;
                                        if (selectedStatus !== 'all') params.status = selectedStatus;
                                        router.get(route('expenses.index'), params, { preserveState: true, preserveScroll: true });
                                    }}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder="All Categories" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All</SelectItem>
                                            {categories?.map((category: any) => (
                                                <SelectItem key={category.id} value={category.id.toString()}>
                                                    {category.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label>Status</Label>
                                    <Select value={selectedStatus} onValueChange={(value) => {
                                        setSelectedStatus(value);
                                        const params: any = { page: 1 };
                                        if (searchTerm) params.search = searchTerm;
                                        if (selectedProject !== 'all') params.project_id = selectedProject;
                                        if (selectedCategory !== 'all') params.category_id = selectedCategory;
                                        if (value !== 'all') params.status = value;
                                        router.get(route('expenses.index'), params, { preserveState: true, preserveScroll: true });
                                    }}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder="All Status" />
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

                                <Button variant="outline" size="sm" onClick={() => {
                                    setSelectedProject('all');
                                    setSelectedCategory('all');
                                    setSelectedStatus('all');
                                    setSearchTerm('');
                                    setShowFilters(false);
                                    router.get(route('expenses.index'), { page: 1 }, { preserveState: true, preserveScroll: true });
                                }}>
                                    Reset Filters
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Expense Content */}
            <div className="bg-white rounded-lg shadow">
                {activeView === 'grid' ? (
                    <div className="p-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            {expenses?.data?.map((expense: Expense) => (
                                <Card key={expense.id} className="overflow-hidden hover:shadow-md transition-shadow">
                                    <CardHeader className="pb-2">
                                        <div className="flex justify-between items-start">
                                            <CardTitle className="text-base line-clamp-1">{expense.title}</CardTitle>
                                            <div className="flex items-center gap-1">
                                                {getStatusIcon(expense.status)}
                                                <Badge className={getStatusColor(expense.status)} variant="secondary">
                                                    {expense.status.replace('_', ' ')}
                                                </Badge>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <span>{formatCurrency(expense.amount)}</span>
                                            <span>•</span>
                                            <span>{expense.project.title}</span>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="py-2">
                                        <div className="space-y-3">
                                            {expense.description && (
                                                <p className="text-sm text-gray-600 line-clamp-2">{expense.description}</p>
                                            )}

                                            <div className="flex items-center justify-between text-xs">
                                                <div className="flex items-center gap-1">
                                                    <Calendar className="h-3 w-3" />
                                                    <span>{new Date(expense.expense_date).toLocaleDateString()}</span>
                                                </div>
                                                {expense.budget_category && (
                                                    <div className="flex items-center gap-1">
                                                        <div
                                                            className="w-3 h-3 rounded-full"
                                                            style={{ backgroundColor: expense.budget_category.color }}
                                                        />
                                                        <span>{expense.budget_category.name}</span>
                                                    </div>
                                                )}
                                            </div>

                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Avatar className="h-6 w-6">
                                                        <AvatarImage src={expense.submitter.avatar} />
                                                        <AvatarFallback className="text-xs">
                                                            {expense.submitter.name?.charAt(0)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="text-xs text-muted-foreground">{expense.submitter.name}</span>
                                                </div>

                                                {expense.vendor && (
                                                    <span className="text-xs text-muted-foreground">{expense.vendor}</span>
                                                )}
                                            </div>
                                        </div>
                                    </CardContent>

                                    <CardFooter className="flex justify-end gap-1 pt-0 pb-2">
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => handleAction('view', expense)}
                                                    className="text-blue-500 hover:text-blue-700 h-8 w-8"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>View</TooltipContent>
                                        </Tooltip>
                                        {expensePermissions?.update && (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => handleAction('edit', expense)}
                                                        className="text-amber-500 hover:text-amber-700 h-8 w-8"
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Edit</TooltipContent>
                                            </Tooltip>
                                        )}
                                        {expensePermissions?.create && (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => handleAction('duplicate', expense)}
                                                        className="text-green-500 hover:text-green-700 h-8 w-8"
                                                    >
                                                        <Copy className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Duplicate</TooltipContent>
                                            </Tooltip>
                                        )}
                                        {expensePermissions?.delete && (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-red-500 hover:text-red-700 h-8 w-8"
                                                        onClick={() => handleAction('delete', expense)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Delete</TooltipContent>
                                            </Tooltip>
                                        )}
                                    </CardFooter>
                                </Card>
                            ))}
                        </div>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expense</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project & Category</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitter</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {expenses?.data?.map((expense: Expense) => (
                                    <tr key={expense.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">{expense.title}</div>
                                            {expense.description && (
                                                <div className="text-sm text-gray-500 truncate max-w-xs">{expense.description}</div>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">
                                                {formatCurrency(expense.amount)}
                                            </div>
                                            {expense.vendor && (
                                                <div className="text-sm text-gray-500">{expense.vendor}</div>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">{expense.project.title}</div>
                                            {expense.budget_category ? (
                                                <div className="flex items-center gap-1 mt-1">
                                                    <div
                                                        className="w-2 h-2 rounded-full"
                                                        style={{ backgroundColor: expense.budget_category.color }}
                                                    />
                                                    <span className="text-xs text-gray-500">{expense.budget_category.name}</span>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-gray-400 mt-1 block">Uncategorized</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-2">
                                                {getStatusIcon(expense.status)}
                                                <Badge className={getStatusColor(expense.status)} variant="secondary">
                                                    {expense.status.replace('_', ' ')}
                                                </Badge>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-2">
                                                <Avatar className="h-6 w-6">
                                                    <AvatarImage src={expense.submitter.avatar} />
                                                    <AvatarFallback className="text-xs">
                                                        {expense.submitter.name?.charAt(0)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <span className="text-sm">{expense.submitter.name}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {new Date(expense.expense_date).toLocaleDateString()}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div className="flex gap-1">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => handleAction('view', expense)}
                                                            className="text-blue-500 hover:text-blue-700 h-8 w-8"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>View</TooltipContent>
                                                </Tooltip>
                                                {expensePermissions?.update && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() => handleAction('edit', expense)}
                                                                className="text-amber-500 hover:text-amber-700 h-8 w-8"
                                                            >
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>Edit</TooltipContent>
                                                    </Tooltip>
                                                )}
                                                {expensePermissions?.create && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() => handleAction('duplicate', expense)}
                                                                className="text-green-500 hover:text-green-700 h-8 w-8"
                                                            >
                                                                <Copy className="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>Duplicate</TooltipContent>
                                                    </Tooltip>
                                                )}
                                                {expensePermissions?.delete && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="text-red-500 hover:text-red-700 h-8 w-8"
                                                                onClick={() => handleAction('delete', expense)}
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>Delete</TooltipContent>
                                                    </Tooltip>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Pagination */}
            {expenses?.links && expenses.data?.length > 0 && (
                <div className="mt-6 bg-white p-4 rounded-lg shadow flex items-center justify-between">
                    <div className="text-sm text-muted-foreground">
                        Showing <span className="font-medium">{expenses?.from || 0}</span> to <span className="font-medium">{expenses?.to || 0}</span> of <span className="font-medium">{expenses?.total || 0}</span> expenses
                    </div>

                    <div className="flex gap-1">
                        {expenses?.links?.map((link: any, i: number) => {
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
            )}

            {expenses?.data?.length === 0 && (
                <div className="bg-white rounded-lg shadow p-8 text-center">
                    <Receipt className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                    <p className="text-gray-500 mb-4">{t('No expenses found')}</p>
                    {expensePermissions?.create && (
                        <Button onClick={handleAddNew}>
                            <Plus className="h-4 w-4 mr-2" />
                            {t('Add your first expense')}
                        </Button>
                    )}
                </div>
            )}


            <ExpenseFormModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                expense={currentExpense}
                projects={projects}
                mode={modalMode}
                redirectUrl={route('expenses.index')}
            />

            <EnhancedDeleteModal
                isOpen={!!deleteExpense}
                onClose={() => setDeleteExpense(null)}
                onConfirm={() => {
                    if (deleteExpense) {
                        toast.loading('Deleting expense...');
                        router.delete(route('expenses.destroy', deleteExpense.id), {
                            onSuccess: () => {
                                toast.dismiss();
                                setDeleteExpense(null);
                            },
                            onError: () => {
                                toast.dismiss();
                                toast.error('Failed to delete expense');
                                setDeleteExpense(null);
                            }
                        });
                    }
                }}
                itemName={deleteExpense?.title || ''}
                entityName="Expense"
                additionalInfo={[
                    `Amount: ${deleteExpense ? formatCurrency(deleteExpense.amount) : ''}`,
                    `Project: ${deleteExpense?.project.title || ''}`,
                    `Date: ${deleteExpense ? new Date(deleteExpense.expense_date).toLocaleDateString() : ''}`
                ]}
            />

        </PageTemplate>
    );
}