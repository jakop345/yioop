<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage bin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}
/** Calculate base directory of script @ignore*/
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0,
    -strlen("/bin")));
ini_set("memory_limit","2000M"); /*
        reindex sometimes takes more than the default 128M, 850 to be safe */
/** This tool does not need logging*/
define("LOG_TO_FILES", false);
/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}
/** NO_CACHE means don't try to use memcache*/
define("NO_CACHE", true);
/** USE_CACHE false rules out file cache as well*/
define("USE_CACHE", false);
/** Load the class that maintains our URL queue */
require_once BASE_DIR."/lib/web_queue_bundle.php";
/** Load word->{array of docs with word} index class */
require_once BASE_DIR."/lib/index_archive_bundle.php";
/** To be able to determine info about word in a index dictionary*/
require_once BASE_DIR."/lib/index_bundle_iterators/word_iterator.php";
/** Used by word_iterator.php*/
require_once BASE_DIR."/lib/index_manager.php";
/** Load the iterator classes for non-yioop archives*/
foreach(glob(BASE_DIR."/lib/archive_bundle_iterators/*_iterator.php")
    as $filename) {
    require_once $filename;
}
/** Used for manipulating urls*/
require_once BASE_DIR."/lib/url_parser.php";
/**  For crawlHash function */
require_once BASE_DIR."/lib/utility.php";
/** Get the database library based on the current database type */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php";
/** Load FetchUrl, used by the MediaWiki archive iterator */
require_once BASE_DIR."/lib/fetch_url.php";
/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";
/** To get models when doing a crawl seed injection*/
require_once BASE_DIR."/controllers/admin_controller.php";
/*
 * We'll set up multi-byte string handling to use UTF-8
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
 * @author Chris Pollett (non-yioop archive code derived from earlier
 *     stuff by Shawn Tice)
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
        if(!isset($argv[1]) || (!isset($argv[2]) && $argv[1] != "list") ||
            (!isset($argv[3]) && in_array($argv[1],
            array("dict", "posting","inject") ) ) ) {
            $this->usageMessageAndExit();
        }
        if($argv[1] != "list") {
            if($argv[1] != "inject") {
                $path = UrlParser::getDocumentFilename($argv[2]);
                if($path == $argv[2] && !file_exists($path)) {
                    $path = CRAWL_DIR."/cache/".$path;
                    if(!file_exists($path)) {
                        $path = CRAWL_DIR."/archives/".$argv[2];
                    }
                }
            } else if(is_numeric($argv[2])) {
                    $path = $argv[2];
            } else {
                $this->usageMessageAndExit();
            }
        }
        switch($argv[1])
        {
            case "list":
                $this->outputArchiveList();
            break;
            case "info":
                $this->outputInfo($path);
            break;
            case "shard":
                $this->outputShardInfo($path, $argv[3]);
            break;
            case "dict":
                $this->outputDictInfo($path, $argv[3]);
            break;
            case "inject":
                $this->inject($path, $argv[3]);
            break;
            case "posting":
                $num = (isset($argv[5])) ? $argv[5] : 1;
                $this->outputPostingInfo($path, $argv[3], $argv[4], $num);
            break;
            case "rebuild":
                $this->rebuildIndexArchive($path);
            break;
            case "reindex":
                $this->reindexIndexArchive($path);
            break;
            case "mergetiers":
                if(!isset($argv[3])) {
                    $this->usageMessageAndExit();
                }
                $this->reindexIndexArchive($path, $argv[3]);
            break;
            case "show":
                if(!isset($argv[3])) {
                    $this->usageMessageAndExit();
                }
                if(!isset($argv[4])) {
                    $argv[4] = 1;
                }
                $this->outputShowPages($path, $argv[3], $argv[4]);
            break;
            default:
                $this->usageMessageAndExit();
        }
    }
    /**
     * Lists the Web or IndexArchives in the crawl directory
     */
     function outputArchiveList()
     {
        $yioop_pattern = CRAWL_DIR."/cache/*{".self::archive_base_name.",".
            self::index_data_base_name."}*";

        $archives = glob($yioop_pattern, GLOB_BRACE);
        $archives_found = false;
        if(is_array($archives) && count($archives) > 0) {
            $archives_found = true;
            echo "\nFound Yioop Archives:\n";
            echo "=====================\n";
            foreach($archives as $archive_path) {
                echo $this->getArchiveName($archive_path)."\n";
            }
        }
        $nonyioop_pattern = CRAWL_DIR."/archives/*/arc_description.ini";
        $archives = glob($nonyioop_pattern);
        if(is_array($archives) && count($archives) > 0 ) {
            $archives_found = true;
            echo "\nFound Non-Yioop Archives:\n";
            echo "=========================\n";
            foreach($archives as $archive_path) {
                $len = strlen("/arc_description.ini");
                $path = substr($archive_path, 0, -$len);
                echo $this->getArchiveName($path)."\n";
            }
        }
        if(!$archives_found) {
            echo "No archives currently in crawl directory \n";
        }
        echo "\n";
     }
    /**
     * Determines whether the supplied path is a WebArchiveBundle or
     * an IndexArchiveBundle or non-Yioop Archive. Then outputs
     * to stdout header information about the
     * bundle by calling the appropriate sub-function.
     *
     * @param string $archive_path the path of a directory that holds
     *     WebArchiveBundle,IndexArchiveBundle, or non-Yioop archive data
     */
    function outputInfo($archive_path)
    {
        $bundle_name = $this->getArchiveName($archive_path);
        echo "Bundle Name: ".$bundle_name."\n";
        $archive_type = $this->getArchiveKind($archive_path);
        echo "Bundle Type: ".$archive_type."\n";
        if($archive_type === false) {
            $this->badFormatMessageAndExit($archive_path);
        }
        if(in_array($archive_type, array("IndexArchiveBundle",
            "WebArchiveBundle"))) {
            $call = "outputInfo".$archive_type;
            $info = $archive_type::getArchiveInfo($archive_path);
            $this->$call($info, $archive_path);
        }
    }
    /**
     * Prints the IndexDictionary records for a word in an IndexArchiveBundle
     *
     * @param string $archive_path the path of a directory that holds
     *     an IndexArchiveBundle
     * @param string $word to look up dictionary record for
     */
    function outputDictInfo($archive_path, $word)
    {
        $bundle_name = $this->getArchiveName($archive_path);
        echo "\nBundle Name: $bundle_name\n";
        $archive_type = $this->getArchiveKind($archive_path);
        echo "Bundle Type: $archive_type\n";

        if(strcmp($archive_type,"IndexArchiveBundle") != 0) {
            $this->badFormatMessageAndExit($archive_path, "index");
        }
        $index_timestamp = substr($archive_path,
            strpos($archive_path, self::index_data_base_name) +
            strlen(self::index_data_base_name));
        $mask = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        $hash_key = crawlHashWord($word, true, $mask) ;
        $info = IndexManager::getWordInfo($index_timestamp, $hash_key, 0,
            $mask, 1);
        if(!$info) {
            //fallback to old word hashes
            $info = IndexManager::getWordInfo($index_timestamp,
                crawlHash($word, true), 0, "", 1);
            if(!$info) {
                echo "\n$word does not appear in bundle!\n\n";
                exit();
            }
        }
        echo "Dictionary Tiers: ";
        $index = IndexManager::getIndex($index_timestamp);
        $tiers = $index->dictionary->active_tiers;
        foreach($tiers as $tier) {
            echo " $tier";
        }
        echo "\nBundle Dictionary Entries for '$word':\n";
        echo "====================================\n";
        $i = 1;
        foreach($info as $record) {
            echo "RECORD: $i\n";
            echo "GENERATION: {$record[0]}\n";
            echo "FIRST WORD OFFSET: {$record[1]}\n";
            echo "LAST WORD OFFSET: {$record[2]}\n";
            echo "NUMBER OF POSTINGS: {$record[3]}\n\n";
            $i++;
        }
    }
    /**
     * Prints information about the number of words and frequencies of words
     * within the $generation'th index shard in the bundle
     *
     * @param string $archive_path the path of a directory that holds
     *     an IndexArchiveBundle
     * @param int $generation which index shard to use
     */
    function outputShardInfo($archive_path, $generation)
    {
        ini_set("memory_limit","2000M"); /*reading in a whole shard might take
                a bit more memory
            */
        $bundle_name = $this->getArchiveName($archive_path);
        echo "\nBundle Name: $bundle_name\n";
        $archive_type = $this->getArchiveKind($archive_path);
        echo "Bundle Type: $archive_type\n";

        if(strcmp($archive_type,"IndexArchiveBundle") != 0) {
            $this->badFormatMessageAndExit($archive_path, "index");
        }
        $index_timestamp = substr($archive_path,
            strpos($archive_path, self::index_data_base_name) +
            strlen(self::index_data_base_name));
        $index = IndexManager::getIndex($index_timestamp);
        $index->setCurrentShard($generation);

        $num_generations = $index->generation_info["ACTIVE"] + 1;
        echo "Number of Generations: $num_generations\n";
        echo "\nShard Information for Generation $generation\n";
        echo "====================================\n";

        $shard = $index->getCurrentShard();
        echo "Number of Distinct Terms Indexed: ".count($shard->words)."\n";
        echo "Number of Docs in Shard: ".$shard->num_docs."\n";
        echo "Number of Link Items in Shard: ".$shard->num_link_docs."\n";
        echo "Total Links and Docs: ".($shard->num_docs +
            $shard->num_link_docs)."\n\n";
        echo "Term histogram for shard\n";
        echo "------------------------\n";
        $word_string_lens = array();
        foreach($shard->words as $word => $posting) {
            $word_string_lens[] = intval(ceil(strlen($posting)/4));
        }
        $word_string_lens = array_count_values($word_string_lens);
        krsort($word_string_lens);
        $i = 1;
        echo "Freq Rank\t# Terms with Rank\t# Docs Term Appears In\n";
        foreach($word_string_lens as $num_docs => $num_terms) {
            echo "$i\t\t\t$num_terms\t\t\t$num_docs\n";
            $i += $num_terms;
        }

    }

    /**
     * Prints information about $num many postings beginning at the
     * provided $generation and $offset
     *
     * @param string $archive_path the path of a directory that holds
     *     an IndexArchiveBundle
     * @param int $generation which index shard to use
     * @param int $offset offset into posting lists for that shard
     * @param int $num how many postings to print info for
     */
    function outputPostingInfo($archive_path, $generation, $offset, $num = 1)
    {
        $bundle_name = $this->getArchiveName($archive_path);
        echo "\nBundle Name: $bundle_name\n";
        $archive_type = $this->getArchiveKind($archive_path);
        echo "Bundle Type: $archive_type\n";
        echo "Generation: $generation\n";
        echo "Offset: $offset\n";

        if(strcmp($archive_type,"IndexArchiveBundle") != 0) {
            $this->badFormatMessageAndExit($archive_path, "index");
        }
        $index_timestamp = substr($archive_path,
            strpos($archive_path, self::index_data_base_name) +
            strlen(self::index_data_base_name));
        $index = IndexManager::getIndex($index_timestamp);
        $index->setCurrentShard($generation, true);
        $shard = $index->getCurrentShard();
        $next = $offset >> 2;
        $raw_postings = array();
        $doc_indexes = array();
        $documents = array();
        for($i = 0; $i < $num; $i++) {
            $dummy_offset = 0;
            $posting_start = $next;
            $posting_end = $next;
            $old_offset = $next << 2;
            $old_start = $next << 2;
            $old_end = $next << 2;
            $tmp = $shard->getPostingAtOffset(
                $next, $posting_start, $posting_end);
            $next = $posting_end + 1;
            if(!$tmp) break;
            $documents = array_merge($documents,
                $shard->getPostingsSlice($old_offset, $old_start, $old_end, 1));
            $raw_postings[] = $tmp;
            $post_array = unpackPosting($tmp, $dummy_offset);
            $doc_indexes[] = $post_array[0];
        }
        $end_offset = $next << 2;
        echo "Offset After Returned Results: $end_offset\n\n";
        if(!$documents || ($count = count($documents)) < 1) {
            echo "No documents correspond to generation and offset given\n\n";
            exit();
        };
        $document_word = ($count == 1) ? "Document" : "Documents";
        echo "$count $document_word Found:\n";
        echo str_pad("", $count + 1, "=")."================\n";
        $j = 0;
        foreach($documents as $key => $document) {
            echo "\nDOC ID: ".toHexString($key);
            echo "\nTYPE: ".(($document[self::IS_DOC]) ? "Document" : "Link");
            echo "\nDOC INDEX: ".$doc_indexes[$j];
            $summary_offset = $document[self::SUMMARY_OFFSET];
            echo "\nSUMMARY OFFSET: ".$summary_offset;
            echo "\nSCORE: ".$document[self::SCORE];
            echo "\nDOC RANK: ".$document[self::DOC_RANK];
            echo "\nRELEVANCE: ".$document[self::RELEVANCE];
            echo "\nPROXIMITY: ".$document[self::PROXIMITY];
            echo "\nHEX POSTING:\n";
            echo "------------\n";
            echo wordwrap(toHexString($raw_postings[$j]), 80);
            if(isset($document[self::POSITION_LIST])) {
                echo "\nTERM OCCURRENCES IN DOCUMENT (Count starts at title):";
                echo "\n-------------------------".
                    "----------------------------\n";
                $i = 0;
                foreach($document[self::POSITION_LIST] as $position) {
                    printf("%09d ",$position);
                    $i++;
                    if($i >= 5) {
                        echo "\n";
                        $i = 0;
                    }
                }
                if($i != 0) { echo "\n"; }
            }
            $page = @$index->getPage($summary_offset);

            if(isset($page[self::TITLE])) {
                echo "SUMMARY TITLE:\n";
                echo "--------------\n";
                echo wordwrap($page[self::TITLE], 80)."\n";
            }

            if(isset($page[self::DESCRIPTION])) {
                echo "SUMMARY DESCRIPTION:\n";
                echo "--------------\n";
                echo $page[self::DESCRIPTION]."\n";
                }
            $j++;
        }
    }
    /**
     * Given a complete path to an archive returns its filename
     *
     * @param string $archive_path a path to a yioop or non-yioop archive
     * @return string its filename
     */
    function getArchiveName($archive_path)
    {
        $start = CRAWL_DIR."/archives/";
        if(strstr($archive_path, $start)) {
            $start_len = strlen($start);
            $name = substr($archive_path, $start_len);
        } else {
            $name = UrlParser::getDocumentFilename($archive_path);
        }
        return $name;
    }
    /**
     * Used to recompute the dictionary of an index archive -- either from
     * scratch using the index shard data or just using the current dictionary
     * but merging the tiers into one tier
     *
     * @param string $path file path to dictionary of an IndexArchiveBundle
     * @param int $max_tier tier up to which the dictionary tiers should be
     *     merge (typically a value greater than the max_tier of the
     *     dictionary)
     */
    function reindexIndexArchive($path, $max_tier = -1)
    {
        if($this->getArchiveKind($path) != "IndexArchiveBundle") {
            echo "\n$path ...\n".
                "  is not an IndexArchiveBundle so cannot be re-indexed\n\n";
            exit();
        }
        $shards = glob($path."/posting_doc_shards/index*");
        if(is_array($shards)) {
            if($max_tier == -1) {
                $dbms_manager = DBMS."Manager";
                $db = new $dbms_manager();
                $db->unlinkRecursive($path."/dictionary", false);
                IndexDictionary::makePrefixLetters($path."/dictionary");
            }
            $dictionary = new IndexDictionary($path."/dictionary");

            if($max_tier == -1) {
                $max_generation = 0;
                foreach($shards as $shard_name) {
                    $file_name = UrlParser::getDocumentFilename($shard_name);
                    $generation = (int)substr($file_name, strlen("index"));
                    $max_generation = max($max_generation, $generation);
                }
                for($i = 0; $i < $max_generation + 1; $i++) {
                    $shard_name = $path."/posting_doc_shards/index$i";
                    echo "\nShard $i\n";
                    $shard = new IndexShard($shard_name, $i,
                        NUM_DOCS_PER_GENERATION, true);
                    $dictionary->addShardDictionary($shard);
                }
                $max_tier = $dictionary->max_tier;
            }
            echo "\nFinal Merge Tiers\n";
            $dictionary->mergeAllTiers(NULL, $max_tier);
            $db->setWorldPermissionsRecursive($path."/dictionary");
            echo "\nReindex complete!!\n";
        } else {
            echo "\n$path ...\n".
                "  does not contain posting shards so cannot be re-indexed\n\n";

        }
    }

    /**
     * Outputs to stdout header information for a IndexArchiveBundle
     * bundle.
     *
     * @param array $info header info that has already been read from
     *     the description.txt file
     * @param string $archive_path file path of the folder containing the bundle
     */
    function outputInfoIndexArchiveBundle($info, $archive_path)
    {
        $more_info = unserialize($info['DESCRIPTION']);
        unset($info['DESCRIPTION']);
        $info = array_merge($info, $more_info);
        echo "Description: ".$info['DESCRIPTION']."\n";
        $generation_info = unserialize(
            file_get_contents("$archive_path/generation.txt"));
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
        if(is_array($info[self::DISALLOWED_SITES])) {
            foreach($info[self::DISALLOWED_SITES] as $site) {
                echo "   $site\n";
            }
        }
        echo "Page Rules:\n";
        if(isset($info[self::PAGE_RULES])) {
            foreach($info[self::PAGE_RULES] as $rule) {
                echo "   $rule\n";
            }
        }
        echo "\n";
    }
    /**
     * Outputs to stdout header information for a WebArchiveBundle
     * bundle.
     *
     * @param array $info header info that has already been read from
     *     the description.txt file
     * @param string $archive_path file path of the folder containing the bundle
     */
    function outputInfoWebArchiveBundle($info, $archive_path)
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
     * Adds a list of urls as a upcoming schedule for a given queue bundle.
     * Can be used to make a closed schedule startable
     *
     * @param string $timestamp for a queue bundle to add urls to
     * @param string $url_file_name name of file consist of urls to inject into
     *      the given crawl
     */
    function inject($timestamp, $url_file_name)
    {
        $admin = new AdminController();
        $machine_urls = $admin->model("machine")->getQueueServerUrls();
        $num_machines = count($machine_urls);
        $new_urls = file_get_contents($url_file_name);
        $inject_urls = $admin->convertStringCleanArray($new_urls);
        if(!$inject_urls || count($inject_urls) == 0) {
            echo "\nNo urls in $url_file_name to inject.\n\n";
            exit();
        }
        $crawl_model = $admin->model("crawl");
        $seed_info = $crawl_model->getCrawlSeedInfo($timestamp, $machine_urls);
        if(!$seed_info) {
            echo "\nNo queue bundle with timestamp: $timestamp.\n\n";
            exit();
        }
        $seed_info['seed_sites']['url'][] = "#\n#". date('r')."\n#";
        $seed_info['seed_sites']['url'] = array_merge(
            $seed_info['seed_sites']['url'], $inject_urls);
        $crawl_model->setCrawlSeedInfo($timestamp,
            $seed_info, $machine_urls);
        $crawl_model->injectUrlsCurrentCrawl($timestamp, $inject_urls,
            $machine_urls);
        echo "Urls injected!";
    }
    /**
     * Used to list out the pages/summaries stored in a bundle at
     * $archive_path. It lists to stdout $num many documents starting at $start.
     *
     * @param string $archive_path path to bundle to list documents for
     * @param int $start first document to list
     * @param int $num number of documents to list
     */
    function outputShowPages($archive_path, $start, $num)
    {
        $fields_to_print = array(
            self::URL => "URL",
            self::IP_ADDRESSES => "IP ADDRESSES",
            self::TIMESTAMP => "DATE",
            self::HTTP_CODE => "HTTP RESPONSE CODE",
            self::TYPE => "MIMETYPE",
            self::ENCODING => "CHARACTER ENCODING",
            self::DESCRIPTION => "DESCRIPTION",
            self::PAGE => "PAGE DATA");
        $archive_type = $this->getArchiveKind($archive_path);
        if($archive_type === false) {
            $this->badFormatMessageAndExit($archive_path);
        }

        $nonyioop = false;
        //for yioop archives we set up a dummy iterator
        $iterator =  (object) array();
        $iterator->end_of_iterator = false;
        if($archive_type == "IndexArchiveBundle") {
            $info = $archive_type::getArchiveInfo($archive_path);
            $num = min($num, $info["COUNT"] - $start);
            $generation_info = unserialize(
                file_get_contents("$archive_path/generation.txt"));
            $num_generations = $generation_info['ACTIVE']+1;
            $archive = new WebArchiveBundle($archive_path."/summaries");
        } else if ($archive_type == "WebArchiveBundle") {
            $info = $archive_type::getArchiveInfo($archive_path);
            $num = min($num, $info["COUNT"] - $start);
            $num_generations = $info["WRITE_PARTITION"]+1;
            $archive = new WebArchiveBundle($archive_path);
        } else {
            $nonyioop = true;
            $num_generations = 1;
            //for non-yioop archives we set up a real iterator
            $iterator = $this->instantiateIterator($archive_path,
                $archive_type);
            if($iterator === false) {
                $this->badFormatMessageAndExit($archive_path);
            }
        }
        if(!$nonyioop) {
            if(isset($this->tmp_results)) unset($this->tmp_results);
        }
        $num = max($num, 0);
        $total = $start + $num;
        $seen = 0;
        $generation = 0;
        while(!$iterator->end_of_iterator &&
            $seen < $total && $generation < $num_generations) {
            if($nonyioop) {
                $partition = (object) array();
                $partition->count = 1;
                $iterator->seekPage($start);
                if($iterator->end_of_iterator) { break; }
                $seen += $start;
            } else {
                $partition = $archive->getPartition($generation, false);
                if($partition->count < $start && $seen < $start) {
                    $generation++;
                    $seen += $partition->count;
                    continue;
                }
            }
            $seen_generation = 0;
            while($seen < $total && $seen_generation < $partition->count) {
                if($nonyioop) {
                    $num_to_get = min(self::MAX_BUFFER_DOCS, $total - $seen);
                    $objects = $iterator->nextPages($num_to_get);
                    $seen += count($objects);
                } else {
                    $num_to_get = min($total - $seen,
                        $partition->count - $seen_generation,
                        self::MAX_BUFFER_DOCS);
                    $objects = $partition->nextObjects($num_to_get);
                    $seen += $num_to_get;
                    $seen_generation += $num_to_get;
                }
                $num_to_get = count($objects);
                if($seen >= $start) {
                    $num_to_show = min($seen - $start, $num_to_get);
                    $cnt = 0;
                    $first = $num_to_get - $num_to_show;
                    foreach($objects as $pre_object) {
                        if($cnt >= $first) {
                            $out = "";
                            if($nonyioop) {
                                $object = $pre_object;
                            } else {
                                if(!isset($pre_object[1])) continue;
                                $object = $pre_object[1];
                            }
                            if(isset($object[self::TIMESTAMP])) {
                                $object[self::TIMESTAMP] =
                                    date("r", $object[self::TIMESTAMP]);
                            }
                            foreach($fields_to_print as $key => $name) {
                                if(isset($object[$key])) {
                                    $out .= "[$name]\n";
                                    if($key != self::IP_ADDRESSES) {
                                        $out .= $object[$key]."\n";
                                    } else {
                                        foreach($object[$key] as $address) {
                                            $out .= $address."\n";
                                        }
                                    }
                                }
                            }
                            $out .= "==========\n\n";
                            echo "BEGIN ITEM, LENGTH:".strlen($out)."\n";
                            echo $out;
                        }
                        $cnt++;
                    }
                }
                if($objects == NULL) break;
            }
            $generation++;
        }
        if(isset($this->tmp_results)) {
            //garbage collect savepoint folder for non-yioop archives
            $dbms_manager = DBMS."Manager";
            $db = new $dbms_manager();
            $db->unlinkRecursive($this->tmp_results);
        }
    }
    /**
     * Used to recompute both the index shards and the dictionary
     * of an index archive. The first step involves re-extracting the
     * word into an inverted index from the summaries' web_archives.
     * Then a reindex is done.
     *
     * @param string $archive_path file path to a IndexArchiveBundle
     */
    function rebuildIndexArchive($archive_path)
    {
        $archive_type = $this->getArchiveKind($archive_path);
        if($archive_type != "IndexArchiveBundle") {
            $this->badFormatMessageAndExit($archive_path);
        }
        $info = $archive_type::getArchiveInfo($archive_path);
        $tmp = unserialize($info["DESCRIPTION"]);
        $video_sources = $tmp[self::VIDEO_SOURCES];
        $generation_info = unserialize(
            file_get_contents("$archive_path/generation.txt"));
        $num_generations = $generation_info['ACTIVE']+1;
        $archive = new WebArchiveBundle($archive_path."/summaries");
        $seen = 0;
        $generation = 0;
        $keypad = "\x00\x00\x00\x00";
        while($generation < $num_generations) {
            $partition = $archive->getPartition($generation, false);
            $shard_name = $archive_path."/posting_doc_shards/index$generation";
            crawlLog("Processing partition $generation");
            if(file_exists($shard_name)) {
                crawlLog("..Unlinking old shard $generation");
                @unlink($shard_name);
            }
            $shard = new IndexShard($shard_name, $generation,
                NUM_DOCS_PER_GENERATION, true);
            $seen_partition = 0;
            while($seen_partition < $partition->count) {
                $num_to_get = min($partition->count - $seen_partition,
                    8000);
                $offset = $partition->iterator_pos;
                $objects = $partition->nextObjects($num_to_get);
                $cnt = 0;
                foreach($objects as $object) {
                    $cnt++;
                    $site = $object[1];
                    if(isset($site[self::TYPE]) && $site[self::TYPE] == "link"){
                        $is_link = true;
                        $doc_keys = $site[self::HTTP_CODE];
                        $site_url = $site[self::TITLE];
                        $host =  UrlParser::getHost($site_url);
                        $link_parts = explode('|', $site[self::HASH]);
                        if(isset($link_parts[5])) {
                            $link_origin = $link_parts[5];
                        } else {
                            $link_origin = $site_url;
                        }
                        $meta_ids = PhraseParser::calculateLinkMetas($site_url,
                            $host, $site[self::DESCRIPTION], $link_origin);
                        $link_to = "LINK TO:";
                    } else {
                        $is_link = false;
                        $site_url = str_replace('|', "%7C", $site[self::URL]);
                        $host = UrlParser::getHost($site_url);
                        $doc_keys = crawlHash($site_url, true) .
                            $site[self::HASH]."d". substr(crawlHash(
                            $host."/",true), 1);
                        $meta_ids =  PhraseParser::calculateMetas($site,
                            $video_sources);
                        $link_to = "";
                    }
                    $so_far_cnt = $seen_partition + $cnt;
                    $time_out_message = "..still processing $so_far_cnt ".
                        "of {$partition->count} in partition $generation.".
                        "\n..Last processed was: ".
                        ($seen + 1).". $link_to$site_url. ";
                    crawlTimeoutLog($time_out_message);
                    $seen++;
                    $word_lists = array();
                    /*
                        self::JUST_METAS check to avoid getting sitemaps in
                        results for popular words
                     */
                    $lang = NULL;
                    if(!isset($site[self::JUST_METAS])) {
                        $host_words = UrlParser::getWordsIfHostUrl($site_url);
                        $path_words = UrlParser::getWordsLastPathPartUrl(
                            $site_url);
                        if($is_link) {
                            $phrase_string = $site[self::DESCRIPTION];
                        } else {
                            $phrase_string = $host_words." ".$site[self::TITLE]
                                . " ". $path_words . " "
                                . $site[self::DESCRIPTION];
                        }
                        if(isset($site[self::LANG])) {
                            $lang = guessLocaleFromString(
                                mb_substr($site[self::DESCRIPTION], 0,
                                AD_HOC_TITLE_LENGTH), $site[self::LANG]);
                        }
                        $word_lists =
                            PhraseParser::extractPhrasesInLists($phrase_string,
                                $lang);
                        $len = strlen($phrase_string);
                        if(PhraseParser::computeSafeSearchScore($word_lists,
                            $len) < 0.012) {
                            $meta_ids[] = "safe:true";
                            $safe = true;
                        } else {
                            $meta_ids[] = "safe:false";
                            $safe = false;
                        }
                    }
                    if(isset($site[self::USER_RANKS]) &&
                        count($site[self::USER_RANKS]) > 0) {
                        $score_keys = "";
                        foreach($site[self::USER_RANKS] as $label => $score) {
                            $score_keys .= packInt($score);
                        }
                        if(strlen($score_keys) % 8 != 0) {
                            $score_keys .= $keypad;
                        }
                        $doc_keys .= $score_keys;
                    }
                    $shard->addDocumentWords($doc_keys, $offset,
                        $word_lists, $meta_ids,
                        PhraseParser::$materialized_metas, true, false);
                    $offset = $object[0];
                }
                $seen_partition += $num_to_get;
            }
            $shard->save(false, true);
            $generation++;
        }
        $this->reindexIndexArchive($archive_path);
    }

    /**
     * Used to create an archive_bundle_iterator for a non-yioop archive
     * As these iterators sometimes make use of a folder to store savepoints
     * We create a temporary folder for this purpose in the current directory
     * This should be garbage collected elsewhere.
     *
     * @param string $archive_path path to non-yioop archive
     * @param string $iterator_type name of archive_bundle_iterator used to
     *     iterate over archive.
     * @param return an ArchiveBundleIterator of the correct type using
     *     a temporary folder to store savepoints
     */
    function instantiateIterator($archive_path, $iterator_type)
    {
        $iterate_timestamp = filectime($archive_path);
        $result_timestamp = strval(time());
        $this->tmp_results = WORK_DIRECTORY.'/temp/TmpArchiveExtract'.
            $iterate_timestamp;
        $dbms_manager = DBMS."Manager";
        $db = new $dbms_manager();
        if(file_exists($this->tmp_results)) {
            $db->unlinkRecursive($this->tmp_results);
        }
        @mkdir($this->tmp_results);
        $iterator_class = "{$iterator_type}Iterator";
        $iterator = new $iterator_class($iterate_timestamp, $archive_path,
            $result_timestamp, $this->tmp_results);
        $db->setWorldPermissionsRecursive($this->tmp_results);
        return $iterator;
    }


    /**
     * Given a folder name, determines the kind of bundle (if any) it holds.
     * It does this based on the expected location of the description.txt file,
     * or arc_description.ini (in the case of a non-yioop archive)
     *
     * @param string $archive_path the path to archive folder
     * @return string the archive bundle type, either: WebArchiveBundle or
     *     IndexArchiveBundle
     */
    function getArchiveKind($archive_path)
    {
        if(file_exists("$archive_path/description.txt")) {
            return "WebArchiveBundle";
        }
        if(file_exists("$archive_path/summaries/description.txt")) {
            return "IndexArchiveBundle";
        }
        $desc_path = "$archive_path/arc_description.ini";
        if(file_exists($desc_path)) {
            $desc = parse_ini_with_fallback($desc_path);
            if(!isset($desc['arc_type'])) {
                return false;
            }
            return $desc['arc_type'];
        }
        return false;
    }
    /**
     * Outputs the "hey, this isn't a known bundle message" and then exit()'s.
     *
     * @param string $archive_name name or path to what was supposed to be
     *     an archive
     * @param string $allowed_archives a string list of archives types
     *     that $archive_name could belong to
     */
    function badFormatMessageAndExit($archive_name,
        $allowed_archives = "web or index")
    {
        echo <<< EOD

$archive_name does not appear to be a $allowed_archives archive bundle

EOD;
        exit();
    }

    /**
     * Outputs the "how to use this tool message" and then exit()'s.
     */
    function usageMessageAndExit()
    {
        echo  <<< EOD

arc_tool is used to look at the contents of WebArchiveBundles and
IndexArchiveBundles. It will look for these using the path provided or
will check in the Yioop! crawl directory as a fall back.

The available commands for arc_tool are:

php arc_tool.php dict bundle_name word
    // returns index dictionary records for word stored in index archive bundle.

php arc_tool.php info bundle_name
    // return info about documents stored in archive.

php arc_tool.php inject timestamp file
    // injects the urls in file as a schedule into crawl of given timestamp

php arc_tool.php list
    /* returns a list of all the archives in the Yioop! crawl directory,
       including non-Yioop! archives in the /archives sub-folder.*/

php arc_tool.php mergetiers bundle_name max_tier
    // merges tiers of word dictionary into one tier up to max_tier

php arc_tool.php posting bundle_name generation offset
    or
php arc_tool.php posting bundle_name generation offset num
    /* returns info about the posting (num many postings) in bundle_name at
       the given generation and offset */

php arc_tool.php rebuild bundle_name
    /*  re-extracts words from summaries files in bundle_name into index shards
        then builds a new dictionary */

php arc_tool.php reindex bundle_name
    // reindex the word dictionary in bundle_name using existing index shards

php arc_tool.php shard bundle_name generation
    /* Prints information about the number of words and frequencies of words
       within the generation'th index shard in the bundle */

php arc_tool.php show bundle_name start num
    /* outputs items start through num from bundle_name or name of
       non-Yioop archive crawl folder */

EOD;
        exit();
    }
}

$arc_tool =  new ArcTool();
$arc_tool->start();
?>
