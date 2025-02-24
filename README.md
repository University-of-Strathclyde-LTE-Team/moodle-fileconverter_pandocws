# pandoc Web Service Document Converter
This plugin works in association with the [pandocwebservice](https://github.com/University-of-Strathclyde-LTE-Team/pandocwebservice)
service to convert documents to different formats using the [pandoc]
(https://pandoc.org/) converter.

It is an **asynchronous** document converter, which means that the 
conversion does not happen immediately, but is queued and processed in the 
background.

The plugin supports **polling** for the status of the conversion process.

## Installation
1. copy the `pandocws/` folder to the `converter/` folder on your Moodle's
   server.
2. Run the Moodle installation / upgrade process.
3. Download the [pandocwebservice](https://github.
   com/University-of-Strathclyde-LTE-Team/pandocwebservice) and deploy in 
   your infrastructure
4. Configure the `pandocwebservice` URL in the plugin settings.
5. Enable the plugin in the Moodle admin settings.

## Usage
Once enabled the plugin should integrate in to the standard Moodle document 
converter process, following the "fall through" order specified in the File 
Converter's settings page.

## Conversion Process
The pandocws application exposes a RESTful API that can be used to convert 
documents using pandoc.

There 3 endpoints which this plugin calls:
* `/convert` - This accepts a POST request with the document to be converted,
  current file format and the desired output format.
  This returns a `file_id` which identifies the uploaded file, and a 
  `task_id` which identifies the conversion process. 
  The conversion of the file will start in the background.
* `/status` - This accepts a GET request with the `task_id` of the conversion
  process. This returns the status of the conversion process.
  This plugin will poll this endpoint until the conversion is complete or if 
  it fails.
* `/download` - Once the conversion is complete, this endpoint can be used to
  download the converted file. This accepts a GET request with the `file_id`
  of the converted file. This returns the converted file, which is then 
  automatically stored in the Moodle file system as part of the conversion 
  API process, and can then be used by other Moodle such as the Annoated PDF 
  feedback plugin.

## Limitations
1. There is current influence the formatting of PDF conversions, so at the 
   moment these come out looking like "academic" papers.
