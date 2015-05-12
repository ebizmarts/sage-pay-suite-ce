<?php

class Ebizmarts_SagePayReporting_Model_Fraud
{
	public function updateThirdMan($order = null, Varien_Object $trn)
	{
		$fraud = Mage::getModel('sagepayreporting/sagepayreporting_fraud')->loadByOrderId($order);
		$fraud->setOrderId($order)
		->setVendorTxCode((string)$trn->getVendortxcode())
		->setData('cv2result', (string)$trn->getCv2result())
		->setAddressresult((string)$trn->getAddressresult())
		->setPostcoderesult((string)$trn->getPostcoderesult())
		->setThirdmanScore((string)$trn->getT3mscore())
		->setThirdmanAction((string)$trn->getT3maction())
		->setThirdmanId((string)$trn->getT3mid())
		->setVpsTxId((string)$trn->getVpstxid());

		$fraud->save();

		return $fraud;
	}


}
