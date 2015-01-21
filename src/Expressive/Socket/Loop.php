<?php
/**
 * Expressive - cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive\Socket {

  /**
   * Implements an async loop over streams
   */
  class Loop {
    /**
     * Main loop scan interval (in ms)
     */
    public $interval = 10000;
    /**
     * Is the loop actually running
     */
    public $run = false;
    /**
     * List of streams to scan
     */
    private $sockets = array();
    private $connections = array();
    protected $timers = array();
    protected $timer;

    public function add(Connection $conn) {
      $id = (int)$conn->socket;
      $this->sockets[$id] = $conn->socket;
      $this->connections[$id] = $conn;
    }
    public function setInterval(callable $fn, $interval = 10) {
      if (!isset($this->timers[$interval])) {
        $this->timers[$interval] = array();
      }
      $id = spl_object_hash($fn);
      $this->timers[$interval][$id] = $fn;
      return $id . '@' . $interval;
    }
    public function clearInterval($id) {
      $id = explode('@', $id, 2);
      if (isset($this->timers[$id[0]][$id[1]])) {
        unset($this->timers[$id[0]][$id[1]]);
      }
    }
    public function remove(Connection $conn) {
      $id = (int)$conn->socket;
      unset($this->connections[$id]);
      unset($this->sockets[$id]);
      if (empty($this->sockets)) {
        $this->run = false;
      }
    }
    public function start() {
      $this->run = true;
      $this->timer = 0;
      $null = NULL;
      if (empty($this->sockets)) {
        return false;
      }
      $read = $this->sockets;
      while($this->run) {
        $ok = stream_select($read, $null, $null, 0, $this->interval);
        if ($ok === false) break;
        if ($ok > 0) {
          foreach($read as $socket) {
            $id = (int)$socket;
            $conn = $this->connections[$id];
            if ($conn->event() === false) $this->remove($conn);
          }
        }
        $this->timer ++;
        foreach($this->timers as $interval => $callbacks) {
          if ($this->timer % $interval === 0) {
            foreach($callbacks as $fn) {
              call_user_func_array($fn, array());
            }
          }
        }
        if ($this->timer > 60000) $this->timer = 0;
        $read = $this->sockets;
      }
    }
    public function stop() {
      $this->run = false;
    }
  }
}