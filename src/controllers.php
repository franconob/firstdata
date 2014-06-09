<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;
use League\Csv\Reader;
use Transactions\Report;

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html');
});

$app->get('/reportes', function (Request $request) use ($app) {

    $url      = "https://api.demo.globalgatewaye4.firstdata.com/transaction/search";
    $username = 'spiralti637';
    $password = 'apology83';

    $enabledHeaders = [
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
        12 => ["friendly" => "Reference 3"]
    ];

    $now         = new \DateTime();
    $last6Months = $now->sub(new \DateInterval('P6M'));

    $client   = new Client();
    $response = $client->get($url, [
        "query"   => ["search" => "Awwa Suite Hotel", "start_date" => $last6Months->format('Y-m-d')],
        "headers" => [
            ["Acecpt", "text/search-v3+csv"]
        ],
        "auth"    => [$username, $password],
        "config"  => [
            "curl" => [
                CURLOPT_SSLVERSION      => 3,
                CURLOPT_SSL_CIPHER_LIST => 'SSLv3'
            ]
        ]
    ]);

    $csv_header = $response->getHeader('Search-CSV');
    $reader     = Reader::createFromString($csv_header);

    // Response header. Info sobre cantidad de filas, el total y el offset
    /*
    $parseHeader = function ($headers) {
        $parsedHeaders = [];
        foreach ($headers as $header) {
            $name = array_map("trim", explode(':', $header, 2));
            $parsedHeaders[$name[0]] = $name[1];
        }

        return $parsedHeaders;
    };

    $parsedHeaders = $parseHeader($reader->fetchOne());
    */
    // End response header

    $response_string = (string)$response->getBody();

    // Guaramos el $data en sesion para poder bajar el CSV mas tarde
    $app['session']->set('csv_data', $response_string);

    $reader_body   = Reader::createFromString($response_string);
    $reader_header = $reader_body->fetchOne();

    $tableHeader = [];

    $searchInEnabledHeaders = function ($header, $enabledHeaders) {
        foreach ($enabledHeaders as $_header) {
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
    };

    foreach ($reader_header as $k => $header) {
        if ($_header = $searchInEnabledHeaders($header, $enabledHeaders)) {
            if (is_array($_header)) {
                $tableHeader[$_header["friendly"]] = array_merge(["index" => $k], array_filter($_header, function ($header) {
                    return $header !== "format";
                }));
            } else {
                $tableHeader[$_header] = ["index" => $k + 2, "type" => "string"];
            }
        }
    };

    $tableHeader["id"]      = ["hidden" => true, "unique" => true];
    $tableHeader["actions"] = ["index" => 1, "friendly" => ' ', "filter" => false, "sorting" => false];

    $data  = $reader_body->setOffset(1)->fetchAll();
    $data2 = $data;

    unset($data[count($data) - 1]);

    $reportHandler = Report::getInstance();

    $cleanData    = [];
    $totalAmount  = 0;
    $debo_aplicar = true;

    foreach ($data as $k_row => &$row) {
        $formattedRow = ["id" => $k_row];

        // indice 12 => "Reference 3"
        if ("" !== $row[12]) {
            $reference_tag = $row[12];

            foreach ($data2 as $k_row2 => $row2) {
                // indice 0 => "Transaction Tag"
                if ($reference_tag == $row2[0]) {
                    if ($debo_aplicar) {
                        $estado_padre = "";
                        switch ($row2[6]) {
                            case "Purchase":
                                switch ($row[6]) {
                                    case "Tagged Refund":
                                        $estado_padre = "Refunded Transaction";
                                        break;
                                    case "Tagged Void":
                                        $estado_padre = "Voided Transaction";
                                        break;
                                };
                                break;
                            case "Pre-Authorization":
                                switch ($row[6]) {
                                    case "Tagged Void":
                                        $estado_padre = "Voided Transaction";
                                        break;
                                    case "Tagged Completion":
                                        $estado_padre = "Completed Transaction";
                                        break;
                                };
                                break;
                            case "Refund":
                                switch ($row[6]) {
                                    case "Tagged Void":
                                        $estado_padre = "Voided Transaction";
                                        break;
                                };
                                break;
                            case "Tagged Refund":
                                switch ($row[6]) {
                                    case "Tagged Void":
                                        $estado_padre = "Voided Transaction";
                                        break;
                                };
                                break;
                            case "Tagged Completion":
                                switch ($row[6]) {
                                    case "Tagged Void":
                                        $estado_padre = "Voided Transaction";
                                        break;
                                };
                                break;
                        }

                        $data[$k_row2][7] = $estado_padre;
                    }

                    //si es void el estado del padre, tengo que ignorar este estado en el proximo hijo
                    $debo_aplicar = ($data[$k_row2][6] == "Tagged Void");
                }
            }
        }

        $cleanRow = array_values(array_intersect_key($row, $enabledHeaders));

        foreach ($cleanRow as $k => $col) {
            if (isset($enabledHeaders[$k]["callback"])) {
                $col = $enabledHeaders[$k]["callback"]($col);
            }
            if (isset($enabledHeaders[$k]["format"])) {
                $colName                = $enabledHeaders[$k]["friendly"] . "Format";
                $formattedRow[$colName] = $enabledHeaders[$k]["format"]($col);
            }
            $formattedRow[is_array($enabledHeaders[$k]) ? $enabledHeaders[$k]["friendly"] : $enabledHeaders[$k]] = $col;
            $formattedRow['actions']                                                                             = $k_row;
            if ($enabledHeaders[$k] == "Amount") {
                $filter_amount = floatval(filter_var($col, FILTER_SANITIZE_NUMBER_FLOAT));
                $totalAmount += sprintf("%.2f", ($filter_amount / 100));
            }
        }
        $formattedRow['actionsFormat'] = $reportHandler->getActionFor($formattedRow['Transaction Type']);
        $cleanData[$k_row]             = $formattedRow;
    }
    $vars = [
        "cols"        => $tableHeader,
        "rows"        => $cleanData,
        "totalAmount" => $totalAmount
    ];

    return $app->json($vars);

})
    ->bind('reportes');

$app->post('/transactions/{transaction_type}', function ($transaction_type) use ($app) {
    $data = $app['request']->request->get('transactions');

    /** @var \Service\FirstData\Transactions $transaction */
    $transaction = $app['firstdata.transactions'];
    $vars        = [];

    try {
        $transaction->execute($transaction_type, isset($data[0]) ? $data[0] : $data);
        $vars['success'] = true;
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        $response        = $e->getResponse();
        $vars['success'] = false;
        $vars['reason']  = $response->getReasonPhrase();
        $vars['debug']   = (string)$response->getBody();
    }

    return $app->json($vars);
})->bind('reportes_refund');

$app->get('/transactions-export', function () use ($app) {
    $data     = $app['session']->get('csv_data');
    $response = new Response();
    $response->setContent($data);
    $response->headers->add([
        'Content-Disposition' => 'attachment; filename="transactions.csv"',
        'Content-Length'      => mb_strlen($data),
        'Content-Type'        => 'text/csv; charsert="utf-8"'
    ]);

    return $response->send();
})->bind('transactions-export');

$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    // 404.html, or 40x.html, or 4xx.html, or error.html
    $templates = array(
        'errors/' . $code . '.html',
        'errors/' . substr($code, 0, 2) . 'x.html',
        'errors/' . substr($code, 0, 1) . 'xx.html',
        'errors/default.html',
    );

    return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});

$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
});
