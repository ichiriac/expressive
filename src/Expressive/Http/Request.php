<?php
/**
 * Expressive - cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive\Http {
  define('HTTP_HEADER_EOF', "\r\n\r\n");
  class Request extends \Expressive\Socket\Client {
    protected $server;
    protected $buffer;
    protected $body = false;
    protected $parsed = false;

    public $method;
    public $httpVersion;
    public $headers;
    public $url;
    public $path;
    public $params = array();
    public $cookies = array();

    public function __construct($socket, Server $server) {
      $this->server = $server;
      parent::__construct($socket, $server->loop);
    }

    protected function ready() {
      $this->parsed = true;
      $this->buffer = null;
      $this->body = false;
      $this->server->onReady($this);
    }

    /**
     * Handle the close event
     */
    protected function onClose() {
      if (IS_WIN) {
        if (!feof($this->socket)) {
          // ignore close until is really closed
          $this->socket->write(SOCK_TOKEN_CLOSE);
          $this->parsed = false;
          $this->params = array();
          $this->cookies = array();
          return;
        }
      }
      return parent::onClose();
    }

    protected function onData($message) {
      if ($this->parsed !== false) {
        // does not expect to talk !
        echo 'Bad Protocol Dude !';
        $this->close();
      }
      $this->buffer .= $message;
      if ($this->body !== false) {
        if (strlen($this->buffer) ===  $this->body) {
          parse_str($this->buffer, $this->params);
          $this->ready();
        }
      } else if (false !== strpos($this->buffer, HTTP_HEADER_EOF)) {
        $this->buffer = explode(HTTP_HEADER_EOF, $this->buffer, 2);
        $parts = http_parse_message($this->buffer[0]);
        $this->method = $parts->requestMethod;
        foreach($parts->headers as $k => $v) {
          $this->headers[strtolower($k)] = $v;
        }
        $this->url = $parts->requestUrl;
        $this->httpVersion = number_format($parts->httpVersion, 1);
        $parts = explode('?', $this->url, 2);
        $this->path = $parts[0];
        if (!empty($parts[1])) {
          parse_str($parts[1], $this->params);
        }
        if (!empty($this->headers['cookie'])) {
          $parts = http_parse_cookie($this->headers['cookie']);
          $this->cookies = $parts['cookies'];
        }
        if (!$this->method !== 'GET' && !empty($this->headers['content-length'])) {
          $this->body = intval($this->headers['content-length']);
          if ($this->body === 0) {
            $this->ready();
          } else {
            $this->buffer = empty($this->buffer[1]) ? '' : $this->buffer[1];
          }
        } else {
          $this->ready();
        }
      }
    }
  }

}