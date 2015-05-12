<?php

class Ebizmarts_SagePayReporting_Model_Sagepayreporting_Collection
extends Varien_Data_Collection
{

	protected $_relatedTransactions = null;

	public function __construct($xml)
	{

		if(!is_object($xml) || (string)$xml->errorcode != '0000'){
			return parent::__construct();
		}

		if((int)$xml->transactions->totalrows >= 1){
			$cols = Mage::helper('sagepayreporting')->getDetailTransactionColumns();

			foreach($xml->transactions->children() as $trn){

				$rn = (string)$trn->rownumber;
				if(empty($rn)){
					continue;
				}

				$new = array('id' => $rn);
				foreach($cols as $k=>$v){
					$new [$k] = (string)$trn->{$k};
				}
				$this->_relatedTransactions []= $new;
			}
		}

		return parent::__construct();
	}
	public function load($printQuery = false, $logQuery = false)
	{
		if($this->isLoaded() || is_null($this->_relatedTransactions)){
			return $this;
		}

		foreach ($this->_relatedTransactions as $row) {
			$item = new Varien_Object;
			$item->addData($row);
			$this->addItem($item);
		}

		$this->_setIsLoaded();

		return $this;
	}
}