<?php include("permisos.php");?>
<?php
function abrirMenu($op) {
	$current_page = basename($_SERVER['SCRIPT_NAME']);
	if ($op == "1") {
		if ($current_page == "dashboard.php") {
			echo 'class="active"';
		}
	} else if ($op == "2") {
		if (($current_page == "listarSitios.php") || 
		($current_page == "listarProyectos.php") || 
		($current_page == "listarProyectosBasico.php")) {
			echo 'class="active"';
		}
	} else if ($op == "3") {
		if (($current_page == "listarCuentas.php") ||
		($current_page == "reporteCentrosCosto.php") || 		
		($current_page == "listarSubcuentas.php") || 
		($current_page == "listarFacturasVenta.php") || 
		($current_page == "listarEventos.php") || 
		($current_page == "listarOrdenesCompraClientes.php") || 
		($current_page == "listarCertificadosMaestros.php") || 
		($current_page == "listarRegimenes.php") || 
		($current_page == "listarPolizas.php")) {
			echo 'class="active"';
		}
	} else if ($op == "4") {
		if ($current_page == "listarPresupuestos.php") {
			echo 'class="active"';
		}
	} else if ($op == "5") {
		if (($current_page == "listarMateriales.php") || 
		($current_page == "listarPedidos.php") || 
		($current_page == "listarCompras.php") || 
		($current_page == "listarIngresos.php") || 
		($current_page == "listarFacturasCompra.php")) {
			echo 'class="active"';
		}
	} else if ($op == "6") {
		if (($current_page == "listarIngresos.php") || 
		($current_page == "listarEgresos.php") || 
		($current_page == "listarOrdenesTrabajo.php") || 
		($current_page == "listarConsumos.php") || 
		($current_page == "listarComputos.php") || 
		($current_page == "listarColadas.php") ||
		($current_page == "listarListasCorte.php") || 
		($current_page == "listarPackingList.php")) {
			echo 'class="active"';
		}
	} else if ($op == "7") {
		if (($current_page == "listarTareas.php") || 
		($current_page == "listarComputos.php") || 
		($current_page == "listarListasCorte.php") || 
		($current_page == "listarPackingList.php")) {
			echo 'class="active"';
		}
	} else if ($op == "8") {
		if ($current_page == "listarDespachos.php") {
			echo 'class="active"';
		}
	} else if ($op == "9") {
		if (($current_page == "listarUsuarios.php") || 
		($current_page == "listarPerfiles.php") || 
		($current_page == "listarPermisos.php")) {
			echo 'class="active"';
		}
	} else if ($op == "10") {
		if (($current_page == "listarParametros.php") || 
		($current_page == "listarLogs.php")) {
			echo 'class="active"';
		}
	}
}

?>
<div class="page-sidebar">
	<div class="main-header-left d-none d-lg-block">
		<div class="logo-wrapper"><a href="dashboard.php"><img src="assets/images/logo.jpg" width="100px" alt=""></a></div>
	</div>
	<div class="sidebar custom-scrollbar">
		<ul class="sidebar-menu">
			<li <?php abrirMenu(1);?>>
				<a class="sidebar-header" href="#">
					<i data-feather="bar-chart"></i>
					<span>Dashboard</span>
					<i class="fa fa-angle-right pull-right"></i>
				</a>
				<ul class="sidebar-submenu"><li><a href="dashboard.php"><i class="fa fa-circle"></i>General</a></li>
				</ul>
			</li>
			
			<li <?php abrirMenu(2);?>><a class="sidebar-header" href="#"><i data-feather="file-text"></i><span>Proyectos</span><i class="fa fa-angle-right pull-right"></i></a>
				<ul class="sidebar-submenu">
				<?php if(tienePermiso(272)){?><li><a href="listarSitios.php"><i class="fa fa-circle"></i>Sitios</a></li><?php }?>
				<?php if(tienePermiso(276)){?><li><a href="listarProyectos.php"><i class="fa fa-circle"></i>Proyectos</a></li><?php }?>
				<?php if(tienePermiso(390)){?><li><a href="listarProyectosBasico.php"><i class="fa fa-circle"></i>Proyectos Básico</a></li><?php }?>
				</ul>
			</li>
			
			<li <?php abrirMenu(3);?>><a class="sidebar-header" href="#"><i data-feather="cloud-lightning"></i><span>Administración</span><i class="fa fa-angle-right pull-right"></i></a>
				<ul class="sidebar-submenu">
				<?php if(tienePermiso(259)){?><li><a href="listarCuentas.php"><i class="fa fa-circle"></i>Cuentas</a></li><?php }?>
				<?php if(tienePermiso(391)){?><li><a href="reporteCentrosCosto.php"><i class="fa fa-circle"></i>Centros de Costo</a></li><?php }?>
				<?php if(tienePermiso(263)){?><li><a href="listarSubcuentas.php"><i class="fa fa-circle"></i>Subcuentas</a></li><?php }?>
				<?php if(tienePermiso(335)){?><li><a href="listarFacturasVenta.php"><i class="fa fa-circle"></i>Facturas Venta</a></li><?php }?>
				<?php if(tienePermiso(367)){?><li><a href="listarEventos.php"><i class="fa fa-circle"></i>Calendario Eventos</a></li><?php }?>
				<?php if(tienePermiso(369)){?><li><a href="listarOrdenesCompraClientes.php"><i class="fa fa-circle"></i>Ordenes</a></li><?php }?>
				<?php if(tienePermiso(373)){?><li><a href="listarCertificadosMaestros.php"><i class="fa fa-circle"></i>Certificados</a></li><?php }?>
				<?php if(tienePermiso(379)){?><li><a href="listarPolizas.php"><i class="fa fa-circle"></i>Polizas</a></li><?php }?>
				<?php if(tienePermiso(386)){?><li><a href="listarRegimenes.php"><i class="fa fa-circle"></i>Regimenes</a></li><?php }?>
				</ul>
			</li>
			
      <li <?php abrirMenu(4);?>><a class="sidebar-header" href="#"><i data-feather="shopping-bag"></i><span>Comercial</span><i class="fa fa-angle-right pull-right"></i></a>
				<ul class="sidebar-submenu">
				<?php if(tienePermiso(267)){?><li><a href="listarPresupuestos.php"><i class="fa fa-circle"></i>Presupuestos</a></li><?php }?>
				</ul>
			</li>
			<li <?php abrirMenu(5);?>><a class="sidebar-header" href="#"><i data-feather="flag"></i><span>Compras</span><i class="fa fa-angle-right pull-right"></i></a>
				<ul class="sidebar-submenu">
				<?php if(tienePermiso(285)){?><li><a href="listarMateriales.php"><i class="fa fa-circle"></i>Conceptos</a></li><?php }?>
				<?php if(tienePermiso(296)){?><li><a href="listarPedidos.php"><i class="fa fa-circle"></i>Pedidos</a></li><?php }?>
				<?php if(tienePermiso(297)){?><li><a href="listarCompras.php"><i class="fa fa-circle"></i>Compras</a></li><?php }?>
				<?php if(tienePermiso(308)){?><li><a href="listarIngresos.php"><i class="fa fa-circle"></i>Ingresos</a></li><?php }?>
				<?php if(tienePermiso(334)){?><li><a href="listarFacturasCompra.php"><i class="fa fa-circle"></i>Facturas Compra</a></li><?php }?>
				</ul>
			</li>
			<li <?php abrirMenu(6);?>><a class="sidebar-header" href="#"><i data-feather="layout"></i><span>Producción</span><i class="fa fa-angle-right pull-right"></i></a>
				<ul class="sidebar-submenu">
				<?php if(tienePermiso(308)){?><li><a href="listarIngresos.php"><i class="fa fa-circle"></i>Ingresos</a></li><?php }?>
				<?php if(tienePermiso(309)){?><li><a href="listarEgresos.php"><i class="fa fa-circle"></i>Egresos</a></li><?php }?>
				<?php if(tienePermiso(314)){?><li><a href="listarOrdenesTrabajo.php"><i class="fa fa-circle"></i>Ordenes de Trabajo</a></li><?php }?>
				<?php if(tienePermiso(314)){?><li><a href="listarConsumos.php"><i class="fa fa-circle"></i>Consumos</a></li><?php }?>
				<?php if(tienePermiso(289)){?><li><a href="listarComputos.php?prod=1"><i class="fa fa-circle"></i>Cómputos</a></li><?php }?>		
				<?php if(tienePermiso(313)){?><li><a href="listarColadas.php"><i class="fa fa-circle"></i>Coladas</a></li><?php }?>			
				<?php if(tienePermiso(314)){?><li><a href="listarListasCorte.php?prod=1"><i class="fa fa-circle"></i>Listas de Corte</a></li><?php }?>
				<?php if(tienePermiso(347)){?><li><a href="listarPackingList.php?prod=1"><i class="fa fa-circle"></i>Packing List</a></li><?php }?>				
				</ul>
			</li>
			<li <?php abrirMenu(7);?>><a class="sidebar-header" href="#"><i data-feather="send"></i><span>Ingeniería</span><i class="fa fa-angle-right pull-right"></i></a>
				<ul class="sidebar-submenu">
				<?php if(tienePermiso(280)){?><li><a href="listarTareas.php"><i class="fa fa-circle"></i>Tareas</a></li> <?php }?>
				<?php if(tienePermiso(289)){?><li><a href="listarComputos.php"><i class="fa fa-circle"></i>Cómputos</a></li><?php }?>		
				<?php if(tienePermiso(314)){?><li><a href="listarListasCorte.php"><i class="fa fa-circle"></i>Listas de Corte</a></li><?php }?>
				<?php if(tienePermiso(347)){?><li><a href="listarPackingList.php"><i class="fa fa-circle"></i>Packing List</a></li><?php }?>
				</ul>
			</li>
			<li <?php abrirMenu(8);?>><a class="sidebar-header" href="#"><i data-feather="truck"></i><span>Logística</span><i class="fa fa-angle-right pull-right"></i></a>
				<ul class="sidebar-submenu">
				<?php if(tienePermiso(357)){?><li><a href="listarDespachos.php"><i class="fa fa-circle"></i>Despachos</a></li><?php }?>
				</ul>
			</li>
			<li <?php abrirMenu(9);?>><a class="sidebar-header" href="#"><i data-feather="lock"></i><span>Seguridad</span><i class="fa fa-angle-right pull-right"></i></a>
				<ul class="sidebar-submenu"><?php
					if(tienePermiso(1)){?><li><a href="listarUsuarios.php"><i class="fa fa-circle"></i>Usuarios</a></li><?php }
					if(tienePermiso(2)){?><li><a href="listarPerfiles.php"><i class="fa fa-circle"></i>Perfiles</a></li><?php }
					if(tienePermiso(3)){?><li><a href="listarPermisos.php"><i class="fa fa-circle"></i>Permisos</a></li><?php }?>
				</ul>
			</li>

			<li <?php abrirMenu(10);?>><a class="sidebar-header" href="#"><i data-feather="settings"></i><span>Configuración</span><i class="fa fa-angle-right pull-right"></i></a>
				<ul class="sidebar-submenu">
					<?php
					if (tienePermiso(13)) { ?><li><a href="listarParametros.php"><i class="fa fa-circle"></i>Parámetros</a></li><?php }
					if (tienePermiso(326)){?><li><a href="listarLogs.php"><i class="fa fa-circle"></i>Auditoría</a></li><?php }?>
				</ul>
			</li>
		</ul>
	</div>
</div>