import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { AppProvider } from '@shopify/polaris';
import enTranslations from '@shopify/polaris/locales/en.json';

const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Buy Now Later';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <AppProvider i18n={enTranslations}>
                <App {...props} />
            </AppProvider>
        );
    },
    progress: {
        color: '#008060', // Shopify Green accent
    },
});
