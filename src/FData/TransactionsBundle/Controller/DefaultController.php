<?php

namespace FData\TransactionsBundle\Controller;

use FData\TransactionsBundle\Grid\Grid;
use FData\TransactionsBundle\Transaction\Exception;
use League\Csv\Reader;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('FDataTransactionsBundle:Default:index.html.twig');
    }

    public function transactionsAction()
    {
        /** @var Grid $grid */
        $grid = $this->get('f_data_transactions.api.search.grid');

        $response_string = (string)$grid->query()->getBody();

        $reader_body   = Reader::createFromString($response_string);
        $reader_header = $reader_body->fetchOne();

        $tableHeader = [];

        foreach ($reader_header as $k => $header) {
            if ($_header = $grid->searchInEnabledHeaders($header)) {
                if (is_array($_header)) {
                    $tableHeader[$_header["friendly"]] = array_merge(["index" => $k], array_filter($_header, function ($header) {
                        return $header !== "format";
                    }));
                } else {
                    $tableHeader[$_header] = ["index" => $k + 2, "type" => "string"];
                }
            }
        };

        $tableHeader["id"]         = ["hidden" => true, "unique" => true];
        $tableHeader["actions"]    = ["index" => 1, "friendly" => ' ', "filter" => false, "sorting" => false];
        $tableHeader["conciliado"] = ["friendly" => "Conciliada", "type" => "bool"];

        $data  = $reader_body->setOffset(1)->fetchAll();
        $data  = array_values($grid->filterResults($data));
        $data2 = $data;

        unset($data[count($data) - 1]);


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

            $cleanRow       = array_values(array_intersect_key($row, $grid->getEnabledHeaders()));
            $enabledHeaders = $grid->getEnabledHeaders();

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
            $formattedRow['conciliado']    = $grid->isConciliada($formattedRow['Tag']);
            $formattedRow['actionsFormat'] = $grid->getActionFor($formattedRow);
            $cleanData[$k_row]             = $formattedRow;
        }
        $vars = [
            "cols"        => $tableHeader,
            "rows"        => $cleanData,
            "totalAmount" => $totalAmount
        ];

        return JsonResponse::create($vars);
    }

    /**
     * @param Request $request
     * @param string $transactionType
     * @return Response|static
     */
    public function executeAction(Request $request, $transactionType)
    {
        $data = json_decode($request->getContent(), true)['transactions'];

        $transaction = $this->get('f_data_transactions.api.transaction');
        $vars        = [];

        try {
            $data = isset($data[0]) ? $data[0] : $data;
            $transaction->execute($transactionType, $data);
            $vars['success']      = true;
            $vars['CTR']          = $transaction->getResponse()->getCTR();
            $vars['bank_message'] = $transaction->getResponse()->getBankMessage();
            $vars['response']     = $transaction->getResponse()->getBody();

            $data['ctr'] = $vars['CTR'];
            $this->get('f_data_transactions.mailer')->createAndSend($data, $this->getUser());
        } catch (Exception $e) {
            $vars = $e->getDebugVars();
        }

        return JsonResponse::create($vars);
    }

    public function conciliarAction(Request $request)
    {
        $data        = json_decode($request->getContent(), true)['transactions'];
        $transaction = $this->get('f_data_transactions.api.transaction');
        $transaction->conciliar($data);
        $vars = [
            "status" => "success"
        ];

        return JsonResponse::create($vars);
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function exportAction(Request $request)
    {
        if ($request->isMethod('POST')) {
            $reportHandler = $this->get('f_data_transactions.api.search.grid');
            $body          = json_decode($request->getContent(), true);
            $data          = $body['transactions'];
            $cols          = $body['cols'];
            $writer        = new Writer(new SplTempFileObject());
            $writer->insertOne(array_filter(array_keys($cols), array($reportHandler, "searchInEnabledHeaders")));
            $rows = $reportHandler->cleanData($data);
            $writer->insertAll($rows);
            $this->get('session')->set('csv_data', $writer->__toString());

            return new Response();

        } else {
            $response = new Response();
            $data     = $this->get('session')->get('csv_data');
            $response->setContent($data);
            $response->headers->add([
                'Content-Disposition' => 'attachment; filename="transactions.csv"',
                'Content-Length'      => mb_strlen($data),
                'Content-Type'        => 'text/csv; charsert="utf-8"'
            ]);

            return $response->send();
        }

    }
}
