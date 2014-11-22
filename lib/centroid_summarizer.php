<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
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
 * @author Mangesh Dahale mangeshadahale@gmail.com
 * @package seek_quarry
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Reads in constants used as enums used for storing web sites
 */
require_once BASE_DIR."/lib/crawl_constants.php";
/**
 * Contains the max_description_length for the summary
 */
require_once BASE_DIR."/lib/processors/page_processor.php";
/**
 * Contains function getTokenizer to get the object of the language specified.
 */
require_once BASE_DIR."/lib/phrase_parser.php";
/**
 * Class which may be used by TextProcessors to get a summary for a text
 * document that may later be used for indexing. It does this by doing
 * centroid-based clustering. It also generates a word cloud for a document
 */
class CentroidSummarizer
{
    /**
     * Number of bytes in a sentence before it is considered long
     * We use strlen rather than mbstrlen. This might actually be
     * a better metric of the potential of a sentence to have info.
     */
    const LONG_SENTENCE_LEN = 50;
    /**
     * Number of sentences in a document before only consider longer
     * sentences in centroid
     */
    const LONG_SENTENCE_THRESHOLD = 100;
    /**
     * Number of distinct terms to use in generating summary
     */
    const MAX_DISTINCT_TERMS = 1000;
    /**
     * Number of words in word cloud
     */
    const WORD_CLOUD_LEN = 5;
    /**
     * Number of nonzero centroid components
     */
    const CENTROID_COMPONENTS = 50;
    /**
     * Generates a centroid with which every sentence is ranked with cosine
     * ranking method and also generates a word cloud.
     * @param string $doc complete raw page to generate the summary from.
     * @param string $lang language of the page to decide which stop words to
     *     call proper tokenizer.php of the specified language.
     *
     * @return array array of summary and word cloud
     */
    static function getCentroidSummary($doc, $lang)
    {
        $doc = self::pageProcessing($doc);
        /* Format the document to remove characters other than periods and
           alphanumerics.
        */
        $formatted_doc = self::formatDoc($doc);
        $stop_obj = PhraseParser::getTokenizer($lang);
        /* Splitting into sentences */
        $out_sentences = self::getSentences($doc);
        $n = count($out_sentences);
        $sentences = array();
        if($stop_obj && method_exists($stop_obj, "stopwordsRemover")) {
            for($i = 0; $i < $n; $i++ ) {
                $sentences[$i] = $stop_obj->stopwordsRemover(
                    self::formatDoc($out_sentences[$i]));
             }
        } else {
            $sentences = $out_sentences;
        }
        /*  Splitting into terms */
        $terms = array();
        foreach($sentences as $sentence) {
            $terms = array_merge($terms,
                PhraseParser::segmentSegment($sentence, $lang));
        }
        $terms = array_filter($terms);
        $terms_counts = array_count_values($terms);
        arsort($terms_counts);
        $terms_counts = array_slice($terms_counts, 0, self::MAX_DISTINCT_TERMS);
        $terms = array_unique(array_keys($terms_counts));
        $t = count($terms);
        if($t == 0) {
            return array("", "");
        }
        /* Initialize Nk array(Number of sentences the term occurs) */
        $nk = array();
        $nk = array_fill(0, $t, 0);
        $nt = array();
        /* Count TF for each word */
        for($i = 0; $i < $n; $i++) {
            for($j = 0; $j < $t; $j++) {
                if(strpos($sentences[$i], $terms[$j]) !== false) {
                    $nk[$j]++;
                }
            }
        }
        /* Calculate weights of each term for every sentence */
        $w = array();
        $idf = array();
        $idf_temp = 0;
        for($k = 0; $k < $t; $k++) {
            if($nk[$k] == 0) {
                $idf_temp = 0;
                $tmp = 0;
            } else {
                $idf_temp = $n / $nk[$k];
                $tmp = log($idf_temp);
            }
            $idf[$k] = $tmp;
        }
        /* Count TF for finding centroid */
        $wc = array();
        $max_nt = -1;
        $b = "\b";
        if(in_array($lang, array("zh-CN", "ja", "ko"))) {
            $b = "";
        }
        for($j = 0; $j < $t; $j++) {
            $nt = @preg_match_all("/$b{$terms[$j]}$b/", $formatted_doc);
            $wc[$j] = $nt * $idf[$j];
            if(is_nan($wc[$j]) || is_infinite($wc[$j])) {
                $wc[$j] = 0;
            }
        }
        /* Calculate centroid */
        arsort($wc);
        $centroid = array_slice($wc, 0, self::CENTROID_COMPONENTS, true);
        /* Initializing centroid weight array by 0 */
        $wc = array_fill(0, $t, 0);
        /* Word cloud */
        $i = 0;
        $word_cloud = array();
        foreach($centroid as $key => $value) {
            $wc[$key] = $value;
            if($i < self::WORD_CLOUD_LEN) {
                $word_cloud[$i] = $terms[$key];
            }
            $i++;
        }
        if(strlen($formatted_doc) < PageProcessor::$max_description_len
            || $n == 1) {
            //if input short only use above to get a word cloud
            $formatted_doc = substr($formatted_doc, 0,
                PageProcessor::$max_description_len);
            return array($formatted_doc, $word_cloud);
        }
        ksort($wc);
        /* Calculate similarity measure between centroid and each sentence */
        $sim = array();
        for($i=0; $i < $n; $i++) {
            $a = $b1 = $b2 = $c1 = $c2 = $d = 0;
            for($k = 0; $k < $t; $k++) {
                    $wck = $wc[$k];
                    $idfk = $idf[$k];
                    $tmp = substr_count($sentences[$i], $terms[$k]);
                    $wik = ($tmp > 0) ? $idfk * (1 + log($tmp)) : 0;
                    $a += ($wik * $wck * $idfk);
                    $b1 += ($wik * $wik);
                    $c1 += ($wck * $wck);
            }
            $b2 = sqrt($b1);
            $c2 = sqrt($c1);
            $d = $b2 * $c2;
            if($d == 0) {
                $sim[$i] = 0;
            } else {
                $sim[$i] = $a / $d;
            }
        }
        arsort($sim);
        /* Getting how many sentences should be there in summary */
        $top = self::summarySentenceCount($out_sentences, $sim);
        $sum_array = array();
        $sum_array = array_slice($sim, 0, $top - 1, true);
        ksort($sum_array);
        /* Printing Summary */
        $summary = '';
        foreach($sum_array as $key => $value) {
            $summary .= "$out_sentences[$key]".". ";
        }
        /* Summary of text summarization */
        return array($summary, $word_cloud);
    }
    /**
     * Calculates how many sentences to put in the summary to match the
     * MAX_DESCRIPTION_LEN.
     *
     * @param array $sentences sentences in doc in their original order
     * @param array $sim associative array of sentence-number-in-doc =>
     *      similarity score to centroid (sorted from highest to lowest score).
     * @return int number of sentences
     */
    static function summarySentenceCount($sentences, $sim)
    {
        $top = NULL;
        $count = 0;
        foreach($sim as $key => $value)
        {
            if($count < PageProcessor::$max_description_len) {
                $count += strlen($sentences[$key]);
                $top++;
            }
        }
        return $top;
    }
    /**
     * Breaks any content into sentences by splitting it on spaces or carriage
     *   returns
     * @param string $content complete page.
     * @return array array of sentences from that content.
     */
    static function getSentences($content)
    {
        $lines = preg_split(
            '/(\.|\||\!|\?|！|？|。)\s+|(\n|\r)(\n|\r)+|\s{5}/',
            $content, 0, PREG_SPLIT_NO_EMPTY);
        $out = array();
        $sentence = "";
        $count = 0;
        $theshold_factor = 1;
        foreach($lines as $line) {
            $sentence .= " " . $line;
            if(strlen($line) < 2) {
                continue;
            }
            if($count < self::LONG_SENTENCE_THRESHOLD ||
                strlen($sentence) > $theshold_factor * self::LONG_SENTENCE_LEN){
                $sentence = preg_replace("/\s+/ui", " ", $sentence);
                $out[] = trim($sentence);
                $count++;
                $theshold_factor =
                    pow(1.5, floor($count/self::LONG_SENTENCE_THRESHOLD));
            }
            $sentence = "";
        }
        if(trim($sentence) != "") {
            $sentence = preg_replace("/\s+/ui", " ", $sentence);
            $out[] = trim($sentence);
        }
        return $out;
    }
    /**
     * Formats the sentences to remove all characters except words,
     *   digits and spaces
     * @param string $sent complete page.
     * @return string formatted sentences.
     */
    static function formatSentence($sent)
    {
        $sent = trim(preg_replace('/[^\p{L}\p{N}\s]+/u',
            ' ', mb_strtolower($sent)));
        return $sent;
    }
    /**
     * Formats the document to remove carriage returns, hyphens and digits
     * as we will not be using digits in word cloud.
     * The formatted document generated by this function is only used to
     * compute centroid.
     * @param string $content formatted page.
     * @return string formatted document.
     */
    static function formatDoc($content)
    {
        $substitute = array('/[\n\r\-]+/', '/[^\p{L}\s\.]+/u', '/[\.]+/');
        $content = preg_replace($substitute, ' ', mb_strtolower($content));
        return $content;
    }
    /**
     * This function does an additional processing on the page
     * such as removing all the tags from the page
     * @param string $page complete page.
     * @return string processed page.
     */
    static function pageProcessing($page)
    {
        $substitutions = array('@<script[^>]*?>.*?</script>@si',
            '/\&nbsp\;|\&rdquo\;|\&ldquo\;|\&mdash\;/si',
            '@<style[^>]*?>.*?</style>@si', '/[\^\(\)]/',
            '/\[(.*?)\]/', '/\t\n/'
        );
        $page = preg_replace($substitutions, ' ', $page);
        $page = preg_replace('/\s{2,}/', ' ', $page);
        $new_page = preg_replace("/\<br\s*(\/)?\s*\>/", "\n", $page);
        $changed = false;
        if($new_page != $page) {
            $changed = true;
            $page = $new_page;
        }
        $page = preg_replace("/\<\/(h1|h2|h3|h4|h5|h6|table|tr|td|div|".
            "p|address|section)\s*\>/", "\n\n", $page);
        $page = preg_replace("/\<a/", " <a", $page);
        $page = preg_replace("/\&\#\d{3}(\d?)\;|\&\w+\;/", " ", $page);
        $page = preg_replace("/\</", " <", $page);
        $page = strip_tags($page);

        if($changed) {
            $page = preg_replace("/(\r?\n[\t| ]*){2}/", "\n", $page);
        }
        $page = preg_replace("/(\r?\n[\t| ]*)/", "\n", $page);
        $page = preg_replace("/\n\n\n+/", "\n\n", $page);
        return $page;
    }
}
?>
