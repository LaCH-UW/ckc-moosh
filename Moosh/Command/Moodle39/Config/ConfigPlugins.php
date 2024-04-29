<?php
/**
 * moosh - Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh\Command\Moodle39\Config;
use Moosh\MooshCommand;

class ConfigPlugins extends MooshCommand
{
    public function __construct()
    {
        parent::__construct('plugins', 'config');
        $this->maxArguments = 1;
    }

    public function execute()
    {
        global $DB;

        $sql = 'SELECT plugin FROM {config_plugins} GROUP BY plugin ORDER BY plugin ASC';
        $parameters = [];

        if (isset($this->arguments[0])) {
            $sql = 'SELECT plugin FROM {config_plugins}
                  WHERE ' . $DB->sql_like('plugin', ':plugin', false) . '
                  GROUP BY plugin
                  ORDER BY plugin ASC';
            $parameters = ['plugin' => '%'.$this->arguments[0].'%'];
        }

        $rows = $DB->get_records_sql($sql, $parameters);

        foreach($rows as $row) {
            echo $row->plugin . "\n";
        }
    }

    protected function getArgumentsHelp()
    {
        $ret = "\n\nARGUMENTS:";
        $ret .= "\n\t";
        $ret .= "<plugin_name_fragment>\n";

        return $ret;
    }
}
