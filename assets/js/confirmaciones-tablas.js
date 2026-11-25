function prepararConfirmacion(enlace, modal, url, fila, opciones) {
  var boton = $(enlace);
  boton.attr('data-toggle', 'modal').attr('data-target', modal).attr('data-url-confirmacion', url);
  boton.data('fila-confirmacion', $(fila)[0]);
  boton.data('tabla-confirmacion', $(fila).closest('table')[0]);
  boton.data('opciones-confirmacion', opciones || {});
}

function iniciarCargaConfirmaciones(tabla) {
  // Una respuesta anterior no puede reactivar acciones de otra cabecera.
  limpiarConfirmacionesTabla(tabla);
  var elemento = $(tabla);
  var version = (elemento.data('version-confirmacion') || 0) + 1;
  elemento.data('version-confirmacion', version).attr('data-cargando-confirmacion', '1');
  if ($.fn.dataTable.isDataTable(elemento[0])) {
    elemento.DataTable().clear().draw();
  }
  return version;
}

function finalizarCargaConfirmaciones(tabla, version) {
  var elemento = $(tabla);
  if (elemento.data('version-confirmacion') !== version) {
    return false;
  }
  elemento.removeAttr('data-cargando-confirmacion');
  return true;
}

function limpiarConfirmacionesTabla(tabla) {
  var elemento = $(tabla)[0];
  if (!elemento) {
    return;
  }
  $(elemento).find('tbody tr.selected').removeClass('selected');
  $('[data-url-confirmacion]').each(function() {
    if ($(this).data('tabla-confirmacion') === elemento) {
      $(this).removeAttr('data-url-confirmacion').removeData('fila-confirmacion tabla-confirmacion opciones-confirmacion').attr('data-target', '#');
    }
  });
  $('.modal-confirmacion-tabla').each(function() {
    if ($(this).attr('data-tabla-padre') === '#' + elemento.id) {
      limpiarConfirmacionesTabla($(this).attr('data-tabla-origen'));
    }
    var contexto = $(this).data('contexto-confirmacion');
    if (contexto && contexto.tabla === elemento) {
      $(this).find('.confirmar-accion').removeAttr('href');
      $(this).removeData('contexto-confirmacion').modal('hide');
    }
  });
}

(function() {
  function obtenerContexto(modal, disparador) {
    var boton = $(disparador);
    var fila = boton.data('fila-confirmacion');
    var seleccionRequerida = !!fila;
    if (!fila) {
      fila = boton.closest('tr')[0];
    }
    var url = boton.attr('data-url-confirmacion');
    var tabla = $(fila).closest('table');
    if (!fila || !document.documentElement.contains(fila) || !url || boton.attr('data-target') !== '#' + modal.attr('id')) {
      return null;
    }
    if (tabla.attr('data-cargando-confirmacion') === '1' || boton.hasClass('disabled') || boton.attr('aria-disabled') === 'true') {
      return null;
    }
    if (seleccionRequerida && !$(fila).hasClass('selected')) {
      return null;
    }
    var tablaPadre = modal.attr('data-tabla-padre');
    var padre = $();
    if (tablaPadre) {
      padre = $(tablaPadre).find('tbody tr.selected');
      if (padre.length !== 1 || tabla[0] !== $(modal.attr('data-tabla-origen'))[0]) {
        return null;
      }
    }
    var destino = new URL(url, document.baseURI);
    var ruta = new URL(modal.attr('data-ruta-confirmacion'), document.baseURI);
    if (destino.origin !== ruta.origin || destino.pathname !== ruta.pathname || !/^[1-9][0-9]*$/.test(destino.searchParams.get('id') || '')) {
      return null;
    }
    return { boton: disparador, fila: fila, tabla: tabla[0], padre: padre[0], url: url, contenido: fila.outerHTML, opciones: boton.data('opciones-confirmacion') || {} };
  }

  $(document).on('show.bs.modal', '.modal-confirmacion-tabla', function(evento) {
    var modal = $(this);
    modal.find('.confirmar-accion').removeAttr('href');
    modal.removeData('contexto-confirmacion');
    var contexto = obtenerContexto(modal, evento.relatedTarget);
    if (!contexto) {
      evento.preventDefault();
      return;
    }
    modal.data('contexto-confirmacion', contexto);
    var cuerpo = modal.find('.modal-body');
    if (cuerpo.data('mensaje-confirmacion') === undefined) {
      cuerpo.data('mensaje-confirmacion', cuerpo.text());
    }
    cuerpo.text(contexto.opciones.mensaje || cuerpo.data('mensaje-confirmacion'));
    modal.find('.confirmar-accion').toggle(!contexto.opciones.bloqueado);
    if (!contexto.opciones.bloqueado) {
      modal.find('.confirmar-accion').attr('href', contexto.url);
    }
  });

  $(document).on('click', '.modal-confirmacion-tabla .confirmar-accion', function(evento) {
    // Volver a comprobar la fila que abrió el modal antes de navegar.
    var modal = $(this).closest('.modal');
    var anterior = modal.data('contexto-confirmacion');
    var actual = null;
    if (anterior) {
      actual = obtenerContexto(modal, anterior.boton);
    }
    if (!actual || actual.opciones.bloqueado || actual.fila !== anterior.fila || actual.padre !== anterior.padre || actual.url !== anterior.url || actual.contenido !== anterior.contenido) {
      evento.preventDefault();
      $(this).removeAttr('href');
      modal.modal('hide');
    }
  });

  $(document).on('hidden.bs.modal', '.modal-confirmacion-tabla', function() {
    $(this).find('.confirmar-accion').removeAttr('href');
    $(this).removeData('contexto-confirmacion');
  });

  $(document).on('preXhr.dt draw.dt destroy.dt', 'table', function() {
    limpiarConfirmacionesTabla(this);
  });
})();
