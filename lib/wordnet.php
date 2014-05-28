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
 * @author Shailesh Padave shaileshpadave49@gmail.com
 * @package seek_quarry
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
/**
 * Get the part of speech tagging for given sentence
 */
$pos_tagger = LOCALE_DIR . "/en-US/resources/part_of_speech_tagger.php";
if(file_exists($pos_tagger)) {
    require_once $pos_tagger;
}
unset($pos_tagger);
/**
 *
 */
class WordNet
{
    /**
     *  Extracts similar words from wordnet output for given query.
     *  Part of speech tagging processed on input, output is given to wordnet
     *  processing. Wordnet function provides list of words as per ranking.
     *  For those words, count will be calculated from posting list and top 2
     *  words are selected.
     *  @param string $orig_query input query from user
     *  @param string $index_name selected index for search engine
     *  @param string $lang language
     *  @param integer $threshold once count in posting list for any word
     *      reaches to threshold then return the number
     *  @return array of top two words
     */
    static function getSimilarWords($orig_query, $index_name, $lang,
        $threshold = 10)
    {
        $replacements = array();
        $scores = array();
        $tagged_query = PartOfSpeechTagger::tagPhrase($orig_query);
        $replacements = self::processOutput($orig_query, $tagged_query);
        foreach($replacements as $replacement) {
            $cnt = self::numDocsIndex($replacement, $threshold, $index_name,
                $lang);
            $scores[$replacement] = $cnt;
        }
        arsort($scores);
        $result = array();
        $i = 0;
        foreach($scores as $k => $v) {
            $result[$i] = $k;
            $i++;
            if($i >= 2) { break; }
        }
        return $result;
    }

    /**
     *  Extracts the similar words from WordNet output generated on command line
     *  It is stored in text file and used for parsing similar words and
     *  sentences.
     *  Sentences are then used in cosine ranking along with query string.
     *  According to score, synonyms are selected.
     *  @param String $query : Query entered by user
     *  @param String $pos_query : Same query processed after Part of speech
     *      tagging
     *  @return String array $result with all possible replacements for
     *      given query.
     */
    static function processOutput($query, $pos_query)
    {
        $replacements = array();
        $result = array();
        // Get the query from user and do PartofSpeech Tagging on it
        $terms = preg_split("/\s+|\-/", trim($query));
        // Find out exact meaning of NN,JJ, in terms of Noun, Verb
        $output_words = preg_split("/\s+/",
            trim($pos_query), -1, PREG_SPLIT_NO_EMPTY);
        $output_words_cnt = count($output_words);
        $word_type = NULL;
        $similar_words = array();
        $known_word_types = array("NN", "VB", "AJ", "AV");
        for ($i = 0; $i < $output_words_cnt; $i++) {
            $wordnet_op = "";
            $pos = strpos($output_words[$i], '~');
            $word_type = trim(substr($output_words[$i], $pos + 1));
            if(!in_array($word_type, $known_word_types)) {
                $word_type = "NA";
            }
            $current_word = substr($output_words[$i], 0, $pos);
            if($word_type != "NA") {
                $data = "";
                exec(WORDNET_EXEC . " {$terms[$i]} -over", $data);
                $wordnet_op = implode(' ', $data);
                /*  Gets a 2D array with matched words and respective
                 *  cosine ranking scores
                 */
                $avg_score = self::splitOutput($word_type, $query, $wordnet_op);
                if ($avg_score == NULL) { continue; }
                if($avg_score) {
                    asort($avg_score[0]);
                    $extracted_words = $avg_score[1];
                    foreach ($avg_score[0] as $key => $value) {
                        $sorted_score_key = $key;
                        $sorted_score_value = $value;
                    }
                    $similar_words[$i] = $extracted_words[$sorted_score_key];
                    //To remove similar words as query word
                    $patterns = array('/^'.$current_word.'\,/',
                        '/\,'.$current_word.'\,/');
                    $extracted_words[$sorted_score_key] = preg_replace(
                        $patterns,'', $extracted_words[$sorted_score_key]);
                    $replacements += array($current_word =>
                        $extracted_words[$sorted_score_key]);
                }
            }
        }
        foreach ($replacements as $words => $similar_word) {
            $arr_similar_word = explode(",", trim($similar_word));
            $arr_similar_word_cnt = count($arr_similar_word);
            $arr_similar_word[$arr_similar_word_cnt - 1] =
                trim(preg_replace('/[\-]+/', '',
                $arr_similar_word[$arr_similar_word_cnt - 1]));
            $replacements[$words] = $arr_similar_word;
        }
        $i = 0;
        foreach($replacements as $word => $rep) {
            foreach ($rep as $replace) {
                if (substr_count(trim($replace), ' ')) {
                    $replace = preg_replace('/~[\w]+/','',$replace);
                    $new_string =
                        preg_replace('/' . $word . '/', trim($replace), $query);
                } else {
                    $new_string =
                        preg_replace('/' . $word . '/', trim($replace), $query);
                }
                if(mb_strtolower($new_string) != mb_strtolower($query)){
                    $result[$i] = $new_string;
                    $i++;
                }
            }
        }
        return $result;
    }
    /**
     *  Returns the number of documents in an index that a phrase occurs in.
     *  If it occurs in more than threshold documents then cut off search.
     *
     *  @param string $phrase 
     *  @param integer $threshold once count in posting list for any word
     *      reaches to threshold then return the number
     *  @param string $index_name selected index for search engine
     *  @param string $lang language
     *  @return int number of documents phrase occurs in
     */
    static function numDocsIndex($phrase, $threshold, $index_name, $lang)
    {
        PhraseParser::canonicalizePunctuatedTerms($phrase, $lang);
        $terms = PhraseParser::stemCharGramSegment($phrase, $lang);
        $num  = count($terms);
        if($index_name == NULL) {
            return 0;
        }
        if(count($terms) > MAX_QUERY_TERMS) {
            $terms  = array_slice($terms, 0, MAX_QUERY_TERMS);
        }
        $whole_phrase = implode(" ", $terms);
        return IndexManager::numDocsTerm($whole_phrase, $index_name,
            $threshold);
    }
    /**
     * This function is used to get similar words from WordNet as per word type.
     * @param word_type is the type of word such as noun, verb, adjective,
     * adverb
     * @param query is query provided by user in search box
     * @return array of similar words along with their score.
     */
    static function splitOutput($word_type, $query, $wordnet_op)
    {
        if ($wordnet_op != "") {
            $paragraphs =
                preg_split("/\n\r/", $wordnet_op, -1, PREG_SPLIT_NO_EMPTY);
            $pos_para = array();
            $pos_type_para = array();
            $word_map = array("VB" => "verb", "NN" => "noun", "AJ" => "adj",
                "AV" => "adv");
            if(isset($word_map[$word_type])) {
                $full_name = $word_map[$word_type];
                $pos_type_para =preg_grep("/\bThe\s$full_name\s/", $paragraphs);
            }
            if($pos_type_para) {
                $temp_para = array_values($pos_type_para);
                $para = $temp_para[0];
                $pos_para  =
                    preg_split("/\d\.\s/", $para, -1, PREG_SPLIT_NO_EMPTY);
                $cnt_pos_para = count($pos_para);
                $sentence = array();
                $wordmatch = array();
                $avg_score = array();
                for ($i = 1; $i < $cnt_pos_para; $i++) {
                    preg_match_all('/\"(.*?)\"/', $pos_para[$i], $match);
                    // to seperate out the words
                    preg_match('/[\w+\s\,\.\']+\s\-+/',
                        $pos_para[$i], $matchword);
                    $wordmatch[$i] = trim($matchword[0]);
                    $sent_count = count($match[1]);
                    $score = array();
                    for ($j = 0; $j < $sent_count; $j++) {
                        $sentence = $match[1][$j];
                        $score[$j] =
                            self::getCosineRank(explode(' ', $query),
                                explode(' ', $match[1][$j]));
                        /*  If Cosine similarity is zero then go for
                         *  intersection similarity ranking
                         */
                        if($score[$j] == 0) {
                            $score[$j] =
                                self::getIntersection($query, $match[1][$j]);
                        }
                        $cosine_score = $score[$j];
                    }
                    if ($sent_count) {
                        $avg_score[$i] = array_sum($score) / $sent_count;
                    } else {
                        $avg_score[$i] = 0;
                    }
                }
                return array($avg_score, $wordmatch);
            } else {
                return NULL;
            }
        } else {
            return NULL;
        }
    }
    /**
     *  This function is used to get the score by Cosine-similarity
     *  ranking method between two sentences.
     *  @param array sentenceA first input sentence as array of terms
     *  @param array sentenceB second input sentence as array of terms
     *  @return score by Cosine-similarity ranking method
     */
    static function getCosineRank($sentenceA, $sentenceB)
    {
        $result = 0;
        $cnt_sentA = 0;
        $cnt_sentB = 0;
        $arrayA = array();
        $arrayB = array();
        $x = 0;
        $merged_words = array_unique(array_merge($sentenceA, $sentenceB));
        foreach ($sentenceA as $word) {
            $arrayA[$word] = 0;
        }
        foreach ($sentenceB as $word) {
            $arrayB[$word] = 0;
        }
        foreach($merged_words as $word) {
            $resA = isset($arrayA[$word]) ? 1 : 0;
            $resB = isset($arrayB[$word]) ? 1 : 0;
            $result += $resA * $resB;
            $cnt_sentA += $x;
            $cnt_sentB += $resB;
        }
        $score = ($cnt_sentA * $cnt_sentB) != 0 ?
            $result / sqrt($normA * $cnt_sentB) : 0;
        return $score;
    }
    /**
     *  Computes the ratio of the number of terms shared by two phrases
     *  divided by the average number of terms in a pair of phrases.
     *
     *  @param string $phrase1 first phrase to consider
     *  @param string $phrase2 second  phrase to consider
     *  @return float the above described ratio
     */
    static function getIntersection($phrase1, $phrase2)
    {
        $terms1 = explode(" ", $phrase1);
        $terms2 = explode(" ", $phrase2);
        $total_terms = count($terms1) + count($terms2);
        if ($total_terms == 0) {
            return 0;
        }
        $num_intersect = count(array_intersect($terms1, $terms2));
        $avg_num_terms = $total_terms / 2;
        return $num_intersect / $avg_num_terms;
    }

    /**
     * Gets array of WordNet score for given input array of summaries
     * @param array $summaries an array of summaries which is generated
     *      during crawl time.
     * @param array $similar_words an array of synonyms which is generated by
     *      WordNet.
     * @return array of Wordnet score for each document
     */
    static function getScore($summaries, $similar_words)
    {
        $score = array();
        //if there are no similar words then
        if(empty($similar_words)) {
            return 0;
        } else {
            for($i = 0; $i < count($similar_words); $i++) {
                $query = $similar_words[$i];
                $terms = explode(' ', $query);
                $summaries = self::changeCaseOfArray($summaries);
                $idf = self::calculateIDF($summaries, $terms);
                $tf = self::calculateTFBM25($summaries, $terms);
                $num_summaries = count($summaries);
                $num_terms = count($terms);
                $bm25_result[$i] =
                    self::calculateBM25($idf, $tf, $num_terms,
                    $num_summaries);
            }
            if (count($bm25_result) == 1){
                for($i = 0; $i < $num_summaries; $i++) {
                    $temp = 0;
                    $temp = $bm25_result[0][$i];
                    $score[$i] = $temp;
                }
            } else {
                for($i = 0; $i < $num_summaries; $i++) {
                    $temp = 0;
                    $temp = $bm25_result[0][$i] * (2/3) +
                        $bm25_result[1][$i] * (1/3);
                    $score[$i] = $temp;
                }
            }
            return $score;
        }
    }
    /**
     * Lower cases an array of strings
     *
     * @param array $summary strings to put into lower case
     * @return array with strings converted to lower case
     */
    static function changeCaseOfArray($summaries)
    {
        return explode("-!-", mb_strtolower(implode("-!-", $summaries)));
    }
    /**
     * Computes the BM25 of an array of documents given that the idf and
     * tf scores for these documents have already been computed
     *
     * @param array $idf inverse doc frequency for given query array
     * @param array $tf term frequency for given query array
     * @param $num_terms number of terms that make up input query
     * @param $num_summaries count for input summaries
     * @returns array consisting of BM25 scores for each document
     */
    static function calculateBM25($idf, $tf, $num_terms, $num_summaries)
    {
        $scores = array();
        for($i = 0; $i < $num_terms; $i++) {
            for($j = 0; $j < $num_summaries; $j++) {
                $bm25_score[$i][$j] = $idf[$i] * $tf[$i][$j];
            }
        }
        for($i = 0; $i < $num_summaries; $i++) {
            $val = 0;
            for($j = 0; $j < $num_terms; $j++) {
                $val += $bm25_score[$j][$i];
            }
            $scores[$i] = $val;
        }
        return $scores;
    }
    /**
     *
     * @param array $summaries
     * @return array of term frequency for each query term
     */
    static function calculateTFBM25($summaries, $terms)
    {
        $k1 = 1.5;
        $b = 0.75;
        $tf_values = array();
        $tfbm25 = array();
        $doc_length = strlen(implode("", $summaries));
        $num_summaries = count($summaries);
        if($num_summaries!= 0) {
            $avg_length = $doc_length / $num_summaries;
        } else {
            $avg_length = 0;
        }
        $avg_length = max($avg_length, 1);
        $tf_values = self::calculateTermFreq($summaries, $terms);
        $num_terms =count($terms);
        for($i = 0; $i < $num_terms; $i++) {
            for($j = 0; $j < $num_summaries; $j++) {
                $frequency = $tf_values[$i][$j];
                $tfbm25[$i][$j] =
                    ($frequency * ($k1 + 1))/($frequency + $k1 *
                    ((1 - $b) + $b * ($doc_length/$avg_length)));
            }
        }
        return $tfbm25;
    }
    /**
     *
     */
    static function calculateTermFreq($summaries, $terms)
    {
        $tf_values = array();
        $num_terms = count($terms);
        $num_summaries = count($summaries);
        for($i = 0; $i < $num_terms; $i++) {
            for($j = 0; $j < $num_summaries; $j++) {
                $frequency = substr_count($summaries[$j], $terms[$i]);
                $tf_values[$i][$j] = $frequency;
            }
        }
        return $tf_values;
    }
    /**
     * To get the Inverse Doc frequency in given summary array
     * @param array $summaries an array of summaries
     * @return array of Inverse Doc frequency for each query term
     */
    static function calculateIDF($summaries, $terms)
    {
        $N = count($summaries);
        $Nt = array();
        $term_count = 0;
        $num_terms = count($terms);
        for($i = 0; $i < $num_terms; $i++) {
            $cnt_Nt = 0;
            $term_count++;
            foreach($summaries as $summary)
            {
                if(stripos($summary, $terms[$i]) !== false) {
                    $cnt_Nt++;
                }
            }
            $Nt[$i] = $cnt_Nt;
            $idf[$i] = ($Nt[$i] != 0) ? log10($N / $Nt[$i]) : 0;
        }
        return $idf;
    }
}
?>
