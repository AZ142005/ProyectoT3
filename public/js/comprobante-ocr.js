/**
 * Extracción asistida de datos de comprobantes de pago.
 *
 * Módulo compartido por el portal de pago directo y el flujo de residentes.
 * Ejecuta OCR en el navegador (Tesseract.js) y, cuando la lectura base no
 * reconoce los datos clave, ejecuta una segunda pasada de refuerzo:
 *
 *  1. Pasada base: umbralizado estándar de Tesseract (óptimo para el texto
 *     pequeño de las etiquetas: fecha, referencia, banco, etc.).
 *  2. Pasada de refuerzo: umbralizado adaptativo (Sauvola) que recupera los
 *     importes mostrados en capturas en modo oscuro (texto blanco sobre cajas
 *     grises), los cuales el umbralizado global descarta.
 *
 * Cada texto se analiza en el backend; las respuestas se combinan priorizando
 * siempre los valores de la primera pasada.
 *
 * Uso: ComprobanteOCR.procesar(file, { endpoint, onEstado })
 *   -> Promise<{ datos: object, error: null }>
 *   onEstado recibe ('motor'), ('leyendo', pct), ('analizando'), ('refuerzo')
 *   o ('pdf') para reflejar el progreso en la interfaz.
 */
(function () {
    'use strict';

    function obtenerTokenCsrf() {
        var oculto = document.querySelector('input[name="csrf_token"]');
        if (oculto && oculto.value) { return oculto.value; }
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) { return meta.getAttribute('content') || ''; }
        return '';
    }

    /**
     * La validación CSRF rota el token tras cada POST: se sincroniza el del
     * formulario para permitir análisis repetidos sin recargar la página.
     */
    function sincronizarTokenCsrf(datos) {
        if (!datos || !datos.csrf_token) { return; }
        var oculto = document.querySelector('input[name="csrf_token"]');
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (oculto) { oculto.value = datos.csrf_token; }
        if (meta) { meta.setAttribute('content', datos.csrf_token); }
    }

    function enviarFormulario(endpoint, construir) {
        var formData = new FormData();
        formData.append('csrf_token', obtenerTokenCsrf());
        construir(formData);
        return fetch(endpoint, { method: 'POST', body: formData })
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (datos) {
                sincronizarTokenCsrf(datos);
                return datos;
            });
    }

    function enviarTextoExtraido(endpoint, texto) {
        return enviarFormulario(endpoint, function (formData) {
            formData.append('texto_extraido', texto);
        });
    }

    function enviarArchivoComprobante(endpoint, file) {
        return enviarFormulario(endpoint, function (formData) {
            formData.append('comprobante', file);
        });
    }

    function textoDe(resultado) {
        return (resultado && resultado.data && resultado.data.text) || '';
    }

    /**
     * Determina si hace falta la pasada de refuerzo: la lectura base no produjo
     * texto analizable o no reconoció el monto. La pasada de refuerzo (Sauvola)
     * está pensada para recuperar importes de capturas en modo oscuro; no aporta
     * fecha ni referencia confiables, por lo que no dispara por esos campos.
     * Nunca reintenta cuando el fallo fue por límite de solicitudes.
     */
    function necesitaRefuerzo(datos) {
        if (!datos) { return false; }
        if (!datos.success) {
            return String(datos.error || '').indexOf('Demasiadas') === -1;
        }
        return !datos.monto;
    }

    /**
     * Combina ambas respuestas rellenando con la pasada de refuerzo únicamente
     * los campos que la pasada base no logró detectar.
     */
    function combinarExtracciones(datosBase, datosRefuerzo) {
        var claves = ['monto', 'fecha_pago', 'metodo_pago', 'banco_pagador', 'banco_receptor', 'referencia', 'cuenta_bancaria_id'];
        var combinado = {};
        Object.keys(datosBase).forEach(function (clave) {
            combinado[clave] = datosBase[clave];
        });
        combinado.confianza = Object.assign({}, datosBase.confianza || {});
        combinado.inconsistencias = Object.assign({}, datosBase.inconsistencias || {});

        claves.forEach(function (clave) {
            var actual = combinado[clave];
            var alterno = datosRefuerzo[clave];
            var vacio = actual === null || actual === undefined || actual === '';
            if (vacio && alterno !== null && alterno !== undefined && alterno !== '') {
                combinado[clave] = alterno;
                if (datosRefuerzo.confianza && datosRefuerzo.confianza[clave] !== undefined) {
                    combinado.confianza[clave] = datosRefuerzo.confianza[clave];
                }
                if (datosRefuerzo.inconsistencias && datosRefuerzo.inconsistencias[clave] !== undefined) {
                    combinado.inconsistencias[clave] = datosRefuerzo.inconsistencias[clave];
                }
            }
        });
        if (combinado.cuenta_destino_valida === undefined && datosRefuerzo.cuenta_destino_valida !== undefined) {
            combinado.cuenta_destino_valida = datosRefuerzo.cuenta_destino_valida;
        }
        return combinado;
    }

    async function procesar(file, opciones) {
        var opts = opciones || {};
        var endpoint = opts.endpoint || '/pago-directo/extraer';
        var onEstado = typeof opts.onEstado === 'function' ? opts.onEstado : function () {};

        if (typeof Tesseract === 'undefined') {
            throw new Error('Librería OCR no disponible. Verifique su conexión.');
        }

        // Los PDF se analizan directamente en el servidor (streams nativos).
        if (!((file.type || '').indexOf('image/') === 0)) {
            onEstado('pdf');
            var datosPdf = await enviarArchivoComprobante(endpoint, file);
            return { datos: datosPdf, error: null };
        }

        var trabajador = null;
        try {
            trabajador = await Tesseract.createWorker('spa', 1, {
                logger: function (m) {
                    if (!m) { return; }
                    if (m.status === 'recognizing text') {
                        onEstado('leyendo', Math.round((m.progress || 0) * 100));
                    } else if (m.status === 'loading tesseract core' || m.status === 'initializing tesseract') {
                        onEstado('motor');
                    }
                }
            });

            // Pasada base
            var lecturaBase = await trabajador.recognize(file);
            onEstado('analizando');
            var datos = await enviarTextoExtraido(endpoint, textoDe(lecturaBase));

            // Pasada de refuerzo (best-effort)
            if (necesitaRefuerzo(datos)) {
                try {
                    await trabajador.setParameters({ thresholding_method: '2' });
                    onEstado('refuerzo');
                    var lecturaRefuerzo = await trabajador.recognize(file);
                    var textoRefuerzo = textoDe(lecturaRefuerzo);
                    if (String(textoRefuerzo).trim() !== '') {
                        var datosRefuerzo = await enviarTextoExtraido(endpoint, textoRefuerzo);
                        if (datosRefuerzo && datosRefuerzo.success) {
                            datos = combinarExtracciones(datos, datosRefuerzo);
                        }
                    }
                } catch (e) {
                    // Si el refuerzo falla, se conserva lo detectado en la primera pasada.
                }
            }

            return { datos: datos, error: null };
        } finally {
            if (trabajador) {
                try { await trabajador.terminate(); } catch (e) { /* sin acción */ }
            }
        }
    }

    window.ComprobanteOCR = { procesar: procesar };
})();
