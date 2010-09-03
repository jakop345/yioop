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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** 
 * logging is done during crawl not through web, 
 * so it will not be used in the phrase model 
 */
define("LOG_TO_FILES", false); 
/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php"; 
/** 
 * Used to look up words and phrases in the inverted index 
 * associated with a given crawl
 */
require_once BASE_DIR."/lib/index_archive_bundle.php";

/**
 * Load iterators to get docs out of index archive
 */
foreach(glob(BASE_DIR."/lib/index_bundle_iterators/*_iterator.php") 
    as $filename) { 
    require_once $filename;
}

/**
 * 
 * This is class is used to handle
 * db results for a given phrase search
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class PhraseModel extends Model 
{

    /** used to hold the name of index archive to look summaries up in
     *  @var string
     */
    var $index_name;


    /**
     * {@inheritdoc}
     */
    function __construct($db_name = DB_NAME) 
    {
        parent::__construct($db_name);
    }


    /**
     * Given a query phrase, returns formatted document summaries of the 
     * documents that match the phrase.
     *
     * @param string $phrase  the phrase to try to match
     * @param int $low  return results beginning with the $low document
     * @param int $results_per_page  how many results to return
     * @param bool $format  whether to highlight in the returned summaries the 
     *      matched text
     * @return array an array of summary data
     */
    function getPhrasePageResults(
        $phrase, $low = 0, $results_per_page = NUM_RESULTS_PER_PAGE, 
        $format = true)
    {

        $results = NULL;
        $word_structs = array();
        /* 
            this is a quick and dirty parsing and will usually work,
            exceptions would be | in quotes or if someone tried
            to escape |. 
        */
        $disjunct_phrases = explode("|", $phrase); 
        foreach($disjunct_phrases as $disjunct) {
            list($word_struct, $format_words) = 
                $this->parseWordStructConjunctiveQuery($disjunct);
            if($word_struct != NULL) {
                $word_structs[] = $word_struct;
            }
        }
        
        $results = $this->getSummariesByHash($word_structs, 
            $low, $results_per_page);
        if(count($results) == 0) {
            $results = NULL;
        }
        if($results == NULL) {
            $results['TOTAL_ROWS'] = 0;
        }
      
        if($format) {
            if(count($format_words) == 0 ){
                $format_words = NULL;
            }
        } else {
            $format_words = NULL;
        }


        $output = $this->formatPageResults($results, $format_words);

        return $output;

    }

    function lookupSummaryOffset($url)
    {
        $index_archive_name = self::index_data_base_name . $this->index_name;
        $index_archive = new IndexArchiveBundle(
            CRAWL_DIR.'/cache/'.$index_archive_name);
        $word_iterator = 
            new WordIterator(crawlHash("info:$url"), $index_archive, 0);
        $num_retrieved = 0;
        $pages = array();
        $summary_offset = NULL;
        while(is_array($next_docs = $word_iterator->nextDocsWithWord()) &&
            $num_retrieved < 1) {
             foreach($next_docs as $doc_key => $doc_info) {
                 $summary_offset = & $doc_info[CrawlConstants::SUMMARY_OFFSET];
                 $num_retrieved++;
                 if($num_retrieved >=  1) {
                     break 2;
                 }
             }
        }
        return $summary_offset;
    }

    function parseWordStructConjunctiveQuery($phrase)
    {
        $phrase = " ".$phrase;
        $phrase_string = $phrase;
        $meta_words = array('link\:', 'site\:', 
            'filetype\:', 'info\:', '\-', 
            'index:', 'i:', 'weight:', 'w:');
        $index_name = $this->index_name;
        $weight = 1;
        $found_metas = array();
        $disallow_phrases = array();
        foreach($meta_words as $meta_word) {
            $pattern = "/(\s)($meta_word(\S)+)/";
            preg_match_all($pattern, $phrase, $matches);
            if(in_array($meta_word, array('link\:', 'site\:', 
            'filetype\:', 'info\:') )) {
                $found_metas = array_merge($found_metas, $matches[2]);
            } else if($meta_word == '\-') {
                if(count($matches[0]) > 0) {
                    $disallow_phrases = 
                        array_merge($disallow_phrases, 
                            array(substr($matches[2][0],2)));
                }
            } else if ($meta_word == "i:" || $meta_word == "index:") {
                if(isset($matches[2][0])) {
                    $index_name = substr($matches[2][0],strlen($meta_word));
                }
            } else if ($meta_word == "w:" || $meta_word == "weight:") {
                if(isset($matches[2][0])) {
                    $weight = substr($matches[2][0],strlen($meta_word));
                }
            }
            $phrase_string = preg_replace($pattern,"", $phrase_string);
        }

        $index_archive_name = self::index_data_base_name . $index_name;
        $index_archive = new IndexArchiveBundle(
            CRAWL_DIR.'/cache/'.$index_archive_name);

        $phrase_string = mb_ereg_replace("[[:punct:]]", " ", $phrase_string);
        $phrase_string = preg_replace("/(\s)+/", " ", $phrase_string);
        
        /*
            we search using the stemmed words, but we format snippets in the 
            results by bolding either
         */
        $query_words = explode(" ", $phrase_string); //not stemmed
        $base_words = 
            array_keys(PhraseParser::extractPhrasesAndCount($phrase_string)); 
            //stemmed
            
        $words = array_merge($base_words, $found_metas);
        if(isset($words) && count($words) == 1) {
            $phrase_string = $words[0];
            $phrase_hash = crawlHash($phrase_string);
            $word_struct = array("KEYS" => array($phrase_hash),
                "RESTRICT_PHRASES" => NULL, "DISALLOW_PHRASES" => NULL,
                "WEIGHT" => $weight, "INDEX_ARCHIVE" => $index_archive
            );
        } else {
            /* 
                handle strings in quotes 
                (we want an exact match on such quoted strings)
            */
            $quoteds =array();
            $hash_quoteds = array();
            $num_quotes = 
                preg_match_all('/\"((?:[^\"\\\]|\\\\.)*)\"/', $phrase,$quoteds);
            if(isset($quoteds[1])) {
                $quoteds = $quoteds[1];
            }

            //get a raw list of words and their hashes 

            $hashes = array();
            foreach($words as $word) {
                $tmp = crawlHash($word); 
                $hashes[] = $tmp;
            }

            $restrict_phrases = array_merge($query_words, $quoteds);

            $hashes = array_unique($hashes);
            $restrict_phrases = array_unique($restrict_phrases);
            $restrict_phrases = array_filter($restrict_phrases);
            $words_array = $index_archive->getSelectiveWords($hashes, 10);

            if(is_array($words_array)) {
                reset($words_array);
                $word_key = key($words_array);
                $word_count = $words_array[$word_key];
                foreach($words_array as $key => $count) {
                    if($count > 3 * $word_count) {
                        unset($words_array[$key]);
                    }
                }
                $word_keys = array_keys($words_array);
                $word_struct = array("KEYS" => $word_keys,
                    "RESTRICT_PHRASES" => $restrict_phrases, 
                    "DISALLOW_PHRASES" => $disallow_phrases,
                    "WEIGHT" => $weight,
                    "INDEX_ARCHIVE" => $index_archive
                );
                if($word_count <= 0 ) {
                    $word_struct = NULL;
                }
            } else {
                $word_struct = NULL;
            }
        }
        $format_words = array_merge($query_words, $base_words);

        return array($word_struct, $format_words);
    }

    /**
     * Given a page summary extract the words from it and try to find documents
     * which match the most relevant words. The algorithm for "relevant" is 
     * pretty weak. For now we pick the $num many words which appear in the 
     * fewest documents.
     *
     * @param string $craw_item a page summary
     * @param int $num number of key phrase to return
     * @return array  an array of most selective key phrases
     */
    function getTopPhrases($crawl_item, $num) 
    {
        $index_archive_name = self::index_data_base_name . $this->index_name;

        $index_archive = 
            new IndexArchiveBundle(CRAWL_DIR.'/cache/'.$index_archive_name);

        $phrase_string = 
            PhraseParser::extractWordStringPageSummary($crawl_item);

        $words = 
            array_keys(PhraseParser::extractPhrasesAndCount($phrase_string));

        $hashes = array();
        $lookup = array();
        foreach($words as $word) {
            $tmp = crawlHash($word); 
            $hashes[] = $tmp;
            $lookup[$tmp] = $word;
        }

        $words_array = 
            $index_archive->getSelectiveWords($hashes, $num, "greaterThan");
        $word_keys = array_keys($words_array);
        $phrases = array();

        foreach($word_keys as $word_key) {
          $phrases[] = $lookup[$word_key];
        }

        return $phrases;

    }

    /**
     * Gets doc summaries of documents containing given words and meeting the
     * additional provided criteria
     * @param array $word_structs an array of word_structs. Here a word_struct
     *      is an associative array with at least the following fields
     *      KEYS -- an array of word keys
     *      RESTRICT_PHRASES -- an array of phrases the document must contain
     *      DISALLOW_PHRASES -- an array of words the document must not contain
     *      WEIGHT -- a weight to multiple scores returned from this iterator by
     *      INDEX_ARCHIVE -- an index_archive object to get results from
     * @param int $limit number of first document in order to return
     * @param int $num number of documents to return summaries of
     * @param object $index_archive index archive to use to get summaries from
     * @return array document summaries
     */
    function getSummariesByHash($word_structs, $limit, $num)
    {

        $iterators = array();
        foreach($word_structs as $word_struct) {
            if(!is_array($word_struct)) { continue;}
            $word_keys = $word_struct["KEYS"];
            $restrict_phrases = $word_struct["RESTRICT_PHRASES"];
            $disallow_phrases = $word_struct["DISALLOW_PHRASES"];
            $index_archive = $word_struct["INDEX_ARCHIVE"];
            $weight = $word_struct["WEIGHT"];
            $num_word_keys = count($word_keys);
            if($num_word_keys < 1) {continue;}

            for($i = 0; $i < $num_word_keys; $i++) {
                $word_iterators[$i] = 
                    new WordIterator($word_keys[$i], $index_archive, 0);
            }
            if($num_word_keys == 1) {
                $base_iterator = $word_iterators[0];
            } else {
                $base_iterator = new IntersectIterator($word_iterators, 0);
            }
            if($restrict_phrases == NULL && $disallow_phrases == NULL &&
                $weight == 1) {
                $iterators[] = $base_iterator;
            } else {
                $iterators[] = new PhraseFilterIterator($base_iterator, 
                    $restrict_phrases, $disallow_phrases, $weight, 0);
            }

        }
        $num_iterators = count($iterators);
        if( $num_iterators < 1) {
            return NULL;
        } else if($num_iterators == 1) {
            $union_iterator = $iterators[0];
        } else {
            $union_iterator = new UnionIterator($iterators, 0);
        }

        $to_retrieve = $limit + max(2*$num, 200);
        $group_iterator = new GroupIterator($union_iterator, 0);
        $num_retrieved = 0;
        $pages = array();
        while(is_array($next_docs = $group_iterator->nextDocsWithWord()) &&
            $num_retrieved < $to_retrieve) {
             foreach($next_docs as $doc_key => $doc_info) {
                 $summary = & $doc_info[CrawlConstants::SUMMARY];
                 unset($doc_info[CrawlConstants::SUMMARY]);
                 $pages[] = array_merge($doc_info, $summary);
                 $num_retrieved++;
                 if($num_retrieved >=  $to_retrieve) {

                     break 2;
                 }
             }
        }
        uasort($pages, "scoreOrderCallback");
        $pages = array_slice($pages, $limit, $num);
        if($num_retrieved < $to_retrieve && $limit<=$group_iterator->num_docs) {
            $results['TOTAL_ROWS'] = $num_retrieved;
        } else {
            $results['TOTAL_ROWS'] = max($group_iterator->num_docs, 
                $num_retrieved);
            /*num_docs is only approximate, so if gives contradictory info
              use $num_retrieved */
        }
        $results['PAGES'] = $pages;
        return $results;
    }

}

?>
