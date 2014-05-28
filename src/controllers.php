<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use GuzzleHttp\Client;
use League\Csv\Reader;

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html');
});

$app->get('/reportes', function (Request $request) use ($app) {

    $url = "https://api.demo.globalgatewaye4.firstdata.com/transaction/search";
    $username = 'spiralti637';
    $password = 'apology83';

    $enabledHeaders = [
        0 => "Tag",
        1 => "Cardholder Name",
        4 => "Card Type",
        5 => "Amount",
        2 => "Card Number",
        3 => "Expiry",
        6 => "Transaction Type",
        7 => ["friendly" => "Status", "format" => function($value) {
                if($value == "Approved") {
                    return '<div class="bg-success">{0}</div>';
                } else {
                    return '<div>{0}</div>';
                }
            }],
        9 => ["friendly" => "Time", "type" => "date", "callback" => function($value) {
                $dt = \DateTime::createFromFormat('m/d/Y H:i:s', $value);
                return $dt->getTimestamp()*1000;
            }],
        8 => "Auth No",
        10 => ["friendly" => "Ref Num", "hidden" => true],
        11 => "Cust. Ref Num"
    ];

    $now = new \DateTime();
    $last6Months = $now->sub(new \DateInterval('P6M'));

    $client = new Client();
    $response = $client->get($url, [
        "query" => ["search" => "Awwa Suite Hotel", "start_date" => $last6Months->format('Y-m-d')],
        "headers" => [
            ["Acecpt", "text/search-v3+csv"]
        ],
        "auth" => [$username, $password],
        "config" => [
            "curl" => [
                CURLOPT_SSLVERSION => 3,
                CURLOPT_SSL_CIPHER_LIST => 'SSLv3'
            ]
        ]
    ]);

    $csv_header = $response->getHeader('Search-CSV');
    $reader = Reader::createFromString($csv_header);

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

    $reader_body = Reader::createFromString($response_string);
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
                $tableHeader[$_header["friendly"]] = array_merge(["index" => $k], array_filter($_header, function($header) {
                    return $header !== "format";
                }));
            } else {
                $tableHeader[$_header] = ["index" => $k+1, "type" => "string"];
            }
        }
    };

    $tableHeader["id"] = ["hidden" => true, "unique" => true];

    $data = $reader_body->setOffset(1)->fetchAll();
    unset($data[count($data) - 1]);

    $cleanData = [];
    $totalAmount = 0;
    foreach ($data as $k_row => $row) {
        $cleanRow = array_values(array_intersect_key($row, $enabledHeaders));
        $formattedRow = ["id" => $k_row];
        foreach ($cleanRow as $k => &$col) {
            if(isset($enabledHeaders[$k]["callback"])) {
                $col = $enabledHeaders[$k]["callback"]($col);
            }
            if(isset($enabledHeaders[$k]["format"])) {
                $colName = $enabledHeaders[$k]["friendly"]."Format";
                $formattedRow[$colName] = $enabledHeaders[$k]["format"]($col);
            }
            $formattedRow[is_array($enabledHeaders[$k]) ? $enabledHeaders[$k]["friendly"] : $enabledHeaders[$k]] = $col;
            if($enabledHeaders[$k] == "Amount") {
                $filter_amount = floatval(filter_var($col, FILTER_SANITIZE_NUMBER_FLOAT));
                $totalAmount += sprintf("%.2f", ($filter_amount)/100);
            }
        }
        $cleanData[$k_row] = $formattedRow;
    }
    $vars = [
        "cols" => $tableHeader,
        "rows" => $cleanData,
        "totalAmount" => $totalAmount
    ];

    return $app->json($vars);

})
    ->bind('reportes');

$app->post('/reportes/{transaction_type}', function($transaction_type) use($app) {
    $data = $app['request']->request->get('transactions');
    $transaction = $app['firstdata.transactions'];
    $vars = [];
    try {
        $transaction->execute($transaction_type, $data[0]);
        $vars['success'] = true;
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        $response = $e->getResponse();
        $vars['success'] = false;
        $vars['reason'] = $response->getReasonPhrase();
        $vars['debug'] = (string)$response->getBody();
    }
    return $app->json($vars);
})->bind('reportes_refund');

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
