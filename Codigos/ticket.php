<?php
/**
 * ====================================================================
 * ARCHIVO: ticket.php
 * OBJETIVO: Generar el ticket de venta imprimible en formato de
 *           quiosco / ticketera térmica con los detalles de la compra.
 * ACCESO: Cualquier usuario autenticado (cajero o admin).
 * ====================================================================
 */

session_start();
require_once 'conexion.php';

// Control de seguridad
if (!isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit();
}

// Obtener el ID de la venta a imprimir
$id_venta = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ====================================================================
// 1. CONSULTA: CABECERA DE LA VENTA (ID, FECHA Y MONTO TOTAL)
// ====================================================================
$stmt = $conn->prepare("SELECT id_venta, fecha_hora, total FROM VENTA WHERE id_venta = ?");
$stmt->bind_param("i", $id_venta);
$stmt->execute();
$venta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$venta) {
    die("Ticket no encontrado.");
}

// ====================================================================
// 2. CONSULTA: ARTÍCULOS VENDIDOS EN ESTA VENTA ESPECÍFICA (JOIN)
// ====================================================================
$sqlDetalle = "SELECT dv.cantidad, dv.subtotal, p.nombre, p.precio 
               FROM DETALLE_VENTA dv
               INNER JOIN PRODUCTO p ON dv.id_producto = p.id_producto
               WHERE dv.id_venta = ?";
$stmtD = $conn->prepare($sqlDetalle);
$stmtD->bind_param("i", $id_venta);
$stmtD->execute();
$detalles = $stmtD->get_result();
$stmtD->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket de Venta #<?php echo $venta['id_venta']; ?></title>
    <style>
        /* ==================================================================== */
        /* CONFIGURACIÓN GENERAL DEL CUERPO (FONDO SIMULANDO ESCRITORIO)        */
        /* ==================================================================== */
        body { font-family: 'Courier New', Courier, monospace; background: #eee; padding: 20px; }
        
        /* ==================================================================== */
        /* DISEÑO DEL TICKET DE PAPEL BLANCO                                    */
        /* ==================================================================== */
        .ticket { background: white; width: 300px; padding: 15px; margin: 0 auto; border-radius: 4px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .text-center { text-align: center; }
        
        /* Línea punteada que separa secciones del ticket */
        .linea { border-top: 1px dashed #333; margin: 10px 0; }
        
        /* ==================================================================== */
        /* TABLA DE PRODUCTOS DENTRO DEL TICKET                                 */
        /* ==================================================================== */
        table { width: 100%; font-size: 0.85em; border-collapse: collapse; }
        th, td { text-align: left; padding: 3px 0; }
        .text-right { text-align: right; }
        
        /* Texto resaltado del total a pagar */
        .total { font-size: 1.1em; font-weight: bold; margin-top: 10px; }
        
        /* ==================================================================== */
        /* BOTONES DE ACCIÓN (VOLVER E IMPRIMIR)                                */
        /* ==================================================================== */
        .btn-box { text-align: center; margin-top: 20px; }
        button, a { padding: 8px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-print { background: #27ae60; color: white; border: none; }
        .btn-back { background: #2980b9; color: white; margin-left: 5px; }
        
        /* ==================================================================== */
        /* ESTILOS EXCLUSIVOS PARA CUANDO SE MANDA A IMPRESORA TÉRMICA O PDF    */
        /* ==================================================================== */
        @media print {
            body { background: white; padding: 0; } /* Fondo blanco puro */
            .ticket { box-shadow: none; width: 100%; } /* Quita la sombra */
            .btn-box { display: none; } /* Oculta los botones en el papel impreso */
        }
    </style>
</head>
<body>

    <!-- Diseño del comprobante térmico -->
    <div class="ticket">
        <div class="text-center">
            <h2 style="margin:0;"> QUIOSCO</h2>
            <p style="margin:5px 0; font-size:0.85em;">Ticket de Venta No Fiscal</p>
            <p style="font-size:0.8em; color:#555;">
                Fecha: <?php echo date('d/m/Y H:i', strtotime($venta['fecha_hora'])); ?><br>
                Ticket N°: #<?php echo str_pad($venta['id_venta'], 6, '0', STR_PAD_LEFT); ?>
            </p>
        </div>

        <div class="linea"></div>

        <!-- Detalle de artículos -->
        <table>
            <thead>
                <tr>
                    <th>Cant.</th>
                    <th>Producto</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($d = $detalles->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $d['cantidad']; ?>x</td>
                        <td><?php echo htmlspecialchars($d['nombre']); ?></td>
                        <td class="text-right">$<?php echo number_format($d['subtotal'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="linea"></div>

        <!-- Monto total cobrado -->
        <div class="total text-right">
            TOTAL: $<?php echo number_format($venta['total'], 2); ?>
        </div>

        <div class="linea"></div>
        <p class="text-center" style="font-size:0.8em; margin-bottom:0;">¡Gracias por su compra!</p>
    </div>

    <!-- Acciones: Imprimir en impresora o volver a la caja -->
    <div class="btn-box">
        <button onclick="window.print()" class="btn-print"> Imprimir Ticket</button>
        <a href="ventas.php" class="btn-back"> Nueva Venta</a>
    </div>

</body>
</html>

