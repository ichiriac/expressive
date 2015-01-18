<?php
/**
 * Expressive - reactphp cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive {


  use Evenement\EventEmitter;
  use React\EventLoop\Timer\Timer;
  use React\Stream\Stream;

  /**
   * Worker manager - this class communicate between the master and current worker
   */
  class Worker extends EventEmitter {
    /**
     * List of worker pipes
     * @see http://php.net/manual/fr/function.proc-open.php
     */
    private $process;

    /**
     * List of worker pipes
     * @see http://php.net/manual/fr/function.proc-open.php
     */
    private $pipes;

    /**
     * Actual worker status
     * @see http://php.net/manual/en/function.proc-get-status.php
     */
    public $status;

    /**
     * Timer that update periodically the worker status
     */
    private $timer;

    /**
     * The cluster instance (called from current child)
     */
    private $cluster;

    // child process port
    private $port;

    // child process connection (used only by windows)
    private $socket;

    /**
     * The process output stream
     */
    private $stdout;

    /**
     * The error output stream
     */
    private $stderr;

    /**
     * Child process connection (used only by windows)
     */
    public $pipe;

    /**
     * The current process id
     */
    public $pid;

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
        $path = dirname($_SERVER['SCRIPT_FILENAME']);
        $io = array(
           0 => array('pipe', 'r'),
           1 => array('file', $path . '/debug.txt', 'a+'),
           2 => array('file', $path . '/error.txt', 'a+'),
        );
        $cmd .= ' ' . $this->port;
      } else {
        $io = array(
           0 => cluster::$socket,
           1 => array('pipe', 'w'),
           2 => array('pipe', 'w'),
        );
      }

      // launch a worker process
      $this->process = proc_open($cmd, $io, $this->pipes, null, null, array('bypass_shell'=>true));
      if (!$this->process) {
        throw new \Exception('Unable to fork the process');
      }

      // use stream over worker pipes to intercept output and errors
      // does not work on windows : http://php.net/manual/fr/function.stream-set-blocking.php#110997
      if (!IS_WIN) {
        $this->stdout = new Stream($this->pipes[1], $this->cluster->loop);
        stream_set_blocking($this->pipes[1], 0);
        $this->stdout->on('data', function($data) {
          $this->emit('debug', array($data));
        });
        $this->stderr = new Stream($this->pipes[2], $this->cluster->loop);
        stream_set_blocking($this->pipes[2], 0);
        $this->stderr->on('data', function($data) {
          $this->emit('error', array($data));
        });
      }

      // getting the worker status and check it's status
      $this->status = proc_get_status($this->process);
      $this->pid = $this->status['pid'];
      $this->timer = $this->cluster->loop->addPeriodicTimer(0.1, function (Timer $timer) {
        $this->status = proc_get_status($this->process);
        if (empty($this->status['running'])) {
          $this->close();
        }
      });
    }

    /**
     * Pipe the worker process with the specified socket
     * @return boolean Returns true if the incomming socket was accepted
     */
    public function pipe($socket) {

      if ($this->pipe || !IS_WIN) return false;

      if (!$this->socket) {

        // creating a persistent connection to the worker
        $this->socket = stream_socket_client('tcp://127.0.0.1:'.$this->port, $errno, $errstr);
        if (!$this->socket) {
          $this->emit('error', array(
            'Unable to connect with worker on port '.$this->port . ' ('.$errno. ':'. $errstr.')' . "\n"
          ));
          $this->close();
          return false;
        }

        // using a stream helper
        stream_set_blocking($this->socket, 0);
        $this->socket = new Stream($this->socket, $this->cluster->loop);

        // handling the worker response
        $this->socket->on('data', function($data) {
          if (substr($data, -strlen(SOCK_TOKEN_CLOSE)) === SOCK_TOKEN_CLOSE) {
            $this->pipe->end(substr($data, 0, strlen($data) - strlen(SOCK_TOKEN_CLOSE)));
          } else {
            $this->pipe->write($data);
          }
        });

        // handle errors
        $this->socket->on('error', function($message) {
          $this->emit('error', array('Worker socket error : ' . $message . "\n"));
          $this->socket->end();
        });

        // handle the server end (restarts at the next request)
        $this->socket->on('close', function() {
          if ($this->pipe) $this->pipe->end();
          $this->socket = null;
        });
      }

      // Start to pipe the incoming request
      $this->pipe = $socket;

      // Handling client request
      $this->pipe->on('data', function($data) {
        if ($this->socket) {
          $this->socket->write($data);
        } else if($this->pipe) {
          $this->emit('error', array('Worker socket is closed, unable to forward the request !' . "\n"));
          $this->pipe->end();
        }
      });

      // The request is finished, free the worker pipe
      $this->pipe->on('close', function() {
        $this->pipe = null;
      });
      return true;
    }

    /**
     * Force to close the current worker
     */
    public function close() {

      if ($this->timer) {
        $this->timer->cancel();
        $this->timer = null;
      }

      // closing items
      if ($this->process) {
        if ($this->stdout) $this->stdout->close();
        if ($this->stderr) $this->stderr->close();
        proc_close($this->process);

        // emit an exit event
        $this->emit('exit', array(
          $this->status['exitcode'],
          empty($this->status['signaled']) ?
            $this->status['stopsig'] || $this->status['termsig'] : null
        ));
      }
      return $this;
    }
  }
}