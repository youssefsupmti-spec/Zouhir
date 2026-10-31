import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import '../css/app.css';

createInertiaApp({
    title: title => title ? `${title} - Ouais Express` : 'Ouais Express - Société de Livraison Express',
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.{jsx,tsx}', { eager: true });
        const pageModule = pages[`./Pages/${name}.jsx`] || pages[`./Pages/${name}.tsx`];
        if (!pageModule) {
            throw new Error(`Inertia Page [./Pages/${name}.{jsx,tsx}] not found.`);
        }
        const page = pageModule.default;
        if (page && page.layout === undefined && !name.startsWith('Auth/') && name !== 'Landing') {
            page.layout = pageComponent => <AuthenticatedLayout>{pageComponent}</AuthenticatedLayout>;
        }
        return pageModule;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#ff8806',
        showSpinner: true,
    },
});
