import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Plus, Trash2 } from 'lucide-react';
import { formatCurrency } from '@/utils/currency';

interface InvoiceItem {
    type: 'task';
    description: string;
    rate: number;
    amount: number;
    task_id: number | null;
}

interface Props {
    invoice?: any;
    projects: any[];
    clients: any[];
    currencies: any[];
}

export default function InvoiceForm({ invoice, projects, clients, currencies }: Props) {
    const { t } = useTranslation();
    const isEdit = !!invoice;
    
    const [formData, setFormData] = useState({
        project_id: invoice?.project_id?.toString() || '',
        client_id: invoice?.client_id?.toString() || '',
        title: invoice?.title || '',
        description: invoice?.description || '',
        invoice_date: invoice?.invoice_date ? new Date(invoice.invoice_date).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
        due_date: invoice?.due_date ? new Date(invoice.due_date).toISOString().split('T')[0] : '',
        tax_rate: invoice?.tax_rate || 0,
        discount_amount: invoice?.discount_amount || 0,
        currency: invoice?.currency || 'USD',
        notes: invoice?.notes || '',
        terms: invoice?.terms || '',
    });

    const [items, setItems] = useState<InvoiceItem[]>(
        invoice?.items?.map((item: any) => ({
            type: 'task',
            description: item.description || '',
            rate: item.rate || 0,
            amount: item.amount || 0,
            task_id: item.task_id,
        })) || [{
            type: 'task',
            description: '',
            rate: 0,
            amount: 0,
            task_id: null
        }]
    );

    const [isSubmitting, setIsSubmitting] = useState(false);
    const [projectTasks, setProjectTasks] = useState([]);
    const [projectClients, setProjectClients] = useState([]);
    const [availableClients, setAvailableClients] = useState([]);

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Invoices'), href: route('invoices.index') },
        { title: isEdit ? `${t('Edit')} ${invoice.invoice_number}` : t('Create Invoice') }
    ];

    useEffect(() => {
        if (formData.project_id) {
            loadProjectData(formData.project_id);
        }
        // Set initial available clients
        setAvailableClients(clients || []);
    }, []);

    const handleInputChange = (field: string, value: any) => {
        setFormData(prev => ({
            ...prev,
            [field]: value
        }));
        
        if (field === 'project_id' && value) {
            loadProjectData(value);
        } else if (field === 'project_id' && !value) {
            setProjectTasks([]);
            setProjectClients([]);
            setAvailableClients(clients || []);
        }
    };
    
    const loadProjectData = async (projectId: string) => {
        try {
            const response = await fetch(route('api.projects.invoice-data', projectId), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            setProjectTasks(data.tasks || []);
            setProjectClients(data.clients || []);
            
            // Merge project clients with all clients, ensuring current client is included
            const mergedClients = [...(data.clients || [])];
            const allClientIds = mergedClients.map(c => c.id);
            
            // Add clients that aren't in project clients but are in workspace
            clients.forEach(client => {
                if (!allClientIds.includes(client.id)) {
                    mergedClients.push(client);
                }
            });
            
            setAvailableClients(mergedClients);
        } catch (error) {
            console.error('Failed to load project data:', error);
            setProjectTasks([]);
            setProjectClients([]);
            setAvailableClients(clients || []);
        }
    };

    const handleItemChange = (index: number, field: string, value: any) => {
        const updatedItems = [...items];
        updatedItems[index] = {
            ...updatedItems[index],
            [field]: value
        };
        setItems(updatedItems);
    };

    const addItem = () => {
        setItems([...items, {
            type: 'task',
            description: '',
            rate: 0,
            amount: 0,
            task_id: null
        }]);
    };

    const removeItem = (index: number) => {
        if (items.length > 1) {
            setItems(items.filter((_, i) => i !== index));
        }
    };

    const calculateSubtotal = () => {
        return items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
    };

    const calculateTax = () => {
        return (calculateSubtotal() * (Number(formData.tax_rate) || 0)) / 100;
    };

    const calculateTotal = () => {
        return calculateSubtotal() + calculateTax() - (Number(formData.discount_amount) || 0);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        const submitData = {
            ...formData,
            client_id: formData.client_id === 'none' ? null : formData.client_id,
            items: items.filter(item => item.task_id !== null && item.task_id !== 'no-tasks')
        };

        if (isEdit) {
            router.put(route('invoices.update', invoice.id), submitData, {
                onFinish: () => setIsSubmitting(false)
            });
        } else {
            router.post(route('invoices.store'), submitData, {
                onFinish: () => setIsSubmitting(false)
            });
        }
    };

    return (
        <PageTemplate 
            title={isEdit ? `${t('Edit Invoice')} ${invoice.invoice_number}` : t('Create Invoice')}
            url={isEdit ? `/invoices/${invoice.id}/edit` : "/invoices/create"}
            breadcrumbs={breadcrumbs}
        >
            <form onSubmit={handleSubmit} className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="project_id">{t('Project')} *</Label>
                        <Select 
                            value={formData.project_id} 
                            onValueChange={(value) => handleInputChange('project_id', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('Select project')} />
                            </SelectTrigger>
                            <SelectContent className="z-[9999]">
                                {projects?.map((project: any) => (
                                    <SelectItem key={project.id} value={project.id.toString()}>
                                        {project.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="client_id">{t('Client')}</Label>
                        <Select 
                            value={formData.client_id} 
                            onValueChange={(value) => handleInputChange('client_id', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('Select client (optional)')} />
                            </SelectTrigger>
                            <SelectContent className="z-[9999]">
                                <SelectItem value="none">{t('No client')}</SelectItem>
                                {availableClients?.map((client: any) => (
                                    <SelectItem key={client.id} value={client.id.toString()}>
                                        {client.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="title">{t('Invoice Title')} *</Label>
                        <Input
                            id="title"
                            value={formData.title}
                            onChange={(e) => handleInputChange('title', e.target.value)}
                            placeholder={t('Enter invoice title')}
                            required
                        />
                    </div>



                    <div className="space-y-2">
                        <Label htmlFor="invoice_date">{t('Invoice Date')} *</Label>
                        <Input
                            id="invoice_date"
                            type="date"
                            value={formData.invoice_date}
                            onChange={(e) => handleInputChange('invoice_date', e.target.value)}
                            required
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="due_date">{t('Due Date')} *</Label>
                        <Input
                            id="due_date"
                            type="date"
                            value={formData.due_date}
                            onChange={(e) => handleInputChange('due_date', e.target.value)}
                            required
                        />
                    </div>
                </div>

                <div className="space-y-2">
                    <Label htmlFor="description">{t('Description')}</Label>
                    <Textarea
                        id="description"
                        value={formData.description}
                        onChange={(e) => handleInputChange('description', e.target.value)}
                        placeholder={t('Enter invoice description')}
                        rows={3}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex justify-between items-center">
                            <CardTitle>{t('Invoice Items')}</CardTitle>
                            <Button type="button" onClick={addItem} size="sm">
                                <Plus className="h-4 w-4 mr-2" />
                                {t('Add Item')}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {items.map((item, index) => (
                                <div key={index} className="grid grid-cols-12 gap-2 items-end">
                                    <div className="col-span-8">
                                        <Label>{t('Task')}</Label>
                                        <Select 
                                            value={item.task_id?.toString() || ''} 
                                            onValueChange={(value) => handleItemChange(index, 'task_id', value ? parseInt(value) : null)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder={t('Select task')} />
                                            </SelectTrigger>
                                            <SelectContent className="z-[9999]">
                                                {projectTasks.map((task: any) => (
                                                    <SelectItem key={task.id} value={task.id.toString()}>
                                                        {task.title}
                                                    </SelectItem>
                                                ))}
                                                {projectTasks.length === 0 && (
                                                    <SelectItem value="no-tasks" disabled>
                                                        {formData.project_id ? t('No tasks found') : t('Select project first')}
                                                    </SelectItem>
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="col-span-2">
                                        <Label>{t('Amount')}</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={item.amount}
                                            onChange={(e) => handleItemChange(index, 'amount', parseFloat(e.target.value) || 0)}
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={() => removeItem(index)}
                                            disabled={items.length === 1}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Totals')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="tax_rate">{t('Tax Rate')} (%)</Label>
                                <Input
                                    id="tax_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    value={formData.tax_rate}
                                    onChange={(e) => handleInputChange('tax_rate', parseFloat(e.target.value) || 0)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="discount_amount">{t('Discount Amount')}</Label>
                                <Input
                                    id="discount_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={formData.discount_amount}
                                    onChange={(e) => handleInputChange('discount_amount', parseFloat(e.target.value) || 0)}
                                />
                            </div>
                        </div>
                        
                        <div className="mt-4 space-y-2 text-right">
                            <div className="flex justify-between">
                                <span>{t('Subtotal')}:</span>
                                <span>{formatCurrency(calculateSubtotal())}</span>
                            </div>
                            {formData.tax_rate > 0 && (
                                <div className="flex justify-between">
                                    <span>{t('Tax')} ({formData.tax_rate}%):</span>
                                    <span>{formatCurrency(calculateTax())}</span>
                                </div>
                            )}
                            {formData.discount_amount > 0 && (
                                <div className="flex justify-between text-green-600">
                                    <span>{t('Discount')}:</span>
                                    <span>-{formatCurrency(Number(formData.discount_amount))}</span>
                                </div>
                            )}
                            <div className="flex justify-between font-bold text-lg border-t pt-2">
                                <span>{t('Total')}:</span>
                                <span>{formatCurrency(calculateTotal())}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="notes">{t('Notes')}</Label>
                        <Textarea
                            id="notes"
                            value={formData.notes}
                            onChange={(e) => handleInputChange('notes', e.target.value)}
                            placeholder={t('Internal notes')}
                            rows={3}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="terms">{t('Terms & Conditions')}</Label>
                        <Textarea
                            id="terms"
                            value={formData.terms}
                            onChange={(e) => handleInputChange('terms', e.target.value)}
                            placeholder={t('Payment terms and conditions')}
                            rows={3}
                        />
                    </div>
                </div>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={() => router.visit(route('invoices.index'))}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={isSubmitting}>
                        {isSubmitting ? (isEdit ? t('Updating...') : t('Creating...')) : (isEdit ? t('Update Invoice') : t('Create Invoice'))}
                    </Button>
                </div>
            </form>
        </PageTemplate>
    );
}