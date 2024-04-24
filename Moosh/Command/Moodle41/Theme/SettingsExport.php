<?php

namespace Moosh\Command\Moodle41\Theme;
use Moosh\MooshCommand;
use \Phar,
    \PharData;
use \DOMDocument;
use \core_plugin_manager;
use \theme_config;
use \context_system;

class SettingsExport extends MooshCommand
{

    public function __construct()
    {
        parent::__construct('settings-export', 'theme');

        $this->addOption(
            't|themename:',
            'The name of the theme.'
        );
        $this->addOption(
            'o|outputdir:',
            'The directory where output XML file is to be saved.'
        );
        $this->addOption(
            'a|archive',
            'Output an archive with the XML file.'
        );
        $this->maxArguments = 4;
    }

    public function execute()
    {
        global $CFG;

        include_once "$CFG->libdir/classes/plugin_manager.php";

        // Init vars
        $themetoexport = null;
        $outputdir = $this->cwd;

        //Validate theme
        $availablethemes = core_plugin_manager::instance()->get_plugins_of_type('theme');
        if (!empty($availablethemes)) {
            $availablethemenames = array_keys($availablethemes);
        }

        if ($this->parsedOptions->has('themename') && in_array($this->parsedOptions['themename']->value, $availablethemenames)) {
            $themetoexport = $this->parsedOptions['themename']->value;
        } else {
            $dirparts = explode('/', $this->cwd);

            if ($index = array_search('theme', $dirparts)) {
                $index++;
                if (array_key_exists($index, $dirparts) && in_array($dirparts[$index], $availablethemenames)) {
                    $themetoexport = $dirparts[$index];
                }
            }
        }

        if (!$themetoexport) {
            echo "Unknown theme. Available themes:- \r\n - ". implode("\r\n - ", $availablethemenames). "  \n";
            exit(0);
        }

        if (false === empty($this->finalOptions['outputdir'])) {
            $outputdir = rtrim($this->finalOptions['outputdir'], '/');
        }

        if (false === file_exists($outputdir)) {
            mkdir($outputdir, 0760);
        }

        if (!is_writable($outputdir)) {
            echo "Output directory is not writable \n";
            exit(0);
        }

        // Load theme settings
        $theme_component = $availablethemes[$themetoexport]->component;
        $theme_config = theme_config::load($themetoexport);
        $theme_settings = $theme_config->settings;
        $final_message = "No settings to export \n";

        if (!empty($theme_settings)) {
            $dom = new DOMDocument('1.0', 'utf-8');
            $root = $dom->createElement('theme');
            $root->setAttribute('name', $themetoexport);
            $root->setAttribute('component', $theme_component);
            $root->setAttribute('version', $theme_settings->version);
            $dom->appendChild($root);

            foreach ($theme_settings as $settingname => $settingvalue) {
                if ($settingname == 'version') {
                    continue;
                }

                $element = $dom->createElement('setting');
                $element->appendChild($dom->createTextNode($settingvalue));
                $element->setAttribute('name', $settingname);

                if ($settingvalue && $settingvalue[0] == '/' && strpos($settingvalue, '.') !== false) {

                    $fs = get_file_storage();
                    if ($files = $fs->get_area_files(context_system::instance()->id, $theme_component, $settingname, $settingvalue)) {
                        foreach ($files as $f) {
                            if (!$f->is_directory()) {
                                $fh = $f->get_content_file_handle();

                                $meta = stream_get_meta_data($fh);
                                $uriparts = explode('/', $meta['uri']);
                                $hash = array_pop($uriparts);

                                $phar->addFile($meta['uri'], $hash);
                                $element->setAttribute('file', $hash);
                                $root->appendChild($element);
                            }
                        }
                    } else {
                        // The value of this setting looked like a filename, but no matching file was found.
                        // Append as a standard setting value.
                        $root->appendChild($element);
                    }
                } else {
                    $root->appendChild($element);
                }
            }
            $time = time();

            $file_name = $themetoexport.'_settings_'.$time;
            $output = $outputdir.'/'.$file_name;
            $final_message = "Settings exported to ";
            $extension = 'xml';

            $save_status = $dom->save($output.'.'.$extension);

            if (false !== $save_status
                && boolval($this->finalOptions['archive']) === true
            ) {
                $phar = new PharData($output.'.tar');
                $phar->addFile($output.'.'.$extension, $file_name);
                $phar->compress(Phar::GZ);
                unlink($output.'.'.$extension);
                unlink($output.'.tar');

                $extension = 'tar.gz';
            }

        }

        echo $final_message.$file_name.'.'.$extension." \n";
        exit(0);
    }
}
