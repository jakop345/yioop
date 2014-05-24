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
class WordNetProcessor
{
    /**
     *  Extracts the similar words from WordNet output generated on command line
     *  It is stored in text file and used for parsing similar words and
     *  sentences.
     *  Sentences are then used in cosine ranking along with query string.
     *  According to score, synonyms are selected.
     *  @param String $query : Query entered by user
     *  @param String $pos_query : Same query processed after Part of speech
     *               tagging
     *  @return String array $result with all possible replacements for
     *             given query.
     */
    static function processWordNetOutput($query, $pos_query)
    {
        $replacements = array();
        $result = array();
        // Get the query from user and do PartofSpeech Tagging on it
        $terms = explode(" ", $query);
        $output = $pos_query;
        // Find out exact meaning of NN,JJ, in terms of Noun, Verb
        $output_words = preg_split("/\s+/", $output, -1, PREG_SPLIT_NO_EMPTY);
        $output_words_cnt = count($output_words);
        $word_type = null;
        $noun_arr = array("NN", "NNS", "NNP", "NNPS", "PRP", "PRP$",
                "WP", "WP$");
        $verb_arr = array("VB", "VBD", "VBG", "VBN", "VBP", "VBZ");
        $adj_arr = array("AJ", "JJ", "JJR", "JJS");
        $adv_arr = array("AV", "RB", "RBR", "RBS", "WRB");
        $similar_words = array();
        for ($i = 0; $i < $output_words_cnt; $i++) {
            $wordnet_op = "";
            $pos = strpos($output_words[$i], '~');
            $substring_output_word = substr($output_words[$i], $pos + 1);
            $current_word = substr($output_words[$i], 0, $pos);
            if (in_array(trim($substring_output_word), $noun_arr) == 1) {
                $word_type = "Noun";
            } else if (in_array(trim($substring_output_word), $verb_arr) == 1) {
                $word_type = "Verb";
            } else if (in_array(trim($substring_output_word), $adj_arr) == 1) {
                $word_type = "Adjective";
            } else if (in_array(trim($substring_output_word), $adv_arr) == 1) {
                $word_type = "Adverb";
            } else {
                $word_type = "NA";
            }
            if ($word_type != "NA") {
                $os_name = PHP_OS;
                $data = "";
                if($os_name == 'WINNT') {
                    exec(escapeshellarg(WORDNET_EXEC).'"/wn.exe" ' .
                        $terms[$i] .' -over',$data);
                } else {
                    exec(escapeshellarg(WORDNET_EXEC).'"/wn" ' .
                        $terms[$i] .' -over',$data);
                }
                $wordnet_op = implode(' ',$data);
                /*  Calls to get an 2D array with matched words and respective
                 *  cosine ranking score.
                 */
                $avg_score = self::splitWordNetOutput($word_type,
                                    $query, $wordnet_op);
                if ($avg_score == null) {
                    continue;
                }
                if ($avg_score) {
                    asort($avg_score[0]);
                    $extracted_words = $avg_score[1];
                    foreach ($avg_score[0] as $key => $value) {
                        $sorted_score_key = $key;
                        $sorted_score_value = $value;
                    }
                    $similar_words[$i] = $extracted_words[$sorted_score_key];
                    //To remove similar words as query word
                    $patterns =
                    array('/^'.$current_word.'\,/','/\,'.$current_word.'\,/');
                    $extracted_words[$sorted_score_key] =
                    preg_replace($patterns,'',
                        $extracted_words[$sorted_score_key]);
                    $replacements +=
                    array($current_word => $extracted_words[$sorted_score_key]);
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
        foreach ($replacements as $word => $rep) {
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
     * This function is used to get similar words from WordNet as per word type.
     * @param word_type is the type of word such as noun, verb, adjective,
     * adverb
     * @param query is query provided by user in search box
     * @return array of similar words along with their score.
     */
    static function splitWordNetOutput($word_type, $query, $wordnet_op)
    {
        if ($wordnet_op != "") {
            $paragraphs =
            preg_split("/\n\r/", $wordnet_op, -1, PREG_SPLIT_NO_EMPTY);
            $pos_para = array();
            $pos_type_para = array();
            if ($word_type == "Verb"){
                $pos_type_para = preg_grep("/\bThe\sverb\s/", $paragraphs);
            } else if ($word_type == "Noun") {
                $pos_type_para = preg_grep("/\bThe\snoun\s/", $paragraphs);
            } else if ($word_type == "Adjective") {
                $pos_type_para = preg_grep("/\bThe\sadj\s/", $paragraphs);
            } else if ($word_type == "Adverb") {
                $pos_type_para = preg_grep("/\bThe\sadv\s/", $paragraphs);
            }
            if ($pos_type_para) {
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
                        if($score[$j] == 0){
                            $score[$j] =
                            self::getIntersection($query,$match[1][$j]);
                        }
                        $cosine_score = $score[$j];
                    }
                    if ($sent_count) {
                        $avg_score[$i] = array_sum($score) / $sent_count;
                    } else {
                        $avg_score[$i] = 0;
                    }
                }
                return array(
                    $avg_score,
                    $wordmatch
                );
            } else {
                return null;
            }
        } else {
            return null;
        }
    }
    /**
     *  This function is used to get the score by Cosine-similarity
     *  ranking method between two sentences.
     *  @param array tokenA, tokenB as input sentences
     *  @return score by Cosine-similarity ranking method
     */
    static function getCosineRank(array $sentenceA, array $sentenceB)
    {
        $result = $cnt_sentA = $cnt_sentB = 0;
        $arrayA = $arrayB = array();
        $x = 0;
        $mergedWords = array_unique(array_merge($sentenceA, $sentenceB));
        foreach ($sentenceA as $word) {
            $arrayA[$word] = 0;
        }
        foreach ($sentenceB as $word) {
            $arrayB[$word] = 0;
        }
        foreach ($mergedWords as $word) {
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
     *  This function is used to get the score by intersection
     *  method between two sentences.
     *  @param s1, s2 are the input sentences
     *  @return score by intersection method
     */
    static function getIntersection($s1, $s2)
    {
        $sentence1 = explode(" ", $s1);
        $sentence2 = explode(" ", $s2);
        if ((count($sentence1) + count($sentence2)) == 0) {
            return 0;
        }
        $count1 = count(array_intersect($sentence1, $sentence2));
        $count2 = ((count($sentence1) + count($sentence2)) / 2);
        return $count1 / $count2;
    }
}
?>