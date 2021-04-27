<?php
/**
 * contains \DavidLienhard\Ftp\FtpInterface
 *
 * @author          David Lienhard <github@lienhard.win>
 * @copyright       David Lienhard
 */

declare(strict_types=1);

namespace DavidLienhard\Ftp;

use \DavidLienhard\Log\LogInterface;

/**
 * contains methods for ftp transfers
 *
 * @author          David Lienhard <github@lienhard.win>
 * @copyright       David Lienhard
*/
interface FtpInterface
{
    /**
     * sets important variables
     *
     * checks if the php functions, the function microtime(true),
     * the classes socket and server exist.
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           bool                                    $debug  turn debugging on or off.
     * @param           \DavidLienhard\Log\LogInterface|null    $log    optional logging object for debugging
     * @return          void
     */
    public function __construct(bool $debug = false, LogInterface|null $log = null);

    /**
     * connects to the ftp-server
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string          $host           host to connect to
     * @param           string          $user           username to connect with
     * @param           string          $pass           password to connect with. or null to use anonymous password
     * @param           int             $port           port to connect to
     * @param           int             $timeout        timeout in seconds
     */
    public function connect(
        string $host,
        string $user,
        string|null $pass,
        int $port = 21,
        int $timeout = 30
    ) : void;

    /**
     * returns the content of a directory
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string          $dir        the directory
     * @return          array<int, array<string, int|string>>
     */
    public function dirList(string $dir = "./") : array;

    /**
     * returns the content of a directory
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string          $dir        the directory
     * @return          string[]
     */
    public function nList(string $dir = "./") : array;

    /**
     * puts a file on the server
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $local       the local file
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $nb          put the file not blocking on the server (ftp_nb_put())
     */
    public function put(string $local, string $remote, int|string $mode = "auto", bool $nb = false) : void;


    /**
     * puts a file not blocking on the server
     *
     * this functions is just a 'link' to the put() function
     * which puts the file on the server. see the parameter
     * $nb in this function.
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $local       the local file
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     */
    public function nb_put(string $local, string $remote, int|string $mode = "auto") : void;

    /**
     * puts a with {@link fopen()} opened file on the server
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           resource         $fp          the local filepointer
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $nb          put the file not blocking on the server (ftp_nb_fput())
     */
    public function fput($fp, string $remote, int|string $mode = "auto", bool $nb = false) : void;


    /**
     * puts a with {@link fopen()} opened file not blocking on the server
     *
     * this functions is just a 'link' to the fput() function
     * which puts the file on the server. see the parameter
     * $nb in this function.
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           resource         $fp          the local filepointer
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     */
    public function nb_fput($fp, string $remote, int|string $mode = "auto") : void;

    /**
     * puts a directory with all contents on the server
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $local       the local directory
     * @param           string           $remote      the remote directory
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $failfast    whether to stop as soon as an error occurs
     */
    public function putDir(
        string $local,
        string $remote,
        int|string $mode = "auto",
        bool $failfast = true
    ) : void;

    /**
     * gets a file from the server
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $local       the local file
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $nb          put the file not blocking on the server (ftp_nb_get())
     */
    public function get(
        string $local,
        string $remote,
        int|string $mode = "auto",
        bool $nb = false
    ) : void;

    /**
     * gets a file not blocking from the server
     *
     * this functions is just a 'link' to the get() function
     * which gets the file from the server. see the parameter
     * $nb in this function.
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $local       the local filepointer
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     */
    public function nb_get(
        string $local,
        string $remote,
        int|string $mode = "auto"
    ) : void;

    /**
     * gets a file from the server and returs a filepointer
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           resource         $fp          the local filepointer
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $nb          put the file not blocking on the server (ftp_nb_fget())
     */
    public function fget(
        $fp,
        string $remote,
        int|string $mode = "auto",
        bool $nb = false
    ) : void;

    /**
     * gets a file not blocking from the server and returns a filepointer
     *
     * this functions is just a 'link' to the fget() function
     * which gets the file from the server. see the parameter
     * $nb in this function.
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           resource         $fp          the local filepointer
     * @param           string           $remote      the remote file
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     */
    public function nb_fget(
        $fp,
        string $remote,
        int|string $mode = "auto"
    ) : void;

    /**
     * gets a directory with all contents from the server
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $local       the local directory
     * @param           string           $remote      the remote directory
     * @param           int|string       $mode        auto for autodetect or FTP_ASCII for ascii or FTP_BINARY for binary upload
     * @param           bool             $failfast    whether to stop as soon as an error occurs
     */
    public function getDir(
        string $local,
        string $remote,
        int|string $mode = "auto",
        bool $failfast = true
    ) : void;

    /**
     * creates a directory
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $dir         the name of the directory
     */
    public function mkdir(string $dir) : void;

    /**
     * changes to another directory
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $dir         the name of the directory
     */
    public function chDir(string $dir) : void;

    /**
     * changes to the directory up
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     */
    public function cdup() : void;

    /**
     * changes the access rights of a file/directory
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           int              $mode        the new access rights
     * @param           string           $filename    the filename
     */
    public function chmod(int $mode, string $filename) : void;

    /**
     * returns the current path
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     */
    public function pwd() : string;

    /**
     * renames a file or directory
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $from        the current name
     * @param           string           $to          the new name
     */
    public function rename(string $from, string $to) : void;

    /**
     * deletes directory on the server
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $remote      the folder to delete
     * @param           bool             $recursive   recursively delete the folder or not
     */
    public function rmdir(string $remote, bool $recursive = false) : void;

    /**
     * deletes file on the server
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $file      the file to delete
     */
    public function delete(string $file) : void;

    /**
     * returns the size of a file
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $filename    the name of the file
     */
    public function size(string $filename) : int;

    /**
     * returns the size of a directory
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $dir         the name of the directory
     * @param           bool             $failfast    whether to stop as soon as an error occurs
     */
    public function dirSize(string $dir, bool $failfast = false) : int;

    /**
     * enables/disables the passive mode
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           bool             $mode        passive mode on/off
     */
    public function pasv(bool $mode) : void;

    /**
     * returns the date of the last modification from a file
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $filename    the name of the file
     */
    public function mdtm(string $filename) : int;

    /**
     * sends a site command to the ftp server
     *
     * since site commands are not standardisized the command
     * will not be checked and just given to the ftp_site() function.
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $command     the command to send
     */
    public function site(string $command) : void;

    /**
     * sends a command to the ftp server
     *
     * since exec commands are not standardisized the command
     * will not be checked and just given to the ftp_exec() function.
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $command     the command to send
     */
    public function exec(string $command) : void;

    /**
     * returns some information about the ftp connction
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           int             $option      the option to return
     */
    public function get_option(int $option): mixed;

    /**
     * sets some information about the ftp connction
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           int             $option         the option to set
     * @param           mixed           $value          value of the option to set
     */
    public function set_option(int $option, mixed $value) : void;

    /**
     * closes the ftp-connection
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     */
    public function close() : void;

    /**
     * analyses a line returned by ftp_rawlist
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string           $dirline     one line
     * @return          string[]
     */
    public function analyzeDir(string $dirline) : array;

    /**
     * sets the password to use for anonymous users
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           string          $password           password to use
     */
    public function setAnonymousPassword(string $password) : void;

    /**
     * turns debugging on or off
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     * @param           bool            $debug              turn debug on or off
     */
    public function setDebug(bool $debug = false) : void;

    /**
     * returns the current state of debug
     *
     * @author          David Lienhard <github@lienhard.win>
     * @copyright       David Lienhard
     */
    public function getDebug() : bool;
}
