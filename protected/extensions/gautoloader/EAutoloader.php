<?php

// Credit: mindplay-dk https://gist.github.com/4234540

require('GAutoloader.php');

class EAutoloader extends CApplicationComponent
{

    private static $autoloader=null;

    /**
     * @return GAutoloader
     */
    public static function getAutoloader()
    {
        
        if (self::$autoloader===null) {
            self::$autoloader = new GAutoloader();
        }

        return self::$autoloader;

    }

    public static function autoload($className)
    {
        
        if (self::getAutoloader()->load($className)) {
            return true;
        }

        return Yii::autoload($className);
    }
}

spl_autoload_unregister(array('YiiBase','autoload'));

spl_autoload_register(array('EAutoloader','autoload'));