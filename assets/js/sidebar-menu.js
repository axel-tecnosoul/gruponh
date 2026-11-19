$.sidebarMenu = function(menu) {
  var animationSpeed = 300,
      subMenuSelector = '.sidebar-submenu';
  $(menu).on('click', 'li a', function(e) {
    var $this = $(this);
    var checkElement = $this.next();
    if (checkElement.is(subMenuSelector) && checkElement.is(':visible')) {
      checkElement.slideUp(animationSpeed, function() {
        checkElement.removeClass('menu-open');
      });
      checkElement.parent("li").removeClass("active");
    }
    else if ((checkElement.is(subMenuSelector)) && (!checkElement.is(':visible'))) {
      var parent = $this.parents('ul').first();
      var ul = parent.find('ul:visible').slideUp(animationSpeed);
      ul.removeClass('menu-open');
      var parent_li = $this.parent("li");
      checkElement.slideDown(animationSpeed, function() {
        checkElement.addClass('menu-open');
        parent.find('li.active').removeClass('active');
        parent_li.addClass('active');
      });
    }
  });
}


$.sidebarMenu($('.sidebar-menu'))
$nav = $('.page-sidebar');
$header = $('.page-main-header');
$toggle_nav_top = $('#sidebar-toggle');
var menuLateralManual = $('body').attr('data-sidebar-manual') === 'true';
var enlaceMenuLateral = $toggle_nav_top.closest('button, a');

function aplicarEstadoMenuLateral(oculto) {
  $nav.toggleClass('open', oculto);
  $header.toggleClass('open', oculto);
  $toggle_nav_top.toggleClass('open', oculto);
  if (menuLateralManual) {
    $('body').toggleClass('menu-lateral-oculto', oculto);
  }
  var descripcion = 'Mostrar menú lateral';
  if (!oculto) {
    descripcion = 'Ocultar menú lateral';
  }
  enlaceMenuLateral.attr({ role: 'button', 'aria-expanded': String(!oculto), 'aria-label': descripcion });
}

function ventanaAngostaMenuLateral() {
  return $(window).width() + 17 <= 991;
}

var menuEnVentanaAngosta = ventanaAngostaMenuLateral();
aplicarEstadoMenuLateral(menuLateralManual || menuEnVentanaAngosta);
enlaceMenuLateral.on('click', function(evento) {
  evento.preventDefault();
  aplicarEstadoMenuLateral(!$nav.hasClass('open'));
});

// Ocultar solo al entrar al tamaño angosto; los demás cambios respetan la elección manual.
$(window).on('resize', function() {
  var ventanaAngosta = ventanaAngostaMenuLateral();
  if (ventanaAngosta && !menuEnVentanaAngosta) {
    aplicarEstadoMenuLateral(true);
  }
  menuEnVentanaAngosta = ventanaAngosta;
});
