<?php

/**
* ownCloud - ajax frontend
*
* @author Robin Appelman
* @copyright 2010 Robin Appelman icewind1991@gmail.com
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

// Init owncloud
require_once('../lib/base.php');

// Check if we are a user
OC_Util::checkLoggedIn();

$filename = $_GET["file"];

if(!OC_Filesystem::file_exists($filename)){
	header("HTTP/1.0 404 Not Found");
	$tmpl = new OC_Template( '', '404', 'guest' );
	$tmpl->assign('file',$filename);
	$tmpl->printPage();
	exit;
}

$ftype=OC_Filesystem::getMimeType( $filename );

header('Content-Type:'.$ftype);
header('Content-Disposition: attachment; filename="'.basename($filename).'"');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: '.OC_Filesystem::filesize($filename));

@ob_end_clean();
OC_Filesystem::readfile( $filename );
?>
