// docentes-handler.js

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
    
    console.log('Recargando tabla de docentes, curso ID:', cursoId); // Debug
    
    if (!cursoId) {
        console.error('No se encontró el ID del curso');
        return Promise.reject('No se encontró el ID del curso');
    }
    
    // Buscar la tabla específicamente dentro del contenedor de docentes
    const docentesContainer = document.getElementById('docentes-list');
    if (!docentesContainer) {
        console.error('No se encontró el contenedor de docentes');
        return Promise.reject('No se encontró el contenedor de docentes');
    }
    
    return fetch('get_docentes_table.php?idcurso=' + cursoId)
        .then(response => response.text())
        .then(html => {
            // Buscar el tbody específicamente dentro del contenedor de docentes
            const tableBody = docentesContainer.querySelector('table tbody');
            if (tableBody) {
                console.log('Actualizando tbody de la tabla'); // Debug
                tableBody.innerHTML = html;
                return true; // Éxito
            } else {
                console.error('No se encontró el tbody de la tabla');
                // Intentar recargar todo el contenedor
                $('#docente-tab').click();
                return false;
            }
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
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar toast de procesando
            showNotification('Actualizando lista de docentes...', 'primary', true);
            
            // Llamar a la función de recarga y esperar que termine
            reloadDocentesTable()
                .then(() => {
                    // Mostrar éxito cuando la tabla se haya actualizado
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
        showNotification('Error al procesar la solicitud', 'danger');
        console.error('Error:', error);
    });
}

function initializeNewDocenteForm() {
    // Inicializar validación de RUT
    const rutInput = document.getElementById('rut_docente');
    if (rutInput) {
        rutInput.addEventListener('input', function() {
            checkRut(this);
        });
    }

    // Manejar el envío del formulario
    const form = document.getElementById('nuevo-docente-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            guardarNuevoDocente();
        });
    }
}

// Hacer las funciones accesibles globalmente
window.reloadDocentesTable = reloadDocentesTable;
window.showNotification = showNotification;
window.guardarNuevoDocente = guardarNuevoDocente;
window.initializeNewDocenteForm = initializeNewDocenteForm;

// Código que se ejecuta cuando el DOM está listo
document.addEventListener('DOMContentLoaded', function() {
    function initializeDocenteSelect() {
        const docenteSelect = $('#docente');
        
        if (!docenteSelect.length) return;

        // Destruir instancia previa si existe
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

        // Manejar el cambio de selección
        docenteSelect.on('change', function() {
            $('#boton_agregar').prop('disabled', !$(this).val());
        });
    }
    
    // Agregar evento al boton de asignar docente
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

        $.post('asignar_docente.php', {
            rut_docente: rutDocente,
            idcurso: cursoId,
            funcion: '4'
        })
        .done(function(response) {
            // Mostrar toast de procesando
            showNotification('Actualizando lista de docentes...', 'primary', true);
            
            // Llamar a la función de recarga y esperar que termine
            reloadDocentesTable()
                .then(() => {
                    // Mostrar éxito cuando la tabla se haya actualizado
                    showNotification('Docente asignado correctamente', 'success');
                })
                .catch(() => {
                    showNotification('Docente asignado, pero hubo un problema al actualizar la vista', 'warning');
                })
                .finally(() => {
                    // Resetear el select2
                    $('#docente').val(null).trigger('change');
                    // Restaurar el botón
                    $button.prop('disabled', false)
                           .html('<i class="bi bi-plus-circle"></i> Asignar Docente');
                });
        })
        .fail(function(xhr, status, error) {
            console.error('Error al asignar docente:', error);
            showNotification('Error al asignar docente', 'danger');
            $button.prop('disabled', false)
                   .html('<i class="bi bi-plus-circle"></i> Asignar Docente');
        });
    });

    // Manejar la carga del formulario de nuevo docente
    $(document).on('click', '#nuevo-docente-btn', function(e) {
        e.preventDefault();
        const docentesList = $('#docentes-list');
        
        // Mostrar indicador de carga
        docentesList.html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>');
        
        // Obtener el ID del curso de la URL
        const urlParams = new URLSearchParams(window.location.search);
        const cursoId = urlParams.get('curso');
        
        // Cargar el formulario de nuevo docente
        fetch('2_crear_docente.php?idcurso=' + cursoId)
            .then(response => response.text())
            .then(html => {
                docentesList.html(html);
                initializeNewDocenteForm();
            })
            .catch(error => {
                docentesList.html('<div class="alert alert-danger">Error al cargar el formulario</div>');
                console.error('Error:', error);
            });
    });

    // Inicializar Select2 cuando se muestra el tab
    $('#docente-tab').on('shown.bs.tab', function(e) {
        console.log('Tab docente mostrado'); // Debug
        setTimeout(initializeDocenteSelect, 100);
    });

    // Inicializar inmediatamente si estamos en la pestaña de docentes
    if ($('#docente-tab').hasClass('active')) {
        initializeDocenteSelect();
    }

    // Observar cambios en el contenedor de docentes
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
    
    // Hacer también initializeDocenteSelect disponible globalmente
    window.initializeDocenteSelect = initializeDocenteSelect;
});