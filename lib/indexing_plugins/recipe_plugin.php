<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2011 - 2014 Priya Gangaraju priya.gangaraju@gmail.com,
 *     Chris Pollett, chris@pollett.org
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
 * @author Priya Gangaraju priya.gangaraju@gmail.com, Chris Pollett
 *     chris@pollett.org
 * @package seek_quarry
 * @subpackage indexing_plugin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2011 -2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/** Un Register plugin if php version not high enough to have spi*/
if(!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300) {
    $INDEXING_PLUGINS = array_values(
        array_diff($INDEXING_PLUGINS, array("recipe")));
}
/**
 * Ratio of clusters/total number of recipes seen
 */
define("CLUSTER_RATIO", 0.1);
/** NO_CACHE means don't try to use memcache*/
if(!defined("NO_CACHE")) {
    define("NO_CACHE", true);
}
/** Don't try to use file cache either*/
if(!defined("USE_CACHE")) {
    define("USE_CACHE", false);
}
/** Loads processor used for */
require_once BASE_DIR."/lib/processors/html_processor.php";
/** Base indexing plugin class*/
require_once BASE_DIR."/lib/indexing_plugins/indexing_plugin.php";
/** Used to create index shards to add ingredient: entries
 * to index
 */
require_once BASE_DIR."/lib/index_shard.php";
/** Used to extract text from documents*/
require_once BASE_DIR."/lib/phrase_parser.php";
/** Get the crawlHash function */
require_once BASE_DIR."/lib/utility.php";
/** Loads common constants for web crawling */
require_once BASE_DIR."/lib/crawl_constants.php";
/** For locale used by recipe query*/
require_once BASE_DIR."/lib/locale_functions.php";
/**Load base controller class, if needed. */
require_once BASE_DIR."/controllers/search_controller.php";
/**
 * This class handles recipe processing.
 * It extracts ingredients from the recipe pages while crawling.
 * It clusters the recipes using Kruskal's minimum spanning tree
 * algorithm after crawl is stopped. This plugin was designed by
 * looking at what was needed to screen scrape recipes from the
 * following sites:
 *
 * http://allrecipes.com/
 * http://www.food.com/
 * http://www.betterrecipes.com/
 * http://www.foodnetwork.com/
 * http://www.bettycrocker.com/
 *
 *
 * @author Priya Gangaraju, Chris Pollett (re-organized, added documentation,
 *     updated)
 * @package seek_quarry
 * @subpackage indexing_plugin
 */
class RecipePlugin extends IndexingPlugin implements CrawlConstants
{
    /**
     * This method is called by a PageProcessor in its handle() method
     * just after it has processed a web page. This method allows
     * an indexing plugin to do additional processing on the page
     * such as adding sub-documents, before the page summary is
     * handed back to the fetcher. For the recipe plugin a sub-document
     * will be the title of the recipe. The description will consists
     * of the ingredients of the recipe. Ingredients will be separated by
     * ||
     *
     * @param string $page web-page contents
     * @param string $url the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return array consisting of a sequence of subdoc arrays found
     *     on the given page. Each subdoc array has a self::TITLE and
     *     a self::DESCRIPTION
     */
    function pageProcessing($page, $url)
    {
        crawlLog("...Using recipe plugin to check for recipes!");
        $page = preg_replace('@<script[^>]*?>.*?</script>@si', ' ', $page);
        $page = preg_replace('/>/', '> ', $page);
        $dom = HtmlProcessor::dom($page);
        if($dom == NULL) return NULL;

        $xpath = new DOMXPath($dom);
        $recipes_per_page = $xpath->evaluate(
            /*allr, f.com, brec, fnet*/
            "/html//ul[@class = 'ingredient-wrap'] |
            /html//*[@class = 'pod ingredients'] |
            /html//*[@id='recipe_title'] |
            /html//div[@class = 'rcp-head clrfix']|
            /html//h1[@class = 'fn recipeDetailHeading']");
        $recipe = array();
        $subdocs_description = array();
        if(is_object($recipes_per_page) && $recipes_per_page->length != 0) {
            $recipes_count = $recipes_per_page->length;
            $titles = $xpath->evaluate(
               /* allr, f.com, brec, fnet   */
               "/html//*[@id = 'itemTitle']|
               /html//h1[@class = 'fn'] |
               /html//*[@id='recipe_title'] |
               /html//div[@class ='rcp-head clrfix']/h1 |
               /html//h1[@class = 'fn recipeDetailHeading']");
            for($i=0; $i < $recipes_count; $i++) {
                $ingredients = $xpath->evaluate(
                    /*allr*, fcomm, brec, fnet*/
                    "/html//ul[@class = 'ingredient-wrap']/li |
                    /html//li[@class = 'ingredient']|
                    /html//*[@class = 'ingredients']/*|
                    /html//*[@itemprop='ingredients']
                    ");
                $ingredients_result = "";
                if(is_object($ingredients) && $ingredients->length != 0){
                    $lastIngredient = end($ingredients);
                    foreach($ingredients as $ingredient) {
                        $content = trim($ingredient->textContent);
                        if(!empty($content)) {
                            if($content  != $lastIngredient)
                                $ingredients_result .= $content."||";
                            else
                                $ingredients_result .= $content;
                        }
                    }
                    $ingredients_result = mb_ereg_replace(
                        "(\s)+", " ", $ingredients_result);
                }
                $recipe[self::TITLE] = $titles->item($i)->textContent;
                $recipe[self::DESCRIPTION] = $ingredients_result;
                $subdocs_description[] = $recipe;
            }
        }
        $num_recipes = count($subdocs_description);
        crawlLog("...$num_recipes found.");
        return $subdocs_description;
    }
    /**
     * Implements post processing of recipes. recipes are extracted
     * ingredients are scrubbed and recipes are clustered. The clustered
     * recipes are added back to the index.
     *
     * @param string $index_name  index name of the current crawl.
     */
    function postProcessing($index_name)
    {
        global $INDEXING_PLUGINS;
        if(!class_exists("SplHeap")) {
            crawlLog("...Recipe Plugin Requires SPLHeap for clustering!");
            crawlLog("...Aborting plugin");
            return;
        }
        $locale_tag = guessLocale();
        setLocaleObject($locale_tag);
        $search_controller = new SearchController($INDEXING_PLUGINS);
        $query = "recipe:all i:$index_name";
        crawlLog("...Running Recipe Plugin!");
        crawlLog("...Finding docs tagged as recipes.");
        $more_docs = true;
        $raw_recipes = array();
        $limit = 0;
        $num = 100;
        while($more_docs) {
            $results = @$search_controller->queryRequest($query,
                $num, $limit, 1, $index_name);
            if(isset($results["PAGES"]) &&
                ($num_results = count($results["PAGES"])) > 0 ) {
                $raw_recipes = array_merge($raw_recipes, $results["PAGES"]);
            }
            crawlLog("Scanning recipes $limit through ".
                ($limit + $num_results).".");
            $limit += $num_results;
            if(isset($results["SAVE_POINT"]) ){
                $end = true;
                foreach($results["SAVE_POINT"] as $save_point)  {
                    if($save_point != -1) {
                        $end = false;
                    }
                }
                if($end) {
                    $more_docs = false;
                }
            } else {
                $more_docs = false;
            }
        }
        crawlLog("...Clustering.");
        // only cluster if would make more than one cluster
        if(count($raw_recipes) * CLUSTER_RATIO > 1 ) {
            $recipes = array();
            $i = 0;
            foreach($raw_recipes as $raw_recipe) {
                $description = $raw_recipe[self::DESCRIPTION];
                $ingredients = explode("||", $description);
                if(is_array($ingredients) && count($ingredients) > 1) {
                    $recipes[$i][0]= $raw_recipe[self::TITLE];
                    $recipes[$i][1] = $ingredients;
                    $recipes[$i][2] = crawlHash($raw_recipe[self::URL]);
                    $recipes[$i][3] = $raw_recipe;
                    $i++;
                }
            }
            $recipes_ingredients = array();
            $count = count($recipes);
            foreach($recipes as $key => $recipe) {
                foreach($recipe[1] as $index => $ingredient) {
                    if(strlen($ingredient) != 0 && (
                            substr($ingredient,
                                strlen($ingredient) - 1) != ":")) {
                        $mainIngredient =
                            $this->getIngredientName((string)$ingredient);
                        if(strlen($mainIngredient) != 0) {
                            $recipe[1][$index] = $mainIngredient;
                        } else {
                            unset($recipe[1][$index]);
                        }
                    } else {
                        unset($recipe[1][$index]);
                    }
                }
                    $recipes[$key] = $recipe;
            }
            $count = count($recipes);
            $k = 0;
            $basic_ingredients = array(
               'onion','oil','cheese','pepper','sauce',
               'salt','milk','butter','flour','cake',
               'garlic','cream','soda','honey','powder',
               'sauce','water','vanilla','pepper','bread',
               'sugar','vanillaextract','celery',
               'seasoning','syrup','skewers','egg',
               'muffin','ginger','basil','oregano',
               'cinammon','cumin','mayonnaise','mayo',
               'chillipowder','lemon','greens','yogurt',
               'margarine','asparagus','halfhalf',
               'pancakemix','coffee','cookies','lime',
               'chillies','cilantro','rosemary',
               'vanillaextract','vinegar','shallots',
               'wine','cornmeal','nonstickspray');
            for($i = 0; $i < $count; $i++) {
                $recipe1_main_ingredient = "";
                $recipe1 = $recipes[$i][1];
                $recipe_name = $recipes[$i][0];
                $recipe1_title = strtolower($recipes[$i][0]);
                $distinct_ingredients[$recipe_name] = $recipes[$i][1];
                $doc_keys[$recipe_name] = $recipes[$i][2];
                $recipes_summary[$recipe_name] = $recipes[$i][3];
                for($j = $i + 1; $j < $count; $j++) {
                    $recipe2_main_ingredient = "";
                    $recipe2 = $recipes[$j][1];
                    $recipe2_title = strtolower($recipes[$j][0]);
                    $weights[$k][0] = $recipes[$i][0];
                    $weights[$k][1] = $recipes[$j][0];
                    $merge_array = array_merge($recipe1, $recipe2);
                    $vector_array = array_unique($merge_array);
                    sort($vector_array);
                    $recipe1_vector = array_fill_keys($vector_array, 0);
                    $recipe2_vector = array_fill_keys($vector_array, 0);
                    foreach($recipe1 as $ingredient){
                        if($ingredient != "" &&
                            !in_array($ingredient, $basic_ingredients)) {
                                if(strstr($recipe1_title, $ingredient)) {
                                    $recipe1_main_ingredient = $ingredient;
                                }
                        }
                        $recipe1_vector[$ingredient] = 1;
                    }
                    foreach($recipe2 as $ingredient) {
                        if($ingredient != ""&& !
                            in_array($ingredient, $basic_ingredients)) {
                                if(strstr($recipe2_title, $ingredient))  {
                                    $recipe2_main_ingredient = $ingredient;
                                }
                        }
                        $recipe2_vector[$ingredient] = 1;
                    }
                    $edge_weight = 0;
                    $matches = 1;
                    foreach($vector_array as $vector) {
                        $diff = $recipe1_vector[$vector] -
                                    $recipe2_vector[$vector];
                        $vector_diff[$vector] = (pow($diff, 2));
                        if(abs($diff) == 1)
                            $matches += 1;
                        $edge_weight += $vector_diff[$vector];
                    }
                    $main_ingredient_match = 1;
                    if($recipe1_main_ingredient != $recipe2_main_ingredient)
                        $main_ingredient_match = 1000;
                    $edge_weight = sqrt($edge_weight)*
                                    $matches * $main_ingredient_match;
                    $weights[$k][2] = $edge_weight;
                    $k++;
                }
            }
            crawlLog("...Making new shard with clustered recipes as docs.");
            $clusters = kruskalClustering($weights,
                $count, $distinct_ingredients);
            $index_shard = new IndexShard("cluster_shard");
            $word_lists = array();
            $recipe_sites = array();
            foreach($clusters as $cluster) {
                $count = count($cluster);
                for($i = 0; $i < $count - 1; $i++) {
                    $meta_ids = array();
                    $summary = array();
                    $recipe = $cluster[$i];
                    $summary[self::URL] =
                        $recipes_summary[$recipe][self::URL];
                    $summary[self::TITLE] =
                        $recipes_summary[$recipe][self::TITLE];
                    $summary[self::DESCRIPTION] =
                        $recipes_summary[$recipe][self::DESCRIPTION];
                    $summary[self::TIMESTAMP] =
                        $recipes_summary[$recipe][self::TIMESTAMP];
                    $summary[self::ENCODING] =
                        $recipes_summary[$recipe][self::ENCODING];
                    $summary[self::HASH] =
                        $recipes_summary[$recipe][self::HASH];
                    $doc_keys[$recipe] =
                        crawlHash($summary[self::URL], true);
                    $hash_rhost =  "r". substr(crawlHash( // r is for recipe
                        UrlParser::getHost($summary[self::URL])."/",true), 1);
                    $doc_keys[$recipe] .= $summary[self::HASH] . $hash_rhost;
                    $summary[self::TYPE] =
                        $recipes_summary[$recipe][self::TYPE];
                    $summary[self::HTTP_CODE] =
                        $recipes_summary[$recipe][self::HTTP_CODE];
                    $recipe_sites[] = $summary;
                    $meta_ids[] = "ingredient:".trim($cluster["ingredient"]);
                    crawlLog("ingredient:".$cluster["ingredient"]);
                    if(!$index_shard->addDocumentWords($doc_keys[$recipe],
                        self::NEEDS_OFFSET_FLAG,
                        $word_lists, $meta_ids, true, false)) {
                        crawlLog("Problem inserting recipe: ".
                            $summary[self::TITLE]);
                    }
                }
            }
            $shard_string = $index_shard->save(true);
            $index_shard = IndexShard::load("cluster_shard",
                $shard_string);
            unset($shard_string);
            crawlLog("...Adding recipe shard to index archive bundle");
            $dir = CRAWL_DIR."/cache/".self::index_data_base_name.$index_name;
            $index_archive = new IndexArchiveBundle($dir, false);
            if($index_shard->word_docs_packed) {
                $index_shard->unpackWordDocs();
            }
            $generation = $index_archive->initGenerationToAdd($index_shard);
            if(isset($recipe_sites)) {
                crawlLog("... Adding ".count($recipe_sites)." recipe docs.");
                $index_archive->addPages($generation,
                    self::SUMMARY_OFFSET, $recipe_sites, 0);
            }
            $k = 0;
            foreach($recipe_sites as $site) {
                $recipe = $site[self::TITLE];
                $hash = crawlHash($site[self::URL], true).
                    $site[self::HASH] .
                    "r". substr(crawlHash( // r is for recipe
                    UrlParser::getHost($site[self::URL])."/",true), 1);
                $summary_offsets[$hash] = $site[self::SUMMARY_OFFSET];
            }
            $index_shard->changeDocumentOffsets($summary_offsets);
            $index_archive->addIndexData($index_shard);
            $index_archive->saveAndAddCurrentShardDictionary();
            $index_archive->dictionary->mergeAllTiers();
            $this->db->setWorldPermissionsRecursive(
                CRAWL_DIR.'/cache/'.
                self::index_data_base_name.$index_name);
            crawlLog("...Recipe plugin finished.");
        }
    }
    /**
     * Extracts the main ingredient from the ingredient.
     *
     * @param string $text ingredient.
     * @return string $name main ingredient
     */
    function getIngredientName($text)
    {
        $special_chars = array('/\d+/','/\\//');
        $ingredient = preg_replace($special_chars," ", $text);
        $ingredient = strtolower($ingredient);
        $varieties = array('apple','bread','cheese','chicken','shrimp',
            'tilapia','salmon','butter','chocolate','sugar','pepper','water',
            'mustard','cream','lettuce','sauce','crab','garlic','mushrooms',
            'tortilla','potatoes','steak','rice','vinegar','carrots',
            'marshmellows','onion','oil','ham','parsley','cilantro','broth',
            'stock','flour','seasoning','banana','pasta','noodles','pork',
            'bacon','olives','spinach','yogurt','celery','beans','egg',
            'apricot','whiskey','wine','milk','mango','tomato','lemon',
            'salsa','herbs','sourdough','prosciutto','seasoning','syrup',
            'honey','skewers','muffin','beef','cinammon','thyme','asparagus',
            'turkey','pumpkin');
        foreach($varieties as $variety){
            if(strstr($ingredient, $variety)) {
                $ingredient = $variety;
            }
        }
        $words = explode(' ', $ingredient);
        $measurements = array('cup','cups','ounces','teaspoon','teaspoons',
            'tablespoon','tablespoons','pound','pounds','tbsp','tsp','lbs',
            'inch','pinch','oz','lb','tbs','can','bag','C','c','tb');
        $sizes = array('small','large','thin','less','thick','bunch');
        $prepositions = array('into', 'for', 'by','to','of');
        $misc = array('hot','cold','room','temperature','plus','stick','pieces',
            "confectioners",'semisweet','white','all-purpose','bittersweet',
            'cut','whole','or','and','french','wedges','package','pkg','shells',
            'cartilege','clean','hickory','fillets','fillet','plank','planks',
            'cedar','taste','spicy','glaze','crunchy','sharp','chips','juice',
            'optional','fine','regular','dash','overnight','soaked','classic',
            'firm','delicious','prefer','plain');
        $attributes = array('boneless','skinless','breast','legs','thighs',
            'washington','fresh','flat','leaf','ground','extra','virgin','dry',
            'cloves','lean','ground','roma','all purpose','light','brown',
            'idaho','kosher','frozen','garnish');
        $nouns = array();
        $i = 0;
        $endings = array('/\,/','/\./','/\+/','/\*/',"/'/","/\(/","/\)/");
        foreach($words as $word) {
            if($word != ''){
                $word = strtolower($word);
                foreach($varieties as $variety){
                        if(strstr($word,$variety))
                            $word = $variety;
                    }
                $word = preg_replace($endings,"",$word);
                if(!in_array($word,$measurements) && !in_array($word,$sizes)
                    && !in_array($word,$prepositions) && !in_array($word,$misc)
                    && !in_array($word,$attributes)) {
                    $ending = substr($word, -2);
                    $ending2 = substr($word, -3);
                    if($ending != 'ly' && $ending != 'ed' && $ending2 != 'ing')
                    {
                    $nouns[] = $word;
                    }
                }
            }
        }
        $name = implode(" ", $nouns);
        $name = preg_replace('/[^a-zA-Z]/', "", $name);
        return $name;
    }
    /**
     * Which mime type page processors this plugin should do additional
     * processing for
     *
     * @return array an array of page processors
     */
    static function getProcessors()
    {
        return array("HtmlProcessor");
    }
    /**
     * Returns an array of additional meta words which have been added by
     * this plugin
     *
     * @return array meta words and maximum description length of results
     *     allowed for that meta word (in this case 2000 as want
     *     to allow sufficient descriptions of whole recipes)
     */
    static function getAdditionalMetaWords()
    {
        return array("recipe:" => MAX_DESCRIPTION_LEN,
            "ingredient:" => MAX_DESCRIPTION_LEN);
    }
}
if(!function_exists("getLocaleTag")) {
    /**
     * Gets the language tag (for instance, en_US for American English) of the
     * locale that is currently being used.
     *
     * @return string  "en-US" since for now the recipe plugin only works
     *     with English recipes
     */
    function getLocaleTag()
    {
        return "en_US";
    }
}
/**
 * Vertex class for Recipe Clustering Minimal Spanning Tree
 * @package seek_quarry
 * @subpackage indexing_plugin
 */
class Vertex
{
    /**
     * Name of this Vertex (recipe title)
     * @var string
     */
    var $label;
    /**
     * Whether this node has been seen as part of MST construction
     * @var bool
     */
    var $visited;
    /**
     * Construct a vertex suitable for the Recipe Clustering Minimal Spanning
     * Tree
     *
     * @param string $label name of this Vertex (recipe title)
     */
    function __construct($label)
    {
        $this->label = $label;
        $this->visited = false;
    }
    /**
     * Accessor for label of this Vertex
     * @return string label of Vertex
     */
    function getLabel()
    {
        return $this->label;
    }
    /**
     * Sets the vertex to visited
     */
    function visited()
    {
        $this->visited = true;
    }
    /**
     * Accessor for $visited state of this Vertex
     * @return bool $visited state
     */
    function isVisited()
    {
        return $this->visited;
    }
}
/**
 * Directed Edge class for Recipe Clustering Minimal Spanning Tree
 *
 * @package seek_quarry
 * @subpackage indexing_plugin
 */
class Edge
{
    /**
     * Starting vertex of the directed edge this object represents
     * @var Vertex
     */
    var $start_vertex;
    /**
     * End vertex of the directed edge this object represents
     * @var Vertex
     */
    var $end_vertex;
    /**
     * Weight of this edge
     * @var float
     */
    var $cost;
    /**
     * Construct a directed Edge using a starting and ending vertex and a weight
     *
     * @param Vertex $vertex1 starting Vertex
     * @param Vertex $vertex2 ending Vertex
     * @param float $cost weight of this edge
     */
    function __construct($vertex1, $vertex2, $cost)
    {
        $this->start_vertex = new Vertex($vertex1);
        $this->end_vertex = new Vertex($vertex2);
        $this->cost = $cost;
    }
    /**
     * Accessor for starting vertex of this edge
     * @return Vertex starting vertex
     */
    function getStartVertex()
    {
        return $this->start_vertex;
    }
    /**
     * Accessor for ending vertex of this edge
     * @return Vertex ending vertex
     */
    function getEndVertex()
    {
        return $this->end_vertex;
    }
    /**
     * Accessor for weight of this edge
     * @return float weight of this edge
     */
    function getCost()
    {
        return $this->cost;
    }
}
/**
 * Class to define Minimum Spanning tree for recipes. constructMST constructs
 * the minimum spanning tree using heap. formCluster forms clusters by
 * deleting the most expensive edge. BreadthFirstSearch is used to
 * traverse the MST.
 * @package seek_quarry
 * @subpackage indexing_plugin
 */
class Tree
{
    /**
     * Maintains a priority queue of edges ordered by max weight
     * @var Cluster
     */
    var $cluster_heap;
    /**
     * Array of Vertices (Recipes)
     * @var array
     */
    var $vertices;
    /**
     * Adjacency matrix of whether recipes are adjacent to each other
     * @var array
     */
    var $adjMatrix;
    /**
     * Constructs a tree suitable for building containing a Minimal Spanning
     * Tree for Kruskal clustering
     */
    function __construct()
    {
        $this->cluster_heap = new Cluster();
        $this->vertices = array();
    }
   /**
    * Constructs the adjacency matrix for the MST.
    *
    * @param array $edges vertices and edge weights of MST
    */
    function constructMST($edges)
    {
        foreach($edges as $edge) {
            $this->cluster_heap->insert($edge);
            $vertex1 = $edge->getStartVertex();
            $vertex2 = $edge->getEndVertex();
            $this->adjMatrix[$vertex1->getLabel()][$vertex2->getLabel()] =
                $vertex2->getLabel();
            $this->adjMatrix[$vertex2->getLabel()][$vertex1->getLabel()] =
                $vertex1->getLabel();
            if(empty($this->vertices) || !in_array($vertex1,$this->vertices))
                $this->vertices[$vertex1->getLabel()] = $vertex1;
            if(empty($this->vertices) || !in_array($vertex2,$this->vertices))
                $this->vertices[$vertex2->getLabel()] = $vertex2;
        }
    }
   /**
    * Forms the clusters by removing maximum weighted edges.
    * performs breadth-first search to cluster the recipes.
    *
    * @param int $k queue size
    * @param int $size number of recipes.
    * @return array $cluster clusters of recipes.
    */
    function formCluster($k, $size)
    {
        $this->cluster_heap->top();
        $nodeQueue = new Queue($k);
        $cluster_count = $size * CLUSTER_RATIO;
        $cluster = array();
        /*
            Idea remove $cluster_count many weightiest edges from tree
            to get a forest. As do this add to queue end points of
            removed edges.
         */
        for($j = 0; $j < $cluster_count - 1; $j++) {
            $max_edge = $this->cluster_heap->extract();
            $cluster1_start = $max_edge->getStartVertex()->getLabel();
            $cluster2_start = $max_edge->getEndVertex()->getLabel();
            $this->adjMatrix[$cluster1_start][$cluster2_start] = -1;
            $this->adjMatrix[$cluster2_start][$cluster1_start] = -1;
            $nodeQueue->enqueue($cluster1_start);
            $nodeQueue->enqueue($cluster2_start);
        }
        $queue = new Queue($k);
        $i = 0;
        // Now use Queue above to make clusters (trees in resulting forest)
        while(!$nodeQueue->isEmpty()) {
            $node = $nodeQueue->dequeue();
            if($this->vertices[$node]->isVisited() == false){
                $this->vertices[$node]->visited();
                $cluster[$i][] = $this->vertices[$node]->getLabel();
                $queue->enqueue($this->vertices[$node]->getLabel());
                while(!$queue->isEmpty()){
                    $node = $queue->dequeue();
                    while(($nextnode = $this->getNextVertex($node)) != -1){
                        $this->vertices[$nextnode]->visited();
                        $cluster[$i][]= $this->vertices[$nextnode]->getLabel();
                        $queue->enqueue($this->vertices[$nextnode]->getLabel());
                    }
                }
            }
            $i++;
        }
        return $cluster;
    }
   /**
    * Gets the next vertex  from the adjacency matrix for a given vertex
    *
    * @param string $vertex vertex
    * @return adjacent vertex if it has otherwise -1.
    */
    function getNextVertex($vertex)
    {
        foreach($this->adjMatrix[$vertex] as $vert=>$value) {
            if($value != -1
                && ($this->vertices[$value]->isVisited() == false)) {
                return $this->adjMatrix[$vertex][$vert];
            }
        }
        return -1;
    }
   /**
    * Finds the common ingredient for each of the clusters.
    *
    * @param array $clusters clusters of recipes.
    * @param array $ingredients array of ingredients of recipes.
    * @return array $new_clusters clusters with common ingredient appended.
    */
    function findCommonIngredient($clusters, $ingredients)
    {
        $k =1;
        $new_clusters = array();
        $basic_ingredients = array("onion", "oil", "cheese", "pepper", "sauce",
            "salt", "milk", "butter", 'flour', 'cake', 'garlic','cream','soda',
            'honey','powder','sauce','water','vanilla','pepper','bread',
            'sugar','vanillaextract','celery','seasoning','syrup','skewers',
            'egg','muffin','ginger','basil','oregano','cinammon','cumin',
            'mayonnaise','mayo','chillipowder','lemon','greens','yogurt',
            'margarine','asparagus','halfhalf','pancakemix','coffee',
            'cookies','lime','chillies','cilantro','rosemary','vanillaextract',
            'vinegar','shallots','wine','cornmeal','nonstickspray');
        foreach($clusters as $cluster) {
            $recipes_count = 0;
            $cluster_recipe_ingredients = array();
            $common_ingredients = array();
            for($i = 0; $i < count($cluster); $i++){
                $recipe_name = $cluster[$i];
                $main_ingredients =
                    array_diff($ingredients[$recipe_name],$basic_ingredients);
                $cluster_recipe_ingredients = array_merge(
                    $cluster_recipe_ingredients,
                    array_unique($main_ingredients));
            }
            $ingredient_occurrence =
                array_count_values($cluster_recipe_ingredients);
            $max = max($ingredient_occurrence);
            foreach($ingredient_occurrence as $key => $value){
                if($max == $value && !in_array($key, $basic_ingredients)) {
                    $common_ingredients[] = $key;
                }
            }
            $cluster_ingredient = $common_ingredients[0];
            $cluster["ingredient"] = $cluster_ingredient;
            $new_clusters[] = $cluster;
            $k++;
        }
        return $new_clusters;

    }
}

if(!class_exists("SplHeap")) {
    /**
     * Dummy version of PHP SplHeap so code doesn't crash if doesn exist
     */
    class SplHeap {
    }
}

/**
 * Heap to maintain the MST
 * @package seek_quarry
 * @subpackage indexing_plugin
 */
class Cluster extends SplHeap
{
    /**
     *  Compares the weights of two edges and returns -1, 0, 1 depending
     *  on which is the largest first, equal, or second
     *
     * @param Edge $edge1 first Edge to compare
     * @param Edge $edge2 second Edge to compare
     * @return int -1,-0,1 as described above
     */
    function compare($edge1, $edge2)
    {
        $values1 = $edge1->getCost();
        $values2 = $edge2->getCost();
        if ($values1 == $values2) return 0;
        return $values1 < $values2 ? -1 : 1;
    }
}
/**
 * Heap to maintain the tree
 * @package seek_quarry
 * @subpackage indexing_plugin
 */
class TreeCluster extends SplHeap
{
    /**
     *  Compares the weights of two edges and returns -1, 0, 1 depending
     *  on which is the largest first, equal, or second
     *
     * @param Edge $edge1 first Edge to compare
     * @param Edge $edge2 second Edge to compare
     * @return int -1,-0,1 as described above
     */
    function compare($edge1, $edge2)
    {
        $values1 = $edge1->getCost();
        $values2 = $edge2->getCost();
        if ($values1 == $values2) return 0;
        return $values1 > $values2 ? -1 : 1;
    }
}

/**
 * Queue for the BFS traversal
 * @package seek_quarry
 * @subpackage indexing_plugin
 */
class Queue
{
    /**
     * Number of elements queue can hold
     * @var int
     */
    var $size;
    /**
     * Circular array used to store queue elements
     * @var array
     */
    var $queArray;
    /**
     * Index in $queArray of the front of the queue
     * @var int
     */
    var $front;
    /**
     * Index in $queArray of the end of the queue
     * @var int
     */
    var $rear;
    /**
     * Builds a queue suitable for doing breadth first search traversal
     * @param int $size number of elements queue can hold
     */
    function __construct($size)
    {
        $this->queArray = array();
        $this->front = 0;
        $this->rear = -1;
        $this->size = $size;
    }
    /**
     * Add an element, typically a Vertex label to the queue
     * @param string $i typically a Vertex label
     */
    function enqueue($i)
    {
        if($this->rear == $this->size - 1)
            $this->rear = -1;
        $this->queArray[++$this->rear] = $i;
    }
    /**
     * Removes the front of the queue and returns it
     * @return string front of queue
     */
    function dequeue()
    {
        $temp = $this->queArray[$this->front++];
        if($this->front == $this->size)
            $this->front = 0;
        return $temp;
    }
    /**
     * Whether or not the queue is empty
     * @return bool
     */
    function isEmpty()
    {
        if(($this->rear + 1)== $this->front ||
            ($this->front + $this->size - 1) == $this->rear)
            return true;
        return false;
    }

}
/**
 * Creates tree from the input and apply Kruskal's algorithm to find MST.
 *
 * @param array $edges recipes with distances between them.
 * @return object arrat $min_edges MST
 */
function construct_tree($edges)
{
    $vertices = array();
    $tree_heap = new TreeCluster();
    $vertice_no = 1;
    for($i = 0; $i < count($edges) - 1; $i++) {
        $edge1 = new Edge($edges[$i][0], $edges[$i][1], $edges[$i][2]);
        $tree_heap->insert($edge1);
        $vertex1 = $edge1->getStartVertex();
        $vertex2 = $edge1->getEndVertex();
        if(empty($vertices[$vertex1->getLabel()])){
                $vertices[$vertex1->getLabel()] = $vertice_no;
                $vertice_no++;
        }
        if(empty($vertices[$vertex2->getLabel()])){
                $vertices[$vertex2->getLabel()] = $vertice_no;
                $vertice_no++;
        }
    }
    $k = 0;
    $tree_heap->top();
    while($k < count($vertices) - 1) {

        $min_edge = $tree_heap->extract();
        $vertex1= $min_edge->getStartVertex()->getLabel();
        $vertex2 = $min_edge->getEndVertex()->getLabel();
        if($vertices[$vertex1] != $vertices[$vertex2]){
            if($vertices[$vertex1] < $vertices[$vertex2]){
                    $m = $vertices[$vertex2];
                    $n = $vertices[$vertex1];
            } else {
                $m = $vertices[$vertex1];
                $n = $vertices[$vertex2];
            }
            foreach($vertices as $vertex => $no){
                if($no == $m){
                    $vertices[$vertex] = $n;
                }
            }
            $min_edges[] = $min_edge;
            $k++;
        }
    }
    return $min_edges;
}
/**
 * Clusters the recipes by applying Kruskal's algorithm
 *
 * @param array $edges array of triples (recipe_1_title, recipe_2_title, weight)
 * @param int $count number of recipes.
 * @param array $distinct_ingredients list of possible ingredients
 * @return clusters of recipes.
 */
function kruskalClustering($edges, $count, $distinct_ingredients)
{
    $mst_edges = construct_tree($edges);
    $mst = new Tree();
    $mst->constructMST($mst_edges);
    $clusters = $mst->formCluster(count($mst_edges), $count);
    $new_clusters = $mst->findCommonIngredient($clusters,
        $distinct_ingredients);
    return $new_clusters;
}
?>
