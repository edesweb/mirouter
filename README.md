# mirouter
MiRouter routes HTTP requests byt config files to php, nodejs, python or anything

Mirouter is 99% configurable.

When instantiated required an array with the main config.

```
THIS IS A SAMPLE CONFIG FILE CALLED router.ini

[paths]
; routes contains the file "hosts.ini" and other .ini files indicated con it
routes = '../routes/'

; src indicates the base path for the source files
src    = '../d/'

[log]
; wtl=What To Log - you can sum the values to log to both
; 0=no log
; 1=log to file
; 2=log to stdout
wtl    = 0

; on path the string {host} will be replaced by the _SERVER['HTTP_HOST']
path   = '../_tmp/log/mirouter_{host}.log'

; how many MB max can have the log, when reach the MB will be renamed to logfile.YmdHis
rotate_log_mb = 10
```

Sample use:
```
namespace lib\MiRouter;
include '../lib/MiRouter/MiRouter.php';

use MiRouter;

$routerIni = parse_ini_file( 'router.ini', true);
$router = new MiRouter\MiRouter( $routerIni  );
if ($router->returnCode < 0) 
  die("($router->returnCode) $router->reason");

include $router->filename;

```
