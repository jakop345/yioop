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
 * This Element is responsible for drawing screens in the Admin View related
 * to localization. Namely, the ability to create, delete, and text writing mode
 * for locales as well as the ability to modify translations within a locale.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class ManagelocalesElement extends Element
{
    /**
     * Responsible for drawing the ceate, delete set writing mode screen for
     * locales as well ass the screen for adding modifying translations
     *
     * @param array $data  contains info about the available locales and what
     *     has been translated
     */
    function render($data)
    {
    ?>
        <div class="current-activity">
        <?php
        if($data['FORM_TYPE'] == "search") {
            $this->renderSearchForm($data);
        } else {
            $this->renderLocaleForm($data);
        }
        $data['TABLE_TITLE'] = tl('managelocales_element_locale_list');
        $data['NO_FLOAT_TABLE'] = true;
        $data['ACTIVITY'] = 'manageLocales';
        $data['VIEW'] = $this->view;
        $this->view->helper("pagingtable")->render($data);
        ?>
        <table class="locale-table">
            <tr>
            <th><?php e(tl('managelocales_element_localename')); ?></th>
            <?php
            if(!MOBILE) { ?>
                <th><?php e(tl('managelocales_element_localetag'));?></th>
                <th><?php e(tl('managelocales_element_writingmode'));
                    ?></th>
                <th><?php e(tl('managelocales_element_enabled'));
                    ?></th>
            <?php
            }
            ?>
            <th><?php e(tl('managelocales_element_percenttranslated'));?></th>
            <th colspan="2"><?php e(tl('managelocales_element_actions'));?></th>
            </tr>
        <?php
        $base_url = '?c=admin&amp;a=manageLocales&amp;'.CSRF_TOKEN."=".
            $data[CSRF_TOKEN];
        foreach($data['LOCALES'] as $locale) {
            e("<tr><td><a href='$base_url".
                "&amp;arg=editstrings&amp;selectlocale=".$locale['LOCALE_TAG'].
                "' >". $locale['LOCALE_NAME']."</a></td>");
            if(!MOBILE) {
                e("<td>".$locale['LOCALE_TAG']."</td>");
                e("<td>".$locale['WRITING_MODE']."</td>");
                $gr_class = ($locale['ACTIVE']) ? " class='green' " :
                    " class='red' ";
                e("<td $gr_class>".($locale['ACTIVE'] ?
                    tl('managelocales_element_true') :
                    tl('managelocales_element_false'))."</td>");
            }
            e("<td class='align-right' >".
                $locale['PERCENT_WITH_STRINGS']."</td>");
            e("<td><a href='$base_url"
                ."&amp;arg=editlocale&amp;selectlocale=".
                $locale['LOCALE_TAG']."' >"
                .tl('managelocales_element_edit')."</a></td>");
            e("<td><a href='$base_url"
                ."&amp;arg=deletelocale&amp;selectlocale=".
                $locale['LOCALE_TAG']."' >"
                .tl('managelocales_element_delete')."</a></td></tr>");
        }
        ?>
        </table>
        </div>
    <?php
    }
    /**
     * Draws the add locale and edit locale forms
     *
     * @param array $data consists of values of locale fields set
     *     so far as well as values of the drops downs on the form
     */
    function renderLocaleForm($data)
    {
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageLocales";
        $editlocale = ($data['FORM_TYPE'] == "editlocale") ? true: false;
        if($editlocale) {
            e("<div class='float-opposite'><a href='$base_url'>".
                tl('manageloecales_element_add_locale_form')."</a></div>");
            e("<h2>".tl('managelocales_element_locale_info'). "</h2>");
        } else {
            e("<h2>".tl('managelocales_element_add_locale'). "</h2>");
        }
        ?>
        <form id="addLocaleForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageLocales" />
        <input type="hidden" name="arg" value="<?php
            e($data['FORM_TYPE']);?>" />
        <?php
        if($editlocale) {
        ?>
            <input type="hidden" name="selectlocale" value="<?php
                e($data['CURRENT_LOCALE']['localetag']);?>" /><?php
        }
        ?>
        <table class="name-table">
            <tr><th><label for="locale-name"><?php
                e(tl('managelocales_element_localenamelabel'))?></label></th>
                <td><input type="text" id="locale-name"
                    name="localename" maxlength="<?php e(LONG_NAME_LEN);
                    ?>" class="narrow-field"
                    value="<?php e($data['CURRENT_LOCALE']['localename']); ?>"
                    <?php
                    if($editlocale) {
                        e(' disabled="disabled" ');
                    }
                    ?> />
                </td><td></td>
            </tr>
            <tr><th><label for="locale-tag"><?php
                e(tl('managelocales_element_localetaglabel'))?></label></th>
                <td><input type="text" id="locale-tag"
                name="localetag"  maxlength="<?php e(NAME_LEN); ?>"
                value="<?php e($data['CURRENT_LOCALE']['localetag']); ?>"
                class="narrow-field"/></td>
            </tr>
            <tr><th><?php e(tl('managelocales_element_writingmodelabel'))?></th>
            <td><?php $this->view->helper("options")->render(
                        "writing-mode", "writingmode",
                        $data['WRITING_MODES'],
                        $data['CURRENT_LOCALE']['writingmode']); ?>
            </td>
            </tr>
            <tr><th><label for="locale-active"><?php
                e(tl('managelocales_element_localeenabled'))?></label></th>
            <td><input type="checkbox" id="locale-active"
                    name="active" value="1" <?php
                    if($data['CURRENT_LOCALE']['active'] > 0) {
                        e("checked='checked'");
                    }
                    ?> />
            </td>
            </tr>
            <tr><td></td><td class="center"><button class="button-box"
                type="submit" name="update" value="true"><?php
                e(tl('managelocales_element_submit'));
                ?></button></td>
            </tr>
        </table>
        </form>
        <?php
    }
    /**
     * Draws the search for locales forms
     *
     * @param array $data consists of values of locale fields set
     *     so far as well as values of the drops downs on the form
     */
    function renderSearchForm($data)
    {
        $controller = "admin";
        $activity = "manageLocales";
        $view = $this->view;
        $title = tl('managelocales_element_search_locales');
        $return_form_name = tl('managelocales_element_addlocale_form');
        $fields = array(
            tl('managelocales_element_localename') => "name",
            tl('managelocales_element_localetag') => "tag",
            tl('managelocales_element_writingmode') => "mode",
            tl('managelocales_element_enabled') =>
                array("active", $data['EQUAL_COMPARISON_TYPES'])
        );
        $dropdowns = array(
            "mode" => array("lr-tb" => "lr-rb", "rl-tb" => "rl-tb",
                "tb-rl" => "tb-rl", "tb-lr" => "tb-lr"),
            "active" => array("1" => tl('managelocales_element_true'),
                "0" => tl('managelocales_element_false'))
        );
        $view->helper("searchform")->render($data, $controller, $activity,
            $view, $title, $return_form_name, $fields, $dropdowns);
    }
}
?>