<?php

namespace YiiAMQP;

class RabbitMQService extends \Guzzle\Service\Client
{

    /**
     * Factory method to create a new Guzzle
     *
     * The following array keys and values are available options:
     * - base_url: Base URL of web service
     * - scheme:   URI scheme: http or https
     * - username: API username
     * - password: API password
     *
     * @param array|Collection $config Configuration data
     *
     * @return self
     */
    public static function factory($config = array()) {

        $default = array(
            'base_url' => '{scheme}://{username}.test.com/',
            'scheme' => 'https'
        );
        $required = array('username', 'password', 'base_url');
        $config = \Guzzle\Common\Collection::fromConfig($config, $default, $required);


        $client = NULL;

        try {
            $client = new self($config->get('base_url'));
        } catch (CException $e) {
            echo 'error: ' . print_r($e);
        }

        // Attach a service description to the client
        $description = \Guzzle\Service\Description\ServiceDescription::factory(__DIR__ . '/rabbitMQ.json');
        $client->setDescription($description);

        $authPlugin = new \Guzzle\Plugin\CurlAuth\CurlAuthPlugin('guest', 'guest');

        $client->addSubscriber($authPlugin);


        return $client;
    }

}
