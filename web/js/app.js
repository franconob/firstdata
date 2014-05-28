/**
 * Created by fherrero on 5/20/14.
 */
$(function () {
    PNotify.prototype.options.styling = "fontawesome";
    $(document).ajaxStart(function () {
        pnotify = notify({
            message: "Cargando transacciones",
            icon: "fa fa-refresh fa-spin"
        })
    });
    $(document).ajaxStop(function () {
        pnotify.remove();
    });
    var waT = $('#transactions').WATable({
        url: '/reportes',
        filter: true,
        pageSize: [10],
        columnPicker: true,
        checkboxes: true,
        checkAllToggle: true,
        transition: 'fade',
        types: {
            string: {
                placeHolder: "Filtro..."
            },
            date: {
                datePicker: true,
                format: 'd/M/yyyy H:m:s'
            }
        },
        actions: {
            custom: [
                $('<a href="/reportes/refund" id="action-refund"><span class="glyphicon glyphicon-usd"></span>&nbsp; Refund</a>'),
            ]
        },
        tableCreated: function(e) {
            $('#total-transactions').html(waT.getData().rows.length);
            $('#total-amout').html(waT.getData().totalAmount);
        }
    }).data('WATable');

    $('body').on('click', '#action-refund', function (e) {
        e.preventDefault();
        var transactions = waT.getData({checked: true});
        if (transactions.rows.length == 0) {
            notify({
                title: "Aviso",
                message: "No se seleccionaron transacciones",
                type: "warning",
                hide: true
            });
            return;
        }
        $.ajax({
            url: '/reportes/refund',
            method: 'POST',
            data: { transactions: transactions.rows },
            success: function (resp) {
                if (resp.success) {
                    notify({
                        title: "Refound realizado",
                        message: "Refound realizado correctamente",
                        type: "success",
                        icon: "fa fa-check",
                        hide: true
                    });
                    waT.update();
                } else {
                    notify({
                        title: "Refund error",
                        message: "La operación no pudo realizarse. Motivo: " + resp.reason + ' ' + resp.debug,
                        type: 'error',
                        icon: 'fa fa-bomb',
                        hide: true
                    });
                }

            },
            error: function (err) {
                notify({
                    title: "Error",
                    message: "Ocurrio un error procesando la transacción",
                    type: "error",
                    icon: "fa fa-bomb"
                })
            },
            dataType: 'json'
        })
    });

    function notify(options) {
        return new PNotify({
            title: options.title || "Ejecutando...",
            text: options.message,
            icon: options.icon || "",
            type: options.type || "info",
            hide: options.hide || false
        })
    }
});


