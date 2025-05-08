// docentes-handler.js
document.addEventListener('DOMContentLoaded', function() {
    function initializeDocenteSelect() {
        const docenteSelect = $('#docente');
        
        if (!docenteSelect.length) return;

        // Destruir instancia previa si existe
        if (docenteSelect.hasClass('select2-hidden-accessible')) {
            docenteSelect.select2('destroy');
        }
        
        // Configuración del Select2
        docenteSelect.select2({
            theme: 'bootstrap-5',
            placeholder: '🔍 Buscar Docente',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function() {
                    return "No se encontraron docentes";
                },
                searching: function() {
                    return "Buscando...";
                },
				inputTooShort: function() {
					return 'Ingrese nombre a buscar ...';
				}
            },
            dropdownParent: docenteSelect.parent(), // Cambio importante aqui
            minimumInputLength: 1, // Reducido a 1 para mejor usabilidad
            minimumResultsForSearch: 0, // Permitir búsqueda inmediata
            maximumSelectionSize: 1 // Cambiado de maximumSelectionLength a maximumSelectionSize
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
    .always(function() {
        showNotification('Docente asignado correctamente', 'success');
        // Agregar el par�metro tab=docente a la URL actual
        //const currentUrl = new URL(window.location.href);
        //currentUrl.searchParams.set('tab', 'docente');
        //setTimeout(() => window.location.href = currentUrl.toString(), 1000);
		
		  reloadDocentesTable();
        // Resetear el select2
        $('#docente').val(null).trigger('change');
        // Restaurar el bot�n
        $button.prop('disabled', false)
               .html('Agregar Docente');
    });
});

function showNotification(message, type) {
    const toast = `
        <div class="toast align-items-center text-white bg-${type} border-0">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : 'x-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    $('.toast-container').html(toast);
    const toastElement = new bootstrap.Toast($('.toast').last());
    toastElement.show();
}

    function reloadDocentesTable() {
        const urlParams = new URLSearchParams(window.location.search);
        const cursoId = urlParams.get('curso');
        
        // Recargar solo la tabla de docentes
        fetch('get_docentes_table.php?idcurso=' + cursoId)
            .then(response => response.text())
            .then(html => {
                const tableBody = document.querySelector('table tbody');
                if (tableBody) {
                    tableBody.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error al recargar la tabla:', error);
            });
    }

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
});


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

function guardarNuevoDocente() {
    const formData = new FormData(document.getElementById('nuevo-docente-form'));
    
    fetch('guardar_docente.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Docente agregado correctamente', 'success');
            // Recargar la lista de docentes
            //document.getElementById('docente-tab').click();
			 reloadDocentesTable();
        } else {
            showNotification(data.message || 'Error al guardar el docente', 'danger');
        }
    })
    .catch(error => {
        showNotification('Error al procesar la solicitud 2', 'danger');
    });
}

