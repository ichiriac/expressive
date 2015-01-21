<?php
/**
 * Expressive - cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive {

  class Process {
    /**
     * The process handle
     * @see http://php.net/manual/fr/function.proc-open.php
     */
    private $process;

    /**
     * List of worker pipes
     * @see http://php.net/manual/fr/function.proc-open.php
     */
    private $pipes;

    /**
     * The process output stream
     */
    private $stdout;

    /**
     * The error output stream
     */
    private $stderr;

    /**
     * The periodic timer
     */
    private $timer;

    /**
     * Handles the exit callback
     */
    private $onExit;

    /**
     * Used for raising errors manually
     */
    private $onError;

    /**
     * Actual worker status
     * @see http://php.net/manual/en/function.proc-get-status.php
     */
    public $status;

    /**
     * The current process id
     */
    public $pid;

    public function __construct($cmd, $stdin) {

      // construct the command line
      if (IS_WIN) {
        $path = dirname($_SERVER['SCRIPT_FILENAME']);
        $io = array(
           0 => $stdin,
           1 => array('file', $path . '/debug.txt', 'w'),
           2 => array('file', $path . '/error.txt', 'a+'),
        );
      } else {
        $io = array(
           0 => $stdin,
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
        $this->stdout = new Proxy\Client($this->pipes[1], Cluster::$mainLoop);
        $this->stderr = new Proxy\Client($this->pipes[2], Cluster::$mainLoop);
      }

      // getting the worker status and check it's status
      $this->status = proc_get_status($this->process);
      $this->pid = $this->status['pid'];
      $this->timer = Cluster::$mainLoop->setInterval(function() {
        $this->status = proc_get_status($this->process);
        if (empty($this->status['running'])) {
          $this->close();
        }
      }, 100);
    }

    /**
     * Listen the debug event
     */
    public function onDebug(callable $fn) {
      if ($this->stdout) $this->stdout->onStreamData($fn);
    }

    /**
     * Listen the error event
     */
    public function onError(callable $fn) {
      if ($this->stderr) $this->stderr->onStreamData($fn);
      $this->onError = $fn;
    }

    /**
     * Raise an error
     */
    public function emitError($message) {
      if ($this->onError) {
        call_user_func_array(
          $this->onError, array($message)
        );
      }
    }

    /**
     * Listen the exit event
     */
    public function onExit(callable $fn) {
      $this->onExit = $fn;
    }

    /**
     * Force to close the current worker
     */
    public function close() {

      if ($this->timer) {
        Cluster::$mainLoop->clearInterval($this->timer);
        $this->timer = null;
      }

      // closing items
      if ($this->process) {
        if ($this->stdout) $this->stdout->close();
        if ($this->stderr) $this->stderr->close();
        proc_close($this->process);

        // emit an exit event
        if ($this->onExit) {
          call_user_func_array(
            $this->onExit,
            array(
              $this->status['exitcode'],
              empty($this->status['signaled']) ?
                $this->status['stopsig'] || $this->status['termsig'] : null
            )
          );
        }
      }
      return $this;
    }

  }
}