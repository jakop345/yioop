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
 * Load charCopy
 */
require_once "utility.php";

/**
 * 
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */ 
 
class BSTArray
{
    var $data;
    var $data_len;
    var $key_len;
    var $value_len;
    var $entry_len;
    var $key_compare;

    /**
     *
     */
    function __construct($key_len, $value_len, $key_compare)
    {
        $this->data = "";
        $this->data_len = 0;
        $this->key_len = $key_len;
        $this->value_len = $value_len;
        $this->entry_len = $key_len + $value_len + 8;
        $this->key_compare = $key_compare;
    }

    /**
     *
     */
    function insertUpdate($key, $value)
    {
        $key_compare = $this->key_compare;
        if($this->contains($key, $offset, $parent_offset))
        {
            list(, , $left_offset, $right_offset) = $this->readEntry($offset);
            charCopy($key . $value . pack("N",$left_offset) . 
                pack("N", $right_offset),$this->data, $offset,$this->entry_len);
        } else {
            if($parent_offset != $offset) { // data already exists
                list($parent_key, $parent_value, $parent_left_offset,
                    $parent_right_offset) = $this->readEntry($parent_offset);
                if($key_compare($parent_key, $key) < 0 ) {
                    $parent_right_offset = $offset;
                } else {
                    $parent_left_offset = $offset;
                }
                $new_parent_entry =  $parent_key . $parent_value . 
                    pack("N", $parent_left_offset) . 
                    pack("N", $parent_right_offset);
                charCopy( $new_parent_entry,
                    $this->data, $parent_offset, $this->entry_len);
            }
            $this->data .= $key . $value . pack("H*", "7FFFFFFF7FFFFFFF");
            $this->data_len += $this->entry_len;
        }
    }

    /**
     *
     */
    function contains($key, &$offset, &$parent_offset)
    {
        $offset = 0;
        $parent_offset = 0;
        $data_len = $this->data_len;
        $entry_len = $this->entry_len;
        $last_entry = $data_len - $entry_len;
        $key_compare = $this->key_compare;
        while($offset <= $last_entry ) {
            list($cur_key, , $left_offset, $right_offset) =
                $this->readEntry($offset);
            $comparison = $key_compare($cur_key, $key);
            if($comparison == 0) {
                return true;
            } else if ($comparison < 0) {
                $parent_offset = $offset;
                $offset = $right_offset;
            } else {
                $parent_offset = $offset;
                $offset = $left_offset;
            }
        }

        $offset = $data_len;
        return false;
    }

    /**
     *
     */
    function readEntry($offset)
    {
        $key = substr($this->data, $offset, $this->key_len);
        $offset += $this->key_len;
        $value = substr($this->data, $offset, $this->value_len);
        $offset += $this->value_len;
        $left_string = substr($this->data, $offset, 4);
        $tmp = unpack("N", $left_string);
        $left_offset = $tmp[1];
        $offset += 4;
        $right_string = substr($this->data, $offset, 4);
        $tmp = unpack("N", $right_string);
        $right_offset = $tmp[1];
        return array($key, $value, $left_offset, $right_offset);
    }
}
