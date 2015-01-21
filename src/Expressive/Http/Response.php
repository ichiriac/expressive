<?php
/**
 * Expressive - cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive\Http {

  define('HTTP_CHUNKED_ENCODING', "0\r\n\r\n");

  /**
   * This class handles responses
   */
  class Response {

    protected $headers;
    protected $status;
    protected $cookies = array();
    protected $headers_sent = false;
    protected $request;
    protected $chunkedEncoding = true;

    public function __construct(Request $request)
    {
      $this->request = $request;
      $this->headers = array('X-Powered-By'=> 'Expressive');
    }
    /**
     * Intercept chunkedEncoding
     */
    public function writeHead($status = null, array $headers = array())
    {
      if ($this->headers_sent) {
        throw new \Exception(
          'Headers already sent !'
        );
      }
      if (is_array($status)) {
        $headers = $status;
        $status = null;
      }
      if (!empty($headers)) {
        $this->headers = array_merge(
          $this->headers, $headers
        );
      }
      $this->chunkedEncoding = empty($this->headers['Content-Length']);
      if ($this->chunkedEncoding) {
        $this->headers['Transfer-Encoding'] = 'chunked';
      }
      if (!empty($status)) $this->status = $status;
    }

    public function write($data) {
      if (!$this->headers_sent) {
        $this->headers_sent = true;
        $status = (int) $this->status;
        $text = isset(ResponseCodes::$statusTexts[$status]) ? ResponseCodes::$statusTexts[$status] : '';
        $header = "HTTP/1.1 $status $text\r\n";
        foreach($this->headers as $name => $value) {
          $name = strtr($name, "\r\n", '');
          $value = strtr($value, "\r\n", '');
          $header .= "$name: $value\r\n";
        }
        $data = $header . "\r\n" . $data;
      }
      $this->request->socket->write($data);
      return $this;
    }
    /**
     * Sends the ending message
     */
    public function end($data = null) {
      if (null !== $data) {
        $this->write($data);
      }
      if ($this->chunkedEncoding) {
        $this->request->socket->write(HTTP_CHUNKED_ENCODING);
      }
      $this->close();
    }
    /**
     * Requests to close the connection
     */
    public function close()
    {
      $this->request->close();
    }
  }
}