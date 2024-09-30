<?php
namespace PHPSHMCache;
error_reporting(E_ALL);
ini_set('display_errors', 1);
/**
 * from openemr: https://github.com/openemr/openemr/portal/patient/fwk/libs/verysimple/DB/DataDriver/MySQLi.php
 */
/** @var array characters that will be escaped */
static $BAD_CHARS = array (
    "\\",
    "\0",
    "\n",
    "\r",
    "\x1a",
    "'",
    '"'
        );

/** @var array characters that will be used to replace bad chars */
static $GOOD_CHARS = array (
    "\\\\",
    "\\0",
    "\\n",
    "\\r",
    "\Z",
    "\'",
    '\"'
        );

/**
 * get a key
 */
function getSHMKey ($path, $pj = "b") 
{
    return ftok($path, $pj);
}

/**
 * Get the microsecond from microtime() and offset $microseconds
 * @param int $microseconds [optional]
 * @return int
 */
function microtime(int $microseconds = 0): int
{
    return intval(round(microtime(true) * 1000)) + $microseconds;
}


/**
 * Package to an array and serialize to a string
 * @param mixed $data
 * @param int $seconds [optional]
 * @return string
 */
function pack($data, int $seconds = 0): string
{
    return serialize(
        array(
            'data' => $data,
            'timeout' => $seconds ? microtime($seconds * 1000) : 0,
        )
    );
}


/**
 * Unpacking a string and parse no timeout data from array
 * @param string $data
 * @return mixed|bool
 */
function unpack(string $data)
{
    return unserialize($data);
}

/**
 * Clean data from shared memory block
 */
function clean($shmKey) 
{
    // Check if the shared memory segment with the given shmkey exists
    $id = @shmop_open($shmKey, 'a', 0, 0);

    if ($id !== false) {
        // The shared memory segment exists, so delete the old data
        //print("[clean] remove $shmKey\n");
        shmop_delete($id);
        return true;
    }
    return false;
}
 
/**
 * Write data into shared memory block
 * @param mixed $data
 * @param int $seconds [optional]
 * @return bool
 */
function write($shmKey, $data, int $seconds = 0)
{
    /*
    if (!$data) {
        return false;
    }
     */

    clean($shmKey);

    $data = pack($data, $seconds);
    $id = shmop_open($shmKey, "n", 0644, strlen($data));
    if ($id === false) {
        return false;
    }

    $size = shmop_write($id, $data, 0);

    return $size;
}

/**
 * Read data from shared memory block
 * @return bool|mixed 
 * @return false for not exists or timeout; if enabled; data otherwise
 */
function read($shmKey)
{
    
    $id = @shmop_open($shmKey, "a", 0, 0);
    if ($id === false) {
        #print("[read] try $shmKey but not found \n");
        return false;
    }

    $data = shmop_read($id, 0, shmop_size($id));

    $data = unpack($data);
    $result = $data['data'];
    $timeout = intval($data['timeout']);
    if ( $timeout != 0 && $timeout < microtime()) {
        $result = false; //timeout
    }
    //shmop_close($id);
    return $result;
}


function getStack () 
{
    $callStack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    // Concatenate relevant information from the call stack into a string
    $stackString = '';
    foreach ($callStack as $trace) {
        // Include relevant information (e.g., function name, class name) in the key
        //$stackString .= isset($trace['class']) ? $trace['class'] . '::' : '';
        $stackString .= $trace['function'] . '|';
    }
    return $stackString;
}

function ADDR($idx) {
    return $idx ^ 0xFFFF0000;
}

function IDX($addr) {
    return $addr ^ 0xFFFF0000;
}
function findAddr($str) 
{
    $matches = [];

    // Define the regex pattern to match decimal numbers
    $pattern = '/\b\d+\b/';

    // Find all matches
    preg_match_all($pattern, $str, $matches);

    // Filter out values in the specified range
    $matches = array_filter($matches[0], function ($match) {
        return ($match >= 0xFFFF0000 && $match <= 0xFFFFFFFF);
    });
    return $matches;
}

function extractTableName($sql) 
{
    // Regular expression for matching UPDATE statement and extracting table name
    $updatePattern = '/UPDATE\s+(\w+)\s+SET/i';

    // Perform the match
    if (preg_match($updatePattern, $sql, $matches)) {
        $tableName = $matches[1];
        return array("UPDATE", hexdec(crc32($tableName)));
    } 
    // Regular expression for matching SELECT statement and extracting table name
    $selectPattern = '/FROM\s+(\w+)/i';

    // Perform the match
    if (preg_match($selectPattern, $sql, $matches)) {
        $tableName = $matches[1];
        return array("SELECT", hexdec(crc32($tableName)));
    }
    return array("None", NULL);
}

class PHPTrace 
{

    // a global variable
    public static $trace = array();
    // $funcName, $args, $ret 

    public static function sqlPutTrace($funcName, $args)
    {
        #print(var_dump($args));
        self::$trace[] = ["funcName"=>$funcName, "args" => $args, "ret" => ADDR(count(self::$trace))];
        return end(self::$trace)["ret"];// return the index
    }

    public static $bitmapSHMKey = 0xFFFFFFFF;
    //hexdec(crc32("BITMAP"));

    /**
     * redo trace for the last trace element function call
     */
    public static function redoQuery($resultIdx)
    {
        // find $result variable from result fetch
        // first argument
        PHPTrace::dumpTrace();
        //$resultIdx = IDX(end(self::$trace)["ret"]) IDX(end(self::$trace)["args"][0]); // start from fetch!!
        $queryCall = self::$trace[$resultIdx];
        $connectIdx = IDX($queryCall["args"][0]);
        $connectCall = self::$trace[$connectIdx];
        $mysqli = call_user_func_array("mysqli_connect", $connectCall["args"]);
	    //echo "redoSql():\tconnect: $connectIdx\tresultIdx: $resultIdx\n";

        for($i = $resultIdx -1; $i > $connectIdx; $i --) {
            // backward search for select_db or use db
            $call = self::$trace[$i];
            if ($call["funcName"] == "mysqli_select_db") {
                    mysqli_select_db($mysqli, $call["args"][1]);
                    break;
            }
            else if ($call["funcName"] =="mysqli_query" && strpos($call["args"][1], "USE ") !== false) {
                #echo "redoSql():\tuseDB: $i\n";
                // XXX check query, embedded values.
                mysqli_query($mysqli, $call["args"][1]);
                break;
            }
        }
        $q = $queryCall["args"][1];
        /*
        switch (gettype($q)) {
            case "string":
                $addrs = findAddr($q);
                foreach ($addrs as $a) {
                    $call = self::$trace[IDX($a)];
                    if($call["funcName"] === "mysqli_real_escape_string") {
                        $data = mysqli_real_escape_string($mysqli, $escapeQueryCall["args"][1]);
                        $q = str_replace($a, $data, $q);
                    }
                }
                $query = $q;
                break;
            case "integer":
                $escapeQueryCall = self::$trace[IDX($q)];
                if($escapeQueryCall["funcName"] === "mysqli_real_escape_string") {
                    $query = mysqli_real_escape_string($mysqli, $escapeQueryCall["args"][1]);
                    break;
                }
            default:
                self::dumpTrace("escapeQuerynotFound"); 
        }
        */
        $query = $q;

        $result = mysqli_query($mysqli, $query);

        $allData = mysqli_fetch_all($result, MYSQLI_BOTH);
        mysqli_close($mysqli);
        
        return $allData;
    }

    public static function dumpTrace($log="")
    {
        $log = "-----\n"
        . $log 
        ."\n"
        . getStack() . "\n----\n";
        for($i = 0; $i < count(self::$trace); $i ++) {
            $t = self::$trace[$i];
            $log .= $i . ": " . $t["funcName"]. "("
            . implode(",", $t["args"])
            . ")@"
            . dechex($t["ret"])
            . "\n";
        }
        $customLogFile = "/tmp/php.log";
        error_log($log, 3, $customLogFile);
    }
}



/**
 * record the sql function calls in trace 
 */
function sqlWrapperFunc($funcName, $args)
{
    PHPTrace::sqlPutTrace($funcName, $args);
    //echo $funcName ."\n";

    switch ($funcName) {
        case "mysqli_connect":
            return end(PHPTrace::$trace)["ret"]; // return the index;
        case "mysqli_real_escape_string":
            return str_replace($BAD_CHARS, $GOOD_CHARS, $args[1]);
        case "mysqli_query":
            if (checkSqlSyntax($args[1])) {
                // can use phpmyadmin or antlr
                // cause an error and report back to fuzzer!

            }
            $table = extractTableName($args[1]);
            $tablehash = $table[1];
            $queryhash = hexdec(crc32($args[1]));
            if ($table[0] === 'SELECT') {
                $table2query = read(PHPTrace::$bitmapSHMKey);
                $table2query = ($table2query!== false)? $table2query: array(); 
                if(array_key_exists($tablehash , $table2query) 
                && array_key_exists($queryhash, $table2query[$tablehash]) 
                && $table2query[$tablehash][$queryhash] === 1 ) {
                    // valid, then we can directly return the results;
                    //return read($queryhash);
                    //return end(PHPTrace::$trace)["ret"]; // return the index;
                    //print("[query]: SELECT: valid cache\n");
                }
                else{
                    $allData = PHPTrace::redoQuery(IDX(end(PHPTrace::$trace)["ret"])); // should give idx of query// default the last one
                    write($queryhash, $allData);
                    $table2query[$tablehash][$queryhash] = 1;
                    write(PHPTrace::$bitmapSHMKey, $table2query);
                    // data is in cache and valid
                    //return $allData;
                    //print("[query]: SELECT: invalid cache\n");
                }
            }
            else if ($table[0] === 'UPDATE' || $table[0] === 'INSERT') {
                
                $allData = PHPTrace::redoQuery(IDX(end(PHPTrace::$trace)["ret"]));
                write($queryhash, $allData);
                // update 
                $table2query = read(PHPTrace::$bitmapSHMKey);
                if(array_key_exists($tablehash , $table2query)) {
                    foreach ($table2query[$tablehash] as $sql) {
                        $table2query[$tablehash][$sql] = 0;
                    }
                    write(PHPTrace::$bitmapSHMKey, $table2query);
                }
            }
            else{
                //print($args[1]);
            }

            return end(PHPTrace::$trace)["ret"]; // return the index;

        case "mysqli_close":
        case "mysqli_error":
        case "mysqli_connect_error":
            return true; // usually no return value
        
        case "mysqli_fetch_assoc":
        case  "mysqli_fetch_array":
        case "mysqli_fetch_row":
        case "mysqli_fetch_all":   
        case "mysqli_num_rows": 
            $resultIdx = IDX($args[0]);
            
            $queryhash = hexdec(crc32(PHPTrace::$trace[$resultIdx]["args"][1]));
            $table = extractTableName(PHPTrace::$trace[$resultIdx]["args"][1]);
            $tablehash = $table[1];
            $table2query = read(PHPTrace::$bitmapSHMKey);

            if(array_key_exists($tablehash , $table2query) 
            && array_key_exists($queryhash, $table2query[$tablehash]) 
            && $table2query[$tablehash][$queryhash] === 1 ) {
                // valid, then we can directly return the results;
                //print("[fetch]: valid sql cached\n");
                $allData = read($queryhash);
            }
            else{
                //print("[fetch]: invalid sql and redo!\n");
                $allData = PHPTrace::redoQuery($resultIdx); // should give idx of query//
                write($queryhash, $allData);
                $table2query[$tablehash][$queryhash] = 1;
                write(PHPTrace::$bitmapSHMKey, $table2query);
                // data is in cache and valid
            }
            return ($funcName === "mysqli_num_rows")? count($allData): $allData;
        case "mysqli_fetch_lengths":
            return array(0);
        }
        return false;
}
