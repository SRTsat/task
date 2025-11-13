import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, CreditCard, AlertCircle } from 'lucide-react';
import { router } from '@inertiajs/react';

interface MidtransPaymentFormProps {
  planId: number;
  planPrice: number;
  couponCode?: string;
  billingCycle: 'monthly' | 'yearly';
  midtransClientKey: string; // client key for snap.js-
  midtransMode?: 'sandbox' | 'live';
  paymentMethod?: string;
  currency?: string;
  onSuccess: () => void;
  onCancel: () => void;
}

export function MidtransPaymentForm({
  planId,
  planPrice,
  couponCode,
  billingCycle,
  midtransClientKey,
  midtransMode = 'sandbox',
  paymentMethod,
  currency = 'IDR',
  onSuccess,
  onCancel,
}: MidtransPaymentFormProps) {
  const { t } = useTranslation();
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handlePayment = async () => {
    if (!midtransClientKey) {
      setError(t('Midtrans belum dikonfigurasi pada frontend. Silakan minta administrator untuk mengisi Midtrans Client Key di Settings.'));
      return;
    }

    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(route('midtrans.create-payment'), {
        method: 'POST',
        credentials: 'same-origin', // ensure session cookie is sent
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'X-Requested-With': 'XMLHttpRequest' // tell Laravel this is AJAX (prevents HTML redirects)
        },
        body: JSON.stringify({
          plan_id: planId,
          billing_cycle: billingCycle,
          coupon_code: couponCode,
          payment_method: paymentMethod,
        }),
      });

      // If server responded with a redirect to login (HTML), detect and show helpful message
      const contentType = response.headers.get('content-type') || '';
      let data: any = null;
      if (contentType.includes('application/json')) {
        data = await response.json();
      } else {
        const text = await response.text();
        // common case: HTML login page returned (user not authenticated) or other HTML error
        if (response.status === 419 || text.toLowerCase().includes('token mismatch') || text.toLowerCase().includes('csrf')) {
          setError(t('Session expired. Silakan login ulang dan coba lagi.'));
          setIsLoading(false);
          return;
        }
        if (text && text.toLowerCase().includes('login')) {
          setError(t('Anda belum terautentikasi. Silakan login terlebih dahulu.'));
          setIsLoading(false);
          return;
        }
        // fallback: unknown non-json response
        setError(t('Unexpected server response. Periksa logs atau coba lagi.'));
        setIsLoading(false);
        return;
      }

      if (!response.ok) {
        const serverMessage = (data && (data.error || data.message)) || t('Payment creation failed');
        if (String(serverMessage).toLowerCase().includes('midtrans')) {
          setError(t('Midtrans belum dikonfigurasi pada server. Minta administrator memasukkan Midtrans Secret Key di Settings.'));
        } else if (response.status === 401 || response.status === 403) {
          setError(t('Akses ditolak. Silakan login dan coba lagi.'));
        } else {
          setError(String(serverMessage));
        }
        setIsLoading(false);
        return;
      }

      if (data.success) {
        // Midtrans can return either snap_token or redirect_url depending on integration
        if (data.redirect_url) {
          // some integrations may prefer direct redirect
          window.location.href = data.redirect_url;
        } else if (data.snap_token) {
          initializeMidtransSnap(data.snap_token, data.order_id);
        } else {
          throw new Error(t('Invalid Midtrans response'));
        }
      } else {
        throw new Error(data.error || t('Payment creation failed'));
      }
    } catch (err) {
      console.error('Midtrans payment error:', err);
      setError(err instanceof Error ? err.message : t('Payment initialization failed'));
      setIsLoading(false);
    }
  };

  const initializeMidtransSnap = (snapToken: string, orderId: string) => {
    if (!(window as any).snap) {
      const script = document.createElement('script');
      const base = midtransMode === 'live' ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
      script.src = `${base}/snap/snap.js`;
      script.setAttribute('data-client-key', midtransClientKey); // Use the client key
      script.onload = () => {
        openSnapPayment(snapToken, orderId);
      };
      script.onerror = () => {
        setError(t('Failed to load Midtrans script'));
        setIsLoading(false);
      };
      document.head.appendChild(script);
    } else {
      openSnapPayment(snapToken, orderId);
    }
  };

  const openSnapPayment = (snapToken: string, orderId: string) => {
    (window as any).snap.pay(snapToken, {
      onSuccess: (result: any) => {
        handlePaymentSuccess(result, orderId);
      },
      onPending: (result: any) => {
        setIsLoading(false);
      },
      onError: (result: any) => {
        setError(t('Payment failed'));
        setIsLoading(false);
      },
      onClose: () => {
        setIsLoading(false);
      }
    });
  };

  const handlePaymentSuccess = (result: any, orderId: string) => {
    router.post(route('midtrans.payment'), {
      plan_id: planId,
      billing_cycle: billingCycle,
      coupon_code: couponCode,
      transaction_status: result.transaction_status,
      order_id: orderId,
    }, {
      onSuccess: () => {
        onSuccess();
      },
      onError: (errors) => {
        console.error('Payment processing error:', errors);
        setError(Object.values(errors).flat().join(', '));
        setIsLoading(false);
      },
    });
  };

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: currency,
    }).format(price);
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <CreditCard className="h-5 w-5" />
          {t('Midtrans Payment')}
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {error && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription className="whitespace-pre-line">{error}</AlertDescription>
            {/* If missing client key, show link to settings */}
          </Alert>
        )}

        {!midtransClientKey && (
          <div className="flex gap-2">
            <Button variant="ghost" onClick={() => router.visit(route('settings.index'))}>
              {t('Open Payment Settings')}
            </Button>
            <Button variant="outline" onClick={onCancel}>
              {t('Cancel')}
            </Button>
          </div>
        )}

        <div className="bg-muted p-4 rounded-lg">
          <div className="flex justify-between items-center">
            <span className="font-medium">{t('Total Amount')}</span>
            <span className="text-lg font-bold">{formatPrice(planPrice)}</span>
          </div>
          <div className="text-sm text-muted-foreground mt-1">
            {t('Billing Cycle')}: {t(billingCycle)}
          </div>
          {couponCode && (
            <div className="text-sm text-green-600 mt-1">
              {t('Coupon Applied')}: {couponCode}
            </div>
          )}
        </div>

        <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
          <h4 className="font-medium text-blue-900 mb-2">{t('Supported Payment Methods')}</h4>
          <ul className="text-sm text-blue-800 space-y-1">
            <li>• Credit/Debit Cards</li>
            <li>• Bank Transfer</li>
            <li>• E-Wallets (GoPay, OVO, DANA)</li>
            <li>• Convenience Stores</li>
          </ul>
        </div>

        <div className="flex gap-3">
          <Button
            variant="outline"
            onClick={onCancel}
            disabled={isLoading}
            className="flex-1"
          >
            {t('Cancel')}
          </Button>
          <Button
            onClick={handlePayment}
            disabled={isLoading || !midtransClientKey}
            className="flex-1"
          >
            {isLoading ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                {t('Processing...')}
              </>
            ) : (
              <>
                <CreditCard className="mr-2 h-4 w-4" />
                {t('Pay with Midtrans')}
              </>
            )}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
} 