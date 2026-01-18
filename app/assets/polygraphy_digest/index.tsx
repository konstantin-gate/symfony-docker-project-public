import React, { Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './index.css';
import i18n from './i18n'; // Import i18n instance

const container = document.getElementById('polygraphy-digest-app');

if (container) {
    const locale = container.dataset.locale;
    if (locale) {
        i18n.changeLanguage(locale);
    }

    const root = createRoot(container);
    root.render(
        <React.StrictMode>
            <Suspense fallback="Loading...">
                <App />
            </Suspense>
        </React.StrictMode>
    );
}
