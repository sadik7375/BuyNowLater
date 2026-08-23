import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { AppProvider } from '@shopify/polaris';
import '@shopify/polaris/build/esm/styles.css';
import enTranslations from '@shopify/polaris/locales/en.json';

const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Buy Now Later';

const el = document.getElementById('app');
if (el) {
    try {
        const initialPage = el.dataset && el.dataset.page ? JSON.parse(el.dataset.page) : null;
        if (initialPage && initialPage.component) {
            createInertiaApp({
                page: initialPage,
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
                    color: '#4B5563',
                },
            });
        } else {
            console.error('Inertia root element found, but data-page component is missing or null.');
        }
    } catch (e) {
        console.error('Inertia page data parse error:', e);
    }
}
