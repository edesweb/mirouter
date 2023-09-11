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

There must exists a file hosts.ini on the routes path.
hosts.ini declare the hosts that MiRouter must attend.
Sample of hosts.ini
```
; If routes is false then no routes are allowed
; If routes is true then must exists a ini file called like the section

[raul.goblin.es]
routes=true
;check_session
; Script to check if the session is valid,
; the script must have a function called check_authenticated() that returns an object with one or two props:
;     return (object)[
;        'returnCode'    => MiRouter::RETCODE_ERROR,
;        'returnReason'  => 'Some error...'
;    ];
;     return (object)[
;        'returnCode'    => MiRouter::RETCODE_AUTHREQ,
;        'returnReason'  => 'Authentication required'
;    ];
;     return (object)[
;        'returnCode'    => MiRouter::RETCODE_OK
;    ];
; the script must be on the routes path established previously
;  on this sample: ../src/api/v1/check_authenticated.php
check_session=api/v1/check_authenticated.php

[goblin.es]
routes=true
; check_auth.php must have a function called check_auth()
check_session=api/v1/check_auth.php

[pepe.goblin.es]
routes=false
```
