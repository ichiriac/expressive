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
        $this->parser = new RequestHeaderParser();
        $this->parser->on('headers', function(Request $request, $bodyBuffer, $conn) {
          $request->remoteAddress = $conn->getRemoteAddress();
          $response = $this->handleRequest($conn, $request, $bodyBuffer);
          $response->on('close', function() use($conn) {
            $this->parser->flush();
            $conn->removeAllListeners();
            $conn->on('data', array($this->parser, 'feed'));
          });
          $this->emit('request', array($request, $response));
          $request->emit('data', array($bodyBuffer));
        });
        $this->io->on('connection', function (ConnectionInterface $conn) {
          $conn->on('data', array($this->parser, 'feed'));
        });
    }
    /**
     * Creates the response handler
     */
    public function handleRequest(ConnectionInterface $conn, Request $request, $bodyBuffer)
    {
        if (IS_WIN) {
          $response = new Response($conn);
        } else {
          $response = new  \React\Http\Response($conn);
        }
        $response->on('close', array($request, 'close'));
        return $response;
    }
  }
}