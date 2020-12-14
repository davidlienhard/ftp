<?php
/**
 * contains \DavidLienhard\Ftp\Ftp class
 *
 * @package         tourBase
 * @author          David Lienhard <david.lienhard@tourasia.ch>
 * @version         1.0.0, 14.12.2020
 * @since           1.0.0, 14.12.2020, created
 * @copyright       tourasia
 */

declare(strict_types=1);

namespace DavidLienhard\Ftp;

use \DavidLienhard\Log\LogInterface;
use \DavidLienhard\Ftp\Exceptions\FtpException as FtpException;
use \DavidLienhard\Ftp\Exceptions\ConnectException as FtpConnectException;
use \DavidLienhard\Ftp\Exceptions\LoginException as FtpLoginException;

/**
 * contains methods for ftp transfers
 *
 * this functions mainly contains the standard php-ftp functions.
 * but there are also some additional functions/parameters making
 * the use of ftp functions easier.
 *
 * @author          David Lienhard <david.lienhard@tourasia.ch>
 * @version         1.0.0, 14.12.2020
 * @since           1.0.0, 14.12.2020, created
 * @copyright       tourasia
*/
class Ftp implements FtpInterface
{
    /**
     * optional logging object to use for debugging
     * @var     \DavidLienhard\Log\LogInterface
     */
    private $log;

    /**
     * the type of operating system
     * @var         string
     */
    private $sysType;

    /**
     * the password to use for anyonymous connections
     * @var         string
     */
    private $anonymousPassword = "qwertz@anonymous.net";

    /**
     * all file-endings for which the ascii upload should be used
     * @var         array
     */
    private $ascii = [
        "asp", "bat", "c", "ccp", "csv", "h", "htm", "html",
        "shtml", "ini", "log", "php", "pl", "perl",
        "sh", "sql", "txt", "cgi", "xml", "lock",
        "json", "xml", "yml"
    ];

    /**
     * the time used for ftp connections
     * @var         float
     */
    private $time = 0;

    /**
     * the hostname to connect to
     * @var         string
     */
    private $host;

    /**
     * the port to connect to
     * @var         int
     */
    private $port;

    /**
     * the connection timeout
     * @var         int
     */
    private $timeout;

    /**
     * the username to login with
     * @var         string
     */
    private $user;

    /**
     * the password to login with
     * @var         string
     */
    private $pass;

    /**
     * the range to calculate the portnumber
     * for active connections if the php functions
     * are not enabled.
     * @var         string
     */
    private $portrange = [ 150, 200 ];

    /**
     * the ip of this server (the php client)
     * used for active connections without
     * the php functions
     * @var         string
     */
    private $ip = null;

    /**
     * passive mode.
     * true = on
     * false = off
     * @var         bool
     */
    private $pasv = false;

    /**
     * print debug messages or not
     * @var         bool
     */
    private $debug = false;

    /**
     * The ftp connection resource
     * @var         resource
     */
    private $ftp;


    /**
     * sets important variables
     *
     * checks if the php functions, the function microtime(true),
     * the classes socket and server exist.
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           bool                                    $debug  turn debugging on or off.
     * @param           \DavidLienhard\Log\LogInterface|null    $log    optional logging object for debugging
     * @return          void
     * @uses            Ftp::$debug
     * @uses            Ftp::$log
     */
    public function __construct(bool $debug = false, ?LogInterface $log = null)
    {
        $this->debug = $debug;
        $this->log = $log;
    }

    /**
     * connects to the ftp-server
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string          $host           host to connect to
     * @param           string          $user           username to connect with
     * @param           string          $pass           password to connect with. or null to use anonymous password
     * @param           int             $port           port to connect to
     * @param           int             $timeout        timeout in seconds
     * @return          void
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
        $this->ftp = @ftp_connect($host, $port);

        // connection failed
        if ($this->ftp === false) {
            $this->debug("connection failed", __FUNCTION__);
            throw new FtpConnectException("could not connect to the host");
        }

        // login
        $this->debug("logging in with '".$user."'", __FUNCTION__);
        $x = @ftp_login($this->ftp, $user, $pass);

        // login failed
        if ($x === false) {
            $this->debug("could not login", __FUNCTION__);
            throw new FtpLoginException("unable to login");
        }

        // set servers system type
        $this->sysType = @ftp_systype($this->ftp);


        // add time used
        $this->time += microtime(true) - $start;

        $this->debug("connection successful in ".round(microtime(true) - $start, 3)."s", __FUNCTION__);
    }

    /**
     * returns the content of a directory
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string          $dir        the directory
     * @return          array
     * @uses            Ftp::$ftp
     * @uses            Ftp::analyzeDir()
     * @uses            Ftp::debug()
     */
    public function dirList(string $dir = "./") : array
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("retrieving raw list for folder '".$dir."'", __FUNCTION__);
        $list = @ftp_rawlist($this->ftp, $dir);

        if ($list === false) {
            $this->debug("could not get rawlist", __FUNCTION__);
            throw new FtpException("could not get rawlist");
        }

        $newlist = [ ];
        for ($i = 0; $i < count($list); $i++) {
            $entry = null;
            $buffer = $this->analyzeDir($list[$i]);
            if ($buffer[0] == 0 || $buffer[0] == 2) {       // file and link
                $entry = $buffer[2];
                $newlist[] = [ $entry, 0 ];
            } elseif ($buffer[0] == 1) {                    // directory
                $entry = $buffer[2];
                $newlist[] = [ $entry, 1 ];
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string          $dir        the directory
     * @return          array
     * @uses            Ftp::$ftp
     * @uses            Ftp::debug()
     */
    public function nList(string $dir = "./") : array
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("retrieving list for folder '".$dir."'", __FUNCTION__);
        $list = @ftp_nlist($this->ftp, $dir);

        if ($list === false) {
            $this->debug("could not get directory list from server", __FUNCTION__);
            throw new FtpException("could not get directory list from server");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $local       the local file
     * @param           string           $remote      the remote file
     * @param           mixed            $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $nb          put the file not blocking on the server (ftp_nb_put())
     * @return          void
     * @uses            Ftp::$ftp
     * @uses            Ftp::getMode()
     * @uses            Ftp::debug()
     * @uses            FTP_ASCII
     * @uses            FTP_BINARY
     * @uses            FTP_FAILED
     */
    public function put(string $local, string $remote, $mode = "auto", bool $nb = false) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        // sanity check
        if (!file_exists($local)) {
            $this->debug("local file '".$local."' does not exist", __FUNCTION__);
            throw new FtpException("local file '".$local."' does not exist", __FUNCTION__);
        }

        // get transfer mode
        if ($mode !== FTP_ASCII && $mode !== FTP_BINARY) {
            $mode = $this->getMode($local);
        }

        $function = $nb ? "ftp_nb_put" : "ftp_put";

        $this->debug("uploading file '".$local."' to '".$remote."' using ".$function."()", __FUNCTION__);
        $result = @$function($this->ftp, $remote, $local, $mode);

        if ($result === false || $result === FTP_FAILED) {
            $errmsg = error_get_last();
            $errmsg = isset($errmsg['message']) ? " (".$errmsg['message'].")" : "";
            $this->debug("could not put file (".$local.") on server".$errmsg, __FUNCTION__);
            throw new FtpException("could not put file (".$local.") on server");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $local       the local file
     * @param           string           $remote      the remote file
     * @param           bool             $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @return          void
     * @uses            Ftp::put()
     */
    public function nb_put(string $local, string $remote, $mode = "auto") : void
    {
        $this->put($local, $remote, $mode, true);
    }

    /**
     * puts a with {@link fopen()} opened file on the server
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           resource         $fp          the local filepointer
     * @param           string           $remote      the remote file
     * @param           mixed            $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $nb          put the file not blocking on the server (ftp_nb_fput())
     * @return          void
     * @uses            Ftp::$ftp
     * @uses            Ftp::getMode()
     * @uses            Ftp::debug()
     */
    public function fput($fp, string $remote, $mode = "auto", bool $nb = false) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        if (!is_resource($fp)) {
            throw new \TypeError("\$fp is not a resource");
        }

        // get the upload mode (ascii|binary)
        if ($mode != FTP_ASCII && $mode != FTP_BINARY) {
            // $mode = $this->getMode($local);
            $mode = FTP_ASCII;
        }

        $function = $nb ? "ftp_nb_fput" : "ftp_fput";

        $this->debug("uploading file using ".$function."()", __FUNCTION__);
        $result = @$function($this->ftp, $fp, $remote, $mode);

        if ($result === false || $result === FTP_FAILED) {
            $this->debug("could not put file on server", __FUNCTION__);
            throw new FtpException("could not put file on server");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           resource         $fp          the local filepointer
     * @param           string           $remote      the remote file
     * @param           bool             $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @return          void
     * @uses            Ftp::fput()
     */
    public function nb_fput($fp, string $remote, $mode = "auto") : void
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $local       the local directory
     * @param           string           $remote      the remote directory
     * @param           mixed            $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $failfast    whether to stop as soon as an error occurs
     * @return          void
     * @uses            Ftp::mkdir()
     * @uses            Ftp::putDir()
     * @uses            Ftp::put()
     */
    public function putDir(string $local, string $remote, $mode = "auto", $failfast = true) : void
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
        $dir = openDir($local);

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
            }
        }
        closeDir($dir);

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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $local       the local file
     * @param           string           $remote      the remote file
     * @param           mixed            $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $nb          put the file not blocking on the server (ftp_nb_get())
     * @return          void
     * @uses            Ftp::$ftp
     * @uses            Ftp::getMode()
     * @uses            Ftp::$time
     * @uses            Ftp::debug()
     * @uses            FTP_ASCII
     * @uses            FTP_BINARY
     */
    public function get(string $local, string $remote, $mode = "auto", bool $nb = false) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        // get the upload mode
        if ($mode != FTP_ASCII && $mode != FTP_BINARY) {
            $mode = $this->getMode($remote);
        }

        $function = $nb ? "ftp_nb_get" : "ftp_get";

        $this->debug("downloading file using ".$function."()", __FUNCTION__);
        $result = @$function($this->ftp, $local, $remote, $mode);

        // download failed
        if ($result === false || $result === FTP_FAILED) {
            $this->debug("could not get file from server", __FUNCTION__);
            throw new FtpException("could not get file from server");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $local       the local filepointer
     * @param           string           $remote      the remote file
     * @param           bool             $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @return          void
     * @uses            Ftp::fget()
     */
    public function nb_get(string $local, string $remote, $mode = "auto") : void
    {
        $this->get($local, $remote, $mode, true);
    }

    /**
     * gets a file from the server and returs a filepointer
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           resource         $fp          the local filepointer
     * @param           string           $remote      the remote file
     * @param           mixed            $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $nb          put the file not blocking on the server (ftp_nb_fget())
     * @return          void
     * @uses            Ftp::$ftp
     * @uses            Ftp::getMode()
     * @uses            Ftp::debug()
     * @uses            Ftp::$time
     * @uses            FTP_ASCII
     * @uses            FTP_BINARY
     * @uses            FTP_FAILED
     */
    public function fget($fp, string $remote, $mode = "auto", bool $nb = false) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        if (!is_resource($fp)) {
            throw new \TypeError("\$fp is not a resource");
        }

        if ($mode != FTP_ASCII && $mode != FTP_BINARY) {
            // $mode = $this->getMode($remote);
            $mode = FTP_ASCII;
        }

        $function = $nb ? "ftp_nb_fget" : "ftp_fget";

        $this->debug("downloading file using ".$function."()", __FUNCTION__);
        $result = @ftp_fget($this->ftp, $fp, $remote, $mode);

        // download failed
        if ($result === false || $result === FTP_FAILED) {
            $this->debug("could not get file from server", __FUNCTION__);
            throw new FtpException("could not get file from server");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           resource         $fp          the local filepointer
     * @param           string           $remote      the remote file
     * @param           mixed            $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @return          void
     * @uses            Ftp::fget()
     */
    public function nb_fget($fp, string $remote, $mode = "auto") : void
    {
        if (!is_resource($fp)) {
            throw new \TypeError("\$fp is not a resource");
        }

        $this->fget($local, $remote, $mode, true);
    }

    /**
     * gets a directory with all contents from the server
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $local       the local directory
     * @param           string           $remote      the remote directory
     * @param           mixed            $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $failfast    whether to stop as soon as an error occurs
     * @return          void
     * @uses            Ftp::dirList()
     * @uses            Ftp::getDir()
     * @uses            Ftp::get()
     */
    public function getDir(string $local, string $remote, $mode = "auto", $failfast = true) : void
    {
        $start = microtime(true);

        $this->sanityCheck();

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
            if ($object[1] == 1) {
                try {
                    mkdir($local . DIRECTORY_SEPARATOR . $object);
                    $this->getDir($local . DIRECTORY_SEPARATOR . $object, $remote."/".$object, $mode, $failfast);
                } catch (FtpException $e) {
                    $error = true;
                    if ($failfast) {
                        throw $e;
                    }
                }
            } else {
                try {
                    $this->get($local . DIRECTORY_SEPARATOR . $object, $remote."/".$object, $mode);
                } catch (FtpException $e) {
                    $error = true;
                    if ($failfast) {
                        throw $e;
                    }
                }
            }
        }

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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $dir         the name of the directory
     * @return          void
     * @uses            Ftp::$ftp
     * @uses            Ftp::$time
     */
    public function mkdir(string $dir) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("creating remote directory '".$dir."'", __FUNCTION__);

        $result = @ftp_mkdir($this->ftp, $dir);

        if ($result == false) {
            $this->debug("could not create remote directory '".$dir."'");
            throw new FtpException("could not create remote directory '".$dir."'");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $dir         the name of the directory
     * @return          void
     * @uses            Ftp::$ftp
     * @uses            Ftp::debug()
     * @uses            Ftp::$time
     */
    public function chDir(string $dir) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("changing directory to '".$dir."'", __FUNCTION__);

        if (!@ftp_chdir($this->ftp, $dir)) {
            $this->debug("could not change the directory to '".$dir."'", __FUNCTION__);
            throw new FtpException("could not change the directory to '".$dir."'");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @return          void
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

        $buffer = @ftp_cdup($this->ftp);
        if ($buffer === false) {
            $this->debug("could not change the directory", __FUNCTION__);
            throw new FtpException("could not change the directory");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           int              $mode        the new access rights
     * @param           string           $filename    the filename
     * @return          void
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

        if (@ftp_chmod($this->ftp, $mode, $filename) === false) {
            $this->debug("could not change the mode. trying with the SITE command", __FUNCTION__);

            if ($this->site($this->ftp, "CHMOD ".$mode." ".$filename) === false) {
                $this->debug("could not change the mode of '".$filename."' to '".$mode."'", __FUNCTION__);
                throw new FtpException("could not change the mode of '".$filename."' to '".$mode."'");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @return          string
     * @uses            Ftp::$ftp
     * @uses            Ftp::debug()
     * @uses            Ftp::$time
     */
    public function pwd() : string
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("getting remote directory", __FUNCTION__);

        $folder = @ftp_pwd($this->ftp);

        if ($folder === false) {
            $this->debug("could not get working directory", __FUNCTION__);
            throw new FtpException("could not get working directory");
        }

        $this->debug(
            "got current folder '".$folder."' in ".round(microtime(true) - $start, 3)."s",
            __FUNCTION__
        );

        // add time used
        $this->time += microtime(true) - $start;

        return $path;
    }

    /**
     * renames a file or directory
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $from        the current name
     * @param           string           $to          the new name
     * @return          void
     * @uses            Ftp::$ftp
     * @uses            Ftp::$time
     */
    public function rename(string $from, string $to) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("renaming '".$from."' to '".$to."'", __FUNCTION__);

        $buffer = @ftp_rename($this->ftp, $from, $to);

        if ($buffer === false) {
            $this->debug("could not rename '".$from."' to '".$to."'", __FUNCTION__);
            throw new FtpException("could not rename '".$from."' to '".$to."'");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $remote      the folder to delete
     * @param           bool             $recursive   recursively delete the folder or not
     * @return          void
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

        if (!$recusive) {
            $buffer = @ftp_rmdir($this->ftp, $remote);

            if ($buffer === false) {
                $this->debug("could not remove the folder '".$remote."'", __FUNCTION__);
                throw new FtpException("could not remove the folder '".$remote."'");
            }

            // add time used
            $this->time += microtime(true) - $start;

            return;
        }

        // init
        $i = 0;
        $files = $folders = [ ];
        $statusnext = false;
        $currentfolder = $directory;

        // get raw file listing
        $list = ftp_rawlist($this->ftp, $remote, true);

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
        }

        // delete all the files
        foreach ($files as $file) {
            $this->delete($file);
        }

        // delete all the directories
        // reverse sort the folders so the deepest directories are unset first
        rSort($folders);
        foreach ($folders as $folder) {
            $this->rmdir($this->ftp, $folder);
        }

        // delete the final folder and return its status
        $buffer = ftp_rmDir($this->ftp, $directory);

        // add time used
        $this->time += microtime(true) - $start;
    }

    /**
     * deletes file on the server
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $file      the file to delete
     * @return          void
     * @uses            Ftp::$ftp
     * @uses            Ftp::$time
     */
    public function delete(string $file) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("deleting file '".$file."'", __FUNCTION__);

        $buffer = @ftp_delete($this->ftp, $file);

        if ($buffer === false) {
            $this->debug("could not delete the file '".$file."'", __FUNCTION__);
            throw new FtpException("could not delete the file '".$file."'");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $filename    the name of the file
     * @return          int
     * @uses            Ftp::$time
     */
    public function size(string $filename) : int
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("getting size of file '".$filename."'", __FUNCTION__);

        $buffer = @ftp_size($this->ftp, $filename);

        if ($buffer === false) {
            $this->debug("could not get size of '".$from."'", __FUNCTION__);
            throw new FtpException("could not get size of '".$from."'");
        }

        $size = intval($buffer);

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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $dir         the name of the directory
     * @param           bool             $failfast    whether to stop as soon as an error occurs
     * @return          int
     * @uses            Ftp::dirList()
     * @uses            Ftp::size()
     */
    public function dirSize(string $dir, $failfast = false) : int
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("getting size of folder '".$dir."'", __FUNCTION__);

        $size = 0;
        $error = false;

        $dirList = $this->dirList($dir);
        foreach ($dirList as $object) {
            if ($object[1] == 1) {
                try {
                    $this->dirSize($dir."/".$object);
                } catch (FtpException $e) {
                    $error = true;
                    if ($failfast) {
                        throw $e;
                    }
                }
            } else {
                try {
                    $size = $size + $this->size($dir."/".$object);
                } catch (FtpException $e) {
                    $error = true;
                    if ($failfast) {
                        throw $e;
                    }
                }
            }
        }

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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           bool             $mode        passive mode on/off
     * @return          void
     * @uses            Ftp::debug()
     * @uses            Ftp::$time
     */
    public function pasv(bool $mode) : void
    {
        $start = microtime(true);
        $this->sanityCheck(__FUNCTION__);

        $this->debug(($mode ? "enabling" : "disabling") . " active mode", __FUNCTION__);

        if ($mode === $this->pasv) {
            $this->debug("nothing to do", __FUNCTION__);
            return;
        }

        $buffer = @ftp_pasv($this->ftp, $mode);

        if ($buffer === false) {
            $this->debug("could not switch mode", __FUNCTION__);
            throw new FtpException("could not switch mode");
        }

        $size = intval($buffer);

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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $filename    the name of the file
     * @return          int
     * @uses            Ftp::$time
     */
    public function mdtm(string $filename) : int
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("getting last modification date from file '".$filename."'", __FUNCTION__);

        $time = @ftp_mdtm($this->ftp, $filename);

        if ($time === -1) {
            $this->debug("could not get last modification date from '".$filename."'", __FUNCTION__);
            throw new FtpException("could not get last modification date from '".$filename."'");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $command     the command to send
     * @return          void
     * @uses            Ftp::$time
     * @uses            Ftp::$ftp
     */
    public function site(string $command) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("sending site command '".$command."' to server", __FUNCTION__);

        $buffer = @ftp_site($this->ftp, $command);

        if ($buffer === false) {
            $this->debug("could not execute the command", __FUNCTION__);
            throw new FtpException("could not execute the command");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $command     the command to send
     * @return          void
     * @uses            Ftp::$time
     * @uses            Ftp::$ftp
     */
    public function exec(string $command) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("sending exec command '".$command."' to server", __FUNCTION__);

        $buffer = @ftp_exec($this->ftp, $command);

        if ($buffer === false) {
            $this->debug("could not execute the command", __FUNCTION__);
            throw new FtpException("could not execute the command");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
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

        $option = @ftp_get_option($this->ftp, $option);

        if ($option === false) {
            $this->debug("could not get option from server", __FUNCTION__);
            throw new FtpException("could not get option from server");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           int             $option         the option to set
     * @param           mixed           $value          value of the option to set
     * @return          void
     * @uses            Ftp::$time
     */
    public function set_option(int $option, $value) : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("setting option '".$option."' to '".$value."'", __FUNCTION__);

        $buffer = @ftp_set_option($this->ftp, $option, $value);

        if ($option === false) {
            $this->debug("could not set option on server", __FUNCTION__);
            throw new FtpException("could not set option on server");
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @return          void
     * @uses            Ftp::debug()
     * @uses            Ftp::$ftp
     * @uses            Ftp::$time
     */
    public function close() : void
    {
        $start = microtime(true);

        $this->sanityCheck(__FUNCTION__);

        $this->debug("closing connection", __FUNCTION__);
        $buffer = @ftp_close($this->ftp);

        if ($buffer === false) {
            $this->debug("could not close the connection", __FUNCTION__);
            throw new FtpException("could not close the connection");
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
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $dirline     one line
     * @return          array
     * @access          private
     * @uses            Ftp::$sysType
     */
    public function analyzeDir($dirline)
    {
        /* if (ereg("([-dl])[rwxst-]{9}",substr($dirline,0,10)))
        {
            $this->sysType="UNIX";
        } */

        if (substr($dirline, 0, 5) == "total") {
            $this->debug("line begins with 'total'. invalid line", __FUNCTION__);
            $entry[0] = -1;
        } elseif ($this->sysType == "Windows_NT") {
            $this->debug("server os is WINDOWS_NT", __FUNCTION__);
            if (ereg("[-0-9]+ *[0-9:]+[PA]?M? +<DIR> {10}(.*)", $dirline, $regs)) {
                $this->debug("object (".$regs[1].") is a directory", __FUNCTION__);
                $entry[0] = 1;
                $entry[1] = 0;
                $entry[2] = $regs[1];
            } elseif (ereg("[-0-9]+ *[0-9:]+[PA]?M? +([0-9]+) (.*)", $dirline, $regs)) {
                $this->debug("object (".$regs[2].") is a file", __FUNCTION__);
                $entry[0] = 0;
                $entry[1] = $regs[1];
                $entry[2] = $regs[2];
            }
        } elseif ($this->sysType == "UNIX") {
            $this->debug("server os is UNIX", __FUNCTION__);
            if (ereg("([-ld])[rwxst-]{9}.* ([0-9]*) [a-zA-Z]+ [0-9: ]*[0-9] (.+)", $dirline, $regs)) {
                if ($regs[1] == "d") {
                    $entry[0] = 1;
                }

                $entry[1] = $regs[2];
                $entry[2] = $regs[3];

                if ($regs[1] == "l") {
                    $entry[0] = 2;
                    if (ereg("(.+) ->.*", $entry[2], $regs)) {
                        $entry[2] = $regs[1];
                    }
                } else {
                    $entry[0] = 1;
                }
            }
        }

        $this->debug("filtering folder descriptors", __FUNCTION__);
        if (($entry[2] == ".") || ($entry[2] == "..")) {
            $entry[0] = 0;
        }

        return $entry;
    }

    /**
     * sets the password to use for anonymous users
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string          $password           password to use
     * @return          void
     * @uses            Ftp::anonymousPassword()
     */
    public function setAnonymousPassword(string $password) : void
    {
        $this->anonymousPassword($password);
    }

    /**
     * turns debugging on or off
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           bool            $debug              turn debug on or off
     * @return          void
     * @uses            Ftp::$debug
     */
    public function setDebug($debug = false) : void
    {
        $this->debug = $debug;
    }

    /**
     * returns the current state of debug
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @return          bool
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $file        the filename
     * @return          int
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string          $message        the message to print
     * @param           string|null     $functionName   the name of the function calling this method
     * @return          void
     * @access          private
     * @uses            Ftp::$debug
     * @uses            Ftp::$log
     */
    private function debug(string $message, ?string $functionName = null) : void
    {
        if (!$this->debug) {
            return;
        }

        $logString = $functionName !== false
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
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string          $functionName       name of the function calling this method
     * @return          void
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
