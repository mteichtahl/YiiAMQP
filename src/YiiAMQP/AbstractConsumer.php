<?php

namespace YiiAMQP;

/**
 * Class Consumer
 *
 * @author Charles Pick <charles@codemix.com>
 * @package YiiAMQP
 */
abstract class AbstractConsumer extends \CComponent
{
    /**
     * Consumes a message
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message the message to consume
     *
     * @return bool true if the message could be consumed
     */
    public function consume($message)
    {
        $channel = $message->delivery_info['channel']; /* @var \PhpAmqpLib\Channel\AMQPChannel $channel */
        $tag = $message->delivery_info['delivery_tag'];
        $decoded = $this->decode($message);
        if ($decoded === false || !$this->predicate($decoded)) {
            $channel->basic_nack($tag);
            return false;
        }
        else {
            $channel->basic_ack($tag);
            $this->process($decoded);
            return true;
        }
    }

    /**
     * Determines whether or not the consumer can consume the given message.
     *
     * @param array $message the decoded message
     *
     * @return bool true if the message is accepted
     */
    abstract public function predicate($message);

    /**
     * Processes a message
     * @param array $message the decoded message to process
     */
    abstract public function process($message);

    /**
     * Decodes a message
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message the message to decode
     *
     * @return bool|array the decoded message, or false if the message content type
     * is unsupported or the message is malformed.
     */
    public function decode($message)
    {
        $contentType = $message->get('content_type');
        if (!stristr($contentType, 'json'))
            return false;
        $decoded = json_decode($message->body, true);
        return $decoded === null ? false : $decoded;
    }
}
