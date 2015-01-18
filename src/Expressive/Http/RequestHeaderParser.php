<?php
/**
 * Expressive - reactphp cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive\Http {

  use React\Http\Request;
  use Guzzle\Parser\Message\PeclHttpMessageParser;
  use Guzzle\Parser\Message\MessageParser;

  /**
   * This class is used only on windows mode, for performance reasons
   * avoid to close the socket and make it persistent
   */
  class RequestHeaderParser extends \React\Http\RequestHeaderParser {

    private $buffer = '';
    private $maxSize = 4096;
    private $request;
    private static $parser;

    /**
     * Receiving data
     */
    public function feed($data, $conn) {
      if ($this->request) {
        $this->request->emit('data', array($data));
      } else {
        if (strlen($this->buffer) + strlen($data) > $this->maxSize) {
          $this->emit('error', array(new \OverflowException("Maximum header size of {$this->maxSize} exceeded."), $this));
          return;
        }
        $this->buffer .= $data;
        if (false !== strpos($this->buffer, "\r\n\r\n")) {
          list($this->request, $bodyBuffer) = $this->parseRequest($this->buffer);
          $this->emit('headers', array($this->request, $bodyBuffer, $conn));
        }
      }
    }

    /**
     * Flushing the current buffer
     */
    public function flush() {
      $this->buffer = '';
      if ($this->request) {
        $this->request->emit('end');
        $this->request->removeAllListeners();
        $this->request = null;
      }
    }

    /**
     * Parsing the request
     */
    public function parseRequest($data)
    {
      list($headers, $bodyBuffer) = explode("\r\n\r\n", $data, 2);
      if (!self::$parser) {
        if ( function_exists('http_parse_message') ) {
          self::$parser = new PeclHttpMessageParser();
        } else {
          self::$parser = new MessageParser();
        }
      }
      $parsed = self::$parser->parseRequest($headers."\r\n\r\n");
      $parsedQuery = array();
      if ($parsed['request_url']['query']) {
        parse_str($parsed['request_url']['query'], $parsedQuery);
      }
      $request = new Request(
          $parsed['method'],
          $parsed['request_url']['path'],
          $parsedQuery,
          $parsed['version'],
          $parsed['headers']
      );
      return array($request, $bodyBuffer);
    }
  }
}