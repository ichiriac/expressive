<?php
/**
 * Expressive - cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive {

  /**
   * Worker manager - this class communicate between the master and current worker
   */
  class Worker extends Process {

    /**
     * The cluster instance (called from current child)
     */
    private $cluster;

    // child process port
    private $port;

    // child process connection (used only by windows)
    public $socket;

    /**
     * Child process connection (used only by windows)
     */
    public $pipe;


    /**
     * Launch a new worker
     */
    public function __construct(Cluster $cluster) {
      if (!$cluster->isMaster()) {
        throw new \Exception('A worker can not fork another worker');
      }
      $this->cluster = $cluster;

      // construct the command line
      $cmd = PHP_BINARY . ' ' . implode(' ', $_SERVER['argv']) . ' --slave ' . $this->cluster->id;
      if (IS_WIN) {
        $this->port = $cluster->port + count($cluster->workers) + 1;
        $cmd .= ' ' . $this->port;
      }

      // launch a worker process
      parent::__construct(
        $cmd, IS_WIN ? array('pipe', 'r') : $cluster->socket
      );
    }

    /**
     * Pipe the worker process with the specified socket (USED ON WINDOWS MODE ONLY)
     * @return boolean Returns true if the incomming socket was accepted
     */
    public function pipe(Proxy\Client $socket) {

      if ($this->pipe || !IS_WIN) return false;

      if (!$this->socket) {

        // creating a persistent connection to the worker
        try {
          $this->socket = new Proxy\Client('tcp://127.0.0.1:'.$this->port);
        } catch(\Exception $ex) {
          $this->emitError($ex);
          $this->pipe->end( Cluster::$busy );
          return;
        }

        // handling the worker response
        $tokenSize = strlen(SOCK_TOKEN_CLOSE);
        $this->socket->onStreamData(function($data) use($tokenSize) {
          if ($this->pipe) {
            if (substr($data, -$tokenSize) === SOCK_TOKEN_CLOSE) {
              $this->pipe->end(substr($data, 0, strlen($data) - $tokenSize));
            } else {
              $this->pipe->write($data);
            }
          }
        });

        // handle the server end (restarts at the next request)
        $this->socket->onStreamClose(function() {
          if ($this->pipe) $this->pipe->end();
          $this->socket = null;
        });
      }

      // Start to pipe the incoming request
      $this->pipe = $socket;

      // Handling client request
      $this->pipe->onStreamData(function($data) {
        if ($this->socket) {
          $this->socket->write($data);
        } else if($this->pipe) {
          $this->emitError("Worker socket is closed, unable to forward the request !\n");
          $this->pipe->end();
        } else {
          $this->emitError("Worker socket is closed, unable to forward the request !\n");
        }
      });

      // The request is finished, free the worker pipe
      $this->pipe->onStreamClose(function() {
        $this->pipe = null;
      });
      return true;
    }

  }
}