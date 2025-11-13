import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useState, useEffect } from 'react';
import { Save } from 'lucide-react';
import { SettingsSection } from '@/components/settings-section';
import { useTranslation } from 'react-i18next';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import MediaPicker from '@/components/MediaPicker';

interface SeoSettingsProps {
  settings?: Record<string, string>;
}

export default function SeoSettings({ settings = {} }: SeoSettingsProps) {
  const { t } = useTranslation();
  const pageProps = usePage().props as any;
  
  // Default settings
  const defaultSettings = {
    metaKeywords: '',
    metaDescription: '',
    metaImage: ''
  };
  
  // Combine settings from props and page props
  const settingsData = Object.keys(settings).length > 0 
    ? settings 
    : (pageProps.settings || {});
  
  // Initialize state with merged settings
  const [seoSettings, setSeoSettings] = useState(() => ({
    metaKeywords: settingsData.metaKeywords || defaultSettings.metaKeywords,
    metaDescription: settingsData.metaDescription || defaultSettings.metaDescription,
    metaImage: settingsData.metaImage || defaultSettings.metaImage
  }));

  // State for media picker
  const [selectedMediaIds, setSelectedMediaIds] = useState<number[]>([]);
  
  // Update state when settings change
  useEffect(() => {
    if (Object.keys(settingsData).length > 0) {
      const mergedSettings = Object.keys(defaultSettings).reduce((acc, key) => {
        acc[key] = settingsData[key] || defaultSettings[key];
        return acc;
      }, {} as Record<string, string>);
      
      setSeoSettings(prevSettings => ({
        ...prevSettings,
        ...mergedSettings
      }));
    }
  }, [settingsData]);

  // Handle SEO settings form changes
  const handleSeoSettingsChange = (field: string, value: string) => {
    setSeoSettings(prev => ({
      ...prev,
      [field]: value
    }));
  };

  // Handle media selection
  const handleMediaSelect = (url: string, mediaIds?: number[]) => {
    setSeoSettings(prev => ({
      ...prev,
      metaImage: url
    }));
    setSelectedMediaIds(mediaIds || []);
  };

  // Handle SEO settings form submission
  const submitSeoSettings = (e: React.FormEvent) => {
    e.preventDefault();
    
    // Client-side validation
    if (!seoSettings.metaKeywords.trim()) {
      toast.error(t('Meta Keywords is required'));
      return;
    }
    
    if (!seoSettings.metaDescription.trim()) {
      toast.error(t('Meta Description is required'));
      return;
    }
    
    if (!seoSettings.metaImage.trim()) {
      toast.error(t('Meta Image is required'));
      return;
    }
    
    // Submit to backend using Inertia
    try {
      const routeUrl = route('settings.seo.update');
      router.post(routeUrl, seoSettings, {
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
        const errorMessage = errors.error || Object.values(errors).join(', ') || t('Failed to update SEO settings');
        toast.error(errorMessage);
      }
    });
    } catch (error) {
      toast.error(t('Failed to submit SEO settings'));
    }
  };

  return (
    <SettingsSection
      title={t("SEO Settings")}
      description={t("Configure SEO settings to improve your website's search engine visibility")}
      action={
        <Button type="submit" form="seo-settings-form" size="sm">
          <Save className="h-4 w-4 mr-2" />
          {t("Save Changes")}
        </Button>
      }
    >
      <form id="seo-settings-form" onSubmit={submitSeoSettings} className="space-y-6">
        <div className="grid grid-cols-1 gap-6">
          <div className="grid gap-2">
            <Label htmlFor="metaKeywords">{t("Meta Keywords")} <span className="text-red-500">*</span></Label>
            <Input
              id="metaKeywords"
              type="text"
              value={seoSettings.metaKeywords}
              onChange={(e) => handleSeoSettingsChange('metaKeywords', e.target.value)}
              placeholder={t("Enter keywords separated by commas")}
              required
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="metaDescription">{t("Meta Description")} <span className="text-red-500">*</span></Label>
            <Textarea
              id="metaDescription"
              value={seoSettings.metaDescription}
              onChange={(e) => handleSeoSettingsChange('metaDescription', e.target.value)}
              placeholder={t("Enter a brief description for search engines (max 160 characters)")}
              maxLength={160}
              rows={3}
              required
            />
            <div className="text-sm text-muted-foreground text-right">
              {seoSettings.metaDescription.length}/160
            </div>
          </div>

          <div className="grid gap-2">
            <MediaPicker
              label={`${t("Meta Image")} *`}
              value={seoSettings.metaImage}
              onChange={handleMediaSelect}
              placeholder={t("Select meta image for SEO")}
              showPreview={true}
            />
            <p className="text-xs text-muted-foreground">
              {t("Recommended size: 1200x630px for optimal social media sharing")}
            </p>
          </div>
        </div>
      </form>
    </SettingsSection>
  );
}