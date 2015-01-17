<?php

namespace expressive {


  use Evenement\EventEmitter;
  use React\EventLoop\Timer\Timer;
  use React\Stream\Stream;

  class worker extends EventEmitter {
    private $process;
    private $pipes;
    private $timer;
    private $status;
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
     * child process connection (used only by windows)
     */
    public $pipe;

    /**
     * The current process id
     */
    public $pid;

    /**
     * Launch a new worker
     */
    public function __construct($cluster) {
      if (!$cluster->isMaster()) {
        throw new \Exception('A worker can not fork another worker');
      }
      $this->cluster = $cluster;
      $cmd = PHP_BINARY . ' ' . implode(' ', $_SERVER['argv']) . ' --slave';
      if (IS_WIN) {
        $this->port = $cluster->port + count($cluster->workers) + 1;
        $path = dirname($_SERVER['SCRIPT_FILENAME']);
        $io = array(
           0 => array('pipe', 'r'),
           1 => array('file', $path . '/debug'.count($cluster->workers).'.txt', 'w'),
           2 => array('file', $path . '/error'.count($cluster->workers).'.txt', 'w'),
        );
        $cmd .= ' ' . $this->port;
      } else {
        $io = array(
           0 => cluster::$socket,
           1 => array('pipe', 'w'),
           2 => array('pipe', 'w'),
        );
      }
      echo $cmd . "\n";
      $this->process = proc_open($cmd, $io, $this->pipes, null, null, array('bypass_shell'=>true));
      if (!$this->process) {
        throw new \Exception('Unable to fork the process');
      }

      $this->status = proc_get_status($this->process);
      $this->pid = $this->status['pid'];

      if (!IS_WIN) {
        // does not work on windows : http://php.net/manual/fr/function.stream-set-blocking.php#110997
        $this->stdout = new Stream($this->pipes[1], cluster::$loop);
        stream_set_blocking($this->pipes[1], 0);
        $this->stderr->on('data', function($data) {
          $this->emit('debug', array($data));
        });
        $this->stderr = new Stream($this->pipes[2], cluster::$loop);
        stream_set_blocking($this->pipes[1], 0);
        $this->stderr->on('data', function($data) {
          $this->emit('error', array($data));
        });
      }

      $this->timer = cluster::$loop->addPeriodicTimer(0.1, function (Timer $timer) {
        $this->status = proc_get_status($this->process);
        if (empty($this->status['running'])) {
          $this->close();
        }
      });
    }

    /**
     * Pipe the worker process with the specified socket
     */
    public function pipe($socket) {
      if ($this->pipe) return false;
      if (!$this->socket) {
        echo '---OPEN CNX' . "\n";
        $this->socket = stream_socket_client('tcp://127.0.0.1:'.$this->port);
        if (!$this->socket) {
          // children is dead
          $this->close();
          return false;
        }
        stream_set_blocking($this->socket, 0);
        $this->socket = new Stream($this->socket, cluster::$loop);
        // pipe data
        $this->socket->on('data', function($data) {
          if (substr($data, -strlen(SOCK_TOKEN_CLOSE)) === SOCK_TOKEN_CLOSE) {
            $this->pipe->end(substr($data, 0, strlen($data) - strlen(SOCK_TOKEN_CLOSE)));
          } else {
            $this->pipe->write($data);
          }
        });
        // handle errors
        $this->socket->on('error', function() {
          echo 'Got an error !';
          if ($this->pipe) $this->pipe->end();
          $this->socket->end();
        });
        // handle the server end (restarts at the next request)
        $this->socket->on('close', function() {
          echo 'Was closed !';
          if ($this->pipe) $this->pipe->end();
          $this->socket = null;
        });
      }
      $this->pipe = $socket;
      $this->pipe->on('data', function($data) {
        if ($this->socket) {
          $this->socket->write($data);
        } else if($this->pipe) {
          $this->pipe->end();
        }
      });
      $this->pipe->on('close', function() {
        // echo '>> CNX CLOSED' . "\n";
        $this->pipe = null;
      });
      return $this->pipe;
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