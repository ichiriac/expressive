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

## How to use it

This library is publised on packagist, so you can use it with composer :

```sh
$ composer require ichiriac/expressive
```

Then bootstrap you app as following :

```php
<?php
  require 'vendor/autoload';

  $loop = React\EventLoop\Factory::create();
  $cluster = new Expressive\Cluster($loop, 1337, '127.0.0.1');

  if ($cluster->isMaster()) {
    for($i = 0; $i < 8; $i++) {
      $cluster->fork();
    }
    $cluster->on('exit', function($worker) use($cluster) {
      echo "worker $worker->pid died\n";
      $cluster->fork();
    });
  } else {
    $cluster->on('request', function($req, $res) {
      // use your application code here
    });
    $cluster->listen();
  }

  $loop->run();
```

Read the wiki for more informations about the API :
https://github.com/ichiriac/expressive/wiki

## Tips for production

### 1. Monitor and keep alive

If you start your script in server mode, it's like the FCGI daemon, so you will
need to monitor it, and be sure that it will not crash.

To do so, you can use `forever`, a Node Js great tool :

```
$ npm install -g forever
$ forever -c php /path/to/your/app.php
```
See https://github.com/foreverjs/forever for more details

### 2. Serve assets

You never should serve assets dirrectly from your PHP script. But at the same
time, your assets and your pages are usually on the same domain and the port.

The next step is to bring a cool webserver in front that dispatch requests. You
can use Apache, Lighttpd, Varnish or Nginx. I am used with Nginx, so that's
a possible configuration :

```
server {

    root /path/to/your/app/www;
    server_name website.com;
    access_log off;

    location / {
      # ASSETS CONFIGURATION
      expires max;
      sendfile on;
      sendfile_max_chunk 256k;
      try_files $uri @backend;
    }

    location @backend {
      # WEBAPP CONFIGURATION
      expires epoch;
      proxy_pass http://127.0.0.1:1337;
    }
}
```

Many things can be done, as serving assets from a subdomain, behind a CDN.

## License

Under MIT - author Ioan CHIRIAC