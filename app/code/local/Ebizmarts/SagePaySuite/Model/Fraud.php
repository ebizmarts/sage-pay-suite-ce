<?php

/**
 * Fraud model
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Model_Fraud
{
	public function updateThirdMan($order = null, Varien_Simplexml_Element $trn)
    {
        $fraud = Mage::getModel('sagepaysuite2/sagepaysuite_fraud')->loadByOrderId($order);
        $fraud->setOrderId($order)
        ->setVendorTxCode((string)$trn->vendortxcode)
        ->setData('cv2result', (string)$trn->cv2result)
        ->setAddressresult((string)$trn->addressresult)
        ->setPostcoderesult((string)$trn->postcoderesult)
        ->setThirdmanScore((string)$trn->t3mscore)
        ->setThirdmanAction((string)$trn->t3maction)
        ->setThirdmanId((string)$trn->t3mid)
        ->setVpsTxId((string)$trn->vpstxid);

        $fraud->save();
    }

    protected function _getAccessUrl()
    {
    	switch ($this->_getCdata('mode')) {
            case 'simulator':
			case 'test':
                $url = 'https://test.sagepay.com/access/access.htm';
				break;
            case 'live':
                $url = 'https://live.sagepay.com/access/access.htm';
                break;
			default:
				break;
		}

        return $url;
    }

    protected function _getCdata($key)
    {
    	return Mage::getModel('sagepayserver/sagePayServer')->getConfigData($key);
    }

    public function getTransactionDetail($vendorTxCode)
    {
        $pwd = Mage::helper('core')->decrypt($this->_getCdata('api_password'));

        $xml_command="<command>getTransactionDetail</command>";
        $xml_command .= "<vendor>{$this->_getCdata('vendor')}</vendor>";
        $xml_command .= "<user>{$this->_getCdata('api_username')}</user>";
        $xml_command .= "<vendortxcode>{$vendorTxCode}</vendortxcode>";

        $xml = "<vspaccess>";
        $xml .= $xml_command;
        $xml .= "<signature>".md5($xml_command.'<password>'.$pwd.'</password>')."</signature>";
        $xml .= "</vspaccess>";

        Ebizmarts_SagePaySuite_Log::w($xml);

        // Initialise output variable
        $output = array();

        // Open the cURL session
        $curlSession = curl_init();

        //ssl version from config
        $sslversion = Mage::getStoreConfig('payment/sagepaysuite/curl_ssl_version');
        curl_setopt($curlSession, CURLOPT_SSLVERSION, $sslversion);
        // Set the URL
        curl_setopt ($curlSession, CURLOPT_URL, $this->_getAccessUrl());
        // No headers, please
        curl_setopt ($curlSession, CURLOPT_HEADER, 0);
        // It's a POST request
        curl_setopt ($curlSession, CURLOPT_POST, 1);
        // Set the fields for the POST
        curl_setopt ($curlSession, CURLOPT_POSTFIELDS, 'XML='.$xml);
        // Return it direct, don't print it out
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER,1);
        // This connection will timeout in 30 seconds
        curl_setopt($curlSession, CURLOPT_TIMEOUT, 90);
        //The next two lines must be present for the kit to work with newer version of cURL
        //You should remove them if you have any problems in earlier versions of cURL
        curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curlSession, CURLOPT_SSL_VERIFYHOST, 2);

        if(Mage::getStoreConfigFlag('payment/sagepaysuite/curl_proxy') == 1){
            curl_setopt($curlSession, CURLOPT_PROXY, Mage::getStoreConfig('payment/sagepaysuite/curl_proxy_port'));
        }

        //Send the request and store the result in an array

        $rawresponse = curl_exec($curlSession);

        Ebizmarts_SagePaySuite_Log::w($rawresponse);

        // Check that a connection was made
        if (curl_error($curlSession)) {
            return curl_error($curlSession);
        }

        // Close the cURL session
        curl_close ($curlSession);

        return trim($rawresponse);

    }

}