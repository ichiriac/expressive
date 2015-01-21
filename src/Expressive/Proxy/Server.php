<?php
/**
 * Expressive - cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive\Proxy {

  use Expressive\Cluster;

  class Server extends \Expressive\Socket\Server {
    /**
     * The port used for listening
     */
    public $port;
    /**
     * The binded ip address
     */
    public $host;
    /**
     * List of current workers
     */
    public $workers;

    /**
     * Initialize a new cluster instance
     */
    public function __construct($port, $host = '127.0.0.1') {
      $this->port = $port;
      $this->host = $host;
      $this->workers = array();
      parent::__construct(
        'tcp://' . $this->host . ':' . $this->port,
        IS_WIN ? Cluster::$mainLoop : null
      );
    }

    /**
     * Redirect incomming connection (only from windows mode)
     */
    protected function onConnect($socket) {
      $client = new Client($socket, Cluster::$mainLoop);
      $worker = false;
      // find any available client
      foreach($this->workers as $w) {
        if (!$w->pipe) {
          $worker = $w;
          break;
        }
      }
      // forward the message
      if ($worker && $worker->pipe($client)) return;
      // no available worker, server is unavailable
      $client->end(Cluster::$busy);
    }
  }

}