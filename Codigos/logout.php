<?php
/**
 * ====================================================================
 * ARCHIVO: logout.php
 * OBJETIVO: Destruir la sesión activa del usuario y redirigir
 *           de forma segura al formulario de inicio de sesión.
 * ====================================================================
 */

// Iniciar sesión para tener acceso a los datos actuales
session_start();

// Liberar todas las variables de sesión registradas
session_unset();

// Destruir por completo la información de la sesión en el servidor
session_destroy();

// Redirigir al usuario al formulario de login
header("Location: login.php");
exit();
?>
