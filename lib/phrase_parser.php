<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load the stem word functions, if necessary
 */
foreach(glob(BASE_DIR."/lib/stemmers/*_stemmer.php")
    as $filename) {
    require_once $filename;
}

/**
 * Load the n word grams File
 */
require_once BASE_DIR."/lib/nword_grams.php";

/**
 * Reads in constants used as enums used for storing web sites
 */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Library of functions used to manipulate words and phrases
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class PhraseParser
{
    /**
     * Language tags and their corresponding stemmer
     * @var array
     */
     static $STEMMERS = array(
        'en' => "EnStemmer",
        'en-US' => "EnStemmer",
        'en-GB' => "EnStemmer",
        'en-CA' => "EnStemmer",
     );

    /**
     * Language tags and their corresponding character n-gram length
     * (should only use one of character n-grams or stemmer)
     */
     static $CHARGRAMS = array(
        'ar' => 5,
        'de' => 5,
        'es' => 5,
        'fr' => 5,
        'fr-FR' => '5',
        'he' => 5,
        'hi' => 5,
        'kn' => 5,
        'in-ID' => 5,
        'pt' => 5,
        'it' => 5,
        'ko' => 3,
        'ja' => 3,
        'ru' => 5,
        'th' => 4,
        'tr' => 6,
        'zh-CN' => 2,
        'cn' => 2,
        'zh' => 2
     );

    /**
     * Converts a summary of a web page into a string of space separated words
     *
     * @param array $page associative array of page summary data. Contains
     *      title, description, and links fields
     * @return string the concatenated words extracted from the page summary
     */
    static function extractWordStringPageSummary($page)
    {
        if(isset($page[CrawlConstants::TITLE])) {
            $title_phrase_string = mb_ereg_replace(PUNCT, " ",
                $page[CrawlConstants::TITLE]);
        } else {
            $title_phrase_string = "";
        }
        if(isset($page[CrawlConstants::DESCRIPTION])) {
            $description_phrase_string = mb_ereg_replace(PUNCT, " ",
                $page[CrawlConstants::DESCRIPTION]);
        } else {
            $description_phrase_string = "";
        }
        $page_string = $title_phrase_string . " " . $description_phrase_string;
        $page_string = preg_replace("/(\s)+/", " ", $page_string);

        return $page_string;
    }

    /**
     * Extracts all phrases (sequences of adjacent words) from $string. Does
     * not extract terms within those phrase. Array key indicates position
     * of phrase
     *
     * @param string $string subject to extract phrases from
     * @param string $lang locale tag for stemming
     * @param bool $orig_and_grams if char-gramming is done whether to keep
     *      the original term as well in what's returned
     * @return array of phrases
     */
    static function extractPhrases($string,$lang = NULL,$orig_and_grams = false)
    {
        self::canonicalizePunctuatedTerms($string, $lang);
        $phrase_pos = self::extractPhrasesInLists($string, $lang, 
            $orig_and_grams, false);
        $phrases = array_keys($phrase_pos);
        return $phrases;
    }

    /**
     * Extracts all phrases (sequences of adjacent words) from $string. Does
     * not extract terms within those phrase. Returns an associative array
     * of phrase => number of occurrences of phrase
     *
     * @param string $string subject to extract phrases from
     * @param string $lang locale tag for stemming
     * @param bool $orig_and_grams if char-gramming is done whether to keep
     *      the original term as well in what's returned
     * @return array pairs of the form (phrase, number of occurrences)
     */
    static function extractPhrasesAndCount($string, $lang = NULL, 
        $orig_and_grams = false)
    {

        self::canonicalizePunctuatedTerms($string, $lang);

        $phrases = self::extractPhrasesInLists($string, $lang, $orig_and_grams, 
            false);

        $phrase_counts = array_count_values($phrases);

        return $phrase_counts;
    }

    /**
     * Extracts all phrases (sequences of adjacent words) from $string. Does
     * extract terms within those phrase. 
     *
     * @param string $string subject to extract phrases from
     * @param string $lang locale tag for stemming
     * @param bool $orig_and_grams if char-gramming is done whether to keep
     *      the original term as well in what's returned
     * @return array word => list of positions at which the word occurred in
     *      the document
     */
    static function extractPhrasesInLists($string,
        $lang = NULL, $orig_and_grams = false, $phrases_and_terms = true)
    {
        $phrase_lists = array();

        self::canonicalizePunctuatedTerms($string, $lang);
        $pre_phrases = 
            self::extractTermsAndFilterPhrases($string, $lang,$orig_and_grams);
        $phrases = array();
        $j = 0;
        foreach($pre_phrases as $pre_phrase) {
            $len = count($pre_phrase);
            if($len == 1) {
                $phrases[$pre_phrase[0]][] = $j++;
            } else {
                $phrases[implode(" ", $pre_phrase)][] = $j;
                if($phrases_and_terms) {
                    foreach($pre_phrase as $term) {
                        $phrases[$term][] = $j++;
                    }
                }
            }
        }
        return $phrases;
    }

    /**
     * This functions tries to convert acronyms, e-mail, urls, etc into
     * a format that does not involved punctuation that will be stripped
     * as we extract phrases.
     *
     * @param &$string a string of words, etc which might involve such terms
     * @param $lang a language tag to use as part of the canonicalization 
     *      process not used right now
     */
    static function canonicalizePunctuatedTerms(&$string, $lang = NULL)
    {
        
        $acronym_pattern = "/[A-Za-z]\.(\s*[A-Za-z]\.)+/";
        $string = preg_replace_callback($acronym_pattern, 
            function($matches) {
                $result = "_".mb_strtolower(
                    mb_ereg_replace("\.", "", $matches[0]));
                return $result;
            }, $string);

        $ampersand_pattern = "/[A-Za-z]+(\s*(\s(\'n|\'N)\s|\&)\s*[A-Za-z])+/";
        $string = preg_replace_callback($ampersand_pattern, 
            function($matches) {
                $result = mb_strtolower(
                    mb_ereg_replace("\s*(\'n|\'N|\&)\s*", "_and_",$matches[0]));
                return $result;
            }, $string);

        $url_or_email_pattern = 
            '@((http|https)://([^ \t\r\n\v\f\'\"\;\,<>])*)|'.
            '([A-Z0-9._%-]+\@[A-Z0-9.-]+\.[A-Z]{2,4})@i';
        $string = preg_replace_callback($url_or_email_pattern, 
            function($matches) {
                $result =  mb_ereg_replace("\.", "_d_",$matches[0]);
                $result =  mb_ereg_replace("\:", "_c_",$result);
                $result =  mb_ereg_replace("\/", "_s_",$result);
                $result =  mb_ereg_replace("\@", "_a_",$result);
                $result =  mb_ereg_replace("\[", "_bo_",$result);
                $result =  mb_ereg_replace("\]", "_bc_",$result);
                $result =  mb_ereg_replace("\(", "_po_",$result);
                $result =  mb_ereg_replace("\)", "_pc_",$result);
                $result =  mb_ereg_replace("\?", "_q_",$result);
                $result =  mb_ereg_replace("\=", "_e_",$result);
                $result =  mb_ereg_replace("\&", "_a_",$result);
                $result = mb_strtolower($result);
                return $result;
            }, $string);
    }

    /**
     *
     */
    static function extractTermsAndFilterPhrases($string,
        $lang = NULL, $orig_and_grams = false)
    {
        mb_internal_encoding("UTF-8");
        //split first on puctuation as n word grams shouldn't cross punctuation
        $fragments = mb_split(PUNCT, $string);

        $final_terms = array();

        if(isset(self::$STEMMERS[$lang])) {
            $stemmer = self::$STEMMERS[$lang];
            $stem_obj = new $stemmer(); //for php 5.2 compatibility
        } else {
            $stemmer = NULL;
        }

        foreach($fragments as $fragment) {
            $pre_terms = mb_split("[[:space:]]", $fragment);
            if($pre_terms == array()) continue;
            $terms = array();
            if(isset(self::$CHARGRAMS[$lang])) {
                foreach($pre_terms as $pre_term) {
                    if($pre_term == "") continue;
                    $ngrams = self::getCharGramsTerm(array($pre_term), $lang);
                    if($orig_and_grams) {
                        $terms[]  = $pre_term;
                        $terms = array_merge($terms, $ngrams);
                    } else if(count($ngrams) > 0) {
                        $terms = array_merge($terms, $ngrams);
                    }
                }
            } else {
                $terms = $pre_terms;
            }
            $stems = array();
            if($stemmer != NULL) { 
                foreach($terms as $term) {
                    $pre_stem = mb_strtolower($term);
                    $stems[] = $stem_obj->stem($pre_stem);
                }
            } else {
                foreach($terms as $term) {
                    $stems[] = mb_strtolower($term);
                }
            }
            $accumulators = array();
            $phrases = array();
            $i = 0;
            $num_stems = count($stems);
            while($i < $num_stems) {
                $tmp = $stems[$i];
                if($stems[$i] == "") {
                    $i++;
                    continue;
                }
                $j = $i + 1;
                $max_j = $i;
                $cont_ngram = true;
                while($cont_ngram && $j < $num_stems) {
                    $tmp .= " " . $stems[$j];
                    $isngram = NWordGrams::ngramsContains($tmp, $lang, "all");
                    if($isngram) {
                        $max_j = $j;
                    }
                    $cont_ngram = NWordGrams::ngramsContains($tmp."*", $lang,
                        "all");
                    $j++;
                }
                $phrases[] = array_slice($stems, $i, $max_j - $i + 1);
                $i = $max_j + 1;
            }

            $phrases = array_values($phrases);
            $final_terms = array_merge($final_terms, $phrases);
        }
        return $final_terms;
    }

    /**
     * Returns the characters n-grams for the given terms where n is the length
     * Yioop uses for the language in question. If a stemmer is used for
     * language then n-gramming is no done and this just returns an empty array
     *
     * @param array $term the terms to make n-grams for
     * @param string $lang locale tag to determine n to be used for n-gramming
     *
     * @return array the n-grams for the terms in question
     */
    static function getCharGramsTerm($terms, $lang)
    {
        mb_internal_encoding("UTF-8");
        if(isset(self::$CHARGRAMS[$lang])) {
            $n = self::$CHARGRAMS[$lang];
        } else {
            return array();
        }

        $ngrams = array();

        foreach($terms as $term) {
            $pre_gram = $term;
            $last_pos = mb_strlen($pre_gram) - $n;
            if($last_pos < 0) {
                $ngrams[] = $pre_gram;
            } else {
                for($i = 0; $i <= $last_pos; $i++) {
                    $tmp = mb_substr($pre_gram, $i, $n);
                    if($tmp != "") {
                        $ngrams[] = $tmp;
                    }
                }
            }
        }
        return $ngrams;
    }

    /**
     *
     */
    static function stemTerms($string, $lang)
    {
        $terms = mb_split("[[:space:]]", $string);
        if(isset(self::$STEMMERS[$lang])) {
            $stemmer = self::$STEMMERS[$lang];
            $stem_obj = new $stemmer(); //for php 5.2 compatibility
        } else {
            $stemmer = NULL;
        }
        $stems = array();
        if($stemmer != NULL) {
            foreach($terms as $term) {
                $pre_stem = mb_strtolower($term);
                $stems[] = $stem_obj->stem($pre_stem);
            }
        } else {
            foreach($terms as $term) {
                $stems[] = mb_strtolower($term);
            }
        }

        return $stems;
    }
}
