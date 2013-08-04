<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2013  Chris Pollett chris@pollett.org
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
 * A library of string, log, hash, time, and conversion functions
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Adds delimiters to a regex that may or may not have them
 *
 * @param string $expression a regex
 * @return string rgex with delimiters if not there
 */
function addRegexDelimiters($expression)
{
    $first = $expression;
    $len = strlen($expression);
    $last = $expression[$len - 1];
    if(($first != $last && $len > 1) || $len == 1) {
        $expression = ($first != '/' ) ?
            "/".$expression."/"
            : "@".$expression."@";
    }
    return $expression;
}

/**
 * Copies from $source string beginning at position $start, $length many
 * bytes to destination string
 *
 * @param string $source  string to copy from
 * @param string &$destination string to copy to
 * @param int $start starting offset
 * @param int $length number of bytes to copy
 * @param string $timeout_msg for long copys message to print if taking more
 *      than 30 seconds
 */
function charCopy($source, &$destination, $start, $length, $timeout_msg = "")
{
    $endk = intval($length - 1);
    $end = intval($start + $endk);
    $start = intval($start);
    if($timeout_msg == "") {
        for($j = $end, $k = $endk; $j >= $start; $j--, $k--) {
            $destination[$j] = $source[$k];
        }
    } else {
        $time_out_check_frequency = 20000;
        for($j = $end, $k = $endk, $t = 0; $j >= $start; $j--, $k--, $t++) {
            $destination[$j] = $source[$k];
            if($t > $time_out_check_frequency) {
                crawlTimeoutLog($timeout_msg);
                $t = 0;
            }
        }
    }
}

/**
 *  Encodes an integer using variable byte coding.
 *
 *  @param int $pos_int integer to encode
 *  @return string a string of 1-5 chars depending on how bit $pos_int was
 */
function vByteEncode($pos_int)
{
    $result = chr($pos_int & 127);
    $pos_int >>= 7;
    while($pos_int > 0){
        $result .= chr(128 | ($pos_int & 127));
        $pos_int >>= 7;
    }
    return $result;
}

/**
 *  Decodes from a string using variable byte coding an integer.
 *
 *  @param string &$str string to use for decoding
 *  @param int $offset byte offset into string when var int stored
 *  @return int the decoded integer
 */
function vByteDecode(&$str, &$offset)
{
    $pos_int = ord($str[$offset] & 127) ;
    $shift = 7;
    while (ord($str[$offset++]) & 128 > 0) {
        $pos_int += (ord($str[$offset] & 127) << $shift);
        $shift += 7;
    }

    return $pos_int;
}

/**
 * Makes an packed integer string from a docindex and the number of
 * occurrences of a word in the document with that docindex.
 *
 * @param int $doc_index index (i.e., a count of which document it
 *      is rather than a byte offset) of a document in the document string
 * @param array integer positions word occurred in that doc
 * @param bool $delta if true then stores the position_list as a sequence of
 *      differences (a delta list)
 * @return string a modified9 (our compression scheme) packed
 *      string containing this info.
 */
function packPosting($doc_index, $position_list, $delta = true)
{
    if($delta) {
        $delta_list = deltaList($position_list);
    } else {
        $delta_list = $position_list;
    }
    if(isset($delta_list[0])){
        $delta_list[0]++;
    }

    if( $doc_index >= (2 << 14) && isset($delta_list[0])
        && $delta_list[0] < (2 << 9)  && $doc_index < (2 << 17)) {
        $delta_list[0] += (((2 << 17) + $doc_index) << 9);
    } else {
        // we add 1 to doc_index to make sure not 0 (modified9 needs > 0)
        array_unshift($delta_list, ($doc_index + 1));
    }
    $encoded_list = encodeModified9($delta_list);
    return $encoded_list;
}

/**
 * Given a packed integer string, uses the top three bytes to calculate
 * a doc_index of a document in the shard, and uses the low order byte
 * to computer a number of occurences of a word in that document.
 *
 * @param string $posting a string containing
 *      a doc index position list pair coded encoded using modified9
 * @param int &offset a offset into the string where the modified9 posting
 *      is encoded
 * @param bool $dedelta if true then assumes the list is a sequence of
 *      differences (a delta list) and undoes the difference to get
 *      the original sequence
 * @return array consisting of integer doc_index and a subarray consisting
 *      of integer positions of word in doc.
 */
function unpackPosting($posting, &$offset, $dedelta = true)
{
    $delta_list = decodeModified9($posting, $offset);
    $doc_index = array_shift($delta_list);

    if(($doc_index & (2 << 26)) > 0) {
        $delta0 = ($doc_index & ((2 << 9) - 1));
        array_unshift($delta_list, $delta0);
        $doc_index -= $delta0;
        $doc_index -= (2 << 26);
        $doc_index >>= 9;
    } else {
        $doc_index--;
    }
    if(isset($delta_list[0])){
        $delta_list[0]--;
    }

    if($dedelta) {
        deDeltaList($delta_list);
    }

    return array($doc_index, $delta_list);
}

/**
 * This method is used while appending one index shard to another. 
 * Given a string of postings adds $add_offset add to each offset to the
 * document map in each posting.
 *
 * @param string &$postings a string of index shard postings
 * @param int $add_offset an fixed amount to add to each postings doc map offset
 *
 * @return string $new_postings where each doc offset has had $add_offset added
 *      to it
 */
function addDocIndexPostings(&$postings, $add_offset)
{
    $offset = 0;
    $new_postings = "";
    $postings_len = strlen($postings);
    while($offset < $postings_len) {
        $post_string = nextPostString($postings, $offset);
        $posting_list = call_user_func_array( "array_merge",
            array_map("unpackListModified9", unpack("N*", $post_string)));
        $doc_index = array_shift($posting_list);
        if(($doc_index & (2 << 26)) > 0) {
            $post0 = ($doc_index & ((2 << 9) - 1));
            array_unshift($posting_list, $post0);
            $doc_index -= $post0;
            $doc_index -= (2 << 26);
            $doc_index >>= 9;
        } else {
            $doc_index--;
        }
        if($posting_list === false) { continue; }

        $doc_index += $add_offset;

        if($doc_index >= (2 << 14) && isset($posting_list[0])
            && $posting_list[0] < (2 << 9)  && $doc_index < (2 << 17)) {
            $posting_list[0] += (((2 << 17) + $doc_index) << 9);
        } else {
            // we add 1 to doc_index to make sure not 0 (modified9 needs > 0)
            array_unshift($posting_list, ($doc_index + 1));
        }
        $new_postings .= encodeModified9($posting_list);
    }
    return $new_postings;
}


/**
 * Computes the difference of a list of integers.
 * i.e., (a1, a2, a3, a4) becomes (a1, a2-a1, a3-a2, a4-a3)
 *
 * @param array $list a nondecreasing list of integers
 * @return array the corresponding list of differences of adjacent
 *      integers
 */
function deltaList($list)
{
    $last = 0;
    $delta_list = array();
    foreach($list as $elt) {
        $delta_list[] = $elt - $last;
        $last = $elt;
    }
    return $delta_list;
}

/**
 * Given an array of differences of integers reconstructs the
 * original list. This computes the inverse of the deltaList function
 *
 * @see deltaList
 * @param array $delta_list a list of nonegative integers
 * @return array a nondecreasing list of integers
 */
function deDeltaList(&$delta_list)
{
    $last = 0;
    $num = count($delta_list);
    for($i = 1; $i < $num; $i++) {
        $delta_list[$i] += $delta_list[$i - 1];
    }
}

/**
 * Encodes a sequence of integers x, such that 1 <= x <= 2<<28-1
 * as a string.
 *
 * The encoded string is a sequence of 4 byte words (packed int's).
 * The high order 2 bits of a given word indicate whether or not
 * to look at the next word. The codes are as follows:
 * 11 start of encoded string, 10 continue four more bytes, 01 end of
 * encoded, and 00 indicates whole sequence encoded in one word.
 *
 * After the high order 2 bits, the next most significant bits indicate
 * the format of the current word. There are nine possibilities:
 * 00 - 1 28 bit number, 01 - 2 14 bit numbers, 10 - 3 9 bit numbers,
 * 1100 - 4 6 bit numbers, 1101 - 5 5 bit numbers, 1110 6 4 bit numbers,
 * 11110 - 7 3 bit numbers, 111110 - 12 2 bit numbers, 111111 - 24 1 bit
 * numbers.
 *
 * @param array $list a list of positive integers satsfying above
 * @return string encoded string
 */
function encodeModified9($list)
{
    global $MOD9_PACK_POSSIBILITIES;
    $cnt = 0;
    $cur_size = 1;
    $cur_len = 1;
    $pack_list = array();
    $list_string = "";
    $continue_bits = 3;
    foreach($list as $elt) {
        $old_len = $cur_len;
        while($elt > $cur_size)
        {
            $cur_len++;
            $cur_size = (1 << $cur_len) - 1;

        }

        if($cnt < $MOD9_PACK_POSSIBILITIES[$cur_len]) {
            $pack_list[] = $elt;
            $cnt++;
        } else {
            $list_string .= packListModified9($continue_bits,
                $MOD9_PACK_POSSIBILITIES[$old_len], $pack_list);
            $continue_bits = 2;
            $pack_list = array($elt);
            $cur_size = 1;
            $cur_len = 1;
            $cnt = 1;
            while($elt > $cur_size)
            {
                $cur_size = (1 << $cur_len) - 1;
                $cur_len++;
            }
        }
    }
    $continue_bits = ($continue_bits == 3) ? 0 : 1;
    $list_string .= packListModified9($continue_bits,
        $MOD9_PACK_POSSIBILITIES[$cur_len], $pack_list);

    return $list_string;
}

/**
 * Packs the contents of a single word of a sequence being encoded
 * using Modified9.
 *
 * @param int $continue_bits the high order 2 bits of the word
 * @param int $cnt the number of element that will be packed in this word
 * @param array $list a list of positive integers to pack into word
 * @return string encoded 4 byte string
 * @see encodeModified9
 */
function packListModified9($continue_bits, $cnt, $pack_list)
{
    global $MOD9_NUM_ELTS_CODES, $MOD9_NUM_BITS_CODES;

    $out_int = 0;
    $code = $MOD9_NUM_ELTS_CODES[$cnt];
    $num_bits = $MOD9_NUM_BITS_CODES[$code];
    foreach($pack_list as $elt) {
        $out_int <<= $num_bits;
        $out_int += $elt;
    }
    $out_string = packInt($out_int);

    $out_string[0] = chr(($continue_bits << 6) + $code + ord($out_string[0]));
    return $out_string;
}


/**
 * Returns the next complete posting string from $input_string being at offset.
 * Does not do any decoding.
 *
 * @param string &$input_string a string of postings
 * @param int &offset an offset to this string which will be updated after call
 * @return string undecoded posting
 */
function nextPostString(&$$input_string, &$offset)
{
    if(!isset($input_string[$offset+3])) return array();
    $flag_mask = 192;
    $continue_threshold = 128;
    $len = strlen($input_string);
    $end = $offset;
    $error = false;
    $flag_bits = (ord($input_string[$end]) & $flag_mask) ;
    if($flag_bits && $flag_bits != $flag_mask) {
        crawlLog("!! Decode Error Flags: $flag_bits $flag_mask");
        crawlLog("!! Dropping posting at $offset cycle to next posting.");
        $error = true;
    }
    $end += 4;
    while ($end < $len &&
            $flag_bits >= $continue_threshold) {
        $flag_bits = (ord($input_string[$end]) & $flag_mask);
        $end += 4;
    }
    $post_string = substr($input_string, $offset, $end - $offset);
    $offset = $end;
    if($error) {
        return array(0, false);
    }
    return $post_string;
}

/**
 * Decoded a sequence of positive integers from a string that has been
 * encoded using Modified 9
 *
 * @param string $int_string string to decode from
 * @param int &$offset where to string in the string, after decode
 *      points to where one was after decoding.
 * @return array sequence of positive integers that were decoded
 * @see encodeModified9
 */
function decodeModified9($input_string, &$offset)
{
    $post_string = nextPostString($input_string, $offset);
    return call_user_func_array( "array_merge",
        array_map("unpackListModified9", unpack("N*", $post_string)));
}

if(!extension_loaded("yioop") ) {

/**
 * Decoded a single word with high two bits off according to modified 9
 *
 * @param string $int_string 4 byte string to decode
 * @return array sequence of integers that results from the decoding.
 */
function unpackListModified9($encoded_list)
{
    switch($encoded_list & 0x30000000)
    {
        case 0:
            return  array($encoded_list & 0x0FFFFFFF); //lop off high nibble
        break;
        case 0x10000000:
            $encoded_list &= 0xEFFFFFFF;
            $num_bits = 14;
            $num_elts = 2;
            $mask = 0x3FFF;
            $shift = 14;
        break;
        case 0x20000000:
            $encoded_list &= 0xDFFFFFFF;
            $num_bits = 9;
            $num_elts = 3;
            $mask = 0x1FF;
            $shift = 18;
        break;
        default:
            global $MOD9_NUM_BITS_CODES, $MOD9_NUM_ELTS_DECODES;
            $int_string = packInt($encoded_list);
            $first_char = ord($int_string[0]);
            foreach($MOD9_NUM_BITS_CODES as $code => $num_bits) {
                if(($first_char & $code) == $code) break;
            }
            $num_elts = $MOD9_NUM_ELTS_DECODES[$code];
            $mask = (1 << $num_bits) - 1;
            $int_string[0] = chr($first_char - $code);
            $encoded_list = unpackInt($int_string);
    }

    $decoded_list = array();
    for($i = 0; $i < $num_elts; $i++) {
        if(($pre_elt = $encoded_list & $mask) == 0) break;
        array_unshift($decoded_list, $pre_elt);
        $encoded_list >>= $num_bits;
    }
    return $decoded_list;
}

/**
 *  Given an int encoding encoding a doc_index followed by a position
 *  list using Modified 9, extracts just the doc_index.
 *
 *  @param int $encoded_list in the just described format
 *  @return int a doc index into an index shard document map.
 */
function docIndexModified9($encoded_list)
{
    $t26 = 2 << 26;
    switch($encoded_list & 0x30000000)
    {
        case 0:
            $encoded_list &= 0x0FFFFFFF; //lop off high nibble
            return (($encoded_list & $t26) > 0) ?
                ($encoded_list - $t26 + ($encoded_list & 0x1FF)) >> 9 :
                $encoded_list - 1;
        break;
        case 0x10000000:
            $encoded_list &= 0xEFFFFFFF;
            $num_bits = 14;
            $mask = 0x3FFF;
            $shift = 14;
        break;
        case 0x20000000:
            $encoded_list &= 0xDFFFFFFF;
            $num_bits = 9;
            $mask = 0x1FF;
            $shift = 18;
        break;
        default:
            global $MOD9_NUM_BITS_CODES, $MOD9_NUM_ELTS_DECODES;
            $first_char = $encoded_list >> 24;
            foreach($MOD9_NUM_BITS_CODES as $code => $num_bits) {
                if(($first_char & $code) == $code) break;
            }
            $num_elts = $MOD9_NUM_ELTS_DECODES[$code];
            $mask = (1 << $num_bits) - 1;
            $shift = $num_bits * ($num_elts - 1);
            $int_string = packInt($encoded_list);
            $int_string[0] = chr($first_char - $code);
            $encoded_list = (int)hexdec(bin2hex($int_string));
    }

    do {
        if($doc_index = (($encoded_list >> $shift) & $mask)) {
            $doc_index -= (($doc_index & $t26) > 0) ?
                $t26 + ($doc_index & 0x1FF) : 1;
            return $doc_index;
        }
        $shift -= $num_bits;
    } while($shift >= 0);

    return $doc_index; //shouldn't get here

}

/**
 * Unpacks an int from a 4 char string
 *
 * @param string $str where to extract int from
 * @return int extracted integer
 */
function unpackInt($str)
{
    if(is_string($str)) {
        return (int)hexdec(bin2hex($str));
    }
    return false;
}

}

/**
 * Packs an int into a 4 char string
 *
 * @param int $my_int the integer to pack
 * @return string the packed string
 */
function packInt($my_int)
{
    return pack("N", $my_int);
}


/**
 * Unpacks a float from a 4 char string
 *
 * @param string $str where to extract int from
 * @return float extracted float
 */
function unpackFloat($str)
{
    if(!is_string($str)) return false;
    $tmp = unpack("f", $str);
    return $tmp[1];
}

/**
 * Packs an float into a 4 char string
 *
 * @param float $my_floatt the float to pack
 * @return string the packed string
 */
function packFloat($my_float)
{
    return pack("f", $my_float);
}

/**
 * Converts a string to string where each char has been replaced by its
 * hexadecimal equivalent
 *
 * @param string $str what we want rewritten in hex
 * @return string the hexified string
 */
function toHexString($str)
{
    $out = "";
    for($i = 0; $i < strlen($str); $i++) {
        $out .= sprintf("%02X",ord($str[$i]))." ";
    }
    return $out;
}

/**
 * Converts a string to string where each char has been replaced by its
 * binary equivalent
 *
 * @param string $str what we want rewritten in hex
 * @return string the binary string
 */
function toBinString($str)
{
    $out = "";
    for($i = 0; $i < strlen($str); $i++) {
        $out .= substr(decbin(256+ord($str[$i])), 1)." ";
    }
    return $out;
}

/**
 * Converts a string of the form some int followed by K, M, or G.
 * into its integer equivalent. For example 4K would become 4000,
 * 16M would become 16000000, and 1G would become 1000000000
 *
 * @param string $metric_num metric number to convert
 * @return int number the metric string corresponded to
 */
function metricToInt($metric_num)
{
    $num = intval($metric_num);
    if(is_string($metric_num)) {
        $last_char = $metric_num[strlen($metric_num) - 1];
        switch(strtoupper($last_char))
        {
            case "K":
                $num *= 1000;
            break;
            case "M":
                $num *= 1000000;
            break;
            case "G":
                $num *= 1000000000;
            break;
        }
    }
    return $num;
}

/**
 *  Logs a message to a logfile or the screen
 *
 *  @param string $msg message to log
 *  @param string $lname name of log file in the LOG_DIR directory, rotated logs
 *      will also use this as their basename followed by a number followed by
 *      bz2 (since they are bzipped).
 */

function crawlLog($msg, $lname = NULL, $check_process_handler = false)
{
    static $logname;
    static $last_check_time = 0;
    static $check_handler = false;

    if(defined("NO_LOGGING") && NO_LOGGING) {
        return;
    }

    if($lname != NULL)
    {
        $logname = $lname;
    } else if(!isset($logname)) {
        $logname = "message";
    }
    if($check_process_handler != NULL) {
        $check_handler = $check_process_handler;
    }
    $time_string = date("r", time());
    $out_msg = "[$time_string] $msg";
    if(defined("LOG_TO_FILES") && LOG_TO_FILES) {
        $logfile = LOG_DIR."/$logname.log";

        clearstatcache(); //hopefully, this doesn't slow things too much

        if(file_exists($logfile) && filesize($logfile) > MAX_LOG_FILE_SIZE) {
            if(file_exists("$logfile.".NUMBER_OF_LOG_FILES.".bz2")) {
                unlink("$logfile.".NUMBER_OF_LOG_FILES.".bz2");
            }
            for($i = NUMBER_OF_LOG_FILES; $i > 0; $i--) {
                if(file_exists("$logfile.".($i-1).".bz2")) {
                    rename("$logfile.".($i-1).".bz2", "$logfile.$i.bz2");
                }
            }
            file_put_contents("$logfile.0.bz2",
                bzcompress(file_get_contents($logfile)));
            unlink($logfile);
        }
        //don't use error_log options in this case to happify hiphop4php
        file_put_contents($logfile, $out_msg."\n", FILE_APPEND);
        if($check_handler && !$check_process_handler && 
            changeInMicrotime($last_check_time) > 5) {
            CrawlDaemon::processHandler(true);
            $last_check_time = microtime();
        }
    } else if (php_sapi_name() != 'cli') {
        error_log($out_msg."\n");
    } else {
        echo $out_msg."\n";
    }
}

/**
 * Writes a log message $msg if more than LOG_TIMEOUT time has passed since 
 * the last time crawlTimeoutLog was callled. Useful in loops to write a message
 * as progress is made through the loop (but not on every iteration, but
 * say every 30 seconds).
 *
 * @param mixed $msg usually a string with what to be printed out after the 
 *      timeout period. If $msg === true then clears the timout cache
 */
function crawlTimeoutLog($msg)
{
    static $cache_time = 0;
    if(changeInMicrotime($cache_time) < LOG_TIMEOUT) {
        return;
    }
    if(func_num_args() > 1) {
        $out_msg = call_user_func_array('sprintf', func_get_args());
    } else {
        $out_msg = & $msg;
    }
    crawlLog($out_msg." Current memory usage:".memory_get_usage());
    $cache_time = microtime();
}

/**
 *  Computes an 8 byte hash of a string for use in storing documents.
 *
 *  An eight byte hash was chosen so that the odds of collision even for
 *  a few billion documents via the birthday problem are still reasonable.
 *  If the raw flag is set to false then an 11 byte base64 encoding of the
 *  8 byte hash is returned. The hash is calculated as the xor of the
 *  two halves of the 16 byte md5 of the string. (8 bytes takes less storage
 *  which is useful for keeping more doc info in memory)
 *
 *  @param string $string the string to hash
 *  @param bool $raw whether to leave raw or base 64 encode
 *  @return string the hash of $string
 */
function crawlHash($string, $raw = false)
{
    $pre_hash = md5($string, true);

    $left = substr($pre_hash,0, 8) ;
    $right = substr($pre_hash,8, 8) ;

    $combine = $right ^ $left;

    if(!$raw) {
        $hash = base64Hash($combine);
            // common variant of base64 safe for urls and paths
    } else {
        $hash = $combine;
    }

    return $hash;
}

/**
 *  Used to create a 20 byte hash of a string (typically a word)
 *  together with a string of meta data about the page that the word
 *  appeared on (media:, safe:, class:) information
 *
 *  @param string $string word to hash
 *  @param bool $raw whether to base64Hash the result
 *  @param $meta_string the up to 11 byte string of meta information
 *  @return string first 8 bytes of md5 of $string concatenated with \x00
 *      to indicate the hash is of a word not a phrase concatenated  with the 
 *      padded to 11 byte $meta_string.
 */
function crawlHashWord($string, $raw = false, $meta_string = "")
{
    $pre_hash = substr(md5($string, true), 0, 8) .
        "\x00";
    $meta_string = substr($meta_string, 0, 11);
    $pre_hash .= $meta_string;
    $pre_hash = str_pad($pre_hash, 20, "\x00");
    /* low order bytes all 0 -- distinguishes it from a crawlHashPath */
    if(!$raw) {
        $hash = base64Hash($pre_hash);
            // common variant of base64 safe for urls and paths
    } else {
        $hash = $pre_hash;
    }
    return $hash;
}

/**
 *  Used to compute all hashes for a phrase based on each possible cond_max
 *  point. Here cond_max is the location of a substring of a phase which is
 *  maximal.
 *
 *  @param string $string what to find hashes for
 *  @param $raw whether to modified base64 the result
 *  @return array of hashes with appropriates shifts if needed
 */
function allCrawlHashPaths($string, $metas = array(),
    $encode_metas = array(), $raw = false)
{
    $mask = "";
    if($encode_metas != array()) {
        $mask_num = min(11, count($encode_metas));
        $found_materialized_metas = findMaterialMetas($metas, $encode_metas);

        foreach($encode_metas as $meta) {
            $mask .= (isset($found_materialized_metas[$meta])) ? "\xFF": "\x00";
        }
    }
    $pos = -1;
    $hashes = array();
    $zero = "*";
    $shift = 0;
    $num_spaces = substr_count($string, " ");
    $num = MAX_QUERY_TERMS - $num_spaces;
    $j = 0;
    do {
        $old_pos = $pos;
        $path_string = $string;
        for($i = 0; $i < $num; $i++) {
            $hash = crawlHashPath($path_string, $pos + 1, $metas,
                $encode_metas, $raw);
            if($i > 0 && $j > 0) {
                $path_len = $num_spaces - $j + 1 + $i;
                if($path_len < 4) {
                    $shift = 32 * $i;
                    if($path_len == 3 && $i == 3) {
                        $shift -= 3;
                    }
                } else if ($path_len < 6) {
                    if($i < 4) {
                        $shift = 16 * $i;
                    } else {
                        $shift = 64 + 29 * ($i - 4);
                    }
                } else if ($path_len < 8) {
                    if($i < 4) {
                        $shift = 8 * $i;
                        if($path_len == 6) {
                            $shift += 8;
                        }
                    } else if ($i < 6) {
                        $shift = 32 + 16 * ($i - 4);
                    } else {
                        $shift = 64 + 29 * ($i - 6);
                    }
                } else if ($path_len < 10) {
                    if($i < 4) {
                        $shift = 4 * $i;
                        if($path_len == 8) {
                            $shift += 4;
                        }
                    } else if ($i < 6) {
                        $shift = 16 + 8 * ($i - 4);
                        if($path_len == 8) {
                            $shift += 12;
                        }
                    } else if ($i < 8) {
                        $shift = 32 + 16 * ($i - 6);
                    } else {
                        $shift = 64 + 29 * ($i - 8);
                    }
                } else if ($path_len < 12) {
                    if($i < 4) {
                        $shift = 2 * $i;
                        if($path_len == 10) {
                            $shift += 2;
                        }
                    } else if ($i < 6) {
                        $shift = 8 + 4 * ($i - 4);
                    } else if ($i < 8) {
                        $shift = 16 + 8 * ($i - 6);
                    } else if ($i < 10) {
                        $shift = 32 + 16 * ($i - 8);
                    } else {
                        $shift = 64 + 29 * ($i - 10);
                    }
                } else if ($path_len < 14) {
                    if($i < 4) {
                        $shift = $i;
                        if($path_len == 12) {
                            $shift += 1;
                        }
                    } else if ($i < 6) {
                        $shift = 4 + 2 * ($i - 4);
                    } else if ($i < 8) {
                        $shift = 8 + 4 * ($i - 6);
                    } else if ($i < 10) {
                        $shift = 16 + 8 * ($i - 8);
                    } else if ($i < 12 ){
                        $shift = 32 + 16 * ($i - 10);
                    } else {
                        $shift = 64 + 29 * ($i - 12);
                    }
                }
                $hashes[] = array($hash, $shift, $mask);
            } else if($mask != "") {
                $hashes[] = array($hash, $shift, $mask);
            } else {
                $hashes[] = $hash;
            }
            if($j == 0) {break; }
            $path_string .= " ".$zero;
        }
        $pos = mb_strpos($string, " ", $pos + 1);
        $encode_metas = array();
        $j++;
    } while($pos > 0 && $old_pos != $pos);

    if(count($hashes) == 1) {
        return $hashes[0];
    }
    return $hashes;
}

/**
 * Give an array of values for meta words (for example, media:video, lang:en) 
 * and an array of names of meta words to be encoded into word_id's 
 * (for example, media:, safe:, class:) return an associative array of pairs
 * (meta word name => array(value of that name)) which should be encoded
 * into word id's
 *
 * @param array $meta array of meta word values 
 * @param array $encode_meta a list of meta word names to encode in word_ids
 * @return array $found_materialized_metas associative array of name =>
 *      values for that name
 */
function findMaterialMetas($metas, $encode_metas)
{
    $found_materialized_metas = array();
    foreach($metas as $meta_id) {
        if($encode_metas != array()) {
            $match_kinds = explode(":", $meta_id);
            $next_char = (isset($match_kinds[1][0])) ? $match_kinds[1][0] :
                ord('a');
            $is_class = ($match_kinds[0] == 'class');
            if(count($match_kinds) > 1 &&
                in_array($match_kinds[0].":", $encode_metas) &&
                !in_array($match_kinds[1], array("all", "false")) &&
                !isset($match_kinds[2])) {
                    $found_materialized_metas[$match_kinds[0].":"][] =
                        $meta_id;
            }
        }
    }
    return $found_materialized_metas;
}

/**
 * Give an array of values for meta words (for example, media:video, lang:en) 
 * and an array of names of meta words to be encoded into word_id's 
 * (for example, media:, safe:, class:) returns a string mask for the
 * byte positions in a word_id after the 9th byte. The format of a word id
 * in the case of a single word is described in the documentation for
 * @see crawlHashPath
 *
 * @param array $meta a list of meta word values extracted from a query
 *  string or document.
 * @param array $encode_meta a list of meta word names that should be encoded
 *  in word id's For example, (media:, safe:, class:)
 * @return string a 9 byte string where encoded meta word values have been
 *      stored
 */
function encodeMaterialMetas($metas, $encode_metas)
{
    $found_materialized_metas = findMaterialMetas($metas, $encode_metas);
    $meta_string = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
    if(!is_array($encode_metas)) return "";
    foreach($found_materialized_metas as $name => $values) {
        foreach($values as $value) {
            if($name == 'class:' && isset($value[6])) {
                $pre_meta_pos = ord($value[6]);
                $meta_pos = (($pre_meta_pos) % 9) + 2;
            } else {
                $meta_pos = (ord($name[0]) > ord('m')) ? 1 : 0;
            }
            $meta_string[$meta_pos] = substr(crawlHash($value, true), 0, 1);
        }
    }
    return $meta_string;
}

/**
 *  Given a string makes an 20 byte hash path - where first 8 bytes is
 *  a hash of the string before path start, last 12 bytes is the path
 *  given by splitting on space and separately hashing each element
 *  according to the number of elements and the 3bit selector below:
 *
 *  general format: (64 bit lead word hash, 3bit selector, hashes of rest of 
 *  words)  according to:
 *  Selector Bits for each remaining word
 *   001     29 32 32
 *   010     29 16 16 16 16
 *   011     29 16 16 8 8 8 8
 *   100     29 16 16 8 8 4 4 4 4
 *   101     29 16 16 8 8 4 4 2 2 2 2
 *   110     29 16 16 8 8 4 4 2 2 1 1 1 1
 *
 *  If $path_start is 0 behaves like crawlHashWord(). The above encoding is
 *  typically used to make word_ids for whole phrases, to make word id's
 *  for single words, the format is
 *  (64 bits for word, 1 byte null, remaining 11 bytes encode an materialized 
 *  meta words present in document or query string). Of this 11 bytes,
 *  the first is used for the meta word media:, so if the document is of type
 *  media:image, then a single byte hash of media:image gives the value of this
 *  byte. The second byte encodes the meta word safe: in a similar fashion. 
 *  The remaining 9 bytes encode different values of the class: meta word.
 *  To encode class:some_value., first class:some_value[0] is hashed to a value
 *  j betwen 0 and 8. Then class:some_value is hash to a single byte b. Then
 *  the jth value of the remaining bytes is set to b. Non affected bytes are 
 *  null.
 *
 *  @param string $string what to hash
 *  @param int $path_start what to use as the split between 5 byte front
 *      hash and the rest
 *  @param array $metas meta word values from a document or query string
 *  @param array $encode_metas a list of names of meta word values which should
 *      encoded into word ids. i.e., (media:, safe:, class:) or none.
 *  @param bool $raw whether to modified base64 the result
 *  @return string 8 bytes that results from this hash process
 */
function crawlHashPath($string, $path_start = 0, $metas = array(),
    $encode_metas = array(), $raw = false)
{
    if($path_start > 0 ) {
        $string_parts = explode(" ", substr($string, $path_start));
        $num_parts = count($string_parts);
    }
    if($path_start == 0 || $num_parts == 0) {
        $meta_string = encodeMaterialMetas($metas, $encode_metas);
        $hash = crawlHashWord($string, true, $meta_string);
        if(!$raw) {
            $hash = base64Hash($hash);
        }
        return $hash;
    }
    $front = substr($string, 0, $path_start);
    //Top 8 bytes what a normal crawlHashWord would be
    $front_hash = substr(crawlHashWord($front, true), 0, 8);
    //Low 8 bytes encode paths
    $path_ints = array();
    $modes = array(3, 3, 3, 3, 5, 5, 7, 7, 9, 9, 11, 11, 13, 13);
    $mode_nums = array(1, 1, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6);

    foreach($string_parts as $part) {
        if($part == "*") {
            $path_ints[] = 0;
        } else {
            $path_ints[] = unpackInt(substr(md5($part, true), 0, 4));
        }
    }
    $num_parts = count($path_ints);
    if($num_parts > 13) {$num_parts = 13; }
    $mode = $modes[$num_parts];
    $mode_num = $mode_nums[$num_parts];
    switch($mode)
    {
        case 3:
            for($i = 0; $i < 3; $i++) {
                $path_ints[$i] = isset($path_ints[$i]) ? $path_ints[$i] : 0;
            }
            $second_int = $path_ints[1];
            $third_int = $path_ints[2];
        break;
        case 5:
            $path_ints[4] = isset($path_ints[4]) ? $path_ints[4] : 0;
            $shift = 16;
            $mask = (1 << $shift) - 1;
            $second_int = (($path_ints[1] & $mask) << $shift)
                + ($path_ints[2] & $mask);
            $third_int = (($path_ints[3] & $mask) << $shift)
                + ($path_ints[4] & $mask);
        break;
        case 7:
            $path_ints[6] = isset($path_ints[6]) ? $path_ints[6] : 0;
            $shift = 16;
            $mask = (1 << $shift) - 1;
            $second_int = (($path_ints[1] & $mask) << $shift)
                + ($path_ints[2] & $mask);
            $shift = 8;
            $mask = 127;
            $third_int = (((((($path_ints[3] & $mask) << $shift)
                + ($path_ints[4] & $mask)) << $shift)
                + ($path_ints[5] & $mask)) << $shift)
                + ($path_ints[6] & $mask);

        break;
        case 9:
            $path_ints[8] = isset($path_ints[8]) ? $path_ints[8] : 0;
            $shift = 16;
            $mask = (1 << $shift) - 1;
            $second_int = (($path_ints[1] & $mask) << $shift)
                + ($path_ints[2] & $mask);
            $shift = 8;
            $mask = 127;
            $third_int = (((($path_ints[3] & $mask) << $shift)
                + ($path_ints[4] & $mask)) << $shift);
            $shift = 4;
            $mask = 15;
            $third_int = (((((($third_int
                + ($path_ints[5] & $mask)) << $shift)
                + ($path_ints[6] & $mask)) << $shift)
                + ($path_ints[7] & $mask)) << $shift)
                + ($path_ints[8] & $mask);
        break;
        case 11:
            $path_ints[10] = isset($path_ints[10]) ? $path_ints[10] : 0;
            $shift = 16;
            $mask = (1 << $shift) - 1;
            $second_int = (($path_ints[1] & $mask) << $shift)
                + ($path_ints[2] & $mask);
            $shift = 8;
            $mask = 127;
            $third_int = (((($path_ints[3] & $mask) << $shift)
                + ($path_ints[4] & $mask)) << $shift);
            $shift = 4;
            $mask = 15;
            $third_int = (((($third_int
                + ($path_ints[5] & $mask)) << $shift)
                + ($path_ints[6] & $mask)) << $shift);
            $shift = 2;
            $mask = 3;
            $third_int = (((((($third_int
                + ($path_ints[7] & $mask)) << $shift)
                + ($path_ints[8] & $mask)) << $shift)
                + ($path_ints[9] & $mask)) << $shift)
                + ($path_ints[10] & $mask);
        break;
        case 13:
        default:
            $path_ints[10] = isset($path_ints[10]) ? $path_ints[10] : 0;
            $path_ints[11] = isset($path_ints[11]) ? $path_ints[11] : 0;
            $path_ints[12] = isset($path_ints[12]) ? $path_ints[12] : 0;
            $shift = 16;
            $mask = (1 << $shift) - 1;
            $second_int = (($path_ints[1] & $mask) << $shift)
                + ($path_ints[2] & $mask);
            $shift = 8;
            $mask = 127;
            $third_int = (((($path_ints[3] & $mask) << $shift)
                + ($path_ints[4] & $mask)) << $shift);
            $shift = 4;
            $mask = 15;
            $third_int = (((($third_int
                + ($path_ints[5] & $mask)) << $shift)
                + ($path_ints[6] & $mask)) << $shift);
            $shift = 2;
            $mask = 3;
            $third_int = (((($third_int
                + ($path_ints[7] & $mask)) << $shift)
                + ($path_ints[8] & $mask)) << $shift);
            $shift = 1;
            $mask = 1;
            $third_int = (((((($third_int
                + ($path_ints[9] & $mask)) << $shift)
                + ($path_ints[10] & $mask)) << $shift)
                + ($path_ints[11] & $mask)) << $shift)
                + ($path_ints[12] & $mask);
        break;
    }
    $hash = $front_hash. pack("NNN", $path_ints[0], $second_int,
        $third_int);
    $hash[8] = chr((ord($hash[8]) & 31) + ($mode_num << 5));
    if(!$raw) {
        $hash = base64Hash($hash);
            // common variant of base64 safe for urls and paths
    }
    return $hash;
}

/**
 *  Used to compare to ids for index dictionary lookup. ids
 *  might be either a crawlHash or a 5 byte crawlHash together
 *  with 3 byte hash path for suffix tree lookup. In the latter
 *  case the shift variable can be used to match up to a subtree
 *
 *  @param string $id1 16 byte word id to compare
 *  @param string $id2 16 byte word id to compare
 *  @param int $shift bit shift to apply before saying paths equal
 *  @return int negative if $id1 smaller, positive if bigger, and 0 if
 *      same
 */
function compareWordHashes($id1, $id2, $shift = 0)
{
    if(!isset($id1[8]) || !isset($id2[8])) {
        return strcmp(substr($id1, 0, 8), substr($id2, 0, 8));
    } else if($id1[8] == "\x00") {
        return strcmp(substr($id1, 0, 9), substr($id2, 0, 9));
    } else if($shift < 32) {
        $cmp = strcmp(substr($id1, 0, 16), substr($id2, 0, 16));
    } else if ($shift < 64) {
        $cmp = strcmp(substr($id1, 0,12), substr($id2, 0, 12));
    } else {
        $cmp = strcmp(substr($id1, 0,8), substr($id2, 0, 8));
    }

    if($cmp != 0) {
        return $cmp;
    }
    if($shift < 32) {
        $pos = 16;
    } else if ($shift < 64) {
        $shift -= 32;
        $pos = 12;
    } else {
        $shift -= 64;
        $pos = 8;
    }
    $id1 = packInt(unpackInt(substr($id1, $pos, 4)) >> $shift);
    $id2 = packInt(unpackInt(substr($id2, $pos, 4)) >> $shift);
    return strcmp($id1, $id2);
}

/**
 * Converts a crawl hash number to something closer to base64 coded but
 * so doesn't get confused in urls or DBs
 *
 *  @param string $string a hash to base64 encode
 *  @return string the encoded hash
 */
function base64Hash($string)
{
    $hash = rtrim(base64_encode($string), "=");
    $hash = str_replace("/", "_", $hash);
    $hash = str_replace("+", "-" , $hash);

    return $hash;
}


/**
 * Decodes a crawl hash number from base64 to raw ASCII
 *
 *  @param string $base64 a hash to decode
 *  @return string the decoded hash
 */
function unbase64Hash($base64)
{
    //get rid of out modified base64 encoding
    $hash = str_replace("_", "/", $base64);
    $hash = str_replace("-", "+" , $hash);
    $hash .= "=";
    $raw = base64_decode($hash);

    return $raw;
}

/**
 * Encodes a string in a format suitable for post data
 * (mainly, base64, but str_replace data that might mess up post in result)
 *
 * @param string $str string to encode
 * @return string encoded string
 */
function webencode($str)
{
    $str = base64_encode($str);
    $str = str_replace("/", "_", $str);
    $str = str_replace("+", ".", $str);
    $str = str_replace("=", "~", $str);
    return $str;
}

/**
 * Decodes a string encoded by webencode
 *
 * @param string $str string to encode
 * @return string encoded string
 */
function webdecode($str)
{
    $str = str_replace("_", "/", $str);
    $str = str_replace(".", "+", $str);
    $str = str_replace("~", "=", $str);
    return base64_decode($str);
}

/**
 * The search engine project's variation on the Unix crypt function using the
 * crawlHash function instead of DES
 *
 * The crawlHash function is used to encrypt passwords stored in the database
 *
 * @param string $string the string to encrypt
 * @param int $salt salt value to be used (needed to verify if a password is
 *      valid)
 * @return string the crypted string where crypting is done using crawlHash
 */
function crawlCrypt($string, $salt = NULL)
{
    if($salt == NULL) {
        $salt = rand(10000, 99999);
    } else {
        $len = strlen($salt);
        $salt = substr($salt, $len - 5, 5);
    }
    return crawlHash($string.$salt).$salt;
}

/**
 * Used by a controller to take a table and return those rows in the
 * table that a given queue_server would be responsible for handling
 *
 * @param array $table an array of rows of associative arrays which
 *      a queue_server might need to process
 * @param string $field column of $table whose values should be used
 *   for partitioning
 * @param int $num_partition number of queue_servers to choose between
 * @param int $instance the id of the particular server we are interested
 *  in
 * @param object $callback function or static method that might be
 *      applied to input before deciding the responsible queue_server.
 *      For example, if input was a url we might want to get the host
 *      before deciding on the queue_server
 * @return array the reduced table that the $instance queue_server is
 *      responsible for
 */
function partitionByHash($table, $field, $num_partition, $instance,
    $callback = NULL)
{
    $out_table = array();
    foreach($table as $row) {
        $cell = ($field === NULL) ? $row : $row[$field];
        $hash_int = calculatePartition($cell, $num_partition, $callback);

        if($hash_int  == $instance) {
            $out_table[] = $row;
        }
    }
    return $out_table;
}

/**
 * Used by a controller to say which queue_server should receive
 * a given input
 * @param string $input can view as a key that might be processes by a
 *      queue_server. For example, in some cases input might be
 *      a url and we want to determine which queue_server should be
 *      responsible for queuing that url
 * @param int $num_partition number of queue_servers to choose between
 * @param object $callback function or static method that might be
 *      applied to input before deciding the responsible queue_server.
 *      For example, if input was a url we might want to get the host
 *      before deciding on the queue_server
 * @return int id of server responsible for input
 */
function calculatePartition($input, $num_partition, $callback = NULL)
{
    if($callback !== NULL) {
        $callback_parts = explode("::", $callback);
        if(count($callback_parts) == 1) {
            $input = $callback($input);
        } else {
            $class_name = $callback_parts[0];
            $method_name = $callback_parts[1];
            $tmp_class = new $class_name;
            $input = $tmp_class->$method_name($input);
        }
    }
    $hash_int =  abs(unpackInt(substr(crawlHash($input, true), 0, 4))) %
        $num_partition;

    return $hash_int;
}

/**
 * Measures the change in time in seconds between two timestamps to microsecond
 * precision
 *
 * @param string $start starting time with microseconds
 * @param string $end ending time with microseconds, if null use current time
 * @return float time difference in seconds
 */
function changeInMicrotime($start, $end = NULL)
{
    if( !$end ) {
        $end = microtime();
    }
    return (substr($end, 11) - substr($start, 11))
        + (substr($end, 0, 9) - substr($start, 0, 9));
}


/**
 *  Converts a CSS unit string into its equivalent in pixels. This is
 *  used by @see SvgProcessor.
 *
 *  @param string $value  a number followed by a legal CSS unit
 *  @return int a number in pixels
 */
function convertPixels($value)
{
    $len = strlen($value);
    if($len < 2) return intval($value);
    if($value[$len - 1] == "%") {
        $num = floatval(substr($value, 0, $len - 1));
        return ($num > 0) ? floor(8*min(100, $num)) : 0;
    }
    $num = floatval(substr($value, 0, $len - 2));
    $unit = substr($value, $len - 2);
    switch($unit)
    {

        case "cm":
        case "pt":
            return intval(28*$num);
        break;
        case "em":
        case "pc":
            return intval(6*$num);
        break;
        case "ex":
            return intval(12*$num);
        break;
        case "in":
            //assume screen 72 dpi as on mac
            return intval(72*$num);
        break;
        case "mm":
            return intval(2.8*$num);
        break;
        case "px":
            return intval($num);
        break;
        default:
            $num = $value;
    }
    return intval($num);
}


/**
 * This is a callback function used in the process of recursively deleting a
 * directory
 *
 * @param string $file_or_dir the filename or directory name to be deleted
 * @see DatasourceManager::unlinkRecursive()
 */
function deleteFileOrDir($file_or_dir)
{
    if(is_file($file_or_dir)) {
        unlink($file_or_dir);
    } else {
        rmdir($file_or_dir);
    }
}

/**
 * This is a callback function used in the process of recursively chmoding to
 * 777 all files in a folder
 *
 * @param string $file the filename or directory name to be chmod
 * @see DatasourceManager::etWorldPermissionsRecursive()
 */
function setWorldPermissions($file)
{
    chmod($file, 0777);
}

/**
 * This is a callback function used in the process of recursively calculating
 * an array of file modification times and files sizes for a directorys
 *
 *  @param string a name of a file in the file system
 *  @return an array whose single element contain an associative array
 *      with the size and modification time of the file
 */
function fileInfo($file)
{
    $info["name"] = $file;
    $info["size"] = filesize($file);
    $info["is_dir"] = is_dir($file);
    $info["modified"] = filemtime($file);
    return array($info);
}

//ordering functions used in sorting

/**
 *  Callback function used to sort documents by a field
 *
 *  Should be initialized before using in usort with a call
 *  like: orderCallback($tmp, $tmp, "field_want");
 *
 *  @param string $word_doc_a doc id of first document to compare
 *  @param string $word_doc_b doc id of second document to compare
 *  @param string $field which field of these associative arrays to sort by
 *  @return int -1 if first doc bigger 1 otherwise
 */
function orderCallback($word_doc_a, $word_doc_b, $order_field = NULL)
{
    static $field = "a";
    if($order_field !== NULL) {
        $field = $order_field;
    }
    return ((float)$word_doc_a[$field] >
        (float)$word_doc_b[$field]) ? -1 : 1;
}

/**
 *  Callback function used to sort documents by a field in reverse order
 *
 *  Should be initialized before using in usort with a call
 *  like: rorderCallback($tmp, $tmp, "field_want");
 *
 *  @param string $word_doc_a doc id of first document to compare
 *  @param string $word_doc_b doc id of second document to compare
 *  @param string $field which field of these associative arrays to sort by
 *  @return int 1 if first doc bigger -1 otherwise
 */
function rorderCallback($word_doc_a, $word_doc_b, $order_field = NULL)
{
    static $field = "a";
    if($order_field !== NULL) {
        $field = $order_field;
    }
    return ((float)$word_doc_a[$field] >
        (float)$word_doc_b[$field]) ? 1 : -1;
}

/**
 * Callback to check if $a is less than $b
 *
 * Used to help sort document results returned in PhraseModel called
 * in IndexArchiveBundle
 *
 * @param float $a first value to compare
 * @param float $b second value to compare
 * @return int -1 if $a is less than $b; 1 otherwise
 * @see IndexArchiveBundle::getSelectiveWords()
 * @see PhraseModel::getPhrasePageResults()
 */
function lessThan($a, $b) {
    if ($a == $b) {
        return 0;
    }
    return ($a < $b) ? -1 : 1;
}

/**
 *  Callback to check if $a is greater than $b
 *
 * Used to help sort document results returned in PhraseModel called in
 * IndexArchiveBundle
 *
 * @param float $a first value to compare
 * @param float $b second value to compare
 * @return int -1 if $a is greater than $b; 1 otherwise
 * @see IndexArchiveBundle::getSelectiveWords()
 * @see PhraseModel::getTopPhrases()
 */
function greaterThan($a, $b) {
    if ($a == $b) {
        return 0;
    }
    return ($a > $b) ? -1 : 1;
}

/**
 * shorthand for echo
 *
 * @param string $text string to send to the current output
 */
function e($text)
{
    echo $text;
}

/**
 * Used to read a line of input from the command-line
 * @return string from the command-line
 */
function readInput()
{
    $stdin = fopen('php://stdin', 'r');
    $line = fgets($stdin);
    $line = rtrim($line);
    fclose($stdin);
    return $line;
}

/**
 * Used to read a line of input from the command-line
 * (on unix machines without echoing it)
 * @return string from the command-line
 */
function readPassword()
{
    system('stty -echo');
    $line = readInput();
    if(!strstr(PHP_OS, "WIN")) {
        e(str_repeat("*", strlen($line))."\n");
    }
    system('stty echo');

    return $line;
}

/**
 * Used to read a several lines from the terminal up until
 * a last line consisting of just a "."
 * @return string from the command-line
 */
function readMessage()
{
    $message = "";
    $line = "";
    do {
        $message .= $line;
        $line = readInput()."\n";
    } while(rtrim($line) != ".");

    return rtrim($message);
}

/**
 * Checks if class_1 is the same as class_2 of has class_2 as a parent
 * Behaves like 3 param version (last param true) of PHP is_a function
 * that came into being with Version 5.3.9.
 *
 */
function generalIsA($class_1, $class_2)
{
    if($class_1 == $class_2) return true;
    return (is_a($class_1, $class_2) || is_subclass_of($class_1, $class_2));
}

/**
 *  Given an array of arrays acting much like a database table, this
 *  returns a sequence of key value pairs, where the keys are the distinct
 *  entries in $key_column and the values are the counts of numbers in
 *  $count_column for each particular key;
 *
 *  @param array $arr an array of arrays
 *  @param mixed $key_column (string or int) field name of key column
 *  @param mixed $count_column (string or int) field name of count column
 *  @return array key => values pairs of counts
 */
function arrayColumnCount($arr, $key_column, $count_column)
{
    $out_arr = array();
    foreach($arr as $row) {
        if(!isset($out_arr[$row[$key_column]])) {
            $out_arr[$row[$key_column]] = 0;
        }
        $out_arr[$row[$key_column]] += $row[$count_column];
    }
    return $out_arr;
}

?>
