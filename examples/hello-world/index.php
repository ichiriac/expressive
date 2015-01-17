<?php

  require_once(__DIR__ . '/../../vendor/autoload.php');

  $cluster = new expressive\cluster(1337, '127.0.0.1');

  if ($cluster->isMaster()) {
    for($i = 0; $i < 8; $i++) {
      $cluster->fork();
    }
    $cluster->on('exit', function($worker, $code, $signal) use($cluster) {
      echo "worker $worker->pid died with code $code \n";
      // restart a new worker
      // $cluster->fork();
    });
    echo "Server is listening on 1337\n";
  } else {
    $i = 0;
    $app = new expressive\server(function($req, $res) use($i) {
      echo "New request $i : $req->method @ $req->url \n";
      $res->writeHead(200);
      $res->end("hello world $i\n");
      if (++$i === 10) {
        exit($i); // make it crash
      }
    });
    $app->listen();
    echo "Child is ready !\n";
  }
