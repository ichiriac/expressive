<?php

  /**
   * This sample is two http servers under a cluster of 4 child processes each
   */

  require_once(__DIR__ . '/../../vendor/autoload.php');

  $loop = React\EventLoop\Factory::create();

  $c1 = new Expressive\Cluster($loop, 1337, '127.0.0.1');
  $c2 = new Expressive\Cluster($loop, 8080, '127.0.0.1');

  // launch the cluster n°1
  if ($c1->isMaster()) {
    for($i = 0; $i < 4; $i++) {
      $c1->fork();
    }
    $c1->on('exit', function($worker, $code, $signal) {
      echo "worker $worker->pid died with code $code \n";
    });
  }

  // launch the cluster n°2
  if ($c2->isMaster()) {
    for($i = 0; $i < 4; $i++) {
      $c2->fork();
    }
    $c2->on('exit', function($worker, $code, $signal) {
      echo "worker $worker->pid died with code $code \n";
    });
  }

  // bind app for cluster n°1
  if ($c1->isWorker()) {
    $i = 0;
    $c1->on('request', function($req, $res) use($i) {
      $res->writeHead(200);
      $res->end("Cluster 1 - hello world $i\n");
    });
    $c1->listen();
  }

  // bind app for cluster n°2
  if ($c2->isWorker()) {
    $i = 0;
    $c2->on('request', function($req, $res) use($i) {
      $res->writeHead(200);
      $res->end("Cluster 2 - hello world $i\n");
    });
    $c2->listen();
  }


  $loop->run();