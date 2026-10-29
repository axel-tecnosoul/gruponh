"use strict";

/**
 * Cotización del dólar: permite elegir el tipo de dólar y toma siempre el valor
 * del día anterior. Si ese día no hay dato (argentinadatos solo publica en días
 * hábiles) retrocede varios días hacia atrás.
 *
 * Opciones de montar():
 *   input, badge, info      selectores jQuery del input y de los textos de estado
 *   selectTipo              selector del tipo de dólar (opcional)
 *   tipoInicial             tipo con el que se precarga (default 'mayorista')
 *   fechaBase               fecha de referencia para el "día anterior" (default hoy)
 *   noSobrescribir          no pisa en la carga inicial un valor ya guardado.
 *                           Si el usuario cambia el tipo a mano, igual se aplica.
 *   onFallo                 callback cuando no se pudo obtener ninguna cotización
 *
 * Uso:
 *   var cot = CotizacionDolar.montar({
 *     input: '#cotizacion',
 *     badge: '#estadoDolar',
 *     info: '#infoCotizacion',
 *     selectTipo: '#tipo_dolar',
 *     tipoInicial: 'mayorista'
 *   });
 *   cot.cargar();
 *   cot.mostrar(true);
 */
var CotizacionDolar = (function () {
    var TIPOS = {
        oficial: 'Dólar Oficial',
        blue: 'Dólar Blue',
        bolsa: 'Dólar Bolsa (MEP)',
        contadoconliqui: 'Dólar CCL',
        mayorista: 'Dólar Mayorista'
    };

    // Días hábiles hacia atrás que se prueban antes de rendirse.
    var DIAS_RETRY = 7;

    var API = 'https://api.argentinadatos.com/v1/cotizaciones/dolares/';

    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function nombreTipo(tipo) {
        return TIPOS[tipo] || tipo;
    }

    function formatear(n, decimales) {
        var v = parseFloat(n);
        if (isNaN(v)) return '';
        return v.toLocaleString('es-AR', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        });
    }

    function fechasHaciaAtras(base, dias) {
        var out = [];
        for (var i = 1; i <= dias; i++) {
            out.push(new Date(base.getFullYear(), base.getMonth(), base.getDate() - i));
        }
        return out;
    }

    function cotizar(tipo, fecha) {
        var url = API + tipo + '/' + fecha.getFullYear() + '/' + pad(fecha.getMonth() + 1) + '/' + pad(fecha.getDate());
        return fetch(url, {
            headers: {
                'Accept': 'application/json'
            }
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function (d) {
            if (Array.isArray(d)) d = d[0];
            if (!d || !d.venta) throw new Error('Sin venta');
            return d;
        });
    }

    function montar(cfg) {
        var input = $(cfg.input);
        var badge = $(cfg.badge);
        var info = $(cfg.info);
        var selectTipo = cfg.selectTipo ? $(cfg.selectTipo) : $();
        var tipoInicial = cfg.tipoInicial || 'mayorista';
        // fechaBase permite pedir el día anterior respecto de una fecha concreta.
        var fechaBase = cfg.fechaBase ? new Date(cfg.fechaBase) : new Date();
        // noSobrescribir: no pisa el valor ya guardado en la carga inicial.
        // Si el usuario cambia el tipo de dólar a mano, el valor sí se aplica (ver cargar(true)).
        var noSobrescribir = !!cfg.noSobrescribir;
        //onFallo: callback cuando no se pudo obtener ninguna cotización.
        var onFallo = typeof cfg.onFallo === 'function' ? cfg.onFallo : function () {};

        if (selectTipo.length && selectTipo.find('option').length === 0) {
            Object.keys(TIPOS).forEach(function (k) {
                selectTipo.append(new Option(TIPOS[k], k));
            });
        }
        // Aplicar siempre el tipo inicial al montar el control. Así el navegador
        // no conserva una selección anterior (por ejemplo, Oficial) como default.
        if (selectTipo.length) {
            selectTipo.val(tipoInicial);
        }

        function mostrar(visible) {
            if (selectTipo.length) {
                selectTipo.toggle(!!visible);
            }
        }

        function cargar(forzado) {
            var tipo = (selectTipo.length && selectTipo.val()) || tipoInicial;
            var fechas = fechasHaciaAtras(fechaBase, DIAS_RETRY);

            // Escribir el valor obtenido: siempre, salvo en pantallas que solo referencian
            // un valor ya guardado (y entonces únicamente si el usuario pidió el tipo a mano).
            var escribir = !noSobrescribir || !!forzado;
            // Vaciar el input si no se pudo obtener ninguna cotización: solo en las pantallas
            // de alta, para no perder la cotización guardada del certificado.
            var limpiarEnFallo = !noSobrescribir;

            badge.text('Cargando...').removeClass('text-danger text-success').addClass('text-secondary');
            if (escribir) {
                input.prop('readonly', true);
            }

            function intentar(idx) {
                if (idx >= fechas.length) {
                    badge.text('Error al obtener').removeClass('text-secondary text-success').addClass('text-danger');
                    info.html('<span class="text-danger">No se pudo obtener la cotización. Ingrésela manualmente.</span>');
                    if (limpiarEnFallo) {
                        input.val('').prop('readonly', false);
                    }
                    onFallo();
                    return;
                }

                cotizar(tipo, fechas[idx])
                    .then(function (d) {
                        if (escribir) {
                            input.val(parseFloat(d.venta).toFixed(2));
                        }
                        badge.html(nombreTipo(tipo)).removeClass('text-secondary text-danger').addClass('text-success');
                        info.html(
                            'Compra: <strong>$' + (formatear(d.compra, 2) || '-') + '</strong>' +
                            ' | Venta: <strong>$' + formatear(d.venta, 2) + '</strong>' +
                            ' | Fecha: <strong>' + d.fecha + '</strong>'
                        );
                        if (escribir) {
                            input.prop('readonly', false);
                        }
                    })
                    .catch(function () {
                        intentar(idx + 1);
                    });
            }

            intentar(0);
        }

        if (selectTipo.length) {
            selectTipo.on('change', function () {
                // El usuario eligió el tipo explícitamente: se aplica el valor.
                cargar(true);
            });
        }

        return {
            cargar: cargar,
            mostrar: mostrar,
            TIPOS: TIPOS
        };
    }

    return {
        TIPOS: TIPOS,
        montar: montar,
        formatear: formatear
    };
})();
