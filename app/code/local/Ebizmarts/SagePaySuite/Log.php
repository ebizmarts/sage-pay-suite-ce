<?php

/**
 * SagePaySuite LOG facility
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Log {

    /**
     * Alias methods
     */
    public static function log($message, $level = null, $file = '') {
        $file = str_replace('/', '-', $file);
        self::w($message, $level, $file);
    }

    public static function logException(Exception $e) {
        self::we($e);
    }

    /**
     * Write exception to log
     *
     * @param Exception $e
     */
    public static function we(Exception $e) {
        self::w("\n" . $e->__toString(), Zend_Log::ERR, 'exceptions.log');
    }

    /**
     * Write log messages
     *
     * @param string $message Message to write
     * @param int $level Message severity level, @see Zend_Log
     * @param string $file Filename, ie: Errors.log
     */
    public static function w($message, $level = null, $file = '') {
        try {
            $logActive = Mage::getStoreConfig('payment/sagepaysuite/logs');
            if (empty($file)) {
                $file = 'general.log';
            }
        } catch (Exception $e) {
            $logActive = true;
        }

        if (!$logActive) {
            return;
        }

        #static $loggers = array();

        $level = is_null($level) ? Zend_Log::DEBUG : $level;
        $file = empty($file) ? 'general.log' : $file;

        try {
            $logFile = Mage::getBaseDir('var') . DS . 'log' . DS . 'SagePaySuite' . DS . $file;

            if (!is_dir(Mage::getBaseDir('var') . DS . 'log')) {
                mkdir(Mage::getBaseDir('var') . DS . 'log', 0777);
            }
            if (!is_dir(Mage::getBaseDir('var') . DS . 'log' . DS . 'SagePaySuite')) {
                mkdir(Mage::getBaseDir('var') . DS . 'log' . DS . 'SagePaySuite', 0777);
            }

            if (!file_exists($logFile)) {
                file_put_contents($logFile, '');
                chmod($logFile, 0777);
            }

            $format = Mage::getSingleton('core/date')->date('Y-m-d H:i:s.u') . ' (' . microtime(true) . ') ' . '%priorityName%: %message%' . PHP_EOL;

            $formatter = new Zend_Log_Formatter_Simple($format);
            $writerModel = (string) Mage::getConfig()->getNode('global/log/core/writer_model');
            if (!$writerModel) {
                $writer = new Zend_Log_Writer_Stream($logFile);
            } else {
                $writer = new $writerModel($logFile);
            }
            $writer->setFormatter($formatter);
            $logger = new Zend_Log($writer);

            if (is_array($message) || is_object($message)) {
                $message = print_r($message, true);
            }

            $logger->log($message, $level);
        } catch (Exception $e) {

        }
    }

}