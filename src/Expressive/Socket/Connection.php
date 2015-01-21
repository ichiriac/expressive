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
  abstract class Connection {
    public $socket;
    public $hash;
    public $loop;
    public function __construct($socket, Loop $loop = null) {
      $this->socket = $socket;
      if ($loop) $loop->add($this);
      $this->loop = $loop;
    }
    public abstract function event();
  }
}