# Expressive

Library using `reactphp/http` and enabling it to take advantage of multi-core
systems (similar with the nodejs cluster module).

Sample code :

```php
<?php

  require_once('vendor/autoload.php');

  $loop = React\EventLoop\Factory::create();
  $cluster = new Expressive\Cluster($loop, 1337, '127.0.0.1');

  if ($cluster->isMaster()) {
    for($i = 0; $i < 8; $i++) {
      $cluster->fork();
    }
    $cluster->on('exit', function($worker, $code, $signal) {
      echo "worker $worker->pid died\n";
    });
  } else {
    $cluster->on('request', function($req, $res) {
      $res->writeHead(200);
      $res->end("hello world\n");
    });
    $cluster->listen();
  }

  $loop->run();
```

You can run multiple clusters in the same application (see `examples/multi`)

# License

Under MIT - author Ioan CHIRIAC