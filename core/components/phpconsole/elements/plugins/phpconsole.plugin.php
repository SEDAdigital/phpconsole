<?php
if (!isset($modx->phpconsole)) return;
if (!is_array($modx->phpconsole->logArray)) return;

foreach ($modx->phpconsole->logArray as $payload) {
    
    $options = array();
    $metadata = array();
    
    if (isset($payload['file']) && isset($payload['line'])) {
        // remove invalid characters from filename and linenumber (added by modx::log())
        $file = trim(str_replace('@ ', '', $payload['file']));
        $line = trim(str_replace(':', '', $payload['line']));
        
        // prepend MODX base path to index.php errors
        if ($file === '/index.php') $file = MODX_BASE_PATH.$file;
        
        // make sure the file exists
        if (file_exists($file)) {
            $metadata['fileName'] = $file;
            $metadata['lineNumber'] = $line;
        }
    }
    
    // set type to error if level is ERROR or FATAL
    if (isset($payload['level']) && ($payload['level'] == 'ERROR' || $payload['level'] == 'FATAL')) {
        $options['type'] = 'error';
    }
    
    // set payload to the actual error string
    $payload = $payload['msg'];
        
    $modx->phpconsole->send($payload, $options, $metadata);
}
?>