<?php

/*
This file is part of Peachy MediaWiki Bot API

Peachy is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once( $IP . 'Diff/textdiff/Diff.php' );
require_once( $IP . 'Diff/textdiff/Diff/Renderer.php' );

/**
 * Generates a diff between two strings
 * 
 * @param string $method Which style of diff to generate: unified or inline (HTML)
 * @param string $diff1 Old text
 * @param string $diff2 New text
 * @return string Generated diff
 * @link http://pear.php.net/package/Text_Diff/redirected
 */
function getTextDiff($method, $diff1, $diff2) {
	switch ($method) {
		case 'unified':
			require_once $IP . 'Diff/textdiff/Diff/Renderer/unified.php';
			$diff = new Text_Diff('auto', array(explode("\n",$diff1), explode("\n",$diff2)));

			$renderer = new Text_Diff_Renderer_unified();
			
			$diff = $renderer->render($diff);
			break;
		case 'inline':
			require_once $IP . 'Diff/textdiff/Diff/Renderer/inline.php';
			$diff = new Text_Diff('auto', array(explode("\n",$diff1), explode("\n",$diff2)));

			$renderer = new Text_Diff_Renderer_inline();
			
			$diff = $renderer->render($diff);
			break;
	}
	unset($renderer);
	return $diff;
}