/**
 * Created by fherrero on 5/20/14.
 */
var waT;

function notify(options) {
    return new PNotify({
        title: options.title || "Ejecutando...",
        text: options.message || "",
        icon: options.icon || "",
        type: options.type || "info",
        hide: options.hide || false
    })
}
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
    waT = $('#transactions').WATable({
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
                $('<a href="/reportes/refund" id="action-refund"><i class="fa fa-usd"></i>&nbsp;Refund</a>'),
                $('<a href="/transactions-export" id="action-export"><i class="fa fa-file-text"></i>&nbsp;Exportar CSV</a>')
            ]
        },
        tableCreated: function (e) {
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
            url: '/transactions/refund',
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

    $('#action-export').click(function (e) {
        e.preventDefault();
        window.location.assign('/transactions-export');
    });


});

var app = angular.module('firstdata', ['ui.bootstrap', 'ui.utils']);
app.value("jQuery", $);
app.value("notify", notify);

app.controller('TransactionCtrl', ['$scope', '$modal', function ($scope, $modal) {

    $scope.openForm = function (transaction_type) {
        var modalInstance = $modal.open({
            templateUrl: transaction_type + '.html',
            controller: 'FormModalCtrl',
            resolve: {
                transaction_type: function () {
                    return transaction_type;
                }
            }
        });
    }
}]);

app.controller('FormModalCtrl', ['$scope', '$modalInstance', '$http', 'transaction_type', 'jQuery', 'notify', function ($scope, $modalInstance, $http, transaction_type, jQuery, notify) {
    $scope.transaction = {};

    $scope.submit = function () {
        var promise = $http.post('/transactions/' + transaction_type, { transactions: $scope.transaction });
        promise.success(function (data, status) {
            if (true == data.success) {
                $modalInstance.dismiss('cancel');
                notify({
                    title: "Operación realizada con éxito",
                    type: 'success',
                    hide: true,
                    icon: 'fa fa-check'
                });
                jQuery('#transactions').data('WATable').update();
            } else {
                notify({
                    title: "Ocurrió un error procesando la transacción",
                    message: "La operación no pudo realizarse. Motivo: " + data.reason + ' ' + data.debug,
                    type: 'error',
                    hide: true,
                    icon: 'fa fa-bomb'
                });
            }
        });
        promise.error(function (err) {
            notify({
                title: "Ocurrió un error procesando la transacción",
                type: 'error',
                hide: true,
                icon: 'fa fa-bomb'
            });
        })
    };

    $scope.cancel = function () {
        $modalInstance.dismiss('cancel');
    }
}]);



