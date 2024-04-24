<?php
/**
 * moosh - Moodle Shell
 *
 * @copyright 2012 onwards Tomasz Muras
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace Moosh\Command\Moodle41\Category;

use DOMDocument;
use Moosh\MooshCommand;

class CategoryExport extends MooshCommand
{
    public function __construct()
    {
        parent::__construct('export', 'category');

        $this->addOption('o|outputdir:', 'Directory where output XML file is to be saved.');
        $this->addOption('p|print', 'Output the XML to stdout.', false);
        $this->addArgument('category_id');

    }

    public function execute()
    {
        global $CFG;

        include_once $CFG->dirroot . '/course/lib.php';
        include_once $CFG->dirroot . '/course/renderer.php';

        $category_id = intval($this->arguments[0]);

        $categories = $this->get_category_tree($category_id);

        if (false === is_array($categories)) {
            $categories = [$categories];
        }

        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;

        $dom->loadXML(
            $this->categories2xml($categories)
        );

        if (false === file_exists($this->finalOptions['outputdir'])) {
            mkdir($this->finalOptions['outputdir'], 0760);
        }

        $xml = $dom->save(
            $this->finalOptions['outputdir'].DIRECTORY_SEPARATOR.'categories_from_'.$category_id.'_'.time().'.xml'
        );

        if (boolval($this->finalOptions['print']) === true) {
            echo $xml . "\n" . PHP_EOL;
        }
    }

    private function get_category_tree($id)
    {
        global $DB;

        $category = new \stdClass();

        if ($id > 0) {
            $category = $DB->get_record('course_categories', array('id' => $id));

            if ($id && !$category) {
                cli_error("Wrong category '$id'");
            }
        }

        $parent_category = \core_course_category::get($id);

        if ($parent_category->has_children()) {
            $children = $parent_category->get_children();

            foreach ($children as $single_category) {
                if ($single_category->has_children()) {
                    $childcategories = $this->get_category_tree($single_category->id);
                    $category->categories[] = $childcategories;
                } else {
                    // coursecat variables are protected, need to get data from db
                    $single_category = $DB->get_record(
                        'course_categories', ['id' => $single_category->id]
                    );
                    $category->categories[] = $single_category;
                }
            }
        }

        if (false === property_exists($category, 'id')) {
            return $category->categories;
        }

        return $category;
    }

    private function categories2xml(array $categories): string
    {
        $output = '<categories>';

        foreach ($categories as $category) {
            if (!is_object($category)) {
                echo "not an object\n";
                var_dump($category);
                debug_print_backtrace();
                die();
            }

            if (property_exists($category, 'id') === true
                && intval($category->id) > 0
            ) {
                $output .= "<category oldid='".$category->id."' ";

                if (isset($category->idnumber) && !empty($category->idnumber)) {
                    $output .= "idnumber='".$category->idnumber."' ";
                }

                $name = str_replace(
                    array("&", "<", ">", '"', "'"),
                    array("&amp;", "&lt;", "&gt;", "&quot;", "&apos;"),
                    $category->name
                );
                $output .= "name='".$name."'>";

                if (property_exists($category, 'categories') === true) {
                    foreach ($category->categories as $sub_categories) {
                        if (false === is_array($sub_categories)) {
                            $sub_categories = [$sub_categories];
                        }

                        $output .= $this->categories2xml($sub_categories);
                    }
                }

                $output .= '</category>';
            }
        }

        return $output.'</categories>';
    }
}
