<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * This element renders the page that lists classifiers, provides a form to
 * create new ones, and provides per-classifier action links to edit, finalize,
 * and delete the associated classifier.
 *
 * @author Shawn Tice
 * @package seek_quarry
 * @subpackage element
 */
class ManageclassifiersElement extends Element
{
    /**
     * Draws the "new classifier" form and table of existing classifiesr
     *
     * @param array $data used to pass the list of existing classifier
     * instances
     */
    function render($data)
    {
        ?>
        <div class="current-activity">
        <?php
        if($data['FORM_TYPE'] == "search") {
            $this->renderSearchForm($data);
        } else {
            $this->renderClassifierForm($data);
        }
        $base_url = "?c=admin&amp;a=manageClassifiers&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN]."&amp;arg=";
        if (!empty($data['classifiers'])) {
            $data['TABLE_TITLE'] = 
                tl('manageclassifiers_available_classifiers');
            $data['ACTIVITY'] = 'manageClassifiers';
            $data['VIEW'] = $this->view;
            $data['NO_FLOAT_TABLE'] = true;
            $this->view->helper("pagingtable")->render($data);
            ?>
            <table class="classifiers-table">
                <tr>
                    <th><?php e(tl('manageclassifiers_label_col')) ?></th>
                    <?php
                    if(!MOBILE) { ?>
                        <th><?php e(tl('manageclassifiers_positive_col'));
                         ?></th>
                        <th><?php e(tl('manageclassifiers_negative_col'));
                        ?></th>
                    <?php
                    }
                    ?>
                    <th colspan="3"><?php
                        e(tl('manageclassifiers_actions_col')) ?></th>
                </tr>
                <?php
                foreach ($data['classifiers'] as $label => $classifier) { ?>
                <tr>
                    <td><b><?php e($label) ?></b><br />
                        <small><?php e(date("d M Y H:i:s",
                            $classifier->timestamp)) ?></small>
                    </td>
                    <?php
                    if(!MOBILE) { ?>
                        <td><?php e($classifier->positive) ?></td>
                        <td><?php e($classifier->negative) ?></td>
                    <?php
                    }
                    ?>
                    <td><a href="<?php e($base_url)
                        ?>editclassifier&amp;name=<?php
                        e($label) ?>"><?php
                            e(tl('manageclassifiers_edit')) ?></a></td>
                    <td><?php
                    if ($classifier->finalized == Classifier::FINALIZED) {
                        e(tl('manageclassifiers_finalized'));
                    } else if ($classifier->finalized == 
                        Classifier::UNFINALIZED) {
                        if ($classifier->total > 0) {
                            ?><a href="<?php e($base_url)
                            ?>finalizeclassifier&amp;name=<?php
                            e($label) ?>"><?php
                                e(tl('manageclassifiers_finalize')) ?></a><?php
                        } else {
                            e(tl('manageclassifiers_finalize'));
                        }
                    } else if($classifier->finalized == Classifier::FINALIZING){
                        e("<span class='red'>".
                            tl('manageclassifiers_finalizing')."</span>");
                    }
                    ?></td>
                    <td><a href="<?php e($base_url)
                        ?>deleteclassifier&amp;name=<?php
                        e($label) ?>"><?php
                            e(tl('manageclassifiers_delete')) ?></a></td>
                </tr>
            <?php } // end foreach over classifiers ?>
            </table>
            <?php
            } // endif for available classifiers 
            ?>
        </div>
        <?php
        if($data['reload']) { 
            ?>
            <script type="text/javascript">
            var sec = 1000;
            function classifierUpdate()
            {
                window.location = "?c=admin&<?php
                    e(CSRF_TOKEN."=".$data[CSRF_TOKEN]);
                    ?>&a=manageClassifiers";
            }
            setTimeout(classifierUpdate, 5 * sec);
            </script>
            <?php
        }
    }
    /**
     * Used to draw the form to create a new classifier
     *
     * @param array $data data for the view in this case we just make
     *     use of the CSRF_TOKEN
     */
     function renderClassifierForm($data)
     {
        ?>
        <h2><?php e(tl('manageclassifiers_manage_classifiers')) ?></h2>
        <form id="classifiersForm" method="get" action=''>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageClassifiers" />
        <input type="hidden" name="arg" value="createclassifier" />
        <div class="top-margin"><label for="class-label"><?php
            e(tl('manageclassifiers_classifier_name')) ?></label>:
            <input type="text" id="class-label" name="name"
                value="" maxlength="<?php e(NAME_LEN)?>"
                    class="wide-field"/>
            <button class="button-box"  type="submit"><?php
                e(tl('manageclassifiers_create_button')) ?></button>
        </div>
        </form>
        <?php
     }
    /**
     * Used to draw the form to search and filter through existing classifiers
     *
     * @param array $data data for the view
     */
     function renderSearchForm($data)
     {
        $controller = "admin";
        $activity = "manageClassifiers";
        $view = $this->view;
        $title = tl('manageclassifiers_element_search');
        $return_form_name = tl('manageclassifiers_element_create_form');
        $fields = array(
            tl('manageclassifiers_classifier_name') => "name",
        );
        $view->helper("searchform")->render($data, $controller, $activity,
                $view, $title, $return_form_name, $fields);
     }
}
?>
