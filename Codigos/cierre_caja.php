<?php
/**
 * ====================================================================
 * ARCHIVO: cierre_caja.php
 * OBJETIVO: Generar reportes contables consolidados y cierres de caja
 *           (diarios, mensuales o históricos) para el Administrador.
 *           Permite imprimir todas las facturas en un solo documento.
 * ACCESO: Exclusivo rol 'admin'.
 * ====================================================================
 */

session_start();
require_once 'conexion.php';

// ====================================================================
// CONTROL DE ACCESO / SEGURIDAD:
// Solo permite el ingreso al Administrador del sistema.
// ====================================================================
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// ====================================================================
// GESTIÓN DE FILTROS TEMPORALES:
// ====================================================================
$fecha_hoy    = date('Y-m-d');
$filtro_tipo  = isset($_GET['tipo']) ? $_GET['tipo'] : 'dia'; // Opciones: 'dia', 'mes' o 'todo'
$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : $fecha_hoy;
$mes_filtro   = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');

$where = "";
if ($filtro_tipo === 'dia') {
    $where = "WHERE DATE(v.fecha_hora) = '$fecha_filtro'";
    $titulo_reporte = "Cierre Diario: " . date('d/m/Y', strtotime($fecha_filtro));
} elseif ($filtro_tipo === 'mes') {
    $where = "WHERE DATE_FORMAT(v.fecha_hora, '%Y-%m') = '$mes_filtro'";
    $titulo_reporte = "Cierre Mensual: " . date('m/Y', strtotime($mes_filtro . "-01"));
} else {
    $where = "";
    $titulo_reporte = "Historial Completo de Facturas / Ventas";
}

// ====================================================================
// 1. CONSULTA: TOTALES GLOBALES DEL PERÍODO (CANTIDAD Y MONTO TOTAL)
// ====================================================================
$sqlTotales = "SELECT COUNT(*) AS total_ventas, COALESCE(SUM(total), 0) AS total_recaudado FROM VENTA v $where";
$totales = $conn->query($sqlTotales)->fetch_assoc();

// ====================================================================
// 2. CONSULTA: LISTADO DE VENTAS DEL PERÍODO
// ====================================================================
$sqlVentas = "SELECT v.id_venta, v.fecha_hora, v.total 
              FROM VENTA v 
              $where 
              ORDER BY v.fecha_hora DESC";
$ventas = $conn->query($sqlVentas);

// ====================================================================
// 3. CONSULTA: OBTENER LOS PRODUCTOS ASOCIADOS A CADA VENTA
// ====================================================================
$ventasConDetalle = [];
while ($row = $ventas->fetch_assoc()) {
    $id_v = $row['id_venta'];
    $sqlD = "SELECT dv.cantidad, dv.subtotal, p.nombre, p.precio 
             FROM DETALLE_VENTA dv 
             INNER JOIN PRODUCTO p ON dv.id_producto = p.id_producto 
             WHERE dv.id_venta = $id_v";
    $detallesRes = $conn->query($sqlD);
    $items = [];
    while ($d = $detallesRes->fetch_assoc()) {
        $items[] = $d;
    }
    $row['items'] = $items;
    $ventasConDetalle[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de Caja y Facturación - Quiosco</title>
    <style>
        /* ==================================================================== */
        /* CONFIGURACIÓN GENERAL DEL CUERPO Y FONDO DE PANTALLA                 */
        /* ==================================================================== */
        body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        
        /* ==================================================================== */
        /* BARRA DE NAVEGACIÓN SUPERIOR                                         */
        /* ==================================================================== */
        .nav { background: #2c3e50; color: white; padding: 12px 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .nav-buttons { display: flex; gap: 8px; align-items: center; }
        .btn-nav { color: white; text-decoration: none; font-weight: bold; background: rgba(255,255,255,0.2); padding: 6px 12px; border-radius: 4px; font-size: 0.9em; }
        .btn-logout { background: #d63031 !important; }
        
        /* ==================================================================== */
        /* TARJETAS BLANCAS GENÉRICAS                                           */
        /* ==================================================================== */
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        
        /* ==================================================================== */
        /* FILTROS (BARRA DE BÚSQUEDA POR FECHA)                                */
        /* ==================================================================== */
        .filter-form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        label { font-weight: bold; color: #555; }
        input, select { padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; }
        
        /* Botones de acción en los filtros */
        .btn-filter { background: #2980b9; color: white; border: none; border-radius: 5px; padding: 8px 16px; font-weight: bold; cursor: pointer; }
        .btn-print { background: #27ae60; color: white; border: none; border-radius: 5px; padding: 8px 18px; font-weight: bold; cursor: pointer; font-size: 1em; }
        
        /* ==================================================================== */
        /* CAJAS DE RESUMEN (TOTALES DE RECAUDACIÓN)                            */
        /* ==================================================================== */
        .summary-box { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .summary-card { flex: 1; min-width: 200px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); border-left: 5px solid #27ae60; }
        .summary-card h3 { margin: 0 0 5px 0; color: #7f8c8d; font-size: 0.95em; }
        .summary-card .number { font-size: 1.8em; font-weight: bold; color: #2c3e50; }
        
        /* ==================================================================== */
        /* TABLA DE FACTURAS EMITIDAS                                           */
        /* ==================================================================== */
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #e1e2e6; text-align: left; }
        th { background: #f1f2f6; }
        .text-right { text-align: right; }

        /* ==================================================================== */
        /* ESTILOS DE IMPRESIÓN (PARA CUANDO SE APRIETA CTRL+P O "IMPRIMIR")    */
        /* ==================================================================== */
        @media print {
            body { background: white; padding: 0; }
            .nav, .filter-card { display: none !important; } /* Oculta el menú y los filtros al imprimir */
            .card, .summary-card { box-shadow: none !important; border: 1px solid #999 !important; } /* Saca las sombras */
            .summary-box { display: flex !important; }
            .print-only-title { display: block !important; } /* Muestra el título especial de impresión */
        }
        
        /* Título que solo se ve en el papel impreso, oculto en la pantalla normal */
        .print-only-title { display: none; text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>

    <!-- Barra de Navegación Superior -->
    <div class="nav">
        <div>
            <strong> Cierre de Caja y Facturas</strong> | <?php echo htmlspecialchars($_SESSION['nombre']); ?>
        </div>
        <div class="nav-buttons">
            <a href="index.php" class="btn-nav"> Menú Admin</a>
            <a href="ventas.php" class="btn-nav"> Ir a Caja</a>
            <a href="logout.php" class="btn-nav btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <!-- Encabezado visible únicamente al momento de imprimir el documento -->
    <div class="print-only-title">
        <h1 style="margin:0;"> QUIOSCO - INFORME DE CIERRE DE CAJA</h1>
        <h3 style="margin:5px 0; color:#555;"><?php echo $titulo_reporte; ?></h3>
        <p style="font-size:0.85em; color:#777;">Generado el <?php echo date('d/m/Y H:i'); ?></p>
    </div>

    <!-- Filtros de fecha y Botón de Impresión -->
    <div class="card filter-card">
        <form method="GET" class="filter-form">
            <label>Tipo de Cierre:</label>
            <select name="tipo" onchange="this.form.submit()">
                <option value="dia" <?php if ($filtro_tipo === 'dia') echo 'selected'; ?>> Cierre Diario</option>
                <option value="mes" <?php if ($filtro_tipo === 'mes') echo 'selected'; ?>> Cierre Mensual</option>
                <option value="todo" <?php if ($filtro_tipo === 'todo') echo 'selected'; ?>> Todo el Historial</option>
            </select>

            <?php if ($filtro_tipo === 'dia'): ?>
                <label>Fecha:</label>
                <input type="date" name="fecha" value="<?php echo $fecha_filtro; ?>">
            <?php elseif ($filtro_tipo === 'mes'): ?>
                <label>Mes:</label>
                <input type="month" name="mes" value="<?php echo $mes_filtro; ?>">
            <?php endif; ?>

            <button type="submit" class="btn-filter"> Filtrar</button>
            <button type="button" onclick="window.print()" class="btn-print" style="margin-left:auto;"> Imprimir Cierre / Comprobantes</button>
        </form>
    </div>

    <!-- Tarjetas de resumen del período -->
    <div class="summary-box">
        <div class="summary-card">
            <h3>TOTAL DE TICKETS / FACTURAS</h3>
            <div class="number"><?php echo $totales['total_ventas']; ?></div>
        </div>
        <div class="summary-card" style="border-left-color: #2980b9;">
            <h3>TOTAL RECAUDADO (CIERRE)</h3>
            <div class="number" style="color:#27ae60;">$<?php echo number_format($totales['total_recaudado'], 2); ?></div>
        </div>
    </div>

    <!-- Tabla compacta de facturas emitidas -->
    <div class="card">
        <h2> Resumen de Facturas Emitidas (<?php echo count($ventasConDetalle); ?>)</h2>

        <?php if (!empty($ventasConDetalle)): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">N° Ticket</th>
                        <th style="width: 20%;">Fecha y Hora</th>
                        <th style="width: 45%;">Artículos Vendidos</th>
                        <th style="width: 20%; text-align:right;">Total ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventasConDetalle as $v): ?>
                        <tr>
                            <td style="font-weight:bold;">#<?php echo str_pad($v['id_venta'], 5, '0', STR_PAD_LEFT); ?></td>
                            <td style="color:#555;"><?php echo date('d/m/Y H:i', strtotime($v['fecha_hora'])); ?></td>
                            <td style="font-size:0.9em; color:#333;">
                                <?php 
                                $resumen = [];
                                foreach ($v['items'] as $it) {
                                    $resumen[] = $it['cantidad'] . "x " . htmlspecialchars($it['nombre']);
                                }
                                echo implode(", ", $resumen);
                                ?>
                            </td>
                            <td style="text-align:right; font-weight:bold; color:#27ae60;">
                                $<?php echo number_format($v['total'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align:center; color:#888; padding:20px;">No se registraron ventas en el período seleccionado.</p>
        <?php endif; ?>
    </div>

</body>
</html>

