<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version details.
 *
 * @package    local_hidehiddencategories
 * @author     Jakob Heinemann <jakob@jakobheinemann.de>
 * @copyright  Jakob Heinemann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_hidehiddencategories_install(){
    global $DB;

    require_once("_services.php");

    foreach($functions as $name=>$params){
        $record = $DB->get_record('external_functions', array('name'=>$name), '*', MUST_EXIST);
        $DB->insert_record('hidehiddencategories_backup',$record);
        $DB->delete_records('external_functions', array('name'=>$name));
        $newrecord = $params;
        $newrecord["name"] = $name;
        if(isset($params["services"]) and is_array($params["services"])){
            $newrecord["services"] = implode(',', $params['services']);
        }
        $DB->insert_record('external_functions',$newrecord);
    }
}