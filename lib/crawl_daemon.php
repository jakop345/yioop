<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010, 2011  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */
 
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Load system-wide defines
 */
require_once BASE_DIR."/configs/config.php";
/**
 * Load the crawlLog function
 */
require_once BASE_DIR."/lib/utility.php"; 
/**
 *  Load common constants for crawling
 */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Used to run scripts as a daemon on *nix systems
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 */
class CrawlDaemon implements CrawlConstants
{

    /**
     * Name prefix to be used on files associated with this daemon
     * (such as lock like and messages)
     * @var string
     * @static
     */
    static $name;

    /**
     * Callback function to handle signals sent to this daemon
     *
     * @param int $signo signal sent to the daemon
     */
    static function processHandler($signo)
    {
         switch ($signo) 
         {
             case SIGTERM:
                 // handle shutdown tasks
                 $info = array();
                 $info[self::STATUS] = self::STOP_STATE;
                 file_put_contents(
                    CRAWL_DIR."/schedules/".self::$name."_messages.txt", 
                    serialize($info));
                 unlink(CRAWL_DIR."/schedules/".self::$name."_lock.txt"); 
             break;

             case SIGSEGV:
                 // handle shutdown tasks
                crawlLog(
                    "Segmentation Fault Caught!! Debug back trace follows:");
                crawlLog(var_dump(debug_backtrace(), true));
             break;

         }
    }

    /**
     * Used to send a message the given daemon or run the program in the
     * foreground.
     *
     * @param array $argv an array of command line arguments. The argument
     *      start will check if the process control functions exists if these
     *      do they will fork and detach a child process to act as a daemon.
     *      a lock file will be created to prevent additional daemons from
     *      running. If the message is stop then a message file is written to 
     *      tell the daemon to stop. If the argument is terminal then the
     *      program won't be run as a daemon.
     * @param string $name the prefix to use for lock and message files
     */
    static function init($argv, $name)
    {
        self::$name = $name;
        //don't let our script be run from apache
        if(isset($_SERVER['DOCUMENT_ROOT']) && 
            strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
            echo "BAD REQUEST";
            exit();
        }
        if(!isset($argv[1])) {
            echo "$name needs to be run with a command-line argument.\n";
            echo "For example,\n";
            echo "php $name.php start //starts the $name as a daemon\n";
            echo "php $name.php stop //stops the $name daemon\n";
            echo "php $name.php terminal //runs $name within the current ".
                "process not as a daemon\n";
            exit();
        }

        //the next code is for running as a daemon on *nix systems
        $terminal_flag = strcmp($argv[1], "terminal") == 0;
        if(function_exists("pcntl_fork") && !$terminal_flag)  {
            $pid = pcntl_fork();
            if ($pid == -1) {
                die("could not fork"); 
            } else if ($pid) {
                exit(); // parent goes away 
            }
        } else { //for Windows systems we fall back to console operation
            if(!$terminal_flag) {
                echo "pcntl_fork function does not exist falling back to ".
                    "terminal mode\n";
            }
            $argv[1] = "terminal";
        }

        //used mainly to handle segmentation faults caused by flaky multi_curl
        if(function_exists("pcntl_signal")) {
            pcntl_signal(SIGSEGV, "CrawlDaemon::processHandler");
        }

        switch($argv[1])
        {
            case "start":
                if(file_exists(CRAWL_DIR."/schedules/$name"."_lock.txt")) {
                    echo "$name appears to be already running...\n";
                    echo "Try stopping it first, then running start.";
                    exit();
                }
                echo "Starting $name...\n";
                // setup signal handler
                pcntl_signal(SIGTERM, "CrawlDaemon::processHandler");

                file_put_contents(
                    CRAWL_DIR."/schedules/$name"."_lock.txt", 
                    serialize(getmypid()));

                $info = array();
                $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                file_put_contents(
                    CRAWL_DIR."/schedules/$name"."_messages.txt", 
                    serialize($info));

                define("LOG_TO_FILES", true); 
                    // if false log messages are sent to the console
            break;

            case "stop":
                if(file_exists(CRAWL_DIR."/schedules/$name"."_lock.txt")) {
                    $pid = unserialize(file_get_contents(
                        CRAWL_DIR."/schedules/$name"."_lock.txt"));
                    echo "Stopping $name...$pid\n";
                    posix_kill($pid, SIGTERM);
                } else {
                    echo "$name does not appear to running...\n";
                }
                exit();
            break;

            case "terminal":
                $info = array();
                $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                file_put_contents(
                    CRAWL_DIR."/schedules/$name"."_messages.txt", 
                    serialize($info));

                define("LOG_TO_FILES", false);
            break;

            default:
                exit();
            break;
        }

    }
}
 ?>
