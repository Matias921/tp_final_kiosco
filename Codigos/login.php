<?php
/**
 * ====================================================================
 * ARCHIVO: login.php
 * OBJETIVO: Autenticar usuarios en el sistema y redirigirlos
 *           automáticamente a su pantalla correspondiente según su ROL.
 * ====================================================================
 */

// Iniciar o reanudar la sesión para gestionar variables de usuario
session_start();

// Incluir el archivo de conexión a la base de datos
require_once 'conexion.php';

$error = '';

// ====================================================================
// 1. CONTROL DE SESIÓN PREVIA:
// Si el usuario ya está autenticado, se lo deriva directamente a su módulo.
// ====================================================================
if (isset($_SESSION['rol'])) {
    if ($_SESSION['rol'] === 'admin') {
        header("Location: index.php"); // Administrador: Panel central
        exit();
    } elseif ($_SESSION['rol'] === 'cajero') {
        header("Location: ventas.php"); // Cajero: Directo a la caja registradora
        exit();
    } elseif ($_SESSION['rol'] === 'almacen') {
        header("Location: productos.php"); // Almacén: Directo a inventario y stock
        exit();
    }
}

// ====================================================================
// 2. PROCESAMIENTO DEL FORMULARIO DE INGRESO (POST):
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario  = trim($_POST['usuario']);
    $password = trim($_POST['password']);

    // Validar que ninguno de los dos campos esté vacío
    if (!empty($usuario) && !empty($password)) {
        
        // Consulta preparada para evitar vulnerabilidades de inyección SQL
        $stmt = $conn->prepare("SELECT id_usuario, nombre, password, rol FROM USUARIO WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        // Verificar si existe exactamente un usuario con ese nombre de usuario
        if ($resultado->num_rows === 1) {
            $user = $resultado->fetch_assoc();
            
            // Verificación de contraseña ingresada
            if ($password === $user['password']) {
                // Registro de variables de sesión del usuario autenticado
                $_SESSION['id_usuario'] = $user['id_usuario'];
                $_SESSION['nombre']     = $user['nombre'];
                $_SESSION['rol']        = $user['rol'];

                // Redirección controlada por rol
                if ($user['rol'] === 'admin') {
                    header("Location: index.php");
                    exit();
                } elseif ($user['rol'] === 'cajero') {
                    header("Location: ventas.php");
                    exit();
                } elseif ($user['rol'] === 'almacen') {
                    header("Location: productos.php");
                    exit();
                }
            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            $error = "El usuario no existe.";
        }
        $stmt->close();
    } else {
        $error = "Por favor, completa todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Quiosco</title>
    <style>
        /* ==================================================================== */
        /* CONFIGURACIÓN GENERAL DEL CUERPO (FONDO Y CENTRADO)                  */
        /* ==================================================================== */
        body { 
            font-family: Arial, sans-serif; 
            background: #eef2f7;            /* Gris levemente azulado para el fondo */
            display: flex;                  /* Permite centrar contenido fácilmente */
            justify-content: center;        /* Centra horizontalmente */
            align-items: center;            /* Centra verticalmente */
            height: 100vh;                  /* Ocupa el 100% de la altura de la pantalla (Viewport Height) */
            margin: 0; 
        }

        /* ==================================================================== */
        /* TARJETA DE LOGIN (CAJA BLANCA CENTRAL)                               */
        /* ==================================================================== */
        .login-box { 
            background: white; 
            padding: 30px; 
            border-radius: 10px;            /* Bordes bien redondeados */
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); /* Sombra difuminada para dar efecto de "flote" */
            width: 320px;                   /* Ancho fijo para que no se deforme el formulario */
        }

        h2 { 
            text-align: center;             /* Centra el título de "Iniciar Sesión" */
            color: #2c3e50; 
            margin-bottom: 20px; 
        }

        /* Contenedor de cada input (margen inferior) */
        .form-group { margin-bottom: 15px; }

        label { 
            display: block; 
            margin-bottom: 5px; 
            color: #555; 
            font-size: 0.9em;               /* Letra ligeramente más chica para las etiquetas */
        }

        /* ==================================================================== */
        /* CAMPOS DE TEXTO Y CONTRASEÑA                                         */
        /* ==================================================================== */
        input[type="text"], input[type="password"] { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ccc;         /* Borde gris claro */
            border-radius: 5px; 
            box-sizing: border-box;         /* Asegura que el padding no rompa el ancho del 100% */
        }

        /* ==================================================================== */
        /* BOTÓN DE INGRESAR AL SISTEMA                                         */
        /* ==================================================================== */
        button { 
            width: 100%; 
            padding: 10px; 
            background: #2980b9;            /* Color azul principal */
            color: white; 
            border: none; 
            border-radius: 5px; 
            font-size: 1em; 
            cursor: pointer; 
            font-weight: bold; 
        }
        button:hover { background: #1c5980; } /* Azul más oscuro al pasar el mouse */

        /* ==================================================================== */
        /* MENSAJE DE ERROR DE CREDENCIALES                                     */
        /* ==================================================================== */
        .error { 
            background: #f8d7da;            /* Fondo rojo clarito */
            color: #721c24;                 /* Texto rojo oscuro */
            padding: 10px; 
            border-radius: 5px; 
            margin-bottom: 15px; 
            font-size: 0.85em; 
            text-align: center; 
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2> Iniciar Sesión</h2>
        
        <!-- Mostrar mensaje de error en caso de fallo en autenticación -->
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Formulario de credenciales -->
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Ingresar al Sistema</button>
        </form>
    </div>
</body>
</html>

