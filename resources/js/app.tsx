import '../css/app.css';
import '../css/dark-mode.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { lazy, Suspense } from 'react';
import { LayoutProvider } from './contexts/LayoutContext';
import { SidebarProvider } from './contexts/SidebarContext';
import { BrandProvider } from './contexts/BrandContext';
import { ModalStackProvider } from './contexts/ModalStackContext';
import { initializeTheme } from './hooks/use-appearance';
import { CustomToast } from './components/custom-toast';
import { initializeGlobalSettings } from './utils/globalSettings';
import './i18n'; // Import i18n configuration
import './utils/axios-config'; // Import axios configuration
import * as Sentry from "@sentry/react";

if (import.meta.env.VITE_SENTRY_DSN) {
    let sentryIntegrations = [];
    sentryIntegrations.push(Sentry.browserTracingIntegration());
    if (!(process.env.NODE_ENV === 'development')) sentryIntegrations.push(Sentry.replayIntegration());
    if (!(process.env.NODE_ENV === 'development')) sentryIntegrations.push(Sentry.feedbackIntegration({ colorScheme: "system" }));
    Sentry.init({
        dsn: (import.meta.env.VITE_SENTRY_DSN || 'https://13a9fa39d33e023ad1e3c89300343c37@collector.sadata.id/8').toString(),
        // Adjust this value in production, or use tracesSampler for greater control
        // or if you want to be able to turn off tracing for specific users
        // Setting this option to true will send default PII data to Sentry.
        // For example, automatic IP address collection on events
        sendDefaultPii: true,
        integrations: sentryIntegrations,
        tracesSampleRate: 1.0, //  Capture 100% of the transactions
        replaysSessionSampleRate: 0.1, // This sets the sample rate at 10%. You may want to change it to 100% while in development and then sample at a lower rate in production.
        replaysOnErrorSampleRate: 1.0, // If you're not already sampling the entire session, change the sample rate to 100% when sampling sessions where errors occur.
        environment: import.meta.env.VITE_APP_ENV || 'development',
        release: import.meta.env.VITE_APP_VERSION || 'unknown',
    });
}

// Add event listener for theme changes
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    // Re-apply theme when system preference changes
    const savedTheme = localStorage.getItem('themeSettings');
    if (savedTheme) {
        const themeSettings = JSON.parse(savedTheme);
        if (themeSettings.appearance === 'system') {
            initializeTheme();
        }
    }
});

const appName = 'SADA - Daily Task';

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,    
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);
        
        // Make page data globally available for axios interceptor
        try {
            (window as any).page = props.initialPage;
        } catch (e) {
            console.warn('Could not set global page data:', e);
        }
        
        // Set demo mode globally
        try {
            (window as any).isDemo = props.initialPage.props?.is_demo || false;
        } catch (e) {
            // Ignore errors
        }
        
        // Initialize global settings from shared data
        const globalSettings = props.initialPage.props.globalSettings || {};
        if (Object.keys(globalSettings).length > 0) {
            initializeGlobalSettings(globalSettings);
        }

        // Create a memoized render function to prevent unnecessary re-renders
        const renderApp = (appProps: any) => {
            const currentGlobalSettings = appProps.initialPage.props.globalSettings || {};
            const user = appProps.initialPage.props.auth?.user;

            return (
                <ModalStackProvider>
                    <LayoutProvider>
                        <SidebarProvider>
                            <BrandProvider globalSettings={currentGlobalSettings} user={user}>
                                <Suspense fallback={<div className="flex h-screen w-full items-center justify-center">Loading...</div>}>
                                    <App {...appProps} />
                                    {/* <App {...appProps} /> */}
                                </Suspense>
                                <CustomToast />
                            </BrandProvider>
                        </SidebarProvider>
                    </LayoutProvider>
                </ModalStackProvider>

            );
        };
        
        // Initial render
        root.render(renderApp(props));
        
        // Update global page data on navigation and re-render with new settings
        router.on('navigate', (event) => {
            try {
                (window as any).page = event.detail.page;
                // Re-render with updated props including globalSettings
                root.render(renderApp({ initialPage: event.detail.page }));
                
                // Force dark mode check on navigation
                const savedTheme = localStorage.getItem('themeSettings');
                if (savedTheme) {
                    const themeSettings = JSON.parse(savedTheme);
                    const isDark = themeSettings.appearance === 'dark' || 
                        (themeSettings.appearance === 'system' && 
                         window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', isDark);
                    document.body.classList.toggle('dark', isDark);
                }
            } catch (e) {
                console.error('Navigation error:', e);
            }
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Initialize direction from localStorage if available
const initializeDirection = () => {
    const savedDirection = localStorage.getItem('layoutDirection');
    if (savedDirection) {
        document.documentElement.dir = savedDirection;
        document.documentElement.setAttribute('dir', savedDirection);
    }
};

// Initialize direction on page load
initializeDirection();