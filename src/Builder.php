<?php
declare(strict_types=1);

namespace SimpleAmqp;

use Bunny\Channel;
use Bunny\Message;
use Kernel\Utils\Instance;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use Workerman\RabbitMQ\Client;
use SimpleAmqp\Message as BaseMessage;

abstract class Builder extends Instance {
    protected $_exchange_name;
    protected $_exchange_type;
    protected $_queue_name;
    protected $_routing_key;
    protected $_consumer_tag;
    protected $_message;

    protected $_logger;

    public function __invoke(LoggerInterface $logger)
    {
        $this->_logger = $logger;
    }

    protected function _initConfig()
    {
        $className = get_called_class();
        $this->_message = $this->_getAbstractMessage();
        $this->_message->setQueue($this->_queue_name ?? $className);
        $this->_message->setExchange($this->_exchange_name ?? $className);
        $this->_message->setExchangeType($this->_exchange_type ?? Constants::DIRECT);
        $this->_message->setRoutingKey($this->_routing_key ?? $className);
        $this->_message->setConsumerTag($this->_consumer_tag ?? $className);
        $this->_message->setCallback([$this,'callback']);
    }

    protected function _getAbstractMessage() : AbstractMessage
    {
        if(!$this->_message instanceof AbstractMessage){
            $this->_message = make($this->_message ?? BaseMessage::class);
        }
        return $this->_message;
    }

    final public function consumer() : Consumer
    {
        $res = Co()->get(Consumer::class);
        if($this->_logger){
            $res->setLogger($this->_logger);
        }
        return $res;
    }

    final public function consume() : void
    {
        $this->consumer()->consume($this->_getAbstractMessage());
    }

    final public function producer() : Producer
    {
        $res = Co()->get(Producer::class);
        if($this->_logger){
            $res->setLogger($this->_logger);
        }
        return $res;
    }

    final public function asyncProducer() : AsyncProducer
    {
        $res = Co()->get(AsyncProducer::class);
        if($this->_logger){
            $res->setLogger($this->_logger);
        }
        return $res;
    }

    final public function produce(string $data, bool $close = true) : bool
    {
        $this->_getAbstractMessage()->setBody($data);
        return $this->producer()->produce($this->_getAbstractMessage(), $close);
    }

    /**
     * @param string $data
     * @param bool $close
     * @return bool|PromiseInterface
     */
    final public function asyncProduce(string $data, bool $close = true) : PromiseInterface
    {
        $this->_getAbstractMessage()->setBody($data);
        return $this->asyncProducer()->produce($this->_getAbstractMessage(), $close);
    }

    abstract public function encode($data);
    abstract public function decode($data);
    abstract public function callback(Message $message, Channel $channel, Client $client) : string;
}