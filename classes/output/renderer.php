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

namespace local_hidehiddencategories\output;

class core_course_category extends \core_course_category {
    protected static $coursecat0 = null;
    protected static $categories = null;
    protected static $canviewids = null;
    protected static $cannotviewids = null;
    protected $children = null;
    protected $childrencount = 0;
    
    public static function get_ids(){
        if(self::$categories === null){
            self::$categories = self::get_all();
            self::$canviewids = array_keys(self::$categories);
            self::$cannotviewids = array_diff(array_keys(self::get_all(array("returnhidden"=>true))),self::$canviewids);

            //expand hidden categories for management page to display correctly
            //
            //seems to work without this
            //
//            foreach(self::$cannotviewids as $catid){
//                $coursecat = core_course_category::get($catid,MUST_EXIST,true);
//                \core_course\management\helper::record_expanded_category($coursecat);
//            }
        }
        return array("canview"=>self::$canviewids,"cannotview"=>self::$cannotviewids);
    }
    
    /**
     * Returns the pseudo-category representing the whole system (id=0, context_system)
     *
     * @return core_course_category
     */
    public static function top() {
        if (!isset(self::$coursecat0)) {
            $record = new \stdClass();
            $record->id = 0;
            $record->visible = 1;
            $record->depth = 0;
            $record->path = '';
            $record->locked = 0;
            self::$coursecat0 = new self($record);
        }
        return self::$coursecat0;
    }

    /**
     * Returns the top-most category for the current user
     * same function as original, just so it uses this class as self::
     * 
     * Examples:
     * 1. User can browse courses everywhere - return self::top() - pseudo-category with id=0
     * 2. User does not have capability to browse courses on the system level but
     *    has it in ONE course category - return this course category
     * 3. User has capability to browse courses in two course categories - return self::top()
     *
     * @return core_course_category|null
     */
    public static function user_top() {
        $children = self::top()->get_children();
        if (count($children) == 1) {
            // User has access to only one category on the top level. Return this category as "user top category".
            return reset($children);
        }
        if (count($children) > 1) {
            // User has access to more than one category on the top level. Return the top as "user top category".
            // In this case user actually may not have capability 'moodle/category:viewcourselist' on the top level.
            return self::top();
        }
        // User can not access any categories on the top level.
        // TODO MDL-10965 find ANY/ALL categories in the tree where user has access to.
        return self::get(0, IGNORE_MISSING);
    }

    
    /**
     * Returns number of subcategories 
     *
     * @return int
     */
    public function get_children_count() {
        if($this->children === null) {
            $this->children = self::get_tree_of_all_visible_to_user($this->id);
            $this->childrencount = count($this->children);
        }
        return $this->childrencount;
    }
    
    /**
     * Returns the entry from categories tree and makes sure the application-level tree cache is built
     * Children which cannot be accessed by the user will be removed within the tree and replaced
     * by possible visible grand children
     * 
     * The following keys can be requested:
     *
     * $id (int) - array of ids of categories that are direct children of category with id $id. If
     *   category with id $id does not exist, or category has no children, returns empty array
     * @param string $id
     * @return array of children, not accessible children are removed
     */

    private static function get_tree_of_all_visible_to_user($id){
        $tree = self::get_tree($id);
        $to_delete = array();
        for($i = 0; $i < count($tree); $i++){
            $leaf = $tree[$i];
            if(in_array($leaf,self::get_ids()["cannotview"])){
                $newids = self::get_tree_of_all_visible_to_user($leaf);
                array_splice($tree,$i,1,$newids);
                $i += count($newids)-1;
            }
        }
        return $tree;
    }
    
    /**
     * Returns array of children categories visible to the current user
     * quitely changed to remove non accessible children but still show accessible grand children
     * 
     * @param array $options options for retrieving children
     *    - sort - list of fields to sort. Example
     *             array('idnumber' => 1, 'name' => 1, 'id' => -1)
     *             will sort by idnumber asc, name asc and id desc.
     *             Default: array('sortorder' => 1)
     *             Only cached fields may be used for sorting!
     *    - offset
     *    - limit - maximum number of children to return, 0 or null for no limit
     * @return core_course_category[] Array of core_course_category objects indexed by category id
     */
    public function get_children($options = array()) {
        global $DB;
        $coursecatcache = \cache::make('core', 'coursecat');
        // Get default values for options.
        if (!empty($options['sort']) && is_array($options['sort'])) {
            $sortfields = $options['sort'];
        } else {
            $sortfields = array('sortorder' => 1);
        }
        $limit = null;
        if (!empty($options['limit']) && (int)$options['limit']) {
            $limit = (int)$options['limit'];
        }
        $offset = 0;
        if (!empty($options['offset']) && (int)$options['offset']) {
            $offset = (int)$options['offset'];
        }

        $key = 'hidehiddencategories'. $this->id. ':'.  serialize($sortfields);
        /*JH remove this line - its for debugging*/ //$coursecatcache->delete($key);
        // First retrieve list of user-visible and sorted children ids from cache.
        $sortedids = $coursecatcache->get('hidehiddencategories'. $this->id. ':'.  serialize($sortfields));
        if ($sortedids === false) {
            $sortfieldskeys = array_keys($sortfields);
            if ($sortfieldskeys[0] === 'sortorder') {
                // No DB requests required to build the list of ids sorted by sortorder.
                // We can easily ignore other sort fields because sortorder is always different.
                $sortedids = self::get_tree_of_all_visible_to_user($this->id);
                if ($sortfields['sortorder'] == -1) {
                    $sortedids = array_reverse($sortedids, true);
                }
            } else {
                // We need to retrieve and sort all children. Good thing that it is done only on first request.
                $ids = self::get_tree_of_all_visible_to_user($this->id);
                if(empty($ids)){
                    return array();
                }
                list($sql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, "id");
                $records = self::get_records('cc.id '.$sql,$params);

                self::sort_records($records, $sortfields);
                $sortedids = array_keys($records);
            }
            $coursecatcache->set('hidehiddencategories'. $this->id. ':'.serialize($sortfields), $sortedids);
        }

        if (empty($sortedids)) {
            return array();
        }

        // Now retrieive and return categories.
        if ($offset || $limit) {
            $sortedids = array_slice($sortedids, $offset, $limit);
        }
        if (isset($records)) {
            // Easy, we have already retrieved records.
            if ($offset || $limit) {
                $records = array_slice($records, $offset, $limit, true);
            }
        } else {
            list($sql, $params) = $DB->get_in_or_equal($sortedids, SQL_PARAMS_NAMED, 'id');
            $records = self::get_records('cc.id '. $sql, array('parent' => $this->id) + $params);
        }

        $rv = array();
        foreach ($sortedids as $id) {
            if (isset($records[$id])) {
                $rv[$id] = new self($records[$id]);
            }
        }
        return $rv;
    }
}

//extend the current course renderer
global $PAGE;
class_alias("\\".$PAGE->get_course_renderer_class(),"local_hidehiddencategories\\output\\renderer_parent_class");

class renderer extends renderer_parent_class {
    /**
     * Returns HTML to print tree of course categories (with number of courses) for the frontpage
     * same as parent function, except for using our course_category class
     * 
     * @return string
     */
    public function frontpage_categories_list() {
        global $CFG;
        // TODO MDL-10965 improve.
        $tree = core_course_category::top();
        if (!$tree->get_children_count()) {
            return '';
        }
        $chelper = new \coursecat_helper();
        $chelper->set_subcat_depth($CFG->maxcategorydepth)->
                set_show_courses(self::COURSECAT_SHOW_COURSES_COUNT)->
                set_categories_display_options(array(
                    'limit' => $CFG->coursesperpage,
                    'viewmoreurl' => new \moodle_url('/course/index.php',
                            array('browse' => 'categories', 'page' => 1))
                ))->
                set_attributes(array('class' => 'frontpage-category-names'));
        return $this->coursecat_tree($chelper, $tree);
    }

    /**
     * Returns HTML to print tree with course categories and courses for the frontpage
     * same as parent function, except for using our course_category class
     * 
     * @return string
     */
    public function frontpage_combo_list() {
        global $CFG;
        // TODO MDL-10965 improve.
        $categories = core_course_category::top();
        $chelper = new \coursecat_helper();
        $chelper->set_subcat_depth($CFG->maxcategorydepth)->
            set_categories_display_options(array(
                'limit' => $CFG->coursesperpage,
                'viewmoreurl' => new \moodle_url('/course/index.php',
                        array('browse' => 'categories', 'page' => 1))
            ))->
            set_courses_display_options(array(
                'limit' => $CFG->coursesperpage,
                'viewmoreurl' => new \moodle_url('/course/index.php',
                        array('browse' => 'courses', 'page' => 1))
            ))->
            set_attributes(array('class' => 'frontpage-category-combo'));
        return $this->coursecat_tree($chelper, $categories);
    }
    
    /**
     * Renders HTML to display particular course category - list of it's subcategories and courses
     *
     * Invoked from /course/index.php
     *
     * @param int|stdClass|core_course_category $category
     */
    public function course_category($category) {
        global $CFG;
        $usertop = core_course_category::user_top();
        if (empty($category)) {
            $coursecat = $usertop;
        } else if (is_object($category) && $category instanceof core_course_category) {
            $coursecat = $category;
        } else {
            $coursecat = core_course_category::get(is_object($category) ? $category->id : $category);
        }
        $site = \get_site();
        $output = '';

        if ($coursecat->can_create_course() || $coursecat->has_manage_capability()) {
            // Add 'Manage' button if user has permissions to edit this category.
            $managebutton = $this->single_button(new \moodle_url('/course/management.php',
                array('categoryid' => $coursecat->id)), \get_string('managecourses'), 'get');
            $this->page->set_button($managebutton);
        }

        if (core_course_category::is_simple_site()) {
            // There is only one category in the system, do not display link to it.
            $strfulllistofcourses = \get_string('fulllistofcourses');
            $this->page->set_title("$site->shortname: $strfulllistofcourses");
        } else if (!$coursecat->id || !$coursecat->is_uservisible()) {
            $strcategories = \get_string('categories');
            $this->page->set_title("$site->shortname: $strcategories");
        } else {
            $strfulllistofcourses = \get_string('fulllistofcourses');
            $this->page->set_title("$site->shortname: $strfulllistofcourses");

            // Print the category selector
            $categorieslist = core_course_category::make_categories_list();
            if (count($categorieslist) > 1) {
                $output .= \html_writer::start_tag('div', array('class' => 'categorypicker'));
                $select = new \single_select(new \moodle_url('/course/index.php'), 'categoryid',
                        core_course_category::make_categories_list(), $coursecat->id, null, 'switchcategory');
                $select->set_label(get_string('categories').':');
                $output .= $this->render($select);
                $output .= \html_writer::end_tag('div'); // .categorypicker
            }
        }

        // Print current category description
        $chelper = new \coursecat_helper();
        if ($description = $chelper->get_category_formatted_description($coursecat)) {
            $output .= $this->box($description, array('class' => 'generalbox info'));
        }

        // Prepare parameters for courses and categories lists in the tree
        $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_AUTO)
                ->set_attributes(array('class' => 'category-browse category-browse-'.$coursecat->id));

        $coursedisplayoptions = array();
        $catdisplayoptions = array();
        $browse = \optional_param('browse', null, PARAM_ALPHA);
        $perpage = \optional_param('perpage', $CFG->coursesperpage, PARAM_INT);
        $page = \optional_param('page', 0, PARAM_INT);
        $baseurl = new \moodle_url('/course/index.php');
        if ($coursecat->id) {
            $baseurl->param('categoryid', $coursecat->id);
        }
        if ($perpage != $CFG->coursesperpage) {
            $baseurl->param('perpage', $perpage);
        }
        $coursedisplayoptions['limit'] = $perpage;
        $catdisplayoptions['limit'] = $perpage;
        if ($browse === 'courses' || !$coursecat->get_children_count()) {
            $coursedisplayoptions['offset'] = $page * $perpage;
            $coursedisplayoptions['paginationurl'] = new \moodle_url($baseurl, array('browse' => 'courses'));
            $catdisplayoptions['nodisplay'] = true;
            $catdisplayoptions['viewmoreurl'] = new \moodle_url($baseurl, array('browse' => 'categories'));
            $catdisplayoptions['viewmoretext'] = new \lang_string('viewallsubcategories');
        } else if ($browse === 'categories' || !$coursecat->get_courses_count()) {
            $coursedisplayoptions['nodisplay'] = true;
            $catdisplayoptions['offset'] = $page * $perpage;
            $catdisplayoptions['paginationurl'] = new \moodle_url($baseurl, array('browse' => 'categories'));
            $coursedisplayoptions['viewmoreurl'] = new \moodle_url($baseurl, array('browse' => 'courses'));
            $coursedisplayoptions['viewmoretext'] = new \lang_string('viewallcourses');
        } else {
            // we have a category that has both subcategories and courses, display pagination separately
            $coursedisplayoptions['viewmoreurl'] = new \moodle_url($baseurl, array('browse' => 'courses', 'page' => 1));
            $catdisplayoptions['viewmoreurl'] = new \moodle_url($baseurl, array('browse' => 'categories', 'page' => 1));
        }
        $chelper->set_courses_display_options($coursedisplayoptions)->set_categories_display_options($catdisplayoptions);
        // Add course search form.
        $output .= $this->course_search_form();

        // Display course category tree.
        $output .= $this->coursecat_tree($chelper, $coursecat);

        // Add action buttons
        $output .= $this->container_start('buttons');
        if ($coursecat->is_uservisible()) {
            $context = \get_category_or_system_context($coursecat->id);
            if (has_capability('moodle/course:create', $context)) {
                // Print link to create a new course, for the 1st available category.
                if ($coursecat->id) {
                    $url = new \moodle_url('/course/edit.php', array('category' => $coursecat->id, 'returnto' => 'category'));
                } else {
                    $url = new \moodle_url('/course/edit.php',
                        array('category' => $CFG->defaultrequestcategory, 'returnto' => 'topcat'));
                }
                $output .= $this->single_button($url, get_string('addnewcourse'), 'get');
            }
            \ob_start();
            \print_course_request_buttons($context);
            $output .= \ob_get_contents();
            \ob_end_clean();
        }
        $output .= $this->container_end();

        return $output;
    }
    
    /**
     * Serves requests to /course/category.ajax.php
     *
     * In this renderer implementation it may expand the category content or
     * course content.
     *
     * @return string
     * @throws coding_exception
     */
    public function coursecat_ajax() {
        global $DB, $CFG;
        
        $type = required_param('type', PARAM_INT);

        if ($type === self::COURSECAT_TYPE_CATEGORY) {
            // This is a request for a category list of some kind.
            $categoryid = required_param('categoryid', PARAM_INT);
            $showcourses = required_param('showcourses', PARAM_INT);
            $depth = required_param('depth', PARAM_INT);

            $category = core_course_category::get($categoryid);

            $chelper = new \coursecat_helper();
            $baseurl = new \moodle_url('/course/index.php', array('categoryid' => $categoryid));
            $coursedisplayoptions = array(
                'limit' => $CFG->coursesperpage,
                'viewmoreurl' => new \moodle_url($baseurl, array('browse' => 'courses', 'page' => 1))
            );
            $catdisplayoptions = array(
                'limit' => $CFG->coursesperpage,
                'viewmoreurl' => new \moodle_url($baseurl, array('browse' => 'categories', 'page' => 1))
            );
            $chelper->set_show_courses($showcourses)->
                    set_courses_display_options($coursedisplayoptions)->
                    set_categories_display_options($catdisplayoptions);

            return $this->coursecat_category_content($chelper, $category, $depth);
        } else if ($type === self::COURSECAT_TYPE_COURSE) {
            // This is a request for the course information.
            $courseid = \required_param('courseid', PARAM_INT);

            $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

            $chelper = new \coursecat_helper();
            $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_EXPANDED);
            return $this->coursecat_coursebox_content($chelper, $course);
        } else {
            throw new \coding_exception('Invalid request type');
        }
    }

}

class_alias("\\".$PAGE->get_course_management_renderer_class(),"local_hidehiddencategories\\output\\management_renderer_parent_class");

class management_renderer extends management_renderer_parent_class {
    
    /**
    * Constructor method, calls the parent constructor
    *
    * @param moodle_page $page
    * @param string $target one of rendering target constants
    */
    public function __construct(\moodle_page $page, $target) {
        parent::__construct($page,$target);
        self::cleanup_navigation($page);
    }
    
    /**
     * Deletes invisible categories in navigation
     * Moves grand children in their positions
     * 
     * @param moodle_page $page
     */
    private static function cleanup_navigation(\moodle_page $page){
        $can_view = array_keys(\core_course_category::get_all());
        foreach($page->navbar->children as $key=>$item){
            $action = $item->action();
            if($action instanceof \moodle_url){
                $params = $action->params();
                if(array_key_exists("categoryid",$params)){
                    $catid = $params["categoryid"];
                    if(!in_array($catid,$can_view))
                        unset($page->navbar->children[$key]);
                }
            }
        } 
    }

    /**
     * Presents a course category listing.
     *
     * @param core_course_category $category The currently selected category. Also the category to highlight in the listing.
     * @return string
     */
    public function category_listing(\core_course_category $category = null) {
        //keep this line on top so core_course_category::$cannotviewids is initialized
        $listing = core_course_category::top()->get_children();

        if ($category === null) {
            $selectedparents = array();
            $selectedcategory = null;
        } else {
            //remove all invisible parents
            $selectedparents = array_diff($category->get_parents(), core_course_category::get_ids()["cannotview"]);
            $selectedparents[] = $category->id;
            $selectedcategory = $category->id;
        }
        $catatlevel = \core_course\management\helper::get_expanded_categories('');
        $catatlevel[] = \array_shift($selectedparents);
        $catatlevel = \array_unique($catatlevel);

        $attributes = array(
                'class' => 'ml-1 list-unstyled',
                'role' => 'tree',
                'aria-labelledby' => 'category-listing-title'
        );

        $html  = \html_writer::start_div('category-listing card w-100');
        $html .= \html_writer::tag('h3', get_string('categories'),
                array('class' => 'card-header', 'id' => 'category-listing-title'));
        $html .= \html_writer::start_div('card-body');
        $html .= $this->category_listing_actions($category);
        $html .= \html_writer::start_tag('ul', $attributes);
        foreach ($listing as $listitem) {
            // Render each category in the listing.
            $subcategories = array();
            if (\in_array($listitem->id, $catatlevel)) {
                $subcategories = $listitem->get_children();
            }
            $html .= $this->category_listitem(
                    $listitem,
                    $subcategories,
                    $listitem->get_children_count(),
                    $selectedcategory,
                    $selectedparents
            );
        }
        $html .= \html_writer::end_tag('ul');
        $html .= $this->category_bulk_actions($category);
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();
        return $html;
    }
}
