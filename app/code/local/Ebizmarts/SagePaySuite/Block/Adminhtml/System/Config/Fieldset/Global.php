<?php

/**
 * Renderer for SagePay global
 * @author      Ebizmart Team <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_Adminhtml_System_Config_Fieldset_Global
    extends Mage_Adminhtml_Block_Abstract
    implements Varien_Data_Form_Element_Renderer_Interface
{

    /**
     * Custom template
     *
     * @var string
     */
	protected $_template = 'sagepaysuite/system/config/fieldset/global.phtml';

    /**
     * Render fieldset html
     *
     * @param Varien_Data_Form_Element_Abstract $fieldset
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $fieldset)
    {
        foreach ($fieldset->getSortedElements() as $element) {
            $htmlId = $element->getHtmlId();
            $this->_elements[$htmlId] = $element;
        }
        $originalData = $fieldset->getOriginalData();
        $this->addData(array(
            'fieldset_label' => $fieldset->getLegend(),
            'fieldset_help_url' => isset($originalData['help_url']) ? $originalData['help_url'] : '',
        ));
        return $this->toHtml();
    }
}