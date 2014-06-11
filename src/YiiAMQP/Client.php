<?php

namespace YiiAMQP;
use PhpAmqpLib\Connection\AMQPConnection;
use YiiAMQP\Meta\AbstractMetaClient;

defined('AMQP_DEBUG') or define('AMQP_DEBUG',false);

/**
 * # AMQP Client
 *
 * Represents a connection to an AMQP service.
 *
 * @author Charles Pick <charles@codemix.com>
 * @package YiiAMQP
 */
class Client extends \CApplicationComponent
{
    /**
     * @var string the name of the default queue to use
     */
    protected $_defaultQueueName;

    /**
     * @var AbstractMetaClient the meta client for the message queue
     */
    protected $_metaClient;

    /**
     * @var ExchangeCollection the collection of exchanges for this connection
     */
    protected $_exchanges;

    /**
     * @var QueueCollection the collection of queues for this connection
     */
    protected $_queues;

    /**
     * @var \PhpAmqpLib\Connection\AbstractConnection the connection
     */
    protected $_connection;

    /**
     * @var array the connection configuration
     */
    protected $_connectionConfig = array();

    /**
     * @var \PhpAmqpLib\Channel\AbstractChannel the channel for the connection
     */
    protected $_channel;

    /**
     * @var array the Quality Of Service settings
     */
    protected $_qos;

    /**
     * @param AMQPConnection $connection
     */
    public function setConnection($connection)
    {
        if (!($connection instanceof \PhpAmqpLib\Connection\AbstractConnection)) {
            $this->_connectionConfig = $connection;
            $connection = $this->createConnection($connection);
        }
        $this->_connection = $connection;
    }

    /**
     * @return AMQPConnection
     */
    public function getConnection()
    {
        if ($this->_connection === null)
            $this->_connection = $this->createConnection($this->getConnectionConfig());
        return $this->_connection;
    }

    /**
     * Sets the connection config
     * @param array $connectionConfig
     */
    public function setConnectionConfig($connectionConfig)
    {
        if (empty($connectionConfig['host']))
            $connectionConfig['host'] = 'localhost';
        if (empty($connectionConfig['port']))
            $connectionConfig['port'] = 5672;
        if (empty($connectionConfig['user']))
            $connectionConfig['user'] = 'guest';
        if (empty($connectionConfig['password']))
            $connectionConfig['password'] = 'guest';
        if (empty($connectionConfig['vhost']))
            $connectionConfig['vhost'] = '/';
        $this->_connectionConfig = $connectionConfig;
    }

    /**
     * Gets the connection config
     * @return array
     */
    public function getConnectionConfig()
    {
        if ($this->_connectionConfig === array()) {
            $this->_connectionConfig = array(
                'host' => 'localhost',
                'port' => 5672,
                'user' => 'guest',
                'password' => 'guest',
                'vhost' => '/',
            );
        }
        return $this->_connectionConfig;
    }

    /**
     * Creates an AMQP connection
     * @param array $config the connection config
     *
     * @return AMQPConnection
     */
    protected function createConnection($config = array())
    {
        return new AMQPConnection(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password'],
            $config['vhost']
        );
    }

    /**
     * Sets the name of the default queue
     * @param string $defaultQueueName
     */
    public function setDefaultQueueName($defaultQueueName)
    {
        $this->_defaultQueueName = $defaultQueueName;
    }

    /**
     * Gets the name of the default queue
     * @return string the default queue name
     */
    public function getDefaultQueueName()
    {
        if ($this->_defaultQueueName === null)
            $this->_defaultQueueName = $this->createDefaultQueueName();
        return $this->_defaultQueueName;
    }

    /**
     * Creates a unique default queue name
     * @return string the default queue name
     */
    public function createDefaultQueueName()
    {
        return uniqid('queue.', true);
    }

    /**
     * Sets the meta client for this queue
     * @param AbstractMetaClient $metaClient
     */
    public function setMetaClient($metaClient)
    {
        if (!($metaClient instanceof AbstractMetaClient))
            $metaClient = $this->createMetaClient($metaClient);

        $this->_metaClient = $metaClient;
    }

    /**
     * @return AbstractMetaClient
     */
    public function getMetaClient()
    {
        if ($this->_metaClient === null)
            $this->_metaClient = $this->createMetaClient();
        return $this->_metaClient;
    }

    /**
     * Creates a meta client based on the given config
     *
     * @param array $config the configuration array
     *
     * @return AbstractMetaClient the meta client instance
     */
    protected function createMetaClient($config = array())
    {
        if (!isset($config['class']))
            $config['class'] = 'YiiAMQP\\Meta\\RabbitMQ\\MetaClient';
        $metaClient = \Yii::createComponent($config); /* @var AbstractMetaClient $metaClient */
        $metaClient->setClient($this);
        return $metaClient;
    }

    /**
     * @param \PhpAmqpLib\Channel\AbstractChannel $channel
     */
    public function setChannel($channel)
    {
        $this->_channel = $channel;
    }

    /**
     * @return \PhpAmqpLib\Channel\AMQPChannel
     */
    public function getChannel()
    {
        if ($this->_channel === null)
            $this->_channel = $this->getConnection()->channel();
        return $this->_channel;
    }

    /**
     * Sets the quality of service for the current channel.
     * QoS should be specified in a 3 element array containing the
     * prefetch size, prefetch count and whether or not the setting is globally applied.
     * @param array $qos
     */
    public function setQos($qos)
    {
        list($prefetchSize, $prefetchCount, $isGlobal) = $qos;
        $this->getChannel()->basic_qos($prefetchSize, $prefetchCount, $isGlobal);
        $this->_qos = $qos;
    }

    /**
     * Gets the quality of service for the current channel
     * @return array
     */
    public function getQos()
    {
        return $this->_qos;
    }


    /**
     * Sets the queues for this connection
     * @param \YiiAMQP\QueueCollection|array $queues
     */
    public function setQueues($queues)
    {
        if ($queues instanceof QueueCollection)
            $queues->client = $this;
        else
            $queues = $this->createQueueCollection($queues);
        $this->_queues = $queues;
    }

    /**
     * Gets the queues
     * @return \YiiAMQP\QueueCollection
     */
    public function getQueues()
    {
        if ($this->_queues === null)
            $this->_queues = $this->createQueueCollection();
        return $this->_queues;
    }

    /**
     * Gets the default queue for the application
     * @return Queue
     */
    public function getDefaultQueue()
    {
        return $this->getQueues()->itemAt($this->getDefaultQueueName());
    }

    /**
     * Sets the default queue
     * @param array|Queue $queue the queue instance or configuration
     */
    public function setDefaultQueue($queue)
    {
        if (!($queue instanceof Queue))
            $queue = $this->getQueues()->createQueue($queue);
        $this->getQueues()->add($this->getDefaultQueueName(), $queue);
    }

    /**
     * Create a collection of queues for this connection
     * @param array $data the data for the queues, if any.
     *
     * @return QueueCollection the queue collection
     */
    protected function createQueueCollection($data = array())
    {
        $collection = new QueueCollection();
        $collection->setClient($this);
        $collection->copyFrom($data);
        return $collection;
    }

    /**
     * Sets the exchanges for this connection
     * @param \YiiAMQP\ExchangeCollection|array $exchanges
     */
    public function setExchanges($exchanges)
    {
        if ($exchanges instanceof ExchangeCollection)
            $exchanges->setClient($this);
        else
            $exchanges = $this->createExchangeCollection($exchanges);
        $this->_exchanges = $exchanges;
    }

    /**
     * @return \YiiAMQP\ExchangeCollection
     */
    public function getExchanges()
    {
        if ($this->_exchanges === null)
            $this->_exchanges = $this->createExchangeCollection();
        return $this->_exchanges;
    }

    /**
     * Create a collection of exchanges for this connection
     * @param array $data the data for the exchanges, if any.
     *
     * @return ExchangeCollection the exchange collection
     */
    protected function createExchangeCollection($data = array())
    {
        $collection = new ExchangeCollection();
        $collection->setClient($this);
        $collection->copyFrom($data);
        return $collection;
    }

    /**
     * Waits until any pending callbacks are completed
     */
    public function wait()
    {
        $channel = $this->getChannel();
        while(count($channel->callbacks))
            $channel->wait();
    }
}
