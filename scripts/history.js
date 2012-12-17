/** 
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
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
 * @author Akshat Kukreti
 * @package seek_quarry
 * @subpackage javascript
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

/*
 * Handles History toggle in cached pages
 */
var historylink = document.getElementById('#history');
historylink.onclick = function()
{
    var historylink = document.getElementById('#history');
    var m_id = document.getElementById('#month');
    var y_id = document.getElementById('#year');
    var cur_year = y_id.options[y_id.selectedIndex].value;
    var cur_month = m_id.options[m_id.selectedIndex].value;
    cur_div = document.getElementById('#'+cur_year+cur_month);
    m_id.options.length = 0;
    var monthjs = historylink.getAttribute("months");
    monthjs = eval(monthjs);
    for(j = 0; j < monthjs.length; j++) {
        var id = document.getElementById('#'+cur_year+monthjs[j]);
            if(id !== null) {
                var opt = document.createElement('option');
                opt.text = monthjs[j];
                m_id.add(opt, null);
            }
    }
    for(i = 0; i < m_id.length; i++) {
        if(m_id.options[i].value == cur_month) {
            m_id.options[i].defaultSelected = true;
        }
    }
    select = document.getElementById('#d1');
    if(select.style.display == 'none') {
        select.style.display = 'block';
    } else {
        select.style.display = 'none';
    }
    if(cur_div.style.display == 'none') {
        cur_div.style.display = 'block';
    }
    else {
        cur_div.style.display = 'none'
    }
}

/*
 * Handles Year selection in History UI
 */
var year = document.getElementById('#year');
year.onchange = function()
{
    var yearops = document.getElementById('#year');
    var monops = document.getElementById('#month');
    for(i = 0; i < yearops.length; i++) {
        for(j = 0; j<monops.length; j++) {
            var y = yearops[i].value;
            var m = monops[j].value;
            var id ='#'+y+m;
            var div = document.getElementById(id);
            if(div !== null){
                div.style.display = 'none';
            }
        }
    }
    var m_id = document.getElementById('#month');
    var y_id = document.getElementById('#year');
    document.getElementById('#month').options.length=0;
    var yearmonth = document.getElementById('#d1')
    var monthjs = yearmonth.getAttribute("months");
    monthjs = eval(monthjs);
    var yearjs = yearmonth.getAttribute("years");
    yearjs = eval(yearjs);
    var temp = y_id.options[y_id.selectedIndex].value;
    for(j = 0; j < monthjs.length; j++) {
        var id = document.getElementById('#'+temp+monthjs[j]);
        if(id !== null){
            var opt = document.createElement('option');
            opt.text = monthjs[j];
            m_id.add(opt, null);
        }
    }
    var month = m_id.options[m_id.selectedIndex].value;
    var year = y_id.options[y_id.selectedIndex].value;
    var id = '#'+year+month;
    ldiv = document.getElementById(id);
    if((ldiv !== null) && (ldiv.style.display == 'none')) {
        ldiv.style.display = 'block';
    }
}

/*
 * Handles Month selection in History UI
 */
var month = document.getElementById('#month');
month.onchange = function()
{
    var m_id = document.getElementById('#month');
    var y_id = document.getElementById('#year');
    for(i = 0; i < y_id.length; i++) {
        for(j = 0; j < m_id.length; j++) {
            var y = y_id[i].value;
            var m = m_id[j].value;
            var id = '#'+y+m;
            var div = document.getElementById(id);
            if(div!== null) {
                div.style.display = 'none';
            }
        }
    }
    var month = m_id.options[m_id.selectedIndex].value;
    var year = y_id.options[y_id.selectedIndex].value;
    var id = '#'+year+month;
    ldiv = document.getElementById(id);
    if((ldiv !== null) && (ldiv.style.display == 'none')) {
        ldiv.style.display = 'block';
    }
}
