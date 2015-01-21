<?php

  /**
   * This sample is a basic http server under a cluster of 8 child processes
   */

  require_once(__DIR__ . '/../vendor/autoload.php');

  $cluster = new Expressive\Cluster(1337, '127.0.0.1');

  if ($cluster->isMaster()) {
    for($i = 0; $i < 8; $i++) {
      $cluster->fork();
    }
    $cluster->onExit(function($worker, $code, $signal) use($cluster) {
      echo "worker $worker->pid died with code $code \n";
    });

    echo "Server is ready to serve at http://127.0.0.1:1337/\n";
  } else {
    $i = 0;
    $cluster->onRequest(function($req, $res) use($i) {
      $res->writeHead(200);
      $res->end("hello world $i\n");
      $i++;
    });
  }
