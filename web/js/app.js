/**
 * Created by fherrero on 5/20/14.
 */
PNotify.prototype.options.styling = "fontawesome";


function __notify(options) {
    return new PNotify({
        title: options.title || "Ejecutando...",
        text: options.message || "",
        icon: options.icon || "",
        type: options.type || "info",
        hide: options.hide || false
    });
}

$(document).ajaxStart(function () {
    globalNotify = __notify({
        title: "Ejecutando..",
        message: "Cargando transacciones",
        icon: "fa fa-refresh fa-spin"
    });
});

$(document).ajaxStop(function () {
    globalNotify.remove();
});
var waT;
var app = angular.module('firstdata', ['ui.bootstrap', 'ui.utils', 'angularSpinner', 'angularMoment']);

app.value('numeral', numeral);

app.factory('firstDataInterceptor', ["$q", "$rootScope", function ($q, $rootScope) {
    return {
        request: function (config) {
            var deferred = $q.defer();
            $rootScope.isLoading = true;
            deferred.resolve(config);

            return deferred.promise;
        },
        response: function (response) {
            var deferred = $q.defer();
            $rootScope.isLoading = false;

            deferred.resolve(response);

            return deferred.promise;
        }
    };
}]);

app.config(["$httpProvider", function ($httpProvider) {
    $httpProvider.interceptors.push('firstDataInterceptor');
}]);

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

app.directive('expiryDate', function (moment) {
    return {
        restrict: 'A',
        require: 'ngModel',
        link: function (scope, elm, attr, ctrl) {

            var REG_EX = /^[0-9]{2}[/]{1}[0-9]{2}$/g;

            ctrl.$parsers.push(function (viewValue) {
                if (!REG_EX.test(viewValue)) {
                    ctrl.$setValidity('expiry_date_invalid', false);
                    return undefined;
                }
                if (viewValue && viewValue.length < 5) {
                    ctrl.$setValidity('expiry_date_invalid', false);
                    return viewValue;
                }
                var expiry_date = moment(viewValue, 'MM/YY');
                if (!expiry_date.isValid()) {
                    console.log('aca');
                    ctrl.$setValidity('expiry_date_invalid', false);
                    return viewValue;
                }

                var now = moment(new Date());

                if (!expiry_date.isAfter(now)) {
                    console.log('fecha anterior')
                    ctrl.$setValidity('expiry_date_before', false);
                    return undefined;
                }

                ctrl.$setValidity('expiry_date_before', true);
                ctrl.$setValidity('expiry_date_invalid', true);
                return viewValue;
            });
        }
    }
});

app.directive('firstdataGrid', function ($compile, numeral, notify, $modal, $http) {
    return {
        restrict: 'E',
        scope: {
            totalRecords: '=total',
            totalAmount: '=',
            grid: '='
        },
        link: function (scope, element, attrs) {
            scope.totalRecords = 0;
            var initial = true;

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

                            // Chequel el amonut para saber si fue ingresado o si debo usar el original (para una transaccion
                            // sin formulario

                            var amount = 0;
                            if (!$scope.transaction['amount']) {
                                amount = numeral().unformat($scope.transaction['Amount']);
                            } else {
                                amount = $scope.transaction['amount'];
                            }

                            $http.post('/transactions/' + _config.action, {transactions: {
                                transaction_tag: $scope.transaction['Tag'],
                                amount: amount,
                                authorization_num: $scope.transaction['Auth No'],
                                reference_no: $scope.transaction['Ref Num']
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
                },
                grid: function () {
                    return $scope.grid;
                }
            }
        });
    };

    $scope.exportCSV = function () {
        $window.location.assign('/transactions-export');
        return false;
    }
}]);

app.controller('FormModalCtrl', ['$scope', '$modalInstance', '$http', 'transaction_type', 'notify', 'grid', 'moment', function ($scope, $modalInstance, $http, transaction_type, notify, grid, moment) {
    $scope.transaction = {};

    $scope.checkExpiryDate = function (input) {

    };

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
                grid.update();
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
        });
    };

    $scope.cancel = function () {
        $modalInstance.dismiss('cancel');
    }
}]);

