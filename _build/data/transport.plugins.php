<?php
$plugins = array();

/* create the plugin object */
$plugins[0] = $modx->newObject('modPlugin');
$plugins[0]->set('id',1);
$plugins[0]->set('name','phpconsole');
$plugins[0]->set('description','The plugin that sends logging messages to phpconsole after send the client response');
$plugins[0]->set('plugincode', getSnippetContent($sources['plugins'] . 'phpconsole.plugin.php'));
$plugins[0]->set('category', 0);

$events = array();
$events['OnWebPageComplete'] = $modx->newObject('modPluginEvent');
$events['OnWebPageComplete']->fromArray(array(
    'event' => 'OnWebPageComplete',
    'priority' => 0,
    'propertyset' => 0
),'',true,true);
$events['OnManagerPageAfterRender'] = $modx->newObject('modPluginEvent');
$events['OnManagerPageAfterRender']->fromArray(array(
    'event' => 'OnManagerPageAfterRender',
    'priority' => 0,
    'propertyset' => 0
),'',true,true);

$plugins[0]->addMany($events);

$modx->log(xPDO::LOG_LEVEL_INFO,'Packaged in '.count($events).' Plugin Events.'); flush();

unset($events);
return $plugins;
?>
