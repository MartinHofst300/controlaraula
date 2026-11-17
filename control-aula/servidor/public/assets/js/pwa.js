/**
 * Registro del Service Worker (PWA)
 * Solo funciona en contexto seguro (HTTPS o localhost). Por HTTP simple no hace nada.
 */
(function () {
    'use strict';
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return;

    window.addEventListener('load', function () {
        navigator.serviceWorker.register('sw.js').catch(function (err) {
            console.warn('No se pudo registrar el Service Worker:', err);
        });
    });
})();
