// docentes-handler.js
//document.addEventListener('DOMContentLoaded', function() {
//    // Manejar la carga del formulario de nuevo docente
// document.addEventListener('click', function(e) {
//        if (e.target && e.target.id === 'nuevo-docente-btn' || 
//            (e.target.parentElement && e.target.parentElement.id === 'nuevo-docente-btn')) {
//             console.log('Bot贸n nuevo docente clickeado');
//            const docentesList = document.getElementById('docentes-list');
//            
//            // Mostrar indicador de carga
//            docentesList.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
//            
//            // Obtener el ID del curso de la URL
//            const urlParams = new URLSearchParams(window.location.search);
//            const cursoId = urlParams.get('curso');
//            
//            // Cargar el formulario de nuevo docente
//            fetch('2_crear_docente.php?idcurso=' + cursoId)
//                .then(response => response.text())
//                .then(html => {
//                    docentesList.innerHTML = html;
//                    initializeNewDocenteForm();
//                })
//                .catch(error => {
//                    docentesList.innerHTML = '<div class="alert alert-danger">Error al cargar el formulario</div>';
//                });
//        }
//    });
//});

// Archivo: docentes-handler.js
//document.addEventListener('DOMContentLoaded', function() {
//    // Funci贸n para inicializar Select2 en el selector de docentes
//    function initializeDocenteSelect() {
//        if ($('#docente').length) {
//            // Destruir instancia previa si existe
//            if ($('#docente').hasClass('select2-hidden-accessible')) {
//                $('#docente').select2('destroy');
//            }
//            
//            // Inicializar Select2 con configuraci贸n
//            $('#docente').select2({
//                theme: 'bootstrap-5',
//                placeholder: '馃攳 Buscar Docente',
//                allowClear: true,
//                width: '100%',
//                language: {
//                    noResults: function() {
//                        return "No se encontraron docentes";
//                    },
//                    searching: function() {
//                        return "Buscando...";
//                    }
//                },
//                // Asegurar que el dropdown se renderice en el contenedor correcto
//                dropdownParent: $('#bordered-justified-docente'),
//                // Mejorar el rendimiento con datos grandes
//                minimumInputLength: 2,
//                minimumResultsForSearch: 10,
//                maximumSelectionLength: 1
//            });
//
//            // Manejar el cambio de selecci贸n
//            $('#docente').on('change', function() {
//                $('#boton_agregar').prop('disabled', !$(this).val());
//            });
//        }
//    }
//
//    // Inicializar cuando se muestra el tab de docentes
//    $('#docente-tab').on('shown.bs.tab', function(e) {
//        setTimeout(initializeDocenteSelect, 100);
//    });
//    
//    // Re-inicializar Select2 cuando el contenido del tab se actualiza
//    const observer = new MutationObserver(function(mutations) {
//        mutations.forEach(function(mutation) {
//            if (mutation.addedNodes.length) {
//                const docenteSelect = document.getElementById('docente');
//                if (docenteSelect && !$(docenteSelect).hasClass('select2-hidden-accessible')) {
//                    initializeDocenteSelect();
//                }
//            }
//        });
//    });
//
//    // Observar cambios en el contenedor de docentes
//    const docentesContainer = document.getElementById('docentes-list');
//    if (docentesContainer) {
//        observer.observe(docentesContainer, {
//            childList: true,
//            subtree: true
//        });
//    }
//});

// Archivo: docentes-handler.js
document.addEventListener('DOMContentLoaded', function() {
    function initializeDocenteSelect() {
        const docenteSelect = $('#docente');
        
        if (!docenteSelect.length) return;

        // Destruir instancia previa si existe
        if (docenteSelect.hasClass('select2-hidden-accessible')) {
            docenteSelect.select2('destroy');
        }
        
        // Configuraci贸n del Select2
        docenteSelect.select2({
            theme: 'bootstrap-5',
            placeholder: '馃攳 Buscar Docente',
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
            dropdownParent: docenteSelect.parent(), // Cambio importante aqu铆
            minimumInputLength: 1, // Reducido a 1 para mejor usabilidad
            minimumResultsForSearch: 0, // Permitir b煤squeda inmediata
            maximumSelectionSize: 1 // Cambiado de maximumSelectionLength a maximumSelectionSize
        });

        // Manejar el cambio de selecci贸n
        docenteSelect.on('change', function() {
            $('#boton_agregar').prop('disabled', !$(this).val());
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

    // Inicializar inmediatamente si estamos en la pesta帽a de docentes
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
    // Inicializar validaci贸n de RUT
    const rutInput = document.getElementById('rut_docente');
    if (rutInput) {
        rutInput.addEventListener('input', function() {
            checkRut(this);
        });
    }

    // Manejar el env铆o del formulario
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
            document.getElementById('docente-tab').click();
        } else {
            showNotification(data.message || 'Error al guardar el docente', 'danger');
        }
    })
    .catch(error => {
        showNotification('Error al procesar la solicitud', 'danger');
    });
}

function showNotification(message, type) {
    const toast = `
        <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    const toastContainer = document.querySelector('.toast-container');
    toastContainer.innerHTML = toast;
    
    const toastElement = new bootstrap.Toast(toastContainer.querySelector('.toast'));
    toastElement.show();
}