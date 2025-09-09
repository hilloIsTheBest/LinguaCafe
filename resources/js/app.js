require('./bootstrap');

import { createApp } from 'vue';
const app = createApp({});

// Register Vue components, directives, etc. here
// Example:
// import ExampleComponent from './components/ExampleComponent.vue';
// app.component('example-component', ExampleComponent);

app.mount('#app');

/**
 * Service Worker Registration for PWA
 *
 * Registers the service worker if supported by the browser.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then((registration) => {
                console.log('[SW] Registered with scope:', registration.scope);
            })
            .catch((error) => {
                console.error('[SW] Registration failed:', error);
            });
    });
}
