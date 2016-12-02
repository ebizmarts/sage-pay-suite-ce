<?php

/**
 * Sandbox helper.
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Helper_Sandbox extends Mage_Core_Helper_Abstract
{

    public function getTestDataJson()
    {
        return Zend_Json::encode($this->getSagePayTestData());
    }

    public function getSandBox()
    {
        $sandbox=$this->_getSandboxContent(Mage::getModuleDir('etc', 'Ebizmarts_SagePaySuite').DS, 'sandbox.xml');
        if ($sandbox === FALSE) {
            $r = new stdClass;
            $r->testcards = array();
            return $r;
        }

        return new Varien_Simplexml_Element($sandbox);
    }

    protected function _getSandboxContent($path, $filename)
    {
        $io = new Varien_Io_File();
        $io->open(array('path' => $path));
        return $io->read($filename);
    }

    public function objToArray($v)
    {
        return (array)$v;
    }

    public function getSagePayTestData()
    {

        $sandbox = $this->getSandBox();

        $cardsArray = array_values(array_map(array($this, 'objToArray'), (array)$sandbox->testcards));

        return $cardsArray;
    }

}