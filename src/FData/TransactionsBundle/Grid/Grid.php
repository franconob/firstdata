<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/3/14
 * Time: 4:10 PM
 */

namespace FData\TransactionsBundle\Grid;


use Carbon\Carbon;
use Doctrine\ORM\EntityManager;
use FData\TransactionsBundle\Entity\Transaction;
use FData\TransactionsBundle\HttpClient\Clients\GridClient;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Security\Core\SecurityContext;

class Grid
{
    private static $transactions_workflow_status = [];
    /**
     * @var array
     */
    private static $transactions_workflow = [];

    /**
     * @var array
     */
    private static $tagged_transactions = [
        'Tagged Pre-Authorization Completion' => [
            "role" => "TAGGED_PRE_AUTH_COMP",
            "label" => "Tagged Pre-Authorization Completion",
            "action" => "taggedPreAuthComp",
            "openModal" => true,
            "template" => false,
            "url" => "/transactions/taggedPreAuthComp"
        ],
        'Tagged Void' => [
            "role" => "TAGGED_VOID",
            "label" => "Tagged Void",
            "action" => "taggedVoid",
            "template" => "confirm.html",
            "openModal" => true,
            "url" => "/transactions/taggedVoid",
            "controller" => "TaggedVoidFormModalCtrl"
        ],
        'Tagged Refund' => [
            "role" => "TAGGED_REFUND",
            "label" => "Tagged Refund",
            "action" => "taggedRefund",
            "openModal" => true,
            "template" => false,
            "url" => "/transactions/taggedRefund"
        ],
        'Conciliar' => [
            "role" => "CONCILIAR",
            "label" => "Conciliar",
            "action" => "conciliar",
            "openModal" => true,
            "template" => "conciliar.html",
            "url" => "/transactions-conciliar"
        ],
        'Recibo' => [
            "role" => 'CONTACTO',
            "label" => "Ver recibo",
            "action" => "recibo",
            "openModal" => false,
            "template" => false,
            "url" => ["f_data_transactions_recibo", ["tag", "Tag"]]
        ]
    ];

    //static

    /**
     * @var string
     */
    static $btn_group_html = <<<EOF
    <div class="btn-group">
        <button type="button" class="btn btn-success dropdown-toggle btn-xs" data-toggle="dropdown">
            <i class="fa fa-list"></i> <span class="caret"></span>
        </button>
        <ul class="dropdown-menu" role="menu">
            {% for action in actions %}
                <li>
                    {% if action.openModal %}
                        <a href="" ng-click="processTransaction({0}, {{ action|json_encode }})">{{ action.label }}</a>
                    {% else %}
                        <a ng-href="{{ action.route }}" target="_blank">{{ action.label }}</a>
                    {% endif %}
                </li>
            {% endfor %}
        </ul>
    </div>
EOF;

    /**
     * @var array
     */
    private $enabledHeaders;

    /**
     * @var \FData\TransactionsBundle\HttpClient\Clients\GridClient
     */
    private $http_client;

    /**
     * @var EntityManager
     */
    private $entity_manager;

    /**
     * @var \Symfony\Component\Security\Core\SecurityContext
     */
    private $securityContext;


    /**
     * @var Router
     */
    private $router;

    /**
     * @var array
     */
    private $customHeaders;

    /**
     * @var array
     */
    private $tableHeader = [];

    public function __construct(GridClient $http_client, EntityManager $entity_manager, SecurityContext $securityContext, Router $router)
    {
        $this->entity_manager = $entity_manager;
        $this->http_client = $http_client;
        $this->securityContext = $securityContext;
        $this->router = $router;
        $this->setupApiHeaders();
        $this->setupCustomHeaders();
        $this->generateWorkflow();
    }

    /**
     * @return \GuzzleHttp\Message\ResponseInterface
     */
    public function query()
    {
        return $this->http_client->createRequest()->send();
    }

    public function filterResults($results)
    {
        if ($this->securityContext->isGranted('ROLE_CONTACTO')) {
            $filteredResults = [];
            if (!$this->securityContext->isGranted('ROLE_OPERA_HOTEL')) {
                $tags = array_column($results, 0);
                $intersection = $this->entity_manager->getRepository('FDataTransactionsBundle:Transaction')->createQueryBuilder('t')
                    ->where('t.usuario = ?1')
                    ->andWhere('t.transactionTag IN (?2)')
                    ->orderBy('t.id', 'DESC')
                    ->setParameter(1, $this->securityContext->getToken()->getUsername())
                    ->setParameter(2, $tags)
                    ->getQuery()->getArrayResult();


                foreach ($intersection as $transaction) {
                    $pos = array_search($transaction['transactionTag'], $tags);
                    if ($pos >= 0) {
                        $filteredResults[] = $results[$pos];
                    }
                    unset($results[$pos]);
                }

                foreach ($results as $result) {
                    if ($result[6] == 'Pre-Authorization' || $result[6] == 'Tagged Completion' || $result[6] == 'Tagged Void') {
                        $filteredResults[] = $result;
                    }
                }

                usort($filteredResults, function ($a, $b) {
                    $timeA = \DateTime::createFromFormat('m/d/Y H:i:s', $a[9])->getTimestamp();
                    $timeB = \DateTime::createFromFormat('m/d/Y H:i:s', $b[9])->getTimestamp();
                    if ($timeA > $timeB) {
                        return -1;
                    } else {
                        return 1;
                    }
                });

                return $filteredResults;
            } else {
                return $results;
            }
        } else {
            foreach ($results as $k => $result) {
                if (!isset($result[11]) || !in_array($result[11], $this->securityContext->getToken()->getUser()->getHotel())) {
                    unset($results[$k]);
                };
            }

            return $results;
        }
    }

    /**
     * @param array $header
     * @return bool
     */
    public function searchInEnabledHeaders($header)
    {
        foreach ($this->enabledHeaders as $k => $_header) {
            if (is_array($_header)) {
                $enabledHeader = isset($_header["friendly"]) ? $_header['friendly'] : $k;
            } else {
                $enabledHeader = $_header;
            }
            if ($header == $enabledHeader) {
                return $_header;
            }
        }

        return false;
    }

    /**
     * Limpia el array de datos dejando solo los headers habilitados
     * @param array $cols
     * @param array $data
     * @return array
     */
    public function cleanData(array $cols, array $data)
    {
        $cleanData = [];
        foreach ($data as $row) {
            $row['Time'] = (new \DateTime())->setTimestamp($row['Time'] / 1000)->format('d/m/Y H:i:s');
            $row['Expiry'] = substr($row['Expiry'], 0, 2) . '/' . substr($row['Expiry'], 2);
            $row['Amount'] = str_replace(',', '', substr($row['Amount'], 1));
            $cleanData[] = array_values(array_intersect_key($row, array_flip($this->getFlatHeaders($cols))));
        }

        return $cleanData;
    }

    /**
     * @param string $transaction
     * @param array $restrictions
     * @return string
     */
    public function getActionFor($transaction, array $restrictions)
    {
        if (
            !isset(self::$transactions_workflow[$transaction['Transaction Type']])
            ||
            "Error" == $transaction['Status']
        ) {
            return '<div class="text-center">&times</div>';
        }
        $loader = new \Twig_Loader_String();
        $twig = new \Twig_Environment($loader);
        if (isset(self::$transactions_workflow_status[$transaction['Status']])) {
            $allowed_actions = self::$transactions_workflow_status[$transaction['Status']]['allows'];
        } else {
            $allowed_actions = self::$transactions_workflow[$transaction['Transaction Type']]['allows'];
        }

        foreach ($allowed_actions as $k => $action) {
            // si es array es porque [$action, $funcionDeChequeo]
            if (isset($action[0])) {
                $callback = $action[1];
                if (!$callback($transaction, $restrictions)) {
                    unset($allowed_actions[$k]);
                    continue;
                } else {
                    $allowed_actions[$k] = $action[0];
                    $action = $action[0];
                }
            }

            if ($action['label'] == 'Conciliar' && isset($transaction['conciliado'])) {
                unset($allowed_actions[$k]);
            }
            if (!$this->securityContext->isGranted('ROLE_' . $action['role'])) {
                unset($allowed_actions[$k]);
            }

            if (is_array($action['url'])) {
                $param = $action['url'][1][0];
                $value = $action['url'][1][1];
                $allowed_actions[$k]['route'] = $this->router->generate($action['url'][0], [$param => $transaction[$value]]);
            }

        }

        if (empty($allowed_actions)) {
            return '<div class="text-center">&times</div>';
        }


        $template = $twig->render(self::$btn_group_html, ["actions" => $allowed_actions]);

        return $template;
    }

    public function isConciliada($transaction_tag)
    {
        /** @var Transaction $transaction */
        $transaction = $this->entity_manager->getRepository('FDataTransactionsBundle:Transaction')->findByTransactionTag($transaction_tag);
        if ($transaction && $transaction->isConciliada()) {
            return $transaction->getFechaConciliacion()->format('Y-m-d H:i:s');
        }

        return null;
    }

    /**
     * @return array
     */
    public function getEnabledHeaders()
    {
        return $this->enabledHeaders;
    }

    /**
     * @param $api_headers
     */
    public function buildHeaders($api_headers)
    {
        foreach ($api_headers as $k => $header) {
            if ($_header = $this->searchInEnabledHeaders($header)) {
                if (is_array($_header)) {
                    $this->tableHeader[$_header["friendly"]] = array_merge(["index" => $k], array_filter($_header, function ($header) {
                        return $header !== "format";
                    }));
                } else {
                    $this->tableHeader[$_header] = ["index" => $k + 2, "type" => "string"];
                }
            }
        };

        $this->tableHeader = array_merge($this->tableHeader, $this->customHeaders);
    }

    public function getTableHeader()
    {
        return $this->tableHeader;
    }


    /**
     * Construye el workflow de dependencia de acciones
     */
    private function generateWorkflow()
    {
        self::$transactions_workflow = [
            'Purchase' => ["allows" => [
                [self::$tagged_transactions['Tagged Void'], function ($transaction, $restrictions) {
                    $transactionDate = \DateTime::createFromFormat('U', $transaction['Time'] / 1000);
                    $transactionDate = Carbon::instance($transactionDate);
                    $nowStart = Carbon::today();
                    $nowEnd = Carbon::now()->endOfDay();

                    if (isset($restrictions['taggedVoidRestrictions']) && $restrictions['taggedVoidRestrictions'] == true) {
                        return false;
                    }

                    if ($transactionDate->between($nowStart, $nowEnd)) {
                        return true;
                    }

                    return false;
                }],
                self::$tagged_transactions['Tagged Refund'],
                self::$tagged_transactions['Conciliar'],
                self::$tagged_transactions['Recibo']]
            ],
            'Pre-Authorization' => ["allows" => [
                self::$tagged_transactions['Tagged Pre-Authorization Completion'],
                [self::$tagged_transactions['Tagged Void'], function ($transaction, $restrictions) {
                    $transactionDate = \DateTime::createFromFormat('U', $transaction['Time'] / 1000);
                    $transactionDate = Carbon::instance($transactionDate);
                    $nowStart = Carbon::today();
                    $nowEnd = Carbon::now()->endOfDay();

                    if ($transactionDate->between($nowStart, $nowEnd)) {
                        return true;
                    }

                    return false;
                }],
                self::$tagged_transactions['Conciliar'],
                self::$tagged_transactions['Recibo']
            ]
            ],
            'Refund' => ["allows" => [
                [self::$tagged_transactions['Tagged Void'], function ($transaction, $restrictions) {
                    $transactionDate = \DateTime::createFromFormat('U', $transaction['Time'] / 1000);
                    $transactionDate = Carbon::instance($transactionDate);
                    $nowStart = Carbon::today();
                    $nowEnd = Carbon::now()->endOfDay();

                    if ($transactionDate->between($nowStart, $nowEnd)) {
                        return true;
                    }

                    return false;
                }], self::$tagged_transactions['Conciliar'],
                self::$tagged_transactions['Recibo']
            ]
            ],
            'Tagged Completion' => ["allows" => [
                [self::$tagged_transactions['Tagged Void'], function ($transaction, $restrictions) {
                    $transactionDate = \DateTime::createFromFormat('U', $transaction['Time'] / 1000);
                    $transactionDate = Carbon::instance($transactionDate);
                    $nowStart = Carbon::today();
                    $nowEnd = Carbon::now()->endOfDay();

                    if (isset($restrictions['taggedVoidRestrictions']) && $restrictions['taggedVoidRestrictions'] == true) {
                        return false;
                    }

                    if ($transactionDate->between($nowStart, $nowEnd)) {
                        return true;
                    }

                    return false;
                }],
                self::$tagged_transactions['Tagged Refund'],
                self::$tagged_transactions['Conciliar'],
                self::$tagged_transactions['Recibo']
            ]
            ],
            'Tagged Void' => ["allows" => [self::$tagged_transactions['Recibo']]],
            'Tagged Refund' => ["allows" => [
                [self::$tagged_transactions['Tagged Void'], function ($transaction, $restrictions) {
                    $transactionDate = \DateTime::createFromFormat('U', $transaction['Time'] / 1000);
                    $transactionDate = Carbon::instance($transactionDate);
                    $nowStart = Carbon::today();
                    $nowEnd = Carbon::now()->endOfDay();

                    if ($transactionDate->between($nowStart, $nowEnd)) {
                        return true;
                    }

                    return false;
                }],
                self::$tagged_transactions['Conciliar'],
                self::$tagged_transactions['Recibo']
            ]
            ],
            'Conciliar' => ["allows" => []],
            'Ver recibo' => ['allows' => []]
        ];

        self::$transactions_workflow_status = [
            'Voided Transaction' => ["allows" => []],
            'Completed Transaction' => ['allows' => []]
        ];
    }

    /**
     *  Headers y formateos
     */
    private function setupApiHeaders()
    {
        $this->enabledHeaders = [
            0 => ["friendly" => "Tag"],
            1 => ["friendly" => "Cardholder Name", "format" => function ($value, $tag) {
                return '<i class="fa fa-history"></i>&nbsp; <a title="Ver historial" ng-href="#" ng-click="showLog(\'' . $tag . '\')">{0}</a>';
            }],
            4 => "Card Type",
            5 => "Amount",
            2 => "Card Number",
            3 => "Expiry",
            6 => "Transaction Type",
            7 => ["friendly" => "Status", "format" => function ($value, $tag) {
                if ($value == "Approved") {
                    return '<div class="bg-success">{0}</div>';
                } else if ($value == "Error") {
                    return '<div class="bg-danger">{0}</div>';
                } else {
                    return '<div>{0}</div>';
                }
            }],
            9 => ["friendly" => "Time", "type" => "date", "callback" => function ($value) {
                $dt = \DateTime::createFromFormat('m/d/Y H:i:s', $value);

                return $dt->getTimestamp() * 1000;
            }],
            8 => "Auth No",
            10 => ["friendly" => "Ref Num", "hidden" => true],
            11 => ["friendly" => "Cust. Ref Num", "hidden" => true],
            12 => ["friendly" => "Reference 3", "hidden" => true],
            "usuario" => ["friendly" => "usuario"]
        ];
    }

    public function setupCustomHeaders()
    {
        $this->customHeaders = [
            "id" => ["hidden" => true, "friendly" => "Id", "unique" => true, "exportable" => false],
            "actions" => ["index" => -1, "friendly" => 'actions', "filter" => false, "sorting" => false, "exportable" => false],
            "usuario" => ["friendly" => "usuario"]
        ];

        if ($this->securityContext->isGranted('ROLE_USUARIO')) {
            $this->customHeaders["conciliado"] = ["friendly" => "Conciliada", "type" => "date"];
        }
    }

    /**
     * @param array $headers
     * @return array
     */
    public function getFlatHeaders(array $headers)
    {
        $flatHeaders = [];
        foreach ($headers as $k => $header) {
            if ((isset($header['exportable']) && !$header['exportable']) || (isset($header['hidden']) && true == $header['hidden'])) {
                continue;
            }
            $enabledHeader = isset($header["friendly"]) ? $header['friendly'] : $k;

            $flatHeaders[] = $enabledHeader;


        }

        return $flatHeaders;
    }

    /**
     * @return GridClient
     */
    public function getHttpClient()
    {
        return $this->http_client;
    }
}
