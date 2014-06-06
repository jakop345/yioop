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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage classifier
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * This is an abstract class that specifies an interface for selecting top
 * features from a dataset.
 *
 * Each FeatureSelection class implements a select method that takes a Features
 * instance and returns a mapping from a subset of the old feature indices to
 * new ones.
 *
 * @author Shawn Tice
 * @package seek_quarry
 * @subpackage classifier
 */
abstract class FeatureSelection
{
    /**
     * Sets any passed runtime parameters.
     *
     * @param array $parameters optional associative array of parameters to
     *  replace the default ones with
     */
    function __construct($parameters = array())
    {
        foreach ($parameters as $parameter => $value) {
            $this->$parameter = $value;
        }
    }
    /**
     * Constructs a map from old feature indices to new ones according to a
     * max-heap of the most informative features. Always keep feature index 0,
     * which is used as an intercept term.
     *
     * @param object $selected max heap containing entries ordered by
     *  informativeness and feature index.
     * @return array associative array mapping a subset of the original feature
     *  indices to the new indices
     */
    function buildMap($selected)
    {
        $keep_features = array(0 => 0);
        $i = 1;
        while (!$selected->isEmpty()) {
            list($chi2, $j) = $selected->extract();
            $keep_features[$j] = $i++;
        }
        return $keep_features;
    }
    /**
     * Computes the top features of a Features instance, and returns a mapping
     * from a subset of those features to new contiguous indices. The mapping
     * allows documents that have already been mapped into the larger feature
     * space to be converted to the smaller feature space, while keeping the
     * feature indices contiguous (e.g., 1, 2, 3, 4, ... instead of 22, 35, 75,
     * ...).
     *
     * @param object $features Features instance
     * @return array associative array mapping a subset of the original feature
     *  indices to new indices
     */
    abstract function select(Features $features);
}
/**
 * A subclass of FeatureSelection that implements chi-squared feature
 * selection.
 *
 * This feature selection method scores each feature according to its
 * informativeness, then selects the top N most informative features, where N
 * is a run-time parameter.
 *
 * @author Shawn Tice
 * @package seek_quarry
 * @subpackage classifier
 */
class ChiSquaredFeatureSelection extends FeatureSelection
{
    /**
     * The maximum number of features to select, a runtime parameter.
     * @var int
     */
    var $max;
    /**
     * Uses the chi-squared feature selection algorithm to rank features by
     * informativeness, and return a map from old feature indices to new ones.
     *
     * @param object $features full feature set
     * @return array associative array mapping a subset of the original feature
     *  indices to new indices
     */
    function select(Features $features)
    {
        $n = $features->numFeatures();
        $selected = new SplMinHeap();
        $allowed = isset($this->max) ? min($this->max, $n) : $n;
        $labels = array(-1, 1);
        /*
           Start with 1, since 0 is dedicated to the constant intercept term;
           <= $n because n is the last feature.
         */
        for ($j = 1; $j <= $n; $j++) {
            $max_chi2 = 0.0;
            foreach ($labels as $label) {
                /*
                   t = term present
                   l = document has label
                   n = negation
                 */
                $stats = $features->varStats($j, $label);
                list($t_l, $t_nl, $nt_l, $nt_nl) = $stats;
                $num = ($t_l * $nt_nl) - ($t_nl * $nt_l);
                $den = ($t_l + $t_nl) * ($nt_l + $nt_nl);
                $chi2 = $den != 0 ? ($num * $num) / $den : INF;
                if ($chi2 > $max_chi2) {
                    $max_chi2 = $chi2;
                }
            }
            /*
               Keep track of top features in a heap, as we compute
               informativeness.
             */
            if ($allowed > 0) {
                $selected->insert(array($max_chi2, $j));
                $allowed -= 1;
            } else {
                list($other_chi2, $_) = $selected->top();
                if ($max_chi2 > $other_chi2) {
                    $selected->extract();
                    $selected->insert(array($max_chi2, $j));
                }
            }
        }
        return $this->buildMap($selected);
    }
}
?>
