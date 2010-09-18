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
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */ 
 
class IndexShard extends PersistentStructure implements Serializable
{
    var $doc_ids;
    var $word_docs;
    var $count_doc256;

    function __construct()
    {
    }
    
    function addDocumentWords($doc_id, $word_id_array)
    {
        $this->doc_ids[] = $doc_id;
        
        foreach($word_id_arr as $word_id => $relevance) {
            $relevance = $relevance & 255;
            $store = pack("N", $this->count_doc256 + $relevance); 
            $this->word_docs[$word_id] .= $store;
        }

        $this->count_doc256 += 256;
    }

    function getWordSlice($word_id, $start, $len)
    {
        $result = array();
        if(isset($word_docs[$word_id])) {
            $docs_string = substr($word_docs[$word_id], $start << 2, $len <<2);
            //check if got at least one item
            if($docs_string !== false && ($doc_len = strlen($doc_string)) > 3) {
                for($i = 0; $i < $doc_len; $i += 4) {
                }
            }
        }

        return $result;
    }

    function appendIndexShard($index_shard)
    {
    }

    function docCount()
    {
        return ($this->count_doc256 >> 8);
    }

}
