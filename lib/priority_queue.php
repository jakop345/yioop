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
 *  Load in base classes and interfaces,get the crawlHash function, if necessary
 */
require_once "string_array.php";
require_once "notifier.php";
require_once "utility.php";
require_once "crawl_constants.php";

/**
 * 
 * Code used to manage a memory efficient priority queue
 * Weights for the queue must be flaots
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */ 
class PriorityQueue extends StringArray implements CrawlConstants
{
    var $num_values;
    var $value_size;
    var $weight_size = 4; //size of a float

    var $count;
    var $min_or_max;

    var $notifier; // who to call if move an item in queue

    /**
     *
     */
    public function __construct($fname, $num_values, $value_size, 
        $min_or_max, $notifier = NULL, 
        $save_frequency = self::DEFAULT_SAVE_FREQUENCY) 
    {
        $this->num_values = $num_values;
        $this->value_size = $value_size;

        $this->min_or_max = $min_or_max; 
        $this->count = 0;

        $this->notifier = $notifier;

        parent::__construct($fname, $num_values, 
            $value_size + $this->weight_size, $save_frequency);

    }

    /**
     *
     */
    public function peek($i = 1)
    {
        if($i < 1 || $i > $this->count) {
            crawlLog("Peek Index $i not in Range [1, {$this->count}]");
            return false;
        }
        return $this->getRow($i);
    }

    /**
     *
     */
    public function poll($i = 1)
    {
        if($i < 1 || $i > $this->count) {
            crawlLog("Index $i not in Range [1, {$this->count}]");
            return false;
        }
        $extreme = $this->peek($i);

        $last_entry = $this->getRow($this->count);
        $this->putRow($i, $last_entry);
        $this->count--;

        $this->percolateDown($i);
        $this->checkSave();

        return $extreme;
    }

    /**
     *
     */
    public function insert($data, $weight)
    {
        if($this->count == $this->num_values) {
            return false;
        }
        $this->count++;
        $cur = $this->count;

        $this->putRow($cur, array($data, $weight));

        $loc = $this->percolateUp($cur);

        return $loc;
    }

    /**
     *
     */
    public function adjustWeight($i, $delta)
    {
        if( ($tmp = $this->peek($i)) === false) {
            crawlLog("Index $i not in queue adjust weight failed");
            return false;
        }
        list($data, $old_weight) = $tmp;
        $new_weight = $old_weight + $delta;

        $this->putRow($i, array($data, $new_weight));

        if($new_weight > $old_weight) {
            if($this->min_or_max == self::MIN) {
                $this->percolateDown($i);
            } else {
                $this->percolateUp($i);
            }
        } else {
            if($this->min_or_max == self::MAX) {
                $this->percolateDown($i);  
            } else {
                $this->percolateUp($i);
            }
        }

    }

    /**
     *
     */
    public function printContents()
    {
        for($i = 1; $i <= $this->count; $i++) {
            $row = $this->peek($i);
            print "Entry: $i Value: ".$row[0]." Weight: ".$row[1]."\n";
        }
    }

    /**
     *
     */
    public function getContents()
    {
        $rows = array();
        for($i = 1; $i <= $this->count; $i++) {
            $rows[] = $this->peek($i);
        }

        return $rows;
    }

    /**
     *
     */
    public function normalize($new_total = NUM_URLS_QUEUE_RAM)
    {
        $count = $this->count;
        $total_weight = $this->totalWeight();

        if($total_weight <= 0) {
            crawlLog(
                "Total queue weight was zero!! Doing uniform renormalization!");
        }

        for($i = 1; $i <= $count; $i++) {
            $row = $this->getRow($i);
            if($total_weight > 0) {
                $row[1] = ($new_total*$row[1])/$total_weight;
            } else {
                $row[1] = $new_total/$count;
            }
            $this->putRow($i, $row);
        }
    }

    /**
     *
     */
    function percolateUp($i)
    {
        if($i <= 1) return $i;

        $start_row = $this->getRow($i);
        $parent = $i;

        while ($parent > 1) {
            $child = $parent;
            $parent = floor($parent/2);
            $row = $this->getRow($parent);

            if($this->compare($row[1], $start_row[1]) < 0) {
                $this->putRow($child, $row);
            } else {
                $this->putRow($child, $start_row);
                return $child;
            }
        }

        $this->putRow(1, $start_row);
        return 1;

    }

    /**
     *
     */
    function percolateDown($i)
    {
        $start_row = $this->getRow($i);

        $count = $this->count;

        $parent = $i;
        $child = 2*$parent;

        while ($child <= $count) {

            $left_child_row = $this->getRow($child);

            if($child < $count) { // this 'if' checks if there is a right child 
                $right_child_row = $this->getRow($child + 1);

                if($this->compare($left_child_row[1], $right_child_row[1]) <0) {
                    $child++;
                }
            }

            $child_row = $this->getRow($child);

            if($this->compare($start_row[1], $child_row[1]) < 0) {
                $this->putRow($parent, $child_row);

            } else {
                $this->putRow($parent, $start_row); 
                return;
            }
            $parent = $child;
            $child = 2*$parent;
        }

        $this->putRow($parent, $start_row);
    }

    /**
     *
     */
    function compare($value1, $value2)
    {
      if($this->min_or_max == self::MIN) {
         return $value2 - $value1;
      } else {
         return $value1 - $value2;
      }
    }

    /**
     *
     */
    function getRow($i)
    {
        $value_size = $this->value_size;
        $weight_size = $this->weight_size;

        $row = $this->get($i);

        $value = substr($row, 0, $value_size); 

        $pre_weight = substr($row, $value_size, $weight_size);
        $weight_array = unpack("f", $pre_weight);
        $weight = $weight_array[1];

        return array($value, $weight);

    }

    /**
     *
     */
    function putRow($i, $row)
    {
        $raw_data = $row[0].pack("f", $row[1]);

        $this->put($i, $raw_data);

        if($this->notifier != NULL) {
            $this->notifier->notify($i, $row);
        }
    }

    /**
     *
     */
    function totalWeight()
    {
        $count = $this->count;
        $total_weight = 0;
        for($i = 1; $i <= $count; $i++) {
            $row = $this->getRow($i);
            $total_weight += $row[1];
        }

        return $total_weight;
    }


}
?>
