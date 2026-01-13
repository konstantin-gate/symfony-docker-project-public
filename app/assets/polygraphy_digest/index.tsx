import React, { Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './index.css';
import './i18n'; // Import i18n configuration

const container = document.getElementById('polygraphy-digest-app');

if (container) {
    const root = createRoot(container);
    root.render(
        <React.StrictMode>
            <Suspense fallback="Loading...">
                <App />
            </Suspense>
        </React.StrictMode>
    );
}
