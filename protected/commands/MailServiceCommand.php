<?php

/**
  {
  "request": {
  "bcc": [
  "marc@amplifieragency.com"
  ],
  "body": "this is the body",
  "cc": [
  "mzt@cisco.com"
  ],
  "from": {
  "email": "marc@teichtahl.com",
  "name": "Marc"
  },
  "replyTo": "marc@teichtahl.com",
  "subject": "test email",
  "to": "marc@teichtahl.com"
  },
  "requestType": "mail"
  }

  {
  "requestType": "info"
  }


 */
Yii::import('application.vendors.*');
require_once('Swift-4.3.0/lib/swift_required.php');

class MailServiceCommand extends CConsoleCommand {

    CONST NO_REQUEUE = false;

    protected static $instance = null;
    private $mailTransport;
    private $mailer;
    private $messageCount = 0;
    
    public $id;
    

    public static function getInstance() {
        if (!isset(static::$instance)) {
            static::$instance = new MailServiceCommand;
        }
        return static::$instance;
    }

    function __construct() {
        date_default_timezone_set(Yii::app()->params['timezone']);

        $this->id = getmypid();

        Yii::log('[' . get_class() . '] [#' . $this->id . '] Initialising Mail Service....', 'info');
        Yii::app()->autoloader->getAutoloader()->addClass('swift', __DIR__ . '/../vendors/Swift-4.3.0/lib');
    }

    public function actionStart() {
        Yii::log('[' . get_class() . '] [#' . $this->id . '] Starting Mail Service....', 'info');

        Yii::app()->rabbitMQ->createConnection();
       
        $queue = Yii::app()->rabbitMQ->declareQueue();

        Yii::app()->rabbitMQ->declareExchange('exchange.mailService', 'topic');
        Yii::app()->rabbitMQ->bind($queue, 'exchange.mailService', 'mail');
        Yii::app()->rabbitMQ->setQoS('0', '1', '0');
        
        Yii::app()->rabbitMQ->registerCallback(array($this, 'myCallback'));
        Yii::app()->rabbitMQ->consume($queue, $this->id);
        Yii::app()->rabbitMQ->wait();
    }

    public static function myCallback($msg) {

        $mailService = MailServiceCommand::getInstance();

        //make sure we only accept JSON
        if ($msg->get('content_type') != 'text/JSON') {
            Yii::log('[' . get_class() . '] [#' . $mailService->id . '] Unknown message recieved', 'info');
            $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'], self::NO_REQUEUE);
        } else {
            $val = json_decode($msg->body);


            if (!$val) {
                $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'], self::NO_REQUEUE);
                Yii::log('[' . get_class() . '] [#' . $mailService->id . '] Malformed JSON Message', 'info');
                return;
            }

            // process the recieved messages
            switch ($val->requestType) {
                case 'mail':
                    $mailService->processEmailRequest($val, $msg);
                    break;

                case 'info':
                    $mailService->processInfoRequest($val, $msg);
                    break;

                default :
                    break;
            }
        }
    }

    protected function processEmailRequest($val, $msg) {
        $mailService = MailServiceCommand::getInstance();

        $deliveryTag = $msg->delivery_info['delivery_tag'];
        $channel = $msg->delivery_info['channel'];

        $mailTransport = Swift_SmtpTransport::newInstance(Yii::app()->params['smtpServer'], 
                                                          Yii::app()->params['smtpPort'], 
                                                          Yii::app()->params['smtpConn'])
                ->setUsername(Yii::app()->params['smtpUsername'])
                ->setPassword(Yii::app()->params['smtpPassword']);

        $mailer = Swift_Mailer::newInstance($mailTransport);

        $message = Swift_Message::newInstance();
        $message->setSubject($val->request->subject);
        $message->setTo($val->request->to);
        $message->setFrom(array($val->request->from->email => $val->request->from->name));
        $message->setBody($val->request->body, 'text/html'); //body html

        $headers = $message->getHeaders();
        $headers->toString();

        Yii::log('[' . get_class() . '] [#' . $mailService->id . '] [' . $msg->get('exchange') . '] Mail queued -  ' . trim($headers->get('Message-ID')), 'info');

        $startTime = microtime(true);
        if ($mailer->send($message) == 1) {
            $msg->delivery_info['channel']->basic_ack($deliveryTag);
            $totalTime = microtime(true) - $startTime;
            Yii::log('[' . get_class() . '] [#' . $mailService->id . '] [' . $msg->get('exchange') . '] Message sent - ' . trim($headers->get('Message-ID')) . ' [ in ' . round($totalTime, 2) . 's ]', 'info');
            $mailService->messageCount++;
            return;
        } else {
            $msg->delivery_info['channel']->basic_nack($deliveryTag, self::NO_REQUEUE);
            Yii::log('[' . get_class() . '] [#' . $mailService->id . ' Send error', 'error');
            return;
        }
    }

    protected function processInfoRequest($val, $msg) {
        
        $msg->delivery_info['channel']->basic_ack($deliveryTag);
        
        $mailService = MailServiceCommand::getInstance();

        $deliveryTag = $msg->delivery_info['delivery_tag'];
        $channel = $msg->delivery_info['channel'];

        // var_dump($msg);

        Yii::log('[' . get_class() . '] [#' . $mailService->id . '] Info Request');
        Yii::log('     - Consumer IP : ' . Yii::app()->rabbitMQ->server['host'] . ':' . Yii::app()->rabbitMQ->server['port']);
        Yii::log('     - Queue: ' . Yii::app()->rabbitMQ->queue);
        Yii::log('     - Exchange: ' . $msg->get('exchange'));
        Yii::log('     - Channel: ' . Yii::app()->rabbitMQ->getChannelId());
        Yii::log('     - Messages Received: ' . $mailService->messageCount);

        
    }

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

?>
