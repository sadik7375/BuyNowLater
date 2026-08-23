import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { AppProvider } from '@shopify/polaris';
import '@shopify/polaris/build/esm/styles.css';
import enTranslations from '@shopify/polaris/locales/en.json';

const appEl = document.getElementById('app');

if (appEl && appEl.dataset && appEl.dataset.page) {
    try {
        createInertiaApp({
            title: (title) => `${title} - Buy Now Later`,
            resolve: (name) => {
                const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
                const page = pages[`./Pages/${name}.jsx`];
                if (!page) {
                    throw new Error(`Page not found: ./Pages/${name}.jsx`);
                }
                return page.default || page;
            },
            setup({ el, App, props }) {
                const root = createRoot(el);
                root.render(
                    <AppProvider i18n={enTranslations}>
                        <App {...props} />
                    </AppProvider>
                );
            },
        });
    } catch (err) {
        console.error("Inertia initialization error:", err);
    }
}
