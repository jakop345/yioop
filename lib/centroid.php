<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
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
 * Load in locale specific tokenizing code
 */
foreach(glob(LOCALE_DIR."/*/resources/tokenizer.php")
    as $filename) {
    require_once $filename;
}

class CentroidSummarizer
{
    /**
     *  Generates a centroid with which every sentence is ranked with cosine
     *  ranking method and also generates a word cloud.
     *  @param string $doc complete raw page to generate the summary from.
     *  @param string $lang language of the page to decide which stop words to
     *      call proper tokenizer.php of the specified language.
     *
     *  @return array array of summary and word cloud
     */
    static function getCentroidSummary($doc, $lang)
    {
        $doc = self::pageProcessing($doc);
        /* Format the document to remove characters other than periods and
        alphanumerics.
        */
        $formatted_doc = self::formatDoc($doc);
        $stop_obj = PhraseParser::getTokenizer($lang);
        if($stop_obj != NULL) {
            $doc_stop = $stop_obj -> stopwordsRemover($doc);
        } else {
            $doc_stop = $doc;
        }

        /* Spliting into sentences */
        $sentences = self::getSentences($doc);
        $n = count($sentences);

        /*  Spliting into terms */
        $doc_st = self::formatSentence($doc_stop);
        $term = preg_split("/[\s,]+/u", $doc_st, -1, PREG_SPLIT_NO_EMPTY);
        $terms = array_unique($term);
        sort($terms);
        $t = count($terms);

        /* Initialize term-frequecy array */
        $tf = array();
        $tf = array_fill(0, $t, array_fill(0, $n, 0));

        /* Initialize Nk array(Number of Documents the term occurs) */
        $nk = array();
        $nk = array_fill(0, $t, 0);

        /* Count TF for each word */
        for($i=0;$i<$n;$i++) {
            for($j=0;$j<$t;$j++) {
                $nt = substr_count($sentences[$i], $terms[$j]);
                $tf[$i][$j] = 1+ log($nt);
                if($nt!=0) {
                    $nk[$j]++;
                }
                else {
                    $tf[$i][$j] = 0;
                }
            }
        }
        /* Calculate weights of each term for every sentence */
        $w = array();
        $idf = array();
        $idf_temp = 0;

        for($k=0; $k<$t; $k++) {
            $idf_temp = @($n/$nk[$k]);
            if($nk[$k] == 0) {
                $idf_temp = 0;
            }
            $tmp = @log($idf_temp);
            $idf[$k] = $tmp;
            for($i = 0; $i < $n; $i++) {
                if($tmp >= 0 && $tmp < 10) {
                    $w[$i][$k] = $tf[$i][$k] * $tmp;
                } else {
                    $w[$i][$k] = 0;
                }

            }
        }

        /* Count TF for finding centroid */
        $doc_centroid = preg_replace('/[\.]+/', ' ', $formatted_doc);
        $wc = array();
        for($j=0; $j<$t; $j++) {
            $nt = preg_match_all('/\b'.$terms[$j].'\b/', $doc_centroid);
            $tfc[$j] = 1 + log($nt);
            $wc[$j] = $tfc[$j] * $idf[$j];
            if(is_nan($wc[$j]) || is_infinite($wc[$j]))
                $wc[$j] = 0;
        }
        /* Calculate centroid */
        arsort($wc);
        $centroid = array();
        $centroid = array_slice($wc, 0, 5, true);

        /* Initializing centroid weight array by 0 */
        $wc = array_fill(0, $t, 0);

        /* Word cloud */
        $i = 0;
        $word_cloud = array();
        foreach($centroid as $key => $value) {
            $wc[$key] = $value;
            $word_cloud[$i] = $terms[$key];
            $i++;
        }
        ksort($wc);

        /* Calculate similarity measure between centroid and each sentence */
        $sim=array();
        for($i=0; $i < $n; $i++) {
            $a = $b1 = $b2 = $c1 = $c2 = $d = 0;
            for($k=0; $k<$t; $k++) {
                    $wik = $w[$i][$k];
                    $wck = $wc[$k];
                    $idfk = $idf[$k];
                    $a += ($wik * $wck * $idfk);
                    $b1 += ($wik * $wik);
                    $c1 += ($wck * $wck);
            }
            $b2 = sqrt($b1);
            $c2 = sqrt($c1);
            $d = $b2 * $c2;
            $sim[$i] = @($a / $d);
            if($d == 0) {
                $sim[$i] = 0;
            }
        }
        arsort($sim);
        /* Getting how many sentences should be there in summary */
        $top = self::summarySentenceCount($sentences, $sim);
        $sum_array = array();
        $sum_array = array_slice($sim, 0, $top-1, true);
        ksort($sum_array);
        /* Printing Summary */
        $summary = '';
        $d = null;
        foreach($sum_array as $key => $value) {
            $summary .= "$sentences[$key]".". ";
        }

        /* Summary of text summarization */
        $words = explode(" ", $doc);
        $sum_words = explode(" ", $summary);
        return array($summary, $word_cloud);
    }
    /**
     *  Calculates how many sentences to put in the summary to match the
     *  MAX_DESCRIPTION_LEN.
     *  @param string $doc complete raw page to generate the summary from.
     *  @param string $lang language of the page to decide which stop words to
     *      call proper tokenizer.php of the specified language.
     *
     *  @return array array of summary and word cloud
     */
    static function summarySentenceCount($sentences, $sim)
    {
        $top = null;
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
     *  Breaks any content into sentences by splitting it on spaces or carriage
     *    returns
     *  @param string $content complete page.
     *  @return array array of sentences from that content.
     */
    static function getSentences($content)
    {
        $content = preg_split("/\.\s|[\n\r]+/", $content, -1,
            PREG_SPLIT_NO_EMPTY);
        return $content;
    }
    /**
     *  Formats the sentences to remove all characters except words,
     *    digits and spaces
     *  @param string $sent complete page.
     *  @return string formatted sentences.
     */
    static function formatSentence($sent)
    {
        $sent = trim(preg_replace('/[^\p{L}\p{N}\s]+/u',
            ' ', mb_strtolower($sent)));
        return $sent;
    }

    /**
     *  Formats the document to remove carriage returns, hyphens and digits
     *    as we will not be using digits in word cloud.
     *    formatted document generated by this function is only used to compute
     *    centroid.
     *  @param string $content formatted page.
     *  @return string formatted document.
     */
    static function formatDoc($content)
    {
        $substitute = array('/[\n\r\-]+/', '/[^\p{L}\s\.]+/u');
        $content = preg_replace($substitute, '', mb_strtolower($content));
        return $content;
    }

    /**
     *  This function does an additional processing on the page
     *    such as removing all the tags from the page
     *  @param string $page complete page.
     *  @return string processed page.
     */
    static function pageProcessing($page)
    {
        $substitutions = array('@<script[^>]*?>.*?</script>@si',
            '/\&nbsp\;|\&rdquo\;|\&ldquo\;|\&mdash\;/si',
            '@<style[^>]*?>.*?</style>@si', '/[\^\(\)]/',
            '/\[(.*?)\]/', '/\t\n/'
        );
        $page = preg_replace($substitutions, ' ', $page);
        $page = preg_replace('/\s{2,}/', '', $page);
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