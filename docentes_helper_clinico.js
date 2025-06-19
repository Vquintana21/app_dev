// docentes-handler.js - Versión mejorada con mejor manejo de errores

// Funciones globales

function showNotification(message, type, showSpinner = false) {
    const toastId = 'toast-' + Date.now();
    
    let iconContent = '';
    if (showSpinner) {
        iconContent = '<div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Cargando...</span></div>';
    } else {
        iconContent = `<i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'x-circle' : 'info-circle'} me-2"></i>`;
    }
    
    const toast = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0">
            <div class="d-flex">
                <div class="toast-body">
                    ${iconContent}
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    $('.toast-container').html(toast);
    const toastElement = new bootstrap.Toast($('.toast').last());
    toastElement.show();
    
    return toastId;
}

function reloadDocentesTable() {
    const urlParams = new URLSearchParams(window.location.search);
    const cursoId = urlParams.get('curso');
    
    console.log('Recargando tabla de docentes, curso ID:', cursoId);
    
    if (!cursoId) {
        console.error('No se encontró el ID del curso');
        return Promise.reject('No se encontró el ID del curso');
    }
    
    const docentesContainer = document.getElementById('docentes-list');
    if (!docentesContainer) {
        console.error('No se encontró el contenedor de docentes');
        return Promise.reject('No se encontró el contenedor de docentes');
    }
    
    // ? CAMBIO AQUÍ: Usar el archivo correcto para cursos clínicos
    return fetch('get_docentes_table_clinico.php?idcurso=' + cursoId)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            // ? CAMBIO AQUÍ: Reemplazar todo el contenedor, no solo el tbody
            console.log('Actualizando contenedor completo de docentes');
            docentesContainer.innerHTML = html;
            
            // ? AGREGAR: Reinicializar funcionalidades después del reemplazo
            setTimeout(() => {
                if (typeof setupHorasDirectasClinico === 'function') {
                    setupHorasDirectasClinico();
                    console.log('? Horas directas reinicializadas');
                }
                
                if (typeof inicializarBusquedaDocentesClinico === 'function') {
                    inicializarBusquedaDocentesClinico();
                    console.log('? Búsqueda de docentes reinicializada');
                }
                
                // Reinicializar el select2 si existe
                if (typeof window.initializeDocenteSelect === 'function') {
                    window.initializeDocenteSelect();
                    console.log('? Select2 reinicializado');
                }
            }, 300);
            
            return true;
        })
        .catch(error => {
            console.error('Error al recargar la tabla:', error);
            throw error;
        });
}

function guardarNuevoDocente() {
    const formData = new FormData(document.getElementById('nuevo-docente-form'));
    
    fetch('guardar_docente.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            return response.text().then(text => {
                console.error('Respuesta no es JSON:', text);
                throw new Error('La respuesta del servidor no es JSON válido');
            });
        }
    })
    .then(data => {
        if (data.success) {
            showNotification('Actualizando lista de docentes...', 'primary', true);
            
            reloadDocentesTable()
                .then(() => {
                    showNotification('Docente agregado correctamente', 'success');
                })
                .catch(() => {
                    showNotification('Docente agregado, pero hubo un problema al actualizar la vista', 'warning');
                });
        } else {
            showNotification(data.message || 'Error al guardar el docente', 'danger');
        }
    })
    .catch(error => {
        showNotification('Error al procesar la solicitud: ' + error.message, 'danger');
        console.error('Error:', error);
    });
}

function initializeNewDocenteForm() {
    const rutInput = document.getElementById('rut_docente');
    if (rutInput) {
        rutInput.addEventListener('input', function() {
            checkRut(this);
        });
    }

    const form = document.getElementById('nuevo-docente-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            guardarNuevoDocente();
        });
    }
}

// Función mejorada para asignar docente
function asignarDocente(rutDocente, cursoId) {
    return new Promise((resolve, reject) => {
        // Validar parámetros
        if (!rutDocente || !cursoId) {
            reject(new Error('Faltan parámetros requeridos'));
            return;
        }

        // Configurar datos
        const formData = new FormData();
        formData.append('rut_docente', rutDocente);
        formData.append('idcurso', cursoId);
        formData.append('funcion', '4');

        // Realizar petición usando fetch en lugar de jQuery
        fetch('asignar_docente.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Verificar el tipo de contenido
            const contentType = response.headers.get('content-type');
            console.log('Content-Type:', contentType);
            
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                // Si no es JSON, obtener el texto para depuración
                return response.text().then(text => {
                    console.error('Respuesta del servidor (no JSON):', text);
                    // Intentar parsear como JSON de todos modos
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(`Respuesta no es JSON válido. Contenido: ${text.substring(0, 200)}...`);
                    }
                });
            }
        })
        .then(data => {
            console.log('Datos recibidos:', data);
            if (data && data.success) {
                resolve(data);
            } else {
                reject(new Error(data?.message || 'Error desconocido en la respuesta del servidor'));
            }
        })
        .catch(error => {
            console.error('Error en asignarDocente:', error);
            reject(error);
        });
    });
}

// Hacer las funciones accesibles globalmente
window.reloadDocentesTable = reloadDocentesTable;
window.showNotification = showNotification;
window.guardarNuevoDocente = guardarNuevoDocente;
window.initializeNewDocenteForm = initializeNewDocenteForm;
window.asignarDocente = asignarDocente;

// Código que se ejecuta cuando el DOM está listo
document.addEventListener('DOMContentLoaded', function() {
    function initializeDocenteSelect() {
        const docenteSelect = $('#docente');
        
        if (!docenteSelect.length) return;

        if (docenteSelect.hasClass('select2-hidden-accessible')) {
            docenteSelect.select2('destroy');
        }
        
        docenteSelect.select2({
            theme: 'bootstrap-5',
            placeholder: 'Escriba nombre o RUT para buscar',
            allowClear: true,
            width: '100%',
            minimumInputLength: 3,
            ajax: {
                url: 'buscar_docentes_ajax.php',
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        q: params.term,
                        page: params.page || 1
                    };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.items,
                        pagination: {
                            more: data.pagination.more
                        }
                    };
                },
                cache: true
            },
            language: {
                searching: function() { return "Buscando..."; },
                noResults: function() { return "No se encontraron docentes"; },
                inputTooShort: function() { return 'Escriba al menos 3 caracteres para buscar'; },
                loadingMore: function() { return 'Cargando más resultados...'; },
                errorLoading: function() { return 'Error al cargar los resultados'; }
            }
        });

        docenteSelect.on('change', function() {
            $('#boton_agregar').prop('disabled', !$(this).val());
        });
    }
    
    // EVENTO MEJORADO para asignar docente
    // Reemplaza el evento del botón asignar en tu docentes-handler.js

$(document).off('click', '#boton_agregar').on('click', '#boton_agregar', function() {
    const rutDocente = $('#docente').val();
    const cursoId = new URLSearchParams(window.location.search).get('curso');

    if (!rutDocente || !cursoId) {
        showNotification('Por favor seleccione un docente', 'danger');
        return;
    }

    const $button = $(this);
    $button.prop('disabled', true)
           .html('<span class="spinner-border spinner-border-sm"></span> Asignando...');

    // Usar jQuery AJAX con mejor manejo de errores
    $.ajax({
        url: 'asignar_docente.php',
        type: 'POST',
        dataType: 'json', // Especificar que esperamos JSON
        data: {
            rut_docente: rutDocente,
            idcurso: cursoId,
            funcion: '4'
        },
        timeout: 10000, // 10 segundos de timeout
        beforeSend: function() {
            console.log('Enviando datos:', {
                rut_docente: rutDocente,
                idcurso: cursoId,
                funcion: '4'
            });
        }
    })
    .done(function(response, textStatus, xhr) {
        console.log('Respuesta recibida:', response);
        console.log('Status:', xhr.status);
        console.log('Content-Type:', xhr.getResponseHeader('Content-Type'));
        
        if (response && response.success) {
            showNotification('Actualizando lista de docentes...', 'primary', true);
            
            reloadDocentesTable()
                .then(() => {
                    showNotification('Docente asignado correctamente', 'success');
                })
                .catch(() => {
                    showNotification('Docente asignado, pero hubo un problema al actualizar la vista', 'warning');
                });
        } else {
            const mensaje = response && response.message ? response.message : 'Error desconocido al asignar docente';
            showNotification(mensaje, 'danger');
        }
    })
    .fail(function(xhr, status, error) {
        console.error('Error completo:', {
            status: status,
            error: error,
            responseText: xhr.responseText,
            statusCode: xhr.status,
            contentType: xhr.getResponseHeader('Content-Type')
        });
        
        let mensaje = 'Error al asignar docente';
        
        if (xhr.responseText) {
            try {
                const errorResponse = JSON.parse(xhr.responseText);
                mensaje = errorResponse.message || mensaje;
            } catch (e) {
                console.error('Respuesta no es JSON válido:', xhr.responseText.substring(0, 200));
                mensaje = 'Error del servidor: Respuesta inválida';
            }
        } else if (status === 'timeout') {
            mensaje = 'Tiempo de espera agotado. Intente nuevamente.';
        } else if (status === 'error') {
            mensaje = 'Error de conexión con el servidor';
        }
        
        showNotification(mensaje, 'danger');
    })
    .always(function() {
        // Restaurar el botón sin importar el resultado
        $('#docente').val(null).trigger('change');
        $button.prop('disabled', false)
               .html('<i class="bi bi-plus-circle"></i> Asignar Docente');
    });
});

    // Manejar la carga del formulario de nuevo docente
    $(document).on('click', '#nuevo-docente-btn', function(e) {
        e.preventDefault();
        const docentesList = $('#docentes-list');
        
        docentesList.html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>');
        
        const urlParams = new URLSearchParams(window.location.search);
        const cursoId = urlParams.get('curso');
        
        fetch('2_crear_docente.php?idcurso=' + cursoId)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(html => {
                docentesList.html(html);
                initializeNewDocenteForm();
            })
            .catch(error => {
                docentesList.html('<div class="alert alert-danger">Error al cargar el formulario: ' + error.message + '</div>');
                console.error('Error:', error);
            });
    });

    $('#docente-tab').on('shown.bs.tab', function(e) {
        console.log('Tab docente mostrado');
        setTimeout(initializeDocenteSelect, 100);
    });

    if ($('#docente-tab').hasClass('active')) {
        initializeDocenteSelect();
    }

    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                if (document.getElementById('docente') && 
                    !$('#docente').hasClass('select2-hidden-accessible')) {
                    initializeDocenteSelect();
                }
            }
        });
    });

    const docentesContainer = document.getElementById('docentes-list');
    if (docentesContainer) {
        observer.observe(docentesContainer, {
            childList: true,
            subtree: true
        });
    }
    
    window.initializeDocenteSelect = initializeDocenteSelect;
});