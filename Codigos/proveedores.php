<?php
/**
 * ====================================================================
 * ARCHIVO: proveedores.php
 * OBJETIVO: Administración de Proveedores (distribuidoras con sus teléfonos)
 *           y Categorías de productos para clasificación en el quiosco.
 * ACCESO: Roles 'almacen' y 'admin'.
 * ====================================================================
 */

session_start();
require_once 'conexion.php';

// ====================================================================
// CONTROL DE ACCESO / SEGURIDAD:
// Solo permite el ingreso a usuarios autorizados de almacén o admin.
// ====================================================================
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'almacen' && $_SESSION['rol'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$error = '';

// ====================================================================
// 1. PROCESAMIENTO: ALTA DE NUEVO PROVEEDOR (POST)
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_proveedor') {
    $nombre_prov = trim($_POST['nombre_proveedor']);
    $telefono    = trim($_POST['telefono']);

    if (!empty($nombre_prov)) {
        $stmt = $conn->prepare("INSERT INTO PROVEEDOR (nombre_proveedor, telefono) VALUES (?, ?)");
        $stmt->bind_param("ss", $nombre_prov, $telefono);
        if ($stmt->execute()) {
            $mensaje = "¡Proveedor agregado con éxito!";
        } else {
            $error = "Error al guardar el proveedor: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = "El nombre del proveedor es obligatorio.";
    }
}

// ====================================================================
// 2. PROCESAMIENTO: ALTA DE NUEVA CATEGORÍA (POST)
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_categoria') {
    $nombre_cat = trim($_POST['nombre_categoria']);

    if (!empty($nombre_cat)) {
        $stmt = $conn->prepare("INSERT INTO CATEGORIA (nombre) VALUES (?)");
        $stmt->bind_param("s", $nombre_cat);
        if ($stmt->execute()) {
            $mensaje = "¡Categoría agregada con éxito!";
        } else {
            $error = "Error al guardar la categoría: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = "El nombre de la categoría es obligatorio.";
    }
}

// ====================================================================
// 3. CONSULTAS: LISTADOS ACTUALES DE PROVEEDORES Y CATEGORÍAS
// ====================================================================
$proveedores = $conn->query("SELECT * FROM PROVEEDOR ORDER BY nombre_proveedor ASC");
$categorias  = $conn->query("SELECT * FROM CATEGORIA ORDER BY nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores y Categorías - Quiosco</title>
    <style>
        /* ==================================================================== */
        /* CONFIGURACIÓN GENERAL DEL CUERPO Y FONDO DE PANTALLA                 */
        /* ==================================================================== */
        body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        
        /* ==================================================================== */
        /* BARRA DE NAVEGACIÓN SUPERIOR                                         */
        /* ==================================================================== */
        .nav { background: #2c3e50; color: white; padding: 12px 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .nav-buttons { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .btn-nav { color: white; text-decoration: none; font-weight: bold; background: rgba(255,255,255,0.2); padding: 6px 12px; border-radius: 4px; font-size: 0.9em; }
        .btn-nav:hover { background: rgba(255,255,255,0.3); }
        .btn-logout { background: #d63031 !important; }
        .btn-logout:hover { background: #b71c1c !important; }
        
        /* ==================================================================== */
        /* ESTRUCTURA (GRILLA) Y TARJETAS BLANCAS                               */
        /* ==================================================================== */
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; } /* Dos columnas del mismo ancho */
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h2, h3 { margin-top: 0; color: #2c3e50; }
        
        /* ==================================================================== */
        /* FORMULARIOS (INPUTS Y SELECTS) Y BOTONES                             */
        /* ==================================================================== */
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 0.9em; }
        input { width: 100%; padding: 8px 10px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #2980b9; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
        button:hover { background: #1f618d; }
        
        /* ==================================================================== */
        /* TABLA DE PROVEEDORES Y CATEGORÍAS                                    */
        /* ==================================================================== */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #ddd; text-align: left; font-size: 0.9em; }
        th { background: #f8f9fa; }
        
        /* Mensajes de Alerta */
        .msg { padding: 10px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
        .msg-ok { background: #d4edda; color: #155724; }
        .msg-err { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

    <!-- Barra de Navegación Superior -->
    <div class="nav">
        <div>
            <strong> Gestión de Proveedores y Rubros</strong> | <?php echo htmlspecialchars($_SESSION['nombre']); ?>
        </div>
        <div class="nav-buttons">
            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                <a href="index.php" class="btn-nav"> Menú Admin</a>
            <?php endif; ?>
            <a href="productos.php" class="btn-nav"> Volver al Inventario</a>
            <a href="logout.php" class="btn-nav btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <!-- Notificaciones de estado -->
    <?php if (!empty($mensaje)): ?>
        <div class="msg msg-ok"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="msg msg-err"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="grid">
        <!-- Columna 1: GESTIÓN DE PROVEEDORES -->
        <div>
            <!-- Formulario para registrar un proveedor -->
            <div class="card">
                <h3> Agregar Nuevo Proveedor</h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="crear_proveedor">
                    <label>Nombre del Proveedor / Distribuidora:</label>
                    <input type="text" name="nombre_proveedor" placeholder="Ej: Lays Argentina" required>

                    <label>Teléfono o Contacto:</label>
                    <input type="text" name="telefono" placeholder="Ej: 11-4433-2211">

                    <button type="submit">Guardar Proveedor</button>
                </form>
            </div>

            <!-- Tabla de Proveedores registrados -->
            <div class="card">
                <h3> Lista de Proveedores</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Proveedor</th>
                            <th>Teléfono</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pr = $proveedores->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $pr['id_proveedor']; ?></td>
                                <td><strong><?php echo htmlspecialchars($pr['nombre_proveedor']); ?></strong></td>
                                <td> <?php echo !empty($pr['telefono']) ? htmlspecialchars($pr['telefono']) : 'Sin teléfono'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Columna 2: GESTIÓN DE CATEGORÍAS -->
        <div>
            <!-- Formulario para registrar una categoría -->
            <div class="card">
                <h3> Agregar Nueva Categoría</h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="crear_categoria">
                    <label>Nombre de la Categoría:</label>
                    <input type="text" name="nombre_categoria" placeholder="Ej: Lácteos / Panadería" required>

                    <button type="submit" style="background:#27ae60;">Guardar Categoría</button>
                </form>
            </div>

            <!-- Tabla de Categorías registradas -->
            <div class="card">
                <h3> Categorías Disponibles</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre de Categoría</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($cat = $categorias->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $cat['id_categoria']; ?></td>
                                <td><strong><?php echo htmlspecialchars($cat['nombre']); ?></strong></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>

