<?php
/**
 * class to define vertex
 **/
class Vertex
{
    private $label;
    private $visited;
    
    function __construct($label){
        $this->label = $label;
        $this->visited = false;
    }
    
    function getLabel(){
        return $this->label;
    }
    
    function visited(){
        $this->visited = true;
    }
    
    function isVisited(){
        return $this->visited;
    }
}    
/**
 * class to define edge
 **/    
class Edge
{
    private $start_vertex;
    private $end_vertex;
    private $cost;
    
    function __construct($vertex1,$vertex2,$cost){
        $this->start_vertex = new Vertex($vertex1);
        $this->end_vertex = new Vertex($vertex2);
        $this->cost = $cost;
    }
    
    function getStartVertex(){ 
        return $this->start_vertex;
    }
    
    function getEndVertex(){ 
        return $this->end_vertex;
    }
    
    function getCost(){
        return $this->cost;
    }
}

/**
 * class to define Minimum Spanning tree. constructMST constructs 
 * the minimum spanning tree using heap. formCluster forms clusters by 
 * deleting the most expensive edge. BreadthFisrtSearch is used to traverse the MST.
**/ 
class Tree 
{
    private $cluster_heap;
    private $vertices;
    private $adjMatrix;
    
    function __construct(){
        $this->cluster_heap = new Cluster();
        $this->vertices = array();
    } 

 /**
    * constructs the adjacency matrix for the MST.
    *
    * @param object array $edges vertices and edge weights of MST
    **/
    function constructMST($edges){
        foreach($edges as $edge){
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
    * forms the clusters by removing maximum weighted edges.
    * performs breadth-first search to cluster the recipes.
    *
    * @param int $k queue size
    * @param int $size number of recipes.
    * @return array $cluster clusters of recipes.
    **/
    function formCluster($k,$size){
        $this->cluster_heap->top();
        $nodeQueue = new Queue($k);
        $cluster_count = $size/10;
        $cluster = array();
        for($j = 0; $j<$cluster_count-1; $j++){
            $max_edge = $this->cluster_heap->extract();
            $cluster1_start = $max_edge->getStartVertex()->getLabel();
            $cluster2_start = $max_edge->getEndVertex()->getLabel();
            $this->adjMatrix[$cluster1_start][$cluster2_start] = -1;
            $this->adjMatrix[$cluster2_start][$cluster1_start] = -1;
            $nodeQueue->enqueue($cluster1_start);
            $nodeQueue->enqueue($cluster2_start);
        }
        $queue = new Queue($k);
        $i=0;
        while(!$nodeQueue->isEmpty()){
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
    * gets the next vertex  from the adjacency matrix for a given vertex
    *
    * @param string $vertex vertex 
    * @return adjacent vertex if it has otherwise -1.
    **/
    function getNextVertex($vertex){
        foreach($this->adjMatrix[$vertex] as $vert=>$value){
            if($value != -1 && ($this->vertices[$value]->isVisited() == false)){
                return $this->adjMatrix[$vertex][$vert];
            }
            
        }
        return -1;
    }
    
 /**
    * finds the common ingredient for each of the clusters.
    *
    * @param array $clusters clusters of recipes.
    * @param array $ingredients array of ingredients of recipes.
    * @return array $new_clusters clusters with common ingredient appended.
    **/
    function displayClusters($clusters,$ingredients){
        $k =1;
        $new_clusters = array();
        $basic_ingredients = array("onion","oil","cheese","pepper","sauce",
            "salt","milk","butter",'flour','cake','garlic','cream','soda',
            'honey','powder','sauce','water','vanilla','pepper','bread',
            'sugar','vanillaextract','celery','seasoning','syrup','skewers',
            'egg','muffin','ginger','basil','oregano','cinammon','cumin',
            'mayonnaise','mayo','chillipowder','lemon','greens','yogurt',
            'margarine','asparagus','halfhalf','pancakemix','coffee',
            'cookies','lime','chillies','cilantro','rosemary','vanillaextract',
            'vinegar','shallots','wine','cornmeal','nonstickspray');
        foreach($clusters as $cluster){
            $recipes_count = 0;
            $cluster_recipe_ingredients = array();
            $common_ingredients = array();
            print("Cluster ".$k."=");
            for($i=0; $i<count($cluster); $i++){
                $recipe_name = $cluster[$i];
                if($i != count($cluster)-1)
                    print($cluster[$i].",");
                else
                    print($cluster[$i]."\n");
                $main_ingredients = 
                    array_diff($ingredients[$recipe_name],$basic_ingredients);
                $cluster_recipe_ingredients = array_merge(
                    $cluster_recipe_ingredients,array_unique($main_ingredients));
            }
            $ingredient_occurrence = 
                array_count_values($cluster_recipe_ingredients);
            $max = max($ingredient_occurrence);
            foreach($ingredient_occurrence as $key=>$value){
                if($max == $value && !in_array($key, $basic_ingredients)){
                    $common_ingredients[] = $key;
                }
            }
            $cluster_ingredient = $common_ingredients[0];
            print("\n cluster ingredient : $cluster_ingredient");
            $cluster["ingredient"] = $cluster_ingredient;
            $new_clusters[] = $cluster;
            $k++;
        }
        return $new_clusters;
        
    }
}
/**
* heap to maintain the MST
**/
class Cluster extends SplHeap
{

    public function compare($edge1,$edge2){
        $values1 = $edge1->getCost();
        $values2 = $edge2->getCost();
        if ($values1 == $values2) return 0;
        return $values1 < $values2 ? -1 : 1;
    }
}
/**
* heap to maintain the tree
**/
class Tree_cluster extends SplHeap
{

    public function compare($edge1,$edge2){
        $values1 = $edge1->getCost();
        $values2 = $edge2->getCost();
        if ($values1 == $values2) return 0;
        return $values1 > $values2 ? -1 : 1;
    }
}

/**
* queue for the BFS traversal
**/
class Queue
{
    private $size;
    private $queArray;
    private $front;
    private $rear;
    
    function __construct($size){
        $this->queArray = array();
        $this->front = 0;
        $this->rear = -1;
        $this->size = $size;
    }
    
    function enqueue($i){
        if($this->rear == $this->size-1)
            $this->rear = -1;
        $this->queArray[++$this->rear] = $i;
    }
    
    function dequeue(){
        $temp = $this->queArray[$this->front++];
        if($this->front == $this->size)
            $this->front = 0;
        return $temp;
    }
    function isEmpty(){
        if(($this->rear+1)== $this->front || 
            ($this->front+$this->size-1) == $this->rear)
            return true;
        return false;
    }
    
}
/**
* creates tree from the input and apply kruskal's algorithm to find MST.
*
* @param object array $edges recipes with distances between them.
* @return object arrat $min_edges MST 
**/
function construct_tree($edges) {
    $vertices = array();
    $tree_heap = new Tree_cluster();
    $vertice_no = 1;
    for($i=0; $i<count($edges)-1;$i++){
        $edge1 = new Edge($edges[$i][0],$edges[$i][1],$edges[$i][2]);
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
    while($k < count($vertices)-1){
        
        $min_edge = $tree_heap->extract();
        $vertex1= $min_edge->getStartVertex()->getLabel();
        $vertex2 = $min_edge->getEndVertex()->getLabel();
        if($vertices[$vertex1]!= $vertices[$vertex2]){
            if($vertices[$vertex1]< $vertices[$vertex2]){
                    $m = $vertices[$vertex2];
                    $n = $vertices[$vertex1];
            }
            else{
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
* clusters the recipes by applying Kruskal's algorithm
* @param array $edges recipes and distances between them.
*
* @param int $count number of recipes.
* @param array $distinct_ingredients recipe names with ingredients.
* @return clusters of recipes.
**/          
function kruskalClustering($edges,$count,$distinct_ingredients) {
   
    $mst_edges = construct_tree($edges);
    $mst = new Tree();
    $mst->constructMST($mst_edges);
    $clusters = $mst->formCluster(count($mst_edges),$count);
    return($mst->displayClusters($clusters,$distinct_ingredients));
}

?>