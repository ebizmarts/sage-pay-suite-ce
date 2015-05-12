<?php

class Ebizmarts_SagePayReporting_Model_SagePayReporting extends Mage_Core_Model_Abstract {

    protected $_vendor;
    protected $_username;
    protected $_password;
    protected $_enviroment;

    public function _construct() {
        parent::_construct();
        $this->_init('sagepayreporting/sagepayreporting');
        $this->_setConfiguration();
    }

    protected function _getRepStoreId() {
        return Mage::registry('reporting_store_id');
    }

    /**
     * Sets the service configurations.
     *
     * @return void
     */
    private function _setConfiguration($moto = '') {
        $_configuration = Mage::app()->getStore($this->_getRepStoreId())->getConfig("sagepayreporting{$moto}/account");

        $this->setVendor($_configuration['vendor']);
        $this->setUsername($_configuration['username']);
        $this->setPassword(Mage::helper('core')->decrypt($_configuration['password']));
        $this->setEnvironment($_configuration['enviroment']);
    }

    public function setVendor($vendor) {
        $this->_vendor = $vendor;
    }

    public function getVendor() {
        return $this->_vendor;
    }

    public function setUsername($username) {
        $this->_username = $username;
    }

    public function getUsername() {
        return $this->_username;
    }

    public function setPassword($password) {
        $this->_password = $password;
    }

    public function getPassword() {
        return $this->_password;
    }

    public function setEnvironment($enviroment) {
        $this->_enviroment = $enviroment;
    }

    public function getEnvironment() {
        return $this->_enviroment;
    }

    /**
     * Returns url for each enviroment according the configuration.
     *
     * @param integer $url Url id.
     * @return  string      Url for enviroment.
     */
    private function _getServiceUrl($id) {

        $url = null;

        switch ($id) {
            case 1:
                $url = 'https://live.sagepay.com/access/access.htm';
                break;
            case 2:
                $url = 'https://test.sagepay.com/access/access.htm';
                break;
        }

        return $url;

    }

    /**
     * Creates the connection's signature.
     *
     * @param string $command Param request to the API.
     * @return string MD5 hash signature.
     */
    private function _getXmlSignature($command, $params) {
        $xml = '<command>' . $command . '</command>';
        $xml .= '<vendor>' . $this->getVendor() . '</vendor>';
        $xml .= '<user>' . $this->getUsername() . '</user>';
        $xml .= $params;
        $xml .= '<password>' . $this->getPassword() . '</password>';

        return md5($xml);
    }

    /**
     * Creates the xml file to be used into the request.
     *
     * @param string $command API command.
     * @param string $params  Parameters used for each command.
     * @return string Xml string to be used into the API connection.
     */
    private function _createXml($command, $params = null) {
        $xml = '';
        $xml .= '<vspaccess>';
        $xml .= '<command>' . $command . '</command>';
        $xml .= '<vendor>' . $this->getVendor() . '</vendor>';
        $xml .= '<user>' . $this->getUsername() . '</user>';

        if (!is_null($params)) {
            $xml .= $params;
        }

        $xml .= '<signature>' . $this->_getXmlSignature($command, $params) . '</signature>';
        $xml .= '</vspaccess>';
        return $xml;
    }

    public function addValidIPs($vendor, $address, $mask, $note, $mode, $apiuser = '', $apipassword = '') {
        $this->setEnvironment($mode);
        $this->setVendor($vendor);

        if (!empty($apiuser)) {
            $this->setUsername($apiuser);
        }
        if (!empty($apipassword)) {
            $this->setPassword($apipassword);
        }

        $params = '<validips><ipaddress><address>' . $address . '</address>';
        $params .= '<mask>' . $mask . '</mask><note>' . $note . '</note></ipaddress></validips>';

        $xml = $this->_createXml('addValidIPs', $params);

        return $this->_executeRequest($xml);
    }

    /**
     * Returns all information held in the database about the specified transaction.
     * Only a transaction associated with the vendor can be returned.
     * Transactions can be specified by either VendorTxCode or VPSTxID.
     *
     * This command does not require the user to have administrator rights, but only
     * those transactions that can be viewed by the user account will be returned.
     *
     * @param string $vendortxcode The VendorTxCode of the transaction.
     * @param string $vpstxid      The VPSTxID (transactionid) of the transaction.
     * @return object Xml object with the transaction details.
     */
    public function getTransactionDetails($vendortxcode, $vpstxid = null) {
        $trnModel = Mage::getModel('sagepaysuite2/sagepaysuite_transaction');

        if (is_null($vpstxid)) {
            $trn = $trnModel->loadByVendorTxCode($vendortxcode);
            $params = '<vendortxcode>' . $vendortxcode . '</vendortxcode>';
        }
        else {
            $trn = $trnModel->loadByVpsTxId($vpstxid);
            $params = '<vpstxid>' . $vpstxid . '</vpstxid>';
        }

        if ($trn->getOrderId()) {
            Mage::unregister('reporting_store_id');
            Mage::register('reporting_store_id', Mage::getModel('sales/order')->load($trn->getOrderId())->getStoreId());
            $this->_setConfiguration();
        }
        else {
            Mage::unregister('reporting_store_id');
            Mage::register('reporting_store_id', $trn->getStoreId());
            $this->_setConfiguration();
        }

        //MOTO different vendor support
        if ($trn->getVendorname() && ($trn->getVendorname() != $this->getVendor())) {
            $this->_setConfiguration('_moto');
        }

        $xml          = $this->_createXml('getTransactionDetail', $params);
        $api_response = $this->_executeRequest($xml);
        $response     = $this->_mapTranscationDetails($api_response);

        if(!$response->getError()) {
            //getTransactionIPDetails
            $ipDetails = $this->basicOperation("getTransactionIPDetails", ('<vpstxid>' . $response->getVpstxid() . '</vpstxid>'));

            if($ipDetails['ok'] === true) {
                $response->setClientip((string)$ipDetails['result']->clientip);
                $response->setIplocation((string)$ipDetails['result']->iplocation);
            }
        }

        return $response;
    }

    public function getValidIPs() {
        return $this->basicOperation('getValidIPs');
    }

    public function getAvsCv2Rules() {
        return $this->basicOperation('getAVSCV2Rules');
    }

    public function getAVSCV2Status() {
        return $this->basicOperation('getAVSCV2Status');
    }

    public function setAVSCV2Status($status) {
        return $this->basicOperation('setAVSCV2Status', ('<status>' . $status . '</status>'));
    }

    public function get3dSecureRules() {
        return $this->basicOperation('get3DSecureRules');
    }

    public function get3dSecureStatus() {
        return $this->basicOperation('get3DSecureStatus');
    }

    public function set3dSecureStatus($status) {
        return $this->basicOperation('set3DSecureStatus', ('<status>' . $status . '</status>'));
    }

    public function getTokenCount() {
        return $this->basicOperation('getTokenCount');
    }

    public function getRelatedTransactions($vpstxid, $startDate = null) {
        if (is_null($startDate)) {
            $startDate = Mage::getModel('core/date')->date('d/m/Y 00:00:00', strtotime('-1 year'));
        }

        $params = '<vpstxid>' . $vpstxid . '</vpstxid>';
        $params .= '<startdate>' . $startDate . '</startdate>';
        return $this->basicOperation('getRelatedTransactions', $params);
    }

    public function getBatchList($startDate, $endDate) {
        $params  = '<startdate>' . $startDate . '</startdate>';
        $params .= '<enddate>' . $endDate . '</enddate>';

        return $this->basicOperation('getBatchList', $params);
    }

    /**
     * Return The 3RD man breakdown
     * @param string Thirdman ID
     * @return mixed
     */
    public function getT3MDetail($t3mid) {
        $params = '<t3mtxid>' . $t3mid . '</t3mtxid>';
        return $this->basicOperation('getT3MDetail', $params);
    }

    /**
     * Convert xml object to a Varien object.
     *
     * @param SimpleXMLElement $xml Xml response from the API.
     * @return object Api response into a Varien object.
     */
    private function _mapTranscationDetails(SimpleXMLElement $xml) {
        $object = new Varien_Object;
        $object->setErrorcode($xml->errorcode);
        $object->setTimestamp($xml->timestamp);

        if ((string) $xml->errorcode === '0000') {

            foreach ($xml as $key => $value) {
                $object->setData($key, ((string) $value));
            }
        }
        else {
            $login = '<vendor>: ' . $this->getVendor() . ' <user>: ' . $this->getUsername();
            $object->setError(htmlentities($xml->error . $login));
        }

        return $object;
    }

    public function basicOperation($opname, $params = null) {
        $xml          = $this->_createXml($opname, $params);
        $api_response = $this->_executeRequest($xml);

        if ((string) $api_response->errorcode !== '0000') {
            $login = '<vendor>: ' . $this->getVendor() . ' <user>: ' . $this->getUsername();
            //Mage::throwException(htmlentities( ((string)$api_response->error) . ' ' . $login));
            $response = array('ok'=>false,'result'=>htmlentities(((string)$api_response->error) . ' ' . $login));
        }else{
            $response = array('ok'=>true,'result'=>$api_response);
        }

        return $response;
    }

    public function getTransactionList($startDate, $endDate, $startRow = 1, $endRow = 50) {
        $params  = '<startdate>' . $startDate . '</startdate>';
        $params .= '<enddate>' . $endDate . '</enddate>';
        $params .= '<startrow>' . $startRow . '</startrow>';
        $params .= '<endrow>' . $endRow . '</endrow>';

        return $this->basicOperation('getTransactionList', $params);
    }

    /**
     * Makes the Curl call and returns the xml response.
     *
     * @param string $xml description
     */
    private function _executeRequest($xml) {
        $url = $this->_getServiceUrl($this->getEnvironment());

        //Sage_Log::log($url, null, 'SagePay_Reporting.log');

        $curlSession = curl_init();

        $sslversion = Mage::getStoreConfig('payment/sagepaysuite/curl_ssl_version');
        curl_setopt($curlSession, CURLOPT_SSLVERSION, $sslversion);

        if(Mage::getStoreConfigFlag('payment/sagepaysuite/curl_proxy') == 1){
            curl_setopt($curlSession, CURLOPT_PROXY, Mage::getStoreConfig('payment/sagepaysuite/curl_proxy_port'));
        }
        curl_setopt($curlSession, CURLOPT_URL, $url);
        curl_setopt($curlSession, CURLOPT_HEADER, 0);
        curl_setopt($curlSession, CURLOPT_POST, 1);
        curl_setopt($curlSession, CURLOPT_POSTFIELDS, 'XML=' . $xml);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlSession, CURLOPT_TIMEOUT, 120);
        curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curlSession, CURLOPT_SSL_VERIFYHOST, 2);

        $rawresponse = curl_exec($curlSession);

        // Check that a connection was made
        if (curl_error($curlSession)) {
            Mage::throwException(curl_error($curlSession));
        }

        //Sage_Log::log($xml, null, 'SagePay_Reporting.log');
        //Sage_Log::log($rawresponse, null, 'SagePay_Reporting.log');

        // Close the cURL session
        curl_close($curlSession);
        $xml = simplexml_load_string($rawresponse);

        return $xml;
    }

}
