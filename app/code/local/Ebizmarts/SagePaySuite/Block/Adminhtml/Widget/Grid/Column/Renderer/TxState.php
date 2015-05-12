<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Widget_Grid_Column_Renderer_TxState extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

    /**
     * Renders grid column
     *
     * @param   Varien_Object $row
     * @return  string
     */
    public function render(Varien_Object $row) {
        
        $value = parent::render($row);
        
        $states = $this->helper('sagepaysuite')->getTxStates();        
        if(isset($states["stateid_{$value}"])) {
            $value = $states["stateid_{$value}"];
        }
        
        return $value;

    }
    
}