<?php
class phpconsoleX {
    /** @var modX $modx */
    public $modx;
    public $config;
    public $project;
    public $phpconsole = false;
    public $logArray = array();
    
    /**
     * Constructor
     */
    public function __construct(modX &$modx) {
        $this->modx = $modx;
        
        // require original phpconsole class
        $phpconsole_file = dirname(__FILE__) . '/vendor/phpconsole.php';
        if (file_exists($phpconsole_file)) {
            require_once $phpconsole_file;
        } else {
            return false;
        }

        // make sure phpconsole should be enabled
        if (!$this->modx->getOption('phpconsole.enabled', null, false)) return false;
        
        // load config
        $this->config = new \Phpconsole\Config;
        
        // set default project
        $this->project = $this->modx->getOption('phpconsole.project', null, 'default');
        
        $configSetting = $this->modx->fromJSON($this->modx->getOption('phpconsole.config', null, ''));
        
        if (!empty($configSetting)) {
            // load config from system setting
            $this->config->loadFromArray($configSetting);
            
        } else if (file_exists(MODX_CORE_PATH.'config/phpconsole-config.inc.php')) {
            // load config from file
            $this->config->loadFromLocation(MODX_CORE_PATH.'config/phpconsole-config.inc.php');
            
        } else {
            // if no config exists, switch back to the FILE logTarget
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
    public function send($payload, $options = array(), $metadata = array()) {
        // return the original data
        $return = $payload;
        
        // set to project specified in MODX system settings
        if (!isset($options['project'])) $options['project'] = $this->project;
        
        // if empty metadata, do a backtrace
        if (empty($metadata)) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
            $backtrace = array_shift($backtrace);
            $metadata['file'] = $backtrace['file'];
            $metadata['line'] = $backtrace['line'];
        }
        
        // fix array keys to match phpconsole api
        if (isset($metadata['file'])) {
            $metadata['fileName'] = $metadata['file'];
            unset($metadata['file']);
        }
        if (isset($metadata['line'])) {
            $metadata['lineNumber'] = $metadata['line'];
            unset($metadata['line']);
        }
        
        // if $payload is a xPDO Object, convert it to an array
        if ($payload instanceof xPDOObject) {
            $payload = $payload->toArray();
        }
        
        // make sure phpconsole was initialized
        if (!$this->phpconsole) {
            // save to normal error log file if phpconsole is not available    
            if ($this->modx->getCacheManager()) {
                $filepath = $this->modx->getCachePath() . xPDOCacheManager::LOG_DIR . 'error.log';
                $this->modx->cacheManager->writeFile($filepath, var_export($payload,true)."\n", 'a');
            }
        } else {
            // call send method of phpconsole
            try {
                $this->phpconsole->send($payload, $options, $metadata);
                
            } catch (Exception $e) {
                if ($this->modx->getCacheManager()) {
                    $filepath = $this->modx->getCachePath() . xPDOCacheManager::LOG_DIR . 'error.log';
                    $this->modx->cacheManager->writeFile($filepath, '[Phpconsole] Exception ' . $e->getCode() . ': ' . $e->getMessage()."\n", 'a');
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
            $project = $modx->getOption('phpconsole.project', null, 'default');
            $modx->phpconsole->send($error, array('project' => $project, 'type' => 'error'));
        } 
    }
}
