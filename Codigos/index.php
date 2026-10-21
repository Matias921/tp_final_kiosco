<?php
/**
 * ====================================================================
 * ARCHIVO: index.php
 * OBJETIVO: Panel de control central exclusivo para el ROL ADMINISTRADOR.
 *           Permite el acceso directo a los 4 módulos principales:
 *           Caja, Almacén, Proveedores y Cierre de Caja / Facturación.
 * ====================================================================
 */

session_start();
require_once 'conexion.php';

// ====================================================================
// CONTROL DE ACCESO / SEGURIDAD:
// Si no hay sesión o el rol no es 'admin', se expulsa al usuario al login.
// ====================================================================
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Quiosco</title>
    <style>
        /* ==================================================================== */
        /* CONFIGURACIÓN GENERAL DEL CUERPO Y FONDO DE PANTALLA                 */
        /* ==================================================================== */
        body { 
            font-family: Arial, sans-serif; /* Usa la fuente Arial (muy legible) */
            background: #f4f6f9;            /* Color gris muy claro para el fondo */
            margin: 0;                      /* Quita los márgenes por defecto del navegador */
            padding: 25px;                  /* Deja un espacio de 25px alrededor de toda la página */
        }

        /* ==================================================================== */
        /* BARRA DE NAVEGACIÓN SUPERIOR (EL MENÚ)                               */
        /* ==================================================================== */
        .nav { 
            background: #2c3e50;            /* Color azul oscuro/grisáceo */
            color: white;                   /* Color del texto adentro de la barra */
            padding: 15px 20px;             /* Espacio interno (arriba/abajo y lados) */
            border-radius: 8px;             /* Redondea las esquinas de la barra */
            display: flex;                  /* Permite organizar los elementos en fila fácilmente */
            justify-content: space-between; /* Empuja el título a la izquierda y botones a la derecha */
            align-items: center;            /* Centra los elementos verticalmente */
            margin-bottom: 25px;            /* Deja espacio por debajo de la barra */
            flex-wrap: wrap;                /* Si la pantalla es chica, tira los elementos abajo */
            gap: 10px;                      /* Espacio entre los elementos si se amontonan */
        }
        
        /* Botón de Cerrar Sesión en la barra de navegación */
        .nav a { 
            color: white; 
            text-decoration: none;          /* Saca el subrayado del link */
            font-weight: bold; 
            background: #d63031;            /* Fondo rojo */
            padding: 6px 14px; 
            border-radius: 4px; 
            font-size: 0.9em; 
        }
        .nav a:hover { background: #b71c1c; } /* Rojo más oscuro al pasar el mouse */

        /* Texto de bienvenida */
        .welcome { margin-bottom: 25px; color: #333; }

        /* ==================================================================== */
        /* ESTRUCTURA Y MAQUETACIÓN (GRILLAS Y TARJETAS DEL MENÚ PRINCIPAL)     */
        /* ==================================================================== */
        /* Contenedor tipo grilla para los módulos (se adapta automáticamente al ancho) */
        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); /* Crea columnas flexibles de mínimo 240px */
            gap: 20px;                      /* Espacio entre cada tarjeta de la grilla */
        }

        /* Tarjeta de cada módulo (Caja, Productos, Proveedores, Cierre) */
        .card { 
            background: white; 
            padding: 30px 20px; 
            border-radius: 8px;             /* Bordes redondeados */
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); /* Sombra sutil para dar profundidad */
            text-align: center;             /* Centra todo el contenido de la tarjeta */
            text-decoration: none;          /* Saca el subrayado del link */
            color: #2c3e50;                 /* Color oscuro para el texto principal */
            font-weight: bold; 
            font-size: 1.15em; 
            transition: 0.2s;               /* Animación suave para cuando le pases el mouse encima */
            border-top: 4px solid #2980b9;  /* Franja gruesa de color en el borde de arriba */
        }
        
        /* Efecto al pasar el mouse sobre la tarjeta */
        .card:hover { 
            transform: translateY(-4px);    /* Levanta la tarjeta 4 píxeles hacia arriba */
            box-shadow: 0 6px 15px rgba(0,0,0,0.15); /* Aumenta y oscurece la sombra */
            color: #2980b9;                 /* Cambia el color del texto a azul claro */
        }

        /* Ícono emoji grande dentro de la tarjeta */
        .card-icon { font-size: 2.5em; margin-bottom: 12px; display: block; }

        /* Texto descriptivo chico debajo del título de la tarjeta */
        .card-desc { 
            font-size: 0.8em; 
            font-weight: normal; 
            color: #666;                    /* Color gris claro */
            margin-top: 8px; 
        }
    </style>
</head>
<body>

    <!-- Barra de navegación superior con identificación del usuario autenticado -->
    <div class="nav">
        <div>
            <strong> Panel de Administración</strong> | <?php echo htmlspecialchars($_SESSION['nombre']); ?>
        </div>
        <a href="logout.php">Cerrar Sesión</a>
    </div>

    <!-- Encabezado de bienvenida -->
    <div class="welcome">
        <h2>Bienvenido al Sistema</h2>
        <p>Selecciona el módulo al que deseas acceder:</p>
    </div>

    <!-- Cuadrícula de módulos del quiosco -->
    <div class="grid">
        <!-- 1. Módulo de Caja / Punto de Venta -->
        <a href="ventas.php" class="card" style="border-color: #27ae60;">
            <span class="card-icon"></span>
            Punto de Venta (Caja)
            <div class="card-desc">Cobrar ventas a clientes y emitir tickets</div>
        </a>

        <!-- 2. Módulo de Almacén / Inventario de Productos -->
        <a href="productos.php" class="card" style="border-color: #2980b9;">
            <span class="card-icon"></span>
            Almacén e Inventario
            <div class="card-desc">Gestionar catálogo de productos, precios y stock</div>
        </a>

        <!-- 3. Módulo de Proveedores y Categorías de Mercadería -->
        <a href="proveedores.php" class="card" style="border-color: #8e44ad;">
            <span class="card-icon"></span>
            Proveedores y Rubros
            <div class="card-desc">Directorio de distribuidoras, contactos y categorías</div>
        </a>

        <!-- 4. Módulo de Cierre de Caja y Facturación Consolidada -->
        <a href="cierre_caja.php" class="card" style="border-color: #f39c12;">
            <span class="card-icon"></span>
            Cierre de Caja y Facturas
            <div class="card-desc">Cierre diario, mensual e impresión de comprobantes</div>
        </a>
    </div>

</body>
</html>

