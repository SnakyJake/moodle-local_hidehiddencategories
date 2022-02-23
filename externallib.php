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
 * External course API
 *
 * @package    local_hidehiddencategories based on core_course
 * @category   external
 * @copyright  2020 Jakob Heinemann, 2009 Petr Skodak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

use core_course\external\course_summary_exporter;

require_once("$CFG->libdir/externallib.php");
require_once("lib.php");

/**
 * local_hidehiddencategories external functions
 *
 * @package    local_hidehiddencategories based on core_course
 * @category   external
 * @copyright  2020 Jakob Heinemann, 2011 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.2
 */
class local_hidehiddencategories_core_course_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.3
     */
    public static function get_categories_parameters() {
        return new external_function_parameters(
            array(
                'criteria' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'key' => new external_value(PARAM_ALPHA,
                                         'The category column to search, expected keys (value format) are:'.
                                         '"id" (int) the category id,'.
                                         '"ids" (string) category ids separated by commas,'.
                                         '"name" (string) the category name,'.
                                         '"parent" (int) the parent category id,'.
                                         '"idnumber" (string) category idnumber'.
                                         ' - user must have \'moodle/category:manage\' to search on idnumber,'.
                                         '"visible" (int) whether the returned categories must be visible or hidden. If the key is not passed,
                                             then the function return all categories that the user can see.'.
                                         ' - user must have \'moodle/category:manage\' or \'moodle/category:viewhiddencategories\' to search on visible,'.
                                         '"theme" (string) only return the categories having this theme'.
                                         ' - user must have \'moodle/category:manage\' to search on theme'),
                            'value' => new external_value(PARAM_RAW, 'the value to match')
                        )
                    ), 'criteria', VALUE_DEFAULT, array()
                ),
                'addsubcategories' => new external_value(PARAM_BOOL, 'return the sub categories infos
                                          (1 - default) otherwise only the category info (0)', VALUE_DEFAULT, 1)
            )
        );
    }

    /**
     * Get categories
     *
     * @param array $criteria Criteria to match the results
     * @param booln $addsubcategories obtain only the category (false) or its subcategories (true - default)
     * @return array list of categories
     * @since Moodle 2.3
     */
    public static function get_categories($criteria = array(), $addsubcategories = true) {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/lib.php");

        // Validate parameters.
        $params = self::validate_parameters(self::get_categories_parameters(),
                array('criteria' => $criteria, 'addsubcategories' => $addsubcategories));

        // Retrieve the categories.
        $categories = array();
        if (!empty($params['criteria'])) {

            $conditions = array();
            $wheres = array();
            foreach ($params['criteria'] as $crit) {
                $key = trim($crit['key']);

                // Trying to avoid duplicate keys.
                if (!isset($conditions[$key])) {

                    $context = context_system::instance();
                    $value = null;
                    switch ($key) {
                        case 'id':
                            $value = clean_param($crit['value'], PARAM_INT);
                            $conditions[$key] = $value;
                            $wheres[] = $key . " = :" . $key;
                            break;

                        case 'ids':
                            $value = clean_param($crit['value'], PARAM_SEQUENCE);
                            $ids = explode(',', $value);
                            list($sqlids, $paramids) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                            $conditions = array_merge($conditions, $paramids);
                            $wheres[] = 'id ' . $sqlids;
                            break;

                        case 'idnumber':
                            if (has_capability('moodle/category:manage', $context)) {
                                $value = clean_param($crit['value'], PARAM_RAW);
                                $conditions[$key] = $value;
                                $wheres[] = $key . " = :" . $key;
                            } else {
                                // We must throw an exception.
                                // Otherwise the dev client would think no idnumber exists.
                                throw new moodle_exception('criteriaerror',
                                        'webservice', '', null,
                                        'You don\'t have the permissions to search on the "idnumber" field.');
                            }
                            break;

                        case 'name':
                            $value = clean_param($crit['value'], PARAM_TEXT);
                            $conditions[$key] = $value;
                            $wheres[] = $key . " = :" . $key;
                            break;

                        case 'parent':
                            $value = clean_param($crit['value'], PARAM_INT);
                            $conditions[$key] = $value;
                            $wheres[] = $key . " = :" . $key;
                            break;

                        case 'visible':
                            if (has_capability('moodle/category:viewhiddencategories', $context)) {
                                $value = clean_param($crit['value'], PARAM_INT);
                                $conditions[$key] = $value;
                                $wheres[] = $key . " = :" . $key;
                            } else {
                                throw new moodle_exception('criteriaerror',
                                        'webservice', '', null,
                                        'You don\'t have the permissions to search on the "visible" field.');
                            }
                            break;

                        case 'theme':
                            if (has_capability('moodle/category:manage', $context)) {
                                $value = clean_param($crit['value'], PARAM_THEME);
                                $conditions[$key] = $value;
                                $wheres[] = $key . " = :" . $key;
                            } else {
                                throw new moodle_exception('criteriaerror',
                                        'webservice', '', null,
                                        'You don\'t have the permissions to search on the "theme" field.');
                            }
                            break;

                        default:
                            throw new moodle_exception('criteriaerror',
                                    'webservice', '', null,
                                    'You can not search on this criteria: ' . $key);
                    }
                }
            }

            if (!empty($wheres)) {
                $wheres = implode(" AND ", $wheres);

                $categories = $DB->get_records_select('course_categories', $wheres, $conditions);

                // Retrieve its sub subcategories (all levels).
                if ($categories and !empty($params['addsubcategories'])) {
                    $newcategories = array();

                    // Check if we required visible/theme checks.
                    $additionalselect = '';
                    $additionalparams = array();
                    if (isset($conditions['visible'])) {
                        $additionalselect .= ' AND visible = :visible';
                        $additionalparams['visible'] = $conditions['visible'];
                    }
                    if (isset($conditions['theme'])) {
                        $additionalselect .= ' AND theme= :theme';
                        $additionalparams['theme'] = $conditions['theme'];
                    }

                    foreach ($categories as $category) {
                        $sqlselect = $DB->sql_like('path', ':path') . $additionalselect;
                        $sqlparams = array('path' => $category->path.'/%') + $additionalparams; // It will NOT include the specified category.
                        $subcategories = $DB->get_records_select('course_categories', $sqlselect, $sqlparams);
                        $newcategories = $newcategories + $subcategories;   // Both arrays have integer as keys.
                    }
                    $categories = $categories + $newcategories;
                }
            }

        } else {
            // Retrieve all categories in the database.
            $categories = $DB->get_records('course_categories');
        }

        // The not returned categories. key => category id, value => reason of exclusion.
        $excludedcats = array();

        // The returned categories.
        $categoriesinfo = array();

        // We need to sort the categories by path.
        // The parent cats need to be checked by the algo first.
        usort($categories, "core_course_external::compare_categories_by_path");
        
        //category ids not visible to user
        $hide_ids = self::get_category_ids_to_hide();

        foreach ($categories as $category) {
            //next category if not visible to user
            if(in_array($category->id,$hide_ids)){
                continue;
            }

            //remove all parents from chain which are not visible to user
            $parents = explode('/', $category->path);
            unset($parents[0]); // First key is always empty because path start with / => /1/2/4.
            array_pop($parents);
            
            while(in_array($category->parent,$hide_ids)){
                array_pop($parents);
                if(count($parents)>0){
                    $category->parent = end($parents);
                } else {
                    $category->parent = 0;
                }
            }
            array_push($parents,$category->id);
            $category->depth = count($parents);
            $category->path = "/".implode("/",$parents);
            
            // Check if the category is a child of an excluded category, if yes exclude it too (excluded => do not return).
            $parents = explode('/', $category->path);
 
            unset($parents[0]); // First key is always empty because path start with / => /1/2/4.
            foreach ($parents as $parentid) {
                // Note: when the parent exclusion was due to the context,
                // the sub category could still be returned.
                if (isset($excludedcats[$parentid]) and $excludedcats[$parentid] != 'context') {
                    $excludedcats[$category->id] = 'parent';
                }
            }

            // Check the user can use the category context.
            $context = context_coursecat::instance($category->id);
            try {
                self::validate_context($context);
            } catch (Exception $e) {
                $excludedcats[$category->id] = 'context';

                // If it was the requested category then throw an exception.
                if (isset($params['categoryid']) && $category->id == $params['categoryid']) {
                    $exceptionparam = new stdClass();
                    $exceptionparam->message = $e->getMessage();
                    $exceptionparam->catid = $category->id;
                    throw new moodle_exception('errorcatcontextnotvalid', 'webservice', '', $exceptionparam);
                }
            }
            
            // Return the category information.
            if (!isset($excludedcats[$category->id])) {
                $categoryinfo = array();
                $categoryinfo['id'] = $category->id;
                $categoryinfo['name'] = external_format_string($category->name, $context);
                list($categoryinfo['description'], $categoryinfo['descriptionformat']) =
                    external_format_text($category->description, $category->descriptionformat,
                            $context->id, 'coursecat', 'description', null);
                $categoryinfo['parent'] = $category->parent;
                $categoryinfo['sortorder'] = $category->sortorder;
                $categoryinfo['coursecount'] = $category->coursecount;
                $categoryinfo['depth'] = $category->depth;
                $categoryinfo['path'] = $category->path;

                // Some fields only returned for admin.
                if (has_capability('moodle/category:manage', $context)) {
                    $categoryinfo['idnumber'] = $category->idnumber;
                    $categoryinfo['visible'] = $category->visible;
                    $categoryinfo['visibleold'] = $category->visibleold;
                    $categoryinfo['timemodified'] = $category->timemodified;
                    $categoryinfo['theme'] = clean_param($category->theme, PARAM_THEME);
                }

                $categoriesinfo[] = $categoryinfo;
            }
        }

        // Sorting the resulting array so it looks a bit better for the client developer.
        usort($categoriesinfo, "core_course_external::compare_categories_by_sortorder");

        return $categoriesinfo;
    }
    
    /**
     * Get all category ids not visible to user
     * private function: only used by get_categories
     *
     * @return array of category ids not visible to user
     */
    private static function get_category_ids_to_hide(){
        $canviewids = array_keys(core_course_category::get_all());
        return array_diff(array_keys(core_course_category::get_all(array("returnhidden"=>true))),$canviewids);
    }

    /**
     * Sort categories array by path
     * private function: only used by get_categories
     *
     * @param array $category1
     * @param array $category2
     * @return int result of strcmp
     * @since Moodle 2.3
     */
    private static function compare_categories_by_path($category1, $category2) {
        return strcmp($category1->path, $category2->path);
    }

    /**
     * Sort categories array by sortorder
     * private function: only used by get_categories
     *
     * @param array $category1
     * @param array $category2
     * @return int result of strcmp
     * @since Moodle 2.3
     */
    private static function compare_categories_by_sortorder($category1, $category2) {
        return strcmp($category1['sortorder'], $category2['sortorder']);
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.3
     */
    public static function get_categories_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'category id'),
                    'name' => new external_value(PARAM_TEXT, 'category name'),
                    'idnumber' => new external_value(PARAM_RAW, 'category id number', VALUE_OPTIONAL),
                    'description' => new external_value(PARAM_RAW, 'category description'),
                    'descriptionformat' => new external_format_value('description'),
                    'parent' => new external_value(PARAM_INT, 'parent category id'),
                    'sortorder' => new external_value(PARAM_INT, 'category sorting order'),
                    'coursecount' => new external_value(PARAM_INT, 'number of courses in this category'),
                    'visible' => new external_value(PARAM_INT, '1: available, 0:not available', VALUE_OPTIONAL),
                    'visibleold' => new external_value(PARAM_INT, '1: available, 0:not available', VALUE_OPTIONAL),
                    'timemodified' => new external_value(PARAM_INT, 'timestamp', VALUE_OPTIONAL),
                    'depth' => new external_value(PARAM_INT, 'category depth'),
                    'path' => new external_value(PARAM_TEXT, 'category path'),
                    'theme' => new external_value(PARAM_THEME, 'category theme', VALUE_OPTIONAL),
                ), 'List of categories'
            )
        );
    }
}
