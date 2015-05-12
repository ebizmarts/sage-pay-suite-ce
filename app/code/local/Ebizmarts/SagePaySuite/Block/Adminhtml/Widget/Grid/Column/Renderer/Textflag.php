<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Widget_Grid_Column_Renderer_Textflag extends
Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

    /**
     * Renders grid column
     *
     * @param Varien_Object $row
     * @return mixed
     */
    public function _getValue(Varien_Object $row) {
        $format = ( $this->getColumn()->getFormat() ) ? $this->getColumn()->getFormat() : null;
        $defaultValue = $this->getColumn()->getDefault();

        // If no format and it column not filtered specified return data as is.
        $data = parent::_getValue($row);
        $string = is_null($data) ? $defaultValue : $data;

        return '<span style="' . $this->_getTextStyle($string) . '">' . $string . '</span>';
    }

    protected function _getTextStyle($string) {
        $s = 'color:';
        switch ($string) {
            case 'NOTMATCHED':
            case 'REJECT':
                $s .= 'red';
                break;
            case 'MATCHED':
            case 'OK':
                $s .= 'green';
                break;
            case 'NOTCHECKED':
                $s .= 'orange';
                break;
            default:
                $s .= 'black';
                break;
        }

        return $s;
    }

}