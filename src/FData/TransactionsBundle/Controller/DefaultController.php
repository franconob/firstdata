<?php

namespace FData\TransactionsBundle\Controller;

use FData\TransactionsBundle\Grid\Grid;
use FData\TransactionsBundle\Transaction\Exception;
use League\Csv\Reader;
use League\Csv\Writer;
use PHPExcel;
use SplTempFileObject;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('FDataTransactionsBundle:Default:index.html.twig');
    }

    public function transactionsAction()
    {
        /** @var Grid $grid */
        $grid            = $this->get('f_data_transactions.api.search.grid');
        $securityContext = $this->get('security.context');

        $response_string = (string)$grid->query()->getBody();

        $reader_body   = Reader::createFromString($response_string);
        $reader_header = $reader_body->fetchOne();


        $grid->buildHeaders($reader_header);

        $data  = $reader_body->setOffset(1)->fetchAll();
        $data  = array_values($grid->filterResults($data));
        $data2 = $data;

        unset($data[count($data) - 1]);
        unset($data2[count($data2) - 1]);


        $cleanData    = [];
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
            }
            if ($securityContext->isGranted('ROLE_USUARIO') && $fecha = $grid->isConciliada($formattedRow['Tag'])) {
                $formattedRow['conciliado'] = $grid->isConciliada($formattedRow['Tag']);
            }
            if ($transaction = $this->get('doctrine.orm.default_entity_manager')->getRepository('FDataTransactionsBundle:Transaction')->findByTransactionTag($formattedRow['Tag'])) {
                $formattedRow['usuario'] = $transaction->getUsuario() ? : "";
            }

            $formattedRow['actionsFormat'] = $grid->getActionFor($formattedRow);
            $cleanData[$k_row]             = $formattedRow;
        }
        $vars = [
            "cols" => $grid->getTableHeader(),
            "rows" => $cleanData,
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
        if(false === $this->get('security.context')->isGranted('ROLE_USUARIO')) {
            throw new AccessDeniedException();
        }
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
            $reportHandler->buildHeaders(array_keys($cols));
            $writer->insertOne($reportHandler->getFlatHeaders($cols));
            $rows = $reportHandler->cleanData($cols, $data);
            $writer->insertAll($rows);

            $tmpfile = tempnam(sys_get_temp_dir(), 'csv');
            $tmpfileR = fopen($tmpfile, "w");

            fwrite($tmpfileR, $writer->__toString());
            fclose($tmpfileR);

            $this->get('session')->set('csv_data', $tmpfile);

            return new Response();

        } else {
            $tmpfile = $this->get('session')->get('csv_data');

            $phpExcelReader = \PHPExcel_IOFactory::createReader('CSV');
            /** @var PhpExcel $phpExcelObj */
            $phpExcelObj = $phpExcelReader->load($tmpfile);

            $phpWriter = \PHPExcel_IOFactory::createWriter($phpExcelObj, 'Excel5');

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="transactions.xlsx"');

            $phpWriter->save('php://output');
        }

    }
}
