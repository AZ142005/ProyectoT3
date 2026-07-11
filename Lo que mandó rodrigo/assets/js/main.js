/**
 * SISTEMA DE VERIFICACION DE PAGOS
 * Archivo principal de JavaScript
 */

// ============================================
// 1. CONFIGURACION GLOBAL
// ============================================

const CONFIG = {
    API_URL: 'api/',
    CURRENCY: 'VES',
    DATE_FORMAT: 'DD/MM/YYYY'
};

// ============================================
// 2. UTILIDADES
// ============================================

/**
 * Formatear numero como moneda venezolana
 */
function formatearMoneda(valor) {
    if (isNaN(valor) || valor === null || valor === undefined) {
        return 'Bs. 0,00';
    }
    return new Intl.NumberFormat('es-VE', {
        style: 'currency',
        currency: 'VES',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(valor);
}

/**
 * Formatear fecha
 */
function formatearFecha(fecha) {
    if (!fecha) return '-';
    const d = new Date(fecha);
    if (isNaN(d.getTime())) return '-';
    
    const dia = String(d.getDate()).padStart(2, '0');
    const mes = String(d.getMonth() + 1).padStart(2, '0');
    const anio = d.getFullYear();
    
    return dia + '/' + mes + '/' + anio;
}

/**
 * Obtener fecha actual
 */
function fechaActual() {
    return new Date().toISOString().split('T')[0];
}

/**
 * Validar email
 */
function validarEmail(email) {
    var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Validar cedula venezolana
 */
function validarCedula(cedula) {
    var regex = /^[VEve]?\d{7,8}$/;
    return regex.test(cedula);
}

/**
 * Validar telefono venezolano
 */
function validarTelefono(telefono) {
    var regex = /^(0?4(1|2|4|6)\d{7})$/;
    return regex.test(telefono);
}

// ============================================
// 3. MANEJO DE MENSAJES
// ============================================

/**
 * Mostrar mensaje de notificacion
 */
function mostrarMensaje(mensaje, tipo) {
    tipo = tipo || 'info';
    
    var container = document.getElementById('mensajes');
    if (!container) {
        container = document.createElement('div');
        container.id = 'mensajes';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;max-width:400px;width:100%;';
        document.body.appendChild(container);
    }
    
    var colores = {
        success: { bg: '#d4edda', color: '#155724', icon: '[OK]' },
        error: { bg: '#f8d7da', color: '#721c24', icon: '[X]' },
        warning: { bg: '#fff3cd', color: '#856404', icon: '[!]' },
        info: { bg: '#d1ecf1', color: '#0c5460', icon: '[i]' }
    };
    
    var estilo = colores[tipo] || colores.info;
    
    var alert = document.createElement('div');
    alert.style.cssText = 
        'background:' + estilo.bg + ';' +
        'color:' + estilo.color + ';' +
        'padding:12px 20px;' +
        'border-radius:8px;' +
        'margin-bottom:10px;' +
        'box-shadow:0 4px 12px rgba(0,0,0,0.15);' +
        'border-left:4px solid ' + estilo.color + ';' +
        'display:flex;' +
        'align-items:center;' +
        'gap:10px;' +
        'animation:slideIn 0.3s ease;';
    
    alert.innerHTML = 
        '<span style="font-size:18px;">' + estilo.icon + '</span>' +
        '<span style="flex:1;font-size:14px;">' + mensaje + '</span>' +
        '<button onclick="this.parentElement.remove()" style="' +
        'background:none;border:none;font-size:18px;cursor:pointer;color:' + estilo.color + ';opacity:0.7;">&times;</button>';
    
    container.appendChild(alert);
    
    setTimeout(function() {
        if (alert.parentElement) {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s ease';
            setTimeout(function() { alert.remove(); }, 300);
        }
    }, 5000);
}

function mostrarExito(mensaje) { mostrarMensaje(mensaje, 'success'); }
function mostrarError(mensaje) { mostrarMensaje(mensaje, 'error'); }
function mostrarAdvertencia(mensaje) { mostrarMensaje(mensaje, 'warning'); }
function mostrarInfo(mensaje) { mostrarMensaje(mensaje, 'info'); }

// ============================================
// 4. MANEJO DE MODALES
// ============================================

function abrirModal(modalId) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    modal.style.display = 'flex';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function cerrarModal(modalId) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    modal.style.display = 'none';
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

// Cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var modales = document.querySelectorAll('.modal:not(.hidden)');
        modales.forEach(function(modal) {
            cerrarModal(modal.id);
        });
    }
});

// Cerrar modal al hacer clic fuera
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        cerrarModal(e.target.id);
    }
});

// ============================================
// 5. MANEJO DE TABLAS
// ============================================

function filtrarTabla(tablaId, filtro) {
    var tabla = document.getElementById(tablaId);
    if (!tabla) return;
    
    var rows = tabla.querySelectorAll('tbody tr');
    var busqueda = filtro.toLowerCase().trim();
    
    rows.forEach(function(row) {
        var texto = row.textContent.toLowerCase();
        row.style.display = texto.includes(busqueda) ? '' : 'none';
    });
}

function ordenarTabla(tablaId, columna) {
    var tabla = document.getElementById(tablaId);
    if (!tabla) return;
    
    var tbody = tabla.querySelector('tbody');
    var rows = Array.from(tbody.querySelectorAll('tr'));
    var isAsc = tabla.dataset.order === 'asc';
    
    rows.sort(function(a, b) {
        var aVal = a.children[columna]?.textContent?.trim() || '';
        var bVal = b.children[columna]?.textContent?.trim() || '';
        
        var aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
        var bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAsc ? aNum - bNum : bNum - aNum;
        }
        return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    });
    
    tabla.dataset.order = isAsc ? 'desc' : 'asc';
    rows.forEach(function(row) { tbody.appendChild(row); });
}

// ============================================
// 6. MANEJO DE FORMULARIOS
// ============================================

function validarFormulario(formId) {
    var form = document.getElementById(formId);
    if (!form) return true;
    
    var inputs = form.querySelectorAll('[required]');
    var valid = true;
    
    inputs.forEach(function(input) {
        if (!input.value.trim()) {
            input.classList.add('error');
            valid = false;
        } else {
            input.classList.remove('error');
        }
    });
    
    return valid;
}

function limpiarFormulario(formId) {
    var form = document.getElementById(formId);
    if (!form) return;
    form.reset();
    form.querySelectorAll('.error').forEach(function(el) {
        el.classList.remove('error');
    });
}

function serializarFormulario(formId) {
    var form = document.getElementById(formId);
    if (!form) return {};
    
    var formData = new FormData(form);
    var data = {};
    for (var [key, value] of formData.entries()) {
        data[key] = value;
    }
    return data;
}

// ============================================
// 7. PETICIONES AJAX
// ============================================

async function getData(endpoint, params) {
    params = params || {};
    try {
        var url = CONFIG.API_URL + endpoint;
        var queryString = new URLSearchParams(params).toString();
        if (queryString) url += '?' + queryString;
        
        var response = await fetch(url);
        if (!response.ok) throw new Error('Error HTTP: ' + response.status);
        return await response.json();
    } catch (error) {
        mostrarError('Error al obtener datos: ' + error.message);
        throw error;
    }
}

async function postData(endpoint, data) {
    data = data || {};
    try {
        var response = await fetch(CONFIG.API_URL + endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        if (!response.ok) throw new Error('Error HTTP: ' + response.status);
        return await response.json();
    } catch (error) {
        mostrarError('Error al enviar datos: ' + error.message);
        throw error;
    }
}

async function postFormData(endpoint, formData) {
    try {
        var response = await fetch(CONFIG.API_URL + endpoint, {
            method: 'POST',
            body: formData
        });
        if (!response.ok) throw new Error('Error HTTP: ' + response.status);
        return await response.json();
    } catch (error) {
        mostrarError('Error al enviar datos: ' + error.message);
        throw error;
    }
}

// ============================================
// 8. INICIALIZACION
// ============================================

function inicializarSistema() {
    console.log('Sistema de Verificacion de Pagos');
    console.log('Fecha:', new Date().toLocaleString('es-VE'));
    
    // Configurar fecha actual en inputs
    document.querySelectorAll('input[type="date"].fecha-actual').forEach(function(input) {
        if (!input.value) {
            input.value = fechaActual();
        }
    });
    
    // Configurar filtros en tablas
    document.querySelectorAll('[data-filtro-tabla]').forEach(function(input) {
        var tablaId = input.dataset.filtroTabla;
        input.addEventListener('input', function() {
            filtrarTabla(tablaId, this.value);
        });
    });
    
    // Configurar botones de cerrar modal
    document.querySelectorAll('.btn-cerrar-modal, .modal-close').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var modalId = this.dataset.modal || this.closest('.modal')?.id;
            if (modalId) cerrarModal(modalId);
        });
    });
    
    // Configurar formularios con data-ajax
    document.querySelectorAll('form[data-ajax]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (validarFormulario(this.id)) {
                if (typeof onSubmitForm === 'function') {
                    onSubmitForm(this);
                }
            }
        });
    });
}

// ============================================
// 9. ESTILOS DINAMICOS
// ============================================

var estilos = document.createElement('style');
estilos.textContent = 
    '@keyframes slideIn {' +
    '  from { opacity:0; transform:translateX(100px); }' +
    '  to { opacity:1; transform:translateX(0); }' +
    '}' +
    '.modal {' +
    '  position:fixed; top:0; left:0; width:100%; height:100%;' +
    '  background:rgba(0,0,0,0.5); display:none;' +
    '  align-items:center; justify-content:center; z-index:9998;' +
    '}' +
    '.modal-content {' +
    '  background:white; border-radius:8px; padding:30px;' +
    '  max-width:500px; width:90%; max-height:90vh; overflow-y:auto;' +
    '}' +
    '.modal-header {' +
    '  display:flex; justify-content:space-between; align-items:center;' +
    '  margin-bottom:20px; padding-bottom:10px; border-bottom:1px solid #eee;' +
    '}' +
    '.hidden { display:none !important; }' +
    'input.error, select.error, textarea.error {' +
    '  border-color:#e74c3c !important;' +
    '  box-shadow:0 0 0 3px rgba(231,76,60,0.1) !important;' +
    '}';

document.head.appendChild(estilos);

// ============================================
// 10. INICIALIZACION AUTOMATICA
// ============================================

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarSistema);
} else {
    inicializarSistema();
}

// ============================================
// 11. EXPORTAR FUNCIONES (para uso global)
// ============================================

window.formatearMoneda = formatearMoneda;
window.formatearFecha = formatearFecha;
window.fechaActual = fechaActual;
window.validarEmail = validarEmail;
window.validarCedula = validarCedula;
window.validarTelefono = validarTelefono;
window.mostrarMensaje = mostrarMensaje;
window.mostrarExito = mostrarExito;
window.mostrarError = mostrarError;
window.mostrarAdvertencia = mostrarAdvertencia;
window.mostrarInfo = mostrarInfo;
window.abrirModal = abrirModal;
window.cerrarModal = cerrarModal;
window.filtrarTabla = filtrarTabla;
window.ordenarTabla = ordenarTabla;
window.validarFormulario = validarFormulario;
window.limpiarFormulario = limpiarFormulario;
window.serializarFormulario = serializarFormulario;
window.getData = getData;
window.postData = postData;
window.postFormData = postFormData;