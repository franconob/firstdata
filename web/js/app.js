/**
 * Created by fherrero on 5/20/14.
 */
PNotify.prototype.options.styling = "fontawesome";

var app = angular.module('firstdata', ['ui.bootstrap', 'ui.utils', 'angularSpinner', 'angularMoment', 'angular-underscore/filters', 'ui.router', 'ngSanitize']);
app.config(['$stateProvider', '$urlRouterProvider', '$httpProvider', function ($stateProvider, $urlRouterProvider, $httpProvider) {
    $httpProvider.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    $urlRouterProvider.otherwise('/app');

    $stateProvider
        .state('app', {
            url: '/app',
            controller: 'TransactionCtrl',
            templateUrl: 'app.html'
        })
        .state('app.history', {
            url: '/app/:id/history',
            controller: 'TransactionCtrl',
            templateUrl: 'app.html'
        })
}]);

app.value('numeral', numeral);
app.value('permissions', {totalAmount: false});

app.factory('firstDataInterceptor', ["$q", "$rootScope", "notify", "$window", function ($q, $rootScope, notify, $window) {
    var currentNotify = null;
    var REGEXURL = /[.]html$/;
    return {
        request: function (config) {
            var deferred = $q.defer();
            if (!REGEXURL.test(config.url)) {
                $rootScope.isLoading = true;
                currentNotify = notify({
                    title: "Ejecutando..",
                    message: "Cargando transacciones",
                    icon: "fa fa-refresh fa-spin"
                });
            }

            deferred.resolve(config);

            return deferred.promise;
        },
        response: function (response) {
            if (!REGEXURL.test(response.config.url)) {
                currentNotify.remove();
                $rootScope.isLoading = false;
            }

            if(response.status == 403) {
               $window.location.href = '/';
            }

            var deferred = $q.defer();

            deferred.resolve(response);

            return deferred.promise;
            //return response;
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
    return function (data) {
        $modal.open({
            templateUrl: 'printCTR.html',
            size: 'md',
            resolve: {
                data: function () {
                    return data;
                }
            },
            controller: function ($modalInstance, $scope, data) {
                $scope.bank_message = data.bank_message;
                $scope.print_route = Routing.generate('f_data_transactions_recibo', {tag: data.tag});

                $scope.cancel = function () {
                    $modalInstance.dismiss('cancel');
                };
            }
        })
    }
}]);

app.directive('checkCountry', function () {
    return {
        restrict: 'A',
        require: 'ngModel',
        link: function (scope, elm, attr, ctrl) {
            var countryNotAllowed = attr['checkCountry'];
            ctrl.$parsers.push(function (viewValue) {
                if (viewValue === countryNotAllowed) {
                    ctrl.$setValidity('country_not_allowed', false);
                    return undefined;
                } else {
                    ctrl.$setValidity('country_not_allowed', true);
                    return viewValue;
                }
            });
        }
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
            editable: '@',
            choices: '='
        },
        transclude: true,
        replace: true,
        link: function (scope, elm, attrs) {
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
        template: function (tElm, tAttrs) {
            var inputElm = '';
            switch (tAttrs['type']) {
                case 'text':
                {
                    inputElm = '<input ng-transclude ng-click="enableField($event)" ng-readonly="!thisEditable" />';
                    break;
                }
                case 'select':
                {
                    inputElm = '<select ng-transclude ng-click="enableField($event)" ng-readonly="!thisEditable" ng-options="value as value for (key,value) in choices"></select>'
                    break;
                }
                default :
                {
                    inputElm = '<input ng-transclude ng-click="enableField($event)" ng-readonly="!thisEditable" />';
                    break;
                }
            }

            return inputElm;
        }
    }
});

app.directive('firstdataGrid', function ($compile, numeral, $modal, $filter, $http, $sce, permissions) {
    return {
        restrict: 'E',
        scope: {
            totalRecords: '=total',
            totalAmount: '=',
            grid: '=',
            subtitle: '=',
            insideLog: '=',
            rows: '='
        },
        link: function (scope, element) {
            scope.totalRecords = 0;
            scope.totalAmount = "no disponible";
            var initial = true;
            var initialTotalAmount = 0;

            var grid = element.WATable({
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

            var updateGrid = function () {
                $http.get(Routing.generate('f_data_transactions_grid')).success(function (data) {
                    grid.data('WATable').setData(data);
                });
            };

            updateGrid();

            scope.$on('grid.update', function () {
                updateGrid();
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
                scope.$evalAsync(function () {
                    updateTotalRecords(args.initial);
                    updateTotalAmount(args.initial);
                });
            });

            var updateTotalRecords = function (initial) {
                if (initial) {
                    scope.totalRecords = scope.grid.getData().rows.length;
                } else {
                    scope.totalRecords = scope.grid.getData(false, true).rows.length;
                }
            };

            var updateTotalAmount = function (initial) {
                if (initial) {
                    initialTotalAmount = scope.grid.getData(false, false).rows.length;
                    //scope.totalAmount = _calculateTotalAmount(scope.grid.getData().rows);
                } else {
                    var nbFilteredTransactions = scope.grid.getData(false, true).rows.length;
                    if (nbFilteredTransactions < initialTotalAmount || permissions.totalAmount) {
                        scope.totalAmount = _calculateTotalAmount(scope.grid.getData(false, true).rows);
                    } else if(nbFilteredTransactions > initialTotalAmount) {
                        scope.totalAmount = 'no disponible';
                    }
                    else {
                        scope.totalAmount = 'no disponible';
                    }
                }
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
                            if (parent_transaction && ("Purchase" == parent_transaction['Transaction Type'])) {
                                total -= numeral().unformat(row.Amount);
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
            };

            scope.showLog = function (tag) {
                if (scope.insideLog) {
                    return false;
                }
                scope.insideLog = true;
                var rows = scope.grid.getData().rows;
                var transactions = _.where(rows, {'Reference 3': tag});
                var parent_transaction = _.findWhere(rows, {Tag: tag});

                var data = [
                    parent_transaction['Tag'],
                    parent_transaction['Cardholder Name'],
                    parent_transaction['Transaction Type'],
                    parent_transaction['Status'],
                    parent_transaction['Time'],
                    parent_transaction['Amount']
                ];

                scope.grid.setData({rows: transactions}, true);
                scope.rows = rows;

                scope.subtitle = $sce.trustAsHtml(' | <i class="fa fa-history"></i> <strong>Mostrando historial de transacción:</strong> ' + data.join(' - '));

            };

        }
    }
});

app.controller('TaggedVoidFormModalCtrl', ["$scope", "$modalInstance", "transaction", "grid", "$http", "notify", "printCTR", "_config", "$rootScope", function ($scope, $modalInstance, transaction, grid, $http, notify, printCTR, _config, $rootScope) {
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

        $http.post(_config.url, {transactions: data}).success(function (data, status) {
            if (data.success) {
                notify({
                    title: "Operación realizada con éxito",
                    type: 'success',
                    hide: true,
                    icon: 'fa fa-check'
                });
                printCTR(data);
            }
            $modalInstance.dismiss('ok');
            $rootScope.$broadcast('grid.update');
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
        if ("Purchase" == current_transaction['Transaction Type'] || "Tagged Completion" == current_transaction['Transaction Type']) {
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
        $modalInstance.dismiss('ok');
        $modal.open({
            templateUrl: "confirmTagged.html",
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

app.controller('ConfirmTaggedFormModalCtrl', ["$scope", "$modalInstance", "_config", "transaction", "$http", "printCTR", "moment", "maxAmount", "notify", "$rootScope", function ($scope, $modalInstance, _config, transaction, $http, printCTR, moment, maxAmount, notify, $rootScope) {
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
            reference_no: $scope.transaction['Ref Num'],
            extra_data: {
                cardholder_name: $scope.transaction['Cardholder Name'],
                cardnumber: $scope.transaction['Card Number'],
                cardtype: $scope.transaction['Card Type'],
                expiry: $scope.transaction['Expiry'],
                tag: $scope.transaction['Tag'],
                amount: $scope.transaction['amount'],
                email: 'franconob.pr@gmail.com'
            }

        };

        if ($scope.transaction['fecha']) {
            data['fecha'] = moment($scope.transaction['fecha']).format('YYYY-MM-DD') + ' ' + moment().format('H:mm:ss');
        }

        $http.post(_config.url, {transactions: data}).success(function (data) {
            if (data.success) {
                notify({
                    title: "Operación realizada con éxito",
                    type: 'success',
                    hide: true,
                    icon: 'fa fa-check'
                });
                printCTR(data);
            }
            $modalInstance.dismiss('ok');
            $rootScope.$broadcast('grid.update');
        });
    };

    $scope.cancel = function () {
        $modalInstance.dismiss('cancel');
    }
}]);

app.controller('TransactionCtrl', ['$scope', '$modal', '$window', '$http', 'moment', 'permissions', function ($scope, $modal, $window, $http, moment, permissons) {
    $scope.nbTransactions = 0;
    $scope.insideLog = false;
    $scope.countries = {};

    $scope.openForm = function (transaction_type, form_title) {
        $http.get(Routing.generate('f_data_transactions_countries')).success(function (data) {
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
                    },
                    countries: function () {
                        return data.countries;
                    },
                    filtroPais: function () {
                        return data.filter;
                    }
                }
            });
        });

    };

    $scope.back = function () {
        $scope.grid.setData({rows: $scope.rows}, true);
        $scope.subtitle = '';
        $scope.insideLog = false;
    };

    $scope.search = {};
    $scope.maxDateTo = new Date();

    $scope.search.from = null;
    $scope.search.to = $scope.minDateTo = null;

    $scope.openDatepickerFrom = function ($event) {
        $event.preventDefault();
        $event.stopPropagation();
        $scope.datepickerFromOpened = true;
    };

    $scope.openDatepickerTo = function ($event) {
        $event.preventDefault();
        $event.stopPropagation()
        $scope.datepickerToOpened = true;
    };

    $scope.changeMin = function () {
        $scope.minDateTo = $scope.search.from;
    };

    $scope.changeMax = function () {
        $scope.maxDateFrom = $scope.search.to;
    };

    $scope.search = function (form) {
        var from, to = null;
        if (form.from) {
            from = moment(form.from).format('YYYY-MM-DD');
        }

        if (form.to) {
            to = moment(form.to).format('YYYY-MM-DD');
        }
        $http.get(Routing.generate('f_data_transactions_grid'), {
            params: {
                from: from,
                to: to
            }
        }).success(function (data) {
            if (from || to) {
                permissons.totalAmount = true;
            } else {
                permissons.totalAmount = false;
            }
            $scope.grid.setData(data, true);

        })
    };

    $scope.exportCSV = function () {
        var transactions = $scope.grid.getData(false, true).rows || $scope.grid.getData().rows;
        $http.post(Routing.generate('f_data_transactions_export'), {
            transactions: transactions,
            cols: $scope.grid.getData().cols
        }).success(function (data) {
            $window.location.assign(Routing.generate('f_data_transactions_export'));
        });
        return false;
    }
}]);


app.controller('FormModalCtrl', ['$scope', '$modalInstance', 'transaction_type', 'form_title', '$modal', 'grid', 'countries', 'filtroPais', function ($scope, $modalInstance, transaction_type, form_title, $modal, grid, countries, filtroPais) {
    $scope.title = form_title;
    $scope.transaction = {};
    $scope.countries = countries;
    $scope.editable = true;
    $scope.filtroPais = filtroPais;
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
                },
                countries: function () {
                    return $scope.countries;
                },
                filtroPais: function () {
                    return $scope.filtroPais;
                }
            }
        });
    };

    $scope.cancel = function () {
        $modalInstance.dismiss('cancel');
    }
}]);


app.controller('ConfirmModalCtrl', ['$scope', '$modalInstance', 'transaction_type', 'notify', '$rootScope', 'printCTR', '$http', 'transaction', 'countries', 'filtroPais', function ($scope, $modalInstance, transaction_type, notify, $rootScope, printCTR, $http, transaction, countries, filtroPais) {
    $scope.transaction = transaction;
    $scope.title = "Confirmar datos de la operación";
    $scope.subtitle = "Click en el campo para poder editarlo";
    $scope.editable = false;
    $scope.countries = countries;
    $scope.filtroPais = filtroPais;


    $scope.submit = function () {
        $scope.transaction.amount = numeral().unformat($scope.transaction.amount);
        var promise = $http.post(Routing.generate('f_data_transactions_execute', {transactionType: transaction_type}), {transactions: $scope.transaction});
        promise.success(function (data, status) {
            if (true == data.success) {
                $modalInstance.dismiss('ok');
                notify({
                    title: "Operación realizada con éxito",
                    type: 'success',
                    hide: true,
                    icon: 'fa fa-check'
                });
                $rootScope.$broadcast('grid.update');
                printCTR(data);
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
