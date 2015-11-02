<?php
/**
 * This tracking is just for analytics proposes, in example, notify in case of new versions or critical issue, email us
 * if you have doubts: info@ebizmarts.com
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Helper_Tracker extends Mage_Core_Helper_Abstract{

    public function grabData(){

        $generalData = Array();

        $generalData['module_name'] = 'Sage Pay Suite'; //change it according to the module

        $generalData['host'] = Mage::getUrl();
        $generalData['server_ip'] = Mage::helper('core/http')->getServerAddr();
        $generalData['vendorname'] = Mage::getModel('sagepaysuite/sagePayServer')->getConfigData('vendor');
        $generalData['magento_version'] = Mage::getVersion();

        if(method_exists('Mage','getEdition')){ // Fix for earlier Magento versions
            $generalData['magento_edition'] = Mage::getEdition();
        }

        $generalData['module_version'] = (string)Mage::getConfig()->getNode('modules/Ebizmarts_SagePaySuite/version');

        if($code = Mage::getSingleton('adminhtml/config_data')->getStore()){
            $generalData['store_id'] = Mage::getModel('core/store')->load($code)->getId();
        }elseif($code = Mage::getSingleton('adminhtml/config_data')->getWebsite()){
            $website_id = Mage::getModel('core/website')->load($code)->getId();
            $generalData['store_id'] = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
        }else{
            $generalData['store_id'] = 0;
        }

        $integrations = Array(  'sagepayform',
            'sagepaypaypal',
            'sagepaydirectpro',
            'sagepayserver',
            'sagepaydirectpro_moto',
            'sagepayserver_moto',
            'sagepaynit');

        $integrationData = Array();

        foreach($integrations as $integration){

            $path = 'payment/' . $integration . '/active';

            if(Mage::getStoreConfig($path, $generalData['store_id'])){

                $integrationData[$integration] = array();

                $path = 'payment/' . $integration . '/mode';

                //$integrationData[$integration]['integration'] = $integration;
                $integrationData[$integration]['mode'] = Mage::getStoreConfig($path, $generalData['store_id']);

                $path = 'payment/' . $integration . '/vendor';
                $vendor = Mage::getStoreConfig($path, $generalData['store_id']);

                if(!empty($vendor) && $vendor != $generalData['vendorname']){
                    $integrationData[$integration]['vendorname'] = $vendor;
                }
            }
        }

        $data = Array();

        $data['general'] = $generalData;
        $data['integrations'] = $integrationData;

        return $data;
    }

    public function send(){

        try {

            $data = $this->grabData();
            $url = 'https://ebizmarts.com/sagepaysuite_tracker.php';

            $curl = curl_init();

            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
            $sslversion = Mage::getStoreConfig('payment/sagepaysuite/curl_ssl_version');
            curl_setopt($curl, CURLOPT_SSLVERSION, $sslversion);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_TIMEOUT, 4);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
            if(Mage::getStoreConfigFlag('payment/sagepaysuite/curl_proxy') == 1){
                curl_setopt($curl, CURLOPT_PROXY, Mage::getStoreConfig('payment/sagepaysuite/curl_proxy_port'));
            }

            $response = curl_exec($curl);

            if (!curl_error($curl)) {
                return TRUE;
            }
            return FALSE;

        }catch(Exception $e){

            Sage_Log::logException($e);

            return FALSE;
        }
    }


}