<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014 Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage iterator
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 *Loads base class for iterating
 */
require_once BASE_DIR.'/lib/index_bundle_iterators/index_bundle_iterator.php';
/**
 * Used to iterate over the documents which occur in all of a set of
 * iterator results
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see IndexArchiveBundle
 */
class IntersectIterator extends IndexBundleIterator
{
    /**
     * An array of iterators whose intersection we  get documents from
     * @var array
     */
    var $index_bundle_iterators;
    /**
     * Number of elements in $this->index_bundle_iterators
     * @var int
     */
    var $num_iterators;
    /**
     * The number of iterated docs before the restriction test
     * @var int
     */
    var $seen_docs_unfiltered;
    /**
     * Index of the iterator amongst those we are intersecting to advance
     * next
     * @var int
     */
    var $to_advance_index;
    /**
     * Associative array (term position in original query => iterator index
     * of an iterator for that term). This is to handle queries where the
     * same term occures multiple times. For example, the rock back "The The"
     *
     * @var array
     */
    var $word_iterator_map;
    /**
     * Number of elements in $this->word_iterator_map
     * @var int
     */
    var $num_words;
    /**
     * Each element in this array corresponds to one quoted phrase in the
     * original query. Each element is in turn an array with elements
     * corresponding to a position of term in the orginal query followed
     * its length (a term might involve more than one word so the length
     * could be greater than one). It is also allowed that entries might
     * be of the form *num => * to indicates that an asterisk (a wild card that
     * can match any number of terms) appeared at that place in the query
     * @var array
     */
    var $quote_positions;
    /**
     * A weighting factor to multiply with each doc SCORE returned from this
     * iterator
     * @var float
     */
    var $weight;
    /**
     * Whether to run a timer that shuts down the intersect iterator if
     * syncGenDocOffsetsAmongstIterators takes longer than the time out period
     */
    var $sync_timer_on;
    /**
     * Number of seconds before timeout and stop
     * syncGenDocOffsetsAmongstIterators if slow
     */
    const SYNC_TIMEOUT = 4;
    /**
     * Creates an intersect iterator with the given parameters.
     *
     * @param object $index_bundle_iterators to use as a source of documents
     *     to iterate over
     * @param array $word_iterator_map ssociative array (
     *      term position in original query => iterator index
     *      of an iterator for that term)
     * @param array $quote_positions Each element in this array corresponds
     *      to one quoted phrase in the original query. @see $quote_positions
     *      field variable in this class for more info
     * @param float $weight multiplicative factor to apply to scores returned
     *      from this iterator
     */
    function __construct($index_bundle_iterators, $word_iterator_map,
        $quote_positions = NULL, $weight = 1)
    {
        $this->index_bundle_iterators = $index_bundle_iterators;
        $this->word_iterator_map  = $word_iterator_map;
        $this->num_words = count($word_iterator_map);
        $this->num_iterators = count($index_bundle_iterators);
        $this->num_docs = 0;
        $this->quote_positions = $quote_positions;
        $this->weight = $weight;
        $this->results_per_block = 1;
        $this->sync_timer_on = false;
        /*
             We take an initial guess of the num_docs we return as the sum
             of the num_docs of the underlying iterators. We are also setting
             up here that we return at most one posting at a time from each
             iterator
        */
        $this->seen_docs = 0;
        $this->seen_docs_unfiltered = 0;
        for($i = 0; $i < $this->num_iterators; $i++) {
            $this->num_docs += $this->index_bundle_iterators[$i]->num_docs;
            $this->index_bundle_iterators[$i]->setResultsPerBlock(1);
            $this->seen_docs += $this->index_bundle_iterators[$i]->seen_docs;
            if(isset($this->index_bundle_iterators[$i]->seen_docs_unfiltered)) {
                $this->seen_docs_unfiltered +=
                    $this->index_bundle_iterators[$i]->seen_docs_unfiltered;
            } else {
                $this->seen_docs_unfiltered += $this->seen_docs;
            }
        }
    }
    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    function reset()
    {
        for($i = 0; $i < $this->num_iterators; $i++) {
            $this->index_bundle_iterators[$i]->setResultsPerBlock(1);
            $this->index_bundle_iterators[$i]->reset();
        }

        $this->seen_docs = 0;
        $this->seen_docs_unfiltered = 0;
    }
    /**
     * Computes a relevancy score for a posting offset with respect to this
     * iterator and generation
     * @param int $generation the generation the posting offset is for
     * @param int $posting_offset an offset into word_docs to compute the
     *     relevance of
     * @return float a relevancy score based on BM25F.
     */
    function computeRelevance($generation, $posting_offset)
    {
        $relevance = 0;
        for($i = 0; $i < $this->num_iterators; $i++) {
            $relevance += $this->index_bundle_iterators[$i]->computeRelevance(
                $generation, $posting_offset);
        }
        return $relevance;
    }
    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and rank if there are docs left, -1 otherwise
     */
    function findDocsWithWord()
    {
        $status = $this->syncGenDocOffsetsAmongstIterators();
        if($status == -1) {
            return -1;
        }
        //next we finish computing BM25F
        $docs = $this->index_bundle_iterators[0]->currentDocsWithWord();
        $weight = $this->weight;
        if(is_array($docs) && count($docs) == 1) {
            //we get intersect docs one at a time so should be only one
            $keys = array_keys($docs);
            $key = $keys[0];
            $position_lists = array();
            $len_lists = array();
            $position_lists[0] = $docs[$key][self::POSITION_LIST];
            $len_lists[0] = count($docs[$key][self::POSITION_LIST]);
            for($i = 1; $i < $this->num_words; $i++) {
                if($this->word_iterator_map[$i] < $i) {
                    $position_lists[] =
                        $position_lists[$this->word_iterator_map[$i]];
                    $docs[$key][self::RELEVANCE] +=
                        $docs[$key][self::RELEVANCE];
                } else {
                    $i_docs =
                        $this->index_bundle_iterators[
                                $this->word_iterator_map[$i]
                            ]->currentDocsWithWord();
                    if(isset($i_docs[$key][self::POSITION_LIST]) &&
                       ($ct = count($i_docs[$key][self::POSITION_LIST]) > 0 )) {
                        $position_lists[] = $i_docs[$key][self::POSITION_LIST];
                        $len_lists[] = $ct;
                    }
                    if(isset($i_docs[$key])) {
                        $docs[$key][self::RELEVANCE] +=
                            $i_docs[$key][self::RELEVANCE];
                    }
                }
            }
            if(count($position_lists) > 1) {
                if($this->quote_positions === NULL ||
                    $this->checkQuotes($position_lists)) {
                    $docs[$key][self::PROXIMITY] =
                        $this->computeProximity($position_lists, $len_lists,
                            $docs[$key][self::IS_DOC]);
                } else {
                    $docs = array();
                }
            } else {
                 $docs[$key][self::PROXIMITY] = 1;
            }
            if($docs != array()) {
                $docs[$key][self::SCORE] = $docs[$key][self::DOC_RANK] *
                     $docs[$key][self::RELEVANCE]* $docs[$key][self::PROXIMITY];
                if($weight != 1) {
                    $docs[$key][self::DOC_RANK] *= $weight;
                    $docs[$key][self::RELEVANCE] *= $weight;
                    $docs[$key][self::PROXIMITY] *= $weight;
                    $docs[$key][self::SCORE] *= $weight;
                }
            }
        }
        $this->count_block = count($docs);
        $this->pages = $docs;
        return $docs;
    }
    /**
     * Used to check if quoted terms in search query appear exactly in
     * the position lists of the current document
     *
     * @param array $position_lists of search terms in the current document
     * @return bool whether the quoted terms in the search appear exactly
     */
    function checkQuotes(&$position_lists)
    {
        foreach($this->quote_positions as $qp) {
            if($this->checkQuote($position_lists, 0, "*", $qp) < 1) {
                return false;
            }
        }
        return true;
    }
    /**
     * Auxiliary function for @see checkQuotes used to check if quoted terms
     * in search query appear exactly in the position lists of the current
     * document
     *
     * @param array $position_lists of search terms in the current document
     * @param int $cur_pos to look after in any position list
     * @param mixed $next_pos * or int if * next_pos must be >= $cur_pos
     *     +len_search_term. $next_pos represents the position the next
     *     quoted term should be at
     * @param $qp $position_list_index => $len_of_list_term pairs
     * @return -1 on failure, 0 on backtrack, 1 on success
     */
    function checkQuote(&$position_lists, $cur_pos, $next_pos, $qp)
    {
        if($qp == array() || $qp == NULL) {
            return 1;
        }
        $list_index = key($qp);
        $len = $qp[$list_index];
        unset($qp[$list_index]);
        if(strcmp($len, "*") == 0) {
            return $this->checkQuote($position_lists, $cur_pos, "*", $qp);
        }
        $list = $position_lists[$list_index];
        $is_star = (strcmp($next_pos, "*") == 0);
        $next_pos = ($is_star) ? $cur_pos + $len: $next_pos;
        while(true) {
            $found = false;
            foreach($list as $elt) {
                if($elt >= $next_pos) {
                    $found = true;
                    break;
                }
            }
            if(!$found) {
                return -1;
            }
            if($is_star || $elt == $next_pos) {
                $check = $this->checkQuote($position_lists, $elt,
                    $elt + $len, $qp);
                if($check != 0) return $check;
                $next_pos = $elt + $len;
            } else {
                return 0;
            }
        }
    }
    /**
     * Given the position_lists of a collection of terms computes
     * a score for how close those words were in the given document
     *
     * @param array& $word_position_lists a 2D array item
     *      number => position_list (locations in doc where item occurred) for
     *      that item.
     * @param array& $word_len_lists length for each item of its position list
     * @param bool $is_doc whether this is the position list of a document
     *     or a link
     * @return sum of inverse of all covers computed by plane sweep algorithm
     */
    function computeProximity(&$word_position_lists, &$word_len_lists, $is_doc)
    {
        $num_iterators = $this->num_iterators;
        if($num_iterators < 1) return 1;
        $covers = array();
        $position_list = $word_position_lists;
        $interval = array();
        $num_words = count($position_list);
        for ($i = 0; $i < $num_words; $i++) {
            $min = array_shift($position_list[$i]);
            if(isset($min)) {
                array_push($interval, array($min, $i));
                for($j = 0; $j < $num_words; $j++) {
                    if(isset($position_list[$j][0]) &&
                        $min == $position_list[$j][0]) {
                        array_shift($position_list[$j]);
                    }
                }
            }
        }
        if(count($interval) != $num_words){
            return 0;
        }
        sort($interval);
        $l = array_shift($interval);
        $r = end($interval);
        $stop = false;
        if(count($position_list[$l[1]]) == 0) {
            $stop = true;
        }
        while(!$stop) {
            $p = array_shift($position_list[$l[1]]);
            for ($i = 0;$i < $num_words; $i++){
                if(isset($position_list[$i][0]) && $p == $position_list[$i][0]){
                    array_shift($position_list[$i]);
                }
            }
            $q = $interval[0][0];
            if($p > $r[0]) {
                array_push($covers, array($l[0], $r[0]));
                array_push($interval, array($p, $l[1]));
            } else {
                if($p < $q) {
                    array_unshift($interval, array($p, $l[1]));
                } else {
                    array_push($interval, array($p, $l[1]));
                    sort($interval);
                }
            }
            $l = array_shift($interval);
            $r = end($interval);
            if(count($position_list[$l[1]]) == 0) {
                $stop = true;
            }
        }
        array_push($covers, array($l[0],$r[0]));
        $score = 0;
        if($is_doc) {
            $weight = TITLE_WEIGHT;
            $cover = array_shift($covers);
            while(isset($cover[1]) && $cover[1] < AD_HOC_TITLE_LENGTH) {
                $score += ($weight/($cover[1] - $cover[0] + 1));
                $cover = array_shift($covers);
            }
            $weight = DESCRIPTION_WEIGHT;
            foreach($covers as $cover){
                $score += ($weight/($cover[1] - $cover[0] + 1));
            }
        } else {
            $weight = LINK_WEIGHT;
            foreach($covers as $cover) {
                $score += ($weight/($cover[1] - $cover[0] + 1));
            }
        }
        return $score;
    }
    /**
     * Finds the next generation and doc offset amongst all the iterators
     * that contains the word. It assumes that the (generation, doc offset)
     * pairs are ordered in an increasing fashion for the underlying iterators
     */
    function syncGenDocOffsetsAmongstIterators()
    {
        static $sync_time = 0;
        if($this->sync_timer_on) {
            $timer_on = true;
            if($sync_time === 0) {
                $sync_time = time();
            }
            $time_out = self::SYNC_TIMEOUT + $sync_time;
        } else {
            $timer_on = false;
        }
        if(($biggest_gen_offset = $this->index_bundle_iterators[
                        0]->currentGenDocOffsetWithWord()) == -1) {
            return -1;
        }
        $gen_doc_offset[0] = $biggest_gen_offset;
        $all_same = true;
        for($i = 1; $i < $this->num_iterators; $i++) {
            $cur_gen_doc_offset =
                $this->index_bundle_iterators[
                    $i]->currentGenDocOffsetWithWord();
            $gen_doc_offset[$i] = $cur_gen_doc_offset;
            if($timer_on && time() > $time_out) {
                return -1;
            }
            if($cur_gen_doc_offset == -1) {
                return -1;
            }
            $gen_doc_cmp = $this->genDocOffsetCmp($cur_gen_doc_offset,
                $biggest_gen_offset);
            if($gen_doc_cmp > 0) {
                $biggest_gen_offset = $cur_gen_doc_offset;
                $all_same = false;
            } else if ($gen_doc_cmp < 0) {
                $all_same = false;
            }
        }
        if($all_same) {
            return 1;
        }
        $last_changed = -1;
        $i = 0;
        while($i != $last_changed) {
            if($timer_on && time() > $time_out) {
                return -1;
            }
            if($last_changed == -1) $last_changed = 0;
            if($this->genDocOffsetCmp($gen_doc_offset[$i],
                $biggest_gen_offset) < 0) {
                $iterator = $this->index_bundle_iterators[$i];
                $iterator->advance($biggest_gen_offset);
                $cur_gen_doc_offset =
                    $iterator->currentGenDocOffsetWithWord();
                $gen_doc_offset[$i] = $cur_gen_doc_offset;
                if($cur_gen_doc_offset == -1) {
                    return -1;
                }
                if($this->genDocOffsetCmp($cur_gen_doc_offset,
                    $biggest_gen_offset) > 0) {
                    $last_changed = $i;
                    $biggest_gen_offset = $cur_gen_doc_offset;
                }
            }
            $i++;
            if($i == $this->num_iterators) {
                $i = 0;
            }
        }
        return 1;
    }
    /**
     * Forwards the iterator one group of docs
     * @param array $gen_doc_offset a generation, doc_offset pair. If set,
     *     the must be of greater than or equal generation, and if equal the
     *     next block must all have $doc_offsets larger than or equal to
     *     this value
     */
    function advance($gen_doc_offset = NULL)
    {
        $this->current_block_fresh = false;
        $this->seen_docs += 1;

        $this->seen_docs_unfiltered = 0;

        //num_docs can change when advance() called so that's why we recompute
        $total_num_docs = 0;
        for($i = 0; $i < $this->num_iterators; $i++) {
            $this->seen_docs_unfiltered +=
                $this->index_bundle_iterators[$i]->seen_docs;
            $total_num_docs += $this->index_bundle_iterators[$i]->num_docs;
        }
        if($this->seen_docs_unfiltered > 0) {
            $this->num_docs =
                floor(($this->seen_docs * $total_num_docs) /
                $this->seen_docs_unfiltered);
        }
        $this->index_bundle_iterators[0]->advance($gen_doc_offset);
    }
    /**
     * Gets the doc_offset and generation for the next document that
     * would be return by this iterator
     *
     * @return mixed an array with the desired document offset
     * and generation; -1 on fail
     */
    function currentGenDocOffsetWithWord() {
        $this->syncGenDocOffsetsAmongstIterators();
        return $this->index_bundle_iterators[0]->currentGenDocOffsetWithWord();
    }
    /**
     * This method is supposed to set
     * the value of the result_per_block field. This field controls
     * the maximum number of results that can be returned in one go by
     * currentDocsWithWord(). This method cannot be consistently
     * implemented for this iterator and expect it to behave nicely
     * it this iterator is used together with union_iterator. So
     * to prevent a user for doing this, calling this method results
     * in a user defined error
     *
     * @param int $num the maximum number of results that can be returned by
     *     a block
     */
     function setResultsPerBlock($num) {
        if($num != 1) {
            trigger_error("Cannot set the results per block of
                an intersect iterator", E_USER_ERROR);
        }
     }
}
?>