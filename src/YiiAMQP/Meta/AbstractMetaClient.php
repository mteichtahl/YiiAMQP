<?php

namespace YiiAMQP\Meta;

/**
 * # Meta Data Client
 *
 * Responsible for returning meta data from the message queue, such as lists of exchanges, queues etc
 *
 * @package YiiAMQP\Meta
 */
abstract class AbstractMetaClient extends \CComponent
{
    /**
     * @var \YiiAMQP\Client the client this meta data client belongs to
     */
    protected $_client;

    /**
    * Sets the AMQP client for the meta data client
    * @param \YiiAMQP\Client $client
    */
    public function setClient($client)
    {
        $this->_client = $client;
    }

    /**
     * Gets the AMQP client for the meta data client
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
     * Fetches the available exchanges from the message queue
     * @return \YiiAMQP\Exchange[] the available exchanges
     */
    abstract public function fetchExchanges();

    /**
     * Fetches the available queues from the message queue
     * @return \YiiAMQP\Exchange[] the available queues
     */
    abstract public function fetchQueues();

}
