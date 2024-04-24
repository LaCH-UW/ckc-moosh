<?php
/**
 * moosh - Moodle Shell - RoleExport command
 *
 * This command should be used to export role definition to an XML file.
 *
 * @example This example will write XML contents to a specific file
 *          $ php moosh.php role-export -f target_file.xml ROLENAME
 *
 * @example This example will output XML contents to stdout
 *          $ php moosh.php role-export ROLENAME
 *
 * @example This example will output pretty printed XML contents to stdout
 *          $ php moosh.php role-export --pretty ROLENAME
 *
 * @copyright 2012 onwards Tomasz Muras
 * @author    Andrej Vitez <contact@andrejvitez.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh\Command\Moodle41\Role;

use core_role_preset;
use DOMDocument;
use Moosh\MooshCommand;

class RoleExport extends MooshCommand
{
    public function __construct()
    {
        parent::__construct('export', 'role');

        $this->addOption('f|file:', 'Output file path. If empty then output will be printed to stdout.');
        $this->addOption(
            'o|outputdir:',
            'Output directory path. If empty then output will be set to current direcotry. Can be overriten by "file" parameter.'
        );
        $this->addOption('r|pretty', 'Output formatted XML with whitespaces.');
        $this->addArgument('shortname');
    }

    public function execute()
    {
        global $CFG, $DB;

        $role_name = strtolower(htmlspecialchars($this->arguments[0]));

        if (!$role_name) {
            printf("Short rolename argument is mandatory");
            exit(1);
        }

        $role = $DB->get_record('role', array('shortname' => $role_name));

        if (!$role) {
            echo "Role '" . $role_name . "' does not exists!\n";
            exit(1);
        }

        $file_dir = '';
        $file_name = '';
        $file = $this->finalOptions['file'];

        if (false === empty($file)) {
            $path_info = pathinfo($file);
            $file_name = $path_info['filename'];
            $file_dir = $path_info['dirname'];
        }

        $output_dir = $this->finalOptions['outputdir'];

        if (false === empty($output_dir)
            && (empty($file_dir) === true || $file_dir === '.')
        ) {
            $file_dir = $output_dir;

            if (empty($file_name) === true) {
                $file_name = $role_name;
            }
        }

        if (false === empty($file_dir) && strlen($file_dir) > 2 && false === file_exists($file_dir)) {
            mkdir($file_dir, 0760);
        }

        $filepath = $file_dir.DIRECTORY_SEPARATOR.$file_name.'_'.time().'.xml';

        if (false === is_writable($file_dir)) {
            printf("Invalid output path value '%s'\n", $file_dir);
            exit(1);
        } else if (file_exists($filepath) === true) {
            printf("File exists '%s'\n", $filepath);
            exit(1);
        }

        include_once $CFG->libdir . '/filelib.php';
        $xml = core_role_preset::get_export_xml($role->id);

        if ($this->expandedOptions['pretty']) {
            $dom = new DOMDocument('1.0');
            $dom->preserveWhiteSpace = true;
            $dom->formatOutput = true;
            $dom->loadXML($xml);
            $xml = $dom->saveXML();
        }

        if ($filepath) {
            if (false === file_put_contents($filepath, $xml)) {
                printf("Could not save XML contents to file '%s'\n", $filepath);
                exit(1);
            }

            exit(0);
        }

        echo $xml . PHP_EOL;
    }
}
