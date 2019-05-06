<?php

namespace YiiAMQP\Meta\RabbitMQ;

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
     * @var \GuzzleHttp\Client the guzzle client
     */
    protected $_guzzle;

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
     * @param \GuzzleHttp\Client $guzzle
     */
    public function setGuzzle($guzzle)
    {
        $this->_guzzle = $guzzle;
    }

    /**
     * @return \GuzzleHttp\Client
     * @throws \CException
     */
    public function getGuzzle()
    {
        if ($this->_guzzle === null)
            $this->_guzzle = $this->createGuzzle();
        return $this->_guzzle;
    }

    /**
     * Creates a guzzle service client
     *
     * @return \GuzzleHttp\Client the guzzle client
     * @throws \CException
     */
    protected function createGuzzle()
    {
        $connectionConfig = $this->getClient()->getConnectionConfig();
        $baseUri = 'http://'.$connectionConfig['host'].':1'.$connectionConfig['port'].'/';

        $guzzle = new \GuzzleHttp\Client(array(
            'base_uri' => $baseUri,
            'auth' => array($connectionConfig['user'], $connectionConfig['password']),
        ));

        return $guzzle;
    }

    /**
     * Fetches the available exchanges from the message queue
     *
     * @return Exchange[] Exchange the available exchanges
     * @throws \CException
     */
    public function fetchExchanges()
    {
        $guzzle = $this->getGuzzle();
        $response = $guzzle->get('/api/exchanges');
        $data = json_decode($response->getBody()->getContents(), true);
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
     *
     * @return Queue[] Exchange the available queues
     * @throws \CException
     */
    public function fetchQueues()
    {
        $guzzle = $this->getGuzzle();
        $response = $guzzle->get('/api/queues');
        $data = json_decode($response->getBody()->getContents(), true);
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
     *
     * @return array The overview for RabbitMQ
     * @throws \CException
     */
    public function fetchOverview()
    {
        $guzzle = $this->getGuzzle();
        $response = $guzzle->get('/api/queues');
        return json_decode($response->getBody()->getContents(), true);
    }
}
