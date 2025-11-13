import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Loader2, CreditCard, DollarSign } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { InvoiceStripeForm } from '@/components/invoices/invoice-stripe-form';
import { InvoicePayPalForm } from '@/components/invoices/invoice-paypal-form';
import { formatCurrency } from '@/utils/currency';
import axios from 'axios';

interface PaymentMethod {
  id: string;
  name: string;
  enabled: boolean;
  config: any;
}

interface Invoice {
  id: number;
  invoice_number: string;
  total_amount: number;
  balance_due: number;
}

interface InvoicePaymentModalProps {
  invoice: Invoice;
  open: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export function InvoicePaymentModal({ invoice, open, onClose, onSuccess }: InvoicePaymentModalProps) {
  const { t } = useTranslation();
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>([]);
  const [selectedMethod, setSelectedMethod] = useState<string>('');
  const [loading, setLoading] = useState(true);
  const [showPaymentForm, setShowPaymentForm] = useState(false);

  useEffect(() => {
    if (open) {
      fetchPaymentMethods();
    }
  }, [open]);

  const fetchPaymentMethods = async () => {
    try {
      setLoading(true);
      const response = await axios.get(route('invoices.payment-methods', invoice.id));
      setPaymentMethods(response.data);
    } catch (error) {
      toast.error(t('Failed to load payment methods'));
    } finally {
      setLoading(false);
    }
  };

  const handleMethodSelect = (methodId: string) => {
    setSelectedMethod(methodId);
    setShowPaymentForm(true);
  };

  const handlePaymentSuccess = () => {
    toast.success(t('Payment processed successfully'));
    onSuccess();
    onClose();
  };

  const handlePaymentCancel = () => {
    setShowPaymentForm(false);
    setSelectedMethod('');
  };

  const getMethodIcon = (methodId: string) => {
    switch (methodId) {
      case 'stripe':
        return <CreditCard className="h-5 w-5" />;
      case 'paypal':
        return <DollarSign className="h-5 w-5" />;
      default:
        return <CreditCard className="h-5 w-5" />;
    }
  };

  const renderPaymentForm = () => {
    const method = paymentMethods.find(m => m.id === selectedMethod);
    if (!method) return null;

    const commonProps = {
      invoice,
      onSuccess: handlePaymentSuccess,
      onCancel: handlePaymentCancel,
    };

    switch (selectedMethod) {
      case 'stripe':
        return (
          <InvoiceStripeForm
            {...commonProps}
            stripeKey={method.config.public_key}
          />
        );
      case 'paypal':
        return (
          <InvoicePayPalForm
            {...commonProps}
            paypalClientId={method.config.client_id}
          />
        );
      default:
        return null;
    }
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>
            {t('Pay Invoice')} {invoice.invoice_number}
          </DialogTitle>
        </DialogHeader>

        {loading ? (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="h-6 w-6 animate-spin" />
          </div>
        ) : showPaymentForm ? (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="font-medium">{t('Complete Payment')}</h3>
              <Button variant="outline" size="sm" onClick={handlePaymentCancel}>
                {t('Back')}
              </Button>
            </div>
            {renderPaymentForm()}
          </div>
        ) : (
          <div className="space-y-4">
            {/* Payment Summary */}
            <Card>
              <CardContent className="p-4">
                <div className="flex justify-between items-center">
                  <div>
                    <h3 className="font-medium">{invoice.invoice_number}</h3>
                    <p className="text-sm text-muted-foreground">
                      {t('Invoice Payment')}
                    </p>
                  </div>
                  <div className="text-right">
                    <div className="text-lg font-bold">
                      {formatCurrency(invoice.balance_due)}
                    </div>
                    <div className="text-sm text-muted-foreground">
                      {t('Amount Due')}
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Payment Methods */}
            <div className="space-y-3">
              <h3 className="font-medium">{t('Select Payment Method')}</h3>
              {paymentMethods.length === 0 ? (
                <p className="text-sm text-muted-foreground text-center py-4">
                  {t('No payment methods available')}
                </p>
              ) : (
                <div className="space-y-2">
                  {paymentMethods.map((method) => (
                    <Card
                      key={method.id}
                      className="cursor-pointer transition-colors hover:border-primary"
                      onClick={() => handleMethodSelect(method.id)}
                    >
                      <CardContent className="p-3">
                        <div className="flex items-center gap-3">
                          <div className="text-primary">
                            {getMethodIcon(method.id)}
                          </div>
                          <span className="font-medium">{method.name}</span>
                          <Badge variant="secondary" className="ml-auto">
                            {t('Available')}
                          </Badge>
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              )}
            </div>

            {/* Actions */}
            <div className="flex gap-3 pt-4">
              <Button variant="outline" onClick={onClose} className="flex-1">
                {t('Cancel')}
              </Button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}