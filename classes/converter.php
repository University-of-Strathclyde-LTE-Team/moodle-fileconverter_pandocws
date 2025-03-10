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

namespace fileconverter_pandocws;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

use core\exception\moodle_exception;
use stored_file;
use core_files\conversion;
use CURLFile;
use core_files\converter_interface;

/**
 * Class converter
 *
 * @package    fileconverter_pandocws
 * @copyright  2025 University Of Strathclyde <education-technology@strath.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class converter implements converter_interface {

    /**
     * TODO: move to a settting.
     * @var string $wsurl The URL of the Pandoc Web Service.
     */
    protected $wsurl = "http://172.26.229.18:5000/";
    /**
     * Test that all of the requirements to use this converter are met.
     */
    public static function are_requirements_met(): bool {
        // Check that the API is accessible.
        return true;
    }

    /**
     * Send a stored file to conversion web service.
     * @param conversion $conversion Conversion record.
     */
    public function start_document_conversion(conversion $conversion) {
        $curl = new \curl();
        $sourcefile = $conversion->get_sourcefile();
        $filename = $sourcefile->get_filename();
        $fileext = pathinfo($filename, PATHINFO_EXTENSION);

        $response = $curl->post($this->wsurl . 'convert', [
            'input_format' => $fileext,
            'output_format' => $conversion->get('targetformat'),
            'file' => $sourcefile,
        ]);

        if (isset($response->error)) {
            throw new moodle_exception($response->error);
        } else {
            var_dump($response);
            $result = json_decode($response);
            $taskid = $result->task_id; // The conversion task on the server.
            $fileid = $result->file_id; // The file id on the conversion server.
            $conversion->set('status', conversion::STATUS_PENDING);
            $conversion->set('data', $result);  // Stash the response data so we can poll the server later..
            $conversion->update();
        }
        return $this;
    }

    /**
     * Check the status with the web service about the conversion's state.
     * @param conversion $conversion Conversion record.
     */
    public function poll_conversion_status(conversion $conversion) {
        $taskdata = $conversion->get('data');
        $taskid = $taskdata->task_id;
        // We make a call to the /status endpoint of the Pandoc Web Service.
        $headers = [];
        $postdata = null;
        $response = download_file_content(
            $this->wsurl . "status/{$taskid}",
            $headers,
            $postdata
        );
        $result = json_decode($response);
        if (isset($result->error)) {
            throw new moodle_exception($result->error);
        } else {
            switch(strtolower($result->status)) {
                case 'pending':
                    $conversion->set('status', conversion::STATUS_PENDING);
                    break;
                case 'completed':
                    // We can now attempt to download the converted file.
                    if ($this->store_converted($conversion)) {
                        $conversion->set('status', conversion::STATUS_COMPLETE);
                    } else {
                        $conversion->set('status', conversion::STATUS_FAILED);
                    }
                    break;
                case 'failed':
                    $conversion->set('status', conversion::STATUS_FAILED);
                    break;
            }
            $conversion->update();
        }
        return $this;
    }

    /**
     * Download the converted file and store it locally.
     * @param \core_files\conversion $conversion Conversion record.
     */
    protected function store_converted(conversion $conversion) {
        try {
            $taskdata = $conversion->get('data');
            $taskid = $taskdata->task_id;
            $headers = [];
            $postdata = null;
            $response = download_file_content(
                $this->wsurl . "download/{$taskid}",
                $headers,
                $postdata,
            );
            // Check the result code.
            if ($response === false) {
                throw new moodle_exception('Failed to download the converted file');
            }
            $conversion->store_destfile_from_string($response);
            $conversion->update();
            return true;
        } catch (\Exception $e) {
            debugging($e->getMessage(), DEBUG_NORMAL);
            return false;
        }
    }
    /**
     * @var array $supported Array of supported from->to formats.
     */
    protected $supported = [
        'docx' => ['pdf', 'html', 'docx'],
        'html' => ['pdf'],
    ];

    /**
     * Indicate what formats this plugin supports converting from and to.
     * @param string $from The source format.
     * @param string $to The target format.
     */
    public static function supports($from, $to): bool {
        // TODO map on to pandoc supporterd formats.
        if (isset($supported[$from]) && in_array($to, $supported[$from])) {
            return true;
        }
        return false;
    }

    /**
     * Return the list of supported conversions.
     */
    public function get_supported_conversions() {
        // TODO map on to pandoc supporterd formats.
        return implode(", ", array_keys($this->supported));
    }

    /**
     * Deliver the test page.
     */
    public function serve_test_document() {
        global $CFG, $OUTPUT;
        require_once($CFG->libdir . '/filelib.php');

        $filerecord = [
            'contextid' => \context_system::instance()->id,
            'component' => 'test',
            'filearea' => 'fileconverter_pandocws',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'conversion_test.docx',
        ];
        // Get the fixture doc file content and generate and stored_file object.
        $fs = get_file_storage();
        $testdocx = $fs->get_file($filerecord['contextid'], $filerecord['component'], $filerecord['filearea'],
                $filerecord['itemid'], $filerecord['filepath'], $filerecord['filename']);

        if (!$testdocx) {
            $fixturefile = dirname(__DIR__) . '/tests/fixtures/TestDoc.docx';
            $testdocx = $fs->create_file_from_pathname($filerecord, $fixturefile);
        }

        $conversion = new conversion(0, (object) [
            'targetformat' => 'pdf',
        ]);
        $conversion->set_sourcefile($testdocx);
        $conversion->create();
        $this->start_document_conversion($conversion);

        var_dump($conversion);

        $statusurl = new \moodle_url('/files/converter/pandocws/test.php',
            ['action' => 'poll', 'conversion' => $conversion->get('id')]
        );

        echo $OUTPUT->action_link($statusurl, 'Poll status');
    }
}
