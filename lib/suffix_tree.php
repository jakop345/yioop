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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */

/**
 * Data structure used to maintain a suffix tree for a passage of words.
 * The suffix tree is constructed using the linear time algorithm of
 * Ukkonen, E. (1995). "On-line construction of suffix trees".
 * Algorithmica 14 (3): 249–260.
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class SuffixTree
{
    /**
     * @var array
     */
    var $root;

    /**
     * @var int
     */
    var $last_added;

    /**
     * @var int
     */
    var $pos;

    /**
     * @var int
     */
    var $need_sym_link;

    /**
     * @var int
     */
    var $remainder;

    /**
     * @var int
     */
    var $active_index;

    /**
     * @var int
     */
    var $active_edge_index;

    /**
     * @var int
     */
    var $active_len;

    /**
     * @var int
     */
    var $size;

    /**
     * @var array
     */
    var $text;

    /**
     * @var array
     */
    var $tree;

    /**
     *
     */
    const INFTY = 2000000000;

    /**
     *
     */
    function __construct($text)
    {
        $this->text = $text;
        $this->size = count($text);
        $this->buildTree();
    }

    /**
     *  Builds the complete suffix tree for the text currently stored in
     *  $this->text. If you change this text and call this method again,
     *  it build a new tree based on the new text. Uses Ukkonen
     */
    function buildTree()
    {
        $this->tree = array();
        $this->need_sym_link = 0;
        $this->last_added = 0;
        $this->pos = -1;
        $this->remainder = 0;
        $this->active_edge_index = 0;
        $this->active_len = 0;
        $this->root = $this->makeNode(-1, -1);
        $this->active_index = $this->root;
        $num_terms = count($this->text);
        for($i = 0; $i < $num_terms; $i++) {
            $this->suffixTreeExtend();
        }
    }

    /**
     *
     */
    function makeNode($start, $end = self::INFTY)
    {
        $node = array();
        $node["start"] = $start;
        $node["end"]  = $end;
        $node["sym_link"] = 0;
        $node["next"] = array();
        $this->tree[++$this->last_added] = $node;
        return $this->last_added;
    }

    /**
     *
     */
    function edgeLength(&$node)
    {
        return min($node["end"], $this->pos + 1) - $node["start"];
    }

    /**
     *
     */
    function addSuffixLink($index)
    {
        if ($this->need_sym_link > 0) {
            $this->tree[$this->need_sym_link]["sym_link"] = $index;
        }
        $this->need_sym_link = $index;
    }

    /**
     *
     */
    function walkDown($index)
    {
        $edge_length = $this->edgeLength($this->tree[$index]);
        if($this->active_len >= $edge_length) {
            $this->active_edge_index += $edge_length;
            $this->active_len -= $edge_length;
            $this->active_index = $index;
            return true;
        }
        return false;
    }

    /**
     *
     */
    function suffixTreeExtend()
    {
        $this->pos++;
        $term = $this->text[$this->pos];
        $this->need_sym_link = -1;
        $this->remainder++;
        while($this->remainder > 0) {
            if ($this->active_len == 0) {
                $this->active_edge_index = $this->pos;
            }
            $active_term = $this->text[$this->active_edge_index];
            if(!isset($this->tree[$this->active_index]["next"][$active_term])) {
                $leaf = $this->makeNode($this->pos);
                $this->tree[$this->active_index]["next"][$active_term] = $leaf;
                $this->addSuffixLink($this->active_index); //rule 2
            } else {
                $next = $this->tree[$this->active_index]["next"][$active_term];
                if($this->walkDown($next)) {
                    continue; //observation 2
                }
                $start = $this->tree[$next]["start"];
                if($this->text[$start + $this->active_len] == $term) {
                    //observation 1
                    $this->active_len++;
                    $this->addSuffixLink($this->active_index); //observation 3
                    break;
                }
                $splitNode = $this->makeNode($start, $start+$this->active_len);
                $active_term = $this->text[$this->active_edge_index];
                $this->tree[$this->active_index]["next"][$active_term] =
                    $splitNode;
                $leaf = $this->makeNode($this->pos);
                $this->tree[$splitNode]["next"][$term] = $leaf;
                $this->tree[$next]["start"] += $this->active_len;
                $this->tree[$splitNode]["next"][
                    $this->text[$this->tree[$next]["start"]]] = $next;
                $this->addSuffixLink($splitNode); //rule 2
            }
            $this->remainder--;
            if ($this->active_index == $this->root && $this->active_len > 0) { 
                //rule 1
                $this->active_len--;
                $this->active_edge_index = $this->pos - $this->remainder + 1;
            } else {
                $this->active_index = 
                    ($this->tree[$this->active_index]["sym_link"] > 0 ) ?
                    $this->tree[$this->active_index]["sym_link"] : $this->root;
                    //rule 3
            }
        }
    }

    /**
     *
     */
    function outputMaximal($index, $path, $len, &$maximal)
    {
        $start = $this->tree[$index]["start"];
        $end = $this->tree[$index]["end"];
        $cond_max = "";
        if($start >= 0 && $end >= 0) {
            $tmp_terms = array_slice($this->text, $start, $end - $start);
            if($path != "") {
                $begin = $start - $len;
                $out_path = $path;
                if($len > MAX_QUERY_TERMS) {
                    $out_array = array_slice($this->text, $begin, 
                            MAX_QUERY_TERMS -1);
                    $out_path = implode(" ", $out_array)." * ";
                }
                $maximal[$out_path][] = $begin;
                if($begin != $start - 1) {
                    $first_last = $this->text[$begin]." * ".
                        $this->text[$start - 1];
                    $maximal[$first_last][] =  $begin;
                }
            }
            $tmp = implode(" ", $tmp_terms);
            $num = count($tmp_terms);
            if($path == "") {
                $len = $num;
                $path = $tmp;
            } else {
                $cond_max = $path;
                $path .= " ".$tmp;
                $len += $num;
            }
        }
        if($end == self::INFTY) {
            $cond_flag = true;
            $begin = $this->size - $len;
            $out_path = $path;
            if($len > MAX_QUERY_TERMS) {
                $out_array = array_slice($this->text, $begin,
                    MAX_QUERY_TERMS - 1);
                $out_path = implode(" ", $out_array). " *";
                $cond_flag = false;
            }
            $maximal[$out_path][] = $begin;
            if($cond_max != "" && $cond_flag) {
                $maximal[$out_path]["cond_max"] = $cond_max;
            }
            if($len != 1) {
                $first_last = $this->text[$begin]." * ".
                    $this->text[$this->size-1];
                $maximal[$first_last][] =  $begin;
            }
            return;
        }
        foreach($this->tree[$index]["next"] as $sub_index) {
            $this->outputMaximal($sub_index, $path, $len, $maximal);
        }
    }

}
?>
