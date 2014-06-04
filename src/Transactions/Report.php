<?php
/**
 * Created by PhpStorm.
 * User: fherrero
 * Date: 6/4/14
 * Time: 9:52 AM
 */

namespace Transactions;


class Report
{
    /**
     * @var null|Report
     */
    private static $instance = null;

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
            "template"  => false,
            "openModal" => false
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
     * @return Report
     */
    public static function  getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->generateWorkflow();
    }

    /**
     * @param string $transaction_type
     * @return string
     */
    public function getActionFor($transaction_type)
    {
        if (!isset(self::$transactions_workflow[$transaction_type])) {
            return "";
        }
        $loader          = new \Twig_Loader_String();
        $twig            = new \Twig_Environment($loader);
        $allowed_actions = self::$transactions_workflow[$transaction_type]["allows"];
        $template        = $twig->render(self::$btn_group_html, ["actions" => $allowed_actions]);

        return $template;
    }

    private function generateWorkflow()
    {
        self::$transactions_workflow = [
            'Purchase'                            => ["allows" => [self::$tagged_transactions['Tagged Refund']]
            ],
            'Pre-Authorization'                   => ["allows" => [
                self::$tagged_transactions['Tagged Pre-Authorization Completion'],
                self::$tagged_transactions['Tagged Void'],
                self::$tagged_transactions['Tagged Refund']
            ]
            ],
            'Refund'                              => ["allows" => [
                self::$tagged_transactions['Tagged Void']
            ]
            ],
            'Tagged Pre-Authorization Completion' => ["allows" => [
                self::$tagged_transactions['Tagged Void'],
                self::$tagged_transactions['Tagged Refund']
            ]
            ],
            'Tagged Void'                         => ["allows" => []],
            'Tagged Refund'                       => ["allows" => [
                self::$tagged_transactions['Tagged Void']
            ]
            ]
        ];
    }
} 