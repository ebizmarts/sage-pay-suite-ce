<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Dashboard extends Mage_Core_Block_Template {

    protected $_arrayTransactions = null;
    protected $_varObjSuccessTransactionsCount = null;
    protected $_varObjFailedTransactionsCount = null;
    private $_graphData = array();
    protected $_errors = null;
    private $_currencies = array();
    private $_cacheLife = 3600;
    public $endDate = null;
    public $startDate = null;
    public $daysAgo = 7;

    protected function _prepareLayout() {
        $this->setChild('reporting.switcher', $this->getLayout()->createBlock('sagepayreporting/adminhtml_sagepayreporting_switcher', 'reporting.switcher'));

        return parent::_prepareLayout();
    }

    public function __construct() {
        parent::__construct();

        $paramStore = $this->getRequest()->getParam('store');
        if (!is_null($paramStore)) {
            Mage::register('reporting_store_id', $paramStore);
        }

        $paramDays = $this->getRequest()->getParam('daysago');
        if (!is_null($paramDays)) {
            $this->daysAgo = (int)$paramDays;
        }

        $this->_errors = array();
        $this->_arrayTransactions              = $this->getTransactionList($this->daysAgo);
        $this->_varObjSuccessTransactionsCount = $this->getTransactionsDetails("SUCCESS");
        $this->_varObjFailedTransactionsCount  = $this->getTransactionsDetails("FAILURE");
    }

    public function getOnlyDate($date) {
        $_date = explode(" ", $date);

        return $_date[0];
    }

    private function _getDate($sageDate) {

        // Listing all the variables
        $sageDate = explode("/", $sageDate);
        list($day, $month, $year) = $sageDate;

        if(strlen($month) == 2 && ((int)$month[0] === 0)) {
            $month = $month[1];
        }

        if(strlen($day) == 2 && ((int)$day[0] === 0)) {
            $day = $day[1];
        }

        $year = $year[0].$year[1].$year[2].$year[3];

        $time = mktime(00, 00, 00, $month, $day, $year);

        return $time;
    }

    private function _mktime($date) {

        $fecha = explode(" ", $date);

        $fecha1 = $fecha[0];
        $hora = $fecha[1];

        $_fecha = explode("/", $fecha1);
        $_hora = explode(":", $hora);

        $s = explode(".", $_hora[2]);
        $second = $s[0];

        $month = $_fecha[1];
        if(strlen($month) == 2 && ((int)$month[0] === 0)) {
                $month = $month[1];
        }

        $day = $_fecha[0];
        if(strlen($day) == 2 && ((int)$day[0] === 0)) {
                $day = $day[1];
        }

        $time = mktime($_hora[0], $_hora[1], $second, $month, $day, $_fecha[2]);

        return $time;

    }

    public function _sortDate($a, $b) {

        $timeA = mktime($_hora[0], $_hora[1], $second, $month, $day, $_fecha[2]);
        $timeB = mktime($_hora[0], $_hora[1], $second, $month, $day, $_fecha[2]);

        if ($timeA == $timeB) {
            return 0;
        }

        return ($timeA < $timeB) ? -1 : 1;

    }

    public function _sortByDate($a, $b) {

        $timeA = $this->_mktime($a['started']);
        $timeB = $this->_mktime($b['started']);

        if ($timeA == $timeB) {
            return 0;
        }

        return ($timeA < $timeB) ? -1 : 1;

    }

    public function getTotals() {

        $data = "var totalsData = new Array();";

        $ts = $this->_varObjSuccessTransactionsCount->getData('total');
        $tf = $this->_varObjFailedTransactionsCount->getData('total');

        $data .= 'totalsData.push({"status":"ok", "label": "Success ('.$ts.')", "value":'.$ts.'});';
        $data .= 'totalsData.push({"status":"nok", "label": "Failed ('.$tf.')","value":'.$tf.'});';

        return $data;
    }

    public function getCurrencies() {

        $currencies = $this->_currencies;

        if(!is_array($currencies)) {
            $currencies = unserialize($this->_currencies);
        }


        return $currencies;
    }

    public function getTabLabel($currencyCode, $amount) {
        return Mage::app()->getLocale()->currency($currencyCode)->toCurrency($amount);
    }

    public function getTransactionList($daysAgo) {
        try {

            $paramStore = $this->getRequest()->getParam('store');

            $tsStart   = Mage::getModel('core/date')->timestamp(" -" . $daysAgo . " days");

            $startDate = date('d/m/Y 00:00:00', $tsStart);
            $endDate   = date('d/m/Y 23:59:59', Mage::getModel('core/date')->timestamp());

            if($daysAgo >= 30) {
                $_dateAux1 = getdate($tsStart);

                $day = str_pad($_dateAux1['mday'], 2, '0', STR_PAD_LEFT);

                $startDate = date('d/m/Y 00:00:00', Mage::getModel('core/date')->timestamp(" -" . ($daysAgo-1) . " days"));

                $endDate[0] = $day[0];
                $endDate[1] = $day[1];
            }

            $this->startDate = $startDate;
            $this->endDate   = $endDate;

            $cacheKey         = 'SAGEPAY_transactions_last' . $startDate . $endDate . 'days' . $paramStore;
            $cacheKeyCurrency = 'SAGEPAY_transactions_curr_last' . $startDate . $endDate . 'days' . $paramStore;
            $cacheKeyGraph    = 'SAGEPAY_transactions_graph' . $startDate . $endDate . 'days' . $paramStore;

            $returnArray = Mage::app()->loadCache($cacheKey);
            if (false === $returnArray) {

                $rowCount = 0;
                $totalRows = 0;
                $returnArray = array();

                do {

                    $APIReturn = Mage::getModel('sagepayreporting/sagepayreporting')->getTransactionList($startDate, $endDate, $rowCount + 1, $rowCount + 50);
                    if($APIReturn['ok'] === true){
                        $APIReturn = $APIReturn['result'];
                    }else{
                        $this->_errors[] = $APIReturn['result'];
                        return null;
                    }

                    $totalRows = (int) $APIReturn->transactions->totalrows;
                    $rowCount = (int) $APIReturn->transactions->endrow;

                    if (count($returnArray) === 0) {
                        $returnArray = $this->_mapTransactions($APIReturn->transactions->transaction);
                    }
                    else {
                        $returnArray = array_merge($returnArray, $this->_mapTransactions($APIReturn->transactions->transaction));
                    }

                } while ($rowCount < $totalRows);

                if(count($returnArray) > 0) {

                    foreach($this->_currencies as $curr => $amount) {
                        if(!floatval($amount)) {
                            unset($this->_currencies[$curr]);
                        }
                    }

                    ksort($this->_currencies);

                    $graphData = array_fill_keys(array_keys($this->_currencies), array());

                    foreach($returnArray as $data) {

                        if($data['result'] != 'SUCCESS') {
                            continue;
                        }

                        $currency = $data['currency'];
                        $stamp    = $this->_getDate($data['started']);

                        if(!isset($graphData[$currency][$stamp])) {
                            $graphData[$currency][$stamp] = 0.00;
                        }

                        $graphData[$currency][$stamp] += $data['amount'];

                    }

                    foreach($this->_currencies as $curr => $amount) {
                        ksort($graphData[$curr], SORT_NUMERIC);

                        foreach($graphData[$curr] as $date => $price) {
                            $graphData[$curr][date('d.n.Y', $date)] = $price;
                            unset($graphData[$curr][$date]);
                        }
                    }

                    $this->_graphData = $graphData;

                    Mage::app()->saveCache(serialize($graphData), $cacheKeyGraph, array(), $this->_cacheLife);

                    usort($returnArray, array($this, '_sortByDate'));

                    Mage::app()->saveCache(serialize($returnArray), $cacheKey, array(), $this->_cacheLife);

                    Mage::app()->saveCache(serialize($this->_currencies), $cacheKeyCurrency, array(), $this->_cacheLife);

                }

            }
            else {
                $returnArray = unserialize($returnArray);
                $this->_currencies = unserialize(Mage::app()->loadCache($cacheKeyCurrency));
                $this->_graphData = unserialize(Mage::app()->loadCache($cacheKeyGraph));
            }

            return $returnArray;

        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();
            return null;
        }
    }

    public function getGraphJson() {

        $jsArrayAsString = "var graphData = new Array();";

        foreach ($this->_graphData as $currency => $value) {

            foreach($value as $date => $amount) {
                $jsArrayAsString .= "graphData.push({date:\"" . $date . "\",currency:\"" . $currency . "\",price:" . $amount . ",formatted_price:\"" . $this->getTabLabel($currency, $amount) . "\"});";
            }

            $jsArrayAsString .= "\n";

        }

        return $jsArrayAsString;

    }

    public function getBankLogo($id, $label) {

        $logosFolder = Mage::getBaseDir('skin') . DS . 'adminhtml' . DS . 'default' . DS . 'default' . DS . 'sagepaysuite' . DS . 'images' . DS . 'bank_logos' . DS;

        $html = $label;

        $logo = $id . '.jpg';
        if(file_exists($logosFolder . $logo)) {
            $html = '<img width="150" height="50" src="' . $this->getSkinUrl("sagepaysuite/images/bank_logos/{$logo}") . '" alt="' . $label . '" title="' . $label . '" />';
        }

        return $html;
    }

    public function getSettlements() {

        $paramStore = $this->getRequest()->getParam('store');
        $cacheKey = 'SAGEPAY_settlements_' . $this->startDate . $this->endDate . 'days' . $paramStore;

        $_batches = Mage::app()->loadCache($cacheKey);

        if(false === $_batches) {

            $_batches = array();

            try {
                $settlements = Mage::getModel('sagepayreporting/sagepayreporting')
                            ->getBatchList($this->startDate, $this->endDate);
                if($settlements['ok'] === true){
                    $settlements = $settlements['result'];
                }else{
                    $settlements = new stdClass;
                    $settlements->errorcode = '9999';
                }
            }catch(Exception $e) {
                $settlements = new stdClass;
                $settlements->errorcode = '9999';
            }

            if(((string)$settlements->errorcode) == '0000') {
                $batches = (array)$settlements->batches;

                if(count($batches) > 0) {

                    foreach($batches['batch'] as $batch) {
                        $keyname = strtolower(uc_words((string)$batch->authprocessorname));

                        if(!isset($_batches[$keyname])) {
                            $_batches[$keyname] = array();
                            foreach ($this->getCurrencies() as $code => $cvalue) {
                                $_batches[$keyname][$code] = 0.00;
                            }
                            $_batches[$keyname]['label'] = (string)$batch->authprocessorname;
                        }

                        if($batch->transactiongroups->transactiongroup) {
                            foreach($batch->transactiongroups->transactiongroup as $trn) {
                                $value = ((float)$trn->paymentvalue - (float)$trn->refundvalue);
                                $_batches[$keyname][((string)$trn->currency)] += $value;
                            }
                        }

                    }

                    Mage::app()->saveCache(serialize($_batches), $cacheKey, array(), $this->_cacheLife);

                }
            }

        }
        else {
            $_batches = unserialize(Mage::app()->loadCache($cacheKey));
        }

        return $_batches;

    }

    private function _mapTransactions(SimpleXMLElement $xml) {

        $arrayObjects = array();

        for ($i = 0; $i < count($xml); $i++) {

            $amount = (float) $xml[$i]->amount;
            $currency = (string) $xml[$i]->currency;

            if (!isset($this->_currencies[$currency])) {
                $this->_currencies[$currency] = 0.00;
            }

            if(((string) $xml[$i]->result) == 'SUCCESS') {
                $this->_currencies[$currency] += $amount;
            }

            //Pie chart por CardType?

            $arrayObjects[] = array(
                'result' => (string)$xml[$i]->result,
                'currency' => $currency,
                'amount' => $amount,
                'started' => (string)$xml[$i]->started,
                'fraudcodedetail' => (string) $xml[$i]->fraudcodedetail,
                'transactiontype' => (string) $xml[$i]->transactiontype,
                'location' => (string)$xml[$i]->location,
            );
        }

        return $arrayObjects;
    }

    public function getTransactionsArrayAsJsString($jsArrayVar, $transactionsArray) {

        $jsArrayAsString = "var " . $jsArrayVar . " = new Array();";

        if ($transactionsArray <> null) {
            for ($i = 0; $i < sizeof($transactionsArray); $i++) {

                if($transactionsArray[$i]["result"] == 'SUCCESS') {
                    $jsArrayAsString .= $jsArrayVar . ".push({date:\"" . substr($transactionsArray[$i]["started"], 0, 10) . "\",
                                                                   currency:\"" . $transactionsArray[$i]["currency"] . "\",
                                                                   price:" . $transactionsArray[$i]["amount"] . "});\n";
                }

            }
        }

        return $jsArrayAsString;
    }

    public function getTransactionsDetails($status) {
        $objTransactionsCount = new Varien_Object();
        $objTransactionsCount->setData("total", 0);
        $objTransactionsCount->setData("Authenticate", 0);
        $objTransactionsCount->setData("Authorise", 0);
        $objTransactionsCount->setData("Deferred", 0);
        $objTransactionsCount->setData("Manual", 0);
        $objTransactionsCount->setData("Payment", 0);
        $objTransactionsCount->setData("PreAuth", 0);
        $objTransactionsCount->setData("Refund", 0);
        $objTransactionsCount->setData("Repeat", 0);
        $objTransactionsCount->setData("RepeatDeferred", 0);

        if ($this->_arrayTransactions <> null) {
            for ($i = 0; $i < sizeof($this->_arrayTransactions); $i++) {
                if ($this->_arrayTransactions[$i]['result'] == $status) {
                    $type = $this->_arrayTransactions[$i]["transactiontype"];
                    if ($type <> "") {
                        $objTransactionsCount->setData($type, $objTransactionsCount->getData($type) + 1);
                    }
                    $objTransactionsCount->setData('total', $objTransactionsCount->getData('total') + 1);
                }
            }
        }
        return $objTransactionsCount;
    }

    public function getDateRangeSelect() {

        $html = '<select name="daysago" id="daystoshow" onchange="setLocation($(\'daystoshow\').getValue());">';

        $array = array(1, 7, 15, 30);

        for ($i = 0; $i < count($array); $i++) {

            $label = $this->__('Last ' . $array[$i] . ' days');

            if($array[$i] === 1) {
                $label = $this->__('Last 24 hours');
            }

            $url   = $this->getUrl('adminhtml/spsDashboard', array('_current' => true, 'daysago' => $array[$i]));

            $html .= '<option' . ($array[$i] === $this->daysAgo ? ' selected="selected"' : '') . ' value="' . $url . '">' . $label . '</option>';
        }

        $html .= '</select>';

        return $html;
    }

}