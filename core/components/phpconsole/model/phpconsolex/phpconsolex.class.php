<?php
// require original phpconsole class
require_once dirname(__FILE__) . '/vendor/phpconsole.php';

class phpconsoleX {
    /** @var modX $modx */
    public $modx;
    public $config;
    public $phpconsole = false;
    public $logArray = array();
    
    /**
     * Constructor
     */
    public function __construct(modX &$modx) {
        $this->modx = $modx;
        
        // load config
        $this->config = new \Phpconsole\Config;
        
        if (file_exists(MODX_CORE_PATH.'config/phpconsole-config.inc.php')) {
            // load config file
            $this->config->loadFromLocation(MODX_CORE_PATH.'config/phpconsole-config.inc.php');
        } else {
            // if no config file exists, switch back to the FILE logTarget
            $this->modx->setLogTarget('FILE');
            return false;
        }
        
        // initialize phpconsole
        $this->phpconsole = new \Phpconsole\Phpconsole($this->config);
        
        // set logTarget to ARRAY_EXTENDED to store all log() calls in an array
        $this->modx->setLogTarget(array(
            'target' => 'ARRAY_EXTENDED',
            'options' => array(
                'var' => &$this->logArray
            )
        ));
        
        // register a shoutdown function to log fatal errors in phpconsole
        register_shutdown_function('phpconsoleFatalLogger');
        
    }

    /**
     * @param mixed $payload
     * @param array $options
     */
    public function send($payload, $options = array()) {
        // return the original data
        $return = $payload;
        
        // if $payload is a xPDO Object, convert it to an array
        if ($payload instanceof xPDOObject) {
            $payload = $payload->toArray();
        }
        
        // make sure phpconsole was initialized
        if (!$this->phpconsole) {
            // save to normal error log file if phpconsole is not available    
            if ($this->modx->getCacheManager()) {
                $filename = isset($targetOptions['filename']) ? $targetOptions['filename'] : 'error.log';
                $filepath = isset($targetOptions['filepath']) ? $targetOptions['filepath'] : $this->modx->getCachePath() . xPDOCacheManager::LOG_DIR;
                $this->modx->cacheManager->writeFile($filepath . $filename, var_export($payload,true)."\n", 'a');
            }
        } else {
            // call send method of phpconsole
            try {
                $this->phpconsole->send($payload, $options);
            } catch (Exception $e) {
                if ($this->modx->getCacheManager()) {
                    $filename = isset($targetOptions['filename']) ? $targetOptions['filename'] : 'error.log';
                    $filepath = isset($targetOptions['filepath']) ? $targetOptions['filepath'] : $this->modx->getCachePath() . xPDOCacheManager::LOG_DIR;
                    $this->modx->cacheManager->writeFile($filepath . $filename, '[Phpconsole] Exception ' . $e->getCode() . ': ' . $e->getMessage()."\n", 'a');
                }
            }
        }

        return $return;
    }

}

// shoutdown function to log fatal errors in phpconsole
if (!function_exists('phpconsoleFatalLogger')) {
    function phpconsoleFatalLogger() {
        global $modx;
        $error = error_get_last();
        // fatal error, E_ERROR === 1
        if ($error['type'] === E_ERROR && isset($modx->phpconsole)) {
            $modx->getOption('phpconsole.project', null, 'default');
            $modx->phpconsole->send($error, array('project' => $environment, 'type' => 'error'));
        } 
    }
}