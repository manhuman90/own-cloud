<?php
/**
 * Copyright (c) 2013 Lukas Reschke <lukas@statuscode.ch>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

// Set the content type to Javascript
header("Content-type: text/javascript");

// Disallow caching
header("Cache-Control: no-cache, must-revalidate"); 
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 

$dir = isset($_GET['dir']) ? $_GET['dir'] : '';
$file = isset($_GET['file']) ? $_GET['file'] : '';

$array = array(
	"PDFJS.workerSrc" => OCP\Util::linkTo('files_pdfviewer', '3rdparty/pdfjs/pdf.worker.js'),
	"window.dir" => $dir,
	"window.file" => $file,
	);

echo("/**
 * Checks if the page is displayed in an iframe. If not redirect to /.
 **/
function redirectIfNotDisplayedInFrame () {
	try {
		if (window.frameElement) {
			return;
		}
	} catch (e) {}

	window.location.href = '/';
}
redirectIfNotDisplayedInFrame();");

// Echo it
foreach ($array as  $setting => $value) {
	echo($setting ."=".json_encode($value).";\n");
}
