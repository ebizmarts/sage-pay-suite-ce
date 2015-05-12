<?php

/**
 * FORM POST data block
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_Checkout_Formpost extends Mage_Core_Block_Template
{

	public function getFormModel()
	{
		return Mage::getModel('sagepaysuite/sagePayForm');
	}

    protected function _toHtml()
    {
        $form = new Varien_Data_Form;
        $form->setAction($this->getFormModel()->getUrl('post', false))
            ->setId('sagepayform')
            ->setName('sagepayform')
            ->setMethod('POST')
            ->setUseContainer(true);

        $crypt = $this->getFormModel()->makeCrypt();
        
        //FORM does not work in SIMULATOR because CRYPT is generated a different way.
        
        $form->addField('VPSProtocol', 'hidden', array('name'=>'VPSProtocol', 'value' => $this->getFormModel()->getVpsProtocolVersion()));
        $form->addField('TxType', 'hidden', array('name'=>'TxType', 'value' => strtoupper($this->getFormModel()->getConfigData('payment_action'))));
        $form->addField('Vendor', 'hidden', array('name'=>'Vendor', 'value' => $this->getFormModel()->getConfigData('vendor')));
        $form->addField('Crypt', 'hidden', array('name'=>'Crypt', 'value' => $crypt));

        $html = '<html><head><title>SagePay FORM</title></head><body>';
        $html.= '<code>' . $this->__('Redirecting to SagePay...') .'</code>';
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("sagepayform").submit();</script>';
        $html.= '</body></html>';

        return $html;
    }

}