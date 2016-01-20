<?php

if ($object->xpdo) {
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            /** @var modX $modx */
            $modx =& $object->xpdo;
            $modelPath = $modx->getOption('phpconsole.core_path');
            if (empty($modelPath)) {
                $modelPath = '[[++core_path]]components/phpconsole/model/';
            }
            if ($modx instanceof modX) {
                $modx->addExtensionPackage('phpconsole',$modelPath, array(
                    'serviceName' => 'phpconsole',
                    'serviceClass' => 'phpconsoleX'
                ));
            }
            break;
        case xPDOTransport::ACTION_UNINSTALL:
            $modx =& $object->xpdo;
            if ($modx instanceof modX) {
                $modx->removeExtensionPackage('phpconsole');
            }
            break;
    }
}
return true;