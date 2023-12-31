<?php
/**
 * MiRouter Class to route http request to our configured PHP files.
 *
 * @author Raúl Díaz <raul.diaztorres@gmail.com>
 * @license  MIT
 *
 * @link     https://github.com/edesweb/mirouter
 */
class MiRouter
{
    const DBGNONE = 0;
    const DBGLOG = 1;
    const DBGOUT = 2;

    // What To Debug
    //private int $WTD=self::DBGNONE;
    private int $WTD=self::DBGOUT;

    private string $logFile;

    const RETCODE_OK = 1;
    const RETCODE_ERROR = -1;
    const RETCODE_AUTHREQ = -2;


    //private array $routerIni;
    public string $reason='';
    public int $returnCode=self::RETCODE_OK;
    public string $filename='';

    public array $libs_to_include=array();

    public $plugin=false;

    private function noRoute($reason): void
    {
        $this->returnCode = self::RETCODE_ERROR;
        $this->reason 	  = $reason;
    }
    /*
	private function route($filename): void
	{
		$this->returnCode = self::RETCODE_OK;
		$this->filename	  = $filename;
	}
    */
    private function dbg( $msg ){
        if( $this->WTD&self::DBGOUT ){
            echo $msg."\n";
        }
        if( $this->WTD&self::DBGLOG && $this->logFile!=null ) {
            $ret = error_log( '['.getmypid().'] '.date('Y-m-d H:i:s').' >>> '.$msg, 3 , $this->logFile );
        }
    }

    /**
     * @param RequestUri $requestUri
     * @param $paths
     *
     * Sample: pepe.acme.com/xxx
     */
    //public function __construct( RequestUri $requestUri, array $routerIni)
    public function __construct( array $routerIni, int $wtd=null ) //self::DBGNONE )
    {

        //$this->routerIni = $routerIni;
        $requestUri      = new RequestUri();
        $paths           = $routerIni['paths'];

        $this->WTD       = ( $wtd==null )? $routerIni['log']['wtl']?? null : $wtd;
        $this->logFile   = $routerIni['log']['path']?? null;
        $this->logFile   = str_ireplace('{host}', $requestUri->host, $this->logFile );
        $rotate_log_mb   = $routerIni['log']['rotate_log_mb']?? 5;
        if( $this->logFile!=null ){
            $filesizeMB = round(@filesize($this->logFile) / 1024 / 1024, 1);
            if( $filesizeMB>$rotate_log_mb ){
                rename( $this->logFile , $this->logFile.'.'.date('Ymdhis') );
            }
        }


        if($this->WTD!=self::DBGNONE) $this->dbg( $this->eEcho($requestUri) );

        // Parse hosts.ini
        $hosts = parse_ini_file($paths['routes'] . 'hosts.ini', true);
        if($this->WTD!=self::DBGNONE) $this->dbg( $this->eEcho($hosts) );



        // Find the $requestUri->host or $requestUri->host_base section to get the routes value
        // If $requestUri->host (john.acme.com) is not found then check for $requestUri->host_base (acme.com)
        if (isset($hosts[$requestUri->host])) {
            $hosts_section = $requestUri->host;
        } else if (isset($hosts[$requestUri->host_base])) {
            $hosts_section = $requestUri->host_base;
        } else {
            $this->noRoute("Host not declared: {$requestUri->host}");
            return;
        }
        if($this->WTD!=self::DBGNONE)  $this->dbg( "hosts.ini section: {$hosts_section}\n". $this->eEcho($hosts[$hosts_section]) );

        // If routes value is false then no routes are defined
        $routes = $hosts[$hosts_section]['routes'] ?? false;
        if (!$routes) {
            $this->noRoute("No routes defined for: {$hosts_section}");
            return;
        }
        /*
                // If uri==login then redirect to login
                    if( strtolower($requestUri->uri)=='login' && $hosts[$hosts_section]['login']??false ){
                        $this->route( $paths['src'] . $hosts[$hosts_section]['login'] );
                        return;
                    }

                // If uri==logout then redirect to logout
                    if( strtolower($requestUri->uri)=='logout' && $hosts[$hosts_section]['logout']??false ){
                        $this->route( $paths['src'] . $hosts[$hosts_section]['logout'] );
                        return;
                    }
        */
        // If routes is true, then must exists a .ini filename with the section name
        $routes_filename = $paths['routes'] . $hosts_section . '.ini';
        if (!file_exists($routes_filename)) {
            $this->noRoute("Can't find routes file: {$routes_filename}");
            return;
        }
        if($this->WTD!=self::DBGNONE)  $this->dbg("routes_filename: {$routes_filename}" );

        // Parse $routes_filename
        $rini = parse_ini_file($routes_filename, true);
        if($this->WTD!=self::DBGNONE)  $this->dbg('rini: '. $this->eEcho($rini)."\nTry to find section: {$requestUri->uri }");

        // Get section for the requested uri
        $uri_section = $rini[$requestUri->uri] ?? null;
        if ($uri_section == null) {

            // if wild_router then return the $requestUri->uri.php
            $wild_router = $rini['mirouterconfig']['wild_router']?? false;
            if( $wild_router ){
                $script_to_load = $requestUri->uri. (( strpos($requestUri->uri,'.')===false )? '.php': '');
                if( !file_exists($paths['src'].$script_to_load) ){
                    $this->noRoute("Can't find route to {$script_to_load}");    //" in file {$routes_filename}");
                    return;
                }
                $this->filename     = $paths['src'] .$script_to_load;
                $this->returnCode   = self::RETCODE_OK;
                return;
            }
            // Check if exists [default] section, if not -> error
            if($this->WTD!=self::DBGNONE)  $this->dbg("Not found, trying to find  [default] section...");
            $requestUri->uri = 'default';
            $uri_section     = $rini[$requestUri->uri] ?? null;
            if ($uri_section == null) {
                $this->noRoute("Can't find route to {$requestUri->uri} in file {$routes_filename}");
                return;
            }
        }
        if($this->WTD!=self::DBGNONE)  $this->dbg("Section: {$requestUri->uri}\n" . $this->eEcho($uri_section) );

        /**
         * if $uri_section[public]==false then authentication required
         * if $uri_section[auth-req]==true then authentication required
         * Then we search for a script at $hosts[$hosts_section]['auth_checker']
         * that script must have a function called like the script but with no extension (points will be replaced with '_')
         * and must return an object with two elements (returnCode & returnReason).
         *    Example: must exists a function "check_authenticated_func()"
         *      [raul.goblin.es]
         *      routes=true
         *      auth_checker=api/v1/check.authenticated_func.php
         *
         *
         *
         * param    | value       | auth required
         * --------------------------------------
         * public   | not present | NO
         * public   | true        | NO
         * public   | false       | YES
         * auth-req | not present | NO
         * auth-req | true        | YES
         * auth-req | false       | NO
         * */

        $uri_section['public']   = $uri_section['public']?? true;
        $uri_section['auth-req'] = $uri_section['auth-req']?? false;

        if ( $uri_section['public']==false || $uri_section['auth-req']==true ) {
            if($this->WTD!=self::DBGNONE)  $this->dbg("Authentication required ({$requestUri->uri}['public']=false or {$requestUri->uri}['auth-req']=true )");

            // Check if session_checker key exists in the routes_file
            if( isset( $rini['mirouterconfig']['session_checker'] ) ){
                $auth_checker_file = $paths['src'] . $rini['mirouterconfig']['session_checker'];
            }else{
                // Try to get the session_checker key in the hosts file
                if (!isset($hosts[$hosts_section]['session_checker'])) {
                    $this->noRoute("check-login not defined in hosts.ini, section {$hosts_section}");
                    return;
                }
                $auth_checker_file = $paths['src'] . $hosts[$hosts_section]['session_checker'];
            }

            if( !file_exists($auth_checker_file) ){
                $this->noRoute("session_checker file does not exists: {$auth_checker_file}");
                return;
            }

            // include the $auth_checker_file and try to execute a function called like the file but without '.php'
            // that function is the responsible of checking if the user is authenticated.
            require $auth_checker_file;
            $function_to_execute = strtolower( str_replace('.','_',str_ireplace('.php','',basename($auth_checker_file)) ) );
            if( !function_exists($function_to_execute)){
                $this->noRoute("[{$function_to_execute}] function does not exists in: {$auth_checker_file}");
                return;
            }
            $checkSessionRet = $function_to_execute();
            if( $checkSessionRet->returnCode != self::RETCODE_OK ){
                $this->returnCode 	= $checkSessionRet->returnCode;
                $this->reason		= $checkSessionRet->returnReason; //'Authentication required';
                return;
            }
        }

        // Check script
        $this->plugin           = false;
        $uri_section['script']  = $uri_section['script']?? null;
        if ($uri_section['script'] == null) {
            // if $uri_section[script] is empty load the section_name.php
            $script_to_load = $paths['src'] . $requestUri->auri[count($requestUri->auri) - 1] . '.php';
            if($this->WTD!=self::DBGNONE)  $this->dbg("Load(1) {$script_to_load}");
            if (!file_exists($script_to_load)) {
                $this->noRoute("Can't load {$script_to_load}");
                return;
            }
        } else {
            $script_to_load = $paths['src'] . $uri_section['script'];
            if($this->WTD!=self::DBGNONE)  $this->dbg("Load(2) {$script_to_load}");

            $this->filename     = $script_to_load;

            // Check if are plugins defined
            $re = ( $routerIni['plugins']['regexp'] )?? false;
            if( !$re ){
                if (!file_exists($script_to_load)) {
                    $this->noRoute("Can't load {$script_to_load}");
                    return;
                }
            }else{
                // Check if script requires a plugin
                $re = '/'.$re.'/';
                if( preg_match_all($re, $uri_section['script'], $matches, PREG_SET_ORDER, 0) ){
                    //echo $matches[0]['PLUGIN'].'<BR>'.$matches[0]['SCRIPT'];exit;
                    $plugin = $matches[0]['PLUGIN']?? false;
                    $script = $matches[0]['SCRIPT']?? false;
                    if( $plugin && $script ){
                        $this->plugin       = $plugin;
                        $this->filename     = $script;
                    }
                }else{
                    if (!file_exists($script_to_load)) {
                        $this->noRoute("Can't load {$script_to_load}");
                        return;
                    }
                }
            }
        }



        // Replace the lib key by the mirouterconfig/libs section keys
        $uri_section['libs'] = $uri_section['libs']?? '';
        $libs = trim($uri_section['libs']);
        $libs2include = array();
        if($libs!=''){
            $libs = explode(',',$libs);
            foreach($libs as $lib){
                if( isset( $rini['mirouterconfig/libs'][trim($lib)]) ){
                    $this->libs_to_include[] = $rini['mirouterconfig/libs'][trim($lib)];
                }
            }
        }


        $this->returnCode   = self::RETCODE_OK;
    }

    private function eEcho($s,$ret=true): string
    {
        if( is_string($s) ){
            $s = str_replace('<', '&lt;', $s);
            $s = str_replace('>', '&gt;', $s);
        }
        if($ret===false){ echo '<code><pre>'.print_r($s,true).'</pre></code><br>'; return '';}
        return print_r($s,true);
    }
}


/**
 * RequestUri class to store info about the client request
 */
class RequestUri
{
    // $host contains _server['HTTP_HOST']
    public  $host;
    // if $host has subdomains contains base domain
    public  $host_base;
    // uri contains the uri without parameters
    public  $uri;
    // auri is an index of $uri folders
    public  $auri;
    // params is an associative array with the _GET params
    public  $params;
    // _GET params as string
    public  $paramsString=null;
    // contains _SERVER['REQUEST_METHOD'] which verb is used on the call
    public string $method;
    public function __construct()
    {
        $this->method 	= $_SERVER['REQUEST_METHOD'];
        $this->uri 		= $_SERVER["REQUEST_URI"];
        $this->host		= str_ireplace('www.','', $_SERVER['HTTP_HOST']);		// Remove www from host
        if( substr_count($this->host,'.')==1 ){
            $this->host_base = $this->host;
        }else{
            $this->host_base = explode('.', $this->host);
            $this->host_base = $this->host_base[ count($this->host_base)-2 ].'.'.$this->host_base[ count($this->host_base)-1 ];
        }

        // _GET parameters to $this->params
        $par=strpos($this->uri,'?');
        if( $par!==false ) {
            $params 	= ' ' . substr($this->uri, $par + 1);
            $this->paramsString = trim($params);
            $this->uri = substr($this->uri, 0, $par);
            $params 	= explode('&', trim($params));
            foreach ($params as $k => $v) {
                $v = explode('=', $v);
                $this->params[$v[0]]=$v[1];
            }
        }

        $this->uri = preg_replace('/\/\/{1,}/', '/', $this->uri);		// Replace multiple //

        $this->uri = preg_replace('/(^\/)|(\/$)/', '', $this->uri);	// Replace first /
        //$this->uri = preg_replace('/^\//', '', $this->uri);	// Replace first /
        //$this->uri = preg_replace('/\/$/', '', $this->uri);	// Replace first and last /

        $this->auri=explode('/',$this->uri);
        //$this->uri .= '/';
    }
}
