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
 * TODO describe file test
 *
 * @package    fileconverter_pandocws
 * @copyright  2025 University Of Strathclyde <education-technology@strath.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 require(__DIR__ . '/../../../config.php');
 require_once($CFG->libdir . '/filelib.php');

 $actions = ['send', 'poll', 'download'];
 $action = optional_param('action', "send", PARAM_ALPHA);

 $PAGE->set_url(new moodle_url('/files/converter/pandocws/test.php'));
 $PAGE->set_context(context_system::instance());
 
 require_login();
 require_capability('moodle/site:config', context_system::instance());
 
 $converter = new \fileconverter_pandocws\converter();
switch($action) {
    case "send":
        // require_sesskey();
        $converter->serve_test_document();
        die();
    case 'poll':
        $conversionid = required_param('conversion', PARAM_INT);
        $conversion = new \core_files\conversion($conversionid);
        print_r($conversion);
        $converter->poll_conversion_status($conversion);
        print_r($conversion);
        if ($conversion->get('status') == \core_files\conversion::STATUS_COMPLETE) {
            echo "Completed<br />";
            echo $OUTPUT->action_link(new \moodle_url('/files/converter/pandocws/test.php', ['action' => 'download', 'conversion' => $conversionid]), 'Download');
        } else {
            echo "Not completed";
            echo "Status: " . $conversion->get('status');
        }
        die();
    case 'download':
        $conversionid = required_param('conversion', PARAM_INT);
        $conversion = new \core_files\conversion($conversionid);
        $testfile = $conversion->get_destfile();
        readfile_accel($testfile, $testfile->get_mimetype(), true);
}