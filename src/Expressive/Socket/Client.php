<?php
/**
 * Expressive - cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive\Socket {

  abstract class Client extends Connection {

    public function __construct($target, Loop $loop = null) {
      if (is_resource($target)) {
        $socket = $target;
      } else {
        $socket = stream_socket_client($target, $no, $err);
      }
      if (!$socket) {
        throw new SocketError($target, $no, $err);
      }
      parent::__construct($socket, $loop);
    }
    public function event() {
      if (feof($this->socket)) {
        $this->onClose();
        return false;
      } else {
        $message = fread($this->socket, 8192);
        $this->onData($message);
      }
    }
    public function write($data) {
      fwrite($this->socket, $data);
    }
    public function close() {
      $this->onClose();
    }
    public function end($data = null) {
      if ($data) $this->write($data);
      $this->close();
    }
    abstract protected function onData($message);
    protected function onClose() {
      $this->loop->remove($this);
      if (!feof($this->socket)) fclose($this->socket);
    }
  }

}