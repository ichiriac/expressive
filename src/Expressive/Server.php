<?php
/**
 * Expressive - reactphp cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive {

  use React\EventLoop\LoopInterface;

  /**
   * Custom socket server
   */
  class Server extends \React\Socket\Server {
    /**
     * Initialize a new already binded stream as a server
     */
    public function __construct($stream, LoopInterface $loop) {
      parent::__construct($loop);
      $this->master = $stream;
    }
    /**
     * Listen without binding a new socket
     */
    public function listen() {
      stream_set_blocking($this->master, 0);
      $this->loop->addReadStream($this->master, function ($master) {
        $newSocket = stream_socket_accept($master);
        if (false === $newSocket) {
            $this->emit('error', array(new \RuntimeException('Error accepting new connection')));
            return;
        }
        $this->handleConnection($newSocket);
      });
      return $this;
    }
  }
}