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

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Used to load common constants among crawl components */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Has methods to parse user-defined page rules to apply documents
 * to be indexed.
 *
 * There are two types of statements that a user can define:
 * command statements and assignment statements
 *
 * A command statement takes a field argument for the page associative
 * and that array and does a function call to manipulate that page.
 * Right now the two supported commands are to unset that field value,
 * and to add the field and field value to the META_WORD array for the page
 * These have the syntax:
 * unset(field)
 * addMetaWords(field)
 *
 * Assignments can either be straight assignments with '=' or concatenation
 * assignments with '.='. There are three kinds of values that one can assign:
 *
 * field = some_other_field ; sets $page['field'] = $page['some_other_field']
 * field = "some_string" ; sets $page['field'] to "some string"
 * field = /some_regex/replacement_where_dollar_vars_allowed/
 * ; computes the results of replacing matches to some_regex in $page['field']
 * ; with replacement_where_dollar_vars_allowed
 *
 * For each of the above assignments we could have used ".=" instead of "="
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */

class PageRuleParser implements CrawlConstants
{
    /**
     * Used to store parse trees that this parser executes
     * @var array
     */
    var $rule_trees;

    /**
     * Constructs a PageRuleParser using the supplied page_rules
     *
     * @param string $page_rules a sequence of lines with page rules
     *      as described in the class comments
     */
    function __construct($page_rules = "")
    {
        $this->rule_trees = $this->parseRules($page_rules);
    }

    /**
     * Parses a string of pages rules into parse trees hican be excuted
     * later
     *
     * @param string $page_rules a sequence of lines with page rules
     *      as described in the class comments
     * @return array of parse trees which can be executed in sequence
     */
    function parseRules($page_rules)
    {
        $quote_string = '"([^"\\\\]*(\\.[^"\\\\]*)*)"';
        $blank = '[ \t]';
        $comment = $blank.'*;[^\n]*';
        $literal = '\w+';
        $assignment = '\.?=';
        $start = '(?:\A|\n)';
        $end = '(?:\n|\Z)';
        $substitution = '(/[^/\n]+/)([^/\n]*)/';
        $command = '(\w+)\((\w+)\)';
        $rule = 
            "@(?:$command$blank*($comment)?$end".
            "|$blank*($literal)$blank*($assignment)$blank*".
            "((".$quote_string.")|($literal)|($substitution))".
            "$blank*($comment)?$end)@";
        $matches = array();
        preg_match_all($rule, $page_rules, $matches);
        $rule_trees = array();
        if(!isset($matches[0]) || 
            ($num_rules = count($matches[0])) == 0) { return $rule_trees; }
        for($i = 0; $i < $num_rules; $i++) {
            $tree = array();
            if($matches[1][$i] != "") {
                $tree["func_call"] = $matches[1][$i];
                $tree["arg"] = $matches[2][$i];
            } else {
                $tree["var"] = $matches[4][$i];
                $tree["assign_op"] = $matches[5][$i];
                $value_type_indicator = $matches[6][$i][0];
                if($value_type_indicator == '"') {
                    $tree["value_type"] = "string";
                    $tree["value"] = $matches[8][$i];
                } else if($value_type_indicator == '/') {
                    $tree["value_type"] = "substitution";
                    $tree["value"] = array($matches[12][$i], $matches[13][$i]);
                } else {
                    $tree["value_type"] = "literal";
                    $tree["value"] = $matches[10][$i];
                }
            }
            $rule_trees[] = $tree;
        }
        return $rule_trees;
    }

    /**
     * Executes either the internal $rule_trees or the passed $rule_trees
     * on the provided $page_data associative array
     *
     *  @param array &$page_data an associative array of containing summary
     *      info of a web page/record (will be changed by this operation)
     *  @param array $rule_trees an array of annotated syntax trees to
     *      for rules used to update $page_data
     */
    function executeRuleTrees(&$page_data, $rule_trees = NULL)
    {
        if($rule_trees == NULL) {
            $rule_trees = & $this->rule_trees;
        }
        foreach($rule_trees as $tree) {
            if(isset($tree['func_call'])) {
                $this->executeFunctionRule($tree, $page_data);
            } else {
                $this->executeAssignmentRule($tree, $page_data);
            }
        }
    }

    /**
     *  Used to execute a single command rule on $page_data
     *
     *  @param array $tree annotated syntax tree of a function call rule
     *  @param array &$page_data an associative array of containing summary
     *      info of a web page/record (will be changed by this operation)
     */
    function executeFunctionRule($tree, &$page_data)
    {
        $allowed_functions = array("unset" => "unsetVariable",
            "addMetaWord" => "addMetaWord", 
            "addKeywordLink" => "addKeywordLink");
        if(in_array($tree['func_call'], array_keys($allowed_functions))) {
            $func = $allowed_functions[$tree['func_call']];
            $this->$func($tree['arg'], $page_data);
        }
    }

    /**
     *  Used to execute a single assignment rule on $page_data
     *
     *  @param array $tree annotated syntax tree of an assignment rule
     *  @param array &$page_data an associative array of containing summary
     *      info of a web page/record (will be changed by this operation)
     */
    function executeAssignmentRule($tree, &$page_data)
    {
        $field = $this->getVarField($tree["var"]);
        if(!isset($page_data[$field])) {
            $page_data[$field] = "";
        }
        $value = "";
        switch($tree['value_type'])
        {
            case "literal":
                $literal = $this->getVarField($tree["value"]);
                if(isset($page_data[$literal])) {
                    $value = $page_data[$literal];
                }
            break;
            case "string":
                $value = $tree["value"];
            break;
            case "substitution":
                $value = preg_replace($tree["value"][0], $tree["value"][1],
                    $page_data[$field]);
            break;
        }
        if($tree["assign_op"] == "=") {
            $page_data[$field] = $value;
        } else {
            $page_data[$field] .= $value;
        }
    }

    /**
     * Either returns $var_name or the value of the CrawlConstant with name
     * $var_name.
     *
     * @param string $var_name field to look up
     * @return string looked up value
     */
    function getVarField($var_name)
    {
        if(defined("CrawlConstants::$var_name")) {
            return constant("CrawlConstants::$var_name");
        }
        return $var_name;
    }

    /**
     *  Unsets the key $field (or the crawl constant it corresponds to) 
     *  in $page_data. If it is a crawlconstant it doesn't unset it --
     *  it just sets it to the empty string
     *
     *  @param $field the key in $page_data to use
     *  @param array &$page_data an associative array of containing summary
     *      info of a web page/record
     */
    function unsetVariable($field, &$page_data)
    {
        $var_field = $this->getVarField($field);
        if($var_field == $field) {
            unset($page_data[$var_field]);
        } else {
            $page_data[$var_field] = "";
        }
    }

    /**
     *  Adds a meta word u:$field:$page_data[$field_name] to the array
     *  of meta words for this page
     *
     *  @param $field the key in $page_data to use
     *  @param array &$page_data an associative array of containing summary
     *      info of a web page/record
     */
    function addMetaWord($field, &$page_data)
    {
        $field_name = $this->getVarField($field);
        if(!isset($page_data[$field_name])) {return; }
        $meta_word = "u:$field_name:{$page_data[$field_name]}";
        if(!isset($page_data[CrawlConstants::META_WORDS])) {
            $page_data[CrawlConstants::META_WORDS] = array();
        }
        $page_data[CrawlConstants::META_WORDS][] = $meta_word;
    }

    /**
     *  Adds a $keywords => $link_text pair to the KEYWORD_LINKS array fro
     *  this page based on the value $field on the page. The pair is extracted
     *  by splitting on comma. The KEYWORD_LINKS array can be used when
     *  a cached version of a page is displayed to show a list of links
     *  from the cached page in the header. These links correspond to search
     *  in Yioop. for example the value:
     *  madonna, rock star
     *  would add a link to the top of the cache page with text "rock star"
     *  which when clicked would perform a Yioop search on madonna.
     *
     *  @param $field the key in $page_data to use
     *  @param array &$page_data an associative array of containing summary
     *      info of a web page/record
     */
    function addKeywordLink($field, &$page_data)
    {
        $field_name = $this->getVarField($field);
        if(!isset($page_data[$field_name])) {return; }
        $link_parts = explode(",", $page_data[$field_name]);
        if(count($link_parts) < 2) {return; }
        list($key_words, $link_text) = $link_parts;
        if(!isset($page_data[CrawlConstants::KEYWORD_LINKS])) {
            $page_data[CrawlConstants::KEYWORD_LINKS] = array();
        }
        $page_data[CrawlConstants::KEYWORD_LINKS][$key_words] = $link_text;
    }
}

?>
