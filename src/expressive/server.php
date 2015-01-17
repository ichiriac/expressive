<?php
namespace expressive {
  use Evenement\EventEmitter;
  use React\Stream\Stream;

  /**
   * Http server
   */
  class server extends EventEmitter {

    private $fn;

    /**
     * Initialize a new http server
     */
    public function __construct(callable $fn) {
      $this->fn = $fn;
    }
    /**
     * Starts to listen on the specified port
     */
    public function listen($port = 8080, $host = '127.0.0.1') {
      if (cluster::$master) {
        $this->socket = stream_socket_server('tcp://' . $host . ':' . $port);
      } else {
        // ignore parameters, socket is already binded from padre
        if (!IS_WIN) {
          $this->socket = cluster::$socket;
        } else {
          $this->socket = stream_socket_server('tcp://127.0.0.1:' .
            $_SERVER['argv'][array_search('--slave', $_SERVER['argv']) + 1]
          );
        }
      }
      if ($this->socket) {
        stream_set_blocking($this->socket, 0);
        cluster::$loop->addReadStream($this->socket, function ($server) {
            echo '>> Receive a request !' . "\n";
            $client = stream_socket_accept($server);
            stream_set_blocking($client, 0);
            $client = new Stream($client, cluster::$loop);
            $client->on('data', function($data) use($client) {
              if (IS_WIN) {
                $client->write("HTTP/1.1 200 OK\r\nContent-Length: 3\r\n\r\nHi\n".SOCK_TOKEN_CLOSE);
              } else {
                $client->end("HTTP/1.1 200 OK\r\nContent-Length: 3\r\n\r\nHi\n");
              }
            });
        });
      }
      return $this;
    }
  }
}