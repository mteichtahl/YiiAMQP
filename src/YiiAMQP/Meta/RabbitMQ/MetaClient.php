<?php

namespace YiiAMQP\Meta\RabbitMQ;

use Guzzle\Plugin\CurlAuth\CurlAuthPlugin;
use YiiAMQP\Client;
use YiiAMQP\Exchange;
use YiiAMQP\Queue;

class MetaClient extends \YiiAMQP\Meta\AbstractMetaClient
{
    /**
     * @var Client the AMQP connection this meta client belongs to
     */
    protected $_client;

    /**
     * @var \Guzzle\Http\Client the guzzle client
     */
    protected $_guzzle;


    /**
     * Sets the AMQP client for the meta client
     * @param \YiiAMQP\Client $client
     */
    public function setClient($client)
    {
        $this->_client = $client;
    }

    /**
     * Gets the AMQP client for the meta client
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
     * @param \Guzzle\Http\Client $guzzle
     */
    public function setGuzzle($guzzle)
    {
        $this->_guzzle = $guzzle;
    }

    /**
     * @return \Guzzle\Http\Client
     */
    public function getGuzzle()
    {
        if ($this->_guzzle === null)
            $this->_guzzle = $this->createGuzzle();
        return $this->_guzzle;
    }

    /**
     * Creates a guzzle service client
     * @return \Guzzle\Http\Client the guzzle client
     */
    protected function createGuzzle()
    {
        $guzzle = new \Guzzle\Http\Client();
        $connectionConfig = $this->getClient()->getConnectionConfig();

        $guzzle->setBaseUrl('http://'.$connectionConfig['host'].':1'.$connectionConfig['port'].'/');

        $authPlugin = new CurlAuthPlugin($connectionConfig['user'], $connectionConfig['password']);
        $guzzle->addSubscriber($authPlugin);

        return $guzzle;
    }


    /**
     * Fetches the available exchanges from the message queue
     * @return Exchange the available exchanges
     */
    public function fetchExchanges()
    {
        $guzzle = $this->getGuzzle();
        $command = $guzzle->get('/api/exchanges');
        $data = json_decode($command->send()->getBody(true), true);
        $exchanges = array();
        foreach($data as $config) {
            $exchange = new Exchange();
            $exchange->name = $config['name'];
            $exchange->vhost = $config['vhost'];
            $exchange->type = $config['type'];
            $exchange->isDurable = $config['durable'];
            $exchange->autoDelete = $config['auto_delete'];
            $exchanges[$config['name']] = $exchange;
        }
        return $exchanges;
    }

    /**
     * Fetches the available queues from the message queue
     * @return Exchange the available queues
     */
    public function fetchQueues()
    {
        $guzzle = $this->getGuzzle();
        $command = $guzzle->get('/api/queues');
        $data = json_decode($command->send()->getBody(true), true);
        $queues = array();
        foreach($data as $config) {
            $queue = new Queue();
            $queue->name = $config['name'];
            $queue->vhost = $config['vhost'];
            $queue->status = $config['status'];
            $queue->isDurable = $config['durable'];
            $queue->autoDelete = $config['auto_delete'];
            $queues[$config['name']] = $queue;
        }
        return $queues;
    }


    /**
     * Fetches the overview for Rabbit MQ
     * @return array the overview for RabbitMQ
     */
    public function fetchOverview()
    {
        $guzzle = $this->getGuzzle();
        $command = $guzzle->get('/api/queues');
        return json_decode($command->send()->getBody(true), true);
    }
}
