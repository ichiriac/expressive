var cluster = require('cluster');
var http = require('http');

if (cluster.isMaster) {
  // Fork workers.
  for (var i = 0; i < 8; i++) {
    cluster.fork();
  }

  cluster.on('exit', function(worker, code, signal) {
    console.log('worker ' + worker.process.pid + ' died');
  });

  console.log('Server is ready to serve at http://127.0.0.1:1337/');
} else {
  var i = 0;
  // In this case its a HTTP server
  http.createServer(function(req, res) {
    res.writeHead(200);
    res.end("hello world" + i + "\n");
    i++;
  }).listen(1337);
}
