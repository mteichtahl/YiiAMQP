<?php

Yii::app()->autoloader->getAutoloader()->addNamespace('PhpAmqpLib\Connection', __DIR__ . '/PhpAmqpLib/Connection');
Yii::app()->autoloader->getAutoloader()->addNamespace('PhpAmqpLib\Channel', __DIR__ . '/PhpAmqpLib/Channel');
Yii::app()->autoloader->getAutoloader()->addNamespace('PhpAmqpLib\Wire', __DIR__ . '/PhpAmqpLib/Wire');
Yii::app()->autoloader->getAutoloader()->addNamespace('PhpAmqpLib\Helper', __DIR__ . '/PhpAmqpLib/Helper');
Yii::app()->autoloader->getAutoloader()->addNamespace('PhpAmqpLib\Exception', __DIR__ . '/PhpAmqpLib/Exception');
Yii::app()->autoloader->getAutoloader()->addNamespace('PhpAmqpLib\Message', __DIR__ . '/PhpAmqpLib/Message');




class rabbitMQ extends CApplicationComponent {

    public $server;
    protected $connection;
    protected $channel;
    protected $queue;
    protected $exchange;
    
    private $callback;

    public function createConnection($host = NULL, $port = NULL, $user = NULL, $password = NULL, $vhost = NULL) {

        Yii::log('[' . get_class() . '] Creating connection' ,'info');

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
            Yii::log('['. get_class(). '] Cannot create connection', 'error');
            return false;
        }

        $this->channel = $this->connection->channel();

        if (!$this->channel) {
            Yii::log('['. get_class(). '] Cannot create channel', 'error');
            return false;
        }

        Yii::log('[' . get_class() . '] Channel ' . $this->channel->channel_id . ' created','info');

        Yii::log('[' . get_class() . '] Connected to ' . $this->server['host'] . ':' . $this->server['port'],'info');
        return $this->channel;
    }

    public function declareQueue($name, $passive = false, $durable = true, $exclusive = false, $auto_delete = false) {

        $ret = $this->channel->queue_declare($name, $passive, $durable, $exclusive, $auto_delete);

        if (!is_array($ret)) {
            Yii::log('['. get_class(). '] Cannot create queue', 'error');
            return false;
        }
        Yii::log('[' . get_class() . '] Created queue ' . $ret[0], 'info');
        $this->queue = $ret[0];

        return true;
    }

    public function declareExchange($name, $type = 'direct', $passive = false, $durable = true, $auto_delete = false) {

        $ret = $this->channel->exchange_declare($name, $type, $passive, $durable, $auto_delete);
        Yii::log('[' . get_class() . '] Created exchange ' . $name, 'info');
        $this->exchange = $name;
        return true;
    }
    
    public function setQos($prefetch_size, $prefetch_count, $a_global)
    {
        $this->channel->basic_qos($prefetch_size, $prefetch_count, $a_global);
    }

    public function bind($queue, $exchange) {

        $this->channel->queue_bind($queue, $exchange);
        Yii::log('[' . get_class() . '] Created binding [' . $queue . ' <--> ' . $exchange . ']','info');
    }

    public function consume() {
        $this->channel->basic_consume($this->queue, '', false, false, false, false, $this->callback);
    }

    public function wait() {
        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }
    
    public function registerCallback($callback)
    {
        //print_r($callback);
        
        if ( is_callable( $callback ) )  
        {
            Yii::log('[' . get_class() . '] Registering worker callback','info');
            $this->callback = $callback  ;
            $this->consume();
        }
        
        
            
    }

}

?>
