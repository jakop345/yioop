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
require_once "string_array.php";
require_once "utility.php";

/**
 * 
 * Code used to manage a memory efficient hash table
 * Weights for the queue must be flaots
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */ 
class HashTable extends StringArray
{

    var $key_size;
    var $value_size;
    var $null;
    var $deleted;

    var $count;

    const ALWAYS_RETURN_PROBE = 1;
    const RETURN_PROBE_ON_KEY_FOUND = 0;
    const RETURN_VALUE = -1;


    /**
    */
    public function __construct($fname, $num_values, $key_size, $value_size, $save_frequency = self::DEFAULT_SAVE_FREQUENCY) 
    {
        $this->key_size = $key_size;
        $this->value_size = $value_size;
        $this->null = pack("x". $this->key_size);
        $this->deleted = pack("H2x".($this->key_size - 1), "FF");

        $this->count = 0;

        parent::__construct($fname, $num_values, $key_size + $value_size, $save_frequency);
    }

    public function insert($key, $value)
    {
        $null = $this->null;
        $deleted = $this->deleted;

        $probe = $this->lookup($key, self::ALWAYS_RETURN_PROBE);

        if($probe === false) {
            /* this is a little slow
               the idea is we can't use deleted slots until we are sure $key isn't in the table
             */
            $probe = $this->lookupArray($key, array($null, $deleted), self::ALWAYS_RETURN_PROBE);

            if($probe === false) {
                crawlLog("No space in hash table");
                return false;
            }
        }

        //there was a free slot so write entry...
        $data = pack("x". ($this->key_size + $this->value_size));

        //first the key

        for ($i = 0; $i < $this->key_size; $i++) {
            $data[$i] = $key[$i];
        }

        //then the value

        for ($i = 0; $i < $this->value_size; $i++) {
            $data[$i + $this->key_size] = $value[$i];
        }

        $this->put($probe, $data);
        $this->count++;
        $this->checkSave();

        return true;
    }


    public function lookup($key, $return_probe_value = self::RETURN_VALUE)
    {
        return $this->lookupArray($key, array($this->null), $return_probe_value);
    }


    public function lookupArray($key, $null_array, $return_probe_value = self::RETURN_VALUE)
    {
        $index = $this->hash($key);

        $num_values = $this->num_values;
        $probe_array = array(self::RETURN_PROBE_ON_KEY_FOUND, self::ALWAYS_RETURN_PROBE);

        for($j = 0; $j < $num_values; $j++)  {
            $probe = ($index + $j) % $num_values;

            list($index_key, $index_value) = $this->getEntry($probe);

            if(in_array($index_key, $null_array)) {
                if($return_probe_value == self::ALWAYS_RETURN_PROBE) {
                    return $probe;
                } else {
                    return false;
                }
            }

            if(strcmp($key, $index_key) == 0) { break; }
        }

        if($j == $num_values) {return false;}

        $result = $index_value;
        if(in_array($return_probe_value, $probe_array)) {
            $result = $probe;
        }

        return $result; 

    }

    public function delete($key)
    {
        $deleted = pack("H2x".($this->key_size + $this->value_size - 1), "FF");
            //deletes

        $probe = $this->lookup($key, self::RETURN_PROBE_ON_KEY_FOUND);

        if($probe === false) { return false; }

        $this->put($probe, $deleted);

        $this->count--;
        $this->checkSave();

        return true;

    }

    function getEntry($i)
    {
        $raw = $this->get($i);
        $key = substr($raw, 0, $this->key_size);
        $value = substr($raw, $this->key_size, $this->value_size);

        return array($key, $value);
    }

    function hash($key)
    {
        $hash = substr(md5($key, true), 0, 4);
        $int_array = unpack("N", $hash);
        $seed = $int_array[1];

        mt_srand($seed);
        $index = mt_rand(0, $this->num_values -1);

        return $index;
    }


}
?>
