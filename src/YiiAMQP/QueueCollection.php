<?php

namespace YiiAMQP;

/**
 * # Queue Collection
 *
 * Represents a collection of queues
 *
 * @package YiiAMQP
 */
class QueueCollection extends \CAttributeCollection
{
    /**
     * @var Client the AMQP connection this belongs to
     */
    protected $_client;

    /**
     * Sets the AMQP client for the queue collection
     * @param \YiiAMQP\Client $client
     */
    public function setClient($client)
    {
        $this->_client = $client;
    }

    /**
     * Gets the AMQP client for the queue collection
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
     * Gets the queue with the given name, or creates it if it doesn't exist.
     * @param string $key the name of the queue
     *
     * @return Queue the queue instance
     */
    public function itemAt($key)
    {
        $item = parent::itemAt($key);
        if ($item === null) {
            $item = $this->createQueue($key);
            $this->add($key, $item);
        }
        return $item;
    }

    /**
     * @inheritDoc
     */
    public function add($key, $value)
    {
        if (!($value instanceof Queue))
            $value = $this->createQueue($key, $value);
        else {
            $value->name = $key;
            $value->setClient($this->getClient());
        }
        parent::add($key, $value);
    }


    /**
     * Override the parent implementation since the collection creates items on demand.
     * @param string $key the queue name to check
     *
     * @return bool always true
     */
    public function contains($key)
    {
        return true;
    }

    /**
     * Creates a queue for the collection
     *
     * @param string $name the name of the queue to create
     * @param array $config the queue configuration
     *
     * @return Queue the queue instance
     */
    public function createQueue($name, $config = array())
    {
        $queue = new Queue();
        foreach($config as $key => $value)
            $queue->{$key} = $value;
        $queue->name = $name;
        $queue->setClient($this->getClient());
        return $queue;
    }

    /**
     * Populates the collection
     * @return $this the populated collection
     */
    public function populate()
    {
        $metaClient = $this->getClient()->getMetaClient();
        if (!is_object($metaClient))
            return $this;
        foreach($metaClient->fetchQueues() as $name => $queue)
            $this->add($name, $queue);
        return $this;
    }
}
