<?php
/**
 * contains \DavidLienhard\Ftp\FtpInterface
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
 * @author          David Lienhard <david.lienhard@tourasia.ch>
 * @version         1.0.0, 14.12.2020
 * @since           1.0.0, 14.12.2020, created
 * @copyright       tourasia
*/
interface FtpInterface
{
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
     */
    public function __construct(bool $debug = false, ?LogInterface $log = null);

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
     */
    public function connect(
        string $host,
        string $user,
        ?string $pass,
        int $port = 21,
        int $timeout = 30
    ) : void;

    /**
     * returns the content of a directory
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string          $dir        the directory
     * @return          array
     */
    public function dirList(string $dir = "./") : array;

    /**
     * returns the content of a directory
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string          $dir        the directory
     * @return          array
     */
    public function nList(string $dir = "./") : array;

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
     */
    public function put(string $local, string $remote, $mode = "auto", bool $nb = false) : void;


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
     */
    public function nb_put(string $local, string $remote, $mode = "auto") : void;

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
     */
    public function fput($fp, string $remote, $mode = "auto", bool $nb = false) : void;


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
     */
    public function nb_fput($fp, string $remote, $mode = "auto") : void;

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
     */
    public function putDir(string $local, string $remote, $mode = "auto", $failfast = true) : void;

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
     */
    public function get(string $local, string $remote, $mode = "auto", bool $nb = false) : void;

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
     */
    public function nb_get(string $local, string $remote, $mode = "auto") : void;

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
     */
    public function fget($fp, string $remote, $mode = "auto", bool $nb = false) : void;

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
     */
    public function nb_fget($fp, string $remote, $mode = "auto") : void;

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
     */
    public function getDir(string $local, string $remote, $mode = "auto", $failfast = true) : void;

    /**
     * creates a directory
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $dir         the name of the directory
     * @return          void
     */
    public function mkdir(string $dir) : void;

    /**
     * changes to another directory
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $dir         the name of the directory
     * @return          void
     */
    public function chDir(string $dir) : void;

    /**
     * changes to the directory up
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @return          void
     */
    public function cdup() : void;

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
     */
    public function chmod(int $mode, string $filename) : void;

    /**
     * returns the current path
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @return          string
     */
    public function pwd() : string;

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
     */
    public function rename(string $from, string $to) : void;

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
     */
    public function rmdir(string $remote, bool $recursive = false) : void;

    /**
     * deletes file on the server
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $file      the file to delete
     * @return          void
     */
    public function delete(string $file) : void;

    /**
     * returns the size of a file
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $filename    the name of the file
     * @return          int
     */
    public function size(string $filename) : int;

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
     */
    public function dirSize(string $dir, $failfast = false) : int;

    /**
     * enables/disables the passive mode
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           bool             $mode        passive mode on/off
     * @return          void
     */
    public function pasv(bool $mode) : void;

    /**
     * returns the date of the last modification from a file
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string           $filename    the name of the file
     * @return          int
     */
    public function mdtm(string $filename) : int;

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
     */
    public function site(string $command) : void;

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
     */
    public function exec(string $command) : void;

    /**
     * returns some information about the ftp connction
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           int             $option      the option to return
     * @return          mixed
     */
    public function get_option(int $option);

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
     */
    public function set_option(int $option, $value) : void;

    /**
     * closes the ftp-connection
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @return          void
     */
    public function close() : void;

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
     */
    public function analyzeDir($dirline);

    /**
     * sets the password to use for anonymous users
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           string          $password           password to use
     * @return          void
     */
    public function setAnonymousPassword(string $password) : void;

    /**
     * turns debugging on or off
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @param           bool            $debug              turn debug on or off
     * @return          void
     */
    public function setDebug($debug = false) : void;

    /**
     * returns the current state of debug
     *
     * @author          David Lienhard <david@t-error.ch>
     * @version         1.0.0, 14.12.2020
     * @since           1.0.0, 14.12.2020, created
     * @copyright       t-error.ch
     * @return          bool
     */
    public function getDebug() : bool;
}
