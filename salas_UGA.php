<!doctype html>
<?php
include("conexion.php");
?>
<html lang="es">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <style>
        .header-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .table-container {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .capacity-badge {
            font-weight: 600;
            padding: 0.5rem 0.75rem;
        }
        .campus-badge {
            font-size: 0.85rem;
            font-weight: 500;
        }
        .search-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        }
    </style>
    <title>Sistema de Gestión - Salas Norte</title>
</head>
<body class="bg-light">
  

    <!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h2 mb-1 text-dark fw-bold">
                            <i class="bi bi-geo-alt-fill text-primary"></i> Catálogo de Salas Disponibles (Pregrado-UGA)
                        </h1>                       
                    </div>                    
                </div>

                <!-- Search Container -->
                <div class="search-container">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="searchInput" placeholder="Buscar por aula, campus o ubicación...">
                            </div>
                        </div>
                        <div class="col-md-3 mt-2 mt-md-0">
                            <select class="form-select" id="campusFilter">
                                <option value="">Todos los campus</option>
                                <!-- Se llenarán dinámicamente -->
                            </select>
                        </div>
                        <div class="col-md-3 mt-2 mt-md-0">
                            <button class="btn btn-outline-primary w-100" onclick="clearFilters()">
                                <i class="bi bi-arrow-clockwise"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                

                <!-- Table Container -->
                <div class="table-container bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="salasTable">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col" class="text-center">#</th>
                                    <th scope="col">
                                        <i class="bi bi-door-open"></i> Aula
                                    </th>
                                    <th scope="col">
                                        <i class="bi bi-building"></i> Campus
                                    </th>
                                    <th scope="col" class="d-none d-md-table-cell">
                                        <i class="bi bi-geo-alt"></i> Ubicación / Referencias
                                    </th>
                                    <th scope="col" class="text-center">
                                        <i class="bi bi-people"></i> Capacidad
                                    </th>
                                   
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $aulas = "SELECT * FROM sala 
                                     WHERE sa_UnidadAdmin = 'UGA' 
                                     ORDER BY sa_UbicCampus ASC, sa_Nombre ASC";
                            $aulasQ = mysqli_query($conexion5, $aulas);
                            $cont = 1;
                            
                            while($fila = mysqli_fetch_assoc($aulasQ)) {
                                // Determinar color del badge según capacidad
                                $capacidad = (int)$fila["sa_Capacidad"];
                               
                                    $badgeClass = "bg-info text-dark";
                                
                                
                                // Color del campus
                                $campusColors = [
                                    'Norte' => 'primary',
                                    'Sur' => 'success', 
                                    'Centro' => 'info',
                                    'Este' => 'warning',
                                    'Oeste' => 'danger'
                                ];
                         $campusColor = isset($campusColors[$fila["sa_UbicCampus"]]) ? $campusColors[$fila["sa_UbicCampus"]] : 'secondary';

                            ?>
                                <tr class="align-middle">
                                    <td class="text-center fw-bold text-muted"><?php echo $cont; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo utf8_encode($fila["sa_Nombre"]); ?></div>
                                        <small class="text-muted d-md-none">
                                            <?php echo substr(utf8_encode($fila["sa_UbicOtraInf"]), 0, 30) . '...'; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $campusColor; ?> campus-badge">
                                            <?php echo $fila["sa_UbicCampus"]; ?>
                                        </span>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <small class="text-muted">
                                            <?php echo utf8_encode($fila["sa_UbicOtraInf"]); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $badgeClass; ?> capacity-badge">
                                            <?php echo $fila["sa_Capacidad"]; ?>
                                        </span>
                                    </td>                                   
                                </tr>
                            <?php $cont++; } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile Action Button -->
                <div class="d-md-none fixed-bottom p-3">
                    <button class="btn btn-primary w-100 shadow">
                        <i class="bi bi-plus-circle"></i> Nueva Sala
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Funcionalidad de búsqueda en tiempo real
        document.getElementById('searchInput').addEventListener('keyup', function() {
            filterTable();
        });

        document.getElementById('campusFilter').addEventListener('change', function() {
            filterTable();
        });

        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const campusFilter = document.getElementById('campusFilter').value.toLowerCase();
            const table = document.getElementById('salasTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const aula = row.cells[1].textContent.toLowerCase();
                const campus = row.cells[2].textContent.toLowerCase();
                const ubicacion = row.cells[3] ? row.cells[3].textContent.toLowerCase() : '';
                
                const matchesSearch = aula.includes(searchTerm) || 
                                    campus.includes(searchTerm) || 
                                    ubicacion.includes(searchTerm);
                const matchesCampus = campusFilter === '' || campus.includes(campusFilter);

                if (matchesSearch && matchesCampus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('campusFilter').value = '';
            filterTable();
        }

        // Llenar dropdown de campus dinámicamente
        document.addEventListener('DOMContentLoaded', function() {
            const campusSet = new Set();
            const rows = document.getElementById('salasTable').getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const campus = rows[i].cells[2].textContent.trim();
                campusSet.add(campus);
            }
            
            const campusSelect = document.getElementById('campusFilter');
            campusSet.forEach(campus => {
                const option = document.createElement('option');
                option.value = campus;
                option.textContent = campus;
                campusSelect.appendChild(option);
            });
        });

        // Agregar efectos de hover mejorados
        document.querySelectorAll('.table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(5px)';
                this.style.transition = 'transform 0.2s ease';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });
    </script>
</body>
</html>