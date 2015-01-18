<?php/** * Expressive - reactphp cluster implementation * @author Ioan CHIRIAC * @license MIT */namespace Expressive {  // check if we are running into Windows Mode (fallback)  defined('IS_WIN') or define('IS_WIN', substr(PHP_OS, 0, 1) === 'W');  if (IS_WIN) {    define('SOCK_TOKEN_CLOSE', "\0eos\r\n");  }  use React\EventLoop\LoopInterface;  /**   * Cluster manager   */  class Cluster extends \React\Http\Server {    /**     * The full busy message when no worker is available (used only in windows mode)     */    public static $busy = "HTTP/1.1 503 Service Unavailable\r\nContent-Length: 32\r\n\r\n<h1>503 Service Unavailable</h1>";    /**     * Checks if currently running from a master or a worker process     */    public static $master = true;     /**     * handling cluster instance counter     */    private static $cur = 0;    /**     * Current cluster instance if executed from a worker     */    public static $cid = null;    /**     * The main loop     */    public $loop;    /**     * The port used for listening     */    public $port;    /**     * The binded ip address     */    public $host;    /**     * List of current workers     */    public $workers;    /**     * Current cluster id (MUST be the same in master or worker modes)     */    public $id;    /**     * Currently binded socket     */    private $socket;    /**     * Initialize a new cluster instance     */    public function __construct(LoopInterface $loop, $port, $host = '127.0.0.1') {      $this->id = ++self::$cur;      $this->port = $port;      $this->host = $host;      $this->loop = $loop;      $this->workers = array();      if ($this->isWorker()) {        $this->socket = new Server(STDIN, $this->loop);        parent::__construct($this->socket);      }    }    /**     * Check if you are running into a master process     */    public function isMaster() {      return self::$master;    }    /**     * Check if you are currently running from a worker     */    public function isWorker() {      return !self::$master && $this->id === self::$cid;    }    /**     * Starts to fork a new child process     */    public function fork() {      if (!$this->isMaster()) {        throw new \Exception(          'You can only fork process from the master mode'        );      }      // handle the master socket      if (!$this->socket) {        $this->socket = stream_socket_server('tcp://' . $this->host . ':' . $this->port,  $errno, $errstr);        if ($this->socket === false) {          throw new \Exception(            'Could not listen on ' . $this->host . ':' . $this->port .' (' . $errno . ':' . $errstr . ')'          );        }        stream_set_blocking($this->socket, 0);        // handling requests from father only on windows mode        if (IS_WIN) {          cluster::$loop->addReadStream($this->socket, function($server) {            $conn = stream_socket_accept($server);            if ($conn) {              stream_set_blocking($conn, 0);              $conn = new Stream($conn, cluster::$loop);              $worker = false;              // find any available client              foreach($this->workers as $w) {                if (!$w->pipe) {                  $worker = $w;                  break;                }              }              // forward the message              if ($worker && $worker->pipe($conn)) return;              // no available worker, server is unavailable              $conn->end(Cluster::$busy);            }          });        }      }      $worker = new Worker($this);      $worker->on('debug', function($output) { fwrite(STDOUT, $output); });      $worker->on('error', function($output) { fwrite(STDERR, $output); });      $worker->on('exit', function($code, $signal) use($worker) {        unset($this->workers[$worker->pid]);        $this->emit('exit', array($worker, $code, $signal));        if (empty($this->workers)) {          $this->close();        }      });      $this->workers[$worker->pid] = $worker;      return $this;    }    /**     * Closing the current cluster instance     */    public function close() {      if (!empty($this->workers)) {        foreach($this->workers as $w) $w->close();      }      $this->workers = array();    }    /**     * Starts to listen     */    public function listen() {      if (!$this->isWorker()) {        throw new \Exception(          'Could not listen, use Cluster::isWorker() to listen only from child mode'        );      }      $this->socket->listen();      return $this;    }  }  // STATIC EXECUTION :  Cluster::$cid = array_search('--slave', $_SERVER['argv']);  Cluster::$master = Cluster::$cid === false;  if (!Cluster::$master) {    Cluster::$cid = intval($_SERVER['argv'][Cluster::$cid + 1]);  }}