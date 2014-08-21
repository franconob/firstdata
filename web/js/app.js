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
var app = angular.module('firstdata', ['ui.bootstrap', 'ui.utils', 'angularSpinner', 'angularMoment', 'angular-underscore/filters']);

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
            size: 'md',
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

app.directive('checkLimit', function (numeral) {
    return {
        restrict: 'A',
        require: 'ngModel',
        link: function checkLimitCtrl(scope, elm, attr, ctrl) {
            ctrl.$parsers.push(function (viewValue) {
                var limit = numeral().unformat(attr.limit);
                var value = numeral(viewValue).format('0.00');
                if (value > limit) {
                    ctrl.$setValidity('limit_reached', false);
                    return undefined;
                }

                ctrl.$setValidity('limit_reached', true);
                return viewValue;
            });
        }
    }
});


app.directive('fdataInput', function () {
    return {
        restrict: 'E',
        scope: {
            editable: '@'
        },
        transclude: true,
        replace: true,
        link: function (scope, elm) {
            scope.thisEditable = true;

            elm.on('blur', function () {
                if ("false" === scope.editable) {
                    scope.thisEditable = false;
                }
            })
        },
        controller: function ($scope) {
            $scope.$watch($scope.editable, function (val) {
                    $scope.thisEditable = val;
                }
            );
            $scope.enableField = function () {
                if ("true" === $scope.editable) {
                    return;
                }
                $scope.thisEditable = true;
            };
        },
        template: '<input ng-transclude ng-click="enableField($event)" ng-readonly="!thisEditable" />'
    }
});

app.directive('firstdataGrid', function ($compile, numeral, $modal, $filter, printCTR) {
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
                url: '/transactions',
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
                        format: 'd/M/yyyy H:mm:ss'
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
                var td_conciliada = angular.element('.conciliada').parent();
                td_conciliada.addClass('text-center');
                if (td_conciliada.hasClass('fa-check')) {
                    td_conciliada.addClass('success');
                } else {
                    td_conciliada.addClass('danger');
                }
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


            var _calculateTotalAmount = function (transactions) {
                var total = 0;
                angular.forEach(transactions, function (row) {
                    if ('Error' == row['Status']) {
                        return;
                    }
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
                            if (parent_transaction && ("Tagged Completion" == parent_transaction['Transaction Type'])) {
                                total -= numeral().unformat(row.Amount);
                                break;
                            }
                    }
                });
                return numeral(total).format('0,0.00');
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
                        transactions: function () {
                            return rows;
                        },
                        grid: function () {
                            return scope.grid;
                        }
                    },
                    controller: config.controller || 'TaggedFormModalCtrl'
                })
            }
        }
    }
});

app.controller('TaggedVoidFormModalCtrl', ["$scope", "$modalInstance", "transaction", "grid", "$http", "notify", "printCTR", "_config", function ($scope, $modalInstance, transaction, grid, $http, notify, printCTR, _config) {
    $scope.title = _config.label;
    $scope.transaction = transaction;
    $scope.submit = function () {

        var amount = numeral().unformat($scope.transaction['Amount']);

        var data = {
            transaction_tag: $scope.transaction['Tag'],
            amount: amount,
            authorization_num: $scope.transaction['Auth No'],
            reference_no: $scope.transaction['Ref Num']
        };

        $http.post(_config.url, { transactions: data }).success(function (data, status) {
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
            grid.update();
        })
    };

    $scope.cancel = function () {
        $modalInstance.dismiss('cancel');
    }
}]);

app.controller('TaggedFormModalCtrl', ["$scope", "$modal", "$modalInstance", "_config", "transaction", "transactions", "$filter", "grid", function ($scope, $modal, $modalInstance, _config, transaction, transactions, $filter, grid) {
    $scope.editable = true;
    var _calculateLimitAmount = function (current_transaction, transactions) {
        var limit = numeral().unformat(current_transaction['Amount']);
        if ("Purchase" == current_transaction['Transaction Type']) {
            var tagged_transactions = $filter('filter')(transactions, function (transaction) {
                return transaction['Reference 3'] == current_transaction['Tag']
                    && 'Error' !== transaction['Status']
            });
            if (tagged_transactions) {
                angular.forEach(tagged_transactions, function (t_t) {
                    if ("Tagged Refund" == t_t['Transaction Type']) {
                        var void_transaction = $filter('findWhere')(transactions, {'Reference 3': t_t['Tag']});
                        if (!void_transaction || (void_transaction && "Tagged Void" !== void_transaction['Transaction Type'])) {
                            limit -= numeral().unformat(t_t['Amount']);
                        }
                    }
                })
            }
        }
        return limit;

    };

    $scope._config = _config;
    $scope.transaction = transaction;
    $scope.maxAmount = numeral(_calculateLimitAmount($scope.transaction, transactions)).format('$0,0.00');
    /** @namespace $scope.transaction.Amount */
    $scope.amount = numeral().unformat($scope.transaction.Amount);

    // Se usa cuando se concilia
    $scope.maxDate = new Date();

    $scope.submit = function () {
        $modalInstance.dismiss('cancel');
        $modal.open({
            templateUrl: _config.template ? _config.template : "tagged.html",
            size: 'md',
            resolve: {
                _config: function () {
                    return _config;
                },
                transaction: function () {
                    return transaction;
                },
                maxAmount: function () {
                    return $scope.maxAmount;
                },
                grid: function () {
                    return grid;
                }
            },
            controller: 'ConfirmTaggedFormModalCtrl'
        })
    };

    $scope.cancel = function () {
        $modalInstance.dismiss('cancel');
    }
}]);

app.controller('ConfirmTaggedFormModalCtrl', ["$scope", "$modalInstance", "_config", "transaction", "$http", "printCTR", "moment", "maxAmount", "notify", "grid", function ($scope, $modalInstance, _config, transaction, $http, printCTR, moment, maxAmount, notify, grid) {
    $scope.editable = false;
    $scope.maxAmount = maxAmount;
    $scope.transaction = transaction;
    $scope.subtitle = "Click en el campo para poder editarlo";
    _config.label = "Confirmar datos de la operación";
    $scope._config = _config;
    $scope.submit = function () {

        // Chequel el amonut para saber si fue ingresado o si debo usar el original (para una transaccion
        // sin formulario

        var amount = 0;
        if (!$scope.transaction['amount']) {
            amount = numeral().unformat($scope.transaction['Amount']);
        } else {
            amount = numeral().unformat($scope.transaction['amount']);
        }

        var data = {
            transaction_tag: $scope.transaction['Tag'],
            amount: amount,
            authorization_num: $scope.transaction['Auth No'],
            reference_no: $scope.transaction['Ref Num']
        };

        if ($scope.transaction['fecha']) {
            data['fecha'] = moment($scope.transaction['fecha']).format('YYYY-MM-DD') + ' ' + moment().format('H:mm:ss');
        }

        $http.post(_config.url, { transactions: data }).success(function (data, status) {
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
            grid.update();
        })
    };

    $scope.cancel = function () {
        $modalInstance.dismiss('cancel');
    }
}]);

app.controller('TransactionCtrl', ['$scope', '$modal', '$window', '$http', function ($scope, $modal, $window, $http) {
    $scope.nbTransactions = 0;
    $scope.openForm = function (transaction_type, form_title) {
        $modal.open({
            templateUrl: transaction_type + '.html',
            controller: 'FormModalCtrl',
            resolve: {
                transaction_type: function () {
                    return transaction_type;
                },
                grid: function () {
                    return $scope.grid;
                },
                form_title: function () {
                    return form_title;
                }
            }
        });
    };

    $scope.exportCSV = function () {
        var transactions = $scope.grid.getData(false, true).rows || $scope.grid.getData().rows;
        $http.post('/transactions-export', {transactions: transactions, cols: $scope.grid.getData().cols}).success(function (data) {
            $window.location.assign('/transactions-export');
        });
        return false;
    }
}]);


app.controller('FormModalCtrl', ['$scope', '$modalInstance', 'transaction_type', 'form_title', '$modal', 'grid', function ($scope, $modalInstance, transaction_type, form_title, $modal, grid) {
    $scope.title = form_title;
    $scope.transaction = {};
    $scope.editable = true;
    $scope.submit = function () {
        $modalInstance.dismiss('cancel');
        $modal.open({
            templateUrl: transaction_type + '.html',
            controller: 'ConfirmModalCtrl',
            resolve: {
                transaction_type: function () {
                    return transaction_type;
                },
                grid: function () {
                    return grid;
                },
                transaction: function () {
                    return $scope.transaction;
                }
            }
        });
    };

    $scope.cancel = function () {
        $modalInstance.dismiss('cancel');
    }
}]);


app.controller('ConfirmModalCtrl', ['$scope', '$modalInstance', 'transaction_type', 'notify', 'grid', 'printCTR', '$http', 'transaction', function ($scope, $modalInstance, transaction_type, notify, grid, printCTR, $http, transaction) {
    $scope.transaction = transaction;
    $scope.title = "Confirmar datos de la operación";
    $scope.subtitle = "Click en el campo para poder editarlo";
    $scope.editable = false;


    $scope.submit = function () {
        $scope.transaction.amount = numeral().unformat($scope.transaction.amount);
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
