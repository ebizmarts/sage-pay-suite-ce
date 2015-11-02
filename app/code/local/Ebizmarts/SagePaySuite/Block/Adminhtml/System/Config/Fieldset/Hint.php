<?php

/**
 * Renderer for SagePay banner in System Configuration
 * @author      Ebizmart Team <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_Adminhtml_System_Config_Fieldset_Hint extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface {

    protected $_template = 'sagepaysuite/system/config/fieldset/hint.phtml';

    /**
     * Render fieldset html
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element) {
        return $this->toHtml();
    }

    public function getSagePaySuiteVersion() {

        //This tracking is just for analytics proposes, in example, notify in case of new versions or critical issue, you can safely comment this line, email us if you have doubts: info@ebizmarts.com
        Mage::helper('sagepaysuite/tracker')->send();

        return (string) Mage::getConfig()->getNode('modules/Ebizmarts_SagePaySuite/version');
    }

    public function getCheckExtensions() {
        return array(
            'iconv',
            'curl',
            'mbstring',
        );
    }

    private function getModuleVersion() {
        return (string) Mage::getConfig()->getNode('modules/Ebizmarts_SagePaySuite/version');
    }

    private function getAdminEmail() {
        return Mage::getSingleton('admin/session')->getUser()->getEmail();
    }

    public function getHelpDeskUrl() {
        $url = "https://tickets.ebizmarts.com/formsupport/sagepaysuite/index.php?";

        $url .= "magever=" . Mage::getVersion() . "&modulever=PRO_" . $this->getModuleVersion() . "&email=" . $this->getAdminEmail();

        return $url;
    }

    public function isWebSessionConfigValid() {

        $okRemoteAddr = (int)Mage::getStoreConfig(Mage_Core_Model_Session_Abstract::XML_PATH_USE_REMOTE_ADDR) === 0;
        $okHttpVia    = (int)Mage::getStoreConfig(Mage_Core_Model_Session_Abstract::XML_PATH_USE_HTTP_VIA) === 0;
        $okFwdFor     = (int)Mage::getStoreConfig(Mage_Core_Model_Session_Abstract::XML_PATH_USE_X_FORWARDED) === 0;
        $okUA         = (int)Mage::getStoreConfig(Mage_Core_Model_Session_Abstract::XML_PATH_USE_USER_AGENT) === 0;
        $okSID        = (int)Mage::getStoreConfig(Mage_Core_Model_Session_Abstract::XML_PATH_USE_FRONTEND_SID) === 1;

        return ($okRemoteAddr && $okHttpVia && $okFwdFor && $okUA && $okSID);
    }

    public function getPxParams() {

        $v = $this->getModuleVersion();
        $ext = "Sage Pay Suite CE;{$v}";

        $modulesArray = (array) Mage::getConfig()->getNode('modules')->children();
        $aux = (array_key_exists('Enterprise_Enterprise', $modulesArray)) ? 'EE' : 'CE';
        $mageVersion = Mage::getVersion();
        $mage = "Magento {$aux};{$mageVersion}";

        $hash = md5($ext . '_' . $mage . '_' . $ext);

        return "ext=$ext&mage={$mage}&ctrl={$hash}";
    }

}
