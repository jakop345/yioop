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
 * Bigrams are pair of words which always occur together in the same
 * sequence in a user query, ex: "honda accord". Yioop treates these
 * pair of words as a single word to increse the speed and efficiency
 * of retrieval. This script can be used to create a bigrams filter
 * file for the yioop search engine to filter such words from documents
 * and queries. The input to this script is an xml file which contains
 * a large collection of such bigrams. The most common source of a large
 * set of bigrams in an xml file is wikipedia dumps. Wikipedia dumps are
 * available for downloaded online free of cost. Typically the bigrams
 * filter file is specific to a language, therefore user has to create a
 * separate filter file for each language to use this functionality. This
 * script can be run multiple times to create a different filter file by
 * specifying a different input xml and a different language as the
 * command line arguments. The xml dumps of wikipedia specific to
 * different languages are available to download. These language specific
 * dumps of wikipedia as xml file serve as input to this script.
 *
 * For example the user can follow the following steps to create a bigrams
 * filter for english langauge.
 *
 * Step 1): Go to link http://dumps.wikimedia.org/enwiki/ which is source
 * of dumps for english wikipedia. This page lists all the dumps according
 * to date they were taken. Choose any suitable date or the latest. Say we
 * chose 20120104/, dumps taken on 01/04/2012. This would take you to the
 * page which has many links based on type of content you are looking for.
 * We are interested in content titled
 * "Recobine all pages, current versions only" with the link
 * "enwiki-20120104-pages-meta-current.xml.bz2"
 * This is a bz2 compressed xml file containing all the english pages of
 * wikipedia. Download the file to the "serach_filters" folder of your
 * yioop work directory associated with your profile.
 * (Note: You should have sufficient hard disk space in the order of
 *        100GB to store the compressed dump and script extracted xml.
 *        The script also accepts an uncompressed XML file as input.
 *        The filter file generated is a few megabytes.)
 *
 * Step 2): Run this script from the php command line as below
 * php bigram_builder enwiki-20120104-pages-meta-current.xml.bz2 en
 *
 * This would create a bigram filter for english in the same directory.
 *
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
    echo "bigram_builder is used to create a bigram filter file for the \n".
        "Yioop! search engine. This filter file is used to detect when two \n".
        "words in a language should be treated as a unit. For example, \n".
        "Bill Clinton. bigram_builder is run from the command line as:\n".
        "php bigram.php wiki_xml lang\n".
        "where wiki_xml is a wikipedia xml file or a bz2 compressed xml\n".
        "file whose urls will be used to determine the bigrams and lang\n".
        "is an IANA language tag.";
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

$wiki_file_path = WORK_DIRECTORY."/search_filters/";
if (!file_exists($wiki_file_path.$argv[1])) {
    echo $argv[1]." does not exist in $wiki_file_path";
    exit();
}

/*
 *This call creates a bigrams text file from input xml file and
 *returns the count of bigrams in the text file.
 */
$num_bigrams = Bigrams::generateBigramsTextFile($argv[1], $argv[2]);

/*
 *This call creates a bloom filter file from bigrams text file based
 *on the language specified.The lang passed as parameter is prefixed
 *to the filter file name. The count of bigrams in text file is passed
 *as a parameter to set the limit of bigrams in the filter file.
 */
Bigrams::createBigramFilterFile($argv[2], $num_bigrams);

?>
