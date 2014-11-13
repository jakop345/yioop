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
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 *
 * Draws the view on which people can control
 * their search settings such as num links per screen
 * and the language settings
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */
class SettingsView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    var $layout = "web";
    /**
     * sDraws the web page on which users can control their search settings.
     *
     * @param array $data   contains anti CSRF token as well
     *     the language info and the current and possible per page settings
     */
    function renderView($data) {
    $logo = LOGO;
    $logged_in = isset($data['ADMIN']) && $data['ADMIN'];
    if(MOBILE) {
        $logo = M_LOGO;
    }
?>
<div class="landing non-search">
<h1 class="logo"><a href="./?<?php if($logged_in) {
        e(CSRF_TOKEN."=".$data[CSRF_TOKEN]. "&amp;");
    } ?>its=<?php
    e($data['its'])?>"><img
    src="<?php e($logo); ?>" alt="<?php e($this->logo_alt_text);
        ?>" /></a><span> - <?php
    e(tl('settings_view_settings')); ?></span>
</h1>
<div class="settings">
<form method="get">
<table>

<tr>
<td class="table-label"><label for="per-page"><b><?php
    e(tl('settings_view_results_per_page')); ?></b></label></td><td
    class="table-input"><?php $this->helper("options")->render(
    "per-page", "perpage", $data['PER_PAGE'], $data['PER_PAGE_SELECTED']); ?>
</td></tr>
<tr>
<td class="table-label"><label for="open-in-tabs"><b><?php
    e(tl('settings_view_open_in_tabs')); ?></b></label></td><td
    class="table-input"><input type="checkbox" id="open-in-tabs"
        name="open_in_tabs" value="true"
        <?php  if($data['OPEN_IN_TABS']) {?>checked='checked'<?php } ?> />
</td></tr>
<tr>
<td class="table-label"><label for="index-ts"><b><?php
    e(tl('settings_view_search_index')); ?></b></label></td><td
    class="table-input"><?php $this->helper("options")->render(
    "index-ts", "index_ts", $data['CRAWLS'], $data['its']); ?>
</td></tr>
<?php if(count($data['LANGUAGES']) > 1) { ?>
<tr><td class="table-label"><label for="locale"><b><?php
    e(tl('settings_view_language_label')); ?></b></label></td><td
    class="table-input"><?php $this->element("language")->render($data); ?>
</td></tr>
<?php } ?>
<tr><td class="cancel"><input type="hidden" name="<?php
    e(CSRF_TOKEN); ?>" value="<?php
    e($data[CSRF_TOKEN]); ?>" /><input type="hidden"
    name="its" value="<?php e($data['its']); ?>" /><button
    class="top-margin" name="c" value="search" <?php
        if(isset($data['RETURN'])) {
            e(' onclick="javascript:window.location.href='."'".
            $data['RETURN']."'".';return false;"');
        } ?>><?php e(tl('settings_view_return_yioop'));
    ?></button></td><td class="table-input">
<button class="top-margin" type="submit" name="c" value="settings"><?php
    e(tl('settings_view_save')); ?></button>
</td></tr>
</table>
</form>
</div>
<div class="setting-footer"><a
    href="javascript:window.external.AddSearchProvider('<?php
    e(SEARCHBAR_PATH);?>')"><?php
    e(tl('settings_install_search_plugin'));
?></a>.</div>
</div>
<div class='landing-spacer'></div>
<?php
    }
}
?>
