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
 *  Load the stem word function, if necessary
 */
require_once BASE_DIR."/lib/porter_stemmer.php";
 
/**
 * Reads in constants used as enums used for storing web sites
 */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Library of functions used to manipulate words and phrases 
 *
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class PhraseParser 
{
    /**
     * Converts a summary of a web page into a string of space separated words
     *
     * @param array $page associateive array of page summary data. Contains
     *      title, description, and links fields
     * @return string the concatenated words extracted from the page summary
     */
    static function extractWordStringPageSummary($page)
    {
        $punct = "\.|\,|\:|\;|\"|\'|\`|\[|\]|\{|\}|\(|\)|\!|\|";
        $title_phrase_string = mb_ereg_replace($punct, " ", 
            $page[CrawlConstants::TITLE]);
        $description_phrase_string = mb_ereg_replace($punct, " ", 
            $page[CrawlConstants::DESCRIPTION]);

        $page_string = $title_phrase_string . " " . $description_phrase_string;
        $page_string = preg_replace("/(\s)+/", " ", $page_string);

        return $page_string;
    }
    
    /**
     * Extracts all phrases (sequences of adjacent words) from $string of 
     * length less than or equal to $len.
     *
     * @param string $string subject to extract phrases from
     * @param int $len longest length of phrases to consider
     * @return array pairs of the form (phrase, number of occurrences)
     */
    static function extractPhrasesAndCount($string, 
        $len =  MAX_PHRASE_LEN) 
    {

        $phrases = array();

        for($i = 0; $i < $len; $i++) {
            $phrases = 
                array_merge($phrases,self::extractPhrasesOfLength($string, $i));
        }

        $phrase_counts = array_count_values($phrases);

        return $phrase_counts;
    }

    /**
     * Extracts all phrases (sequences of adjacent words) from $string of 
     * length exactly equal to $len.
     *
     * @param string $string subject to extract phrases from
     * @param int $len length of phrases to consider
     * @return array pairs of the form (phrase, number of occurrences)
     */
    static function extractPhrasesOfLength($string, $phrase_len) 
    {
        $phrases = array();
       
        for($i = 0; $i < $phrase_len; $i++) {
            $phrases = array_merge($phrases, 
                self::extractPhrasesOfLengthOffset($string, $phrase_len, $i));
        }

        return $phrases;
    }

    /**
     * Extracts phrases (sequences of adjacent words) from $string of 
     * length exactly equal to $len, beginning with the $offset'th word.
     * This extracts the the $len many words after offset, then the $len
     * many words after that, and so on.
     *
     * @param string $string subject to extract phrases from
     * @param int $len length of phrases to consider
     * @param int $offset the first word to begin with
     * @return array pairs of the form (phrase, number of occurrences)
     */
    static function extractPhrasesOfLengthOffset($string, 
        $phrase_len, $offset) 
    {
        $punct = "\.|\,|\:|\;|\"|\'|\`|\[|\]|\{|\}|\(|\)|\!|\||\&";
        $words = mb_split("[[:space:]]|".$punct, $string);

        $stems = array();


        for($i = $offset; $i < count($words); $i++) {
            if($words[$i] == "") {continue;}

            $phrase_number = ($i - $offset)/$phrase_len;
            if(!isset($stems[$phrase_number])) { 
                $stems[$phrase_number]="";
                $first_time = "";
            }
            $pre_stem = mb_strtolower($words[$i]);

            if(strlen($pre_stem) == mb_strlen($pre_stem)) {
                $stem = PorterStemmer::stem($pre_stem);
            } else {
                $stem = $pre_stem;
            }

            $stems[$phrase_number] .= $first_time.$stem;
            $first_time = " ";
        }

        return $stems;

    }
}
