<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_Tokenintegration
{
    public function toOptionArray()
    {
        return array(
				       'false'     => Mage::helper('sagepaysuite')->__('-- Not Enabled'),
			           'server' => Mage::helper('sagepaysuite')->__('Server'),
					   'direct' => Mage::helper('sagepaysuite')->__('Direct')
					);
    }
}