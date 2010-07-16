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
 * Code used to manage a bloom filter in-memory and in file
 * a Bloom filter is used to store a set of objects.
 * It can support inserts into the set and it can also be
 * used to check membership in the set.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 */ 
class BloomFilterBundle
{

    var $current_filter;
    var $num_filters;
    var $current_filter_count;
    var $filter_size;
    var $dir_name;
    
    const default_filter_size = 10000000;

    /**
     *
     */
    public function __construct($dir_name, 
        $filter_size = self::default_filter_size ) 
    {
        $this->dir_name = $dir_name;
        if(!is_dir($dir_name)) {
            mkdir($dir_name);
        }
        
        $this->loadMetaData();
        
        if($this->num_filters == 0) {
            $this->current_filter = 
                new BloomFilterFile($dir_name."/filter_0.ftr", $filter_size);
            $this->num_filters++;
            $this->filter_size = $filter_size;
            $this->current_filter->save();
           $this->saveMetaData();
        } else {
            $last_filter = $this->num_filters - 1;
            $this->current_filter = 
                BloomFilterFile::load($dir_name."/filter_$last_filter.ftr");
        }


    }

    /**
     *
     */
    public function add($value)
    {
        if($this->current_filter_count >= $this->filter_size) {
            $this->current_filter->save();
            $this->current_filter = NULL;
            gc_collect_cycles();
            $last_filter = $this->num_filters;
            $this->current_filter = 
                new BloomFilterFile($this->dir_name."/filter_$last_filter.ftr", 
                    $this->filter_size);
            $this->current_filter_count = 0;
            $this->num_filters++;
        }

        $this->current_filter->add($value);

        $this->current_filter_count++;
        $this->saveMetaData();
    }

    /**
     *
     */
    public function differenceFilter(&$arr, $field_name = NULL)
    {

        $num_filters = $this->num_filters;
        $count = count($arr);
        for($i = 0; $i < $num_filters; $i++) {
            if($i == $num_filters - 1) {
                $tmp_filter = $this->current_filter;
            } else {
                $tmp_filter = 
                    BloomFilterFile::load($this->dir_name."/filter_$i.ftr");
            }

            for($j = 0; $j < $count; $j++) {
                if($field_name === NULL) {
                    $tmp = & $arr[$j];
                } else {
                    $tmp = & $arr[$j][$field_name];
                }
                if($tmp_filter->contains($tmp)) {
                    unset($arr[$j]);
                }
            }
        }

    }

    /**
     *
     */
    public function loadMetaData()
    {
        if(file_exists($this->dir_name.'/meta.txt')) {
            $meta = unserialize(
                file_get_contents($this->dir_name.'/meta.txt') );
            $this->num_filters = $meta['NUM_FILTERS'];
            $this->current_filter_count = $meta['CURRENT_FILTER_COUNT'];
            $this->filter_size = $meta['FILTER_SIZE'];
        } else {
            $this->num_filters = 0;
            $this->current_filter_count = 0;
            $this->filter_size = self::default_filter_size;
        }
    }

    /**
     *
     */
    public function saveMetaData()
    {
        $meta = array();
        $meta['NUM_FILTERS'] = $this->num_filters;
        $meta['CURRENT_FILTER_COUNT' ]= $this->current_filter_count;
        $meta['FILTER_SIZE'] = $this->filter_size;

        file_put_contents($this->dir_name.'/meta.txt', serialize($meta));
    }

    /**
     *
     */
    public function forceSave()
    {
        $this->saveMetaData();
        $this->current_filter->save();
    }

}
?>
