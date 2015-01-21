<?php
/**
 * Expressive - cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive\Proxy {

  use Expressive\Cluster;

  /**
   * Forward messages likes events with bindings :
   * - onStreamClose
   * - onStreamData
   */
  class Client extends \Expressive\Socket\Client {
    protected $onClose = false;
    protected $onData = false;
    protected $buffer = null;
    public function onStreamClose(callable $fn) {
      $this->onClose = $fn;
      if (!feof($this->socket)) {
        call_user_func_array($this->onClose, array($this));
      }
    }
    public function onStreamData(callable $fn) {
      $this->onData = $fn;
      if (!empty($this->buffer)) {
        $this->onData($this->buffer);
        $this->buffer = null;
      }
    }
    protected function onData($message) {
      if($this->onData) {
        call_user_func_array($this->onData, array($message, $this));
      } else {
        $this->buffer .= $message;
      }
    }
    protected function onClose() {
      if($this->onClose) {
        call_user_func_array($this->onClose, array($this));
      }
      parent::onClose();
    }
  }
}