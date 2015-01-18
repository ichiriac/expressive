<?php
/**
 * Expressive - reactphp cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive\Http {

  use React\Socket\ServerInterface as SocketServerInterface;
  use React\Socket\ConnectionInterface;
  use React\Http\Request;

  /**
   * This class is used only on windows mode, for performance reasons
   * avoid to close the socket and make it persistent
   */
  class Server extends \React\Http\Server {
    private $io;
    private $parser;

    public function __construct(SocketServerInterface $io)
    {
        $this->io = $io;
        $this->io->on('connection', function (ConnectionInterface $conn) {
          $parser = new RequestHeaderParser();
          $parser->on('headers', function (Request $request, $bodyBuffer) use ($conn, $parser) {
            $request->remoteAddress = '0.0.0.0'; // proxy / not reliable
            $response = $this->handleRequest($conn, $request, $bodyBuffer);
            $response->on('close', function() use($conn, $parser) {
              $parser->flush();
              $conn->removeAllListeners();
              $conn->on('data', array($parser, 'feed'));
            });
            $this->emit('request', array($request, $response));
            $request->emit('data', array($bodyBuffer));
          });
          $conn->on('data', array($parser, 'feed'));
        });
    }
    /**
     * Creates the response handler
     */
    public function handleRequest(ConnectionInterface $conn, Request $request, $bodyBuffer)
    {
        $response = new Response($conn);
        $response->on('close', array($request, 'close'));
        return $response;
    }

    /**
     * Forward to connection socket (sorry liskov for the duck typing)
     */
    public function listen($port, $host = '127.0.0.1') {
      $this->io->listen();
    }
  }
}