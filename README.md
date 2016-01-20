# phpconsole
This extra integrates [phpconsole](http://phpconsole.com/) into the (MODX)[http://modx.com/] CMS. You will see all `$modx->log()` calls in phpconsole instead of the default error log. You can also use `$modx->phpconsole->send($anything)` to send any data to phpconsole. You can even log XPDOObjects with phpconsoleX, since they are automatically converted to an array.


## Configuration
You can either use the system setting `phpconsole.config` (JSON string) for the configuration of phpconsole or you can save your configuration into a `phpconsole-config.inc.php` file (PHP array) into your MODX config folder.
This is a basic JSON configration for your `phpconsole.config` system setting:
```
{
    "debug": "false",
    "defaultProject": "default",
    "projects": {
        "default": {
            "apiKey": "your-api-key-here",
            "encryptionPassword": "optional-end-to-end-encryption-password"
        }
    },
    "backtraceDepth": "3"
}
```
For more information about the configuration please follow the phpconsole docs: https://github.com/phpconsole/phpconsole/blob/master/CONFIGURATION.md

## Installation
You can install phpconsoleX via the MODX Package Manager. Make sure to update the configuration after the installation – you need to add your api key!
