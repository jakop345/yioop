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
 *
 */
require_once "persistent_structure.php";

/**
 * 
 * Code used to manage a bloom filter in-memory and in file
 * a Bloom filter is used to store a set of objects.
 * It can support inserts into the set and it can also be
 * used to check membership in the set.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 */  
class BloomFilterFile extends PersistentStructure
{

    var $num_keys;
    var $filter_size;
    var $filter;

    /**
     *
     */
    public function __construct($fname, $num_values, 
        $save_frequency = self::DEFAULT_SAVE_FREQUENCY) 
    {
        $log2 = log(2);
        $this->num_keys = ceil(log($num_values)/($log2*$log2));
        $this->filter_size = ($this->num_keys)*$num_values;

        $mem_before =  memory_get_usage(true);
        $this->filter = pack("x". ceil(.125*$this->filter_size)); 
            // 1/8 =.125 = num bits/bytes, want to make things floats
        $mem = memory_get_usage(true) - $mem_before;
        parent::__construct($fname, $save_frequency);

    }

    /**
     *
     */
    public function add($value)
    {
        $num_keys = $this->num_keys;
        for($i = 0;  $i < $num_keys; $i++) {
            $pos = $this->getHashBitPosition($value.$i);
            $this->setBit($pos);
        }

        $this->checkSave();
    }

    /**
     *
     */
    public function contains($value)
    {
        $num_keys = $this->num_keys;
        for($i = 0;  $i < $num_keys; $i++) {
            $pos = $this->getHashBitPosition($value.$i);

            if(!$this->getBit($pos)) {
                return false;
            }
        }

        return true;
    }

    /**
     *
     */
    function getHashBitPosition($value)
    {
        $hash = substr(md5($value, true), 0, 4);
        $int_array = unpack("N", $hash);
        $seed = $int_array[1];

        mt_srand($seed);
        $pos = mt_rand(0, $this->filter_size -1);
        return $pos;
    }

    /**
     *
     */
    function setBit($i)
    {
        $byte = ($i >> 3);;

        $bit_in_byte = $i - ($byte << 3);

        $tmp = $this->filter[$byte];
              
        $this->filter[$byte] = $tmp | chr(1 << $bit_in_byte);

    }

    /**
     *
     */
    function getBit($i)
    {
        $byte = $i >> 3;
        $bit_in_byte = $i - ($byte << 3);

        return ($this->filter[$byte] & chr(1 << $bit_in_byte)) != chr(0);
    }
}
?>
