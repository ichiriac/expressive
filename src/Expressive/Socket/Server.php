<?php
/**
 * Expressive - cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive\Socket {

  /**
   * Creates an async socket server (used by workers)
   * That's improve connectivity and allows only one
   * single connection at time by process
   */
  abstract class Server extends Connection {
    public function __construct($target, Loop $loop = null) {
      if (is_resource($target)) {
        $socket = $target;
      } else {
        $socket = stream_socket_server($target, $no, $err);
      }
      if (!$socket) {
        throw new SocketError($target, $no, $err);
      }
      parent::__construct($socket, $loop);
    }
    public function event() {
      $conn = stream_socket_accept($this->socket, 5);
      if ($conn) {
        $this->onConnect($conn);
      }
    }
    public function close() {
      $this->loop->remove($this);
      fclose($this->socket);
    }
    abstract protected function onConnect($socket);
  }
}