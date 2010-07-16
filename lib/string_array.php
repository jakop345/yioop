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
 * Read in base class, if necessary
 */
require_once "persistent_structure.php";

/**
 * Memory efficient implementation of persistent arrays
 *
 * The standard array ob
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */  
class StringArray extends PersistentStructure 
{

    var $filename;
    var $num_values;
    var $array_size;
    var $data_size;
    var $string_array;


    /**
     *
     */
    public function __construct($fname, $num_values, $data_size, 
        $save_frequency = self::DEFAULT_SAVE_FREQUENCY) 
    {
        $this->filename = $fname;
        $this->num_values = $num_values;
        $this->data_size = $data_size;

        $this->string_array_size = $num_values*($data_size);

        $this->string_array = pack("x". $this->string_array_size);

        parent::__construct($fname, $save_frequency);

    }


    /**
     *
     */
    public function get($i)
    {
        $data_size = $this->data_size;

        return substr($this->string_array, $i*$data_size, $data_size);
    }

    /**
     *
     */
    public function put($i, $data)
    {
        $data_size = $this->data_size;

        $start = $i * $data_size;
        $end = $start + $data_size;

        for($j = $start, $k = 0; $j < $end; $j++, $k++) {
            $this->string_array[$j] = $data[$k];
        }
    }
   
}
?>
