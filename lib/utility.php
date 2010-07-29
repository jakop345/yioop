<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
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
 * A library of log, hash, and time functions
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Logs a message to a logfile or the screen
 *
 *  @param string $msg message to log
 *  @param string $lname name of log file in the LOG_DIR directory, rotated logs
 *      will also use this as their basename followed by a number followed by 
 *      bz2 (since they are bzipped).
 */

function crawlLog($msg, $lname = NULL)
{
    static $logname;


    if($lname != NULL)
    {
        $logname = $lname;
    } else if(!isset($logname)) {
        $logname = "message";
    }

    $time_string = date("r", time());
    $out_msg = "[$time_string] $msg";
    if(LOG_TO_FILES) {
        $logfile = LOG_DIR."/$logname.log";

        clearstatcache(); //hopefully, this doesn't slow things too much

        if(file_exists($logfile) && filesize($logfile) > MAX_LOG_FILE_SIZE) {
            if(file_exists("$logfile.".NUMBER_OF_LOG_FILES.".bz2")) {
                unlink("$logfile.".NUMBER_OF_LOG_FILES.".bz2");
            }
            for($i = NUMBER_OF_LOG_FILES; $i > 0; $i--) {
                if(file_exists("$logfile.".($i-1).".bz2")) {
                    rename("$logfile.".($i-1).".bz2", "$logfile.$i.bz2");
                }
            }
            file_put_contents("$logfile.0.bz2", 
                bzcompress(file_get_contents($logfile)));
            unlink($logfile);
        }
        error_log($out_msg."\n", 3, $logfile);
    } else {
        error_log($out_msg);
    }
}

/**
 *  Computes an 8 byte hash of a string for use in storing documents.
 *
 *  An eight byte hash was chosen so that the odds of collision even for
 *  a few billion documents via the birthday problem are still reasonable.
 *  If the raw flag is set to false then an 11 byte base64 encoding of the
 *  8 byte hash is returned. The hash is calculated as the xor of the
 *  two halves of the 16 byte md5 of the string. (8 bytes takes less storage
 *  which is useful for keeping more doc info in memory)
 *
 *  @param string $string the string to hash
 *  @param bool $raw whether to leave raw or base 64 encode
 *  @return string the hash of $string
 */
function crawlHash($string, $raw=false) 
{
    $pre_hash = md5($string, true);

    $left = substr($pre_hash,0, 8) ;
    $right = substr($pre_hash,8, 8) ;

    $combine = $right ^ $left;

    if(!$raw) {
        $hash = rtrim(base64_encode($combine), "=");
        $hash = str_replace("/", "_", $hash);
        $hash = str_replace("+", "-" , $hash); 
            // common variant of base64 safe for urls and paths
    } else {
        $hash = $combine;
    }

    return $hash; 
}

/**
 * The search engine project's variation on the Unix crypt function using the 
 * crawlHash function instead of DES
 *
 * The crawlHash function is used to encrypt passwords stored in the database
 *
 * @param string $string the string to encrypt
 * @param int $salt salt value to be used (needed to verify if a password is 
 *      valid)
 * @return string the crypted string where crypting is done using crawlHash
 */
function crawlCrypt($string, $salt = NULL)
{
    if($salt == NULL) {
        $salt = rand(10000,99999);
    } else {
        $len = strlen($salt);
        $salt = substr($salt, $len - 5, 5);
    }
    return crawlHash($string.$salt).$salt;
}




/**
 * Measures the change in time in seconds between two timestamps to microsecond
 * precision
 *
 * @param string $start starting time with microseconds
 * @param string $end ending time with microseconds
 * @return float time difference in seconds
 * @see SigninModel::changePassword()
 * @see SigninModel::checkValidSignin()
 */
function changeInMicrotime( $start, $end=NULL ) 
{
    if( !$end ) {
    	    $end= microtime();
    }
    list($start_microseconds, $start_seconds) = explode(" ", $start);
    list($end_microseconds, $end_seconds) = explode(" ", $end);

    $change_in_seconds = intval($end_seconds) - intval($start_seconds);
    $change_in_microseconds = 
        floatval($end_microseconds) - floatval($start_microseconds);

    return floatval( $change_in_seconds ) + $change_in_microseconds;
} 

// callbacks for Model::traverseDirectory

/**
 * This is a callback function used in the process of recursively deleting a 
 * directory
 *
 * @param string $file_or_dir the filename or directory name to be deleted
 * @see DatasourceManager::unlinkRecursive()
 */
function deleteFileOrDir($file_or_dir)
{
    if(is_file($file_or_dir)) {
        unlink($file_or_dir);
    } else {
        rmdir($file_or_dir);
    }
}

/**
 * This is a callback function used in the process of recursively chmoding to 
 * 777 all files in a folder
 *
 * @param string $file the filename or directory name to be chmod
 * @see DatasourceManager::etWorldPermissionsRecursive()
 */
function setWorldPermissions($file)
{
    chmod($file, 0777);
}

//ordering functions used in sorting

/**
 *  Callback function used to sort documents by score
 *
 *  The function is used to sort documents being added to an IndexArchiveBundle
 *
 *  @param string $word_doc_a doc id of first document to compare
 *  @param string $word_doc_b doc id of second document to compare
 *  @return int -1 if first doc bigger 1 otherwise
 *  @see IndexArchiveBundle::addPartitionWordData()
 */
function scoreOrderCallback($word_doc_a, $word_doc_b)
{
    return ((float)$word_doc_a[CrawlConstants::SCORE] > 
        (float)$word_doc_b[CrawlConstants::SCORE]) ? -1 : 1;
}

/**
 * Callback to check if $a is less than $b
 *
 * Used to help sort document results returned in PhraseModel called 
 * in IndexArchiveBundle
 *
 * @param float $a first value to compare
 * @param float $b second value to compare
 * @return int -1 if $a is less than $b; 1 otherwise
 * @see IndexArchiveBundle::getSelectiveWords()
 * @see PhraseModel::getPhrasePageResults()
 */
function lessThan($a, $b) {
    if ($a == $b) {
        return 0;
    }
    return ($a < $b) ? -1 : 1;
}

/**
 *  Callback to check if $a is greater than $b
 *
 * Used to help sort document results returned in PhraseModel called in 
 * IndexArchiveBundle
 *
 * @param float $a first value to compare
 * @param float $b second value to compare
 * @return int -1 if $a is greater than $b; 1 otherwise
 * @see IndexArchiveBundle::getSelectiveWords()
 * @see PhraseModel::getTopPhrases()
 */
function greaterThan($a, $b) {
    if ($a == $b) {
        return 0;
    }
    return ($a > $b) ? -1 : 1;
}


?>