<?php

/**
 * Log facility for SagePay, files at "var/log/SagePaySuite/*"
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Sage_Log
{

    public static function log($message, $level = null, $file = '')
    {
        Ebizmarts_SagePaySuite_Log::w($message, $level, $file);
    }

    public static function logException(Exception $e)
    {
        Ebizmarts_SagePaySuite_Log::we($e);
    }

}