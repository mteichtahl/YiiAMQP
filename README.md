YiiAMQP
=======

YiiAMQP is a fully functional AMQP producer and conusumer Yii application component.

##Requirements

Tested with Yii version 1.1.13


##Quick Start

Install via composer, then configure your application to use this component by adding and updating to match your needs the following configuration

```php

'components' => array(
        'mq' => array(
            'class' => 'YiiAMQP\Client',
            'connection' => array(
                'host' => 'localhost',
                'port' => '5672',
                'vhost' => '/',
                'user' => 'guest',
                'password' => 'guest'
            )
        ),
```


##Usage

### Producer

```php
$myMessage = array('greeting' => 'Hello World');
Yii::app()->mq->exchanges->greeter->send($myMessage); // will be JSON encoded
```

### Consumer

Initialise the component

```php

Yii::app()->mq->defaultQueue->consume(function($message){ print_r($message); });
Yii::app()->mq->queues->myQueue->consume(function($message){ print_r($message); });
Yii::app()->mq->wait(); // wait for results
```

##Contributing
Please submit all pull requests against *-wip branches. Thanks!

##Bug tracker
If you find any bugs, please create an issue at [https://github.com/mteichtahl/YiiAMQP/issues](https://github.com/mteichtahl/YiiAMQP/issues)

##Credits

- php-amqplib [https://github.com/videlalvaro/php-amqplib] Vadim Zaliva lord@crocodile.org
- rabbitMQ [http://www.rabbitmq.com/] VMWare

##License


MIT.
