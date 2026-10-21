<?php
/**
 * ====================================================================
 * ARCHIVO: ventas.php
 * OBJETIVO: Punto de Venta (Caja Registradora) para atención rápida.
 *           Permite armar el ticket en tiempo real, verificar stock,
 *           cobrar y ejecutar el descuento de stock de forma atómica.
 * ACCESO: Roles 'cajero' y 'admin'.
 * ====================================================================
 */

session_start();
require_once 'conexion.php';

// ====================================================================
// CONTROL DE ACCESO / SEGURIDAD:
if (!isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit();
}

// Inicializar el carrito
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

$mensaje = '';
$error = '';

// ====================================================================
// 1. PROCESAMIENTO: AGREGAR UN ARTÍCULO A LA CUENTA (POST)
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
    $id_producto  = intval($_POST['id_producto'] ?? 0);
    $codigo_barra = trim($_POST['codigo_barra'] ?? '');
    $cantidad     = intval($_POST['cantidad'] ?? 1);

    if (!empty($codigo_barra)) {
        $stmt_cb = $conn->prepare("SELECT id_producto FROM PRODUCTO WHERE codigo_barra = ?");
        $stmt_cb->bind_param("s", $codigo_barra);
        $stmt_cb->execute();
        $res_cb = $stmt_cb->get_result();
        
        if ($row_cb = $res_cb->fetch_assoc()) {
            $id_producto = $row_cb['id_producto'];
        } else {
            $error = "Ese código de barras no existe en el inventario.";
        }
        $stmt_cb->close();
    }

    if (empty($error) && $id_producto > 0 && $cantidad > 0) {
        $stmt = $conn->prepare("SELECT id_producto, nombre, precio, stock FROM PRODUCTO WHERE id_producto = ?");
        $stmt->bind_param("i", $id_producto);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($producto = $res->fetch_assoc()) {
            $cantidad_en_carrito = isset($_SESSION['carrito'][$id_producto]) ? $_SESSION['carrito'][$id_producto]['cantidad'] : 0;
            
            if (($cantidad_en_carrito + $cantidad) <= $producto['stock']) {
                if (isset($_SESSION['carrito'][$id_producto])) {
                    $_SESSION['carrito'][$id_producto]['cantidad'] += $cantidad;
                    $_SESSION['carrito'][$id_producto]['subtotal'] = $_SESSION['carrito'][$id_producto]['cantidad'] * $producto['precio'];
                } else {
                    $_SESSION['carrito'][$id_producto] = [
                        'nombre'   => $producto['nombre'],
                        'precio'   => $producto['precio'],
                        'cantidad' => $cantidad,
                        'subtotal' => $producto['precio'] * $cantidad
                    ];
                }
            } else {
                $error = "No hay suficiente stock. Stock disponible: " . $producto['stock'];
            }
        }
        $stmt->close();
    } elseif (empty($error) && $id_producto <= 0) {
        $error = "Por favor, escaneá un código o seleccioná un producto.";
    }
}

// ====================================================================
// 2. PROCESAMIENTO: QUITAR UN PRODUCTO DE LA CUENTA (GET)
// ====================================================================
if (isset($_GET['quitar'])) {
    $id_quitar = intval($_GET['quitar']);
    if (isset($_SESSION['carrito'][$id_quitar])) {
        unset($_SESSION['carrito'][$id_quitar]);
    }
    header("Location: ventas.php");
    exit();
}

// ====================================================================
// 3. PROCESAMIENTO: CONFIRMAR Y COBRAR LA VENTA (POST)
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'confirmar') {
    if (!empty($_SESSION['carrito'])) {
        $total = 0;
        foreach ($_SESSION['carrito'] as $item) {
            $total += $item['subtotal'];
        }

        $conn->begin_transaction();
        try {
            $fecha_hora = date('Y-m-d H:i:s');
            $stmtVenta = $conn->prepare("INSERT INTO VENTA (fecha_hora, total) VALUES (?, ?)");
            $stmtVenta->bind_param("sd", $fecha_hora, $total);
            $stmtVenta->execute();
            $id_venta = $conn->insert_id;
            $stmtVenta->close();

            $stmtDetalle = $conn->prepare("INSERT INTO DETALLE_VENTA (id_venta, id_producto, cantidad, subtotal) VALUES (?, ?, ?, ?)");
            $stmtStock   = $conn->prepare("UPDATE PRODUCTO SET stock = stock - ? WHERE id_producto = ?");

            foreach ($_SESSION['carrito'] as $id_prod => $item) {
                $stmtDetalle->bind_param("iiid", $id_venta, $id_prod, $item['cantidad'], $item['subtotal']);
                $stmtDetalle->execute();

                $stmtStock->bind_param("ii", $item['cantidad'], $id_prod);
                $stmtStock->execute();
            }

            $stmtDetalle->close();
            $stmtStock->close();
            $conn->commit();

            $_SESSION['carrito'] = [];
            $mensaje = "¡Venta #" . $id_venta . " registrada con éxito! Total cobrado: $" . number_format($total, 2) . 
                       " &nbsp;&nbsp; <a href='ticket.php?id=" . $id_venta . "' style='background:#27ae60; color:white; padding:5px 10px; border-radius:4px; text-decoration:none; font-size:0.9em;'>Imprimir Ticket</a>";

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error al procesar la venta: " . $e->getMessage();
        }
    } else {
        $error = "La cuenta está vacía, no hay nada para cobrar.";
    }
}

// ====================================================================
// 4. CONSULTA: PRODUCTOS DISPONIBLES CON STOCK MAYOR A CERO
// ====================================================================
$productos_query = $conn->query("SELECT id_producto, nombre, precio, stock FROM PRODUCTO WHERE stock > 0 ORDER BY nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Quiosco</title>
    <style>
        /* ==================================================================== */
        /* CONFIGURACIÓN GENERAL DEL CUERPO Y FONDO DE PANTALLA                 */
        /* ==================================================================== */
        body { 
            font-family: Arial, sans-serif; /* Usa la fuente Arial (muy legible) */
            background: #f4f6f9;            /* Color gris muy claro para el fondo */
            margin: 0;                      /* Quita los márgenes por defecto del navegador */
            padding: 20px;                  /* Deja un espacio de 20px alrededor de toda la página */
        }

        /* ==================================================================== */
        /* BARRA DE NAVEGACIÓN SUPERIOR (EL MENÚ)                               */
        /* ==================================================================== */
        .nav { 
            background: #2c3e50;            /* Color azul oscuro/grisáceo */
            color: white;                   /* Color del texto adentro de la barra */
            padding: 12px 20px;             /* Espacio interno (arriba/abajo y lados) */
            border-radius: 8px;             /* Redondea las esquinas de la barra */
            display: flex;                  /* Permite organizar los elementos en fila fácilmente */
            justify-content: space-between; /* Empuja el título a la izq y botones a la der */
            align-items: center;            /* Centra los elementos verticalmente */
            margin-bottom: 20px;            /* Deja espacio por debajo de la barra */
            flex-wrap: wrap;                /* Si la pantalla es chica, tira los elementos abajo */
            gap: 10px;                      /* Espacio entre los elementos si se amontonan */
        }
        .nav-buttons { 
            display: flex; gap: 8px; align-items: center; flex-wrap: wrap; 
        }
        .btn-nav { 
            color: white; text-decoration: none; font-weight: bold; 
            background: rgba(255,255,255,0.2); padding: 6px 12px; border-radius: 4px; font-size: 0.9em; 
        }
        .btn-nav:hover { background: rgba(255,255,255,0.3); }
        .btn-logout { background: #d63031 !important; }
        .btn-logout:hover { background: #b71c1c !important; }

        /* ==================================================================== */
        /* ESTRUCTURA Y MAQUETACIÓN (GRILLAS Y TARJETAS)                        */
        /* ==================================================================== */
        .grid { 
            display: grid; 
            grid-template-columns: 1fr 2fr; /* Columna izq: 1 tercio, Columna der: 2 tercios */
            gap: 20px; 
        }
        .card { 
            background: white; padding: 20px; border-radius: 8px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Sombrita sutil */
        }
        h2 { margin-top: 0; color: #2c3e50; }

        /* ==================================================================== */
        /* FORMULARIOS (INPUTS Y SELECTS)                                       */
        /* ==================================================================== */
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        select, input[type="text"], input[type="number"] { 
            width: 100%; padding: 10px; margin-bottom: 15px; 
            border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; 
        }

        /* ==================================================================== */
        /* BOTONES DE ACCIÓN (AGREGAR Y COBRAR)                                 */
        /* ==================================================================== */
        button { 
            width: 100%; padding: 12px; border: none; border-radius: 5px; 
            font-weight: bold; font-size: 1em; cursor: pointer; 
        }
        .btn-add { background: #0984e3; color: white; }
        .btn-add:hover { background: #0767b1; }
        .btn-pay { background: #00b894; color: white; font-size: 1.2em; margin-top: 15px; }
        .btn-pay:hover { background: #009477; }

        /* ==================================================================== */
        /* TABLAS Y TOTALES (EL CARRITO DE COMPRAS)                             */
        /* ==================================================================== */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
        .total-box { 
            font-size: 1.5em; font-weight: bold; text-align: right; 
            margin-top: 15px; color: #2d3436; 
        }
        .btn-del { color: #d63031; text-decoration: none; font-weight: bold; }

        /* ==================================================================== */
        /* MENSAJES DE ALERTA (ÉXITO O ERROR)                                   */
        /* ==================================================================== */
        .msg { padding: 10px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
        .msg-ok { background: #d4edda; color: #155724; }
        .msg-err { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

    <div class="nav">
        <div>
            <strong> Caja Quiosco</strong> | <?php echo htmlspecialchars($_SESSION['nombre']); ?>
        </div>
        <div class="nav-buttons">
            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                <a href="index.php" class="btn-nav"> Menú Admin</a>
            <?php endif; ?>
            <a href="logout.php" class="btn-nav btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="msg msg-ok"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="msg msg-err"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <h2>Agregar a la cuenta</h2>
            <form action="ventas.php" method="POST">
                <input type="hidden" name="accion" value="agregar">
                
                <label for="codigo_barra"> Escanear Código de Barras</label>
                <input type="text" name="codigo_barra" id="codigo_barra" placeholder="Pasar lector o escribir..." autofocus>
                
                <p style="text-align:center; color:#888; font-size:0.85em; margin-top: 0px;">--- o seleccionar de la lista ---</p>
                
                <label for="id_producto">Producto</label>
                <select name="id_producto" id="id_producto">
                    <option value="">-- Seleccionar producto --</option>
                    <?php while ($prod = $productos_query->fetch_assoc()): ?>
                        <option value="<?php echo $prod['id_producto']; ?>">
                            <?php echo htmlspecialchars($prod['nombre']); ?> - $<?php echo number_format($prod['precio'], 2); ?> (Stock: <?php echo $prod['stock']; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>

                <label for="cantidad">Cantidad</label>
                <input type="number" name="cantidad" id="cantidad" value="1" min="1" required>

                <button type="submit" class="btn-add"> Agregar Producto</button>
            </form>
        </div>

        <div class="card">
            <h2>Cuenta Actual</h2>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Cant.</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $gran_total = 0;
                    if (!empty($_SESSION['carrito'])): 
                        foreach ($_SESSION['carrito'] as $id => $item): 
                            $gran_total += $item['subtotal'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                            <td>$<?php echo number_format($item['precio'], 2); ?></td>
                            <td><?php echo $item['cantidad']; ?></td>
                            <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                            <td><a href="ventas.php?quitar=<?php echo $id; ?>" class="btn-del">❌</a></td>
                        </tr>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                        <tr>
                            <td colspan="5" style="text-align:center; color:#888;">No hay productos agregados en la cuenta.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="total-box">
                Total a Cobrar: $<?php echo number_format($gran_total, 2); ?>
            </div>

            <?php if (!empty($_SESSION['carrito'])): ?>
                <form action="ventas.php" method="POST">
                    <input type="hidden" name="accion" value="confirmar">
                    <button type="submit" class="btn-pay"> Confirmar y Cobrar Venta</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>


