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
 * @author Ravi Dhillon  ravi.dhillon@yahoo.com
 * @package seek_quarry
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}

ini_set("memory_limit","1024M");

if(count($argv) != 3){
    echo "Please provide arguments xmlFileName and language";
    exit();
}

/**
 * Calculate base directory of script
 * @ignore
 */
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0,
    -strlen("/configs")));

/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance ".
        "by visiting its web interface on localhost.\n";
    exit();
}

/**
 * Load the Bigrams File
 */
require_once BASE_DIR."/lib/bigrams.php";

$xmlFilePath = WORK_DIRECTORY."/search_filters/".$argv[1];
if (!file_exists($xmlFilePath)) {
    echo $argv[1]." does not exist in the directory.";
    exit();
}

/*
 *This call creates a bigrams text file from input xml file and
 *returns the count of bigrams in the text file.
 */
$num_of_bigrams = Bigrams::generateBigramsTextFile($argv[1], $argv[2]);

/*
 *This call creates a bloom filter file from bigrams text file based
 *on the language specified.The lang passed as parameter is prefixed
 *to the filter file name. The count of bigrams in text file is passed
 *as a parameter to set the limit of bigrams in the filter file.
 */
Bigrams::createBigramFilterFile($argv[2], $num_of_bigrams);

?>
