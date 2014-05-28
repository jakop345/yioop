<?php
/**
 * SeekQuarry/Yioop --
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
 * @author Shailesh Padave based on Ian Barber's Code
 * @package seek_quarry
 * @subpackage locale
 * @license http://www.gnu.org/licenses/ GPLv3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource http://phpir.com/part-of-speech-tagging
 */
/**
 * This class is used to get part of speech tagging for given input.
 * @package seek_quarry
 * @subpackage locale
 */
class PartOfSpeechTagger
{
    /**
     *  Split input text into terms and output an array with one element
     *  per term, that element consisting of array with the term token 
     *  and the part of speech tag.
     *
     *  @param string $text string to tag and tokenize
     *  @return array of pairs of the form( "token" => token_for_term,
     *      "tag"=> part_of_speech_tag_for_term) for one each token in $text
     */
    static function tagTokenize($text)
    {
        static $lex_string = NULL;
        if(!$lex_string) {
            $lex_string = gzdecode(file_get_contents(
                LOCALE_DIR . "/en-US/resources/lexicon.txt.gz"));
        }
        preg_match_all("/[\w\d]+/", $text, $matches);
        $tokens = $matches[0];
        $nouns = array('NN', 'NNS');
        $verbs = array('VBD', 'VBP', 'VB');
        $result = array();
        $previous = array('token' => -1, 'tag' => -1);
        $previous_token = -1;
        sort($tokens);
        $dictionary = array();
        /*
            Notice we sorted the tokens, and notice how we use $cur_pos
            so only advance forward through $lex_string. So the
            run time of this is bound by at most one scan of $lex_string
         */
        $cur_pos = 0;
        foreach($tokens as $token) {
            $token = strtolower(rtrim($token, "."));
            $token_pos = stripos($lex_string, "\n".$token." ", $cur_pos);
            if($token_pos !== false) {
                $token_pos++;
                $cur_pos = stripos($lex_string, "\n", $token_pos);
                $line = trim(substr($lex_string, $token_pos,
                    $cur_pos - $token_pos));
                $tag_list = explode(' ', $line);
                $dictionary[strtolower(rtrim($token, "."))] =
                    array_slice($tag_list, 1);
                $cur_pos++;
            }
        }
        // now using our dictionary we tag
        foreach($matches[0] as $token) {
            $tag_list = array();
            // default to a common noun
            $current = array('token' => $token, 'tag' => 'NN');
            // remove trailing full stops
            $token = strtolower(rtrim($token, "."));
            if(isset($dictionary[$token])) {
                $tag_list = $dictionary[$token];
                $current['tag'] = $tag_list[0];
            }
            // Converts verbs after 'the' to nouns
            if($previous['tag'] == 'DT' && in_array($current['tag'], $verbs)) {
                $current['tag'] = 'NN';
            }
            // Convert noun to number if . appears
            if($current['tag'][0] == 'N' && strpos($token, '.') !== false) {
                $current['tag'] = 'CD';
            }
            $ends_with = substr($token, -2);
            switch($ends_with)
            {
                case 'ed':
                    // Convert noun to past particle if ends with 'ed'
                    if($current['tag'][0] == 'N') { $current['tag'] = 'VBN'; }
                break;
                case 'ly':
                    // Anything that ends 'ly' is an adverb
                    $current['tag'] = 'RB';
                break;
                case 'al':
                    // Common noun to adjective if it ends with al
                    if(in_array($current['tag'], $nouns)) {
                        $current['tag'] = 'JJ';
                    }
                break;
            }
            // Noun to verb if the word before is 'would'
            if($current['tag'] == 'NN' && $previous_token == 'would') {
                $current['tag'] = 'VB';
            }
            // Convert common noun to gerund
            if(in_array($current['tag'], $nouns) &&
                substr($token, -3) == 'ing') { $current['tag'] = 'VBG'; }
            /* If we get noun noun, and the second can be a verb,
             * convert to verb
             */
            if(in_array($previous['tag'], $nouns) &&
                in_array($current['tag'], $nouns) ) {
                if(in_array('VBN', $tag_list)) {
                    $current['tag'] = 'VBN';
                } else if(in_array('VBZ', $tag_list)) {
                    $current['tag'] = 'VBZ';
                }
            }
            $result[] = $current;
            $previous = $current;
            $previous_token = $token;
        }
        return $result;
    }
    /**
     * Takes an array of pairs (token, tag) that came from phrase
     * and builds a new phrase where terms look like token~tag.
     * 
     * @param array $tagged_tokens array of pairs as might come from tagTokenize
     * @return $tagged_phrase a phrase with terms in the format token~tag
     */
    static function taggedTokensToString($tagged_tokens)
    {
        $tagged_phrase = "";
        $simplified_parts_of_speech = array(
            "NN" => "NN", "NNS" => "NN", "NNP" => "NN", "NNPS" => "NN",
            "PRP" => "NN", 'PRP$' => "NN", "WP" => "NN",
            "VB" => "VB", "VBD" => "VB", "VBN" => "VB", "VBP" => "VB",
            "VBZ" => "VB",
            "JJ" => "AJ", "JJR" => "AJ", "JJS" => "AJ",
            "RB" => "AV", "RBR" => "AV", "RBS" => "AV", "WRB" => "AV"
        );
        foreach($tagged_tokens as $t) {
            $tag = trim($t['tag']);
            $tag = (isset($simplified_parts_of_speech[$tag])) ?
                $simplified_parts_of_speech[$tag] : $tag;
            $tagged_phrase .= $t['token'] . "~" . $tag .  " ";
        }
        return $tagged_phrase;
    }
    /**
     * Takes a phrase and tags each term in it with its part of speech.
     * So each term in the original phrase gets mapped to term~part_of_speech
     *
     * @param string $phrase text to add parts speech tags to
     * @return string $tagged_phrase phrase where each term has ~part_of_speech
     *      appended
     */
    static function tagPhrase($phrase)
    {
        preg_match_all("/[\w\d]+/", $phrase, $matches);
        $tokens = $matches[0];
        $tagged_tokens = self::tagTokenize($phrase);
        $tagged_phrase  = self::taggedTokensToString($tagged_tokens);
        return $tagged_phrase;
    }
}
?>
