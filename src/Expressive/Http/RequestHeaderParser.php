<?php
/**
 * Expressive - reactphp cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive\Http {

  /**
   * This class is used only on windows mode, for performance reasons
   * avoid to close the socket and make it persistent
   */
  class RequestHeaderParser extends \React\Http\RequestHeaderParser {

    private $buffer = '';
    private $maxSize = 4096;

    public function feed($data)
    {
        if (strlen($this->buffer) + strlen($data) > $this->maxSize) {
            $this->emit('error', array(new \OverflowException("Maximum header size of {$this->maxSize} exceeded."), $this));
            return;
        }
        $this->buffer .= $data;
        if (false !== strpos($this->buffer, "\r\n\r\n")) {
            list($request, $bodyBuffer) = $this->parseRequest($this->buffer);
            $this->buffer = '';
            $this->emit('headers', array($request, $bodyBuffer));
        }
    }
  }
}