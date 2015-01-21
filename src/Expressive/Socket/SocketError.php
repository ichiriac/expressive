<?php
/**
 * Expressive - cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive\Socket {

  class SocketError extends \Exception {
    public function __construct($target, $no, $err) {
      parent::__construct(
        'Network error on ' . $target . ' (' . $no . ' - ' . $err . ')'
      );
    }
  }
}