<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2013  Chris Pollett chris@pollett.org
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
 * @author Tanmayee Potluri
 * @package seek_quarry
 * @subpackage iterator
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *Loads base class for iterating
 */
require_once BASE_DIR.
    '/lib/archive_bundle_iterators/archive_bundle_iterator.php';

/**
 * Used to iterate through the collection of log files stored in
 * a WebArchiveBundle folder. Log is the file format which has the
 * activities the system or a server performs. Iteration would be
 * for the purpose making an index of these files.
 *
 * @author Tanmayee Potluri
 * @package seek_quarry
 * @subpackage iterator
 * @see WebArchiveBundle
 */

class LogArchiveBundleIterator extends ArchiveBundleIterator
    implements CrawlConstants
{
    /**
     * The path to the directory containing the archive partitions to be
     * iterated over.
     * @var string
     */
    var $iterate_dir;

    /**
     * The path to the directory where the iteration status is stored.
     * @var string
     */
    var $result_dir;

    /**
     * The path to the directory where all html files are stored
     * and used to point to.
     * @var string
     */
    var $index_dir;

    /**
     * The part of the path to the directory where all html files are stored
     * and used to point to.
     * @var string
     */
    var $path_for_html_files;

    /**
     * The number of log files in this log archive bundle
     *  @var int
     */
    var $num_partitions;

    /**
     *  current record number in the master log file
     *  @var int
     */
    var $current_page_num;

    /**
     *  number of records in the master log file
     *  @var int
     */
    var $num_of_records;

    /**
     *  Array of log records in the master log file in the directory
     *  @var array
     */
    var $records;

    /**
     *  Array of filenames of log files in this directory (glob order)
     *  @var array
     */
    var $partitions;

    /**
     *  Array of fields of log file specified by the user
     *  @var array
     */
    var $fields;

    /**
     *  Array of fieldnames specified by the user
     *  @var array
     */
    var $field_names;

    /**
     *  Array of fieldtypes specified by the user
     *  @var array
     */
    var $field_types;

    /**
     *  Array of fields in each record separately
     *  @var array
     */
    var $page_info;

    /**
     *  Array of regular expressions for all the data types
     *  @var array
     */
    var $regular_exprs = array(
        'IP_Address' => '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/',
        'Timestamp' => '/\[[^:]+:\d+:\d+:\d+ [^\]]+\]/',
        'Request' => '/(GET|HEAD|POST|PUT|DELETE|TRACE|OPTIONS|CONNECT)+[^"]*/',
        'Status Code'=> '/\s[1-5]\d{2}\s/',
        'Int' => '/\s[0-9]+\s/',
        'URL'        => '/(http|https|ftp):\/\/[A-Za-z0-9][A-Za-z0-9_-]*[\/]*(?:.[A-Za-z0-9][A-Za-z0-9_-]*[\/]*)+:?(d*)[\/]*/',
        'User Agent' => '/"([a-zA-Z0-9][^"]+)"/');

    /**
     *  Array of log fields type in drop down box in the UI
     *  @var array
     */
    var $logfields_type_ddm = array(
        1=>'IP_Address',
        2=>'Timestamp',
        3=>'URL',
        4=>'Status Code',
        5=>'User Agent',
        6=>'Request',
        7=>'Int');

    /**
     *  Constants required for log archive_bundle_iterator
     *  @var const
     */
    const FIELDS_DATA_FILE = 'fields_data.txt';
    const MASTER_LOG_FILE = 'master.log';

    /**
     * Creates a log archive iterator with the given parameters.
     *
     * @param string $iterate_timestamp timestamp of the log archive bundle to
     *      iterate  over the pages of
     * @param string $result_timestamp timestamp of the log archive bundle
     *      results are being stored in
     */
    function __construct($iterate_timestamp, $iterate_dir,
        $result_timestamp, $result_dir)
    {
        $this->path_for_html_files = "";
        $this->iterate_timestamp = $iterate_timestamp;
        $this->iterate_dir = $iterate_dir;
        $this->result_timestamp = $result_timestamp;
        $this->result_dir = $result_dir;
        $this->index_dir = CRAWL_DIR."/cache/IndexData".$this->result_timestamp;
        $temp_array = explode("/", $this->index_dir);
        $temp_array_len = count($temp_array);

        for($i = 1; $i < $temp_array_len; $i++) {
            if($temp_array[$i-1] == "htdocs") {
                while($temp_array[$i] != "cache") {
                    $this->path_for_html_files .= $temp_array[$i]."/";
                    $i++;
                }
                break;
            }
        }
        if(file_exists("{$this->iterate_dir}/".self::FIELDS_DATA_FILE)) {
            $fields_data = unserialize(file_get_contents(
                               "{$this->iterate_dir}/".self::FIELDS_DATA_FILE));
            file_put_contents(
                "{$this->index_dir}/fields_data".$this->result_timestamp.".txt",
                serialize($fields_data));
            @unlink("{$this->iterate_dir}/".self::FIELDS_DATA_FILE);
        }
        if(!is_dir("{$this->index_dir}/HTML_FILES")) {
            mkdir("{$this->index_dir}/HTML_FILES");
        }
        $this->partitions = array();
        foreach(glob("{$this->iterate_dir}/*.log") as $filename) {
            if(strpos($filename,self::MASTER_LOG_FILE)!= true) {
                $this->partitions[] = $filename;
            }
        }
        $this->num_partitions = count($this->partitions);
        $this->records = $this->createMasterAndRecords();
        $this->num_of_records = count($this->records);
        if(file_exists("{$this->result_dir}/iterate_status.txt")){
            $this->restoreCheckpoint();
        }
        else {
            $this->reset();
        }
    }

    /**
     * Estimates the important of the site according to the weighting of
     * the particular archive iterator
     * @param $site an associative array containing info about a web page
     * @return value 1 we assume all log files crawled have the same
     *  page importance
     */
    function weight(&$site)
    {
        return 1;
    }

    /**
     * Resets the iterator to the start of the archive bundle
     */
    function reset()
    {
        $this->current_page_num = -1;
        $this->end_of_iterator = false;
        @unlink("{$this->result_dir}/iterate_status.txt");
    }

    /**
     * Saves the current state so that a new instantiation can pick up just
     * after the last batch of pages extracted.
     */
    function saveCheckpoint($info = array())
    {
        $info['end_of_iterator'] = $this->end_of_iterator;
        $info['current_page_num'] = $this->current_page_num;
        $info['log_iterator'] = $this->log_iterator;
        file_put_contents("{$this->result_dir}/iterate_status.txt",
            serialize($info));
        if($this->end_of_iterator == true){
            @unlink("{$this->iterate_dir}/".self::MASTER_LOG_FILE);
        }
    }

    /**
     * Restores state from a previous instantiation, after the last batch of
     * pages extracted.
     */
    function restoreCheckpoint()
    {
        $info = unserialize(file_get_contents(
            "{$this->result_dir}/iterate_status.txt"));
        $this->end_of_iterator = $info['end_of_iterator'];
        $this->current_page_num = $info['current_page_num'];
        $this->log_iterator = $info['log_iterator'];
        return $info;
    }

    /**
     * Pulls data from all the log files in the directory and place it in
     * the master log file and splits the file into log records
     * @return array  of log records from the master log file
     */
    function createMasterAndRecords(){
        if(file_exists("{$this->iterate_dir}/".self::MASTER_LOG_FILE)) {
            @unlink("{$this->iterate_dir}/".self::MASTER_LOG_FILE);
        }
        for ($i=0; $i < $this->num_partitions; $i++){
            $file_data = file_get_contents($this->partitions[$i]);
            file_put_contents("{$this->iterate_dir}/".self::MASTER_LOG_FILE,
                 $file_data, FILE_APPEND);
        }
        $recordArray = explode("\n",
                file_get_contents("{$this->iterate_dir}/".
                        self::MASTER_LOG_FILE));

        return $recordArray;
    }

    /**
     * Unserializes the array of log records stored when save
     * options is clicked and stores them in the global arrays.
     */
     function getFieldDetails()
     {
        $fieldArray = unserialize(file_get_contents(
            "{$this->index_dir}/fields_data".$this->result_timestamp.".txt"));
        foreach($fieldArray as $field=>$field_nt){
            $matches = explode("::",$field_nt);
            $this->fields[] = $field;
            $this->field_names[] = $matches[0];
            $this->field_types[] = $matches[1];
        }
     }

    /**
     * Takes the log record as input and parses the log record and returns
     * the array containing the details of each record.
     *
     * @param string $record single log record in a file
     * @return array $matches matched fields of a log record
     */
     function parseLogRecord($record)
     {
        $content = "";
        $field_types_len = count($this->field_types);
        for($j = 0; $j < $field_types_len; $j++) {
            $content = "";
            $matches = array();
            preg_match(
        $this->regular_exprs[$this->logfields_type_ddm[$this->field_types[$j]]],
        $record,$matches);
            if (count($matches)>0) {
                $spaces_removed = trim($matches[0]);
                $record = str_replace($spaces_removed,"",$record);
                $return_page[$this->logfields_type_ddm[$this->field_types[$j]]]
                        = $matches[0];
                }
                else {
                $return_page[$this->logfields_type_ddm[$this->field_types[$j]]]
                        = "-";
                }
        }
        return $return_page;
     }

    /**
     * Gets the next at most $num many docs from the iterator. It might return
     * less than $num many documents if the partition changes or the end of the
     * bundle is reached.
     *
     * @param int $num number of docs to get
     * @return array associative arrays for $num pages
     */
    function nextPages($num)
    {
        $this->getFieldDetails();
        $pages = array();
        $page_count = 0;
        for($i = 0; $i < $num; $i++) {
            $this->current_page_num++;
            $this->page_info
               = $this->parseLogRecord($this->records[$this->current_page_num]);
            $page = $this->nextPage();
            if($this->current_page_num >= $this->num_of_records) {
               $this->end_of_iterator = true;
               break;
            }
            else {
               $pages[] = $page;
               $page_count++;
           }
        }
        $this->saveCheckpoint();
        return $pages;
    }


    /**
     * Gets the next doc from the iterator
     * @return array associative array for doc
     */
    function nextPage()
    {
        $site = array();
        $field_nt = "";
        $fields_count = count($this->page_info);
        $dom = new DOMDocument('1.0');
        $root =$dom->createElement('html');
        $root = $dom->appendChild($root);
        $head = $dom->createElement('head');
        $head = $root->appendChild($head);
        $title = $dom->createElement('title');
        $title = $head->appendChild($title);
        for($i=0;$i<$fields_count;$i++){
            if($this->logfields_type_ddm[$this->field_types[$i]] =="Request"
                && $this->page_info['Request'] !="-") {
                $recordTitle = "Line ".$this->current_page_num.":".
        $this->page_info[$this->logfields_type_ddm[$this->field_types[$i]]];
                break;
                }
        }
        if($recordTitle == ""){
                $recordTitle = "Line ".$this->current_page_num;
        }
        $text = $dom->createTextNode($recordTitle);
        $text = $title->appendChild($text);
        $body = $dom->createElement('body');
        $body = $root->appendChild($body);
        $field = $dom->createElement('p');
        $field = $body->appendChild($field);
        for($i=0;$i<$fields_count;$i++){
            $field_nt .= $this->field_names[$i].":".
        $this->page_info[$this->logfields_type_ddm[$this->field_types[$i]]]
        ."<br/>";
        }
        $text1 = $dom->createTextNode($field_nt);
        $text1 = $field->appendChild($text1);
        $desc = $dom->createElement('p');
        $desc = $body->appendChild($desc);
        $text3 = "This is line ".$this->current_page_num;
        $text2 = $dom->createTextNode($text3);
        $text2 = $desc->appendChild($text2);
        $site[self::PAGE] =$dom->saveHTML();
        $html_page = "<html><head><title>LogRecord".$this->current_page_num.
                "</title></head><body><h1>".
                "The details of the log record are: <br/></h1><h3>".
                $field_nt."</h3></body></html>";
        file_put_contents("{$this->index_dir}/HTML_FILES/logrecord".
                $this->current_page_num.".php",$html_page);
        $site[self::URL] = "http://localhost/".$this->path_for_html_files.
                "cache/IndexData".$this->result_timestamp.
                "/HTML_FILES/logrecord"
                .$this->current_page_num.".php";
        $site[self::TYPE] ="text/html";
        $site[self::HTTP_CODE] = 200;
        $site[self::ENCODING] = "UTF-8";
        $site[self::SERVER] = "unknown";
        $site[self::SERVER_VERSION] = "unknown";
        $site[self::OPERATING_SYSTEM] = "unknown";
        $site[self::HASH] = FetchUrl::computePageHash($site[self::PAGE]);
        $site[self::WEIGHT] = 1;

        return $site;
    }
}
?>