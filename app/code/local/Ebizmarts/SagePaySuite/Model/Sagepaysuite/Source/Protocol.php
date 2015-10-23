<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_Protocol
{
    public function toOptionArray()
    {
        return $this->toOption();
    }

    public function toOption()
    {
        return array('server'=>'Server', 'direct'=>'Direct');
    }
}