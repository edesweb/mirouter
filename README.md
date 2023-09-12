# MiRouter
MiRouter routes HTTP requests to our configured PHP files.

With MiRouter can serve as an API gateway or to serve pages dinamically.

Mirouter is 99% configurable.

### Requirements

* Apache HTTP server
* PHP 7+

### Install

### .htaccess

You should configure a `.htaccess` file on one  document root to activate automatically MiRouter

.htaccess sample file, this redirects all to {*document_root*}/mirouter.php
```
<IfModule mod_rewrite.c>
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^(.*)$ /router.php [NC,L,QSA]
</IfModule>
```

`/mirouter.php` file (we have the main router file out of document root):
```
<?php
    include '../router.php'
```

### How does it works?

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

; how many MB max can have the log, when reach the MB will be renamed to logfile.YmdHis and a new one will be created
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

Must exists a file `hosts.ini` on the routes path.

In `hosts.ini`we declare the hosts that MiRouter must attend.

Sample of `hosts.ini`
```
[big.acme.com]
; If routes is true then must exists a ini file called like the section on the routes path
; in this sample must exists the file ../routes/big.acme.com.ini
routes=true

;auth_checker
; Script to check if the user is authenticated,
; the script must have a function called like the script without the extension and must return an object with one or two props:
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
;  the file check_authenticated.php must have a function called check_authenticated()
auth_checker=api/v1/check_authenticated.php

[acme.net]
; If routes is true then must exists a ini file called like the section on the routes path
; in this sample must exists the file ../routes/acme.net.ini
routes=true

; check_auth.php must have a function called check_auth()
auth_checker=api/v1/check_auth.php

[small.acme.com]
; If routes is false then no routes are allowed
routes=false
```

### Routes file

When MiRouter find a host declared as attendable then reads its config file, it contains the route parts of the URL as sections, this file must be called like the host section and end with `.ini`

This `.ini` file must contain the allowed routes for that host.
```
onedomain.com -> {routes_path}/onedomain.com.ini
raul.goblin.es -> {routes_pah}/raul.goblin.es.ini
```

Example: `../routes/big.acme.com.ini`
```
; public or auth-req key
;    You can use public or auth-req indistinctly to indicate if an authentication is required for the section.
;
;    param    | value       | auth required
;    --------------------------------
;    public   | not present | NO
;    public   | true        | NO
;    public   | false       | YES
;    auth-req | not present | NO
;    auth-req | true        | YES
;    auth-req | false       | NO
;
; script key
;   if empty then will try load last section name php file:
;      sample: [api/v1/login/] will try to load {sources_path}/api/v1/login/login.php
;
; [default] section is a fallback used when a route is not found


[default]
public=false
script=api/v1/login/login.php

[auth]
public=true
script=api/v1/authenticate_by_apikey.php

[users/search]
public=false
script=api/v1/users/search.php

[main]
auth-req=false
script=api/v1/main/main.php

[cliens/add]
public=false
script=api/v1/clients/clients.php

[inscription]
public=true
script=inscription.php
```

On that file should exists the section corresponding with the route of the URL.

In `acme.com/fireworks` sould exists the section `fireworks`, if does't then will try to use de `default` section and if also doesn't exists then that will be an error.

When a route is found then MiRouter checks the key `public` or `auth-req`, if the route requires authentication then the file indicated on the key `auth_checker` in the `hosts.ini` file will be used to check if the user is authenticated. **MiRouter doesn't have any auth system, you must provide it.**

If auth required and the user is authenticated then MiRouter finish by returning the file to include.

If no auth required MiRouter simply returns the file to be included.

