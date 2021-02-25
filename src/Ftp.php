<?php
/**
 * contains \DavidLienhard\Ftp\Ftp class
 *
 * @package         tourBase
 * @author          David Lienhard <david.lienhard@tourasia.ch>
 * @copyright       tourasia
 */

declare(strict_types=1);

namespace DavidLienhard\Ftp;

use \DavidLienhard\Log\LogInterface;
use \DavidLienhard\Ftp\Exceptions\FtpException as FtpException;
use \DavidLienhard\Ftp\Exceptions\ConnectException as FtpConnectException;
use \DavidLienhard\Ftp\Exceptions\LoginException as FtpLoginException;
use \DavidLienhard\FunctionCaller\Call as FunctionCaller;

/**
 * contains methods for ftp transfers
 *
 * this functions mainly contains the standard php-ftp functions.
 * but there are also some additional functions/parameters making
 * the use of ftp functions easier.
 *
 * @author          David Lienhard <david.lienhard@tourasia.ch>
 * @copyright       tourasia
*/
class Ftp implements FtpInterface
{
    /** the type of operating system */
    private string $sysType;

    /** the password to use for anyonymous connections */
    private string $anonymousPassword = "qwertz@anonymous.net";

    /**
     * all file-endings for which the ascii upload should be used
     * @var     string[]
     */
    private array $ascii = [
        "asp",
        "bat",
        "c",
        "ccp",
        "csv",
        "h",
        "htm",
        "html",
        "shtml",
        "ini",
        "log",
        "php",
        "pl",
        "perl",
        "sh",
        "sql",
        "txt",
        "cgi",
        "lock",
        "json",
        "xml",
        "yml"
    ];

    /** the time used for ftp connections */
    private float $time = 0;

    /** the hostname to connect to */
    private string $host;

    /** the port to connect to */
    private int $port;

    /** the connection timeout */
    private int $timeout;

    /** the username to login with */
    private string $user;

    /** the password to login with */
    private ?string $pass;

    /**
     * the range to calculate the portnumber
     * for active connections if the php functions
     * are not enabled.
     * @var     int[]
     */
    private array $portrange = [ 150, 200 ];

    /**
     * the ip of this server (the php client)
     * used for active connections without
     * the php functions
     */
    private ?string $ip = null;

    /** passive mode */
    private bool $pasv = false;

    /**
     * The ftp connection resource
     * @var     resource
     */
    private $ftp;


    /**
     * sets important variables
     *
     * checks if the php functions, the function microtime(true),
     * the classes socket and server exist.
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           bool                                    $debug  turn debugging on or off.
     * @param           \DavidLienhard\Log\LogInterface|null    $log    optional logging object for debugging
     * @return          void
     * @uses            Ftp::$debug
     * @uses            Ftp::$log
     */
    public function __construct(private bool $debug = false, private ?LogInterface $log = null)
    {
        $this->debug = $debug;
        $this->log = $log;
    }

    /**
     * connects to the ftp-server
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string          $host           host to connect to
     * @param           string          $user           username to connect with
     * @param           string          $pass           password to connect with. or null to use anonymous password
     * @param           int             $port           port to connect to
     * @param           int             $timeout        timeout in seconds
     * @uses            Ftp::debug()
     * @uses            Ftp::$ftp
     * @uses            Ftp::$host
     * @uses            Ftp::$user
     * @uses            Ftp::$pass
     * @uses            Ftp::$timeout
     * @uses            Ftp::$anonymousPassword
     * @uses            Ftp::$sysType
     */
    public function connect(
        string $host,
        string $user,
        ?string $pass,
        int $port = 21,
        int $timeout = 30
    ) : void {
        $start = microtime(true);

        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->port = $port;
        $this->timeout = $timeout;

        // check if a password is set
        // if not, use the anonymous password
        if ($pass === null) {
            $pass = $this->anonymousPassword;
        }

        // connect
        $this->debug("connecting to '".$host.":".$port."'", __FUNCTION__);

        $caller = new FunctionCaller("ftp_connect", $host, $port);
        $ftp = $caller->getResult();

        // connection failed
        if ($ftp === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("connection failed".$errmsg, __FUNCTION__);
            throw new FtpConnectException("could not connect to the host".$errmsg);
        }

        $this->ftp = $ftp;

        // login
        $this->debug("logging in with '".$user."'", __FUNCTION__);

        $caller = new FunctionCaller("ftp_login", $this->ftp, $user, $pass);

        // login failed
        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not login".$errmsg, __FUNCTION__);
            throw new FtpLoginException("unable to login".$errmsg);
        }

        // set servers system type
        $caller = new FunctionCaller("ftp_systype", $this->ftp);
        $sysType = $caller->getResult();
        $sysType = $sysType !== false ? $sysType : "";


        // add time used
        $this->time += microtime(true) - $start;

        $this->debug("connection successful in ".round(microtime(true) - $start, 3)."s", __FUNCTION__);
    }

    /**
     * returns the content of a directory
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string          $dir        the directory
     * @return          array<int, array<string, int|string>>
     * @uses            Ftp::$ftp
     * @uses            Ftp::analyzeDir()
     * @uses            Ftp::debug()
     */
    public function dirList(string $dir = "./") : array
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("retrieving raw list for folder '".$dir."'", __FUNCTION__);
        $caller = new FunctionCaller("ftp_rawlist", $this->ftp, $dir);
        $list = $caller->getResult();

        if ($list === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not get rawlist".$errmsg, __FUNCTION__);
            throw new FtpException("could not get rawlist".$errmsg);
        }

        $newlist = [];
        for ($i = 0; $i < count($list); $i++) {
            $buffer = $this->analyzeDir($list[$i]);
            if ($buffer['type'] === 0 || $buffer['type'] === 2) {   // file and link
                $newlist[] = [
                    "name" => $buffer['name'],
                    "type" => 0
                ];
            } elseif ($buffer['type'] == 1) {                       // directory
                $newlist[] = [
                    "name" => $buffer['name'],
                    "type" => 1
                ];
            }
        }

        $this->debug(
            "list successfully retreived in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;

        return $newlist;
    }

    /**
     * returns the content of a directory
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string          $dir        the directory
     * @return          mixed[]
     * @uses            Ftp::$ftp
     * @uses            Ftp::debug()
     */
    public function nList(string $dir = "./") : array
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("retrieving list for folder '".$dir."'", __FUNCTION__);
        $caller = new FunctionCaller("ftp_nlist", $this->ftp, $dir);
        $list = $caller->getResult();

        if ($list === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not get directory list from server".$errmsg, __FUNCTION__);
            throw new FtpException("could not get directory list from server".$errmsg);
        }

        $this->debug(
            "list successfully retreived in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;

        return $list;
    }

    /**
     * puts a file on the server
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $local       the local file
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $nb          put the file not blocking on the server (ftp_nb_put())
     * @uses            Ftp::$ftp
     * @uses            Ftp::getMode()
     * @uses            Ftp::debug()
     * @uses            FTP_ASCII
     * @uses            FTP_BINARY
     * @uses            FTP_FAILED
     */
    public function put(string $local, string $remote, int | string $mode = "auto", bool $nb = false) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        // sanity check
        if (!file_exists($local)) {
            $this->debug("local file '".$local."' does not exist", __FUNCTION__);
            throw new FtpException("local file '".$local."' does not exist");
        }

        // get transfer mode
        if ($mode !== FTP_ASCII && $mode !== FTP_BINARY) {
            $mode = $this->getMode($local);
        }

        $function = $nb ? "ftp_nb_put" : "ftp_put";

        $this->debug("uploading file '".$local."' to '".$remote."' using ".$function."()", __FUNCTION__);
        $caller = new FunctionCaller($function, $this->ftp, $remote, $local, $mode);

        if ($caller->getResult() === false || $caller->getResult() === FTP_FAILED) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not put file (".$local.") on server".$errmsg, __FUNCTION__);
            throw new FtpException("could not put file (".$local.") on server".$errmsg);
        }

        $this->debug(
            "file (".$local.") successfully uploaded in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }


    /**
     * puts a file not blocking on the server
     *
     * this functions is just a 'link' to the put() function
     * which puts the file on the server. see the parameter
     * $nb in this function.
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $local       the local file
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @uses            Ftp::put()
     */
    public function nb_put(string $local, string $remote, int | string $mode = "auto") : void
    {
        $this->put($local, $remote, $mode, true);
    }

    /**
     * puts a with {@link fopen()} opened file on the server
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           resource         $fp          the local filepointer
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $nb          put the file not blocking on the server (ftp_nb_fput())
     * @uses            Ftp::$ftp
     * @uses            Ftp::getMode()
     * @uses            Ftp::debug()
     */
    public function fput($fp, string $remote, int | string $mode = "auto", bool $nb = false) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        if (!is_resource($fp)) {
            throw new \TypeError("\$fp is not a resource");
        }

        // get the upload mode (ascii|binary)
        if ($mode != FTP_ASCII && $mode != FTP_BINARY) {
            $mode = FTP_ASCII;
        }

        $function = $nb ? "ftp_nb_fput" : "ftp_fput";

        $this->debug("uploading file using ".$function."()", __FUNCTION__);
        $caller = new FunctionCaller($function, $this->ftp, $remote, $fp, (int) $mode);

        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not put file on server".$errmsg, __FUNCTION__);
            throw new FtpException("could not put file on server".$errmsg);
        }

        $this->debug(
            "file successfully uploaded in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }


    /**
     * puts a with {@link fopen()} opened file not blocking on the server
     *
     * this functions is just a 'link' to the fput() function
     * which puts the file on the server. see the parameter
     * $nb in this function.
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           resource         $fp          the local filepointer
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @uses            Ftp::fput()
     */
    public function nb_fput($fp, string $remote, int | string $mode = "auto") : void
    {
        if (!is_resource($fp)) {
            throw new \TypeError("\$fp is not a resource");
        }

        $this->fput($fp, $remote, $mode, true);
    }

    /**
     * puts a directory with all contents on the server
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $local       the local directory
     * @param           string           $remote      the remote directory
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $failfast    whether to stop as soon as an error occurs
     * @uses            Ftp::mkdir()
     * @uses            Ftp::putDir()
     * @uses            Ftp::put()
     */
    public function putDir(string $local, string $remote, int | string $mode = "auto", bool $failfast = true) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("uploading folder '".$local."' to '".$remote."'", __FUNCTION__);

        // check if local file is a directory
        if (!is_dir($local)) {
            $this->debug("directory '".$local."' is not directory", __FUNCTION__);
            throw new FtpException("directory '".$local."' is not directory");
        }

        // open the local directory and check for success
        $dir = opendir($local);

        if ($dir === false) {
            $this->debug("could not open the directory '".$local."'", __FUNCTION__);
            throw new FtpException("could not open the directory '".$local."'");
        }

        $error = false;

        // loop though the folder
        while (($object = readDir($dir)) !== false) {
            if (fileType($local.DIRECTORY_SEPARATOR.$object) === "dir" && $object !== "." && $object !== "..") { // object is a folder
                // create folder
                try {
                    $this->mkdir($remote."/".$object);
                    $this->putDir($local.DIRECTORY_SEPARATOR.$object, $remote."/".$object, $mode, $failfast);
                } catch (FtpException $e) {
                    $error = true;
                    if ($failfast) {
                        throw $e;
                    }
                }
            } else {                                                                                            // object is a file
                try {
                    $this->put($local.DIRECTORY_SEPARATOR.$object, $remote."/".$object, $mode);
                } catch (FtpException $e) {
                    $error = true;
                    if ($failfast) {
                        throw $e;
                    }
                }
            }//end if
        }//end while
        closedir($dir);

        if ($error) {
            $this->debug(
                "copied folder with errors in ".round(microtime(true) - $start, 3)."s",
                __FUNCTION__
            );
            throw new FtpException("unable to copy folder to server");
        }

        $this->debug(
            "successfully copied folder in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        $this->time += microtime(true) - $start;
    }

    /**
     * gets a file from the server
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $local       the local file
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $nb          put the file not blocking on the server (ftp_nb_get())
     * @uses            Ftp::$ftp
     * @uses            Ftp::getMode()
     * @uses            Ftp::$time
     * @uses            Ftp::debug()
     * @uses            FTP_ASCII
     * @uses            FTP_BINARY
     */
    public function get(string $local, string $remote, int | string $mode = "auto", bool $nb = false) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        // get the upload mode
        if ($mode != FTP_ASCII && $mode != FTP_BINARY) {
            $mode = $this->getMode($remote);
        }

        $function = $nb ? "ftp_nb_get" : "ftp_get";

        $this->debug("downloading file using ".$function."()", __FUNCTION__);
        $caller = new FunctionCaller($function, $this->ftp, $local, $remote, (int) $mode);

        // download failed
        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not get file from server".$errmsg, __FUNCTION__);
            throw new FtpException("could not get file from server".$errmsg);
        }

        $this->debug(
            "file successfully downloaded in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * gets a file not blocking from the server
     *
     * this functions is just a 'link' to the get() function
     * which gets the file from the server. see the parameter
     * $nb in this function.
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $local       the local filepointer
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @uses            Ftp::fget()
     */
    public function nb_get(string $local, string $remote, int | string $mode = "auto") : void
    {
        $this->get($local, $remote, $mode, true);
    }

    /**
     * gets a file from the server and returs a filepointer
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           resource         $fp          the local filepointer
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $nb          put the file not blocking on the server (ftp_nb_fget())
     * @uses            Ftp::$ftp
     * @uses            Ftp::getMode()
     * @uses            Ftp::debug()
     * @uses            Ftp::$time
     * @uses            FTP_ASCII
     * @uses            FTP_BINARY
     * @uses            FTP_FAILED
     */
    public function fget($fp, string $remote, int | string $mode = "auto", bool $nb = false) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        if (!is_resource($fp)) {
            throw new \TypeError("\$fp is not a resource");
        }

        if ($mode != FTP_ASCII && $mode != FTP_BINARY) {
            $mode = FTP_ASCII;
        }

        $function = $nb ? "ftp_nb_fget" : "ftp_fget";

        $this->debug("downloading file using ".$function."()", __FUNCTION__);
        $caller = new FunctionCaller($function, $this->ftp, $fp, $remote, (int) $mode);

        // download failed
        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not get file from server".$errmsg, __FUNCTION__);
            throw new FtpException("could not get file from server".$errmsg);
        }

        $this->debug(
            "file successfully downloaded in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * gets a file not blocking from the server and returns a filepointer
     *
     * this functions is just a 'link' to the fget() function
     * which gets the file from the server. see the parameter
     * $nb in this function.
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           resource         $fp          the local filepointer
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @uses            Ftp::fget()
     */
    public function nb_fget($fp, string $remote, int | string $mode = "auto") : void
    {
        if (!is_resource($fp)) {
            throw new \TypeError("\$fp is not a resource");
        }

        $this->fget($fp, $remote, $mode, true);
    }

    /**
     * gets a directory with all contents from the server
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $local       the local directory
     * @param           string           $remote      the remote directory
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $failfast    whether to stop as soon as an error occurs
     * @uses            Ftp::dirList()
     * @uses            Ftp::getDir()
     * @uses            Ftp::get()
     */
    public function getDir(string $local, string $remote, int | string $mode = "auto", bool $failfast = true) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        if (file_exists($local) && !is_dir($local)) {
            $this->debug("local path '".$local."' is no directory", __FUNCTION__);
            throw new FtpException("local path '".$local."' is no directory");
        } elseif (!file_exists($local) && !mkdir($local)) {
            $this->debug("could not create local directory ".$local, __FUNCTION__);
            throw new FtpException("could not create local directory '".$local."'");
        }

        $error = false;

        $dir = $this->dirList($remote);
        foreach ($dir as $object) {
            if ($object['type'] === 1) {
                try {
                    mkdir($local.DIRECTORY_SEPARATOR.$object['name']);
                    $this->getDir(
                        $local.DIRECTORY_SEPARATOR.$object['name'],
                        $remote."/".$object['name'],
                        $mode,
                        $failfast
                    );
                } catch (FtpException $e) {
                    $error = true;
                    if ($failfast) {
                        throw $e;
                    }
                }
            } else {
                try {
                    $this->get(
                        $local.DIRECTORY_SEPARATOR.$object['name'],
                        $remote."/".$object['name'],
                        $mode
                    );
                } catch (FtpException $e) {
                    $error = true;
                    if ($failfast) {
                        throw $e;
                    }
                }
            }//end if
        }//end foreach

        if ($error) {
            $this->debug(
                "copied folder with errors in ".round(microtime(true) - $start, 3)."s",
                __FUNCTION__
            );
            throw new FtpException("unable to copy folder from server");
        }

        $this->debug(
            "successfully copied folder in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        $this->time += microtime(true) - $start;
    }

    /**
     * creates a directory
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $dir         the name of the directory
     * @uses            Ftp::$ftp
     * @uses            Ftp::$time
     */
    public function mkdir(string $dir) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("creating remote directory '".$dir."'", __FUNCTION__);

        $caller = new FunctionCaller("ftp_mkdir", $this->ftp, $dir);

        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not create remote directory '".$dir."'".$errmsg);
            throw new FtpException("could not create remote directory '".$dir."'".$errmsg);
        }

        $this->debug(
            "folder successfully created in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * changes to another directory
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $dir         the name of the directory
     * @uses            Ftp::$ftp
     * @uses            Ftp::debug()
     * @uses            Ftp::$time
     */
    public function chDir(string $dir) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("changing directory to '".$dir."'", __FUNCTION__);

        $caller = new FunctionCaller("ftp_chdir", $this->ftp, $dir);

        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not change the directory to '".$dir."'".$errmsg, __FUNCTION__);
            throw new FtpException("could not change the directory to '".$dir."'".$errmsg);
        }

        $this->debug(
            "folder successfully changed in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * changes to the directory up
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @uses            Ftp::$ftp
     * @uses            Ftp::debug()
     * @uses            Ftp::pwd()
     * @uses            Ftp::chDir()
     * @uses            Ftp::$time
     */
    public function cdup() : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("changing directory up", __FUNCTION__);

        $caller = new FunctionCaller("ftp_cdup", $this->ftp);
        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not change the directory".$errmsg, __FUNCTION__);
            throw new FtpException("could not change the directory".$errmsg);
        }

        $this->debug(
            "successfully changed directory in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * changes the access rights of a file/directory
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           int              $mode        the new access rights
     * @param           string           $filename    the filename
     * @uses            Ftp::$ftp
     * @uses            Ftp::site()
     * @uses            Ftp::$time
     * @uses            Ftp::debug()
     */
    public function chmod(int $mode, string $filename) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("changing the mode of '".$filename."' to '".$mode."'", __FUNCTION__);

        $caller = new FunctionCaller("ftp_chmod", $this->ftp, $mode, $filename);

        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not change the mode. trying with the SITE command".$errmsg, __FUNCTION__);

            try {
                $this->site("CHMOD ".$mode." ".$filename);
            } catch (FtpException $e) {
                $this->debug("could not change the mode of '".$filename."' to '".$mode."'", __FUNCTION__);
                throw new FtpException("could not change the mode of '".$filename."' to '".$mode."'", $e->getCode(), $e);
            }
        }

        $this->debug(
            "mode successfully changed in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * returns the current path
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @uses            Ftp::$ftp
     * @uses            Ftp::debug()
     * @uses            Ftp::$time
     */
    public function pwd() : string
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("getting remote directory", __FUNCTION__);

        $caller = new FunctionCaller("ftp_pwd", $this->ftp);
        $folder = $caller->getResult();

        if ($folder === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not get working directory".$errmsg, __FUNCTION__);
            throw new FtpException("could not get working directory".$errmsg);
        }

        $this->debug(
            "got current folder '".$folder."' in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;

        return $folder;
    }

    /**
     * renames a file or directory
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $from        the current name
     * @param           string           $to          the new name
     * @uses            Ftp::$ftp
     * @uses            Ftp::$time
     */
    public function rename(string $from, string $to) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("renaming '".$from."' to '".$to."'", __FUNCTION__);

        $caller = new FunctionCaller("ftp_rename", $this->ftp, $from, $to);

        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not rename '".$from."' to '".$to."'".$errmsg, __FUNCTION__);
            throw new FtpException("could not rename '".$from."' to '".$to."'".$errmsg);
        }

        $this->debug(
            "successfully renamed '".$from."' to '".$to."' in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * deletes directory on the server
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $remote      the folder to delete
     * @param           bool             $recursive   recursively delete the folder or not
     * @uses            Ftp::$ftp
     * @uses            Ftp::$time
     * @uses            Ftp::rawlist()
     * @uses            Ftp::delete()
     * @uses            Ftp::rmdir()
     * @uses            Ftp::$time
     */
    public function rmdir(string $remote, bool $recursive = false) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug(($recursive ? "recursively " : "")."deleting folder '".$remote."'", __FUNCTION__);

        if (!$recursive) {
            $caller = new FunctionCaller("ftp_rmdir", $this->ftp, $remote);

            if ($caller->getResult() === false) {
                $lastError = $caller->getLastError()?->getErrstr();
                $errmsg = $lastError !== null ? " (".$lastError.")" : "";
                $this->debug("could not remove the folder '".$remote."'".$errmsg, __FUNCTION__);
                throw new FtpException("could not remove the folder '".$remote."'".$errmsg);
            }

            // add time used
            $this->time += microtime(true) - $start;

            return;
        }

        // init
        $i = 0;
        $files = $folders = [];
        $statusnext = false;
        $currentfolder = $remote;

        // get raw file listing
        $list = ftp_rawlist($this->ftp, $remote, true);

        if ($list === false) {
            throw new FtpException("unable to get raw list");
        }

        // iterate listing
        foreach ($list as $current) {
            // an empty element means the next element will be the new folder
            if (empty($current)) {
                $statusnext = true;
                continue;
            }

            // save the current folder
            if ($statusnext === true) {
                $currentfolder = substr($current, 0, -1);
                $statusnext = false;
                continue;
            }

            // split the data into chunks
            $split = preg_split("[ ]", $current, 9, PREG_SPLIT_NO_EMPTY);
            if ($split === false) {
                continue;
            }

            $entry = $split[8];
            $isdir = $split[0][0] === "d";

            // skip pointers
            if ($entry === "." || $entry === "..") {
                continue;
            }

            // Build the file and folder list
            if ($isdir) {
                $folders[] = $currentfolder."/".$entry;
            } else {
                $files[] = $currentfolder."/".$entry;
            }
        }//end foreach

        // delete all the files
        foreach ($files as $file) {
            $this->delete($file);
        }

        // delete all the directories
        // reverse sort the folders so the deepest directories are unset first
        rSort($folders);
        foreach ($folders as $folder) {
            $this->rmdir($folder, $recursive);
        }

        // delete the final folder and return its status
        ftp_rmdir($this->ftp, $remote);

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * deletes file on the server
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $file      the file to delete
     * @uses            Ftp::$ftp
     * @uses            Ftp::$time
     */
    public function delete(string $file) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("deleting file '".$file."'", __FUNCTION__);

        $caller = new FunctionCaller("ftp_delete", $this->ftp, $file);

        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not delete the file '".$file."'".$errmsg, __FUNCTION__);
            throw new FtpException("could not delete the file '".$file."'".$errmsg);
        }

        $this->debug(
            "successfully deleted '".$file."' in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * returns the size of a file
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $filename    the name of the file
     * @uses            Ftp::$time
     */
    public function size(string $filename) : int
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("getting size of file '".$filename."'", __FUNCTION__);

        $caller = new FunctionCaller("ftp_size", $this->ftp, $filename);
        $size = $caller->getResult();

        if ($size === -1) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not get size of '".$filename."'".$errmsg, __FUNCTION__);
            throw new FtpException("could not get size of '".$filename."'".$errmsg);
        }

        $size = intval($size);

        $this->debug(
            "successfully got size of '".$filename."' in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;

        return $size;
    }

    /**
     * returns the size of a directory
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $dir         the name of the directory
     * @param           bool             $failfast    whether to stop as soon as an error occurs
     * @uses            Ftp::dirList()
     * @uses            Ftp::size()
     */
    public function dirSize(string $dir, bool $failfast = false) : int
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("getting size of folder '".$dir."'", __FUNCTION__);

        $size = 0;
        $error = false;

        $dirList = $this->dirList($dir);
        foreach ($dirList as $object) {
            if ($object['type'] == 1) {
                try {
                    $this->dirSize($dir."/".$object['name']);
                } catch (FtpException $e) {
                    $error = true;
                    if ($failfast) {
                        throw $e;
                    }
                }
            } else {
                try {
                    $size = $size + $this->size($dir."/".$object['name']);
                } catch (FtpException $e) {
                    $error = true;
                    if ($failfast) {
                        throw $e;
                    }
                }
            }
        }//end foreach

        if ($error) {
            $this->debug(
                "got size of folder with errors in ".round(microtime(true) - $start, 3)."s",
                __FUNCTION__
            );
            throw new FtpException("unable to get size of folder from server");
        }

        $this->debug(
            "successfully got size of folder '".$dir."' in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        return $size;
    }

    /**
     * enables/disables the passive mode
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           bool             $mode        passive mode on/off
     * @uses            Ftp::debug()
     * @uses            Ftp::$time
     */
    public function pasv(bool $mode) : void
    {
        $start = microtime(true);
        $this->sanityCheck(__FUNCTION__);

        $this->debug(($mode ? "enabling" : "disabling")." active mode", __FUNCTION__);

        if ($mode === $this->pasv) {
            $this->debug("nothing to do", __FUNCTION__);
            return;
        }

        $caller = new FunctionCaller("ftp_pasv", $this->ftp, $mode);
        $size = $caller->getResult();

        if ($size === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not switch mode".$errmsg, __FUNCTION__);
            throw new FtpException("could not switch mode".$errmsg);
        }

        $size = intval($size);

        $this->debug(
            "successfully switched mode in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * returns the date of the last modification from a file
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $filename    the name of the file
     * @uses            Ftp::$time
     */
    public function mdtm(string $filename) : int
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("getting last modification date from file '".$filename."'", __FUNCTION__);

        $caller = new FunctionCaller("ftp_mdtm", $this->ftp, $filename);
        $time = $caller->getResult();

        if ($time === -1) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not get last modification date from '".$filename."'".$errmsg, __FUNCTION__);
            throw new FtpException("could not get last modification date from '".$filename."'".$errmsg);
        }

        $this->debug(
            "successfully got last modification date '".date("c", $time)."' in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;

        return $time;
    }

    /**
     * sends a site command to the ftp server
     *
     * since site commands are not standardisized the command
     * will not be checked and just given to the ftp_site() function.
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $command     the command to send
     * @uses            Ftp::$time
     * @uses            Ftp::$ftp
     */
    public function site(string $command) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("sending site command '".$command."' to server", __FUNCTION__);

        $caller = new FunctionCaller("ftp_site", $this->ftp, $command);

        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not execute the command".$errmsg, __FUNCTION__);
            throw new FtpException("could not execute the command".$errmsg);
        }

        $this->debug(
            "successfully executed site command in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * sends a command to the ftp server
     *
     * since exec commands are not standardisized the command
     * will not be checked and just given to the ftp_exec() function.
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $command     the command to send
     * @uses            Ftp::$time
     * @uses            Ftp::$ftp
     */
    public function exec(string $command) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("sending exec command '".$command."' to server", __FUNCTION__);

        $caller = new FunctionCaller("ftp_exec", $this->ftp, $command);

        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not execute the command".$errmsg, __FUNCTION__);
            throw new FtpException("could not execute the command".$errmsg);
        }

        $this->debug(
            "successfully executed exec command in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * returns some information about the ftp connction
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           int             $option      the option to return
     * @return          mixed
     * @uses            Ftp::$time
     */
    public function get_option(int $option)
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("getting option '".$option."' from sever", __FUNCTION__);

        $caller = new FunctionCaller("ftp_get_option", $this->ftp, $option);
        $option = $caller->getResult();

        if ($option === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not get option from server".$errmsg, __FUNCTION__);
            throw new FtpException("could not get option from server".$errmsg);
        }

        $this->debug(
            "successfully got option in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;

        return $option;
    }

    /**
     * sets some information about the ftp connction
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           int             $option         the option to set
     * @param           mixed           $value          value of the option to set
     * @uses            Ftp::$time
     */
    public function set_option(int $option, $value) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("setting option '".$option."' to '".$value."'", __FUNCTION__);

        $caller = new FunctionCaller("ftp_set_option", $this->ftp, $option, $value);

        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not set option on server".$errmsg, __FUNCTION__);
            throw new FtpException("could not set option on server".$errmsg);
        }

        $this->debug(
            "successfully set option in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * closes the ftp-connection
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @uses            Ftp::debug()
     * @uses            Ftp::$ftp
     * @uses            Ftp::$time
     */
    public function close() : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("closing connection", __FUNCTION__);
        $caller = new FunctionCaller("ftp_close", $this->ftp);

        if ($caller->getResult() === false) {
            $lastError = $caller->getLastError()?->getErrstr();
            $errmsg = $lastError !== null ? " (".$lastError.")" : "";
            $this->debug("could not close the connection".$errmsg, __FUNCTION__);
            throw new FtpException("could not close the connection".$errmsg);
        }

        $this->debug(
            "connection closed in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * analyses a line returned by ftp_rawlist
     *
     * returns an assotive array with the following keys:
     *   type (type of the object)
     *    -1: invalid
     *     0: file
     *     1: folder
     *     2: link
     *
     *   size (filesize in bytes)
     *
     *   name (name of the file/folder)
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $dirline     one line
     * @return          array<string, int|string>
     * @uses            Ftp::$sysType
     */
    public function analyzeDir(string $dirline) : array
    {
        if (substr($dirline, 0, 5) == "total") {
            $this->debug("line begins with 'total'. invalid line", __FUNCTION__);
            $entry = [
                "type" => -1,
                "size" => 0,
                "name" => ""
            ];
        } elseif ($this->sysType == "Windows_NT") {
            $this->debug("server os is WINDOWS_NT", __FUNCTION__);
            if (preg_match("/[-0-9]+ *[0-9:]+[PA]?M? +<DIR> {10}(.*)/", $dirline, $regs)) {
                $this->debug("object (".$regs[1].") is a directory", __FUNCTION__);
                $entry = [
                    "type" => 1,
                    "size" => 0,
                    "name" => $regs[1] ?? ""
                ];
            } elseif (preg_match("/[-0-9]+ *[0-9:]+[PA]?M? +([0-9]+) (.*)/", $dirline, $regs)) {
                $this->debug("object (".$regs[2].") is a file", __FUNCTION__);
                $entry = [
                    "type" => 0,
                    "size" => intval($regs[1] ?? 0),
                    "name" => $regs[2] ?? ""
                ];
            } else {
                $this->debug("invalid line", __FUNCTION__);
                $entry = [
                    "type" => -1,
                    "size" => 0,
                    "name" => ""
                ];
            }//end if
        } elseif ($this->sysType == "UNIX") {
            $this->debug("server os is UNIX", __FUNCTION__);
            if (preg_match("/([-ld])[rwxst-]{9}.* ([0-9]*) [a-zA-Z]+ [0-9: ]*[0-9] (.+)/", $dirline, $regs)) {
                $entry = [
                    "type" => -1,
                    "size" => intval($regs[2] ?? 0),
                    "name" => $regs[3] ?? ""
                ];

                if (($regs[1] ?? "") === "d") {
                    $entry['type'] = 1;
                } elseif (($regs[1] ?? "") === "l") {
                    $entry['type'] = 2;
                    if (preg_match("/(.+) ->.*/", $entry['name'], $regs) && $regs !== false && isset($regs[1])) {
                        $entry['name'] = $regs[1];
                    }
                } else {
                    $entry['type'] = 1;
                }
            } else {
                $this->debug("invalid line", __FUNCTION__);
                $entry = [
                    "type" => -1,
                    "size" => 0,
                    "name" => ""
                ];
            }//end if
        } else {
            $this->debug("invalid line", __FUNCTION__);
            $entry = [
                "type" => -1,
                "size" => 0,
                "name" => ""
            ];
        }//end if

        $this->debug("filtering folder descriptors", __FUNCTION__);
        if ($entry['name'] === "." || $entry['name'] === "..") {
            $entry['type'] = 0;
        }

        return $entry;
    }

    /**
     * sets the password to use for anonymous users
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string          $password           password to use
     * @uses            Ftp::anonymousPassword()
     */
    public function setAnonymousPassword(string $password) : void
    {
        $this->anonymousPassword = $password;
    }

    /**
     * turns debugging on or off
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           bool            $debug              turn debug on or off
     * @uses            Ftp::$debug
     */
    public function setDebug(bool $debug = false) : void
    {
        $this->debug = $debug;
    }

    /**
     * returns the current state of debug
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @uses            Ftp::$debug
     */
    public function getDebug() : bool
    {
        return $this->debug;
    }

    /**
     * checks if a file has to be up-/downloaded binary or with ascii
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string           $file        the filename
     * @access          private
     * @uses            Ftp::$ascii
     * @uses            FTP_ASCII
     * @uses            FTP_BINARY
     */
    private function getMode(string $file) : int
    {
        // read the filending
        $this->debug("looking for the fileending on file '".$file."'", __FUNCTION__);
        if (!preg_match("/\.([a-z0-9]+)$/i", $file, $buffer)) {
            $this->debug("could not determine the fileending", __FUNCTION__);
            // no filending, use ascii as default
            return FTP_ASCII;
        }

        $ending = $buffer[1];
        $this->debug("fileending is '".$ending."'", __FUNCTION__);

        // check if fileending is ascii or binary
        if (in_array($ending, $this->ascii)) {
            $this->debug("ASCII transfer", __FUNCTION__);
            return FTP_ASCII;
        }

        $this->debug("BINARY transfer", __FUNCTION__);
        return FTP_BINARY;
    }

    /**
     * prints a debug message on the screen if required
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string          $message        the message to print
     * @param           string|null     $functionName   the name of the function calling this method
     * @access          private
     * @uses            Ftp::$debug
     * @uses            Ftp::$log
     */
    private function debug(string $message, ?string $functionName = null) : void
    {
        if (!$this->debug) {
            return;
        }

        $logString = $functionName !== null
            ? __CLASS__."->".$functionName."(): "
            : __CLASS__.": ";

        if ($this->log === null) {
            echo $logString.$message."\n";
            flush();
        } else {
            $this->log->write($logString.$message);
        }
    }

    /**
     * sanity check
     *
     * @author          David Lienhard <david@t-error.ch>
     * @copyright       t-error.ch
     * @param           string          $functionName       name of the function calling this method
     * @uses            Ftp::$ftp
     */
    private function sanityCheck(string $functionName) : void
    {
        if (!is_resource($this->ftp)) {
            $this->debug("\$this->ftp is no resource", $functionName);
            throw new FtpException("\$this->ftp is no resource");
        }
    }
}
