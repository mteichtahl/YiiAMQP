<?php

// This is the configuration for yiic console application.
// Any writable CConsoleApplication properties can be configured here.


return array(
    'basePath' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..',
    'name' => 'MailServer',
    // preloading 'log' component
    'preload' => array('log'),
    'import' => array(
        'application.extensions.gautoloader.*',
        'application.components.mail.YiiMailMessage',
    ),
    // application components
    'components' => array(
        'rabbitMQ' => array(
            'class' => 'application.components.RabbitMQ.RabbitMQ',
            'server' => array(
                'host' => 'localhost',
                'port' => '5672',
                'vhost' => '/',
                'user' => 'mailServer',
                'password' => 'mail'
            )
        ),
        'db' => array(
            'connectionString' => 'sqlite:' . dirname(__FILE__) . '/../data/testdrive.db',
        ),
        // uncomment the following to use a MySQL database
        /*
          'db'=>array(
          'connectionString' => 'mysql:host=localhost;dbname=testdrive',
          'emulatePrepare' => true,
          'username' => 'root',
          'password' => '',
          'charset' => 'utf8',
          ),
         */
        'autoloader' => array(
            'class' => 'ext.gautoloader.EAutoloader'
        ),
        'log' => array(
            'class' => 'CLogRouter',
            'routes' => array(
                array(
                    'class' => 'application.components.CPSLiveLogRoute',
                    'levels' => 'error, warning, info, trace',
                    'maxFileSize' => '10240',
                    'logFile' => 'mailServer',
                    //  Optional excluded category
                    'excludeCategories' => array(
                        'system.db.CDbCommand',
                        'system.CModule'
                    ),
                ),
            ),
        ),
    ),
    'params' => array(
        
        'adminEmail' => 'webmaster@example.com',
        'smtpServer' => 'smtp.googlemail.com',
        'smtpPort'  => '465',
        'smtpConn'  => 'ssl',
        'smtpUsername' => 'marc@teichtahl.com',
        'smtpPassword' => 'Nmiv9qyqn!',
        'timezone' => 'Australia/Melbourne'
    ),
);