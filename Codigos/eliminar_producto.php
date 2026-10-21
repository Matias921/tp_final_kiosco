<?php
/**
 * ====================================================================
 * ARCHIVO: eliminar_producto.php
 * OBJETIVO: Dar de baja un producto de la base de datos aplicando
 *           la regla de integridad referencial: no se puede borrar
 *           un producto que ya figure en ventas históricas.
 * ACCESO: Roles 'almacen' y 'admin'.
 * ====================================================================
 */

session_start();
require_once 'conexion.php';

// ====================================================================
// CONTROL DE ACCESO / SEGURIDAD:
// Solo permite ejecutar la eliminación a roles autorizados.
// ====================================================================
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'almacen' && $_SESSION['rol'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

// Validar que se haya enviado el parámetro ID por GET
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // ================================================================
    // VALIDACIÓN DE INTEGRIDAD HISTÓRICA:
    // Comprobar si el producto ya fue vendido en la tabla DETALLE_VENTA.
    // Si tiene ventas asociadas, eliminarlo rompería las facturas pasadas.
    // ================================================================
    $check = $conn->prepare("SELECT COUNT(*) AS total FROM DETALLE_VENTA WHERE id_producto = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $yaVendido = $check->get_result()->fetch_assoc()['total'];
    $check->close();

    if ($yaVendido > 0) {
        // Rechazar borrado físico y sugerir ajuste de stock a cero
        header("Location: productos.php?err=No se puede eliminar porque tiene ventas registradas. Te sugerimos poner su stock en 0.");
        exit();
    } else {
        // Si no tiene ventas históricas asociadas, se procede al borrado seguro
        $stmt = $conn->prepare("DELETE FROM PRODUCTO WHERE id_producto = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: productos.php?msg=Producto eliminado correctamente.");
        exit();
    }
} else {
    // Si no se proporcionó un ID válido, redirigir al listado
    header("Location: productos.php");
    exit();
}
?>
