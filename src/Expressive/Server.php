<?php
/**
 * Expressive - reactphp cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive {

  use React\EventLoop\LoopInterface;

  /**
   * Cluster socket server
   */
  class Server extends \React\Socket\Server {
    /**
     * The cluster instance
     */
    private $cluster;
    /**
     * Initialize a new already binded stream as a server
     */
    public function __construct($cluster) {
      if (!$cluster->isWorker()) {
        throw new \Exception(
          'The server MUST be started only from worker mode (use Cluster->isWorker())'
        );
      }
      $this->cluster = $cluster;
      parent::__construct($this->cluster->loop);
      if (IS_WIN) {
        $this->master = @stream_socket_server('tcp://127.0.0.1:'.$cluster->port, $errno, $errstr);
        if (false === $this->master) {
          throw new \Exception(
            'Could not bind on ' . $cluster->port . ' ('.$errno.':'.$errstr.')'
          );
        }
      } else {
        $this->master = STDIN;
      }
    }
    /**
     * Listen without binding a new socket
     */
    public function listen($port = null, $host = '127.0.0.1') {
      stream_set_blocking($this->master, 0);
      $this->cluster->loop->addReadStream($this->master, function ($master) {
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