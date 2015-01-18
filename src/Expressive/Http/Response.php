<?php
/**
 * Expressive - reactphp cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive\Http {

  use React\Socket\ConnectionInterface;

  /**
   * This class is used only on windows mode, for performance reasons
   * avoid to close the socket and make it persistent
   */
  class Response extends \React\Http\Response {

    private $conn;
    private $chunkedEncoding = true;

    /**
     * Overwrite constructor
     */
    public function __construct(ConnectionInterface $conn)
    {
      parent::__construct($conn);
      $this->conn = $conn;
      $this->on('end', array($this, 'close'));
    }
    /**
     * Intercept chunkedEncoding
     */
    public function writeHead($status = 200, array $headers = array())
    {
      if (isset($headers['Content-Length'])) {
          $this->chunkedEncoding = false;
      }
      return parent::writeHead($status, $headers);
    }
    /**
     * Sends the ending message
     */
    public function end($data = null) {
      if (null !== $data) {
        $this->write($data);
      }
      if ($this->chunkedEncoding) {
        $this->conn->write("0\r\n\r\n");
      }
      $this->emit('end');
    }
    /**
     * Requests to close the connection
     */
    public function close()
    {
      $this->conn->write(SOCK_TOKEN_CLOSE);
      $this->emit('close');
      $this->removeAllListeners();
      $this->close = true;
      $this->writable = false;
    }
  }
}