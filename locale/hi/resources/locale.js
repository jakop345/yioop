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
 * @author Mallika Perepa
 * @package seek_quarry
 * @subpackage javascript
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014s
 * @filesource
 */

/*
 * The alphabet for this locale
 */
var alpha = "अआइईउऊऋएऐओऔअंअँकखगघङचछजझञटठडढणतथदधनपफबभमयरलवशषसहर्हर्सर्मर्नर्जर्दर्टर्"+
"तब्रह्रग्रद्रज्रड्रप्रर्रक्रत्रच्रट्रन्रन्रव्रल्रस्रय्रब्नह्नग्नद्नप्नर्नक्नत्नम्नव्"+
"नस्नक्कट्टठ्ठत्तन्नड्डद्दटमट्मसससकस्सस्कक्षत्रज्ञश्रऋािीोौुूेैंकाकिकीकुकूकेकैकोकौकंकः"+
"खखाखिखीखुखूखेखैखोखौखंखःगगागिगीगुगूगेगैगोगौगंगःघघाघिघीघुघूघेघैघोघौघंघःचचाचिचीचुचूच"+
"ेचैचोचौचंचःछछाछिछीछुछूछेछैछोछौछंछःजाजिजीजुजूजेजैजोजौजंजःझाझिझीझुझूझेझैझोझौझंझःटाटिटी"+
"टुटूटेटैटोटौटंटःठाठिठीठुठूठेठैठोठौठंठःडाडिडीडुडूडेडैडोडौडंडःढाढिढीढुढूढेढैढोढौढंढःणाण"+
"िणीणुणूणेणैणोणौणंणःतातितीतुतूतेतैतोतौतंतःथाथिथीथुथूथेथैथोथौथंथःदादिदीदुदूदेदैदोदौदंदः"+
"धाधिधीधुधूधेधैधोधौधंधःनानिनीनुनूनेनैनोनौनंनःपापिपीपुपूपेपैपोपौपंपःफाफिफीफुफूफेफैफोफौफंफः"+
"बाबिबीबुबूबेबैबोबौबंबःभाभिभीभुभूभेभैभोभौभंभःमामिमीमुमूमेमैमोमौमंमःयायियीयुयूयेयैयोयौयंयः"+
"रारिरीरुरूरेरैरोरौरंरःलालिलीलुलूलेलैलोलौलंलःवाविवीवुवूवेवैवोवौवंवःक्षाक्षिक्षीक्षुक्षूक्षेक्ष"+
"ैक्षोक्षौक्षंक्षःज्ञाज्ञिज्ञीज्ञुज्ञूज्ञेज्ञैज्ञोज्ञौज्ञंज्ञःस्वस्नस्थस्तस्टस्जस्कष्रव्रल्मल्द"+
"र्सर्यर्तर्चम्हम्बभ्रब्रब्बफ्रफ़्तप्रन्सन्रन्तध्रद्वद्रद्धथ्रत्नण्रण्डण्टढ्रड्रठ्रट्रट्टच्छच्च"+
"ग्यग्रग्ञख्यख्मक्यक्लक्सक्रश्वक्षज्ञत्तत्रद्मद्यन्नश्चश्र";

/*
 * Transliteration maping for this locale
 */
var roman_array = {};

/*
 * To analyze the query and generate actual input query from the
 * transliterated query
 */
function analyzeQuery()
{
}