import React, { useState, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { PageTemplate } from '@/components/page-template';
import TimesheetCalendar from '@/components/timesheets/TimesheetCalendar';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/authorization';

interface CalendarDay {
    date: string;
    entries: any[];
    totalHours: number;
    isCurrentMonth: boolean;
    isToday: boolean;
}

interface Props {
    calendarData: CalendarDay[];
    currentMonth: string;
    projects: any[];
    timesheetId: number;
    permissions?: any;
}

export default function CalendarView({ calendarData, currentMonth, projects, timesheetId, permissions }: Props) {
    const { t } = useTranslation();
    const { flash, auth } = usePage().props as any;
    const userPermissions = auth?.permissions || [];

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const handleMonthChange = (month: string) => {
        router.get(route('timesheets.calendar-view'), { month }, { preserveState: true });
    };

    const pageActions: any[] = [];

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Timesheets'), href: route('timesheets.index') },
        { title: t('Calendar View') }
    ];

    return (
        <PageTemplate 
            title={t('Calendar View')} 
            actions={pageActions}
            breadcrumbs={breadcrumbs}
        >
            <Head title={t('Calendar View')} />
            
            <TimesheetCalendar
                calendarData={calendarData}
                currentMonth={currentMonth}
                projects={projects}
                timesheetId={timesheetId}
                onMonthChange={handleMonthChange}
                permissions={userPermissions}
            />
        </PageTemplate>
    );
}