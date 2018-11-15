<?php

/**
 * YiiAMQP Component
 *
 * This is a Yii component (CApplicationComponent)
 *
 *  @author Marc Teichtahl <marc@teichtahl.com>
 * @copyright Copyright &copy; Marc Teichtahl 2013-
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version 1.0.0
 *
 * @package YiiAMQP
 */

namespace YiiAMQP;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * YiiAMQP
 *
 * The class is a Yii CApplicationCompontent to be used as a component in Yii applications.
 *
 *
 * @package    YiiAMQP
 * @subpackage Common
 * @author     Marc Teichtahl <marc@teichtahl.com>
 */
class AppComponent extends \CApplicationComponent {

    /**
     * Configuration / credentials.
     *
     * @var array
     */
    public $server;

    /**
     * @var AMQPStreamConnection
     */
    public $connection;

    /**
     * @var AMQPChannel
     */
    public $channel;

    public $queue;

    /**
     * Exchange name.
     *
     * @var string
     */
    public $exchange;

    public $managementQueue;

    public $managementExchange;

    public $managementCallback;

    public $exchangeName;

    public $exchangeType;

    public $queueName;

    private $callback;

    private $client;

    /**
     * Creates a connection to a rabbitMQ server
     *
     * @param string $host IP Address or hostname of the rabbitMQ server. If NULL configuration setting will be used
     * @param string $port Post number of the rabbitMQ server. If NULL configuration setting will be used
     * @param string $user Username of the authorised rabbitMQ user. If NULL configuration setting will be used
     * @param string $password Password of the authorised rabbitMQ user. If NULL configuration setting will be used
     * @param string $vhost virtual rabbitMQ server. If NULL configuration setting will be used
     *
     * @return An AMQP channel or FALSE on failure
     */
    public function createConnection($host = NULL, $port = NULL, $user = NULL, $password = NULL, $vhost = NULL) {

        \Yii::log('[' . get_class() . '] Creating connection', 'info');


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

        $this->connection = new AMQPStreamConnection(
            $this->server['host'],
            $this->server['port'],
            $this->server['user'],
            $this->server['password'],
            $this->server['vhost']
        );

        if (!$this->connection) {
            \Yii::log('[' . get_class() . '] Cannot create connection', 'error');
            return false;
        }

        $this->channel = $this->connection->channel();

        if (!$this->channel) {
            \Yii::log('[' . get_class() . '] Cannot create channel', 'error');
            return false;
        }

        \Yii::log('[' . get_class() . '] Channel ' . $this->channel->getChannelId() . ' created', 'info');

        $this->managementExchange = $this->declareExchange('exchange.management', 'fanout');

        \Yii::log('[' . get_class() . '] Connected to ' . $this->server['host'] . ':' . $this->server['port'], 'info');
        return $this->channel;
    }

    /**
     * Declares an AMQP queue
     *
     * @param string $name Name of the queue to be declared
     * @param bool $passive If set, the server will reply with Declare-Ok if the queue already exists with the same name, and raise an error if not
     * @param bool $durable If set when creating a new queue, the queue will be marked as durable. Durable queues remain active when a server restarts. Non-durable queues (transient queues) are purged if/when a server restarts. Note that durable queues do not necessarily hold persistent messages, although it does not make sense to send persistent messages to a transient queue.
     * @param bool $exclusive Exclusive queues may only be accessed by the current connection, and are deleted when that connection closes. Passive declaration of an exclusive queue by other connections are not allowed.
     * @param bool $auto_delete If set, the queue is deleted when all consumers have finished using it. The last consumer can be cancelled either explicitly or because its channel is closed. If there was no consumer ever on the queue, it won't be deleted. Applications can explicitly delete auto-delete queues using the Delete method as normal.
     *
     * @return An AMQP channel or FALSE on failure
     */
    public function declareQueue($name = NULL, $passive = false, $durable = true, $exclusive = false, $auto_delete = true) {

        if (!$name)
            $name = $this->generateRandomString('10');

        $ret = $this->channel->queue_declare($name, $passive, $durable, $exclusive, $auto_delete);

        if (!is_array($ret)) {
            \Yii::log('[' . get_class() . '] Cannot create queue', 'error');
            return false;
        }

        \Yii::log('[' . get_class() . '] Created queue ' . $ret[0], 'info');
        $this->bind($name, 'exchange.management');
        $this->queue = $ret[0];

        return $name;
    }

    /**
     * Send a plain text message (content_type = text/plain)
     *
     * @param string $msg Content of the message to be sent
     * @param string $routingKey Routing key to be used
     *
     */
    public function sendTextMessage($msg, $routingKey = '') {
        $message = new AMQPMessage($msg, array('content_type' => 'text/plain', 'delivery_mode' => 2));
        $this->channel->basic_publish($message, $this->exchange, $routingKey);
    }

    /**
     * Send a JSON  message (content_type = text/JSON)
     *
     * @param string $msg Content of the message to be sent
     * @param string $routingKey Routing key to be used
     *
     */
    public function sendJSONMessage($msg, $routingKey = '') {
        $message = new AMQPMessage($msg, array('content_type' => 'text/JSON', 'delivery_mode' => 2));
        $this->channel->basic_publish($message, $this->exchange, $routingKey);
    }

    /**
     * Declares an AMQP exchange
     *
     * @param string $name Name of the exchange to be declared
     * @param string $type Each exchange belongs to one of a set of exchange types implemented by the server. The exchange types define the functionality of the exchange - i.e. how messages are routed through it. It is not valid or meaningful to attempt to change the type of an existing exchange.
     * @param bool $passive If set, the server will reply with Declare-Ok if the exchange already exists with the same name, and raise an error if not. The client can use this to check whether an exchange exists without modifying the server state. When set, all other method fields except name and no-wait are ignored. A declare with both passive and no-wait has no effect. Arguments are compared for semantic equivalence.
     * @param bool $durable If set when creating a new exchange, the exchange will be marked as durable. Durable exchanges remain active when a server restarts. Non-durable exchanges (transient exchanges) are purged if/when a server restarts.
     * @param bool $auto_delete If set, the exchange is deleted when all queues have finished using it.
     *
     * @return true on success, otherwise false
     */
    public function declareExchange($name, $type = 'direct', $passive = false, $durable = true, $auto_delete = true) {

        $ret = $this->channel->exchange_declare($name, $type, $passive, $durable, $auto_delete);
        \Yii::log('[' . get_class() . '] Created exchange ' . $name . ' [ ' . $type . ' ]', 'info');
        $this->exchange = $name;
        return true;
    }

    /**
     * Set QoS parameters for the current channel
     *
     * @param string|int $prefetch_size The client can request that messages be sent in advance so that when the client finishes processing a message, the following message is already held locally, rather than needing to be sent down the channel. Prefetching gives a performance improvement. This field specifies the prefetch window size in octets. The server will send a message in advance if it is equal to or smaller in size than the available prefetch size (and also falls into other prefetch limits). May be set to zero, meaning "no specific limit", although other prefetch limits may still apply. The prefetch-size is ignored if the no-ack option is set.
     * @param string|int $prefetch_count Specifies a prefetch window in terms of whole messages. This field may be used in combination with the prefetch-size field; a message will only be sent in advance if both prefetch windows (and those at the channel and connection level) allow it. The prefetch-count is ignored if the no-ack option is set.
     * @param bool $a_global By default the QoS settings apply to the current channel only. If this field is set, they are applied to the entire connection.
     *
     * @return true on success, otherwise false
     */
    public function setQos($prefetch_size, $prefetch_count, $a_global) {
        $this->channel->basic_qos($prefetch_size, $prefetch_count, $a_global);
    }

    /**
     * Bind a queue to an exchange
     *
     * @param string $queue Specifies the name of the destination exchange to bind.
     * @param string $exchange Specifies the name of the source exchange to bind.
     * @param string $routingKey Specifies the routing key for the binding. The routing key is used for routing messages depending on the exchange configuration. Not all exchanges use a routing key - refer to the specific exchange documentation.
     */
    public function bind($queue, $exchange, $routingKey = NULL) {

        $this->channel->queue_bind($queue, $exchange, $routingKey);
        \Yii::log('[' . get_class() . '] Created binding [' . $queue . ' <--> ' . $exchange . ']', 'info');
    }

    /**
     * This method asks the server to start a "consumer", which is a transient request for messages from a specific queue. Consumers last as long as the channel they were declared on, or until the client cancels them.
     *
     * @param string $queue Specifies the name of the queue to consume from.
     * @param string $consumerTag Specifies the identifier for the consumer. The consumer tag is local to a channel, so two clients can use the same consumer tags. If this field is empty the server will generate a unique tag.
     * @param bool $noLocal Don't receive messages published by this consumer.
     * @param bool $noAck Tells the server if the consumer will acknowledge the messages.
     * @param bool $exclusive Request exclusive consumer access, meaning only this consumer can access the queue.
     * @param bool $nowait don't wait for a server response. In case of error the server will raise a channel exception
     */
    public function consume($queue = NULL, $consumerTag = '', $noLocal = false, $noAck = false, $exclusive = false, $nowait = false) {

        if (!$queue)
            $queue = $this->queue;


        /*
          queue: Queue from where to get the messages
          consumer_tag: Consumer identifier
          no_local: Don't receive messages published by this consumer.
          no_ack: Tells the server if the consumer will acknowledge the messages.
          exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
          nowait: don't wait for a server response. In case of error the server will raise a channel
          exception
          callback: A PHP Callback
         */
        $this->channel->basic_consume($queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $this->callback);
    }

    /**
     * Wait for some expected AMQP methods and dispatch to them.
     *
     * */
    public function wait() {
        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    /**
     * Get the Id for the channel being used
     *
     * @return integer
     *
     * */
    public function getChannelId() {
        return $this->channel->getChannelId();
    }

    /**
     * Register a call back function that is called when a message is received
     *
     * @param function
     *
     * */
    public function registerCallback($callback) {
        if (is_callable($callback)) {
            \Yii::log('[' . get_class() . '] Registering worker callback', 'info');
            $this->callback = $callback;
        }
    }

    /**
     * Utility function for returning a random string of specified length
     *
     * @param int $length Length of the random string
     * @return string
     */
    private function generateRandomString($length = 10) {
        $randomstring = '';
        if ($length > 32) {
            $multiplier = round($length / 32, 0, PHP_ROUND_HALF_DOWN);
            $remainder = $length % 32;
            for ($i = 0; $i < $multiplier; $i++) {
                $randomstring .= substr(str_shuffle(md5(rand())), 0, 32);
            }
            $randomstring .= substr(str_shuffle(md5(rand())), 0, $remainder);
        }
        else
            $randomstring = substr(str_shuffle(md5(rand())), 0, $length);
        return $randomstring;
    }

}
