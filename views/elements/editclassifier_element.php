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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * This element renders the initial edit page for a classifier, where the user
 * can update the classifier label and find documents to label and add to the
 * training set. The page displays some initial statistics and a form for
 * finding documents in any existing index, but after that it is heavily
 * modified by JavaScript in response to user actions and XmlHttpRequests
 * made to the server.
 *
 * @author Shawn Tice
 * @package seek_quarry
 * @subpackage element
 */
class EditclassifierElement extends Element
{
    /**
     * Draws the "edit classifier" element to the output buffers.
     *
     * @param array $data used to pass the class label, classifier instance,
     *  and list of existing crawls
     */
    function render($data)
    {
        $classifier = $data['classifier'];
    ?>
        <div class="current-activity">
        <div class="<?php e($data['leftorright']);?>">
        <a href="?c=admin&amp;a=manageClassifiers&amp;<?php
            e(CSRF_TOKEN.'='.$data[CSRF_TOKEN]) ?>"><?php
            e(tl('editclassifier_back')) ?></a>
        </div>
        <h2><?php e(tl('editclassifier_edit_classifier')) ?></h2>
        <form id="classifierForm" method="get" action="">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageClassifiers" />
        <input type="hidden" name="arg" value="editclassifier" />
        <input type="hidden" name="update" value="update" />
        <input type="hidden" name="class_label"
            value="<?php e($data['class_label']) ?>" />
        <div class="top-margin">
        <label for="rename-label"><?php
            e(tl('editclassifier_classifier_label')) ?></label>
            <input type="text" id="rename-label" name="rename_label"
                value="<?php e($data['class_label']) ?>"
                maxlength="80" class="wide-field"/>
            <button class="button-box" type="submit"><?php
                e(tl('editclassifier_change')); ?></button>
        </div>
        </form>
        <h3><?php e(tl('editclassifier_statistics')) ?></h3>
        <p><b><?php e(tl('editclassifier_positive_examples'))
            ?></b> <span id="positive-count"><?php
            e($classifier->positive) ?></span></p>
        <p><b><?php e(tl('editclassifier_negative_examples'))
            ?></b> <span id="negative-count"><?php
            e($classifier->negative) ?></span></p>
        <p><b><?php e(tl('editclassifier_accuracy'))
            ?></b> <span id="accuracy"><?php
            if (!is_null($classifier->accuracy)) {
                printf('%.1f%%', $classifier->accuracy * 100);
            } else {
                e(tl('crawl_component_na'));
            }?></span>
            [<a id="update-accuracy" href="#update-accuracy"
            <?php if ($classifier->total < 10) {
                e('class="disabled"');
            } ?>><?php e(tl('editclassifier_update')) ?></a>]</p>
        <h3><?php e(tl('editclassifier_add_examples')) ?></h3>
        <form id="label-docs-form" action="" method="GET">
        <?php
            $td = (MOBILE) ? "</tr><td>" : "<td>";
        ?>
        <table>
            <tr>
            <th><?php e(tl('editclassifier_source')) ?></th>
            <?php e($td); ?>
                <select id="label-docs-source" name="label_docs_source">
                    <option value="1" selected="selected"><?php
                        e(tl('editclassifier_default_crawl')) ?></option>
                <?php foreach ($data['CRAWLS'] as $crawl) { ?>
                    <option value="<?php e($crawl['CRAWL_TIME']) ?>"><?php
                        e($crawl['DESCRIPTION']) ?></option>
                <?php } ?>
                </select>
            </td>
            <?php e($td); ?>
                <select id="label-docs-type" name="label_docs_type">
                    <option value="manual" selected="selected"><?php
                        e(tl('editclassifier_label_by_hand')) ?></option>
                    <option value="positive"><?php
                        e(tl('editclassifier_all_in_class')) ?></option>
                    <option value="negative"><?php
                        e(tl('editclassifier_none_in_class')) ?></option>
                </select>
            </td>
            </tr>
            <tr>
                <th><?php e(tl('editclassifier_keywords')) ?></th><?php
                if(MOBILE) {
                    e("</tr><tr>");
                }?>
                <td <?php if(!MOBILE) {?>colspan="2" <?php } ?> >
                    <input type="text" maxlength="80" id="label-docs-keywords"
                        name="label_docs_keywords" />
                    <button class="button-box" type="submit"><?php
                        e(tl('editclassifier_load')) ?></button>
                    <button class="button-box back-dark-gray" type="button"
                        onclick="window.location='<?php
                        e("?c=admin&a=manageClassifiers&arg=finalizeclassifier".
                            "&".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                            "&class_label=".$data['class_label']); ?>'"><?php
                        e(tl('editclassifier_finalize')) ?></button>
                </td>
            </tr>
            <tr><?php
                if(!MOBILE) {
                    e("<th>&nbsp;</th>");
                }
                ?><td id="label-docs-status" colspan="2"><?php
                    e(tl('editclassifier_no_documents')) ?></td>
            </tr>
        </table>
        </form>
    <?php
    }
}
?>
