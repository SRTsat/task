import React, { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Plus, Search, Filter, Eye, Edit, Trash2, LayoutGrid, List, DollarSign, AlertTriangle } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';

import BudgetFormModal from '@/components/budgets/BudgetFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { useTranslation } from 'react-i18next';

interface Budget {
    id: number;
    project: {
        id: number;
        title: string;
    };
    total_budget: number;
    currency: string;
    period_type: string;
    status: string;
    total_spent: number;
    remaining_budget: number;
    utilization_percentage: number;
    categories: Array<{
        id: number;
        name: string;
        allocated_amount: number;
        color: string;
        total_spent: number;
        utilization_percentage: number;
    }>;
    created_at: string;
}

export default function BudgetIndex() {
    const { t } = useTranslation();
    const { budgets, auth, userWorkspaceRole, flash, permissions: pagePermissions } = usePage().props as any;
    const budgetPermissions = pagePermissions;

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
        if (flash?.info) {
            toast.info(flash.info);
        }
    }, [flash]);

    const [activeView, setActiveView] = useState('grid');
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedStatus, setSelectedStatus] = useState('all');
    const [showFilters, setShowFilters] = useState(false);
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [currentBudget, setCurrentBudget] = useState<Budget | null>(null);
    const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const applyFilters = () => {
        const params: any = { page: 1 };
        if (searchTerm) params.search = searchTerm;
        if (selectedStatus !== 'all') params.status = selectedStatus;
        router.get(route('budgets.index'), params, { preserveState: true, preserveScroll: true });
    };

    const handleAction = (action: string, budget: Budget) => {
        setCurrentBudget(budget);
        switch (action) {
            case 'view':
                router.get(route('budgets.show', budget.id));
                break;
            case 'edit':
                setModalMode('edit');
                setIsFormModalOpen(true);
                break;
            case 'delete':
                setIsDeleteModalOpen(true);
                break;
        }
    };

    const handleAddNew = () => {
        setCurrentBudget(null);
        setModalMode('create');
        setIsFormModalOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (currentBudget) {
            toast.loading('Deleting budget...');
            router.delete(route('budgets.destroy', currentBudget.id), {
                onSuccess: () => {
                    toast.dismiss();
                    setIsDeleteModalOpen(false);
                },
                onError: () => {
                    toast.dismiss();
                    toast.error('Failed to delete budget');
                    setIsDeleteModalOpen(false);
                }
            });
        }
    };

    const getStatusColor = (status: string) => {
        const colors = {
            active: 'bg-green-100 text-green-800',
            completed: 'bg-blue-100 text-blue-800',
            cancelled: 'bg-red-100 text-red-800'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };

    const getUtilizationColor = (percentage: number) => {
        if (percentage >= 90) return 'text-red-600';
        if (percentage >= 75) return 'text-yellow-600';
        return 'text-green-600';
    };

    const formatCurrency = (amount: string | number) => {
        if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
            const numericAmount = typeof amount === 'number' ? amount : parseFloat(amount) || 0;
            return window.appSettings.formatCurrency(numericAmount);
        }
        return amount || 0;
    };

    const pageActions = [];

    if (budgetPermissions?.create) {
        pageActions.push({
            label: t('Create Budget'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default',
            onClick: handleAddNew
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') }, 
        { title: t('Budget & Expenses') },
        { title: t('Budgets') }
    ];

    return (
        <PageTemplate
            title={t('Budget Management')}
            url="/budgets"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Search and filters */}
            <div className="bg-white rounded-lg shadow mb-4">
                <div className="p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative w-64">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder={t('Search budgets...')}
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
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
                                <Filter className="h-3.5 w-3.5 mr-1.5" />
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

                            <Label className="text-xs text-muted-foreground">{t('Per Page:')}</Label>
                            <Select
                                value={budgets?.per_page?.toString() || "12"}
                                onValueChange={(value) => {
                                    const params: any = { page: 1, per_page: parseInt(value) };
                                    if (searchTerm) params.search = searchTerm;
                                    if (selectedStatus !== 'all') params.status = selectedStatus;
                                    router.get(route('budgets.index'), params, { preserveState: true, preserveScroll: true });
                                }}
                            >
                                <SelectTrigger className="w-16 h-8">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="12">12</SelectItem>
                                    <SelectItem value="24">24</SelectItem>
                                    <SelectItem value="36">36</SelectItem>
                                    <SelectItem value="48">48</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {showFilters && (
                        <div className="w-full mt-3 p-4 bg-gray-50 border rounded-md">
                            <div className="flex flex-wrap gap-4 items-end">
                                <div className="space-y-2">
                                    <Label>Status</Label>
                                    <Select value={selectedStatus} onValueChange={setSelectedStatus}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder="All Status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Status</SelectItem>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="completed">Completed</SelectItem>
                                            <SelectItem value="cancelled">Cancelled</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <Button variant="outline" size="sm" onClick={() => {
                                    setSelectedStatus('all');
                                    setSearchTerm('');
                                    setShowFilters(false);
                                }}>
                                    Reset Filters
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Budget Content */}
            {activeView === 'grid' ? (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    {budgets?.data?.map((budget: Budget) => (
                        <Card key={budget.id} className="overflow-hidden hover:shadow-md transition-shadow">
                            <CardHeader className="pb-2">
                                <div className="flex justify-between items-start">
                                    <CardTitle
                                        className="text-base line-clamp-1 cursor-pointer hover:text-blue-600 transition-colors"
                                        onClick={() => handleAction('view', budget)}
                                    >
                                        {budget.project.title}
                                    </CardTitle>
                                    <Badge className={getStatusColor(budget.status)} variant="secondary">
                                        {budget.status}
                                    </Badge>
                                </div>
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <span>{formatCurrency(budget.total_budget)}</span>
                                    <span>•</span>
                                    <span className="capitalize">{budget.period_type}</span>
                                </div>
                            </CardHeader>

                            <CardContent className="py-2">
                                <div className="space-y-3">
                                    <div className="space-y-1">
                                        <div className="flex justify-between text-xs">
                                            <span>Budget Utilization</span>
                                            <span className={getUtilizationColor(budget.utilization_percentage || 0)}>
                                                {(budget.utilization_percentage || 0).toFixed(1)}%
                                            </span>
                                        </div>
                                        <Progress value={budget.utilization_percentage} className="h-2" />
                                    </div>

                                    <div className="grid grid-cols-2 gap-2 text-xs">
                                        <div>
                                            <span className="text-muted-foreground">Spent:</span>
                                            <div className="font-medium">{formatCurrency(budget.total_spent)}</div>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">Remaining:</span>
                                            <div className="font-medium">{formatCurrency(budget.remaining_budget)}</div>
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <div className="flex -space-x-1">
                                            {budget.categories?.slice(0, 3).map((category, index) => (
                                                <Tooltip key={index}>
                                                    <TooltipTrigger asChild>
                                                        <div
                                                            className="w-6 h-6 rounded-full border-2 border-white flex items-center justify-center text-xs font-medium text-white"
                                                            style={{ backgroundColor: category.color }}
                                                        >
                                                            {category.name.charAt(0)}
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        {category.name}: {formatCurrency(category.allocated_amount)}
                                                    </TooltipContent>
                                                </Tooltip>
                                            ))}
                                            {budget.categories?.length > 3 && (
                                                <div className="w-6 h-6 rounded-full bg-gray-200 border-2 border-white flex items-center justify-center text-xs">
                                                    +{budget.categories.length - 3}
                                                </div>
                                            )}
                                        </div>

                                        {(budget.utilization_percentage || 0) >= 90 && (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <AlertTriangle className="h-4 w-4 text-red-500" />
                                                </TooltipTrigger>
                                                <TooltipContent>Budget nearly exhausted</TooltipContent>
                                            </Tooltip>
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
                                            onClick={() => handleAction('view', budget)}
                                            className="text-blue-500 hover:text-blue-700 h-8 w-8"
                                        >
                                            <Eye className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>View</TooltipContent>
                                </Tooltip>
                                {budgetPermissions?.update && (
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleAction('edit', budget)}
                                                className="text-amber-500 hover:text-amber-700 h-8 w-8"
                                            >
                                                <Edit className="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>Edit</TooltipContent>
                                    </Tooltip>
                                )}
                                {budgetPermissions?.delete && (
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-red-500 hover:text-red-700 h-8 w-8"
                                                onClick={() => handleAction('delete', budget)}
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
            ) : (
                <div className="bg-white rounded-lg shadow">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utilization</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categories</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {budgets?.data?.map((budget: Budget) => (
                                    <tr key={budget.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div
                                                className="text-sm font-medium text-gray-900 cursor-pointer hover:text-blue-600 transition-colors"
                                                onClick={() => handleAction('view', budget)}
                                            >
                                                {budget.project.title}
                                            </div>
                                            <div className="text-sm text-gray-500 capitalize">{budget.period_type} budget</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">
                                                {formatCurrency(budget.total_budget)}
                                            </div>
                                            <div className="text-sm text-gray-500">
                                                Spent: {formatCurrency(budget.total_spent)}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <Badge className={getStatusColor(budget.status)} variant="secondary">
                                                {budget.status}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center">
                                                <div className="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                    <div
                                                        className="bg-blue-600 h-2 rounded-full"
                                                        style={{width: `${Math.min(budget.utilization_percentage || 0, 100)}%`}}
                                                    />
                                                </div>
                                                <span className={`text-sm ${getUtilizationColor(budget.utilization_percentage || 0)}`}>
                                                    {(budget.utilization_percentage || 0).toFixed(1)}%
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex -space-x-1">
                                                {budget.categories?.slice(0, 4).map((category, index) => (
                                                    <Tooltip key={index}>
                                                        <TooltipTrigger asChild>
                                                            <div
                                                                className="w-6 h-6 rounded-full border-2 border-white flex items-center justify-center text-xs font-medium text-white"
                                                                style={{ backgroundColor: category.color }}
                                                            >
                                                                {category.name.charAt(0)}
                                                            </div>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {category.name}: {formatCurrency(category.allocated_amount)}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                ))}
                                                {budget.categories?.length > 4 && (
                                                    <div className="w-6 h-6 rounded-full bg-gray-200 border-2 border-white flex items-center justify-center text-xs">
                                                        +{budget.categories.length - 4}
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div className="flex gap-1">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => handleAction('view', budget)}
                                                            className="text-blue-500 hover:text-blue-700 h-8 w-8"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>View</TooltipContent>
                                                </Tooltip>
                                                {budgetPermissions?.update && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() => handleAction('edit', budget)}
                                                                className="text-amber-500 hover:text-amber-700 h-8 w-8"
                                                            >
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>Edit</TooltipContent>
                                                    </Tooltip>
                                                )}
                                                {budgetPermissions?.delete && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="text-red-500 hover:text-red-700 h-8 w-8"
                                                                onClick={() => handleAction('delete', budget)}
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

                    {/* Pagination for list view */}
                    {budgets?.links && (
                        <div className="p-4 border-t flex items-center justify-between">
                            <div className="text-sm text-muted-foreground">
                                Showing <span className="font-medium">{budgets?.from || 0}</span> to <span className="font-medium">{budgets?.to || 0}</span> of <span className="font-medium">{budgets?.total || 0}</span> budgets
                            </div>

                            <div className="flex gap-1">
                                {budgets?.links?.map((link: any, i: number) => {
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
                </div>
            )}

            {/* Pagination for grid view */}
            {activeView === 'grid' && budgets?.links && (
                <div className="mt-6 bg-white p-4 rounded-lg shadow flex items-center justify-between">
                    <div className="text-sm text-muted-foreground">
                        Showing <span className="font-medium">{budgets?.from || 0}</span> to <span className="font-medium">{budgets?.to || 0}</span> of <span className="font-medium">{budgets?.total || 0}</span> budgets
                    </div>

                    <div className="flex gap-1">
                        {budgets?.links?.map((link: any, i: number) => {
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

            {budgets?.data?.length === 0 && (
                <div className="bg-white rounded-lg shadow p-8 text-center">
                    <DollarSign className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                    <p className="text-gray-500 mb-4">No budgets found</p>
                    {budgetPermissions?.create && (
                        <Button onClick={handleAddNew}>
                            <Plus className="h-4 w-4 mr-2" />
                            {t('Create your first budget')}
                        </Button>
                    )}
                </div>
            )}

            {/* Modals */}


            <BudgetFormModal
                isOpen={isFormModalOpen}
                onClose={() => {
                    setIsFormModalOpen(false);
                    setCurrentBudget(null);
                }}
                budget={currentBudget}
                mode={modalMode}
            />

            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentBudget?.project?.title || ''}
                entityName="budget"
            />
        </PageTemplate>
    );
}