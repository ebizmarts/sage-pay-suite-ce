<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Widget_Grid_Column_Renderer_Image extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{

    /**
     * Renders grid column
     *
     * @param Varien_Object $row
     * @return mixed
     */
    public function _getValue(Varien_Object $row)
    {
        $format = ( $this->getColumn()->getFormat() ) ? $this->getColumn()->getFormat() : null;
        $defaultValue = $this->getColumn()->getDefault();

        #If no format and it column not filtered specified return data as is.
        $data = parent::_getValue($row);
        $string = is_null($data) ? $defaultValue : $data;

		if(false !== strpos($string, 'NOT')){
			$string = $this->getSkinUrl('sagepaysuite/images/flag_red.png');
		}else if(false !== strpos($string, 'OK')){
			$string = $this->getSkinUrl('sagepaysuite/images/flag_green.png');
		}

        return '<img src="'. $string .'" alt="' . $data . '" title="' . $data . '" />';
    }
}