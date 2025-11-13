import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePage } from '@inertiajs/react';

export default function CookieConsentBanner() {
  const { t } = useTranslation();
  const { props } = usePage();
  const [isVisible, setIsVisible] = useState(false);
  
  const globalSettings = (props as any).globalSettings || {};
  const settings = {
    cookieTitle: globalSettings.cookieTitle || 'Cookie Consent',
    cookieDescription: globalSettings.cookieDescription || 'We use cookies to enhance your browsing experience and provide personalized content.',
    contactUsDescription: globalSettings.contactUsDescription || 'If you have any questions about our cookie policy, please contact us.',
    contactUsUrl: globalSettings.contactUsUrl || '#'
  };

  useEffect(() => {
    const enableLogging = globalSettings.enableLogging === '1' || globalSettings.enableLogging === 1 || globalSettings.enableLogging === true;
    const isDemoMode = (window as any).isDemo === true;
       
    // Only show banner if logging is explicitly enabled and not in demo mode
    if (enableLogging && !isDemoMode) {
      const consent = localStorage.getItem('cookie-consent');
      if (!consent) {
        setIsVisible(true);
      }
    }
  }, []);

  const acceptAll = () => {
    const consentData = {
      necessary: true,
      analytics: true,
      marketing: true,
      timestamp: Date.now()
    };
    
    localStorage.setItem('cookie-consent', JSON.stringify(consentData));    
    setIsVisible(false);
  };

  const acceptNecessary = () => {
    const consentData = {
      necessary: true,
      analytics: false,
      marketing: false,
      timestamp: Date.now()
    };
    
    localStorage.setItem('cookie-consent', JSON.stringify(consentData));    
    setIsVisible(false);
  };

  // Temporary: Add to window for testing
  if (typeof window !== 'undefined') {
    (window as any).resetCookieConsent = () => {
      localStorage.removeItem('cookie-consent');
      setIsVisible(true);
      console.log('Cookie consent reset - banner should now be visible');
    };
    
    (window as any).showCookieBanner = () => {
      setIsVisible(true);
      console.log('Cookie banner forced to show');
    };
    
    (window as any).checkCookieSettings = () => {
      console.log('Current cookie settings:', {
        enableLogging: globalSettings.enableLogging,
        isVisible,
        hasConsent: !!localStorage.getItem('cookie-consent'),
        consentData: localStorage.getItem('cookie-consent')
      });
    };
  }

  if (!isVisible) return null;

  return (
    <div className="fixed bottom-4 left-4 right-4 z-50 md:left-auto md:max-w-md">
      <Card className="p-4 shadow-lg border">
        <div className="flex justify-between items-start mb-3">
          <h3 className="font-semibold text-sm">
            {settings.cookieTitle || t('Cookie Consent')}
          </h3>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setIsVisible(false)}
            className="h-6 w-6 p-0"
          >
            <X className="h-4 w-4" />
          </Button>
        </div>
        
        <p className="text-sm text-muted-foreground mb-4">
          {settings.cookieDescription || t('We use cookies to enhance your browsing experience and provide personalized content.')}
        </p>
        
        <div className="flex flex-col gap-2 sm:flex-row">
          <Button onClick={acceptAll} size="sm" className="flex-1">
            {t('Accept All')}
          </Button>
          <Button onClick={acceptNecessary} variant="outline" size="sm" className="flex-1">
            {t('Necessary Only')}
          </Button>
        </div>
        
        {settings.contactUsUrl && (
          <p className="text-xs text-muted-foreground mt-2">
            {settings.contactUsDescription || t('Questions about our cookie policy?')}{' '}
            <a href={settings.contactUsUrl} className="underline">
              {t('Contact us')}
            </a>
          </p>
        )}
      </Card>
    </div>
  );
}