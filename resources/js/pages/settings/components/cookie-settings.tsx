import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { useState, useEffect } from 'react';
import { Save, Download } from 'lucide-react';
import { SettingsSection } from '@/components/settings-section';
import { useTranslation } from 'react-i18next';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';

interface CookieSettingsProps {
  settings?: Record<string, string>;
}

export default function CookieSettings({ settings = {} }: CookieSettingsProps) {
  const { t } = useTranslation();
  const pageProps = usePage().props as any;
  
  // Default settings
  const defaultSettings = {
    enableLogging: false,
    strictlyNecessaryCookies: true,
    cookieTitle: 'Cookie Consent',
    strictlyCookieTitle: 'Strictly Necessary Cookies',
    cookieDescription: 'We use cookies to enhance your browsing experience and provide personalized content.',
    strictlyCookieDescription: 'These cookies are essential for the website to function properly.',
    contactUsDescription: 'If you have any questions about our cookie policy, please contact us.',
    contactUsUrl: 'https://example.com/contact'
  };
  
  // Helper function to convert boolean values
  const convertToBoolean = (value: any, defaultValue: boolean) => {
    return value === '1' || value === true || defaultValue;
  };

  // Combine settings from props and page props
  const settingsData = Object.keys(settings).length > 0 
    ? settings 
    : (pageProps.settings || {});
  
  // Initialize state with merged settings
  const [cookieSettings, setCookieSettings] = useState(() => ({
    enableLogging: convertToBoolean(settingsData.enableLogging, defaultSettings.enableLogging),
    strictlyNecessaryCookies: convertToBoolean(settingsData.strictlyNecessaryCookies, defaultSettings.strictlyNecessaryCookies),
    cookieTitle: settingsData.cookieTitle || defaultSettings.cookieTitle,
    strictlyCookieTitle: settingsData.strictlyCookieTitle || defaultSettings.strictlyCookieTitle,
    cookieDescription: settingsData.cookieDescription || defaultSettings.cookieDescription,
    strictlyCookieDescription: settingsData.strictlyCookieDescription || defaultSettings.strictlyCookieDescription,
    contactUsDescription: settingsData.contactUsDescription || defaultSettings.contactUsDescription,
    contactUsUrl: settingsData.contactUsUrl || defaultSettings.contactUsUrl
  }));
  
  // Update state when settings change
  useEffect(() => {
    if (Object.keys(settingsData).length > 0) {
      setCookieSettings(prevSettings => ({
        ...prevSettings,
        enableLogging: convertToBoolean(settingsData.enableLogging, defaultSettings.enableLogging),
        strictlyNecessaryCookies: convertToBoolean(settingsData.strictlyNecessaryCookies, defaultSettings.strictlyNecessaryCookies),
        cookieTitle: settingsData.cookieTitle || defaultSettings.cookieTitle,
        strictlyCookieTitle: settingsData.strictlyCookieTitle || defaultSettings.strictlyCookieTitle,
        cookieDescription: settingsData.cookieDescription || defaultSettings.cookieDescription,
        strictlyCookieDescription: settingsData.strictlyCookieDescription || defaultSettings.strictlyCookieDescription,
        contactUsDescription: settingsData.contactUsDescription || defaultSettings.contactUsDescription,
        contactUsUrl: settingsData.contactUsUrl || defaultSettings.contactUsUrl
      }));
    }
  }, [settingsData]);

  // Handle cookie settings form changes
  const handleCookieSettingsChange = (field: string, value: string | boolean) => {
    setCookieSettings(prev => ({
      ...prev,
      [field]: value
    }));
  };

  // Handle cookie settings form submission
  const submitCookieSettings = (e: React.FormEvent) => {
    e.preventDefault();
    
    try {
      // Submit to backend using Inertia
      router.post(route('settings.cookie.update'), cookieSettings, {
        preserveScroll: true,
        onSuccess: (page) => {
          const successMessage = page.props.flash?.success;
          const errorMessage = page.props.flash?.error;
          
          if (successMessage) {
            toast.success(successMessage);
          } else if (errorMessage) {
            toast.error(errorMessage);
          }
        },
        onError: (errors) => {
          const errorMessage = errors.error || Object.values(errors).join(', ') || t('Failed to update cookie settings');
          toast.error(errorMessage);
        }
      });
    } catch (error) {
      toast.error(t('Failed to update cookie settings'));
    }
  };

  // Handle CSV download
  const handleDownloadCSV = async () => {
    try {
      const consent = localStorage.getItem('cookie-consent');
      const consentData = consent ? JSON.parse(consent) : null;
      
      if (!consentData) {
        toast.error(t('No cookie consent data found'));
        return;
      }
      
      // Get device and browser info
      const userAgent = navigator.userAgent;
      const language = navigator.language;
      const date = new Date(consentData.timestamp);
      
      // Detect device type
      const deviceType = /Mobile|Android|iPhone|iPad/.test(userAgent) ? 'Mobile' : 'Desktop';
      
      // Detect browser
      let browserName = 'Unknown';
      if (userAgent.includes('Chrome')) browserName = 'Chrome';
      else if (userAgent.includes('Firefox')) browserName = 'Firefox';
      else if (userAgent.includes('Safari')) browserName = 'Safari';
      else if (userAgent.includes('Edge')) browserName = 'Edge';
      
      // Detect OS
      let osName = 'Unknown';
      if (userAgent.includes('Windows')) osName = 'Windows';
      else if (userAgent.includes('Mac')) osName = 'macOS';
      else if (userAgent.includes('Linux')) osName = 'Linux';
      else if (userAgent.includes('Android')) osName = 'Android';
      else if (userAgent.includes('iOS')) osName = 'iOS';
      
      // Get location info (using a free IP API)
      let locationData = {
        ip: 'Unknown',
        country: 'Unknown',
        region: 'Unknown',
        regionName: 'Unknown',
        city: 'Unknown',
        zipcode: 'Unknown',
        lat: 'Unknown',
        lon: 'Unknown'
      };
      
      try {
        const response = await fetch('https://api.ipify.org?format=json');
        if (response.ok) {
          const ipData = await response.json();
          const locationResponse = await fetch(`https://ipapi.co/${ipData.ip}/json/`);
          if (locationResponse.ok) {
            const data = await locationResponse.json();
            locationData = {
              ip: ipData.ip || 'Unknown',
              country: data.country_name || 'Unknown',
              region: data.region_code || 'Unknown',
              regionName: data.region || 'Unknown',
              city: data.city || 'Unknown',
              zipcode: data.postal || 'Unknown',
              lat: data.latitude || 'Unknown',
              lon: data.longitude || 'Unknown'
            };
          }
        }
      } catch (error) {
        console.log('Location API failed, using defaults');
      }
      
      // Format accepted cookies
      const acceptedCookies = [];
      if (consentData.necessary) acceptedCookies.push('Necessary');
      if (consentData.analytics) acceptedCookies.push('Analytics');
      if (consentData.marketing) acceptedCookies.push('Marketing');
      
      const csvData = [
        ['IP', 'Date', 'Time', 'Accepted-cookies', 'Device type', 'Browser language', 'Browser name', 'OS Name', 'Country', 'Region', 'RegionName', 'City', 'Zipcode', 'Lat', 'Lon'],
        [
          locationData.ip,
          date.toLocaleDateString(),
          date.toLocaleTimeString(),
          acceptedCookies.join(';'),
          deviceType,
          language,
          browserName,
          osName,
          locationData.country,
          locationData.region,
          locationData.regionName,
          locationData.city,
          locationData.zipcode,
          locationData.lat,
          locationData.lon
        ]
      ];
      
      const csvContent = csvData.map(row => 
        row.map(field => `"${field.toString().replace(/"/g, '""')}"`).join(',')
      ).join('\n');
      
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', `accepted-cookies-${new Date().toISOString().split('T')[0]}.csv`);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      
      toast.success(t('Cookie preferences exported successfully'));
    } catch (error) {
      toast.error(t('Failed to export cookie preferences'));
    }
  };

  return (
    <SettingsSection
      title={t("Cookie Settings")}
      description={t("Configure cookie consent and privacy settings for your application")}
      action={
        <Button type="submit" form="cookie-settings-form" size="sm">
          <Save className="h-4 w-4 mr-2" />
          {t("Save Changes")}
        </Button>
      }
    >
      <form id="cookie-settings-form" onSubmit={submitCookieSettings} className="space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Enable Logging Switch */}
          <div className="flex items-center justify-between space-x-2">
            <div className="space-y-0.5">
              <Label htmlFor="enableLogging">{t("Enable Logging")}</Label>
              <p className="text-sm text-muted-foreground">
                {t("Enable cookie activity logging")}
              </p>
            </div>
            <Switch
              id="enableLogging"
              checked={cookieSettings.enableLogging}
              onCheckedChange={(checked) => handleCookieSettingsChange('enableLogging', checked)}
            />
          </div>

          {/* Strictly Necessary Cookies Switch */}
          <div className="flex items-center justify-between space-x-2">
            <div className="space-y-0.5">
              <Label htmlFor="strictlyNecessaryCookies">{t("Strictly Necessary Cookies")}</Label>
              <p className="text-sm text-muted-foreground">
                {t("Enable strictly necessary cookies")}
              </p>
            </div>
            <Switch
              id="strictlyNecessaryCookies"
              checked={cookieSettings.strictlyNecessaryCookies}
              onCheckedChange={(checked) => handleCookieSettingsChange('strictlyNecessaryCookies', checked)}
            />
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Cookie Title */}
          <div className="grid gap-2">
            <Label htmlFor="cookieTitle">{t("Cookie Title")}</Label>
            <Input
              id="cookieTitle"
              type="text"
              value={cookieSettings.cookieTitle}
              onChange={(e) => handleCookieSettingsChange('cookieTitle', e.target.value)}
              placeholder={t("Enter the main cookie consent title")}
            />
          </div>

          {/* Strictly Cookie Title */}
          <div className="grid gap-2">
            <Label htmlFor="strictlyCookieTitle">{t("Strictly Cookie Title")}</Label>
            <Input
              id="strictlyCookieTitle"
              type="text"
              value={cookieSettings.strictlyCookieTitle}
              onChange={(e) => handleCookieSettingsChange('strictlyCookieTitle', e.target.value)}
              placeholder={t("Enter the strictly necessary cookies title")}
            />
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Cookie Description */}
          <div className="grid gap-2">
            <Label htmlFor="cookieDescription">{t("Cookie Description")}</Label>
            <Textarea
              id="cookieDescription"
              value={cookieSettings.cookieDescription}
              onChange={(e) => handleCookieSettingsChange('cookieDescription', e.target.value)}
              placeholder={t("Enter the cookie consent description")}
              rows={4}
            />
          </div>

          {/* Strictly Cookie Description */}
          <div className="grid gap-2">
            <Label htmlFor="strictlyCookieDescription">{t("Strictly Cookie Description")}</Label>
            <Textarea
              id="strictlyCookieDescription"
              value={cookieSettings.strictlyCookieDescription}
              onChange={(e) => handleCookieSettingsChange('strictlyCookieDescription', e.target.value)}
              placeholder={t("Enter the strictly necessary cookies description")}
              rows={4}
            />
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Contact Us Description */}
          <div className="grid gap-2">
            <Label htmlFor="contactUsDescription">{t("Contact Us Description")}</Label>
            <Textarea
              id="contactUsDescription"
              value={cookieSettings.contactUsDescription}
              onChange={(e) => handleCookieSettingsChange('contactUsDescription', e.target.value)}
              placeholder={t("Enter the contact us description for cookie inquiries")}
              rows={3}
            />
          </div>

          {/* Contact Us URL */}
          <div className="grid gap-2">
            <Label htmlFor="contactUsUrl">{t("Contact Us URL")}</Label>
            <Input
              id="contactUsUrl"
              type="url"
              value={cookieSettings.contactUsUrl}
              onChange={(e) => handleCookieSettingsChange('contactUsUrl', e.target.value)}
              placeholder={t("Enter the contact us URL for cookie inquiries")}
            />
          </div>
        </div>

        {/* Download CSV Section */}
        <div className="pt-4 border-t">
          <div className="flex items-center justify-between">
            <div>
              <h4 className="text-sm font-medium">{t("Download Accepted Cookies")}</h4>
              <p className="text-sm text-muted-foreground">
                Download a CSV file of accepted cookie preferences
              </p>
            </div>
            <Button type="button" variant="outline" size="sm" onClick={handleDownloadCSV}>
              <Download className="h-4 w-4 mr-2" />
              Download CSV
            </Button>
          </div>
        </div>
      </form>
    </SettingsSection>
  );
}