<?php

namespace YiiAMQP;

/**
 * Represents an AMQP queue
 * @package YiiAMQP
 */
class Queue extends \CComponent
{
    /**
     * @var string the name of the queue
     */
    public $name;

    /**
     * Whether or not the queue is exclusive to this connection.
     * Exclusive queues may only be accessed by the current connection,  and are deleted when that
     * connection closes. Passive declaration of an exclusive queue by other connections are not allowed.
     * @var bool
     */
    public $isExclusive = false;

    /**
     * Whether or not the queue is passive,
     * If true, the server will reply with Declare-Ok if the queue already exists with the same name,
     * and raise an error if not
     * @var bool
     */
    public $isPassive = false;

    /**
     * Whether or not the exchange is durable.     *
     * Durable queues remain active when a server restarts.
     * Non-durable queues (transient queues) are purged if/when a server restarts.
     * Note that durable queues do not necessarily hold persistent messages,  although it
     * does not make sense to send persistent messages to a transient queue.
     * @var bool
     */
    public $isDurable = true;

    /**
     * Whether or not the queue should be deleted when all consumers have finished using it.
     * The last consumer can be cancelled either explicitly or because its channel is closed.
     * If there was no consumer ever on the queue, it won't be deleted.
     * Applications can explicitly delete auto-delete queues using the Delete method as normal.
     * @var bool
     */
    public $autoDelete = true;

    /**
     * @var string the queue status
     */
    public $status;

    /**
     * @var string the vhost for the queue
     */
    public $vhost = '/';

    /**
     * @var string the queue identifier
     */
    protected $_identifier;

    /**
     * @var Client the AMQP connection this queue belongs to
     */
    protected $_client;


    /**
     * @var bool whether or not the queue has been initialized
     */
    protected $_isInitialized = false;

    /**
     * Sets the AMQP client for the queue
     * @param \YiiAMQP\Client $client
     */
    public function setClient($client)
    {
        $this->_client = $client;
    }

    /**
     * Gets the AMQP client for the queue
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
     * Gets the identifier for the queue.
     * @return string the identifier
     */
    public function getIdentifier()
    {
        return $this->_identifier;
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
     * Initializes the queue.
     * Will only run once no matter how many times it's called.
     */
    public function init()
    {
        if ($this->getIsInitialized())
            return;
        $response = $this->getClient()->getChannel()->queue_declare(
            $this->name,
            $this->isPassive,
            $this->isDurable,
            $this->isExclusive,
            $this->autoDelete
        );
        $this->_identifier = array_shift($response);
        $this->setIsInitialized(true);
    }

    /**
     * Binds a given exchange to the queue
     * @param Exchange $exchange the exchange to bind
     * @param null $routingKey the routing key to use
     */
    public function bind(Exchange $exchange, $routingKey = null)
    {
        $this->init();
        $this->getClient()->getChannel()->queue_bind($this->getIdentifier(), $exchange->name, $routingKey);
    }

    /**
     * Registers a callback that can consume messages on the queue.
     *
     * @param AbstractConsumer|callable $callback the consumer or callback to invoke when messages are received
     * @param string $tag Specifies the identifier for the consumer. The consumer tag is
     * local to a channel, so two clients can use the same consumer tags.
     * If this field is empty the server will generate a unique tag.
     * @param bool $excludeLocal when true, don't receive messages published by this consumer.
     * @param bool $noAck when true, the consumer will not acknowledge messages
     * @param bool $isExclusive when true, request exclusive consumer access, meaning only this consumer can access the queue.
     * @param bool $noWait when true, don't wait for a server response. In case of error the server will raise a channel exception
     *
     * @throws \InvalidArgumentException if the callback is not callable
     */
    public function consume($callback, $tag = '', $excludeLocal = false, $noAck = false, $isExclusive = null, $noWait = false)
    {
        if ($callback instanceof AbstractConsumer)
            $callback = array($callback, 'consume');
        elseif (!is_callable($callback))
            throw new \InvalidArgumentException('First argument to '.__METHOD__.' must be callable!');
        $this->init();
        $this->getClient()->getChannel()->basic_consume(
            $this->getIdentifier(),
            $tag,
            $excludeLocal,
            $noAck,
            ($isExclusive === null) ? $this->isExclusive : $isExclusive,
            $noWait,
            $callback
        );
    }
}
