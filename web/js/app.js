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
var app = angular.module('firstdata', ['ui.bootstrap', 'ui.utils', 'angularSpinner', 'angularMoment', 'angular-underscore/filters/findWhere', 'angular-underscore/filters/where']);

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

app.factory('printCTR', ['$modal', '$window', function ($modal, $window) {
    return function (CTR, bank_message) {
        $modal.open({
            templateUrl: 'printCTR.html',
            resolve: {
                CTR: function () {
                    return CTR;
                },
                bank_message: function () {
                    return bank_message;
                }
            },
            controller: function ($modalInstance, $scope, CTR, bank_message) {
                $scope.CTR = CTR;
                $scope.bank_message = bank_message;

                $scope.cancel = function () {
                    $modalInstance.dismiss('cancel');
                };

                $scope.print = function () {
                    $window.print();
                }
            }
        })
    }
}]);

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
                    ctrl.$setValidity('expiry_date_invalid', false);
                    return viewValue;
                }

                var now = moment(new Date());

                if (!expiry_date.isAfter(now)) {
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

app.directive('firstdataGrid', function ($compile, numeral, notify, $modal, $filter, printCTR) {
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
                checkboxes: false,
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
                        scope.totalAmount = _calculateTotalAmount(scope.grid.getData().rows);
                    } else {
                        scope.totalAmount = _calculateTotalAmount(scope.grid.getData(false, true).rows);
                    }
                });
            };

            var _calculateLimitAmount = function (current_transaction, transactions) {
                var limit = numeral().unformat(current_transaction['Amount']);
                if ("Purchase" == current_transaction['Transaction Type']) {
                    var tagged_transactions = $filter('where')(transactions, {'Reference 3': current_transaction['Tag']});
                    if (tagged_transactions) {
                        angular.forEach(tagged_transactions, function (t_t) {
                            if ("Tagged Refund" == t_t['Transaction Type']) {
                                var void_transaction = $filter('findWhere')(transactions, {'Reference 3': t_t['Tag']});
                                if ("Tagged Void" !== void_transaction['Transaction Type']) {
                                    limit -= numeral().unformat(t_t['Amount']);
                                }
                            }
                        })
                    }
                }
                return limit;

            };

            var _calculateTotalAmount = function (transactions) {
                var total = 0;
                angular.forEach(transactions, function (row) {
                    switch (row['Transaction Type']) {
                        case "Purchase":
                            total += numeral().unformat(row.Amount);
                            break;
                        case "Tagged Refund":
                            total -= numeral().unformat(row.Amount);
                            break;
                        case "Refund":
                            total -= numeral().unformat(row.Amount);
                            break;
                        case "Tagged Completion":
                            total += numeral().unformat(row.Amount);
                            break;
                        case "Tagged Void":
                            var parent_transaction = $filter('findWhere')(transactions, {'Tag': row['Reference 3']});
                            if (parent_transaction && (("Tagged Refund" == parent_transaction['Transaction Type']) || ("Refund" == parent_transaction['Transaction Type']))) {
                                total += numeral().unformat(row.Amount);
                                break;
                            }
                    }
                });

                return total;
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
                        },
                        printCTR: function () {
                            return printCTR;
                        },
                        transactions: function () {
                            return rows;
                        }
                    },
                    controller: function ($scope, $modalInstance, _config, transaction, $http, printCTR, transactions) {
                        $scope._config = _config;
                        $scope.transaction = transaction;
                        $scope.maxAmount = numeral(_calculateLimitAmount($scope.transaction, transactions)).format('$0,0.00');
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
                                    });
                                    printCTR(data.CTR, data.bank_message);
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

app.controller('TransactionCtrl', ['$scope', '$modal', '$window', '$http', function ($scope, $modal, $window, $http) {
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
        var transactions = $scope.grid.getData(false, true).rows || $scope.gird.getData().rows;
        $http.post('/transactions-export', {transactions: transactions, cols: $scope.grid.getData().cols}).success(function (data) {
            $window.location.assign('/transactions-export');
        });
        return false;
    }
}]);

app.controller('FormModalCtrl', ['$scope', '$modalInstance', '$http', 'transaction_type', 'notify', 'grid', 'printCTR', function ($scope, $modalInstance, $http, transaction_type, notify, grid, printCTR) {
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
                grid.update();
                printCTR(data.CTR, data.bank_message);
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

