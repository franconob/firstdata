<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 7/3/14
 * Time: 4:10 PM
 */

namespace FData\TransactionsBundle\Grid;


use FData\TransactionsBundle\HttpClient\Clients\GridClient;

class Grid
{
    /**
     * @var array
     */
    private static $transactions_workflow = [];

    /**
     * @var array
     */
    private static $tagged_transactions = [
        'Tagged Pre-Authorization Completion' => [
            "label"     => "Tagged Pre-Authorization Completion",
            "action"    => "taggedPreAuthComp",
            "openModal" => true,
            "template"  => false
        ],
        'Tagged Void'                         => [
            "label"     => "Tagged Void",
            "action"    => "taggedVoid",
            "template"  => "confirm.html",
            "openModal" => true
        ],
        'Tagged Refund'                       => [
            "label"     => "Tagged Refund",
            "action"    => "taggedRefund",
            "openModal" => true,
            "template"  => false
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
                <li><a href="" ng-click="processTransaction({0}, {{ action|json_encode }})">{{ action.label }}</a></li>
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

    public function __construct(GridClient $http_client)
    {
        $this->http_client = $http_client;
        $this->setupHeaders();
        $this->generateWorkflow();
    }

    /**
     * @return \GuzzleHttp\Message\ResponseInterface
     */
    public function query()
    {
        return $this->http_client->createRequest()->send();
    }

    /**
     * @param array $header
     * @return bool
     */
    public function searchInEnabledHeaders($header)
    {
        foreach ($this->enabledHeaders as $_header) {
            if (is_array($_header)) {
                $enabledHeader = $_header["friendly"];
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
     * @param array $data
     * @return array
     */
    public function cleanData($data)
    {
        $cleanData = [];
        foreach ($data as $key => $row) {
            $row['Time']   = (new \DateTime())->setTimestamp($row['Time'] / 1000)->format('d/m/Y H:i:s');
            $row['Expiry'] = substr($row['Expiry'], 0, 2) . '/' . substr($row['Expiry'], 2);
            $cleanData[]   = array_values(array_intersect_key($row, array_flip($this->getFlatHeaders())));
        }

        return $cleanData;
    }

    /**
     * @param string $transaction
     * @return string
     */
    public function getActionFor($transaction)
    {
        if (
            !isset(self::$transactions_workflow[$transaction['Transaction Type']])
            ||
            "Error" == $transaction['Status']
        ) {
            return '<div class="text-center">&times</div>';
        }
        $loader          = new \Twig_Loader_String();
        $twig            = new \Twig_Environment($loader);
        $allowed_actions = self::$transactions_workflow[$transaction['Transaction Type']]["allows"];

        if (empty($allowed_actions)) {
            return '<div class="text-center">&times</div>';
        }
        $template = $twig->render(self::$btn_group_html, ["actions" => $allowed_actions]);

        return $template;
    }

    /**
     * @return array
     */
    public function getEnabledHeaders()
    {
        return $this->enabledHeaders;
    }

    /**
     * Construye el workflow de dependencia de acciones
     */
    private function generateWorkflow()
    {
        self::$transactions_workflow = [
            'Purchase'          => ["allows" => [self::$tagged_transactions['Tagged Refund']]
            ],
            'Pre-Authorization' => ["allows" => [
                self::$tagged_transactions['Tagged Pre-Authorization Completion'],
                self::$tagged_transactions['Tagged Void'],
            ]
            ],
            'Refund'            => ["allows" => [
                self::$tagged_transactions['Tagged Void']
            ]
            ],
            'Tagged Completion' => ["allows" => [
                self::$tagged_transactions['Tagged Void'],
                self::$tagged_transactions['Tagged Refund']
            ]
            ],
            'Tagged Void'       => ["allows" => []],
            'Tagged Refund'     => ["allows" => [
                self::$tagged_transactions['Tagged Void']
            ]
            ]
        ];
    }

    /**
     *  Headers y formateos
     */
    private function setupHeaders()
    {
        $this->enabledHeaders = [
            0  => "Tag",
            1  => "Cardholder Name",
            4  => "Card Type",
            5  => "Amount",
            2  => "Card Number",
            3  => "Expiry",
            6  => "Transaction Type",
            7  => ["friendly" => "Status", "format" => function ($value) {
                    if ($value == "Approved") {
                        return '<div class="bg-success">{0}</div>';
                    } else if ($value == "Error") {
                        return '<div class="bg-danger">{0}</div>';
                    } else {
                        return '<div>{0}</div>';
                    }
                }],
            9  => ["friendly" => "Time", "type" => "date", "callback" => function ($value) {
                    $dt = \DateTime::createFromFormat('m/d/Y H:i:s', $value);

                    return $dt->getTimestamp() * 1000;
                }],
            8  => "Auth No",
            10 => ["friendly" => "Ref Num", "hidden" => true],
            11 => "Cust. Ref Num",
            12 => ["friendly" => "Reference 3", "hidden" => true]
        ];
    }

    /**
     * @return array
     */
    private function getFlatHeaders()
    {
        $flatHeaders = [];

        foreach ($this->enabledHeaders as $header) {
            $flatHeaders[] = is_array($header) ? $header['friendly'] : $header;
        }

        return $flatHeaders;
    }
}