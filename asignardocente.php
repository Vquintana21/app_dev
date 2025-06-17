<?php
// test_asignar_docente.php - Script temporal para probar el archivo asignar_docente.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Asignar Docente</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, button { padding: 8px; margin: 5px 0; }
        .result { 
            margin-top: 20px; 
            padding: 15px; 
            border-radius: 5px; 
        }
        .success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background-color: #e2e3e5; border: 1px solid #d6d8db; color: #383d41; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow: auto; }
        .debug { font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <h1>🔧 Test de asignar_docente.php</h1>
    
    <form id="testForm">
        <div class="form-group">
            <label for="rut_docente">RUT Docente:</label>
            <input type="text" id="rut_docente" name="rut_docente" placeholder="Ej: 12345678" required>
        </div>
        
        <div class="form-group">
            <label for="idcurso">ID Curso:</label>
            <input type="number" id="idcurso" name="idcurso" placeholder="Ej: 8924" required>
        </div>
        
        <div class="form-group">
            <label for="funcion">Función:</label>
            <input type="number" id="funcion" name="funcion" value="4" required>
        </div>
        
        <button type="submit">🚀 Probar Asignación</button>
        <button type="button" onclick="testConnection()">🔌 Probar Conexión</button>
    </form>
    
    <div id="result"></div>
    
    <script>
        document.getElementById('testForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const resultDiv = document.getElementById('result');
            
            resultDiv.innerHTML = '<div class="info">⏳ Enviando petición...</div>';
            
            fetch('asignar_docente.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', [...response.headers.entries()]);
                
                const contentType = response.headers.get('content-type');
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error(`Respuesta no es JSON. Content-Type: ${contentType}. Contenido: ${text.substring(0, 200)}...`);
                    });
                }
            })
            .then(data => {
                console.log('Data received:', data);
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <h3>✅ Éxito</h3>
                            <p><strong>Mensaje:</strong> ${data.message}</p>
                            <h4>Datos:</h4>
                            <pre>${JSON.stringify(data.data, null, 2)}</pre>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="error">
                            <h3>❌ Error</h3>
                            <p><strong>Mensaje:</strong> ${data.message}</p>
                            <h4>Respuesta completa:</h4>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = `
                    <div class="error">
                        <h3>💥 Error en la petición</h3>
                        <p><strong>Error:</strong> ${error.message}</p>
                        <div class="debug">
                            <p>Revisa la consola del navegador para más detalles.</p>
                        </div>
                    </div>
                `;
            });
        });
        
        function testConnection() {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<div class="info">🔌 Probando conexión básica...</div>';
            
            fetch('asignar_docente.php', {
                method: 'GET'
            })
            .then(response => {
                console.log('Connection test response:', response);
                const contentType = response.headers.get('content-type');
                
                resultDiv.innerHTML = `
                    <div class="info">
                        <h3>🔌 Resultado de conexión</h3>
                        <p><strong>Status:</strong> ${response.status} ${response.statusText}</p>
                        <p><strong>Content-Type:</strong> ${contentType}</p>
                        <p><strong>El archivo está accesible y responde.</strong></p>
                    </div>
                `;
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h3>💥 Error de conexión</h3>
                        <p><strong>Error:</strong> ${error.message}</p>
                        <p>El archivo asignar_docente.php no está accesible.</p>
                    </div>
                `;
            });
        }
    </script>
    
    <hr>
    <h2>📋 Instrucciones:</h2>
    <ol>
        <li><strong>Probar Conexión:</strong> Verifica que el archivo sea accesible</li>
        <li><strong>Probar Asignación:</strong> Ingresa datos reales de tu sistema</li>
        <li><strong>Revisar resultado:</strong> Ve si el JSON es válido y qué contiene</li>
        <li><strong>Verificar en BD:</strong> Confirma si los datos se insertaron</li>
    </ol>
    
    <div class="info">
        <h3>🔍 Para depurar:</h3>
        <ul>
            <li>Abre las <strong>Herramientas de Desarrollador</strong> (F12)</li>
            <li>Ve a la pestaña <strong>Console</strong></li>
            <li>Ve a la pestaña <strong>Network</strong> para ver las peticiones HTTP</li>
            <li>Revisa los logs del servidor en caso de errores internos</li>
        </ul>
    </div>
    
    <p class="debug"><strong>Nota:</strong> Elimina este archivo cuando hayas resuelto el problema.</p>
</body>
</html>