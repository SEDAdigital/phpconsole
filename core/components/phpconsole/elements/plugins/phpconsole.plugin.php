<?php
if (!isset($modx->phpconsole)) return;
if (!is_array($modx->phpconsole->logArray)) return;

$project = $modx->getOption('phpconsole.project', null, 'default');

foreach ($modx->phpconsole->logArray as $entry) {
    $type = '';
    if ($entry['level'] == 'ERROR' || $entry['level'] == 'FATAL') $type = 'error';
    $modx->phpconsole->send($entry, array('project'=>$project, 'type'=>$type));
}
?>