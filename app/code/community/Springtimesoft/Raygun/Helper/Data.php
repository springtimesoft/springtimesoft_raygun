<?php
class Springtimesoft_Raygun_Helper_Data extends Mage_Core_Helper_Abstract
{
    const CONFIG_API_KEY = 'springtimesoft_raygun/springtimesoft_raygun_group/springtimesoft_raygun_api_key';
    const CONFIG_ASYNC = 'springtimesoft_raygun/springtimesoft_raygun_advanced_group/springtimesoft_raygun_async';
    const CONFIG_TAGS = 'springtimesoft_raygun/springtimesoft_raygun_advanced_group/springtimesoft_raygun_tags';
    const CONFIG_ENABLED = 'springtimesoft_raygun/springtimesoft_raygun_group/springtimesoft_raygun_enabled';

    protected $client = null;

    /**
     * Return Magento version.
     *
     * @return string
     */
    public function getVersion()
    {
        return Mage::getVersion();
    }

    /**
     * Return the Raygun API Key.
     *
     * @return string
     */
    public function getAPIKey()
    {
        return (string)Mage::getStoreConfig(self::CONFIG_API_KEY);
    }

    /**
     * True if Raygun should be used asynchronously.
     *
     * @return bool
     */
    public function getAsync()
    {
        return (bool)Mage::getStoreConfig(self::CONFIG_ASYNC);
    }

    /**
     * Return tags to append to errors.
     * 
     * @return array
     */
    public function getTags()
    {
        return explode(' ', (string)Mage::getStoreConfig(self::CONFIG_TAGS));
    }

    /**
     * True if Raygun is enabled.
     * 
     * @return bool
     */
    public function isEnabled()
    {
        return (bool)Mage::getStoreConfig(self::CONFIG_ENABLED);
    }

    /**
     * Return the Raygun client.
     * 
     * @return RaygunClient
     */
    public function getClient()
    {
        if (!$this->getAPIKey() || !$this->isEnabled())
            return null;

        if (!$this->client) {
            require_once __DIR__ . '/../Raygun4php/RaygunClient.php'; 

            $this->client = new \Raygun4php\RaygunClient($this->getAPIKey(), $this->getAsync(), false);
            $this->client->SetVersion($this->getVersion());

            $customer = Mage::helper('customer')->getCustomer();

            if ($customer !== null) {
                $this->client->SetUser(
                    $customer->getEmail(),           // Unique ID
                    $customer->getFirstname(),       // First name
                    $customer->getName(),            // Full name
                    $customer->getEmail(),           // Email
                    ($customer->getEmail() == null), // Anonymous?
                    null                             // UUID
                );
            }
        }

        return $this->client;
    }

    /**
     * Shutdown error handler.
     *
     * This can be a bit problematic so don't do this for now.
     */
    public function shutdownHandler()
    {
        /*
        $err = error_get_last();

        if (isset($err['type']) && $err['type'] !== E_WARNING) {
            //$this->exceptionHandler(new ErrorException($err['type'], $err['message'], $err['file'], $err['line']));
        }
        */
    }

    /**
     * Error handler.
     * 
     * @param  int    $errno   Error number
     * @param  string $errstr  Error description
     * @param  string $errfile File the error occured in
     * @param  int    $errline Line the error occured on
     */
    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        // Ignore empty error messages
        if ($errno == null && $errstr == null)
            return;

        // Ignore intentionally silenced errors (i.e. using the '@' operator)
        // When using this PHP temporarily sets the error_reporting setting to 0
        // so we can check this way.
        if (error_reporting() === 0)
            return;

        // Wrap in an ErrorException and pass off to exception handler.
        $this->exceptionHandler(new ErrorException($errstr, 0, $errno, $errfile, $errline));
    }

    /**
     * Exception error handler.
     * This takes care of actually submitting the message to Raygun (via the client API).
     *
     * @param  Exception $exception PHP Exception
     */
    public function exceptionHandler($exception)
    {
        try {
            if (!$this->getClient())
                return;

            // Send the exception!
            return $this->getClient()->SendException($exception, $this->getTags());
        } catch (Exception $e) {
            // Welp, most likely the database is missing so we can't get the Raygun API key.
            // Let Magento log this to file and we will get it later.
        }
    }

    /**
     * Read reports from var/reports directory and send them as Exceptions.
     */
    public function logReports()
    {
        try {
            $reportsDirectory = Mage::getBaseDir('var') . '/report';

            foreach (glob($reportsDirectory . '/*') as $report) {
                $reportData = file_get_contents($report);
                $reportData = unserialize($reportData);
                $this->logReport($reportData);
                unlink($report);
            }
        } catch (Exception $e) {
            // Ignore
        }
    }

    /**
     * Take the unserialized reportData and pass it to the exception handler.
     */
    public function logReport($reportData)
    {
        $this->exceptionHandler(new ErrorException("{$reportData[0]}: {$reportData[1]}"));
    }
}
