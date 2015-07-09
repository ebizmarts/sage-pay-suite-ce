<?php
/**
 * Log view controller
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Adminhtml_SpsLogController extends Mage_Adminhtml_Controller_Action
{

    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('sales/sagepay/logs')
            ->_addBreadcrumb($this->__('Sales'), $this->__('Sales'))
            ->_addBreadcrumb($this->__('Logs'), $this->__('Logs'));
        return $this;
    }

	public function indexAction()
	{
		$this->_title($this->__('Sales'))->_title($this->__('Sage Pay'))->_title($this->__('Logs'));
		$this->_initAction()->renderLayout();
	}

	public function downloadFileAction()
	{
		$fileName = $this->getRequest()->getParam('f');
		if(is_null($fileName)){
			return;
		}

		$file = Mage::helper('sagepaysuite')->getSagePaySuiteLogDir() . DS . $fileName;

		$this->_prepareDownloadResponse($fileName, file_get_contents($file), 'text/plain', filesize($file));
	}

	public function tailAction()
	{
		$r = $this->getRequest();

		if(!$r->getParam('file')){
			$this->getResponse()->setBody('<html><head><title></title></head><body><pre>'. Mage::helper('sagepaysuite')->__('Please choose a file.') .'</pre></body></html>');
			return;
		}

	$f = Mage::helper('sagepaysuite')->getSagePaySuiteLogDir() . DS . $r->getParam('file');

    $numberOfLines = 200;
    $handle = fopen($f, "r");
    $linecounter = $numberOfLines;
    $pos = -2;
    $beginning = false;
    $text = array();
    while ($linecounter > 0) {
        $t = " ";
        while ($t != "\n") {
            if(fseek($handle, $pos, SEEK_END) == -1) {
                $beginning = true;
                break;
            }
            $t = fgetc($handle);
            $pos --;
        }
        $linecounter --;
        if ($beginning) {
            rewind($handle);
        }
        $text[$numberOfLines-$linecounter-1] = fgets($handle);
        if ($beginning) break;
    }
    fclose ($handle);

    $dlFile = '<a href="' . Mage::helper('adminhtml')->getUrl('adminhtml/spsLog/downloadFile', array('f'=>$r->getParam('file'))) . '">' . $this->__('Download file') . '</a>';

    return $this->getResponse()->setBody('<html><head><title></title><meta http-equiv="refresh" content="10"></head><body><pre>' . $dlFile ."\r\n\n". implode('',$text).'</pre></body></html>');



	}

    protected function _isAllowed() {
            $acl = 'sales/sagepay/logs';
            return Mage::getSingleton('admin/session')->isAllowed($acl);
    }

}