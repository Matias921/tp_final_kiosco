<?php
/**
 * ====================================================================
 * ARCHIVO: editar_producto.php
 * OBJETIVO: Formulario para modificar datos de un producto existente:
 *           nombre, precio, stock, categoría asignada y proveedor.
 * ACCESO: Roles 'almacen' y 'admin'.
 * ====================================================================
 */

session_start();
require_once 'conexion.php';

// ====================================================================
// CONTROL DE ACCESO / SEGURIDAD:
// Solo permite el ingreso a usuarios con rol 'almacen' o 'admin'.
// ====================================================================
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'almacen' && $_SESSION['rol'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

// Obtener el ID del producto desde el parámetro GET de la URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';

// ====================================================================
// PROCESAMIENTO: ACTUALIZAR DATOS DEL PRODUCTO (POST):
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre       = trim($_POST['nombre']);
    $precio       = floatval($_POST['precio']);
    $stock        = intval($_POST['stock']);
    $id_categoria = intval($_POST['id_categoria']);
    $id_proveedor = intval($_POST['id_proveedor']);
    $codigo_barra = trim($_POST['codigo_barra']);

    // Validaciones de negocio
    if ($precio <= 0) {
        $error = "El precio debe ser mayor a cero.";
    } elseif ($stock < 0) {
        $error = "El stock no puede ser negativo.";
    } elseif (empty($nombre) || $id_categoria <= 0 || $id_proveedor <= 0) {
        $error = "Todos los campos son obligatorios.";
    } else {
        // Ejecución de la sentencia UPDATE preparada
        $stmt = $conn->prepare("UPDATE PRODUCTO SET nombre=?, precio=?, stock=?, id_categoria=?, id_proveedor=?, codigo_barra=? WHERE id_producto=?");
        $stmt->bind_param("sdiiisi", $nombre, $precio, $stock, $id_categoria, $id_proveedor, $codigo_barra, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            // Redirigir al inventario con mensaje de confirmación
            header("Location: productos.php?msg=Producto actualizado con éxito.");
            exit();
        } else {
            $error = "Error al actualizar: " . $conn->error;
        }
    }
}

// ====================================================================
// CONSULTA: CARGAR INFORMACIÓN ACTUAL DEL PRODUCTO A EDITAR:
// ====================================================================
$stmt = $conn->prepare("SELECT * FROM PRODUCTO WHERE id_producto = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$prod = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Si no existe un producto con ese ID, redirigir con mensaje de error
if (!$prod) {
    header("Location: productos.php?err=Producto no encontrado.");
    exit();
}

// Cargar opciones para los menús desplegables de Categorías y Proveedores
$categorias  = $conn->query("SELECT id_categoria, nombre FROM CATEGORIA ORDER BY nombre ASC");
$proveedores = $conn->query("SELECT id_proveedor, nombre_proveedor FROM PROVEEDOR ORDER BY nombre_proveedor ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <style>
        /* ==================================================================== */
        /* CONFIGURACIÓN GENERAL DEL CUERPO                                     */
        /* ==================================================================== */
        body { font-family: Arial, sans-serif; background: #f4f6f9; padding: 30px; display: flex; justify-content: center; }
        
        /* ==================================================================== */
        /* TARJETA DEL FORMULARIO CENTRAL                                       */
        /* ==================================================================== */
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 400px; }
        h2 { margin-top: 0; color: #2c3e50; }
        
        /* ==================================================================== */
        /* ETIQUETAS, CAMPOS Y DESPLEGABLES                                     */
        /* ==================================================================== */
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input, select { width: 100%; padding: 8px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        
        /* ==================================================================== */
        /* BOTONES                                                              */
        /* ==================================================================== */
        .btn-save { width: 100%; padding: 10px; background: #2980b9; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
        .btn-back { display: block; text-align: center; margin-top: 10px; color: #7f8c8d; text-decoration: none; }
        
        /* ==================================================================== */
        /* MENSAJE DE ERROR DE VALIDACIÓN                                       */
        /* ==================================================================== */
        .error { background: #f8d7da; color: #721c24; padding: 8px; border-radius: 4px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="card">
        <h2> Editar Producto</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Formulario precargado con los valores actuales del producto -->
        <form method="POST">
            <label>Nombre:</label>
            <input type="text" name="nombre" value="<?php echo htmlspecialchars($prod['nombre']); ?>" required>

            <label>Código de Barras:</label>
            <input type="text" name="codigo_barra" value="<?php echo htmlspecialchars($prod['codigo_barra']); ?>" required>

            <label>Precio ($):</label>
            <input type="number" step="0.01" min="0.01" name="precio" value="<?php echo $prod['precio']; ?>" required>

            <label>Stock:</label>
            <input type="number" min="0" name="stock" value="<?php echo $prod['stock']; ?>" required>

            <label>Categoría:</label>
            <select name="id_categoria" required>
                <?php while ($c = $categorias->fetch_assoc()): ?>
                    <option value="<?php echo $c['id_categoria']; ?>" <?php if ($c['id_categoria'] == $prod['id_categoria']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($c['nombre']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Proveedor:</label>
            <select name="id_proveedor" required>
                <?php while ($pr = $proveedores->fetch_assoc()): ?>
                    <option value="<?php echo $pr['id_proveedor']; ?>" <?php if ($pr['id_proveedor'] == $prod['id_proveedor']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($pr['nombre_proveedor']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit" class="btn-save"> Guardar Cambios</button>
            <a href="productos.php" class="btn-back">Cancelar y Volver</a>
        </form>
    </div>
</body>
</html>

