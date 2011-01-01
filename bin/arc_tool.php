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
 * @subpackage bin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */

/** Calculate base directory of script @ignore*/
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0, 
    -strlen("/bin")));

/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}

/** NO_CACHE means don't try to use memcache*/
define("NO_CACHE", true);

/** Load the class that maintains our URL queue */
require_once BASE_DIR."/lib/web_queue_bundle.php";

/** Load word->{array of docs with word} index class */
require_once BASE_DIR."/lib/index_archive_bundle.php";

/** Used for manipulating urls*/
require_once BASE_DIR."/lib/url_parser.php";

/**  For crawlHash function */
require_once BASE_DIR."/lib/utility.php";

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/*
 *  We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

/**
 * Command line program that allows one to examine the content of
 * the WebArchiveBundles and IndexArchiveBundles of Yioop crawls.
 * For now it supports returning header information about bundles,
 * as well as pretty printing the page/summary contents of the bundle.
 *
 * The former can be gotten from a bundle by running arc_tool with a
 * command like:
 * php arc_tool.php info bundle_name
 *
 * The latter can be gotten from a bundle by running arc_tool with a 
 * command like:
 * php arc_tool.php list bundle_name start_doc_num num_results
 *
 * @author Chris Pollett
 * @package seek_quarry
 */
class ArcTool implements CrawlConstants
{

    /** 
     * The maximum number of documents the arc_tool list function
     * will read into memory in one go.
     */
    const MAX_BUFFER_DOCS = 200;

    /**
     * Initializes the ArcTool, for now does nothing
     */
    function __construct() 
    {

    }

    /**
     * Runs the ArcTool on the supplied command line arguments
     */
    function start()
    {
        global $argv;

        if(isset($_SERVER['DOCUMENT_ROOT']) && 
            strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
            echo "BAD REQUEST";
            exit();
        }
        
        if(!isset($argv[1])) {
            $this->usageMessageAndExit();
        }

        switch($argv[1])
        {
            case "info":
                if(!isset($argv[2]) ) {
                    $this->usageMessageAndExit();
                }
                $this->outputInfo($argv[2]);
            break;

            case "list":
                if(!isset($argv[2]) || !isset($argv[3])) {
                    $this->usageMessageAndExit();
                }
                $this->outputList($argv[2], $argv[3], $argv[4]);
            break;

            default:
                $this->usageMessageAndExit();
        }

    }

    /**
     * Determines whether the supplied name is a WebArchiveBundle or
     * an IndexArchiveBundle. Then outputsto stdout header information about the
     * bundle by calling the appropriate sub-function.
     *
     * @param string $archive_name the name of a directory that holds 
     *      WebArchiveBundle or IndexArchiveBundle data
     */
    function outputInfo($archive_name)
    {
        $bundle_name = UrlParser::getDocumentFilename($archive_name);
        echo "Bundle Name: ".$bundle_name."\n";
        $archive_type = $this->getArchiveKind($archive_name);
        echo "Bundle Type: ".$archive_type."\n";
        if($archive_type === false) {
            $this->badFormatMessageAndExit($archive_name);
        }
        $call = "outputInfo".$archive_type;
        $info = $archive_type::getArchiveInfo($archive_name);
        $this->$call($info, $archive_name);
    }

    /**
     * Outputs to stdout header information for a IndexArchiveBundle
     * bundle.
     *
     * @param array $info header info that has already been read from
     *      the description.txt file
     * @param string $archive_name the name of the folder containing the bundle
     */
    function outputInfoIndexArchiveBundle($info, $archive_name)
    {
        $more_info = unserialize($info['DESCRIPTION']);
        unset($info['DESCRIPTION']);
        $info = array_merge($info, $more_info);
        echo "Description: ".$info['DESCRIPTION']."\n";
        $generation_info = unserialize(
            file_get_contents("$archive_name/generation.txt"));
        $num_generations = $generation_info['ACTIVE']+1;
        echo "Number of generations: ".$num_generations."\n";
        echo "Number of stored links and documents: ".$info['COUNT']."\n";
        echo "Number of stored documents: ".$info['VISITED_URLS_COUNT']."\n";
        $crawl_order = ($info[self::CRAWL_ORDER] == self::BREADTH_FIRST) ?
            "Bread First" : "Page Importance";
        echo "Crawl order was: $crawl_order\n";
        echo "Seed sites:\n";
        foreach($info[self::TO_CRAWL] as $seed) {
            echo "   $seed\n";
        }
        if($info[self::RESTRICT_SITES_BY_URL]) {
            echo "Sites allowed to crawl:\n";
            foreach($info[self::ALLOWED_SITES] as $site) {
                echo "   $site\n";
            }
        }
        echo "Sites not allowed to be crawled:\n";
        foreach($info[self::DISALLOWED_SITES] as $site) {
            echo "   $site\n";
        }
        echo "Meta Words:\n";
        foreach($info[self::META_WORDS] as $word) {
            echo "   $word\n";
        }
        echo "\n";
    }

    /**
     * Outputs to stdout header information for a WebArchiveBundle
     * bundle.
     *
     * @param array $info header info that has already been read from
     *      the description.txt file
     * @param string $archive_name the name of the folder containing the bundle

     */
    function outputInfoWebArchiveBundle($info, $archive_name)
    {
        echo "Description: ".$info['DESCRIPTION']."\n";
        echo "Number of stored documents: ".$info['COUNT']."\n";
        echo "Maximum Number of documents per partition: ".
            $info['NUM_DOCS_PER_PARTITION']."\n";
        echo "Number of partitions: ".
            ($info['WRITE_PARTITION']+1)."\n";
        echo "\n";
    }

    /**
     * Used to list out the pages/summaries stored in a bundle
     * $archive_name. It lists to stdout $num many documents starting at $start.
     *
     * @param string $archive_name name of bundle to list documents for
     * @param int $start first document to list
     * @param int $num number of documents to list
     */
    function outputList($archive_name, $start, $num)
    {
        $fields_to_print = array(
            self::URL => "URL",
            self::HTTP_CODE => "HTTP RESPONSE CODE",
            self::TYPE => "MIMETYPE",
            self::ENCODING => "CHARACTER ENCODING",
            self::DESCRIPTION => "DESCRIPTION",
            self::PAGE => "PAGE DATA");
        $archive_type = $this->getArchiveKind($archive_name);
        if($archive_type === false) {
            $this->badFormatMessageAndExit($archive_name);
        }
        $info = $archive_type::getArchiveInfo($archive_name);
        $num = min($num, $info["COUNT"] - $start);

        if($archive_type == "IndexArchiveBundle") {
            $generation_info = unserialize(
                file_get_contents("$archive_name/generation.txt"));
            $num_generations = $generation_info['ACTIVE']+1;
            $archive = new WebArchiveBundle($archive_name."/summaries");
        } else {
            $num_generations = $info["WRITE_PARTITION"]+1;
            $archive = new WebArchiveBundle($archive_name);
        }
        $num = max($num, 0);
        $total = $start + $num;
        $seen = 0;
        $generation = 0;
        while($seen < $total && $generation < $num_generations) {
            $partition = $archive->getPartition($generation, false);
            if($archive->count < $start && $seen < $start) {
                $generation++;
                $seen += $this->count;
                continue;
            }
            $seen_generation = 0;
            while($seen < $total && $seen_generation < $archive->count) {
                $num_to_get = min($total - $seen,  
                    $archive->count - $seen_generation, 
                    self::MAX_BUFFER_DOCS);
                $objects = $partition->nextObjects($num_to_get);
                $seen += $num_to_get;
                $seen_generation += $num_to_get;
                if($seen > $start) {
                    $num_to_show = min($seen - $start, $num_to_get);
                    $cnt = 0;
                    $first = $num_to_get - $num_to_show;
                    foreach($objects as $object) {
                        if($cnt >= $first) {
                            $out = "";
                            foreach($fields_to_print as $key => $name) {
                                if(isset($object[1][$key])) {
                                    $out .= "[$name]\n";
                                    $out .= $object[1][$key]."\n";
                                }
                            }
                            $out .= "==========\n\n";
                            echo "BEGIN ITEM, LENGTH:".strlen($out)."\n";
                            echo $out;
                        }
                        $cnt++;
                    }
                }
            }
            $generation++;
        }
    }

    /**
     * Given a folder name, determines the kind of bundle (if any) it holds.
     * It does this based on the expected location of the description.txt file.
     *
     * @param string $archive_name the name of folder
     * @return string the archive bundle type, either: WebArchiveBundle or
     *      IndexArchiveBundle
     */
    function getArchiveKind($archive_name)
    {
        if(file_exists("$archive_name/description.txt")) {
            return "WebArchiveBundle";
        }
        if(file_exists("$archive_name/summaries/description.txt")) {
            return "IndexArchiveBundle";
        }
        return false;
    }

    /**
     * Outputs the "hey, this isn't a known bundle message" and then exit()'s.
     */
    function badFormatMessageAndExit($archive_name) 
    {
        echo "$archive_name does not appear to be a web or index ".
        "archive bundle\n";
        exit();
    }

    /**
     * Outputs the "how to use this tool message" and then exit()'s.
     */
    function usageMessageAndExit() 
    {
        echo "arc_tool is used to look at the contents of";
        echo " WebArchiveBundles and IndexArchiveBundles.\n For example,\n";
        echo "php arc_tool.php info bundle_name //return info about ".
            "documents stored in archive.\n";
        echo "php arc_tool.php list bundle_name start num //outputs".
            " items start through num.from bundle_name\n";
        exit();
    }
}

$arc_tool =  new ArcTool();
$arc_tool->start();

