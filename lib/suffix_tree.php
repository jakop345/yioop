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
     * The root node of the suffix trees
     * @var array
     */
    var $root;

    /**
     * Index of last node added to the suffix tree in the array used to
     * hold the suffix tree data structures
     * @var int
     */
    var $last_added;

    /**
     * Position in the $this->text up to which we have created a suffix tree
     * so far
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
     * Number of elements in $this->text. i.e., count($this->text)
     * @var int
     */
    var $size;

    /**
     * The sequence of terms, one array entry per term, that a suffix tree is
     * to be made from
     * @var array
     */
    var $text;

    /**
     * Used to hold the suffix tree data structure (represented as a sequence
     * of nodes)
     * @var array
     */
    var $tree;

    /**
     *  Upper bound on the length of any path in the tree
     */
    const INFTY = 2000000000;

    /**
     * Initializes a suffix tree based on the supplied array of terms.
     *
     * @param array $text a sequence of terms to build the suffix tree for
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
     * Makes a new node for the suffix tree structure. This node
     * is inserted at the end of the tree so far. A node is associative
     * array consisting of the fields "start" whose value
     * is the starting location in $this->text for this node, 
     * "end" location in $this->text up to which this node is
     * responsible, "sym_link" is a link to an isomorphic subtree for the
     * purposes of building the suffix tree, and "next" is an array of
     * next children in the tree.
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
     * The number of elements out of $this->text that this node is currently
     * responsible for
     *
     * @param array &$node the node to compute the length of
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
     * Given a suffix tree of the array of terms in $this->text up to
     * $this->pos, adds one to pos and build the suffix tree up to this
     * new value. i.e., the text with one more term added.
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
        if($start >= 0 && $end >= 0) {
            $tmp_terms = array_slice($this->text, $start, $end - $start);
            $tmp = implode(" ", $tmp_terms);
            $num = count($tmp_terms);
            if($path != "") {
                $begin = $start - $len;
                $out_path = $path;
                if($len > MAX_QUERY_TERMS) {
                    $out_path = implode(" ", array_slice($this->text, $begin,
                        MAX_QUERY_TERMS));
                }
                $maximal[$out_path][] = $begin;
                if(!isset($maximal[$out_path]["cond_max"])) {
                    $maximal[$out_path]["cond_max"] =
                        strpos($out_path, " ") + 1;
                }
                if($len > 1 && $len < MAX_QUERY_TERMS) {
                    $cond_max = strlen($path) + 1;
                }
                $path .= " ".$tmp;
                $len += $num;
                if(isset($cond_max)) {
                    $out_path = $path;
                    if($len > MAX_QUERY_TERMS) {
                        $out_path = implode(" ", array_slice($this->text,
                            $begin, MAX_QUERY_TERMS));
                    }
                    $maximal[$out_path]["cond_max"] = $cond_max;
                }
            } else {
                $len = $num;
                $path = $tmp;
            }
        }
        if($end == self::INFTY) {
            $begin = $this->size - $len;
            $out_path = $path;
            if($len > MAX_QUERY_TERMS) {
                $out_path = implode(" ", array_slice($this->text, $begin,
                    MAX_QUERY_TERMS));
            }
            $maximal[$out_path][] = $begin;
            if(!isset($maximal[$out_path]["cond_max"])) {
                $maximal[$out_path]["cond_max"] =
                    strpos($out_path, " ") + 1;
            }
            return;
        }
        foreach($this->tree[$index]["next"] as $sub_index) {
            $this->outputMaximal($sub_index, $path, $len, $maximal);
        }
    }
}
?>
