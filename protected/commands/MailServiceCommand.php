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
 
 
 
 
 */
Yii::import('application.vendors.*');
require_once('Swift-4.3.0/lib/swift_required.php');

class MailServiceCommand extends CConsoleCommand {

    CONST NO_REQUEUE = false;
    protected static $instance = null;
    
    private $mailTransport;
    private $mailer;
    public $id;
    private $messageCount = 0;
    

    public static function getInstance() {
        if (!isset(static::$instance)) {
            static::$instance = new MailServiceCommand;
        }
        return static::$instance;
    }

    function __construct() {
        date_default_timezone_set('Australia/Melbourne');

        $this->id = $this->generateRandomString('5');
        
        Yii::log('[' . get_class() . '] [#' . $this->id . '] Initialising Mail Service....', 'info');
        Yii::app()->autoloader->getAutoloader()->addClass('swift', __DIR__ . '/../vendors/Swift-4.3.0/lib');
    }

    public function actionStart() {

        Yii::log('[' . get_class() . '] [#' . $this->id . '] Starting Mail Service....', 'info');


        Yii::app()->rabbitMQ->createConnection();
        Yii::app()->rabbitMQ->declareQueue('qu.test');
        Yii::app()->rabbitMQ->setQoS('0', '1', '0');
        Yii::app()->rabbitMQ->declareExchange('ex.test');
        Yii::app()->rabbitMQ->bind('qu.test', 'ex.test');
        Yii::app()->rabbitMQ->registerCallback(array($this, 'myCallback'));
        Yii::app()->rabbitMQ->wait();
    }

    private function generateRandomString($length = 10) {
        $randomstring ='';
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

    public static function myCallback($msg) {

        $mailService = MailServiceCommand::getInstance();


        $deliveryTag = $msg->delivery_info['delivery_tag'];
        $channel = $msg->delivery_info['channel'];
        $contentType = $msg->get('content_type');

        $mailTransport = Swift_SmtpTransport::newInstance('smtp.googlemail.com', 465, 'ssl')
                ->setUsername("marc@teichtahl.com")
                ->setPassword("Nmiv9qyqn!");

        $mailer = Swift_Mailer::newInstance($mailTransport);

        //echo $this->{$this->id};
        //make sure we only accept JSON
        if ($contentType != 'text/JSON') {
            Yii::log('[' . get_class() . '] [#' . $this->id . '] Unknown message recieved', 'info');
        } else {
            $val = json_decode($msg->body);

            switch ($val->requestType) {
                case 'mail':
                    $message = Swift_Message::newInstance();
                    $message->setSubject($val->request->subject);
                    $message->setTo($val->request->to);
                    $message->setFrom(array($val->request->from->email => $val->request->from->name));
                    $message->setBody($val->request->body, 'text/html'); //body html

                    $headers = $message->getHeaders();
                    $headers->toString();

                    Yii::log('[' . get_class() . '] [#' . $mailService->id . '] Mail queued -  ' . trim($headers->get('Message-ID')), 'info');

                    if ($mailer->send($message) == 1) {
                        Yii::log('[' . get_class() . '] [#' . $mailService->id . '] Message sent - ' . trim($headers->get('Message-ID')), 'info');
                        $msg->delivery_info['channel']->basic_ack($deliveryTag);
                        $mailService->messageCount++;
                    } else {
                        Yii::log('[' . get_class() . '] [#' . $mailService->id . ' Send error', 'error');
                        $msg->delivery_info['channel']->basic_nack($deliveryTag, self::NO_REQUEUE);
                    }
                     
                    break;
            }
        }
    }

}

?>
