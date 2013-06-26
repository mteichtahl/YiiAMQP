YiiAMQP
=======

YiiAMQP is a fully functional AMQP producer and conusumer Yii application component.

##Requirements

Tested with Yii version 1.1.13

##Dependencies

This component has a number of critical dependencies in order to function properly. Given the broad range on possible applications of this component these dependencies have not been included.

Please ensure you install and configure these dependencies prior to the installation of YiiAMQP.

- gautoloader [http://www.yiiframework.com/extension/gautoloader]. 
  This is just a repackage of mindplay-dk's GAutoloader [https://gist.github.com/4234540]
- CPSLiveLogRoute [http://www.yiiframework.com/wiki/140/real-time-logging/] 

### Documentation

Refer to docs/class-YiiAMQP.html for details on the component methods

##Quick Start
Clone the repo, `git clone git://github.com/mteichtahl/YiiAMQP.git`, or [download the latest release](https://github.com/mteichtahl/YiiAMQP/zipball/master).

We have provided a prebuilt composer.json which will include the required Guzzle dependency and ensure the proper configuration
of the namespaces.

Install composer and install the dependencies.

```
curl -s http://getcomposer.org/installer | php && ./composer.phar install

```



Configure your application to use this component by adding and updating to match your needs the following configuration

```php

'components' => array(
        'rabbitMQ' => array(
            'class' => 'application.components.RabbitMQ.RabbitMQ',
            'server' => array(
                'host' => 'localhost',
                'port' => '5672',
                'vhost' => '/',
                'user' => 'guest',
                'password' => 'guest'
            )
        ),
```

Due to the introduction of the YiiAMQP namespace the following must be added to the configuration file
either main.php or console.php to ensure Yii has knowledge of the namespace.

```php

Yii::setPathOfAlias('YiiAMQP', DIR.'/../components/YiiAMQP');

```

##Usage

### Producer

```php
Yii::app()->rabbitMQ->createConnection();
Yii::app()->rabbitMQ->declareQueue('mail');
Yii::app()->rabbitMQ->declareExchange('exchange.mailService', 'topic');
Yii::app()->rabbitMQ->bind('mail', 'exchange.mailService', 'mail');
Yii::app()->rabbitMQ->setQoS('0', '1', '0');
Yii::app()->rabbitMQ->sendJSONMessage('"test":"test"','mail');
Yii::app()->rabbitMQ->sendTextMessage('text message"','mail');
```

### Consumer

Initialise the component

```php
Yii::app()->rabbitMQ->declareExchange('exchange.mailService', 'topic');
Yii::app()->rabbitMQ->bind($queue, 'exchange.mailService', 'mail');
Yii::app()->rabbitMQ->setQoS('0', '1', '0');
Yii::app()->rabbitMQ->registerCallback(array($this, 'myCallback'));
Yii::app()->rabbitMQ->consume($queue, $this->id);
Yii::app()->rabbitMQ->wait();
```

Create the callback function

```php
public static function myCallback($msg) { }
```

##Contributing
Please submit all pull requests against *-wip branches. Thanks!

##Bug tracker
If you find any bugs, please create an issue at [https://github.com/mteichtahl/YiiAMQP/issues](https://github.com/mteichtahl/YiiAMQP/issues)

##Credits

- gaAutoLoader [https://gist.github.com/mindplay-dk/4234540] Rasmus Schultz
- CPSLiveLogRoute [http://www.pogostick.com] Jerry Ablan jablan@pogostick.com
- php-amqplib [https://github.com/videlalvaro/php-amqplib] Vadim Zaliva lord@crocodile.org
- rabbitMQ [http://www.rabbitmq.com/] VMWare

##License  
[![License](http://i.creativecommons.org/l/by-sa/3.0/88x31.png)](http://creativecommons.org/licenses/by-sa/3.0/)  
This work is licensed under a [Creative Commons Attribution-ShareAlike 3.0 Unported License](http://creativecommons.org/licenses/by-sa/3.0/)  