<?php
/**
 * Expressive - cluster implementation
 * @author Ioan CHIRIAC
 * @license MIT
 */
namespace Expressive\Http {

  /**
   * Listen HTTP requests
   */
  class Server extends \Expressive\Socket\Server {

    public $onRequest;

    /**
     * At each request create a new handler
     */
    protected function onConnect($socket) {
      return new Request(
        $socket, $this
      );
    }

    /**
     * What to do when the specified request is ready to be executed
     */
    public function onReady(Request $request) {
      if ($this->onRequest) {
        call_user_func_array(
          $this->onRequest,
          array(
            $request,
            new Response($request)
          )
        );
      }
    }
  }
}