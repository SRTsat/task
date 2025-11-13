import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { router } from '@inertiajs/react';

interface InvoicePayPalFormProps {
  invoice: {
    id: number;
    balance_due: number;
  };
  paypalClientId: string;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoicePayPalForm({ 
  invoice, 
  paypalClientId,
  onSuccess, 
  onCancel 
}: InvoicePayPalFormProps) {
  const { t } = useTranslation();
  const paypalRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!paypalClientId || !paypalRef.current) return;

    // Load PayPal SDK
    const script = document.createElement('script');
    script.src = `https://www.paypal.com/sdk/js?client-id=${paypalClientId}&currency=USD&disable-funding=credit,card`;
    script.async = true;
    
    script.onload = () => {
      if (window.paypal && paypalRef.current) {
        window.paypal.Buttons({
          createOrder: (data: any, actions: any) => {
            const formattedAmount = invoice.balance_due.toString().replace(/,/g, '');
            const numericAmount = parseFloat(formattedAmount).toFixed(2);
            
            return actions.order.create({
              purchase_units: [{
                amount: {
                  value: numericAmount,
                  currency_code: 'USD'
                }
              }]
            });
          },
          onApprove: (data: any, actions: any) => {
            return actions.order.capture().then((details: any) => {
              // Process payment through our invoice payment endpoint
              router.post(route('invoices.process-payment', invoice.id), {
                payment_method: 'paypal',
                payment_data: {
                  order_id: data.orderID,
                  payment_id: details.id,
                }
              }, {
                onSuccess: () => {
                  toast.success(t('Payment processed successfully'));
                  onSuccess();
                },
                onError: (errors) => {
                  toast.error(errors.message || t('Payment failed'));
                }
              });
            });
          },
          onError: (err: any) => {
            console.error('PayPal error:', err);
            if (err.message && err.message.includes('declined')) {
              toast.error(t('Card was declined. Please try a different payment method.'));
            } else {
              toast.error(t('Payment failed. Please try again.'));
            }
          },
          onCancel: () => {
            onCancel();
          }
        }).render(paypalRef.current);
      }
    };

    document.head.appendChild(script);

    return () => {
      if (document.head.contains(script)) {
        document.head.removeChild(script);
      }
    };
  }, [paypalClientId, invoice.id, invoice.balance_due]);

  if (!paypalClientId) {
    return <div className="p-4 text-center text-red-500">{t('PayPal not configured')}</div>;
  }

  return (
    <div className="space-y-4">
      <div ref={paypalRef}></div>
    </div>
  );
}

// Extend window object for PayPal
declare global {
  interface Window {
    paypal?: any;
  }
}