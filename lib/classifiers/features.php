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
/**
 * Manages a dataset's features, providing a standard interface for converting
 * documents to feature vectors, and for accessing feature statistics.
 *
 * Each document in the training set is expected to be fed through an instance
 * of a subclass of this abstract class in order to convert it to a feature
 * vector. Terms are replaced with feature indices (e.g., 'Pythagorean' => 1,
 * 'theorem' => 2, and so on), which are contiguous. The value at a feature
 * index is determined by the subclass; one might weight terms according to how
 * often they occur in the document, while another might use a simple binary
 * representation. The feature index 0 is reserved for an intercept term, which
 * always has a value of one.
 *
 * @author Shawn Tice
 * @package seek_quarry
 * @subpackage classifier
 */
abstract class Features
{
    /**
     * Maps terms to their feature indices, which start at 1.
     * @var array
     */
    var $vocab = array();
    /**
     * Maps terms to how often they occur in documents by label.
     * @var array
     */
    var $var_freqs = array();
    /**
     * Maps labels to the number of documents they're assigned to.
     * @var array
     */
    var $label_freqs = array(-1 => 0, 1 => 0);
    /**
     * Maps old feature indices to new ones when a feature subset operation has
     * been applied to restrict the number of features.
     * @var array
     */
    var $feature_map;
    /**
     * A list of the top terms according to the last feature subset operation,
     * if any.
     * @var array
     */
    var $top_terms = array();
    /**
     * Maps a new example to a feature vector, adding any new terms to the
     * vocabulary, and updating term and label statistics. The example should
     * be an array of terms and their counts, and the output simply replaces
     * terms with feature indices.
     *
     * @param array $terms array of terms mapped to the number of times they
     *  occur in the example
     * @param int $label label for this example, either -1 or 1
     * @return array input example with terms replaced by feature indices
     */
    function addExample($terms, $label)
    {
        $this->label_freqs[$label]++;
        $features = array();
        foreach ($terms as $term => $count) {
            if (isset($this->vocab[$term])) {
                $j = $this->vocab[$term];
            } else {
                // Var indices start at 1 to accommodate the intercept at 0.
                $j = count($this->vocab) + 1;
                $this->vocab[$term] = $j;
            }
            $features[$j] = $count;
            // Update term statistics
            if (!isset($this->var_freqs[$j][$label])) {
                $this->var_freqs[$j][$label] = 1;
            } else {
                $this->var_freqs[$j][$label]++;
            }
        }
        // Feature 0 is an intercept term
        $features[0] = 1;
        ksort($features);
        return $features;
    }
    /**
     * Updates the label and term statistics to reflect a label change for an
     * example from the training set. A new label of 0 indicates that the
     * example is being removed entirely. Note that term statistics only count
     * one occurrence of a term per example.
     *
     * @param array $features feature vector from when the example was
     *  originally added
     * @param int $old_label old example label in {-1, 1}
     * @param int $new_label new example label in {-1, 0, 1}, where 0 indicates
     *  that the example should be removed entirely
     */
    function updateExampleLabel($features, $old_label, $new_label)
    {
        $this->label_freqs[$old_label]--;
        if ($new_label != 0) {
            $this->label_freqs[$new_label]++;
        }
        // Remove the intercept term first.
        unset($features[0]);
        foreach (array_keys($features) as $j) {
            $this->var_freqs[$j][$old_label]--;
            if ($new_label != 0) {
                $this->var_freqs[$j][$new_label]++;
            }
        }
    }
    /**
     * Returns the number of features, not including the intercept term
     * represented by feature zero. For example, if we had features 0..10,
     * this function would return 10.
     *
     * @return int the number of features in the training set
     */
    function numFeatures()
    {
        return count($this->vocab);
    }
    /**
     * Returns the positive and negative label counts for the training set.
     *
     * @return array positive and negative label counts indexed by label,
     *  either 1 or -1
     */
    function labelStats()
    {
        return array($this->label_freqs[1], $this->label_freqs[-1]);
    }
    /**
     * Returns the statistics for a particular feature and label in the
     * training set. The statistics are counts of how often the term appears or
     * fails to appear in examples with or without the target label. They are
     * returned in a flat array, in the following order:
     *
     *     0 => # examples where feature present, label matches
     *     1 => # examples where feature present, label doesn't match
     *     2 => # examples where feature absent, label matches
     *     3 => # examples where feature absent, label doesn't match
     *
     * @param int $j feature index
     * @param int $label target label
     * @return array feature statistics in 4-element flat array
     */
    function varStats($j, $label)
    {
        $tl = isset($this->var_freqs[$j][$label]) ?
            $this->var_freqs[$j][$label] : 0;
        $t  = array_sum($this->var_freqs[$j]);
        $l  = $this->label_freqs[$label];
        $N  = array_sum($this->label_freqs);
        return array(
            $tl,               //  t and  l
            $t - $tl,          //  t and ~l
            $l - $tl,          // ~t and  l
            $N - $t - $l + $tl // ~t and ~l
        );
    }
    /**
     * Given a FeatureSelection instance, return a new clone of this Features
     * instance using a restricted feature subset. The new Features instance
     * is augmented with a feature map that it can use to convert feature
     * indices from the larger feature set to indices for the reduced set.
     *
     * @param object $fs FeatureSelection instance to be used to select the
     *  most informative terms
     * @return object new Features instance using the restricted feature set
     */
    function restrict(FeatureSelection $fs)
    {
        $feature_map = $fs->select($this);
        /*
           Collect the top few most-informative features (if any). The features
           are inserted into the feature map by decreasing informativeness, so
           iterating through from the beginning will yield the most informative
           features first, excepting the very first one, which is guaranteed to
           be the intercept term.
         */
        $top_features = array();
        next($feature_map);
        for ($i = 0; $i < 5; $i++) {
            if (!(list($j) = each($feature_map))) {
                break;
            }
            $top_features[$j] = true;
        }
        $classname = get_class($this);
        $new_features = new $classname;
        foreach ($this->vocab as $term => $old_j) {
            if (isset($feature_map[$old_j])) {
                $new_j = $feature_map[$old_j];
                $new_features->vocab[$term] = $new_j;
                $new_features->var_freqs[$new_j] = $this->var_freqs[$old_j];
                // Get the actual term associated with a top feature.
                if (isset($top_features[$old_j])) {
                    $top_features[$old_j] = $term;
                }
            }
        }
        $new_features->label_freqs = $this->label_freqs;
        $new_features->feature_map = $feature_map;
        // Note that this preserves the order of top features.
        $new_features->top_terms = array_values($top_features);
        return $new_features;
    }
    /**
     * Maps the indices of a feature vector to those used by a restricted
     * feature set, dropping and features that aren't in the map. If this
     * Features instance isn't restricted, then the passed-in features are
     * returned unmodified.
     *
     * @param array $features feature vector mapping feature indices to
     *  frequencies
     * @return array original feature vector with indices mapped
     *  according to the feature_map property, and any features that don't
     *  occcur in feature_map dropped
     */
    function mapToRestrictedFeatures($features)
    {
        if (empty($this->feature_map)) {
            return $features;
        }
        $mapped_features = array();
        foreach ($features as $j => $count) {
            if (isset($this->feature_map[$j])) {
                $mapped_features[$this->feature_map[$j]] = $count;
            }
        }
        return $mapped_features;
    }
    /**
     * Given an array of feature vectors mapping feature indices to counts,
     * returns a sparse matrix representing the dataset transformed according
     * to the specific Features subclass. A Features subclass might use simple
     * binary features, but it might also use some form of TF * IDF, which
     * requires the full dataset in order to assign weights to particular
     * document features; thus the necessity of a map over the entire training
     * set prior to its input to a classification algorithm.
     *
     * @param array $docs array of training examples represented as feature
     *  vectors where the values are per-example counts
     * @return object SparseMatrix instance whose rows are the transformed
     *  feature vectors
     */
    abstract function mapTrainingSet($docs);
    /**
     * Maps a vector of terms mapped to their counts within a single document
     * to a transformed feature vector, exactly like a row in the sparse matrix
     * returned by mapTrainingSet. This method is used to transform a tokenized
     * document prior to classification.
     *
     * @param array $tokens associative array of terms mapped to their
     *  within-document counts
     * @return array feature vector corresponding to the tokens, mapped
     *  according to the implementation of a particular Features subclass
     */
    abstract function mapDocument($tokens);
}
/**
 * A concrete Features subclass that represents a document as a binary
 * vector where a one indicates that a feature is present in the document, and
 * a zero indicates that it is not. The absent features are ignored, so the
 * binary vector is actually sparse, containing only those feature indices
 * where the value is one.
 *
 * @author Shawn Tice
 * @package seek_quarry
 * @subpackage classifier
 */
class BinaryFeatures extends Features
{
    /**
     * Replaces term counts with 1, indicating only that a feature occurs in a
     * document.  When a Features instance is a subset of a larger instance, it
     * will have a feature_map member that maps feature indices from the larger
     * feature set to the smaller one. The indices must be mapped in this way
     * so that the training set can retain complete information, only throwing
     * away features just before training. See the abstract parent class for a
     * more thorough introduction to the interface.
     *
     * @param array $docs array of training examples represented as feature
     *  vectors where the values are per-example counts
     * @return object SparseMatrix instance whose rows are the transformed
     *  feature vectors
     */
    function mapTrainingSet($docs)
    {
        $m = count($docs);
        $n = count($this->vocab) + 1;
        $X = new SparseMatrix($m, $n);

        $i = 0;
        foreach ($docs as $features) {
            /*
               If this is a restricted feature set, map from the expanded
               feature set first, potentially dropping features.
             */
            $features = $this->mapToRestrictedFeatures($features);
            $new_features = array_combine(
                array_keys($features),
                array_fill(0, count($features), 1));
            $X->setRow($i++, $new_features);
        }
        return $X;
    }
    /**
     * Converts a map from terms to  within-document term counts with the
     * corresponding sparse binary feature vector used for classification.
     *
     * @param array $tokens associative array of terms mapped to their
     *  within-document counts
     * @return array feature vector corresponding to the tokens, mapped
     *  according to the implementation of a particular Features subclass
     */
    function mapDocument($tokens)
    {
        $x = array();
        foreach ($tokens as $token => $count) {
            if (isset($this->vocab[$token])) {
                $x[$this->vocab[$token]] = 1;
            }
        }
        $x[0] = 1;
        ksort($x);
        return $x;
    }
}
/**
 * A concrete Features subclass that represents a document as a
 * vector of feature weights, where weights are computed using a modified form
 * of TF * IDF. This feature mapping is experimental, and may not work
 * correctly.
 *
 * @author Shawn Tice
 * @package seek_quarry
 * @subpackage classifier
 */
class WeightedFeatures extends Features
{
    var $D = 0;
    var $n = array();

    function mapTrainingSet($docs)
    {
        $m = count($this->examples);
        $n = count($this->vocab);
        $this->D = $m;
        $this->n = array();
        // Fill in $n, the count of documents that contain each term
        foreach ($this->examples as $features) {
            foreach (array_keys($features) as $j) {
                if (!isset($this->n[$j]))
                    $this->n[$j] = 1;
                else
                    $this->n[$j] += 1;
            }
        }
        $X = new SparseMatrix($m, $n);
        $y = $this->exampleLabels;
        foreach ($this->examples as $i => $features) {
            $u = array();
            $sum = 0;
            // First compute the unnormalized TF * IDF term weights and keep
            // track of the sum of all weights in the document.
            foreach ($features as $j => $count) {
                $tf = 1 + log($count);
                $idf = log(($this->D + 1) / ($this->n[$j] + 1));
                $weight = $tf * $idf;
                $u[$j] = $weight;
                $sum += $weight * $weight;
            }
            // Now normalize each of the term weights.
            $norm = sqrt($sum);
            foreach (array_keys($features) as $j) {
                $features[$j] = $u[$j] / $norm;
            }
            $X->setRow($i, $features);
        }
        return array($X, $y);
    }
    function mapDocument($tokens)
    {
        $u = array();
        $sum = 0;
        ksort($this->current);

        foreach ($this->current as $j => $count) {
            $tf = 1 + log($count);
            $idf = log(($this->D + 1) / ($this->n[$j] + 1));
            $weight = $tf * $idf;
            $u[$j] = $weight;
            $sum += $weight * $weight;
        }
        $norm = sqrt($sum);
        $x = array();
        foreach (array_keys($this->current) as $j) {
            $x[$j] = $u[$j] / $norm;
        }
        $this->current = array();
        return $x;
    }
}
/**
 * A sparse matrix implementation based on an associative array of associative
 * arrays.
 *
 * A SparseMatrix is mostly a wrapper around an array of arrays, but it keeps
 * track of some extra information such as the true matrix dimensions, and the
 * number of non-zero entries. It also provides a convenience method for
 * partitioning the matrix rows into two new sparse matrices.
 *
 * @author Shawn Tice
 * @package seek_quarry
 * @subpackage classifier
 */
class SparseMatrix implements Iterator //Iterator is built-in to PHP
{
    /**
     * The number of rows, regardless of whether or not some are empty.
     * @var int
     */
    var $m;
    /**
     * The number of columns, regardless of whether or not some are empty.
     * @var int
     */
    var $n;
    /**
     * The number of non-zero entries.
     * @var int
     */
    var $nonzero = 0;
    /**
     * The actual matrix data, an associative array mapping row indices to
     * associative arrays mapping column indices to their values.
     * @var array
     */
    var $data;
    /**
     * Initializes a new sparse matrix with specific dimensions.
     *
     * @param int $m number of rows
     * @param int $n number of columns
     */
    function __construct($m, $n)
    {
        $this->m = $m;
        $this->n = $n;
        $this->data = array();
    }
    /**
     * Accessor method which the number of rows in the matrix
     * @return number of rows
     */
    function rows() 
    {
        return $this->m;
    }
    /**
     * Accessor method which the number of columns in the matrix
     * @return number of columns
     */
    function columns() 
    {
        return $this->n; 
    }
    /**
     * Accessor method which the number of nonzero entries in the matrix
     * @return number of nonzero entries
     */
    function nonzero()
    {
        return $this->nonzero;
    }
    /**
     * Sets a particular row of data, keeping track of any new non-zero
     * entries.
     *
     * @param int $i row index
     * @param array $row associative array mapping column indices to values
     */
    function setRow($i, $row)
    {
        $this->data[$i] = $row;
        $this->nonzero += count($row);
    }
    /**
     * Given two sets of row indices, returns two new sparse matrices
     * consisting of the corresponding rows.
     *
     * @param array $a_indices row indices for first new sparse matrix
     * @param array $b_indices row indices for second new sparse matrix
     * @return array array with two entries corresponding to the first and
     *  second new matrices
     */
    function partition($a_indices, $b_indices)
    {
        $a = new SparseMatrix(count($a_indices), $this->n);
        $b = new SparseMatrix(count($b_indices), $this->n);
        $new_i = 0;
        foreach ($a_indices as $i) {
            $a->setRow($new_i++, $this->data[$i]);
        }
        $new_i = 0;
        foreach ($b_indices as $i) {
            $b->setRow($new_i++, $this->data[$i]);
        }
        return array($a, $b);
    }
    /* Iterator Interface */
    function rewind() { reset($this->data); }
    function current() { return current($this->data); }
    function key() { return key($this->data); }
    function next() { return next($this->data); }
    function valid() { return !is_null(key($this->data)); }
}
?>
