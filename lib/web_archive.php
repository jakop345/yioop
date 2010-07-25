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
 * Loads crawlLog functions if needed
 */
require_once "utility.php";

/**
 * 
 * Code used to manage web archive files
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class WebArchive
{

    /**
     *
     */
    var $filename;
    /**
     *
     */
    var $iterator_pos;
    /**
     *
     */
    var $compressor;
    /**
     *
     */
    var $count;

    /**
     *
     */
    const OPEN_AND_CLOSE = 1;
    /**
     *
     */
    const OPEN = 2;
    /**
     *
     */
    const CLOSE = 3;
    /**
     *
     */
    function __construct($fname, $compressor, $fast_construct = false) 
    {
        $this->filename = $fname;
        $this->compressor = $compressor;
        if(file_exists($fname)) {
            if(!$fast_construct) {
                $this->readInfoBlock();
            }
            $this->iterator_pos = 0;
        } else {
            $this->iterator_pos = 0;
            $this->count = 0;
            $fh =  fopen($this->filename, "w");
            $this->writeInfoBlock($fh);
            fclose($fh);
        }
    }

    /**
     *
     */
    function readInfoBlock()
    {
        $fh =  fopen($this->filename, "r");
        $len = $this->seekEndObjects($fh);
        $info_string = fread($fh, $len);
        $info_block = unserialize($info_string);
        $this->count = $info_block["count"];
        if(isset($info_block["data"])) {
            return unserialize(
                $this->compressor->uncompress($info_block["data"]));
        } else {
            return NULL;
        }
    }

    /**
     *
     */
    function writeInfoBlock($fh = NULL, &$data = NULL)
    {
        $open_flag = false;
        if($fh == NULL) {
            $open_flag = true;
            $fh =  fopen($this->filename, "r+");
            $this->seekEndObjects($fh);
        }
        $info_block = array();

        $info_block["count"] = $this->count;
        if($data != NULL) {
            $info_block['data'] = $this->compressor->compress(serialize($data));
        }
        $info_string = serialize($info_block);
        $len = strlen($info_string) + 4;

        $out = $info_string.pack("N", $len);
        fwrite($fh, $out, $len);

        if($open_flag) {
            fclose($fh);
        }
    }

    /**
     *
     */
    function seekEndObjects($fh)
    {
        fseek($fh, - 4, SEEK_END);
        $len_block_arr = unpack("N", fread($fh, 4));
        $len_block = $len_block_arr[1];
        fseek($fh, - ($len_block), SEEK_END);

        return $len_block - 4;
    }

    /**
     *
     */
    function addObjects($offset_field, &$objects, 
        $data = NULL, $callback = NULL, $return_flag = true)
    {

        $fh =  fopen($this->filename, "r+");

        $this->seekEndObjects($fh);

        $offset = ftell($fh);

        $out = "";

        if($return_flag) {
            $new_objects = $objects;
        } else {
            $new_objects = & $objects;
        }
        $num_objects = count($new_objects);
        for($i = 0; $i < $num_objects; $i++) {
            $new_objects[$i][$offset_field] = $offset;

            $file = serialize($new_objects[$i]);
            $compressed_file = $this->compressor->compress($file);
            $len = strlen($compressed_file);
            $out .= $len."\n".$compressed_file;
            $offset += strlen($len) + 1 + $len;
        }
        
        $this->count += $num_objects;
    
        fwrite($fh, $out, strlen($out));
        
        if($data != NULL && $callback != NULL) {
            $data = $callback($data, $new_objects, $offset_field);
        }
        $this->writeInfoBlock($fh, $data);

        fclose($fh);

        if($return_flag) {
            return $new_objects;
        } else {
            return;
        }

    }

    /**
     *
     */
    function open($mode = "r")
    {
        $fh = fopen($this->filename, $mode);
        return $fh;
    }

    /**
     * Closes a file handle (which should be of a web archive)
     */
    function close($fh)
    {
        fclose($fh);
    }

    /**
     *
     */
    function getObjects($offset, $num, $next_flag = true, $fh = NULL)
    {

        $open_flag = false;
        if($fh == NULL) {
            $fh =  fopen($this->filename, "r");
            $open_flag = true;
        }

        $objects = array();
        if(fseek($fh, $offset) == 0) {

            for($i = 0; $i < $num; $i++) {
                if(feof($fh)) {break; }

                $object = NULL;
                $line = fgets($fh);

                $line_length = strlen($line);
                $len = intval($line);
                if($len > 0) {
                    $compressed_file = fread($fh, $len);
                    $file = $this->compressor->uncompress($compressed_file);
                    $object = @unserialize($file);

                    $offset += $line_length + $len;
                    $objects[] = array($offset, $object);
                } else {
                    crawlLog("Web archive saw blank line ".
                        "when looked for offset $offset");
                }
            }

            if($next_flag) {
                $this->iterator_pos = $offset;
            }
        }

        if($open_flag) {
            fclose($fh);
        }
        
        return $objects;
    }

    /**
     * Returns $num many objects from the web archive starting at the current 
     * iterator position, leaving the iterator position unchanged
     *
     * @param int $num number of objects to return
     * @return array an array of objects from the web archive
     */
    function currentObjects($num)
    {
        return $this->getObjects($this->iterator_pos, $num, false);
    }

    /**
     * Returns $num many objects from the web archive starting at the 
     * current iterator position. The iterator is advance to the object 
     * after the last one returned
     *
     * @param int $num number of objects to return
     * @return array an array of objects from the web archive
     */
    function nextObjects($num)
    {
        return $this->getObjects($this->iterator_pos, $num);
    }

    /**
     * Resets the iterator for this web archive to the first object 
     * in the archive
     */
    function reset() 
    {
        $this->iterator_pos = 0;
    }

}
?>
