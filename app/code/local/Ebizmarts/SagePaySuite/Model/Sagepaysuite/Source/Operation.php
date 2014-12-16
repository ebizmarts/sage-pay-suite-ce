<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_Operation
{
	protected $_ops = array('repeat', 'release', 'refund', 'authorise', 'abort', 'cancel', 'void');

    public function toOptionArray()
    {
        return $this->toOption();
    }

    public function toOption()
    {
        $options = array();
        foreach($this->_ops as $o){
        	$options [$o] = strtoupper($o);
        }

        return $options;
    }
}