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
 *  Load in all dependencies for IndexArchiveBundle, if necessary
 */ 

require_once 'web_archive_bundle.php'; 
require_once 'bloom_filter_file.php';
require_once 'bloom_filter_bundle.php';
require_once 'gzip_compressor.php';
require_once 'non_compressor.php';
require_once 'utility.php';
/** Loads common constants for web crawling*/
require_once 'crawl_constants.php';

/**
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 */
interface IndexingConstants
{
    const COUNT = -1;
    const END_BLOCK = -2;
    const LIST_OFFSET = -3;
    const POINT_BLOCK = -4;
    const PARTIAL_COUNT = -5;
    const NAME = -6;
} 


/**
 *
 */
function setOffsetPointers($data, &$objects, $offset_field)
{
    $count = count($objects);

    for($i = 0 ; $i < $count ; $i++ ) {
        if(isset($objects[$i][$offset_field]) ) {
            $offset = $objects[$i][$offset_field];
            foreach($objects[$i] as $word_key_and_block_num => $docs_info) {
                $tmp = explode(":", $word_key_and_block_num);
                if(isset($tmp[1]) ) {
                    list($word_key, $block_num) = $tmp;
                    if(strcmp($word_key, "offset") != 0) {
                        if(($block_num +1)*BLOCK_SIZE < 
                            COMMON_WORD_THRESHOLD) {
                            $data[$word_key][$block_num] = $offset;
                        } else if(isset(
                            $docs_info[IndexingConstants::POINT_BLOCK])) {
                            $data[$word_key][IndexingConstants::LIST_OFFSET] = 
                                $offset;
                        } 
                    }
                }
            }

        }
    }

    return $data;
}

/**
 *
 * @author Chris Pollett
 * @package seek_quarry
 */
class WordIterator implements IndexingConstants, CrawlConstants
{
    var $word_key;
    var $index;
    var $seen_docs;
    var $num_docs;
    var $diagnostics;
    
    //common word fields
    var $next_offset;
    var $last_pointed_block;
    var $list_offset;

    //rare word fields

    var $block_pointers;
    var $num_full_blocks;
    var $num_generations;
    var $last_block;
    var $info_block;
    var $current_pointer;
    var $limit;

    /**
     *
     */
    public function __construct($word_key, $index, $limit = 0)
    {
        $this->word_key = $word_key;
        $this->index = $index;
        $this->limit = $limit;
        $this->reset();
    }

    /**
     *
     */
    public function reset()
    {
        $partition = 
            WebArchiveBundle::selectPartition($this->word_key, 
                $this->index->num_partitions_index);

        $this->info_block = $this->index->getPhraseIndexInfo($this->word_key);

        if($this->info_block !== NULL) {
            $this->num_generations = count($this->info_block['GENERATIONS']);
            $count_till_generation = $this->info_block[self::COUNT];

            while($this->limit >= $count_till_generation) {
                $this->info_block['CURRENT_GENERATION_INDEX']++;
                if($this->num_generations <= 
                    $this->info_block['CURRENT_GENERATION_INDEX']) {
                    $this->num_docs = 0;
                    $this->current_pointer = -1;
                    return;
                }
                $info_block = $this->index->getPhraseIndexInfo(
                    $this->word_key, 
                    $this->info_block['CURRENT_GENERATION_INDEX'], 
                    $this->info_block);
                if($info_block !== NULL) {
                    $this->info_block = $info_block;
                }
                $count_till_generation += $this->info_block[self::COUNT];
            }
            

        }

        $this->initGeneration();
        $this->seen_docs = $this->current_pointer * BLOCK_SIZE;

    }

    /**
     *
     */
    public function initGeneration()
    {

        if($this->info_block !== NULL) {
            $info_block = $this->index->getPhraseIndexInfo(
                $this->word_key, $this->info_block['CURRENT_GENERATION_INDEX'], 
                $this->info_block);
            if($info_block === NULL) {
                return false;
            }
            $this->info_block = $info_block;
            $this->num_docs = $info_block['TOTAL_COUNT'];
            $this->num_docs_generation = $info_block[self::COUNT];

            $this->current_pointer = floor($this->limit / BLOCK_SIZE);

            $this->last_block = $info_block[self::END_BLOCK];
            $this->num_full_blocks = 
                floor($this->num_docs_generation / BLOCK_SIZE);
            if($this->num_docs_generation > COMMON_WORD_THRESHOLD) {
                $this->last_pointed_block = 
                    floor(COMMON_WORD_THRESHOLD / BLOCK_SIZE);
            } else {
                $this->last_pointed_block = $this->num_full_blocks;
            }

            for($i = 0; $i < $this->last_pointed_block; $i++) {
                if(isset($info_block[$i])) {
                    $this->block_pointers[$i] = $info_block[$i];
                }
            }
            
            if($this->num_docs_generation > COMMON_WORD_THRESHOLD) {
                if($info_block[self::LIST_OFFSET] === NULL) {
                    $this->list_offset = NULL;
                } else {
                    $this->list_offset = $info_block[self::LIST_OFFSET][0];
                    $this->current_block_num =$info_block[self::LIST_OFFSET][1];
                }
            }

        } else {
            $this->num_docs = 0;
            $this->num_docs_generation = 0;
            $this->current_pointer = -1;
        }
        return true;
    }

    /**
     *
     */
    public function currentDocsWithWord($restrict_phrases = NULL)
    {
        $generation = 
            $this->info_block['GENERATIONS'][
                $this->info_block['CURRENT_GENERATION_INDEX']];
        if($this->current_pointer >= 0) {
            if($this->current_pointer == $this->num_full_blocks) {
                $pages = $this->last_block;
            } else if ($this->current_pointer >= $this->last_pointed_block) {
                if($this->list_offset === NULL) {
                    return -1;
                }
                $doc_block = $this->index->getWordDocBlock($this->word_key, 
                    $this->list_offset, $generation);

                $pages = $doc_block[$this->word_key.":".$this->current_pointer];
            } else {
                if(isset($this->block_pointers[$this->current_pointer])) {
                    $doc_block = $this->index->getWordDocBlock($this->word_key, 
                        $this->block_pointers[$this->current_pointer], 
                        $generation);
                    if(isset(
                        $doc_block[$this->word_key.":".$this->current_pointer]
                        )) {
                        $pages = 
                            $doc_block[
                                $this->word_key.":".$this->current_pointer];
                    } else {
                        $pages = array();
                    }
                } else {
                    $pages = array();
                }
            }

            if($this->seen_docs < $this->limit) {
                $diff_offset = $this->limit - $this->seen_docs;
                $pages = array_slice($pages, $diff_offset);
            }

            if($restrict_phrases != NULL) {
                 $out_pages = array();
                 if(count($pages) >0 ) {
                     foreach($pages as $doc_key => $doc_info) {

                         if(isset($doc_info[self::SUMMARY_OFFSET])) {

                             $page = $this->index->getPage(
                                $doc_key, $doc_info[self::SUMMARY_OFFSET]);
                             /* build a string out of title, links, 
                                and description
                              */
                             $page_string = mb_strtolower(
                                PhraseParser::extractWordStringPageSummary(
                                    $page));

                             $found = true;
                             foreach($restrict_phrases as $phrase) {
                                 if(mb_strpos($page_string, $phrase) 
                                    === false) {
                                     $found = false;
                                 }
                             }
                             if($found == true) {
                                 $out_pages[$doc_key] = $doc_info;
                             }
                         }
                     }
                 }
                 $pages = $out_pages;
            }
            return $pages;
        } else {
            return -1;
        }
    }

    /**
     *
     */
    public function nextDocsWithWord($restrict_phrases = NULL)
    {
        $doc_block = $this->currentDocsWithWord($restrict_phrases);

        $this->seen_docs += count($doc_block);

        if($doc_block == -1 || !is_array($doc_block)) {
            return NULL;
        }
        if(isset($doc_block[self::LIST_OFFSET]) && 
            $doc_block[self::LIST_OFFSET] != NULL) {
            $this->list_offset = $doc_block[self::LIST_OFFSET];
        }
        
        $this->current_pointer ++;
        if($this->current_pointer > $this->num_full_blocks) {
            $flag = false;
            while ($this->info_block['CURRENT_GENERATION_INDEX'] < 
                $this->num_generations -1 && !$flag) {
                $this->info_block['CURRENT_GENERATION_INDEX']++;
                $flag = $this->initGeneration();
            } 
            if ($this->info_block['CURRENT_GENERATION_INDEX'] >= 
                $this->num_generations -1) {
                $this->current_pointer = -1;
            }
        }

        return $doc_block;

    }

}

/**
 *
 * @author Chris Pollett
 * @package seek_quarry
 */
class IndexArchiveBundle implements IndexingConstants, CrawlConstants
{

    var $dir_name;
    var $description;
    var $num_partitions_summaries;
    var $num_partitions_index;
    var $generation_info;
    var $num_words_per_generation;
    var $summaries;
    var $index;
    var $index_partition_filters;

    /**
     *
     */
    public function __construct($dir_name, $filter_size = -1, 
        $num_partitions_summaries = NULL, $num_partitions_index = NULL, 
        $description = NULL)
    {

        $this->dir_name = $dir_name;
        $index_archive_exists = false;

        if(!is_dir($this->dir_name)) {
            mkdir($this->dir_name);
            mkdir($this->dir_name."/index_filters");
        } else {
            $index_archive_exists = true;

        }

        if(file_exists($this->dir_name."/generation.txt")) {
            $this->generation_info = unserialize(
                file_get_contents($this->dir_name."/generation.txt"));
        } else {
            $this->generation_info['ACTIVE'] = 0;
            $this->generation_info['NUM_WORDS'] = 0;
            file_put_contents($this->dir_name."/generation.txt", 
                serialize($this->generation_info));
        }
        $this->summaries = new WebArchiveBundle($dir_name."/summaries",
            $filter_size, $num_partitions_summaries, $description);
        $this->num_partitions_summaries = $this->summaries->num_partitions;

        $this->index = new WebArchiveBundle(
            $dir_name."/index".$this->generation_info['ACTIVE'], -1, 
            $num_partitions_index);
        $this->num_partitions_index = $this->index->num_partitions;
        $this->description = $this->summaries->description;

        $this->num_words_per_generation = NUM_WORDS_PER_GENERATION;

    }

    /**
     *
     */
    public function addPages($key_field, $offset_field, $pages)
    {
        $result = $this->summaries->addPages($key_field, $offset_field, $pages);

        return $result;
    }

    /**
     *
     */
    public function addIndexData($index_data)
    {

        $out_data = array();

        if(!count($index_data) > 0) return;

        /* Arrange the words according to the partitions they are in
         */

        $this->diagnostics['SELECT_TIME'] = 0;
        $this->diagnostics['INFO_BLOCKS_TIME'] = 0;
        $this->diagnostics['ADD_FILTER_TIME'] = 0;
        $this->diagnostics['ADD_OBJECTS_TIME'] = 0;
        $start_time = microtime();
        foreach($index_data as $word_key => $docs_info) {

            $partition = WebArchiveBundle::selectPartition(
                 $word_key, $this->num_partitions_index);
            $out_data[$partition][$word_key] = $docs_info;

        }
        $this->diagnostics['SELECT_TIME'] += changeInMicrotime($start_time);

        /* for each partition add the word data for the partition to the 
           partition web archive
         */
        $cnt = 0;
        foreach($out_data as $partition => $word_data) {
            $this->addPartitionWordData($partition, $word_data);
            $cnt++;
        }
        file_put_contents($this->dir_name."/generation.txt", 
            serialize($this->generation_info));
        $out_data = NULL; 
        gc_collect_cycles();

        crawlLog("**ADD INDEX DIAGNOSTIC INFO...");
        crawlLog("**Time calculating select partition functions ".
            $this->diagnostics['SELECT_TIME']);
        crawlLog("**Time reading info blocks ".
            $this->diagnostics['INFO_BLOCKS_TIME']);
        crawlLog("**Time adding objects to index ".
            $this->diagnostics['ADD_OBJECTS_TIME']);
        crawlLog("**Time adding to filters ".
            $this->diagnostics['ADD_FILTER_TIME']);
        crawlLog("**Number of partitions ".$cnt);

    }

    /**
     *
     */
    public function addPartitionWordData($partition, 
        &$word_data, $overwrite = false)
    {
        $start_time = microtime();

        $block_data = $this->readPartitionInfoBlock($partition);

        if(isset($this->diagnostics['INFO_BLOCKS_TIME'])) {
            $this->diagnostics['INFO_BLOCKS_TIME'] += 
                changeInMicrotime($start_time);
        }
        
        if($block_data == NULL) {
            $block_data[self::NAME] = $partition;
        }

        //update counts set-up add link to offset linked lists
        $out_data = array();
        $out_data[0] = array();

        $this->initPartitionIndexFilter($partition);

        foreach($word_data as $word_key => $docs_info) {
            $start_time = microtime();

            $this->addPartitionIndexFilter($partition, $word_key);
            $this->addPartitionIndexFilter(
                $partition, $word_key . $this->generation_info['ACTIVE']);
            if(isset($this->diagnostics['ADD_FILTER_TIME'])) {
                $this->diagnostics['ADD_FILTER_TIME'] += 
                    changeInMicrotime($start_time);
            }

            if(!isset($block_data[$word_key]) || $overwrite == true) {
                unset($block_data[$word_key]);
                $block_data[$word_key][self::COUNT] = 0;
                $block_data[$word_key][self::END_BLOCK] = array();
                $block_data[$word_key][self::LIST_OFFSET] = NULL;
                $unfilled_block_num = 0;

            } else {
                $unfilled_block_num = 
                    floor($block_data[$word_key][self::COUNT] / BLOCK_SIZE);
            }

            $cnt = count($docs_info);
            $block_data[$word_key][self::COUNT] += $cnt;

            $tmp = 
                array_merge($block_data[$word_key][self::END_BLOCK],$docs_info);
            uasort($tmp, "scoreOrderCallback");
            $add_cnt = count($tmp);
            $num_blocks = floor($add_cnt / BLOCK_SIZE);
            $block_data[$word_key][self::END_BLOCK] = 
                array_slice($tmp, $num_blocks*BLOCK_SIZE);

            $first_common_flag = true;
            $min_common = NULL;
            $slice_cnt = $num_blocks - 1;
            for($i = $unfilled_block_num + $num_blocks - 1; 
                $i >= $unfilled_block_num ; $i--) {
                $out_data[0][$word_key .":". $i] = 
                    array_slice($tmp, $slice_cnt*BLOCK_SIZE, BLOCK_SIZE);
                if(($i+1)*BLOCK_SIZE > COMMON_WORD_THRESHOLD) {
                    $min_common = $i;
                    if($first_common_flag) {
                        if(isset($block_data[$word_key][self::LIST_OFFSET])) {
                            $out_data[0][$word_key .":". $i][self::LIST_OFFSET]=
                                $block_data[$word_key][self::LIST_OFFSET];
                        } else {
                            $out_data[0][$word_key .":". $i][self::LIST_OFFSET]=
                                NULL;
                        }
                        $first_common_flag = false;
                    } else {
                        $out_data[0][$word_key .":". $i][self::LIST_OFFSET] = 
                            NULL; // next in list is in same block
                    }
                }

                $slice_cnt--;
            }
            if($min_common !== NULL) {
                $out_data[0][$word_key .":". $min_common][self::POINT_BLOCK] = 0; 
                // this index needs to point to previous block with word
            }

        }

        $start_time = microtime();
        $this->index->addObjectsPartition("offset", $partition, 
            $out_data, $block_data, "setOffsetPointers", false);

        if(isset($this->diagnostics['ADD_OBJECTS_TIME'])) {
            $this->diagnostics['ADD_OBJECTS_TIME'] += 
                changeInMicrotime($start_time);
        }


        if($this->generation_info['NUM_WORDS']>$this->num_words_per_generation){
            $index_filter_size = $this->index->filter_size;
            $this->generation_info['ACTIVE']++;
            $this->generation_info['NUM_WORDS'] = 0;
            $this->index = new WebArchiveBundle(
                $this->dir_name."/index".$this->generation_info['ACTIVE'], 
                $index_filter_size, $this->num_partitions_index);
            file_put_contents(
                $this->dir_name."/generation.txt", 
                serialize($this->generation_info));
        }

    }

    /**
     *
     */
    public function addPartitionIndexFilter($partition, $word_key)
    {
        if($this->initPartitionIndexFilter($partition) === false) {
            return false;
        }
        if(!$this->index_partition_filters[$partition]->contains($word_key)) {
            $this->generation_info['NUM_WORDS']++;
            $this->index_partition_filters[$partition]->add($word_key);
        }
        
        return true;
    }

    /**
     *
     */
    public function initPartitionIndexFilter($partition)
    {
        if(!isset($this->index_partition_filters[$partition])) {
            if(file_exists($this->dir_name.
                "/index_filters/partition$partition.ftr")) {
                $this->index_partition_filters[$partition] = 
                    BloomFilterFile::load(
                        $this->dir_name .
                        "/index_filters/partition$partition.ftr");
            } else {
                $filter_size = $this->num_words_per_generation;
                $this->index_partition_filters[$partition] = 
                    new BloomFilterFile(
                        $this->dir_name .
                        "/index_filters/partition$partition.ftr", $filter_size);
            }
        }
        return true;
    }

    /**
     *
     */
    public function getSummariesByHash($word_key, $limit, $num, 
        $restrict_phrases = NULL, $phrase_key = NULL)
    {
        if($phrase_key ==  NULL) {
            $phrase_key = $word_key;
        }

        $phrase_info = $this->getPhraseIndexInfo($phrase_key);

        if($phrase_info == NULL || (isset($phrase_info[self::PARTIAL_COUNT]) 
            && $phrase_info[self::PARTIAL_COUNT] < $limit + $num)) {

            $this->addPhraseIndex(
                $word_key, $restrict_phrases, $phrase_key, $limit + $num);
        }

        $iterator = new WordIterator($phrase_key, $this, $limit);

        $num_retrieved = 0;
        $pages = array();

         while(is_array($next_docs = $iterator->nextDocsWithWord()) && 
            $num_retrieved < $num) {
             $num_docs_in_block = count($next_docs);
             foreach($next_docs as $doc_key => $doc_info) {
                 if(isset($doc_info[self::SUMMARY_OFFSET])) {
                     $page = $this->getPage(
                        $doc_key, $doc_info[self::SUMMARY_OFFSET]);
                     $pages[] = array_merge($doc_info, $page);
                     $num_retrieved++;
                 }
                 if($num_retrieved >=  $num) {
                     break 2;
                 }
             }
         }
        $results['TOTAL_ROWS'] = $iterator->num_docs;
        $results['PAGES'] = $pages;
        return $results;
    }

    /**
     *
     */
    public function getPage($key, $offset)
    {
        return $this->summaries->getPage($key, $offset);
    }

    /**
     *
     */
    public function getWordDocBlock($word_key, $offset, $generation = -1)
    {
        if($generation == -1) {
            return $this->index->getPage($word_key, $offset);
        } else {
            $archive = 
                new WebArchiveBundle($this->dir_name."/index".$generation);
            return $archive->getPage($word_key, $offset);
        }
    }

    /**
     *
     */
    public function getPageByPartition($partition, $offset, $file_handle = NULL)
    {
        return $this->index->getPageByPartition(
            $partition, $offset, $file_handle);
    }

    /**
     *
     */
    public function addPageFilter($key_field, $page)
    {
        $this->summaries->addPageFilter($key_field, $page);
    }

    /**
     *
     */
    public function differenceContainsPages(&$page_array, $field_name = NULL)
    {
        return $this->summaries->differencePagesFilter(
            $page_array, $field_name);
    }

    /**
     *
     */
    public function forceSave()
    {
        $this->summaries->forceSave();
        for($i = 0; $i < $this->num_partitions_index; $i++) {
            if($this->index_partition_filters[$i] != NULL) {
                $this->index_partition_filters[$i]->save();
            }
        }
    }

    /**
     *
     */
    public function getPhraseIndexInfo(
        $phrase_key, $generation_index = 0, $info_block = NULL)
    {

        $partition = 
            WebArchiveBundle::selectPartition(
                $phrase_key, $this->num_partitions_index);
        $info = array();

        if($info_block == NULL) {

            if(!$this->initPartitionIndexFilter($partition)) {
                return NULL;
            }
            $filter = & $this->index_partition_filters[$partition];

            if(!$filter->contains($phrase_key)) {
                return NULL;
            }

            $active_generation = $this->generation_info['ACTIVE'];

            $min_generation = 0;
            for($i = 0; $i <= $active_generation; $i++) {
                if($filter->contains($phrase_key . $i)) {
                    if($filter->contains("delete". $phrase_key . $i)) {
                        $info['GENERATIONS'] = array(); 
                        //truncate all previously seen
                    } else {
                        $info['GENERATIONS'][] = $i;
                    }
                }
            }

            $num_generations = count($info['GENERATIONS']);
            if($num_generations == 0) {
                return NULL;
            }

            $sample_size = min($num_generations, SAMPLE_GENERATIONS);
            $sum_count = 0;
            for($i = 0; $i < $sample_size; $i++) {
                $block_info = 
                    $this->readPartitionInfoBlock(
                        $partition, $info['GENERATIONS'][$i]);

                $sum_count += $block_info[$phrase_key][self::COUNT];
            }

            $info['TOTAL_COUNT'] = 
                ceil(($sum_count*$num_generations)/$sample_size); 
                // this is an estimate
        } else {
            $info['TOTAL_COUNT'] = $info_block['TOTAL_COUNT'];
            $info['GENERATIONS'] = $info_block['GENERATIONS'];
        }

        $block_info = $this->readPartitionInfoBlock(
            $partition, $info['GENERATIONS'][$generation_index]);
        $phrase_info = $block_info[$phrase_key];

        $info['CURRENT_GENERATION_INDEX'] = $generation_index;

        if(isset($phrase_info)) {
            $phrase_info['CURRENT_GENERATION_INDEX'] = 
                $info['CURRENT_GENERATION_INDEX'];
            $phrase_info['TOTAL_COUNT'] = $info['TOTAL_COUNT'];
            $phrase_info['GENERATIONS'] = $info['GENERATIONS'];

            return $phrase_info;
        } else {
            return NULL;
        }

    }

    /**
     *
     */
    public function setPhraseIndexInfo($phrase_key, $info)
    {
        $partition = WebArchiveBundle::selectPartition(
            $phrase_key, $this->num_partitions_index);

        $partition_block_data = $this->readPartitionInfoBlock($partition);

        if($partition_block_data == NULL || !is_array($partition_block_data)) {
            $partition_block_data = array();
        }

        $partition_block_data[$phrase_key] = $info;

        $this->writePartitionInfoBlock($partition, $partition_block_data);

    }

    /**
     *
     */
    public function addPhraseIndex($word_key, $restrict_phrases, 
        $phrase_key, $num_needed)
    {
        if($phrase_key == NULL) {
            return;
        }

        $partition = 
            WebArchiveBundle::selectPartition($phrase_key, 
                $this->num_partitions_index);

        $iterator = new WordIterator($word_key, $this);
        $current_count = 0;
        $buffer = array();
        $word_data = array();
        $partial_flag = false;
        $first_time = true;

        while(is_array($next_docs = 
            $iterator->nextDocsWithWord($restrict_phrases))) {
            $buffer = array_merge($buffer, $next_docs);
            $cnt = count($buffer);

            if($cnt > COMMON_WORD_THRESHOLD) {
                $word_data[$phrase_key] = 
                    array_slice($buffer, 0, COMMON_WORD_THRESHOLD);

                $this->addPartitionWordData($partition,$word_data, $first_time);
                $first_time = false;
                $buffer = array_slice($buffer, COMMON_WORD_THRESHOLD); 
                $current_count += COMMON_WORD_THRESHOLD;

                if($current_count > $num_needed) { 
                    /* notice $num_needed only plays a role when 
                      greater than COMMON_WORD_THRESHOLD
                     */
                    $partial_flag = true;
                    break;
                }
             }
        }

        $word_data[$phrase_key] = $buffer;

        $this->addPartitionIndexFilter(
            $partition, 
            "delete". $phrase_key . ($this->generation_info['ACTIVE'] - 1));

        $this->addPartitionWordData($partition, $word_data);
        $this->addPartitionIndexFilter($partition, $phrase_key);
        $this->addPartitionIndexFilter($partition, $phrase_key . 
            $this->generation_info['ACTIVE']);
        $this->index_partition_filters[$partition]->save();
        file_put_contents($this->dir_name."/generation.txt", 
            serialize($this->generation_info));

        $block_info = $this->readPartitionInfoBlock($partition);
        $info = $block_info[$phrase_key];
        $current_count += count($buffer);
        if($partial_flag) {
            $info[self::PARTIAL_COUNT] = $current_count;
            $info[self::COUNT] = 
                floor($current_count*$iterator->num_docs/$iterator->seen_docs);
            $this->setPhraseIndexInfo($phrase_key, $info);
        }
    }

    /**
     *
     */
    public function getSelectiveWords($word_keys, $num, $comparison="lessThan") 
        //lessThan is in utility.php
    {
        $words_array = array();
        if(!is_array($word_keys) || count($word_keys) < 1) { return NULL;}

        foreach($word_keys as $word_key) {
            $info = $this->getPhraseIndexInfo($word_key);
            if(isset($info['TOTAL_COUNT'])) {
                $words_array[$word_key] = $info['TOTAL_COUNT'];
            } else {
                $words_array[$word_key] = 0;
            }
        }

        uasort( $words_array, $comparison); 
        
        return array_slice($words_array, 0, $num);
    }

    /**
     *
     */
    public function readPartitionInfoBlock($partition, $generation = -1)
    {
        if($generation == -1) {
            return $this->index->readPartitionInfoBlock($partition);
        } else {
            $archive = new WebArchiveBundle(
                $this->dir_name."/index".$generation);
            return $archive->readPartitionInfoBlock($partition);
        }

    }

    /**
     *
     */
    public function writePartitionInfoBlock($partition, $data)
    {
        $this->index->writePartitionInfoBlock($partition, $data);
    }

    /**
     *
     */
    public static function getArchiveInfo($dir_name)
    {
        return WebArchiveBundle::getArchiveInfo($dir_name."/summaries");
    }


}
?>
