<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Sales_Sagepay_Logs extends Mage_Core_Block_Template
{

	public function getTailUrl(array $params = array())
	{
		$params ['_secure'] = true;

		return $this->helper('adminhtml')->getUrl('adminhtml/spsLog/tail', $params);
	}

	protected function _getLogPath()
	{
		return $this->helper('sagepaysuite')->getSagePaySuiteLogDir();
	}

	public function getLogFilesSelect()
	{

		$logPath = $this->_getLogPath();
		$logFiles = array();

		if( file_exists($logPath) ){
			foreach (new DirectoryIterator($logPath) as $fileInfo) {
			    if($fileInfo->isDot()){
			    	continue;
			    }

				if(preg_match('/[(.log)(.logs)]$/', $fileInfo->getFilename())){
					$logFiles [] = array('file' => $fileInfo->getPathname(), 'filename'=>$fileInfo->getFilename());
				}
			}
		}

		if(empty($logFiles)){
			return $this->__('No log files found');
		}

		$html = '<label for="sl-log-switcher">' . $this->__('Please, choose a file:') . '</label><select id="sl-log-switcher" name="sl-log-switcher"><option value=""></option>';

		foreach($logFiles as $l){
			$html .= '<option value="' . $this->getTailUrl(array('file'=>$l['filename'])) . '">' . $l['filename'] . '</option>';
		}

		$html .= '</select>';

		return $html;


	}

}