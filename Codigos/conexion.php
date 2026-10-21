<?php
/**
 * ====================================================================
 * ARCHIVO: conexion.php
 * OBJETIVO: Establecer y configurar la conexión con la base de datos
 *           MySQL en el servidor local de XAMPP.
 * ====================================================================
 */

// Parámetros de conexión a la base de datos
$servidor   = "localhost";           // Servidor 
$usuario    = "root";                // Usuario 
$password   = "";         // Contraseña de acceso 
$base_datos = "db_tp_final_kiosco";  // Nombre de la base de datos del proyecto

// Creación de la instancia de conexión mediante la extensión mysqli
$conn = new mysqli($servidor, $usuario, $password, $base_datos);

// Verificación de posibles errores de conexión
if ($conn->connect_error) {
    die("Error crítico de conexión a la base de datos: " . $conn->connect_error);
}

// Configuración de la codificación de caracteres a utf8mb4 (soporte completo de tildes, ñ y caracteres especiales)
$conn->set_charset("utf8mb4");
?>
