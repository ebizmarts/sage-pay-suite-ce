<?php
class Ebizmarts_SagePayReporting_Block_SagePayReporting extends Mage_Core_Block_Template
{
	public function _prepareLayout()
	{
		return parent::_prepareLayout();
	}

	public function getSagePayReporting()
	{
		if (!$this->hasData('sagepayreporting')) {
			$this->setData('sagepayreporting', Mage::registry('sagepayreporting'));
		}
		return $this->getData('sagepayreporting');

	}
}