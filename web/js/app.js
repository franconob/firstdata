/**
 * Created by fherrero on 5/20/14.
 */
PNotify.prototype.options.styling = "fontawesome";

var waT;
var app = angular.module('firstdata', ['ui.bootstrap', 'ui.utils']);

app.value('numeral', numeral);

app.factory('notify', function () {
    return function (options) {
        return new PNotify({
            title: options.title || "Ejecutando...",
            text: options.message || "",
            icon: options.icon || "",
            type: options.type || "info",
            hide: options.hide || false
        });
    }
});

app.directive('firstdataGrid', function ($compile, numeral, notify, $modal, $http) {
    return {
        restrict: 'E',
        scope: {
            totalRecords: '=total',
            totalAmount: '='
        },
        link: function (scope, element, attrs) {
            scope.totalRecords = 0;
            var initial = true;

            var currentNotify = notify({
                title: "Ejecutando..",
                message: "Cargando transacciones",
                icon: "fa fa-refresh fa-spin"
            });

            var grid = element.WATable({
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
                tableCreated: function () {
                    scope.$emit('gridCreated', {grid: grid, gridObj: grid.data('WATable'), initial: initial});
                    currentNotify.remove();
                }
            });

            scope.$on('gridCreated', function (event, args) {
                $compile(args.grid.contents())(scope);
                initial = false;
                scope.grid = args.gridObj;
                updateTotalRecords(args.initial);
                updateTotalAmount(args.initial);
            });

            var updateTotalRecords = function (initial) {
                scope.$apply(function () {
                    if (initial) {
                        scope.totalRecords = scope.grid.getData().rows.length;
                    } else {
                        scope.totalRecords = scope.grid.getData(false, true).rows.length;
                    }
                });
            };

            var updateTotalAmount = function (initial) {
                scope.$apply(function () {
                    if (initial) {
                        scope.totalAmount = scope.grid.getData().totalAmount;
                    } else {
                        var filteredRows = scope.grid.getData(false, true).rows;
                        var total = 0;
                        angular.forEach(filteredRows, function (row) {
                            total += numeral().unformat(row.Amount);
                        });
                        scope.totalAmount = total;
                    }
                });
            };

            scope.processTransaction = function (id, config) {
                scope._config = config;
                var rows = scope.grid.getData().rows;
                var transaction = Lazy(rows).findWhere({id: id});
                $modal.open({
                    templateUrl: config.template ? config.template : "tagged.html",
                    size: 'md',
                    resolve: {
                        _config: function () {
                            return scope._config;
                        },
                        transaction: function () {
                            return transaction;
                        }
                    },
                    controller: function ($scope, $modalInstance, _config, transaction, $http) {
                        $scope._config = _config;
                        $scope.transaction = transaction;
                        $scope.maxAmount = transaction.Amount;
                        /** @namespace $scope.transaction.Amount */
                        $scope.amount = numeral().unformat($scope.transaction.Amount);

                        $scope.checkLimitAmount = function (value, max, form) {
                            var max = numeral().unformat(max);
                            var value = numeral().unformat(value);
                            form.amount.$error.max = false;
                            if (value > max) {
                                form.amount.$error.max = true;
                                form.amount.$invalid = true;
                                form.$invalid = true;
                            }
                        };

                        $scope.submit = function () {
                            $http.post('/transactions/' + _config.action, {transactions: {
                                transaction_tag: $scope.transaction['Tag'],
                                amount: $scope.transaction.amount,
                                authorization_num: $scope.transaction['Auth No']
                            }}).success(function (data, status) {
                                if (data.success) {
                                    notify({
                                        title: "Operación realizada con éxito",
                                        type: 'success',
                                        hide: true,
                                        icon: 'fa fa-check'
                                    })
                                }
                                $modalInstance.dismiss('ok');
                                scope.grid.update();
                                notify({
                                    title: "Ejecutando..",
                                    message: "Cargando transacciones",
                                    icon: "fa fa-refresh fa-spin",
                                    hide: true
                                });
                            })
                        };

                        $scope.cancel = function () {
                            $modalInstance.dismiss('cancel');
                        }
                    }
                })
            }
        }
    }
});

app.controller('TransactionCtrl', ['$scope', '$modal', '$window', function ($scope, $modal, $window) {
    $scope.nbTransactions = 0;
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
    };

    $scope.exportCSV = function () {
        $window.location.assign('/transactions-export');
        return false;
    }
}]);

app.controller('FormModalCtrl', ['$scope', '$modalInstance', '$http', 'transaction_type', 'notify', function ($scope, $modalInstance, $http, transaction_type, notify) {
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
                angular.element('#transactions').data('WATable').update();
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


$(function () {
    $('body').on('click', '.multi-action', function (e) {
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
        var transaction_type = $(this).data('transaction');
        $.ajax({
            url: '/transactions/' + transaction_type,
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


