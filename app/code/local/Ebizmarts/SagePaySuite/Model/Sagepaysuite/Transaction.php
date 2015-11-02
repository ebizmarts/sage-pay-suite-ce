<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Transaction extends Mage_Core_Model_Abstract
{
	 /** Order instance
     *
     * @var Mage_Sales_Model_Order
     */
    protected $_order;
	protected $_paypal_trn = null;

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'sagepaysuite_transaction';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getObject() in this case
     *
     * @var string
     */
    protected $_eventObject = 'transaction';

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('sagepaysuite2/sagepaysuite_transaction');
    }

    public function loadByParent($orderId)
    {
        $this->load($orderId, 'order_id');
        return $this;
    }

    public function loadByVpsTxId($vpsTxId)
    {
        $this->load(trim($vpsTxId), 'vps_tx_id');
        return $this;
    }

    public function loadByVendorTxCode($vendorTxCode)
    {
        $this->load(trim($vendorTxCode), 'vendor_tx_code');
        return $this;
    }

	public function loadMultipleBy($attribute, $value)
	{
		$this->getCollection()
		->addFieldToFilter($attribute, $value);
		return $this;
	}

	/**
	 * Adds some API data to transaction
	 *
	 * @param int Order ID
	 */
	public function addApiDetails($orderId)
	{
		$this->loadByParent($orderId);

		if($this->getId()){

			try{
				$details = Mage::getModel('sagepayreporting/sagepayreporting')
						->getTransactionDetails(null, $this->getVpsTxId());

                if((string)$details->getErrorcode() === '0000'){
					$this->setEci($details->getEci())
						->setPaymentSystemDetails($details->getPaymentsystemdetails())
						->save();
				}

			}catch(Exception $e){}

		}
	}

    /**
     * Update transaction from API
     *
     * @param int Order ID
     */
    public function updateFromApi()
    {

        if($this->getId()){

            try{

                $details = Mage::getModel('sagepayreporting/sagepayreporting')
                        ->getTransactionDetails($this->getVendorTxCode(), $this->getVpsTxId());

                if((string)$details->getErrorcode() === '0000') {

                        //Mage::log("STATUS: " . (int)$details->getTxstateid() . " " . $details->getStatus());

                        if(((int)$details->getTxstateid()) === 16) {
                            $this->setStatus('OK');
                        }

                        $this
                        ->setVpsTxId($details->getVpstxid())
                        ->setBatchId($details->getBatchid())
                        ->setSecurityKey($details->getSecuritykey())
                        ->setCustomerCcHolderName($details->getCardholder())
                        ->setAddressResult($details->getAddressresult())
                        ->setPostcodeResult($details->getPostcoderesult())
                        ->setCv2result($details->getCv2result())
                        ->setCustomerContactInfo($details->getCustomeremail() . ' - ' . $details->getContactnumber())
                        ->setThreedSecureStatus($details->getThreedresult())
                        ->setStatusDetail($details->getStatus())
                        ->setTxStateId($details->getTxstateid())
                        ->setEci($details->getEci())
                        ->setPaymentSystemDetails($details->getPaymentsystemdetails())
                        ->setReleased(($details->getReleased() ? 1 : 0))
                        ->setAborted(($details->getAborted() ? 1 : 0))
                        ->setSurchargeAmount($details->getSurcharge())
                        ->setVoided((((int)$details->getTxstateid() == 18) ? 1 : 0))
                        ->save();

                        //Update Fraud Score
                        if($details->getT3maction() != 'NORESULT') {
                            $fraud = Mage::getModel('sagepayreporting/fraud')->updateThirdMan($this->getOrderId(), $details);
                            $this->setFraud($fraud);
                        }

                }
                else {
                    //Mage::log((string)$details->getError());
					$this->setApiError(Mage::helper('sagepayreporting/error')->parseError((string)$details->getError(),
                        Mage::getStoreConfig('sagepayreporting/account/vendor')));
				}

            }catch(Exception $e){
				Mage::logException($e);
				$this->setApiError($e->getMessage());

                //set as status 0 (not found)
                if(is_null($this->getTxStateId())){
                    $this->setTxStateId(0)->save();
                }
			}

        }

        return $this;
    }

	public function getisPayPalTransaction()
	{
		if(!is_null($this->_paypal_trn)){
			return $this->_paypal_trn;
		}

		$pp = Mage::getModel('sagepaysuite2/sagepaysuite_paypaltransaction')
		->loadByVendorTxCode($this->getVendorTxCode());

		if($pp->getId()){
			$this->_paypal_trn = true;
		}else{
			$this->_paypal_trn = false;
		}
		return $this->_paypal_trn;
	}

	public function getCardExpiryDate()
	{
		$data = $this->getData('card_expiry_date');
		if(!$data){
			return null;
		}
		return $data[0].$data[1].'/'.$data[2].$data[3];
	}

    /**
     * Processing object before save data
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _beforeSave()
    {
    	$quote = Mage::getModel('sagepaysuite/api_payment')->getQuote();
    	$dbQuote = Mage::getModel('sagepaysuite/api_payment')->loadQuote($quote->getId(), Mage::app()->getStore()->getId());

        if($quote->getId() && $dbQuote->getId()){
    		$this->setQuoteId($quote->getId())
    		->setStoreId(Mage::app()->getStore()->getId());
        }

        return parent::_beforeSave();
    }

    /**
     * Processing object after load data
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _afterLoad()
    {
    	/**
    	 * Multishipping parent TRN
    	 */
        if($this->getOrderId() && $this->getParentTrnId()){
        	$parent = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
        			  ->load($this->getParentTrnId())->toArray();
        	unset($parent['id']);
        	unset($parent['order_id']);
        	unset($parent['parent_trn_id']);

        	$this->addData($parent);
        }
    	/**
    	 * Multishipping parent TRN
    	 */

        return parent::_afterLoad();
    }

}
