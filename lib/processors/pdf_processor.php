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
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Load in the base class if necessary
 */

require_once BASE_DIR."/lib/processors/text_processor.php";

/**
 * Used to create crawl summary information 
 * for PDF files
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
class PdfProcessor extends TextProcessor
{

    /**
     *  Used to extract the title, description and links from
     *  a string consisting of PDF data.
     *
     *  @param $page - a string consisting of web-page contents
     *  @param $url - the url where the page contents came from,
     *     used to canonicalize relative links
     *
     *  @return a summary of the contents of the page
     *
     */
    public static function process($page, $url)
    {
        $text = "";
        if(is_string($page)) {
            $text =  self::getText($page);
        }

        if($text == "") {
            $text = $url;
        }

        $summary = parent::process($text, $url);

        return $summary;

    }

    /**
     *
     */
    static function getText($pdf_string) {
        $len = strlen($pdf_string);
        $cur_pos = 0;
        $out = "";
        
        $i = 0;
        while($cur_pos < $len) {
            list($cur_pos, $object_string) = 
                self::getNextObject($pdf_string, $cur_pos);
            $object_dictionary = self::getObjectDictionary($object_string);
            if(!self::objectDictionaryHas(
                $object_dictionary, array("Image", "Catalog"))) {
                $stream_data = 
                    rtrim(ltrim(self::getObjectStream($object_string)));
                if(self::objectDictionaryHas(
                    $object_dictionary, array("FlateDecode"))) {
                    $stream_data = @gzuncompress($stream_data);
                    if(strpos($stream_data, "PS-AdobeFont")){
                        $out .= $stream_data; 
                        break;
                    }
                    $text = self::parseText($stream_data);
                    $out .= $text;

                } else {
                    $text = self::parseText($stream_data);
                    if(strpos($stream_data, "PS-AdobeFont")){
                        $out .= $stream_data; 
                        break;
                    }
                    $out .= $text;

                }

            }
        }

        $font_pos = strpos($out, "PS-AdobeFont");
        if(!$font_pos) {
            $font_pos = strlen($out);
        }
        $out = substr($out, 0, $font_pos);
        
        return $out;
    }

    /**
     *
     */
    static function getNextObject($pdf_string, $cur_pos) 
    {
        return self::getBetweenTags($pdf_string, $cur_pos, "obj", "endobj"); 
    }

    /**
     *
     */
    static function objectDictionaryHas($object_Dictionary, $type_array) 
    {
        foreach ($type_array as $type) {
            if(strstr($object_Dictionary, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     *
     */
    static function getObjectDictionary($object_string) 
    {
        list( , $object_dictionary) =
            self::getBetweenTags($object_string, 0, '<<', '>>'); 
        return $object_dictionary;
    }

    /**
     *
     */
    static function getObjectStream($object_string) 
    {
        list( , $stream_data) = 
            self::getBetweenTags($object_string, 0, 'stream', 'endstream');
        return $stream_data;

    }

    /**
     *
     */
    static function parseText($data)
    {
        $cur_pos = 0;

        //replace ASCII codes in decimal with their value
        $data = preg_replace_callback('/\\\(\d{3})/',
            create_function( '$matches', 'return chr(intval($matches[1]));'), 
            $data);
        //replace ASCII codes in hex with their value
        $data = preg_replace_callback('/\<([0-9A-F]{2})\>/',
            create_function( '$matches', 'return chr(hexdec($matches[1]));'), 
            $data);
        $len = strlen($data);

        $out = "";
        $escape_flag =false;
        while($cur_pos < $len) {
            $cur_char = $data[$cur_pos];

            if($cur_char == '[' && !$escape_flag) {
                list($cur_pos, $text) = self::parseBrackets($data, $cur_pos);
                $cur_pos --;
                $out .= $text;
            }

            if($cur_char == '\\') {
                $escape_flag = true;
            } else {
                $escape_flag = false;
            }

            $cur_pos++;
        }

        return $out;
    }

    /**
     *
     */
    static function parseBrackets($data, $cur_pos)
    {
        $cur_pos++;
        $len = strlen($data);

        $out = "";
        $escape_flag =false;
        $cur_char="";

        while($cur_pos < $len && ($cur_char != "]")) {
            $cur_char = $data[$cur_pos];
            if($cur_char == '(') {
                list($cur_pos, $text) = self::parseParentheses($data, $cur_pos);
                $cur_pos --;
                $out .= $text;
            }

            $cur_pos++;
        }

        if(isset($data[$cur_pos]) && isset($data[$cur_pos + 1]) &&
            ord($data[$cur_pos]) == ord('T') && 
                ord($data[$cur_pos + 1]) == ord('J') ) {
            if(isset($data[$cur_pos + 3]) && 
                ord($data[$cur_pos + 3]) != ord('F')) {
                $out .= " ";
            } else {
                $out .= "\n";
            }
        }

        return array($cur_pos, $out);

    }

    /**
     *
     */
    static function parseParentheses($data, $cur_pos)
    {
        $cur_pos++;
        $len = strlen($data);

        $out = "";
        $escape_flag =false;
        $cur_char = "";

        while($cur_pos < $len && ($cur_char != ")" || $escape_flag)) {
            $cur_char = $data[$cur_pos];
            if($cur_char == '\\' && !$escape_flag) {
                $escape_flag = true;
            } else {
                if($escape_flag || $cur_char !=")"){
                    $ascii = ord($cur_char);
                    if((9 <= $ascii && $ascii <= 13) || 
                        (32 <= $ascii && $ascii <= 126)) {
                        $out .= $cur_char;
                    }
                }
                $escape_flag = false;

            }

            $cur_pos++;
        }

        $check_positioning = substr($data, $cur_pos, 4);
        if(preg_match("/\-\d{3}/", $check_positioning) >0 ) {
            $out .= " ";
        }

        return array($cur_pos, $out);

    }

}

?>
