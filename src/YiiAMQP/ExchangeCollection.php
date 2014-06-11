<?php

namespace YiiAMQP;

/**
 * # Exchange Collection
 *
 * Represents a collection of exchanges
 *
 * @package YiiAMQP
 */
class ExchangeCollection extends \CAttributeCollection
{
    /**
     * @var Client the AMQP connection this belongs to
     */
    protected $_client;

    /**
     * Sets the AMQP client for the exchange collection
     * @param \YiiAMQP\Client $client
     */
    public function setClient($client)
    {
        $this->_client = $client;
    }

    /**
     * Gets the AMQP client for the exchange collection
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
     * @inheritDoc
     */
    public function add($key, $value)
    {
        if (!($value instanceof Exchange))
            $value = $this->createExchange($key, $value);
        else {
            $value->name = $key;
            $value->setClient($this->getClient());
        }
        parent::add($key, $value);
    }

    /**
     * Gets the exchange with the given name, or creates it if it doesn't exist.
     * @param string $key the name of the exchange
     *
     * @return Exchange the exchange instance
     */
    public function itemAt($key)
    {
        $item = parent::itemAt($key);
        if ($item === null) {
            $item = $this->createExchange($key);
            $this->add($key, $item);
        }
        return $item;
    }

    /**
     * Override the parent implementation since the collection creates items on demand.
     * @param string $key the exchange name to check
     *
     * @return bool always true
     */
    public function contains($key)
    {
        return true;
    }

    /**
     * Creates an exchange for the collection
     *
     * @param string $name the name of the exchange to create
     * @param array $config the exchange configuration
     *
     * @return Exchange the exchange instance
     */
    protected function createExchange($name, $config = array())
    {
        $exchange = new Exchange();
        foreach($config as $key => $value)
            $exchange->{$key} = $value;
        $exchange->name = strtolower($name);
        $exchange->setClient($this->getClient());
        return $exchange;
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
        foreach($metaClient->fetchExchanges() as $name => $exchange)
            $this->add($name, $exchange);
        return $this;
    }
}
