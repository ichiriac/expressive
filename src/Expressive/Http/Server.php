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

    public function __construct(SocketServerInterface $io)
    {
        $this->io = $io;
        $this->io->on('connection', function (ConnectionInterface $conn) {
            // TODO: http 1.1 keep-alive
            // TODO: chunked transfer encoding (also for outgoing data)
            // TODO: multipart parsing
            $parser = new RequestHeaderParser();
            $parser->on('headers', function (Request $request, $bodyBuffer) use ($conn, $parser) {
                // attach remote ip to the request as metadata
                $request->remoteAddress = $conn->getRemoteAddress();
                $this->handleRequest($conn, $request, $bodyBuffer);
                $conn->removeListener('data', array($parser, 'feed'));
                $conn->on('end', function () use ($request) {
                    $request->emit('end');
                });
                $conn->on('data', function ($data) use ($request) {
                    $request->emit('data', array($data));
                });
                $request->on('pause', function () use ($conn) {
                    $conn->emit('pause');
                });
                $request->on('resume', function () use ($conn) {
                    $conn->emit('resume');
                });
            });
            $conn->on('data', array($parser, 'feed'));
        });
    }
    public function handleRequest(ConnectionInterface $conn, Request $request, $bodyBuffer)
    {
        $response = new Response($conn);
        $this->emit('request', array($request, $response));
        $request->emit('data', array($bodyBuffer));
    }

    /**
     * Forward to connection socket (sorry liskov for the duck typing)
     */
    public function listen($port, $host = '127.0.0.1') {
      $this->io->listen();
    }
  }
}