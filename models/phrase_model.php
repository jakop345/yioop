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

        $index_archive_name = self::index_data_base_name . $this->index_name;

        $index_archive = new IndexArchiveBundle(
            CRAWL_DIR.'/cache/'.$index_archive_name);

        $results = NULL;

        $phrase_string = mb_ereg_replace("[[:punct:]]", " ", $phrase);
        $phrase_string = preg_replace("/(\s)+/", " ", $phrase_string);
        /*
            we search using the stemmed words, but we format snippets in the 
            results by bolding either
         */
        $query_words = explode(" ", $phrase_string); //not stemmed
        $words = 
            array_keys(PhraseParser::extractPhrasesAndCount($phrase_string)); 
            //stemmed
        if(isset($words) && count($words) == 1) {
            $phrase_string = $words[0];
        }
        $phrase_hash = crawlHash($phrase_string);

        $phrase_info = $index_archive->getPhraseIndexInfo($phrase_hash);
        if(isset($phrase_info[IndexingConstants::PARTIAL_COUNT]) &&
            $phrase_info[IndexingConstants::PARTIAL_COUNT] < 
                $low + $results_per_page) {
            $phrase_info = NULL;
        }

        if($phrase_info  != NULL) {

            $results = $index_archive->getSummariesByHash(
                $phrase_hash, $low, $results_per_page, NULL, NULL, $phrase_info);

            if(count($results) == 0) {
                $results = NULL;
            }

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
                foreach($quoteds as $quote_phrase) {
                    $hash_quote = crawlHash($quote_phrase);
                    if($index_archive->getPhraseIndexInfo($hash_quote) != NULL){
                        $hash_quoteds[] = $hash_quote;
                    }
                }

            }

            //get a raw list of words and their hashes 

            $hashes = array();
            foreach($words as $word) {
                $tmp = crawlHash($word); 
                $hashes[] = $tmp;
            }
            $hashes = array_merge($hashes, $hash_quoteds);
            $restrict_phrases = array_merge($words, $quoteds);
  
  
            $hashes = array_unique($hashes);
            $restrict_phrases = array_unique($restrict_phrases);

            $words_array = $index_archive->getSelectiveWords($hashes, 1);
            $word_keys = array_keys($words_array);
            $word_key = $word_keys[0];
            $count = $words_array[$word_key];
            if($count > 0 ) {
                $results = $index_archive->getSummariesByHash(
                    $word_key, $low, $results_per_page, 
                    $restrict_phrases, $phrase_hash);
            }
        }

        if($results == NULL) {
            $results['TOTAL_ROWS'] = 0;
        }
      
        if($format) {
            $formatted_words = array_merge($query_words, $words);
        } else {
            $formatted_words = NULL;
        }


        $output = $this->formatPageResults($results, $formatted_words);

        return $output;

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

}

?>
