<?php
/**
 * ====================================================================
 * ARCHIVO: productos.php
 * OBJETIVO: Gestión integral del inventario de mercadería del quiosco.
 *           Permite dar de alta nuevos productos, monitorear el stock,
 *           detectar alertas de stock bajo y consultar proveedores.
 * ACCESO: Roles 'almacen' y 'admin'.
 * ====================================================================
 */

session_start();
require_once 'conexion.php';

// ====================================================================
// 1. SEGURIDAD Y CONTROL DE ACCESO:
// Solo permite el ingreso si el usuario tiene rol 'almacen' o 'admin'.
// ====================================================================
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'almacen' && $_SESSION['rol'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

// Variables para captura de mensajes de éxito o error enviados por GET o generados en POST
$mensaje = isset($_GET['msg']) ? trim($_GET['msg']) : '';
$error   = isset($_GET['err']) ? trim($_GET['err']) : '';

// ====================================================================
// 2. PROCESAMIENTO: REGISTRAR UN NUEVO PRODUCTO (POST):
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    // Sanitización y conversión de tipos de datos ingresados en el formulario
    $nombre       = trim($_POST['nombre']);
    $precio       = floatval($_POST['precio']);
    $stock        = intval($_POST['stock']);
    $id_categoria = intval($_POST['id_categoria']);
    $id_proveedor = intval($_POST['id_proveedor']);
    $codigo_barra = trim($_POST['codigo_barra']);
    
    // Validaciones de reglas de negocio en el backend
    if ($precio <= 0) {
        $error = "El precio debe ser un valor positivo mayor a cero.";
    } elseif ($stock < 0) {
        $error = "El stock no puede ser negativo.";
    } elseif (empty($nombre) || $id_categoria <= 0 || $id_proveedor <= 0) {
        $error = "Por favor completa todos los campos requeridos.";
    } else {
        // Inserción segura mediante sentencia preparada
        $stmt = $conn->prepare("INSERT INTO PRODUCTO (nombre, precio, stock, id_categoria, id_proveedor, codigo_barra) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdiiiis", $nombre, $precio, $stock, $id_categoria, $id_proveedor, $codigo_barra);
        
        if ($stmt->execute()) {
            $mensaje = "¡Producto cargado exitosamente al inventario!";
        } else {
            $error = "Error al guardar el producto: " . $conn->error;
        }
        $stmt->close();
    }
}

// ====================================================================
// 3. CONSULTAS PARA VISTA (LISTADOS):
// ====================================================================

// Obtener productos combinados con su Categoría y Proveedor mediante INNER JOIN
$sql = "SELECT p.id_producto, p.nombre, p.precio, p.stock, c.nombre AS categoria, pr.nombre_proveedor, pr.telefono AS tel_proveedor, p.codigo_barra
        FROM PRODUCTO p
        INNER JOIN CATEGORIA c ON p.id_categoria = c.id_categoria
        INNER JOIN PROVEEDOR pr ON p.id_proveedor = pr.id_proveedor
        ORDER BY p.nombre ASC";
$productos = $conn->query($sql);

// Consultar categorías para llenar el menú desplegable (<select>) del formulario
$categorias = $conn->query("SELECT id_categoria, nombre FROM CATEGORIA ORDER BY nombre ASC");

// Consultar proveedores para el desplegable y para el directorio telefónico
$proveedores = $conn->query("SELECT id_proveedor, nombre_proveedor, telefono FROM PROVEEDOR ORDER BY nombre_proveedor ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Almacén y Stock - Quiosco</title>
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
        .grid { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h2, h3 { margin-top: 0; color: #2c3e50; }
        
        /* ==================================================================== */
        /* FORMULARIOS (INPUTS Y SELECTS) Y BOTONES                             */
        /* ==================================================================== */
        label { display: block; margin-bottom: 4px; font-weight: bold; color: #555; font-size: 0.9em; }
        input, select { width: 100%; padding: 8px 10px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #27ae60; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
        button:hover { background: #219150; }
        
        /* ==================================================================== */
        /* TABLA DE INVENTARIO                                                  */
        /* ==================================================================== */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 9px 10px; border-bottom: 1px solid #ddd; text-align: left; font-size: 0.9em; }
        th { background: #f8f9fa; }
        
        /* Etiquetas de Stock */
        .stock-bajo { background: #ffeaa7; color: #d63031; font-weight: bold; padding: 3px 6px; border-radius: 4px; } /* Alerta amarilla y roja */
        .stock-ok { color: #27ae60; font-weight: bold; } /* Verde para stock normal */
        
        /* Mensajes de Alerta */
        .msg { padding: 10px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
        .msg-ok { background: #d4edda; color: #155724; }
        .msg-err { background: #f8d7da; color: #721c24; }
        
        /* Directorio de Proveedores */
        .prov-box { background: #eef2f7; padding: 10px; border-radius: 6px; margin-bottom: 8px; font-size: 0.9em; }
        
        /* Enlaces de Acción en la tabla (Editar / Eliminar) */
        .btn-action-edit { color: #2980b9; text-decoration: none; font-weight: bold; margin-right: 8px; }
        .btn-action-edit:hover { text-decoration: underline; }
        .btn-action-del { color: #d63031; text-decoration: none; font-weight: bold; }
        .btn-action-del:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <!-- Barra de Navegación Superior -->
    <div class="nav">
        <div>
            <strong> Panel de Almacén</strong> | <?php echo htmlspecialchars($_SESSION['nombre']); ?>
        </div>
        <div class="nav-buttons">
            <!-- Botón de retorno al panel principal si el usuario es Administrador -->
            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                <a href="index.php" class="btn-nav"> Menú Admin</a>
            <?php endif; ?>
            <a href="proveedores.php" class="btn-nav"> Proveedores y Rubros</a>
            <a href="logout.php" class="btn-nav btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <!-- Mensajes de feedback para el usuario (éxito o error) -->
    <?php if (!empty($mensaje)): ?>
        <div class="msg msg-ok"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="msg msg-err"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="grid">
        <!-- Columna Izquierda: Formulario de Alta y Directorio de Proveedores -->
        <div>
            <!-- Formulario para agregar un nuevo producto -->
            <div class="card">
                <h3> Cargar Nuevo Producto</h3>
                <form action="productos.php" method="POST">
                    <input type="hidden" name="accion" value="crear">

                    <label for="nombre">Nombre del Producto</label>
                    <input type="text" id="nombre" name="nombre" placeholder="Ej: Alfajor Jorgito" required>

                    <label for="codigo_barra">Código de Barras</label>
                    <input type="text" id="codigo_barra" name="codigo_barra" placeholder="Ej: 7791234567890" required>


                    <label for="precio">Precio de Venta ($)</label>
                    <input type="number" step="0.01" min="0.01" id="precio" name="precio" placeholder="Ej: 850.00" required>

                    <label for="stock">Stock Inicial</label>
                    <input type="number" min="0" id="stock" name="stock" placeholder="Ej: 24" required>

                    <label for="id_categoria">Categoría</label>
                    <select id="id_categoria" name="id_categoria" required>
                        <option value="">-- Seleccionar Categoría --</option>
                        <?php while ($cat = $categorias->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                        <?php endwhile; ?>
                    </select>

                    <label for="id_proveedor">Proveedor que lo suministra</label>
                    <select id="id_proveedor" name="id_proveedor" required>
                        <option value="">-- Seleccionar Proveedor --</option>
                        <?php 
                        // Almacenamos proveedores en un array para reutilizarlos en el directorio inferior
                        $lista_prov = [];
                        while ($pr = $proveedores->fetch_assoc()) {
                            $lista_prov[] = $pr;
                            echo '<option value="'.$pr['id_proveedor'].'">'.htmlspecialchars($pr['nombre_proveedor']).'</option>';
                        }
                        ?>
                    </select>

                    <button type="submit">Guardar en Inventario</button>
                </form>
            </div>

            <!-- Directorio de contacto rápido con Proveedores para reposición -->
            <div class="card">
                <h3> Directorio de Proveedores</h3>
                <p style="font-size:0.85em; color:#666;">Contactos para reposición de mercadería:</p>
                <?php if (!empty($lista_prov)): ?>
                    <?php foreach ($lista_prov as $p): ?>
                        <div class="prov-box">
                            <strong> <?php echo htmlspecialchars($p['nombre_proveedor']); ?></strong><br>
                             Tel: <?php echo !empty($p['telefono']) ? htmlspecialchars($p['telefono']) : 'Sin teléfono registrado'; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:#888; font-size:0.9em;">No hay proveedores cargados.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Columna Derecha: Tabla con el catálogo completo de productos y stock -->
        <div class="card">
            <h2> Inventario y Stock de Mercadería</h2>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cód. Barra</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Stock Actual</th>
                        <th>Proveedor</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($productos && $productos->num_rows > 0): ?>
                        <?php while ($p = $productos->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($p['nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($p['codigo_barra']); ?></td>
                                <td><?php echo htmlspecialchars($p['categoria']); ?></td>
                                <td>$<?php echo number_format($p['precio'], 2); ?></td>
                                <td>
                                    <!-- Alerta visual si el stock es menor o igual a 5 unidades -->
                                    <?php if ($p['stock'] <= 5): ?>
                                        <span class="stock-bajo"><?php echo $p['stock']; ?> un. (Bajo)</span>
                                    <?php else: ?>
                                        <span class="stock-ok"><?php echo $p['stock']; ?> un.</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($p['nombre_proveedor']); ?><br>
                                    <small style="color:#777;"> <?php echo htmlspecialchars($p['tel_proveedor']); ?></small>
                                </td>
                                <td>
                                    <!-- Botones de Acción: Editar y Eliminar -->
                                    <a href="editar_producto.php?id=<?php echo $p['id_producto']; ?>" class="btn-action-edit"> Editar</a>
                                    <a href="eliminar_producto.php?id=<?php echo $p['id_producto']; ?>" onclick="return confirm('¿Seguro que deseas eliminar este producto?');" class="btn-action-del"> Eliminar</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; color:#888; padding:20px;">
                                Aún no hay productos registrados en el inventario.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>

