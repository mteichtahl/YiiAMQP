<?php

Yii::app()->autoloader->getAutoloader()->addNamespace('PhpAmqpLib\Connection', __DIR__ . '/PhpAmqpLib/Connection');
Yii::app()->autoloader->getAutoloader()->addNamespace('PhpAmqpLib\Channel', __DIR__ . '/PhpAmqpLib/Channel');
Yii::app()->autoloader->getAutoloader()->addNamespace('PhpAmqpLib\Wire', __DIR__ . '/PhpAmqpLib/Wire');
Yii::app()->autoloader->getAutoloader()->addNamespace('PhpAmqpLib\Helper', __DIR__ . '/PhpAmqpLib/Helper');
Yii::app()->autoloader->getAutoloader()->addNamespace('PhpAmqpLib\Exception', __DIR__ . '/PhpAmqpLib/Exception');
Yii::app()->autoloader->getAutoloader()->addNamespace('PhpAmqpLib\Message', __DIR__ . '/PhpAmqpLib/Message');

class rabbitMQ extends CApplicationComponent {

    public $server;
    public $connection;
    public $channel;
    public $queue;
    public $exchange;
    public $managementQueue;
    public $managementExchange;
    public $managementCallback;
    private $callback;

    public function createConnection($host = NULL, $port = NULL, $user = NULL, $password = NULL, $vhost = NULL) {

        Yii::log('[' . get_class() . '] Creating connection', 'info');

        //check if we need to overwrite the defaults

        if ($host)
            $this->server['host'] = $host;

        if ($port)
            $this->server['port'] = $port;

        if ($user)
            $this->server['user'] = $user;

        if ($password)
            $this->server['password'] = $password;

        if ($vhost)
            $this->server['vhost'] = $vhost;

        // create the connection using $server as the config
        $this->connection = new PhpAmqpLib\Connection\AMQPConnection($this->server['host'], $this->server['port'], $this->server['user'], $this->server['password'], $this->server['vhost']);



        if (!$this->connection) {
            Yii::log('[' . get_class() . '] Cannot create connection', 'error');
            return false;
        }

        $this->channel = $this->connection->channel();

        if (!$this->channel) {
            Yii::log('[' . get_class() . '] Cannot create channel', 'error');
            return false;
        }

        Yii::log('[' . get_class() . '] Channel ' . $this->channel->channel_id . ' created', 'info');

        $this->managementExchange = $this->declareExchange('exchange.management', 'fanout');
        
        Yii::log('[' . get_class() . '] Connected to ' . $this->server['host'] . ':' . $this->server['port'], 'info');
        return $this->channel;
    }

    public function declareQueue($name = NULL, $passive = false, $durable = true, $exclusive = false, $auto_delete = true) {

        if (!$name)
            $name = $this->generateRandomString('10');

        $ret = $this->channel->queue_declare($name, $passive, $durable, $exclusive, $auto_delete);

        if (!is_array($ret)) {
            Yii::log('[' . get_class() . '] Cannot create queue', 'error');
            return false;
        }
        
        Yii::log('[' . get_class() . '] Created queue ' . $ret[0], 'info');
        $this->bind($name, 'exchange.management');
        $this->queue = $ret[0];

        return $name;
    }

    public function declareExchange($name, $type = 'direct', $passive = false, $durable = true, $auto_delete = true) {

        $ret = $this->channel->exchange_declare($name, $type, $passive, $durable, $auto_delete);
        Yii::log('[' . get_class() . '] Created exchange ' . $name . ' [ ' . $type . ' ]', 'info');
        $this->exchange = $name;
        return true;
    }

    public function setQos($prefetch_size, $prefetch_count, $a_global) {
        $this->channel->basic_qos($prefetch_size, $prefetch_count, $a_global);
    }

    public function bind($queue, $exchange, $routingKey = NULL) {

        $this->channel->queue_bind($queue, $exchange, $routingKey);
        Yii::log('[' . get_class() . '] Created binding [' . $queue . ' <--> ' . $exchange . ']', 'info');
    }

    public function consume($queue=NULL,$consumer=NULL, $noLocal=false, $noAck=false, $exclusive=false, $nowait=false) {
        
        if (!$queue)
            $queue = $this->queue;
        
        if ($consumer)
            $consumer = '';
        /*
          queue: Queue from where to get the messages
          consumer_tag: Consumer identifier
          no_local: Don't receive messages published by this consumer.
          no_ack: Tells the server if the consumer will acknowledge the messages.
          exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
          nowait: don't wait for a server response. In case of error the server will raise a channel
          exception
          callback: A PHP Callback
         */
        $this->channel->basic_consume($queue, $consumer, $noLocal, $noAck, $exclusive, $nowait, $this->callback);
    }

    public function wait() {
        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    public function getChannelId() {
        return $this->channel->getChannelId();
    }

    public function registerCallback($callback) {
        if (is_callable($callback)) {
            Yii::log('[' . get_class() . '] Registering worker callback', 'info');
            $this->callback = $callback;
           
        }
    }

    private function generateRandomString($length = 10) {
        $randomstring = '';
        if ($length > 32) {
            $multiplier = round($length / 32, 0, PHP_ROUND_HALF_DOWN);
            $remainder = $length % 32;
            for ($i = 0; $i < $multiplier; $i++) {
                $randomstring .= substr(str_shuffle(md5(rand())), 0, 32);
            }
            $randomstring .= substr(str_shuffle(md5(rand())), 0, $remainder);
        }
        else
            $randomstring = substr(str_shuffle(md5(rand())), 0, $length);
        return $randomstring;
    }

}

?>
