
<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2011  Priya Gangaraju priya.gangaraju@gmail.com
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
 * @author Priya Gangaraju priya.gangaraju@gmail.com
 * @package seek_quarry
  * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}


require_once BASE_DIR."/lib/components/component.php";
require_once BASE_DIR."/lib/crawl_constants.php";
require_once BASE_DIR."/lib/components/Kruskal_clustering.php";
require_once BASE_DIR."/lib/index_shard.php";

class RecipeComponent extends Component implements CrawlConstants 
{

    var $models = array("phrase","locale","crawl");
    /**
     * extracts title and description from a recipe page.
     *
     * @param object $dom   a document object to extract a description from.
     * @return string a description of the page
     */
    function description($dom) {

        $xpath = new DOMXPath($dom);
        $recipes_per_page = $xpath->evaluate(
                               "/html//div[@class = 'ingredients'] |
                                /html//div[@class = 'body-text'] |
                                /html//ul[@class = 'clr'] |
                                /html//div[@class = 'recipeDetails']
                                     /ul[@class='ingredient_list']");
        $recipe = array();
        $subdocs_description = array();
        if($recipes_per_page->length != 0) {
            $recipes_count = $recipes_per_page->length;
            $titles = $xpath->evaluate("/html//div[@class='rectitle'] |
                                       /html//h1[@class = 'fn'] |
                                       /html//div[@class = 
                                        'pod about-recipe clrfix']/p |
                                       /html//h1[@class = 'recipeTitle']");
            for($i=0; $i<$recipes_count;$i++) {
                $ingredients = $xpath->evaluate("/html//div[@class = 
                                            'ingredients']/ul/li |
                                            /html//div[@class = 'body-text']
                                            /ul/li[@class = 'ingredient'] |
                                            /html//ul[@class = 'clr']/li |
                                            /html//div[@class = 'recipeDetails']
                                            /ul[@class='ingredient_list']/li |
                                            /html//div[@class = 'ingredients']
                                            /table/tr[@class = 'ingredient']");
                $ingredients_result = "";
                if($ingredients->length != 0){
                    $lastIngredient = end($ingredients);
                    foreach($ingredients as $ingredient) {
                        $content = trim($ingredient->textContent);
                        if(!empty($content)) {
                            if($content  != $lastIngredient)
                                $ingredients_result.= $content."||";
                            else
                                $ingredients_result.= $content;
                        }
                    }
                    $ingredients_result = mb_ereg_replace(
                                            "(\s)+", " ", $ingredients_result);
                }
                $recipe['title'] = $titles->item($i)->textContent;
                $recipe['ingredients'] = $ingredients_result;
                $subdocs_description[] = $recipe;
            }
        }
  
        return $subdocs_description;
    }
    /**
     *  implements post processing of recipes. recipes are extracted
     * ingredients are scrubbed and recipes are clustered. The clustered
     * recipes are added back to the index.
     *
     * @param string $index_name  index name of the current crawl.
     */    
    function postProcessing($index_name){
        
        $this->phraseModel->index_name = $index_name;
        $this->crawlModel->index_name = $index_name;
        $limit = 0;
        $results_per_page = 100;
        $raw_recipes = $this->phraseModel->getPhrasePageResults(
                    "recipe:all",$limit, $results_per_page, false);
        $total_count = $raw_recipes['TOTAL_ROWS'];
        if($total_count != 0){
            $k=0;
            $raw_recipes = array();
            while($k < $total_count) {
                $paginated_recipes = $this->phraseModel->getPhrasePageResults(
                        "recipe:all",$limit, $results_per_page, false);
                $k = $k + count($paginated_recipes['PAGES']);
                $raw_recipes = array_merge(
                                $raw_recipes,$paginated_recipes['PAGES']);
                $limit = $limit + $results_per_page;
            }
            $recipes = array();
            $i =0;    
            foreach($raw_recipes as $raw_recipe) {
                $description = $raw_recipe[self::DESCRIPTION];
                $ingredients = explode("||",$description);
                if(is_array($ingredients) && count($ingredients) > 1){
                    $recipes[$i][0]= $raw_recipe[self::TITLE];
                    $recipes[$i][1] = $ingredients;
                    $recipes[$i][2] = $raw_recipe['KEY'];
                    $recipes[$i][3] = $raw_recipe;
                    $i++;
                }
            }
                
            $recipes_ingredients = array();
            $count = count($recipes);
            foreach($recipes as $key=>$recipe){
                foreach($recipe[1] as $index=>$ingredient){
                    if(strlen($ingredient) != 0 && (
                            substr($ingredient,strlen($ingredient)-1) != ":")) {
                        $mainIngredient = 
                            $this->getIngredientName((string)$ingredient);
                        if(strlen($mainIngredient) != 0)
                            $recipe[1][$index] = $mainIngredient;
                        else
                            unset($recipe[1][$index]);
                            
                    }
                    else {
                        unset($recipe[1][$index]);
                    }
                }
                    $recipes[$key] = $recipe;
                    
                    
            }
            $count = count($recipes);
            $k = 0;
            $basic_ingredients = array('onion','oil','cheese','pepper','sauce',
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
            for($i =0;$i<$count;$i++) {
                $recipe1_main_ingredient = "";
                $recipe1 = $recipes[$i][1];
                $recipe_name = $recipes[$i][0];
                $recipe1_title = strtolower($recipes[$i][0]); 
                $distinct_ingredients[$recipe_name] = $recipes[$i][1];
                $doc_keys[$recipe_name] = $recipes[$i][2];
                $recipes_summary[$recipe_name] = $recipes[$i][3];
                for($j = $i+1; $j<$count;$j++) {
                    $recipe2_main_ingredient = "";
                    $recipe2 = $recipes[$j][1];
                    $recipe2_title = strtolower($recipes[$j][0]); 
                    $weights[$k][0] = $recipes[$i][0];
                    $weights[$k][1] = $recipes[$j][0];
                    $merge_array = array_merge($recipe1,$recipe2);
                    $vector_array = array_unique($merge_array);
                    sort($vector_array);
                    $recipe1_vector = array_fill_keys($vector_array, 0);
                    $recipe2_vector = array_fill_keys($vector_array, 0);
                    foreach($recipe1 as $ingredient){
                        if($ingredient != "" && 
                            !in_array($ingredient,$basic_ingredients)) {
                                if(strstr($recipe1_title,$ingredient)) {
                                    $recipe1_main_ingredient = $ingredient;
                                }
                        }
                        $recipe1_vector[$ingredient] = 1;
                    }
                    foreach($recipe2 as $ingredient) {
                        if($ingredient != ""&& !
                            in_array($ingredient,$basic_ingredients)) {
                                if(strstr($recipe2_title,$ingredient))  {
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
                        $vector_diff[$vector] = (pow($diff,2));
                        if(abs($diff) == 1)
                            $matches += 1;
                        $edge_weight += $vector_diff[$vector];
                    }
                    $main_ingredient_match = 1;
                    if($recipe1_main_ingredient != $recipe2_main_ingredient)
                        $main_ingredient_match = 1000;
                    $edge_weight = sqrt($edge_weight)*
                                    $matches*$main_ingredient_match;
                    $weights[$k][2] = $edge_weight;
                    $k++;
                }
            }
            
            $clusters = kruskalClustering($weights,$count,$distinct_ingredients);
            $index_shard = new IndexShard("cluster_shard");
            $meta_ids = array();
            $word_counts = array();
            $recipe_sites = array();
            foreach($clusters as $cluster) {
                $count = count($cluster);
                for($i=0; $i<$count-1;$i++) {
                    $meta_id = array();
                    $summary = array();
                    $recipe = $cluster[$i];
                    $doc_key = $doc_keys[$recipe];
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
                    $summary[self::TYPE] = 
                        $recipes_summary[$recipe][self::TYPE];
                    $summary[self::HTTP_CODE] = 
                        $recipes_summary[$recipe][self::HTTP_CODE];
                    $recipe_sites[] = $summary;
                    $meta_ids[$recipe] = $cluster["ingredient"];
                    $meta_id[] = "ingredient:".$cluster["ingredient"];
                    $index_shard->addDocumentWords($doc_key, 
                        self::NEEDS_OFFSET_FLAG, 
                        $word_counts, $meta_id, true, false);
                    $index_shard->save(true);
                }
            
            }
            
            $dir = CRAWL_DIR."/cache/".self::index_data_base_name.$index_name;
            $index_archive = new IndexArchiveBundle($dir,false);
            $generation = $index_archive->initGenerationToAdd($index_shard);
            if(isset($recipe_sites)) {
                $index_archive->addPages($generation, 
                    self::SUMMARY_OFFSET, $recipe_sites,0);
            }
            $k = 0;
            foreach($recipe_sites as $site) {
                $recipe = $site[self::TITLE];
                $hash = crawlHash($site[self::URL], true). 
                            $site[self::HASH] . 
                            crawlHash("link:".$site[self::URL], true);
                $summary_offsets[$hash] = 
                    array($site[self::SUMMARY_OFFSET], null);
            }
            $index_shard->changeDocumentOffsets($summary_offsets);
            $index_archive->addIndexData($index_shard);
            $index_archive->saveAndAddCurrentShardDictionary();
            $index_archive->dictionary->mergeAllTiers();
            $this->db->setWorldPermissionsRecursive(
                            CRAWL_DIR.'/cache/'.
                            self::index_data_base_name.$index_name);
            
        }
    }
    
    /**
     *  extracts the main ingredient from the ingredient.
     *
     * @param string $text ingredient.
     * @return string $name main ingredient
     */
    public function getIngredientName($text) {
        $special_chars = array('/\d+/','/\\//');
        $ingredient = preg_replace($special_chars,"",$text);
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
                        if(strstr($ingredient,$variety))
                            $ingredient = $variety;
        }
        $words = explode(' ',$ingredient);
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
        $name = implode(" ",$nouns);
        $name = preg_replace('/[^a-zA-Z]/',"",$name);
        return $name;
    }
    
}

?>
