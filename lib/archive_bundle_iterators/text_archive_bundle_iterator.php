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
 * @author Chris Pollett chris@pollett.org
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

/** For bzip2 compression*/
require_once BASE_DIR.'/lib/bzip2_block_iterator.php';

/** For webencode */
require_once BASE_DIR.'/lib/utility.php';

/**
 * Used to iterate through the records of a collection of text or compressed
 * text-oriented records
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see WebArchiveBundle
 */
class TextArchiveBundleIterator extends ArchiveBundleIterator
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
     * The number of arc files in this arc archive bundle
     *  @var int
     */
    var $num_partitions;
    /**
     *  Counting in glob order for this arc archive bundle directory, the
     *  current active file number of the arc file being process.
     *
     *  @var int
     */
    var $current_partition_num;
    /**
     *  current number of pages into the current arc file
     *  @var int
     */
    var $current_page_num;
    /**
     *  current byte offset into the current arc file
     *  @var int
     */
    var $current_offset;
    /**
     *  Array of filenames of arc files in this directory (glob order)
     *  @var array
     */
    var $partitions;
    /**
     *  File handle for current archive file
     *  @var resource
     */
    var $fh;
    /**
     *  Used to buffer data from the currently opened file
     *  @var string
     */
    var $buffer;

    /**
     *  Starting delimiters for records
     *  @var string
     */
    var $start_delimiter;

    /**
     *  Ending delimiters for records
     *  @var string
     */
    var $end_delimiter;

    /**
     *  File name to write this archive iterator status messages to
     *  @var string
     */
    var $status_filename;

    /**
     * If gzip is being used a buffer file is also employed to
     * try to reduce the number of calls to gzseek. $gzh is a
     * filehandle for the buffer file
     *
     * @var resource
     */
    var $gzh;

    /**
     * Which block of self::GZ_BUFFER_SIZE from the current archive
     * file is stored in the file $this->gz_buffer_filename
     *
     * @var int
     */
    var $gz_block_num;

    /**
     * Name of a buffer file to be used to reduce gzseek calls in the
     * case where gzip compression is being used
     *
     * @var string
     */
    var $gz_buffer_filename;

    /**
     * Name of 
     *
     * @var string
     */
    var $switch_partition_callback_name = NULL;

    /**
     * How many bytes at a time should be read from the current archive
     * file into the gz buffer file when gzip is being used
     */
    const GZ_BUFFER_SIZE = 32000000;

    /**
     * Creates an text archive iterator with the given parameters.
     *
     * @param string $iterate_timestamp timestamp of the arc archive bundle to
     *      iterate  over the pages of
     * @param string $iterate_dir folder of files to iterate over
     * @param string $result_timestamp timestamp of the arc archive bundle
     *      results are being stored in
     * @param string $result_dir where to write last position checkpoints to
     * @param array $ini describes start_ and end_delimiter, file_extension,
     *      encoding, and compression method used for pages in this archive
     */
    function __construct($iterate_timestamp, $iterate_dir,
        $result_timestamp, $result_dir, $ini = array())
    {
        $this->iterate_timestamp = $iterate_timestamp;
        $this->iterate_dir = $iterate_dir;
        $this->result_timestamp = $result_timestamp;
        $this->result_dir = $result_dir;
        $this->partitions = array();
        if($ini == array()) {
            $ini = parse_ini_file("{$this->iterate_dir}/arc_description.ini");
        }
        $extension = $ini['file_extension'];
        if(isset($ini['compression'])) {
            $this->compression = $ini['compression'];
        } else {
            $this->compression = "plain";
        }
        if(isset($ini['start_delimiter'])) {
            $this->start_delimiter =addRegexDelimiters($ini['start_delimiter']);
        } else {
            $this->start_delimiter = "";
        }
        if(isset($ini['end_delimiter'])) {
            $this->end_delimiter = addRegexDelimiters($ini['end_delimiter']);
        } else {
            $this->end_delimiter = "";
        }
        if($this->start_delimiter == "" && $this->end_delimiter == "") {
            crawlLog("At least one of start or end delimiter must be set!!");
            exit();
        }
        if($this->end_delimiter == "") {
            $this->delimiter = $this->start_delimiter;
        } else {
            $this->delimiter = $this->end_delimiter;
        }
        if(isset($ini['encoding'])) {
            $this->encoding = $ini['encoding'];
        } else {
            $this->encoding = "UTF-8";
        }
        foreach(glob("{$this->iterate_dir}/*.$extension") as $filename) {
            $this->partitions[] = $filename;
        }
        $this->num_partitions = count($this->partitions);
        $this->status_filename = "{$this->result_dir}/iterate_status.txt";
        $this->gz_buffer_filename = $this->result_dir."/gz_buffer.txt";


        if(file_exists($this->status_filename)) {
            $this->restoreCheckpoint();
        } else {
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
        $this->current_partition_num = -1;
        $this->end_of_iterator = false;
        $this->current_offset = 0;
        $this->gz_offset = 0;
        $this->fh = NULL;
        $this->gzh = NULL;
        $this->gz_block_num = 0;
        $this->bz2_iterator = NULL;
        $this->buffer = "";
        $this->remainder = "";
        @unlink($this->status_filename);
        @unlink($this->gz_buffer_filename);
    }

    /**
     * Gets the next at most $num many docs from the iterator. It might return
     * less than $num many documents if the partition changes or the end of the
     * bundle is reached.
     *
     * @param int $num number of docs to get
     * @param bool $no_process if true then just an array of page strings found
     *      not any additional meta data.
     * @return array associative arrays for $num pages
     */
    function nextPages($num, $no_process = false)
    {
        $pages = array();
        $page_count = 0;
        for($i = 0; $i < $num; $i++) {
            $page = $this->nextPage($no_process);
            if(!$page) {
                if($this->checkFileHandle()) {
                    $this->fileClose();
                }
                $this->current_partition_num++;
                if($this->current_partition_num >= $this->num_partitions) {
                    $this->end_of_iterator = true;
                    break;
                }
                $this->fileOpen(
                    $this->partitions[$this->current_partition_num]);

                if($this->switch_partition_callback_name != NULL) {
                    $callback_name = $this->switch_partition_callback_name;
                    $result = $this->$callback_name();
                    if(!$result) { break; }
                }
                $page = $this->nextPage($no_process);
                if(!$page) {continue; }
            }
            $pages[] = $page;
            $page_count++;
        }
        if($this->checkFileHandle()) {
            $this->current_offset = $this->fileTell();
            $this->current_page_num += $page_count;
        }

        $this->saveCheckpoint();
        return $pages;
    }


    /**
     * Gets the next doc from the iterator
     * @param bool $no_process if true then just return page string found
     *      not any additional meta data.
     * @return mixed associative array for doc or just string of doc
     *  
     */
    function nextPage($no_process = false)
    {
        if(!$this->checkFileHandle()) return NULL;
        $matches = array();
        while((preg_match($this->delimiter, $this->buffer, $matches,
            PREG_OFFSET_CAPTURE)) != 1) {
            $block = $this->getFileBlock();
            if(!$block || 
                !$this->checkFileHandle() || $this->checkEof()) {
                return NULL;
            }
            $this->buffer .= $block;
        }
        $delim_len = strlen($matches[0][0]);
        $pos = $matches[0][1] + $delim_len;
        $page_pos = ($this->start_delimiter == "") ? $pos : $pos - $delim_len;
        $page = substr($this->buffer, 0, $page_pos);
        if($this->end_delimiter == "") {
            $page = $this->remainder . $page;
            $this->remainder = $matches[0][0];
        }
        $this->buffer = substr($this->buffer, $pos + $delim_len);
        if($this->start_delimiter != "") {
            $matches = array();
            if((preg_match($this->start_delimiter, $this->buffer, $matches,
                PREG_OFFSET_CAPTURE)) != 1) {
                if(isset($matches[0][1])) {
                    $page = substr($page, $matches[0][1]);
                }
            }
        }
        if($no_process == true) {return $page; }

        $site = array();
        $site[self::HEADER] = "text_archive_bundle_iterator extractor";
        $site[self::IP_ADDRESSES] = array("0.0.0.0");
        $site[self::TIMESTAMP] = date("U", time());
        $site[self::TYPE] = "text/plain";
        $site[self::PAGE] = $page;
        $site[self::HASH] = FetchUrl::computePageHash($page);
        $site[self::URL] = "record:".webencode($site[self::HASH]);
        $site[self::HTTP_CODE] = 200;
        $site[self::ENCODING] = $this->encoding;
        $site[self::SERVER] = "unknown";
        $site[self::SERVER_VERSION] = "unknown";
        $site[self::OPERATING_SYSTEM] = "unknown";

        $site[self::WEIGHT] = 1;
        return $site;
    }


    /**
     * Reads and return the block of data from the current partition
     * @return mixed a uncompressed string from the current partitin
     *      or NULL if iterator not set up, or false if EOF reached.
     */
    function getFileBlock()
    {
        $block = NULL;
        switch($this->compression)
        {
            case 'bzip2':
                while(!is_string($block = $this->bz2_iterator->nextBlock())) {
                    if($this->bz2_iterator->eof())
                        return false;
                }
            break;

            case 'gzip':
                $block = $this->gzFileGets();
            break;

            case 'plain':
                $block = fgets($this->fh);
            break;
        }
        return $block;
    }

    /**
     * Acts as gzread($num_bytes, $archive_file), hiding the fact that 
     * buffering of the archive_file is being done to a buffer file
     *
     * @param int $num_bytes to read from archive file
     * @return string of length up to $num_bytes (less if eof occurs)
     */
    function gzFileRead($num_bytes)
    {
        $len = 0;
        $read_string = "";
        do {
            $read_string .= fread($this->gzh, $num_bytes - $len);
            $len += strlen($read_string);
        } while($len < $num_bytes && $this->updateGzBuffer());
        return $read_string;
    }

    /**
     * Acts as gzgets(), hiding the fact that 
     * buffering of the archive_file is being done to a buffer file
     *
     * @return string from archive file up to next line ending or eof
     */
    function gzFileGets()
    {
        $len = 0;
        $read_string = "";
        do {
            $read_string .= fgets($this->gzh);
        } while(feof($this->gzh) && $this->updateGzBuffer());
        return $read_string;
    }

    /**
     *  If reading from a gzbuffer file goes off the end of the current
     *  buffer, reads in the next block from archive file.
     *
     *  @return bool whether successfully read in next block or not
     */
    function updateGzBuffer()
    {
        $this->gz_block_num++;
        return $this->makeGzBufferFile();
    }

    /**
     *  Reads in block $this->gz_block_num of size self::GZ_BUFFER_SIZE from
     *  the archive file
     *
     *  @return bool whether successfully read in block or not
     */
    function makeGzBufferFile()
    {
        if(!$this->checkFileHandle()) { return false; }
        $success = gzseek($this->fh, $this->gz_block_num * 
            self::GZ_BUFFER_SIZE);
        if($success == -1 || !$this->checkFileHandle() 
            || $this->checkEof()) { return false; }
        if(is_resource($this->gzh)) {
            fclose($this->gzh);
        }
        $gz_buffer = gzread($this->fh, self::GZ_BUFFER_SIZE);
        file_put_contents($this->gz_buffer_filename, $gz_buffer);
        $this->gzh = fopen($this->gz_buffer_filename, "rb");
        return true;
    }

    /**
     *  Checks if have a valid handle to object's archive's current partition
     *
     *  @return bool whether it has or not (true -it has)
     */
    function checkFileHandle()
    {
        if($this->compression != "bzip2") {
            return is_resource($this->fh);
        } else {
            return !is_null($this->bz2_iterator);
        }
    }

    /**
     *  Checks if this object's archive's current partition is at an end of file
     *
     *  @return bool whether end of file has been reached (true -it has)
     */
    function checkEof()
    {
        switch($this->compression)
        {
            case 'bzip2':
                $eof = $this->bz2_iterator->eof();
            break;

            case 'gzip':
                $eof = gzeof($this->fh);
            break;

            case 'plain':
                $eof = feof($this->fh);
            break;
        }
    }

    /**
     *  Wrapper around particular compression scheme fopen function
     *
     *  @param string filename to open
     */
    function fileOpen($filename)
    {
        switch($this->compression)
        {
            case 'bzip2':
                $this->bz2_iterator = new BZip2BlockIterator($filename);
            break;

            case 'gzip':
                $this->fh = gzopen($filename, "rb");
                if(!file_exists($this->gz_buffer_filename)) {
                    $this->makeGzBufferFile();
                } else {
                    $this->gzh = fopen($this->gz_buffer_filename, "rb");
                }
            break;

            case 'plain':
                $this->fh = fopen($filename, "rb");
            break;
        }
    }

    /**
     *  Wrapper around particular compression scheme fclose function
     */
    function fileClose()
    {
        switch($this->compression)
        {
            case 'bzip2':
                $this->bz2_iterator->close();
            break;

            case 'gzip':
                gzclose($this->fh);
                fclose($this->gzh);
            break;

            case 'plain':
                fclose($this->fh);
            break;
        }
    }

    /**
     *  Returns the current position in the current iterator partition file
     *  for the given compression scheme.
     *  @return int a position into the currently being processed file of the
     *      iterator
     */
    function fileTell()
    {
        switch($this->compression)
        {
            case 'bzip2':
                return ($this->bz2_iterator) ? 1 : -1;
            break;

            case 'gzip':
                return ftell($this->gzh);
            break;

            case 'plain':
                return ftell($this->fh);
            break;
        }
    }


    /**
     * Stores the current progress to the file iterate_status.txt in the result
     * dir such that a new instance of the iterator could be constructed and
     * return the next set of pages without having to process all of the pages
     * that came before. Each iterator should make a call to saveCheckpoint
     * after extracting a batch of pages.
     * @param array $info any extra info a subclass wants to save
     */
    function saveCheckPoint($info = array())
    {
        if($this->compression != "bzip2") {
            $info['buffer'] = $this->buffer;
            $info['gz_block_num'] = $this->gz_block_num; //used gzip case; else0
            parent::saveCheckpoint($info);
            return;
        }
        $info['end_of_iterator'] = $this->end_of_iterator;
        $info['current_partition_num'] = $this->current_partition_num;
        $info['current_page_num'] = $this->current_page_num;
        $info['buffer'] = $this->buffer;
        $info['remainder'] = $this->remainder;
        $info['header'] = $this->header;
        $info['bz2_iterator'] = $this->bz2_iterator;
        file_put_contents($this->status_filename,
            serialize($info));
    }

    /**
     * Restores  the internal state from the file iterate_status.txt in the
     * result dir such that the next call to nextPages will pick up from just
     * after the last checkpoint. Text archive bundle iterator takes
     * the unserialized data from the last check point and calls the
     * compression specific restore checkpoint to further set up the iterator
     * according to the given compression scheme.
     *
     * @return array the data serialized when saveCheckpoint was called
     */
    function restoreCheckPoint()
    {
        $info = unserialize(file_get_contents($this->status_filename));
        $this->end_of_iterator = $info['end_of_iterator'];
        $this->current_partition_num = $info['current_partition_num'];
        $this->buffer = (isset($info['buffer'])) ? $info['buffer'] : "";
        $this->remainder = (isset($info['remainder'])) ? $info['remainder']:"";
        $restore_function = $this->compression . "RestoreCheckpoint";
        return $this->$restore_function($info);
    }

    /**
     * Does additional restoration of the last checkpoint in the case that
     * plain text (no compression) was being used.
     *
     * @param array $info associative array gotten from unserializing the last
     *      checkpoint
     * @return array the data serialized when saveCheckpoint was called
     */
    function plainRestoreCheckpoint($info)
    {
        $this->current_offset = $info['current_offset'];
        if(!$this->end_of_iterator) {
            $this->fileOpen($this->partitions[$this->current_partition_num]);
            $success = fseek($this->fh, $this->current_offset);
            if($success == -1) { $this->fh = NULL; }
        }
        return $info;
    }

    /**
     * Does additional restoration of the last checkpoint in the case that
     * gz compression was being used.
     *
     * @param array $info associative array gotten from unserializing the last
     *      checkpoint
     * @return array the data serialized when saveCheckpoint was called
     */
    function gzipRestoreCheckpoint($info)
    {
        $this->current_offset = $info['current_offset'];
        if(!$this->end_of_iterator) {
            $this->fileOpen($this->partitions[$this->current_partition_num]);
            $success = fseek($this->gzh, $this->current_offset);
            if($success == -1) { $this->gzh = NULL; }
        }
        return $info;
    }

    /**
     * Does additional restoration of the last checkpoint in the case that
     * bzip2 compression was being used.
     *
     * @param array $info associative array gotten from unserializing the last
     *      checkpoint
     */
    function bzip2RestoreCheckpoint($info)
    {
        $this->current_page_num = $info['current_page_num'];
        $this->buffer = $info['buffer'];
        $this->remainder = $info['remainder'];
        $this->header = $info['header'];
        $this->bz2_iterator = $info['bz2_iterator'];

        return $info;
    }


    /**
     * Used to extract data between two tags. After operation $this->buffer has
     * contents after the close tag.
     *
     * @param string $tag tag name to look for
     *
     * @return string data start tag contents close tag of name $tag
     */
    function getNextTagData($tag)
    {
        $info = $this->getNextTagsData(array($tag));
        if(!isset($info[1])) {return $info; }
        return $info[0];
    }

    /**
     * Used to extract data between two tags for the first tag found
     * amongst the array of tags $tags. After operation $this->buffer has
     * contents after the close tag.
     *
     * @param array $tags array of tagnames to look for
     *
     * @return array of two elements: the first element is a string consisting
     *      of start tag contents close tag of first tag found, the second
     *      has the name of the tag amongst $tags found
     */
    function getNextTagsData($tags)
    {
        $close_regex = '@</('.implode('|', $tags).')[^>]*?>@';

        $offset = 0;
        while(!preg_match($close_regex, $this->buffer, $matches,
                    PREG_OFFSET_CAPTURE, $offset)) {
            if(!$this->checkFileHandle() || $this->checkEof()) {
                return false;
            }
            /*
               Get the next block; the block iterator can very occasionally
               return a bad block if a block header pattern happens to show up
               in compressed data, in which case decompression will fail. We
               want to skip over these false blocks and get back to real
               blocks.
            */
            while(!is_string($block = $this->getFileBlock())) {
                if($this->checkEof())
                    return false;
            }
            $this->buffer .= $block;
        }
        $tag = $matches[1][0];
        $start_info = strpos($this->buffer, "<$tag");
        $this->remainder = substr($this->buffer, 0, $start_info);
        $pre_end_info = strpos($this->buffer, "</$tag", $start_info);
        $end_info = strpos($this->buffer, ">", $pre_end_info) + 1;
        $tag_info = substr($this->buffer, $start_info,
            $end_info - $start_info);
        $this->buffer = substr($this->buffer, $end_info);
        return array($tag_info, $tag);
    }
}
?>
