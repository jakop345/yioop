<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2012
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
 * To use CrawlDaemon need to declare ticks first in a scope that
 * won't go away after CrawlDaemon:init is called
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
     * Used by processHandler to decide when to update the lock file
     * @var int
     * @static
     */
    static $time;

    /**
     * Tick callback function used to update the timestamp in this processes
     * lock. If lock_file does not exist it stops the process
     *
     * @param int $signo signal sent to the daemon
     */
    static function processHandler()
    {
        $now = time();
        if($now - self::$time < 30) return;

        $lock_file = CRAWL_DIR."/schedules/".self::$name."_lock.txt";
        if(!file_exists($lock_file)) {
            crawlLog("Stopping ".self::$name." ...");
            exit();
        }

        file_put_contents($lock_file, $now);
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
        if(isset($argv[2])) {
            self::$name = intval($argv[2])."-".$name;
        } else {
            self::$name = $name;
        }
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
                "process, not as a daemon, output going to the terminal\n";
            exit();
        }

        $lock_file = CRAWL_DIR."/schedules/".self::$name."_lock.txt";
        $messages_file = CRAWL_DIR."/schedules/".self::$name."_messages.txt";

        switch($argv[1])
        {
            case "start":
                
                if(file_exists($lock_file)) {
                    $time = intval(file_get_contents($lock_file));
                    if(time() - $time < 60) {
                        echo "$name appears to be already running...\n";
                        echo "Try stopping it first, then running start.";
                        exit();
                    }
                }
                $start_time = date('H:m', time() + 60);
                if(strstr(PHP_OS, "WIN")) {
                    $script = "at $start_time \"php $name.php child %s\"";
                } else {
                    $script = "echo \"php ".
                        BASE_DIR."/bin/$name.php child %s\" | at now ";
                }
                $options = "";
                for($i = 2; $i < count($argv); $i++) {
                    $options .= " ". $argv[$i];
                }
                $at_job = sprintf($script, $options);
                echo $at_job;
                exec($at_job);
                echo "Starting $name...\n";

                file_put_contents($lock_file,  time());
                exit();
            break;

            case "stop":
                if(file_exists($lock_file)) {
                    unlink($lock_file);
                    echo "Sending stop signal to $name...\n";
                } else {
                    echo "$name does not appear to running...\n";
                }
                exit();
            break;

            case "terminal":
                $info = array();
                $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                file_put_contents($messages_file, serialize($info));
                define("LOG_TO_FILES", false);
            break;

            case "child":
                register_tick_function('CrawlDaemon::processHandler');

                self::$time = time();
                $info = array();
                $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                file_put_contents($messages_file, serialize($info));

                define("LOG_TO_FILES", true); 
                    // if false log messages are sent to the console
            break;

            default:
                exit();
            break;
        }

    }

    /**
     * Returns the statuses of the running daemons 
     * 
     * @return array 2d array active_daemons[name][instance] = true
     */
    static function statuses()
    {
        $prefix = CRAWL_DIR."/schedules/";
        $prefix_len = strlen($prefix);
        $suffix = "_lock.txt";
        $suffix_len = strlen($suffix);
        $lock_files = "$prefix*$suffix";
        clearstatcache();
        $time = time();
        $active_daemons = array();
        foreach (glob($lock_files) as $file) {
            if(filemtime($file) - $time < 120) {
                $len = strlen($file) - $suffix_len - $prefix_len;
                $pre_name = substr($file, $prefix_len, $len);
                $pre_name_parts = explode("-", $pre_name);
                if(count($pre_name_parts) == 1) {
                    $active_daemons[$pre_name][-1] = true;
                } else { 
                    $first = array_shift($pre_name_parts);
                    $rest = implode("-", $pre_name_parts);
                    $active_daemons[$rest][$first] = true;
                }
            }
        }
        return $active_daemons;
    }
}
 ?>
