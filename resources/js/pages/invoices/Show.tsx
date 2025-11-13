import React, { useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Edit, DollarSign, Download, ArrowLeft, Calendar, User, Building, FileText, Clock, CreditCard, Send } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { formatCurrency } from '@/utils/currency';
import { InvoicePaymentModal } from '@/components/invoices/invoice-payment-modal';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface InvoiceItem {
    id: number;
    type: string;
    description: string;
    rate: number;
    amount: number;
    task?: {
        id: number;
        title: string;
    };
    expense?: {
        id: number;
        title: string;
    };
}

interface Invoice {
    id: number;
    invoice_number: string;
    project: {
        id: number;
        title: string;
    };
    client?: {
        id: number;
        name: string;
        avatar?: string;
    };
    creator: {
        id: number;
        name: string;
    };
    title: string;
    description?: string;
    invoice_date: string;
    due_date: string;
    subtotal: number;
    tax_rate: number;
    tax_amount: number;
    discount_amount: number;
    total_amount: number;
    paid_amount: number;
    payment_method?: string;
    payment_reference?: string;
    payment_details?: any;
    status: string;
    is_overdue: boolean;
    days_overdue: number;
    balance_due: number;
    notes?: string;
    terms?: string;
    items: InvoiceItem[];
    created_at: string;
}

export default function InvoiceShow() {
    const { t } = useTranslation();
    const { invoice, userWorkspaceRole, flash } = usePage().props as { invoice: Invoice; userWorkspaceRole: string; flash?: any };
    const [showPaymentModal, setShowPaymentModal] = React.useState(false);
    const [showMarkPaidModal, setShowMarkPaidModal] = React.useState(false);

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const getStatusColor = (status: string) => {
        const colors = {
            draft: 'bg-gray-100 text-gray-800',
            sent: 'bg-blue-100 text-blue-800',
            viewed: 'bg-yellow-100 text-yellow-800',
            paid: 'bg-green-100 text-green-800',
            overdue: 'bg-red-100 text-red-800',
            cancelled: 'bg-gray-100 text-gray-800'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };



    const handleAction = (action: string) => {
        switch (action) {
            case 'edit':
                router.get(route('invoices.edit', invoice.id));
                break;

            case 'mark-paid':
                setShowMarkPaidModal(true);
                break;

            case 'send':
                toast.loading('Sending invoice...');
                router.post(route('invoices.send', invoice.id), {}, {
                    onSuccess: () => {
                        toast.dismiss();
                    },
                    onError: () => {
                        toast.dismiss();
                        toast.error('Failed to send invoice');
                    }
                });
                break;

            case 'pay':
                setShowPaymentModal(true);
                break;
        }
    };

    const handlePaymentSuccess = () => {
        router.reload();
    };

    const handleMarkPaidConfirm = () => {
        toast.loading('Marking invoice as paid...');
        router.post(route('invoices.mark-paid', invoice.id), {}, {
            onSuccess: () => {
                toast.dismiss();
                setShowMarkPaidModal(false);
            },
            onError: () => {
                toast.dismiss();
                toast.error('Failed to mark invoice as paid');
                setShowMarkPaidModal(false);
            }
        });
    };

    const pageActions = [];
    
    if (invoice.status === 'draft' && ['owner', 'manager'].includes(userWorkspaceRole)) {
        pageActions.push(
            {
                label: t('Edit'),
                icon: <Edit className="h-4 w-4 mr-2" />,
                variant: 'outline',
                onClick: () => handleAction('edit')
            },
            {
                label: t('Send'),
                icon: <Send className="h-4 w-4 mr-2" />,
                variant: 'default',
                onClick: () => handleAction('send')
            }
        );
    }

    if (['pending', 'sent', 'viewed', 'overdue'].includes(invoice.status)) {
        // Show Pay button for workspace clients
        if (userWorkspaceRole === 'client') {
            pageActions.push({
                label: t('Pay Now'),
                icon: <CreditCard className="h-4 w-4 mr-2" />,
                variant: 'default',
                onClick: () => handleAction('pay')
            });
        } else {
            // Show Mark as Paid for non-clients (admin, manager, member)
            pageActions.push({
                label: t('Mark as Paid'),
                icon: <DollarSign className="h-4 w-4 mr-2" />,
                variant: 'default',
                onClick: () => handleAction('mark-paid')
            });
        }
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Invoices'), href: route('invoices.index') },
        { title: invoice.invoice_number }
    ];

    return (
        <PageTemplate 
            title={`${t('Invoice')} ${invoice.invoice_number}`}
            url={`/invoices/${invoice.id}`}
            actions={pageActions}
            breadcrumbs={breadcrumbs}
        >
            <div className="max-w-4xl mx-auto space-y-6">
                {/* Invoice Header */}
                <Card>
                    <CardHeader>
                        <div className="flex justify-between items-start">
                            <div>
                                <CardTitle className="text-2xl">{invoice.invoice_number}</CardTitle>
                                <p className="text-muted-foreground mt-1">{invoice.title}</p>
                            </div>
                            <div className="text-right">
                                <Badge className={getStatusColor(invoice.status)} variant="secondary">
                                    {invoice.status.toUpperCase()}
                                </Badge>
                                {invoice.is_overdue && (
                                    <p className="text-red-600 text-sm mt-1">
                                        {invoice.days_overdue} {t('days overdue')}
                                    </p>
                                )}
                            </div>
                        </div>
                    </CardHeader>
                    
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            {/* Invoice Details */}
                            <div className="space-y-4">
                                <div>
                                    <h2 className="font-semibold text-sm text-gray-600 uppercase tracking-wide">{t('Invoice Details')}</h2>
                                    <div className="mt-2 space-y-2">
                                        <div className="flex items-center gap-2">
                                            <Calendar className="h-4 w-4 text-gray-400" />
                                            <span className="text-sm">
                                                <span className="text-gray-600">{t('Date')}:</span> {new Date(invoice.invoice_date).toLocaleDateString()}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Clock className="h-4 w-4 text-gray-400" />
                                            <span className="text-sm">
                                                <span className="text-gray-600">{t('Due')}:</span> {new Date(invoice.due_date).toLocaleDateString()}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Building className="h-4 w-4 text-gray-400" />
                                            <span className="text-sm">
                                                <span className="text-gray-600">{t('Project')}:</span> {invoice.project.title}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Client Information */}
                            {invoice.client && (
                                <div className="space-y-4">
                                    <div>
                                        <h2 className="font-semibold text-sm text-gray-600 uppercase tracking-wide">{t('Bill To')}</h2>
                                        <div className="mt-2">
                                            <div className="flex items-center gap-2">
                                                <Avatar className="h-8 w-8">
                                                    <AvatarImage src={invoice.client.avatar} />
                                                    <AvatarFallback>
                                                        {invoice.client.name?.charAt(0)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <span className="font-medium">{invoice.client.name}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Amount Summary */}
                            <div className="space-y-4">
                                <div>
                                    <h2 className="font-semibold text-sm text-gray-600 uppercase tracking-wide">{t('Amount')}</h2>
                                    <div className="mt-2">
                                        <div className="text-2xl font-bold">
                                            {formatCurrency(invoice.total_amount)}
                                        </div>
                                        {invoice.paid_amount > 0 && (
                                            <div className="text-sm text-gray-600">
                                                {t('Paid')}: {formatCurrency(invoice.paid_amount)}
                                                {invoice.payment_method && (
                                                    <div className="text-xs text-gray-500 mt-1">
                                                        via {invoice.payment_method.charAt(0).toUpperCase() + invoice.payment_method.slice(1)}
                                                        {invoice.payment_reference && (
                                                            <span className="ml-1">({invoice.payment_reference})</span>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                        {invoice.balance_due > 0 && (
                                            <div className="text-sm font-medium text-red-600">
                                                {t('Balance Due')}: {formatCurrency(invoice.balance_due)}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {invoice.description && (
                            <div className="mt-6">
                                <h2 className="font-semibold text-sm text-gray-600 uppercase tracking-wide mb-2">{t('Description')}</h2>
                                <p className="text-gray-700">{invoice.description}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Invoice Items */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('Invoice Items')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left py-2 font-medium text-gray-600">{t('Description')}</th>
                                        <th className="text-right py-2 font-medium text-gray-600">{t('Rate')}</th>
                                        <th className="text-right py-2 font-medium text-gray-600">{t('Amount')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {invoice.items.map((item) => (
                                        <tr key={item.id} className="border-b">
                                            <td className="py-3">
                                                <div>
                                                    <div className="font-medium">{item.description}</div>
                                                    {item.task && (
                                                        <div className="text-sm text-gray-500">
                                                            {t('Task')}: {item.task.title}
                                                        </div>
                                                    )}
                                                    {item.expense && (
                                                        <div className="text-sm text-gray-500">
                                                            {t('Expense')}: {item.expense.title}
                                                        </div>
                                                    )}
                                                    <Badge variant="outline" className="text-xs mt-1">
                                                        {item.type}
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td className="text-right py-3">
                                                {formatCurrency(item.rate)}
                                            </td>
                                            <td className="text-right py-3 font-medium">
                                                {formatCurrency(item.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <Separator className="my-4" />

                        {/* Totals */}
                        <div className="flex justify-end">
                            <div className="w-64 space-y-2">
                                <div className="flex justify-between">
                                    <span>{t('Subtotal')}:</span>
                                    <span>{formatCurrency(invoice.subtotal)}</span>
                                </div>
                                
                                {invoice.discount_amount > 0 && (
                                    <div className="flex justify-between text-green-600">
                                        <span>{t('Discount')}:</span>
                                        <span>-{formatCurrency(invoice.discount_amount)}</span>
                                    </div>
                                )}
                                
                                {invoice.tax_rate > 0 && (
                                    <div className="flex justify-between">
                                        <span>{t('Tax')} ({invoice.tax_rate}%):</span>
                                        <span>{formatCurrency(invoice.tax_amount)}</span>
                                    </div>
                                )}
                                
                                <Separator />
                                
                                <div className="flex justify-between font-bold text-lg">
                                    <span>{t('Total')}:</span>
                                    <span>{formatCurrency(invoice.total_amount)}</span>
                                </div>
                                
                                {invoice.paid_amount > 0 && (
                                    <>
                                        <div className="flex justify-between text-green-600">
                                            <span>{t('Paid')}:</span>
                                            <span>{formatCurrency(invoice.paid_amount)}</span>
                                        </div>
                                        
                                        <div className="flex justify-between font-bold text-red-600">
                                            <span>{t('Balance Due')}:</span>
                                            <span>{formatCurrency(invoice.balance_due)}</span>
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Notes and Terms */}
                {(invoice.notes || invoice.terms) && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {invoice.notes && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">{t('Notes')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-gray-700 whitespace-pre-wrap">{invoice.notes}</p>
                                </CardContent>
                            </Card>
                        )}

                        {invoice.terms && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">{t('Terms & Conditions')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-gray-700 whitespace-pre-wrap">{invoice.terms}</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Payment Information */}
                {invoice.payment_method && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">{t('Payment Information')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <h2 className="font-medium text-sm text-gray-600 uppercase tracking-wide mb-2">{t('Payment Method')}</h2>
                                    <p className="capitalize">{invoice.payment_method}</p>
                                </div>
                                {invoice.payment_reference && (
                                    <div>
                                        <h2 className="font-medium text-sm text-gray-600 uppercase tracking-wide mb-2">{t('Reference')}</h2>
                                        <p className="font-mono text-sm">{invoice.payment_reference}</p>
                                    </div>
                                )}
                                {invoice.payment_details?.status && (
                                    <div>
                                        <h2 className="font-medium text-sm text-gray-600 uppercase tracking-wide mb-2">{t('Status')}</h2>
                                        <Badge variant={invoice.payment_details.status === 'completed' ? 'default' : 'secondary'}>
                                            {invoice.payment_details.status}
                                        </Badge>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Invoice Meta */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center justify-between text-sm text-gray-500">
                            <div className="flex items-center gap-2">
                                <User className="h-4 w-4" />
                                <span>{t('Created by')} {invoice.creator.name}</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <FileText className="h-4 w-4" />
                                <span>{t('Created on')} {new Date(invoice.created_at).toLocaleDateString()}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Payment Modal */}
                <InvoicePaymentModal
                    invoice={invoice}
                    open={showPaymentModal}
                    onClose={() => setShowPaymentModal(false)}
                    onSuccess={handlePaymentSuccess}
                />

                {/* Mark as Paid Confirmation Modal */}
                <Dialog open={showMarkPaidModal} onOpenChange={setShowMarkPaidModal}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('Mark Invoice as Paid')}</DialogTitle>
                        </DialogHeader>
                        <p>{t('Are you sure you want to mark invoice')} {invoice.invoice_number} {t('as paid')}?</p>
                        <div className="flex justify-end gap-2 mt-4">
                            <Button variant="outline" onClick={() => setShowMarkPaidModal(false)}>
                                {t('Cancel')}
                            </Button>
                            <Button onClick={handleMarkPaidConfirm}>
                                {t('Mark as Paid')}
                            </Button>
                        </div>
                    </DialogContent>
                </Dialog>
            </div>
        </PageTemplate>
    );
}