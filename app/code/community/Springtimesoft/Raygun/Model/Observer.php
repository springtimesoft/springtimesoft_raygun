<?php
class Springtimesoft_Raygun_Model_Observer
{
    /**
     * Register error handler earlish in Magento boot.
     * 
     * @param  Varien_Event_Observer $event [description]
     */
    public function registerHandlers(Varien_Event_Observer $event)
    {
        set_error_handler('Springtimesoft_Raygun_Model_Observer::errorHandler');

        // Maybe one day we can use these...
        //set_exception_handler('Springtimesoft_Raygun_Model_Observer::exceptionHandler');
        //register_shutdown_function('Springtimesoft_Raygun_Model_Observer::shutdownHandler');
    }

    /**
     * Catch errors and pass them off to helper.
     */
    public static function errorHandler($errno = null, $errstr = null, $errfile = null, $errline = null)
    {
        Mage::helper('springtimesoft_raygun')->errorHandler($errno, $errstr, $errfile, $errline);
    }

    public static function exceptionHandler($exception)
    {
        Mage::helper('springtimesoft_raygun')->exceptionHandler($exception);
    }

    public static function shutdownHandler()
    {
        Mage::helper('springtimesoft_raygun')->shutdownHandler();
    }

    public static function fatalExceptionHandler($observer)
    {
        $event = $observer->getEvent();
        $exception = $event->getException();
        self::exceptionHandler($exception);
    }

    public static function logReports()
    {
        Mage::helper('springtimesoft_raygun')->logReports();
    }
}
