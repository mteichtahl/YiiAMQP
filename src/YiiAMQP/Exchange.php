<?php

namespace YiiAMQP;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * # Exchange
 *
 * Represents an AMQP message exchange.
 *
 * @package YiiAMQP
 */
class Exchange extends \CComponent
{
    /**
     * @var string the name of the exchange
     */
    public $name;

    /**
     * The exchange type.
     * Each exchange belongs to one of a set of exchange types implemented by the server.
     * The exchange types define the functionality of the exchange - i.e. how messages are routed through it.
     * It is not valid or meaningful to attempt to change the type of an existing exchange.
     * @var string
     */
    public $type = 'topic';

    /**
     * Whether or not the exchange is passive,
     * If true, the server will reply with Declare-Ok if the exchange already exists with the same name,
     * and raise an error if not. The client can use this to check whether an exchange exists without
     * modifying the server state. When set, all other method fields except name and no-wait are ignored.
     * A declare with both passive and no-wait has no effect. Arguments are compared for semantic equivalence.
     * @var bool
     */
    public $isPassive = false;

    /**
     * Whether or not the exchange is durable.     *
     * Durable exchanges remain active when a server restarts.
     * Non-durable exchanges (transient exchanges) are purged if/when a server restarts.
     * @var bool
     */
    public $isDurable = true;

    /**
     * Whether or not the exchange should be deleted when all queues have finished using it.
     * @var bool
     */
    public $autoDelete = true;

    /**
     * @var string the vhost for the exchange
     */
    public $vhost = '/';

    /**
     * @var null|string the default routing key for the exchange
     */
    public $routingKey = null;

    /**
     * @var Client the AMQP connection this exchange belongs to
     */
    protected $_client;

    /**
     * @var Queue the queue to bind to
     */
    protected $_queue;

    /**
     * @var bool whether or not the exchange has been initialized
     */
    protected $_isInitialized = false;

    /**
     * Sets the AMQP client for the exchange
     * @param \YiiAMQP\Client $client
     */
    public function setClient($client)
    {
        $this->_client = $client;
    }

    /**
     * Gets the AMQP client for the exchange
     * @throws \CException if no client is available
     * @return \YiiAMQP\Client the client
     */
    public function getClient()
    {
        if ($this->_client === null) {
            $app = \Yii::app();
            if (!$app->hasComponent('mq'))
                throw new \CException(__CLASS__." expects a 'mq' application component!");
            $this->_client = $app->getComponent('mq');
        }
        return $this->_client;
    }

    /**
     * @param boolean $isInitialized
     */
    public function setIsInitialized($isInitialized)
    {
        $this->_isInitialized = $isInitialized;
    }

    /**
     * @return boolean
     */
    public function getIsInitialized()
    {
        return $this->_isInitialized;
    }

    /**
     * @param \YiiAMQP\Queue $queue
     */
    public function setQueue($queue)
    {
        if (is_string($queue))
            $queue = $this->getClient()->getQueues()->itemAt($queue);
        $this->_queue = $queue;
    }

    /**
     * @return \YiiAMQP\Queue
     */
    public function getQueue()
    {
        if ($this->_queue === null)
            $this->_queue = $this->getClient()->getDefaultQueue();
        return $this->_queue;
    }

    /**
     * Initializes the exchange.
     * Will only run once no matter how many times it's called.
     */
    public function init()
    {
        if ($this->getIsInitialized())
            return;
        $client = $this->getClient();
        $client->getChannel()->exchange_declare(
            $this->name,
            $this->type,
            $this->isPassive,
            $this->isDurable,
            $this->autoDelete
        );
        
         if ($this->routingKey === null)
             $this->routingKey = $this->name;
         
        
        $this->getQueue()->bind($this, $this->routingKey);
        $this->setIsInitialized(true);
    }


    /**
     * Send the given message
     * @param string|AMQPMessage $message the message to send
     * @param string $routingKey the routing key to use
     */
    public function send($message, $routingKey = null)
    {
        $this->init();
        if ($routingKey === null)
            $routingKey = $this->routingKey;
        if (!($message instanceof AMQPMessage))
            $message = $this->createMessage($message);
        $this->getClient()->getChannel()->basic_publish($message, $this->name, $routingKey);
    }

    /**
     * Creates a message that can be sent
     *
     * @param string $content the message text
     * @param array $options the message options
     *
     * @return AMQPMessage the message instance
     */
    public function createMessage($content, $options = array('content_type' => 'application/json', 'delivery_mode' => 2))
    {
        if (!empty($options['content_type']) && $options['content_type'] == 'application/json')
            $content = json_encode($content);
        return new AMQPMessage($content, $options);
    }

}
