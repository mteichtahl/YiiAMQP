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


needs

 'application.extensions.gautoloader.*',
        'application.components.mail.YiiMailMessage',

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