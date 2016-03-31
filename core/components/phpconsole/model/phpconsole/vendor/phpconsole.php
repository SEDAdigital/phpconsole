<?php

/**
 * A detached logging facility for PHP to aid your daily development routine.
 *
 * Watch quick tutorial at: https://vimeo.com/58393977
 *
 * @link http://phpconsole.com
 * @link https://github.com/phpconsole
 * @copyright Copyright (c) 2012 - 2014 phpconsole.com
 * @license See LICENSE file
 * @version 3.4.0
 */


namespace Phpconsole
{
    use \Legierski\AES\AES as Crypto;

    interface LoggerInterface
    {
        public function log($message);
    }

    class Client
    {
        protected $config;

        public function __construct(Config &$config = null)
        {
            $this->config = $config ?: new Config;
        }

        public function log($message, $highlight = false)
        {
            if ($this->config->debug) {
                $_ENV['PHPCONSOLE_DEBUG_LOG'][] = array(microtime(true), $message, $highlight);
            }
        }

        public function send($payload)
        {
            $headers     = array('Content-Type: application/x-www-form-urlencoded');
            $post_string = http_build_query($payload);
            $cacertPath  = dirname(__FILE__).'/cacert.pem';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->config->apiAddress);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            if (file_exists($cacertPath)) {

                $this->log('cacert.pem file found, verifying API endpoint');

                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_CAINFO, $cacertPath);

            } else {

                $this->log('cacert.pem file not found, the API endpoint will not be verified', true);

                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }

            curl_exec($ch);
            $curl_error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($http_code !== 200) {

                throw new \Exception('cURL error code '.$http_code.': '.$curl_error);
            }
        }
    }

    class Config implements LoggerInterface
    {
        public $debug            = false; // temporary
        public $apiAddress       = 'https://app.phpconsole.com/api/0.3/';
        public $defaultProject   = 'none';
        public $projects         = array();
        public $backtraceDepth   = 3;
        public $isContextEnabled = true;
        public $contextSize      = 10;
        public $captureWith      = 'print_r';
        public $UUID             = '00000000-0000-0000-0000-000000000000';
        public $cookiesAllowed   = true;

        public function __construct()
        {
            $this->loadFromDefaultLocation();
        }

        public function log($message, $highlight = false)
        {
            if ($this->debug) {
                $_ENV['PHPCONSOLE_DEBUG_LOG'][] = array(microtime(true), $message, $highlight);
            }
        }

        public function loadFromDefaultLocation()
        {
            $defaultLocations = array(
                'phpconsole_config.php',
                'app/config/phpconsole.php',
                'app/config/packages/phpconsole/phpconsole/config.php',
                'config/packages/phpconsole/phpconsole/config.php',
                'application/config/phpconsole.php',
                '../phpconsole_config.php',
                '../app/config/phpconsole.php',
                '../app/config/packages/phpconsole/phpconsole/config.php',
                '../application/config/phpconsole.php',
                dirname(__FILE__).'/phpconsole_config.php'
            );

            if (defined('LARAVEL_START') && function_exists('app_path')) {

                $defaultLocations[] = app_path().'/config/phpconsole.php';
                $defaultLocations[] = app_path().'/config/packages/phpconsole/phpconsole/config.php';
            }

            if (defined('PHPCONSOLE_CONFIG_LOCATION')) {
                $this->log('Found \'PHPCONSOLE_CONFIG_LOCATION\' constant - adding to the list of locations to check');
                array_unshift($defaultLocations, PHPCONSOLE_CONFIG_LOCATION);
            }

            foreach ($defaultLocations as $location) {
                if (file_exists($location)) {

                    $this->log('Config file found in '.$location);
                    return $this->loadFromLocation($location);
                }
            }

            $this->debug = true; // temporary

            $this->log('Config file not found - this is really bad!', true);

            return false;
        }

        public function loadFromLocation($location)
        {
            if (!is_string($location)) {
                throw new Exception('Location should be a string', 1);
            }

            if (!file_exists($location)) {
                throw new Exception('File doesn\'t exist', 1);
            }

            $config = include $location;

            $this->log('Config loaded from file into array');

            return $this->loadFromArray($config);
        }

        public function loadFromArray(array $config)
        {
            foreach ($config as $configItemName => $configItemValue) {

                if (isset($this->{$configItemName})) {

                    $this->{$configItemName} = $configItemValue;
                }
            }

            $this->log('Config loaded from array into Config object');

            $this->determineUUID();
            $this->determineDefaultProject();

            return true;
        }

        protected function determineDefaultProject()
        {
            if (isset($_COOKIE['phpconsole_default_project'])) {

                $this->log('Default project loaded from cookie "phpconsole_default_project"');
                $this->defaultProject = $_COOKIE['phpconsole_default_project'];
            } elseif (file_exists('.phpconsole_default_project')) {

                $this->log('Default project loaded from file .phpconsole_default_project');
                $this->defaultProject = trim(@file_get_contents('.phpconsole_default_project'));
            } elseif (defined('PHPCONSOLE_DEFAULT_PROJECT')) {

                $this->log('Default project loaded from constant "PHPCONSOLE_DEFAULT_PROJECT"');
                $this->defaultProject = PHPCONSOLE_DEFAULT_PROJECT;
            }

            $this->log('Default project determined as "'.$this->defaultProject.'"');
        }

        public function getApiKeyFor($project)
        {
            if (isset($this->projects[$project]) && isset($this->projects[$project]['apiKey'])) {

                $this->log('API key for "'.$project.'" found');
                return $this->projects[$project]['apiKey'];
            } else {

                $this->log('API key for "'.$project.'" not found', true);
                return null;
            }
        }

        public function getEncryptionPasswordFor($project)
        {
            if (isset($this->projects[$project]) && isset($this->projects[$project]['encryptionPassword'])) {

                $this->log('Encryption password for "'.$project.'" found');
                return $this->projects[$project]['encryptionPassword'];
            } else {

                $this->log('Encryption password for "'.$project.'" not found (not specified in config?)', true);
                return null;
            }
        }

        protected function determineUUID()
        {
            $UUIDRegex = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

            if (isset($_COOKIE['phpconsole_UUID']) && preg_match($UUIDRegex, $_COOKIE['phpconsole_UUID'])) {

                $this->log('Existing UUID found');
                $this->UUID = $_COOKIE['phpconsole_UUID'];

            } else {

                $this->log('UUID not found, generating a new one...', true);

                $this->UUID = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

                if ($this->cookiesAllowed) {
                    setcookie('phpconsole_UUID', $this->UUID, time()+60*60*24*365*10);
                    $this->log('Cookie phpconsole_UUID created');
                }
                else {
                    $this->log('Creating cookies is not allowed, cookie phpconsole_UUID was NOT created', true);
                }
            }
        }
    }

    class Debugger
    {
        protected $config;

        public function __construct(Config &$config = null)
        {
            $this->config = $config ?: new Config;
        }

        public function displayDebugInfo()
        {
            if ($this->config->debug) {

                echo '
                <style>
                    .phpconsole-debugger {
                        background-color: #E7E6E3;
                        padding: 20px;
                        font-family: Verdana;
                    }

                    .phpconsole-debugger .phpconsole-header {
                        font-size: 24px;
                        margin: 0 0 20px;
                    }

                    .phpconsole-debugger .phpconsole-subheader {
                        font-size: 14px;
                        margin: 0 0 20px;
                    }

                    .phpconsole-debugger .phpconsole-subheader a {
                        color: #08c;
                        text-decoration: none;
                    }

                    .phpconsole-debugger .phpconsole-subheader a:hover {
                        color: #005580;
                        text-decoration: underline;
                    }

                    .phpconsole-debugger .phpconsole-table {
                        background-color: #fff;
                        border: 1px solid #aaaaaa;
                        width: 100%;
                        border-spacing: 0;
                        border-collapse: collapse;
                        font-size: 12px;
                        margin-bottom: 20px;
                    }

                    .phpconsole-debugger .phpconsole-table td {
                        padding: 7px;
                        margin: 0;
                        vertical-align: top;
                    }

                    .phpconsole-debugger .phpconsole-table td:first-child {
                        width: 150px;
                    }

                    .phpconsole-debugger .phpconsole-table thead td {
                        font-weight: bold;
                    }

                    .phpconsole-debugger .phpconsole-table tbody td {
                        border-top: 1px solid #ddd;
                    }

                    .phpconsole-debugger .phpconsole-table tbody tr:nth-child(odd) {
                        background-color: #f9f9f9;
                    }

                    .phpconsole-highlight {
                        color: #c00;
                    }
                </style>
                ';


                $log = $this->getLog();
                $log_html = '';

                foreach ($_ENV['PHPCONSOLE_DEBUG_LOG'] as $row) {
                    $log_html .= '
                    <tr class="'.($row[2]?'phpconsole-highlight':'').'">
                        <td>
                            '.$row[0].'
                        </td>
                        <td>
                            '.$row[1].'
                        </td>
                    </tr>
                    ';
                }

                $info = $this->getInfo();

                echo '
                <div class="phpconsole-debugger">

                    <h1 class="phpconsole-header">
                        Phpconsole debug info
                    </h1>

                    <p class="phpconsole-subheader">
                        Need help? Contact support: <a href="mailto:support@phpconsole.com">support@phpconsole.com</a>
                    </p>

                    <table class="phpconsole-table">
                        <thead>
                            <tr>
                                <td>Timestamp</td>
                                <td>Event</td>
                            </tr>
                        </thead>
                        <tbody>
                            '.$log_html.'
                        </tbody>
                    </table>

                    <table class="phpconsole-table">
                        <thead>
                            <tr>
                                <td>Name</td>
                                <td>Value</td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Phpconsole version</td>
                                <td>'.$info['phpconsoleVersion'].'</td>
                            </tr>
                            <tr>
                                <td>PHP version</td>
                                <td>'.$info['phpVersion'].'</td>
                            </tr>
                            <tr>
                                <td>cURL enabled</td>
                                <td>'.$info['curlEnabled'].'</td>
                            </tr>
                            <tr>
                                <td>Hostname</td>
                                <td>'.$info['hostname'].'</td>
                            </tr>
                            <tr>
                                <td>Config values</td>
                                <td>
                                    <pre>'.$info['config'].'</pre>
                                </td>
                            </tr>
                            <tr>
                                <td>$_SERVER values</td>
                                <td>
                                    <pre>'.$info['server'].'</pre>
                                </td>
                            </tr>
                            <tr>
                                <td>Config exists</td>
                                <td>'.$info['classExists']['Config'].'</td>
                            </tr>
                            <tr>
                                <td>Debugger exists</td>
                                <td>'.$info['classExists']['Debugger'].'</td>
                            </tr>
                            <tr>
                                <td>Dispatcher exists</td>
                                <td>'.$info['classExists']['Dispatcher'].'</td>
                            </tr>
                            <tr>
                                <td>Encryptor exists</td>
                                <td>'.$info['classExists']['Encryptor'].'</td>
                            </tr>
                            <tr>
                                <td>MetadataWrapper exists</td>
                                <td>'.$info['classExists']['MetadataWrapper'].'</td>
                            </tr>
                            <tr>
                                <td>P exists</td>
                                <td>'.$info['classExists']['P'].'</td>
                            </tr>
                            <tr>
                                <td>Phpconsole exists</td>
                                <td>'.$info['classExists']['Phpconsole'].'</td>
                            </tr>
                            <tr>
                                <td>Queue exists</td>
                                <td>'.$info['classExists']['Queue'].'</td>
                            </tr>
                            <tr>
                                <td>Snippet exists</td>
                                <td>'.$info['classExists']['Snippet'].'</td>
                            </tr>
                            <tr>
                                <td>SnippetFactory exists</td>
                                <td>'.$info['classExists']['SnippetFactory'].'</td>
                            </tr>
                        </tbody>
                    </table>

                </div>
                ';

            }
        }

        public function getLog()
        {
            return $_ENV['PHPCONSOLE_DEBUG_LOG'];
        }

        public function getInfo()
        {
            $info = array(
                'phpconsoleVersion' => Phpconsole::VERSION,
                'phpVersion' => phpversion(),
                'curlEnabled' => in_array('curl', get_loaded_extensions())?'yes':'no',
                'hostname' => gethostname(),
                'config' => print_r((array)$this->config, true),
                'server' => print_r($_SERVER, true),
                'classExists' => array(
                    'Config'          => class_exists('Phpconsole\Config')?'yes':'no',
                    'Debugger'        => class_exists('Phpconsole\Debugger')?'yes':'no',
                    'Dispatcher'      => class_exists('Phpconsole\Dispatcher')?'yes':'no',
                    'Encryptor'       => class_exists('Phpconsole\Encryptor')?'yes':'no',
                    'MetadataWrapper' => class_exists('Phpconsole\MetadataWrapper')?'yes':'no',
                    'P'               => class_exists('Phpconsole\P')?'yes':'no',
                    'Phpconsole'      => class_exists('Phpconsole\Phpconsole')?'yes':'no',
                    'Queue'           => class_exists('Phpconsole\Queue')?'yes':'no',
                    'Snippet'         => class_exists('Phpconsole\Snippet')?'yes':'no',
                    'SnippetFactory'  => class_exists('Phpconsole\SnippetFactory')?'yes':'no',
                )
            );

            return $info;
        }
    }

    class Dispatcher implements LoggerInterface
    {
        protected $config;
        protected $client;

        public function __construct(Config &$config = null, Client $client = null, MetadataWrapper $metadataWrapper = null)
        {
            $this->config          = $config          ?: new Config;
            $this->client          = $client          ?: new Client($this->config);
            $this->metadataWrapper = $metadataWrapper ?: new MetadataWrapper($this->config);
        }

        public function log($message, $highlight = false)
        {
            if ($this->config->debug) {
                $_ENV['PHPCONSOLE_DEBUG_LOG'][] = array(microtime(true), $message, $highlight);
            }
        }

        public function dispatch(Queue $queue)
        {
            $snippets     = $this->prepareForDispatch($queue->flush());
            $isCliRequest = $this->metadataWrapper->isCliRequest();

            if (count($snippets) > 0) {

                $this->log('Snippets found in the queue, preparing POST request');

                try {

                    $payload = array(
                        'type'     => Phpconsole::TYPE,
                        'version'  => Phpconsole::VERSION,
                        'UUID'     => $this->config->UUID,
                        'snippets' => $snippets,
                        'cli'      => $isCliRequest
                    );

                    $this->client->send($payload);

                    $this->log('Request successfully sent to the API endpoint');

                } catch (\Exception $e) {
                    $this->log('Request failed. Exception message: '.$e->getMessage(), true);
                }
            } else {
                $this->log('No snippets found in the queue, dispatcher exits', true);
            }
        }

        public function prepareForDispatch(array $snippets)
        {
            $snippetsAsArrays = array();

            if (count($snippets) > 0) {

                $this->log('Snippets found, preparing for dispatch');

                foreach ($snippets as $snippet) {

                    $snippetsAsArrays[] = array(
                        'payload'           => $snippet->payload,

                        'type'              => $snippet->type,
                        'projectApiKey'     => $snippet->projectApiKey,
                        'encryptionVersion' => $snippet->encryptionVersion,
                        'isEncrypted'       => $snippet->isEncrypted,

                        'fileName'          => $snippet->fileName,
                        'lineNumber'        => $snippet->lineNumber,
                        'context'           => $snippet->context,
                        'address'           => $snippet->address,
                        'hostname'          => $snippet->hostname
                    );

                    $this->log('Snippet prepared for dispatch');
                }

                $this->log('All snippets prepared for dispatch');
            }

            return $snippetsAsArrays;
        }
    }

    class Encryptor implements LoggerInterface
    {
        protected $config;
        protected $crypto;

        protected $password;
        protected $version = 1; // v1: AES-256, CBC, OpenSSL-compatible, legierski/aes v0.1.0

        public function __construct(Config &$config = null, Crypto $crypto = null)
        {
            $this->config = $config ?: new Config;
            $this->crypto = $crypto ?: new Crypto;
        }

        public function log($message, $highlight = false)
        {
            if ($this->config->debug) {
                $_ENV['PHPCONSOLE_DEBUG_LOG'][] = array(microtime(true), $message, $highlight);
            }
        }

        public function setPassword($password)
        {
            $this->password = $password;
        }

        public function encrypt($plaintext)
        {
            return $this->crypto->encrypt($plaintext, $this->password);
        }

        public function getVersion()
        {
            return $this->version;
        }
    }

    class MetadataWrapper
    {
        protected $config;

        public function __construct(Config &$config = null)
        {
            $this->config = $config ?: new Config;
        }

        public function server()
        {
            return $_SERVER;
        }

        public function file($fileName)
        {
            return file($fileName);
        }

        public function debugBacktrace()
        {
            if (version_compare(PHP_VERSION, '5.3.6') >= 0) {
                return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            } else {
                return debug_backtrace();
            }
        }

        public function gethostname()
        {
            return gethostname();
        }

        public function isCliRequest()
        {
            return (php_sapi_name() === 'cli' || defined('STDIN'));
        }
    }

    class P
    {
        protected static $phpconsole = null;

        public static function send($payload, $options = array(), $metadata = array())
        {
            return self::getPhpconsole()->send($payload, $options, $metadata);
        }

        public static function success($payload, $options = array(), $metadata = array())
        {
            if (is_string($options)) {
                $options = array('project' => $options);
            }

            $options = array_merge(array('type' => 'success'), $options);

            return self::getPhpconsole()->send($payload, $options, $metadata);
        }

        public static function info($payload, $options = array(), $metadata = array())
        {
            if (is_string($options)) {
                $options = array('project' => $options);
            }

            $options = array_merge(array('type' => 'info'), $options);

            return self::getPhpconsole()->send($payload, $options, $metadata);
        }

        public static function error($payload, $options = array(), $metadata = array())
        {
            if (is_string($options)) {
                $options = array('project' => $options);
            }

            $options = array_merge(array('type' => 'error'), $options);

            return self::getPhpconsole()->send($payload, $options, $metadata);
        }

        public static function sendToAll($payload, $options = array(), $metadata = array())
        {
            return self::getPhpconsole()->sendToAll($payload, $options, $metadata);
        }

        protected static function getPhpconsole()
        {
            if (is_null(self::$phpconsole)) {

                $config = new Config;
                $config->loadFromArray(array(
                    'backtraceDepth' => 3
                ));

                self::$phpconsole = new Phpconsole($config);
            }

            return self::$phpconsole;
        }
    }

    class Phpconsole implements LoggerInterface
    {
        const TYPE    = 'php-composer';
        const VERSION = '3.4.0';

        protected $config;
        protected $queue;
        protected $snippetFactory;
        protected $dispatcher;
        protected $debugger;

        protected $log;

        public function __construct(Config $config = null, Queue $queue = null, SnippetFactory $snippetFactory = null, Dispatcher $dispatcher = null, Debugger $debugger = null)
        {
            $this->config         = $config         ?: new Config;
            $this->queue          = $queue          ?: new Queue($this->config);
            $this->snippetFactory = $snippetFactory ?: new SnippetFactory($this->config);
            $this->dispatcher     = $dispatcher     ?: new Dispatcher($this->config);
            $this->debugger       = $debugger       ?: new Debugger($this->config);
        }

        public function log($message, $highlight = false)
        {
            if ($this->config->debug) {
                $_ENV['PHPCONSOLE_DEBUG_LOG'][] = array(microtime(true), $message, $highlight);
            }
        }

        public function send($payload, $options = array(), $metadata = array())
        {
            $snippet = $this->snippetFactory->create();

            $snippet->setOptions($options);
            $snippet->setPayload($payload);
            $snippet->setMetadata($metadata);
            $snippet->encrypt();

            $this->queue->add($snippet);

            return $payload;
        }

        public function sendToAll($payload, $options = array(), $metadata = array())
        {
            $this->config->backtraceDepth++;

            $projects = $this->config->projects;

            if (is_array($projects) && count($projects) > 0) {
                foreach ($projects as $name => $api_key) {

                    $options = array_merge($options, array('project' => $name));

                    $this->send($payload, $options, $metadata);
                }
            }

            $this->config->backtraceDepth--;

            return $payload;
        }

        public function __destruct()
        {
            $this->dispatch();
            $this->displayDebugInfo();
        }

        public function dispatch()
        {
            $this->dispatcher->dispatch($this->queue);
        }

        public function displayDebugInfo()
        {
            $this->debugger->displayDebugInfo();
        }
    }

    class Queue implements LoggerInterface
    {
        protected $config;

        protected $queue = array();

        public function __construct(Config &$config = null)
        {
            $this->config = $config ?: new Config;
        }

        public function log($message, $highlight = false)
        {
            if ($this->config->debug) {
                $_ENV['PHPCONSOLE_DEBUG_LOG'][] = array(microtime(true), $message, $highlight);
            }
        }

        public function add(Snippet $snippet)
        {
            if ($snippet->projectApiKey !== null) {
                $this->queue[] = $snippet;
                $this->log('Snippet added to the queue');
            } else {
                $this->log('Project API key not found - snippet not added to the queue', true);
            }

            return $snippet;
        }

        public function flush()
        {
            $queue = $this->queue;
            $this->queue = array();

            $this->log('Queue flushed');

            return $queue;
        }
    }

    class Snippet implements LoggerInterface
    {
        protected $config;
        protected $metadataWrapper;
        protected $encryptor;

        public $payload;

        public $type;
        public $project;
        public $projectApiKey;
        public $encryptionVersion;
        public $isEncrypted = false;

        public $fileName;
        public $lineNumber;
        public $context;
        public $address;
        public $hostname;

        public function __construct(Config &$config = null, MetadataWrapper $metadataWrapper = null, Encryptor $encryptor = null)
        {
            $this->config          = $config          ?: new Config;
            $this->metadataWrapper = $metadataWrapper ?: new MetadataWrapper($this->config);
            $this->encryptor       = $encryptor       ?: new Encryptor($this->config);
        }

        public function log($message, $highlight = false)
        {
            if ($this->config->debug) {
                $_ENV['PHPCONSOLE_DEBUG_LOG'][] = array(microtime(true), $message, $highlight);
            }
        }

        public function setPayload($payload)
        {
            $this->payload = $this->preparePayload($payload);

            $this->log('Payload set for snippet');
        }

        public function setOptions($options)
        {
            $options = $this->prepareOptions($options);

            $this->type    = $options['type'];
            $this->project = $options['project'];

            $this->log('Options set for snippet');

            $this->projectApiKey = $this->config->getApiKeyFor($this->project);
        }

        public function setMetadata($metadata = array())
        {
            $metadata = $this->prepareMetadata($metadata);

            $this->fileName   = base64_encode($metadata['fileName']);
            $this->lineNumber = base64_encode($metadata['lineNumber']);
            $this->context    = base64_encode($metadata['context']);
            $this->address    = base64_encode($metadata['address']);
            $this->hostname   = base64_encode($metadata['hostname']);

            $this->log('Metadata set for snippets');
        }

        public function encrypt()
        {
            $password = $this->config->getEncryptionPasswordFor($this->project);

            if ($password !== null) {

                $this->encryptor->setPassword($password);

                $this->log('Password set for encryptor');

                $this->payload    = base64_decode($this->payload);
                $this->fileName   = base64_decode($this->fileName);
                $this->lineNumber = base64_decode($this->lineNumber);
                $this->context    = base64_decode($this->context);
                $this->address    = base64_decode($this->address);
                $this->hostname   = base64_decode($this->hostname);

                $this->payload    = $this->encryptor->encrypt($this->payload);
                $this->fileName   = $this->encryptor->encrypt($this->fileName);
                $this->lineNumber = $this->encryptor->encrypt($this->lineNumber);
                $this->context    = $this->encryptor->encrypt($this->context);
                $this->address    = $this->encryptor->encrypt($this->address);
                $this->hostname   = $this->encryptor->encrypt($this->hostname);

                $this->log('Snippet data encrypted');

                $this->encryptionVersion = $this->encryptor->getVersion();
                $this->isEncrypted = true;
            } else {
                $this->log('Snippet data not encrypted', true);
            }
        }

        protected function preparePayload($payload)
        {
            switch ($this->config->captureWith) {

                case 'print_r':
                    $payload = $this->replaceTrueFalseNull($payload);
                    $payload = print_r($payload, true);
                    break;

                case 'var_dump':
                    ob_start();
                    var_dump($payload);
                    $payload = ob_get_clean();
                    break;

                default:
                    $payload = 'Function to capture payload with not recognised';
            }

            $payload = base64_encode($payload);

            $this->log('Payload prepared for snippet');

            return $payload;
        }

        protected function prepareOptions($options)
        {
            if (is_string($options)) {
                $options = array('project' => $options);
            }

            if (!isset($options['project'])) {
                $options['project'] = $this->config->defaultProject;
            }

            if (!isset($options['type'])) {
                $options['type'] = 'normal';
            }

            $this->log('Options prepared for snippet');

            return $options;
        }

        protected function prepareMetadata($metadata)
        {
            $backtrace = $this->metadataWrapper->debugBacktrace();
            $depth     = $this->config->backtraceDepth;

            if (!isset($metadata['fileName'])) {
                $metadata['fileName'] = $backtrace[$depth]['file'];
            }

            if (!isset($metadata['lineNumber'])) {
                $metadata['lineNumber'] = $backtrace[$depth]['line'];
            }

            if (!isset($metadata['context'])) {
                $metadata['context'] = $this->readContext($metadata['fileName'], $metadata['lineNumber']);
            }

            if (!isset($metadata['address'])) {
                $metadata['address'] = $this->currentPageAddress();
            }

            if (!isset($metadata['hostname'])) {
                $metadata['hostname'] = $this->metadataWrapper->gethostname();
            }

            return $metadata;
        }

        protected function replaceTrueFalseNull($input)
        {
            if (is_array($input)) {
                if (count($input) > 0) {
                    foreach ($input as $key => $value) {
                        $input[$key] = $this->replaceTrueFalseNull($value);
                    }
                }
            } elseif (is_object($input)) {
                if (count($input) > 0) {
                    foreach ($input as $key => $value) {
                        $input->$key = $this->replaceTrueFalseNull($value);
                    }
                }
            }

            if ($input === true) {
                $input = 'true';
            } elseif ($input === false) {
                $input = 'false';
            } elseif ($input === null) {
                $input = 'null';
            }

            $this->log('true, false and null values replaced');

            return $input;
        }

        protected function readContext($fileName, $lineNumber)
        {
            $context = array();

            if ($this->config->isContextEnabled && function_exists('file')) {

                $file = $this->metadataWrapper->file($fileName);
                $contextSize = $this->config->contextSize;

                $contextFrom = $lineNumber - $contextSize - 1;
                $contextTo   = $lineNumber + $contextSize - 1;

                for ($i = $contextFrom; $i <= $contextTo; $i++) {

                    if ($i < 0 || $i >= count($file)) {
                        $context[] = '';
                    } else {
                        $context[] = $file[$i];
                    }
                }

                $this->log('Context read for snippet');
            }

            return json_encode($context);
        }

        protected function currentPageAddress()
        {
            $server = $this->metadataWrapper->server();
            $isCli  = $this->metadataWrapper->isCliRequest();

            if($isCli) {
                $address = 'n/a';
            } else {

                if (isset($server['HTTPS']) && $server['HTTPS'] == 'on') {
                    $address = 'https://';
                } else {
                    $address = 'http://';
                }

                if (isset($server['HTTP_HOST'])) {
                    $address .= $server['HTTP_HOST'];
                }

                if (isset($server['SERVER_PORT']) && $server['SERVER_PORT'] != '80') {

                    $port = $server['SERVER_PORT'];
                    $address_end = substr($address, -1*(strlen($port)+1));

                    if ($address_end !== ':'.$port) {
                        $address .= ':'.$port;
                    }
                }

                if (isset($server['REQUEST_URI'])) {
                    $address .= $server['REQUEST_URI'];
                }
            }

            $this->log('Current page address read for snippet');

            return $address;
        }
    }

    class SnippetFactory implements LoggerInterface
    {
        protected $config;

        public function __construct(Config &$config = null)
        {
            $this->config = $config ?: new Config;
        }

        public function log($message, $highlight = false)
        {
            if ($this->config->debug) {
                $_ENV['PHPCONSOLE_DEBUG_LOG'][] = array(microtime(true), $message, $highlight);
            }
        }

        public function create()
        {
            $snippet = new Snippet($this->config);

            $this->log('Snippet created');

            return $snippet;
        }
    }
}

namespace Legierski\AES
{
    class AES
    {
        public function encrypt($data, $password)
        {
            $salt = openssl_random_pseudo_bytes(8);

            $salted = '';
            $dx = '';

            // Salt the key(32) and iv(16) = 48
            while (strlen($salted) < 48) {

                $dx = md5($dx.$password.$salt, true);
                $salted .= $dx;
            }

            $key = substr($salted, 0, 32);
            $iv  = substr($salted, 32, 16);

            $encryptedData = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

            return base64_encode('Salted__' . $salt . $encryptedData);
        }

        public function decrypt($data, $password)
        {
            $data = base64_decode($data);
            $salt = substr($data, 8, 8);
            $ciphertext = substr($data, 16);

            $rounds = 3;
            $data00 = $password.$salt;
            $md5Hash = array();
            $md5Hash[0] = md5($data00, true);
            $result = $md5Hash[0];

            for ($i = 1; $i < $rounds; $i++) {

                $md5Hash[$i] = md5($md5Hash[$i - 1].$data00, true);
                $result .= $md5Hash[$i];
            }

            $key = substr($result, 0, 32);
            $iv  = substr($result, 32, 16);

            return openssl_decrypt($ciphertext, 'aes-256-cbc', $key, true, $iv);
        }

        public function wrapForOpenSSL($data)
        {
            return chunk_split($data, 64);
        }
    }
}


