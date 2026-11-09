/**
 * Gestor de Instalación PWA y Adaptación Móvil - Control de Aula
 * Soporta instalación directa en 1 clic (beforeinstallprompt en Android/Chrome/Edge)
 * y guía asistida automática para iOS (Safari) o redes locales HTTP.
 */
(function () {
    'use strict';

    var deferredInstallPrompt = null;

    function esModoStandalone() {
        return (
            (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
            window.navigator.standalone === true
        );
    }

    // 1. Registrar Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker
                .register('./sw.js', { scope: './' })
                .catch(function () {});
        });
    }

    // 2. Actualizar visibilidad de los botones de instalación
    function actualizarBotonesInstalacion() {
        var botones = document.querySelectorAll('[data-btn-instalar-pwa]');
        var yaInstalada = esModoStandalone();
        for (var i = 0; i < botones.length; i++) {
            if (yaInstalada) {
                botones[i].style.display = 'none';
            } else {
                botones[i].style.display = 'inline-flex';
                if (deferredInstallPrompt) {
                    botones[i].classList.add('pwa-nativo-listo');
                }
            }
        }
    }

    // 3. Capturar evento nativo de instalación de Chrome / Edge / Android
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredInstallPrompt = e;
        actualizarBotonesInstalacion();
    });

    window.addEventListener('appinstalled', function () {
        deferredInstallPrompt = null;
        actualizarBotonesInstalacion();
        cerrarModalInstruccionesPWA();
    });

    // 4. Crear y mostrar modal de guía paso a paso si el navegador no lanza el prompt directo
    function asegurarModalInstruccionesPWA() {
        var existente = document.getElementById('modalInstalarPWA');
        if (existente) return existente;

        var ua = navigator.userAgent || '';
        var esIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
        var esAndroid = /Android/i.test(ua);

        var pasosHTML = '';
        if (esIOS) {
            pasosHTML =
                '<div class="pwa-paso">' +
                '  <span class="pwa-paso-num">1</span>' +
                '  <div>Tocá el botón <strong>Compartir</strong> (ícono del cuadrado con flecha hacia arriba <strong>&#x2191;</strong>) en la barra de Safari.</div>' +
                '</div>' +
                '<div class="pwa-paso">' +
                '  <span class="pwa-paso-num">2</span>' +
                '  <div>Deslizá hacia abajo y seleccioná <strong>"Agregar a inicio"</strong> (Add to Home Screen).</div>' +
                '</div>' +
                '<div class="pwa-paso">' +
                '  <span class="pwa-paso-num">3</span>' +
                '  <div>Tocá <strong>"Agregar"</strong> arriba a la derecha para tener <strong>Control Aula</strong> como app en tu pantalla.</div>' +
                '</div>';
        } else if (esAndroid) {
            pasosHTML =
                '<div class="pwa-paso">' +
                '  <span class="pwa-paso-num">1</span>' +
                '  <div>Tocá el menú de 3 puntos <strong>&#8942;</strong> arriba a la derecha en tu navegador (Chrome / Samsung).</div>' +
                '</div>' +
                '<div class="pwa-paso">' +
                '  <span class="pwa-paso-num">2</span>' +
                '  <div>Elegí la opción <strong>"Instalar aplicación"</strong> o <strong>"Agregar a la pantalla principal"</strong>.</div>' +
                '</div>' +
                '<div class="pwa-paso">' +
                '  <span class="pwa-paso-num">3</span>' +
                '  <div>Confirmá tocando <strong>"Instalar"</strong> para abrir el panel del aula directo desde tu celular a pantalla completa.</div>' +
                '</div>';
        } else {
            pasosHTML =
                '<div class="pwa-paso">' +
                '  <span class="pwa-paso-num">1</span>' +
                '  <div>En la barra de direcciones de <strong>Chrome</strong> o <strong>Edge</strong> (arriba a la derecha), buscá el ícono de <strong>Instalar Control de Aula</strong> (monitor con flecha).</div>' +
                '</div>' +
                '<div class="pwa-paso">' +
                '  <span class="pwa-paso-num">2</span>' +
                '  <div>O abrí el menú <strong>&#8942;</strong> del navegador &rarr; <strong>"Guardar y compartir" / "Aplicaciones"</strong> &rarr; <strong>"Instalar Control de Aula"</strong>.</div>' +
                '</div>';
        }

        var overlay = document.createElement('div');
        overlay.id = 'modalInstalarPWA';
        overlay.className = 'modal-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML =
            '<div class="modal-panel pwa-modal-panel">' +
            '  <div class="modal-header">' +
            '    <h3>' +
            '      <img src="assets/icons/icon-192.png" alt="" width="28" height="28" style="border-radius:7px;">' +
            '      <span>Instalar App Control de Aula</span>' +
            '    </h3>' +
            '    <button type="button" class="modal-cerrar" id="btnCerrarModalPWA" aria-label="Cerrar">&times;</button>' +
            '  </div>' +
            '  <div class="modal-body-scroll">' +
            '    <p style="font-size:13px; color:var(--text-secondary); margin-bottom:14px;">' +
            '      Podés instalar este panel en tu celular, tablet o PC para abrirlo con un toque desde tu pantalla de inicio como una aplicación nativa a pantalla completa:' +
            '    </p>' +
            '    <div class="pwa-pasos-lista">' + pasosHTML + '</div>' +
            '  </div>' +
            '  <div class="modal-footer" style="justify-content:flex-end;">' +
            '    <button type="button" class="btn btn-primario btn-pequeno" id="btnEntendidoPWA">Entendido</button>' +
            '  </div>' +
            '</div>';

        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (ev) {
            if (ev.target === overlay) cerrarModalInstruccionesPWA();
        });
        var btnX = document.getElementById('btnCerrarModalPWA');
        var btnOk = document.getElementById('btnEntendidoPWA');
        if (btnX) btnX.addEventListener('click', cerrarModalInstruccionesPWA);
        if (btnOk) btnOk.addEventListener('click', cerrarModalInstruccionesPWA);

        return overlay;
    }

    function abrirModalInstruccionesPWA() {
        var modal = asegurarModalInstruccionesPWA();
        modal.classList.add('activo');
    }

    function cerrarModalInstruccionesPWA() {
        var modal = document.getElementById('modalInstalarPWA');
        if (modal) modal.classList.remove('activo');
    }

    // 5. Acción al tocar el botón "Instalar App"
    function manejarClickInstalarPWA() {
        if (deferredInstallPrompt) {
            deferredInstallPrompt.prompt();
            deferredInstallPrompt.userChoice.then(function (choice) {
                if (choice && choice.outcome === 'accepted') {
                    deferredInstallPrompt = null;
                }
                actualizarBotonesInstalacion();
            });
        } else {
            abrirModalInstruccionesPWA();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        actualizarBotonesInstalacion();
        var botones = document.querySelectorAll('[data-btn-instalar-pwa]');
        for (var i = 0; i < botones.length; i++) {
            botones[i].addEventListener('click', manejarClickInstalarPWA);
        }
    });
})();

