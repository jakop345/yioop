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
 * Used to iterate through the records of a database stored in a
 * DatabaseArchiveBundle folder. Database is a collection of tables with
 * various rows in each table. Iteration would be for the purpose of making
 * an index of each of these records.
 *
 * @author Tanmayee Potluri
 * @package seek_quarry
 * @subpackage iterator
 * @see WebArchiveBundle
 */
class DatabaseArchiveBundleIterator extends ArchiveBundleIterator
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
     *  current number of record in the current database
     *  @var int
     */
    var $current_page_num;
    /**
     * The number of database records in this database archive bundle
     *  @var int
     */
    var $num_of_records;
    /**
     *  Array of database records according to the query specified by the user
     *  @var array
     */
    var $records;
    /**
     *  Array of fields of database record specified by the user
     *  @var array
     */
    var $fields;
    /**
     *  Array of field names in database record specified by the user
     *  @var array
     */
    var $field_names;
    /**
     *  Array of database fieldtypes specified by the user
     *  @var array
     */
    var $field_types;
    /**
     *  Database handle for a database
     *  @var resource
     */
    var $db_handle;
    /**
     *  Whether database exists or not
     *  @var resource
     */
    var $db_found;
    /**
     *  Array of database connection details to connect to database
     *  @var array
     */
    var $databaseConnectionsArray;
    /**
     *  Host name for the database
     *  @var string
     */
    var $host;
    /**
     *  User Name for the localhost
     *  @var string
     */
    var $user_name;
    /**
     *  Password for the connection
     *  @var string
     */
    var $password;
    /**
     *  Name of the database for the records
     *  @var string
     */
    var $database;
    /**
     *  Query to retrieve the records to be indexed from the database
     *  @var array
     */
    var $query;
    /**
     *  Constants required for database archive_bundle_iterator
     *  @var const
     */
    const DATABASE_CONNECTION_DETAILS_FILE = 'database_connection_details.txt';

    /**
     * Creates a database archive iterator with the given parameters.
     * @param string $iterate_timestamp timestamp of the arc archive bundle to
     *      iterate  over the pages of
     * @param string $result_timestamp timestamp of the arc archive bundle
     *      results are being stored in
     */
    function __construct($iterate_timestamp, $iterate_dir,
        $result_timestamp, $result_dir)
    {
        $this->iterate_timestamp = $iterate_timestamp;
        $this->iterate_dir = $iterate_dir;
        $this->result_timestamp = $result_timestamp;
        $this->result_dir = $result_dir;
        $this->index_dir = CRAWL_DIR."/cache/IndexData".$this->result_timestamp;
        $temp_array = explode("/", $this->index_dir);
        $temp_array_len = count($temp_array);

        for($i = 1;$i<$temp_array_len;$i++){
            if($temp_array[$i-1]=="htdocs"){
                while($temp_array[$i] != "cache"){
                    $this->path_for_html_files .= $temp_array[$i]."/";
                    $i++;
                }
                break;
            }
        }
        if(file_exists("{$this->iterate_dir}/".
            self::DATABASE_CONNECTION_DETAILS_FILE))
        {
            $database_connection_details_info = unserialize
               (file_get_contents(
               "{$this->iterate_dir}/".self::DATABASE_CONNECTION_DETAILS_FILE));
            file_put_contents("{$this->index_dir}/database_connection_details".
                $this->result_timestamp.".txt",
                serialize($database_connection_details_info));
            @unlink("{$this->iterate_dir}/".
                self::DATABASE_CONNECTION_DETAILS_FILE);
        }
        if(!is_dir("{$this->index_dir}/HTML_FILES")){
            mkdir("{$this->index_dir}/HTML_FILES");
        }
        $this->createRecords();

        if(file_exists("{$this->result_dir}/iterate_status.txt")) {
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
     * @return bool false we assume arc files were crawled according to
     *      OPIC and so we use the default doc_depth to estimate page importance
     */
    function weight(&$site)
    {
        return false;
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
        $info['database_iterator'] = $this->database_iterator;
        file_put_contents("{$this->result_dir}/iterate_status.txt",
            serialize($info));
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
        $this->database_iterator = $info['database_iterator'];
        return $info;
    }

    /**
     * Creates Records array containing all the records to satisfying the query
     */
    function createRecords()
    {
        $this->databaseConnectionsArray = unserialize(file_get_contents(
            "{$this->index_dir}/database_connection_details".
            $this->result_timestamp.".txt"));
        $this->host = $this->databaseConnectionsArray['HOSTNAME'];
        $this->user_name = $this->databaseConnectionsArray['USERNAME'];
        $this->password = $this->databaseConnectionsArray['PASSWORD'];
        $this->database = $this->databaseConnectionsArray['DATABASENAME'];
        $this->query = $this->databaseConnectionsArray['QUERY'];
        $this->db_handle = mysql_connect($this->host, $this->user_name,
            $this->password);
        $this->db_found = mysql_select_db($this->database, $this->db_handle);

        /*If database exists*/
        if ($this->db_found) {
            $result1 = mysql_query($this->query);
            $num_fields = mysql_num_fields($result1);
            for($i = 0; $i < $num_fields; $i++) {
                $this->field_names[$i] = mysql_field_name($result1, $i);
            }
            while ($row = mysql_fetch_row($result1)) {
                $this->records[] = $row;
            }
            $this->num_of_records = count($this->records);
            mysql_free_result($result1);
            mysql_close($db_handle);
        }
    }
    /**
     * Gets the next at most $num many records from the iterator. It might
     * return less than $num many documents if the end of the bundle is reached.
     * @param int $num number of docs to get
     * @return array associative arrays for $num pages
     */
    function nextPages($num)
    {
        $pages = array();
        for($i = 0; $i < $num; $i++) {
            $this->current_page_num++;
            $page = $this->nextPage();
            if($this->current_page_num >= $this->num_of_records) {
                $this->end_of_iterator = true;
                break;
            }
            else {
                $pages[] = $page;
            }
        }

        $this->saveCheckpoint();
        return $pages;
    }


    /**
     * Gets the next record from the iterator
     * @return array associative array for record
     */
    function nextPage()
    {
        $site = array();
        $field_nd = "";
        $html_page = "";
        $temp_record = array();
        $temp_record = $this->records[$this->current_page_num];
        $dom = new DOMDocument('1.0');
        $root =$dom->createElement('html');
        $root = $dom->appendChild($root);
        $head = $dom->createElement('head');
        $head = $root->appendChild($head);
        $title = $dom->createElement('title');
        $title = $head->appendChild($title);
        $recordTitle = "Database Record".$this->current_page_num;
        $text = $dom->createTextNode($recordTitle);
        $text = $title->appendChild($text);
        $body = $dom->createElement('body');
        $body = $root->appendChild($body);
        $field = $dom->createElement('p');
        $field = $body->appendChild($field);
        $fieldnames_len = count($this->field_names);
        for($i = 0; $i < $fieldnames_len; $i++) {
                $field_nd .= $this->field_names[$i]." : ".$temp_record[$i]
                ."<br>";
        }
        $text1 = $dom->createTextNode($field_nd);
        $text1 = $field->appendChild($text1);
        $desc = $dom->createElement('p');
        $desc = $body->appendChild($desc);
        $text3 = "This is database record ".$this->current_page_num;
        $text2 = $dom->createTextNode($text3);
        $text2 = $desc->appendChild($text2);
        $site[self::PAGE] =$dom->saveHTML();
        $html_page = "<html><head><title>DatabaseRecord".
            $this->current_page_num.
            "</title></head><body><h1>The details of the database record are:".
            "<br></h1><h3>".$field_nd."</h3></body></html>";
        file_put_contents("{$this->index_dir}/HTML_FILES/databaserecord".
            $this->current_page_num.".php",$html_page);
        $site[self::URL] = "http://localhost/".$this->path_for_html_files.
            "cache/IndexData".$this->result_timestamp.
            "/HTML_FILES/databaserecord".$this->current_page_num.".php";
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