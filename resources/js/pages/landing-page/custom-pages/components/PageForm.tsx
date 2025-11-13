import React from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from 'react-i18next';

interface PageFormProps {
  data: {
    title: string;
    content: string;
    meta_title: string;
    meta_description: string;
    is_active: boolean;
    sort_order: number;
  };
  setData: (key: string, value: any) => void;
  errors: Record<string, string>;
  processing: boolean;
  onSubmit: (e: React.FormEvent) => void;
  onCancel: () => void;
  isEditing?: boolean;
}

export function PageForm({ 
  data, 
  setData, 
  errors, 
  processing, 
  onSubmit, 
  onCancel, 
  isEditing = false 
}: PageFormProps) {
  const { t } = useTranslation();

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      <div>
        <Label htmlFor="title">{t('Page Title')}</Label>
        <Input
          id="title"
          value={data.title}
          onChange={(e) => setData('title', e.target.value)}
          placeholder={t('About Us')}
        />
        {errors.title && <p className="text-red-600 text-sm">{errors.title}</p>}
      </div>

      <div>
        <Label htmlFor="content">{t('Content')}</Label>
        <RichTextEditor
          content={data.content}
          onChange={(content) => setData('content', content)}
          placeholder={t('Page content...')}
          className="min-h-[200px]"
        />
        {errors.content && <p className="text-red-600 text-sm">{errors.content}</p>}
      </div>

      <div>
        <Label htmlFor="meta_title">{t('Meta Title (SEO)')}</Label>
        <Input
          id="meta_title"
          value={data.meta_title}
          onChange={(e) => setData('meta_title', e.target.value)}
          placeholder={t('SEO title')}
        />
      </div>

      <div>
        <Label htmlFor="meta_description">{t('Meta Description (SEO)')}</Label>
        <Textarea
          id="meta_description"
          value={data.meta_description}
          onChange={(e) => setData('meta_description', e.target.value)}
          placeholder={t('SEO description')}
          rows={3}
        />
      </div>

      <div className="flex items-center space-x-2">
        <Switch
          id="is_active"
          checked={data.is_active}
          onCheckedChange={(checked) => setData('is_active', checked)}
        />
        <Label htmlFor="is_active">{t('Active')}</Label>
      </div>

      <div className="flex justify-end space-x-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          {t('Cancel')}
        </Button>
        <Button type="submit" disabled={processing}>
          {processing 
            ? (isEditing ? t('Updating...') : t('Creating...'))
            : (isEditing ? t('Update Page') : t('Create Page'))
          }
        </Button>
      </div>
    </form>
  );
}