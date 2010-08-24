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

/**
 * @file
 * Stores all the subclasses of Exception
 */

/**
 * Generic Peachy Error
 * 
 * @package Peachy_Exceptions
 */
class PeachyError extends Exception {

	public function __construct( $code, $text ) {
		parent::__construct( 
			"API Error: " . $code . " (" . $text . ")"
		);
	}
}

/**
 * Generic API Error
 * 
 * @package Peachy_Exceptions
 */
class APIError extends Exception {

	public function __construct( $error ) {
		parent::__construct( 
			"API Error: " . $error['code'] . " (" . $error['text'] . $error['info'] . ")"
		);
	}
}

/**
 * Error with user permissions
 * 
 * @package Peachy_Exceptions
 */
class PermissionsError extends Exception {
	public function __construct( $error ) {
		parent::__construct( 
			"Permissions Error: " . $error
		);
	}
}

/**
 * Generic cURL Error
 * 
 * @package Peachy_Exceptions
 */
class CURLError extends Exception {
	private $errno;
	private $error;
	
	public function __construct( $errno, $error ) {
		$this->errno = $errno;
		$this->error = $error;
		
		parent::__construct( 
			"cURL Error (" . $this->errno . "): " . $this->error
		);
	}
	
	public function get_errno() {
		return $this->errno;
	}
	public function get_error() {
		return $this->error;
	}

}

/**
 * Invalid Title Error
 * 
 * @package Peachy_Exceptions
 */
class BadTitle extends Exception {
	
	private $title;
	
	public function __construct( $title ) {
		$this->title = $title;
		parent::__construct( 
			"Invalid title: $title"
		);
 
	}
	
	public function getTitle() {
		return $this->title;
	}
}

/**
 * No Title Error
 * 
 * @package Peachy_Exceptions
 */
class NoTitle extends Exception {

	public function __construct() {
		parent::__construct( 
			"No title or pageid stated when instantiating Page class"
		);
 
	}
}

/**
 * No User Error
 * 
 * @package Peachy_Exceptions
 */
class NoUser extends Exception {

	public function __construct( $title ) {
		parent::__construct( 
			"Non-existant user: $title"
		);
 
	}
}

/**
 * Blocked User Error
 * 
 * @package Peachy_Exceptions
 */
class UserBlocked extends Exception {

	public function __construct( $username = "User" ) {
		parent::__construct( 
			$username . " is currently blocked."
		);
 
	}

}

/**
 * Logged Out Error
 * 
 * @package Peachy_Exceptions
 */
class LoggedOut extends Exception {

	public function __construct() {
		parent::__construct( 
			"User is not logged in."
		);
 
	}

}

/**
 * Missing DependencyError Error
 * 
 * @package Peachy_Exceptions
 */
class DependencyError extends Exception {

	public function __construct( $software, $url = false ) {
		$message = "Missing dependency: \`" . $software . "\`. ";
		if( $url ) $message .= "Download from <$url>";
		parent::__construct( 
			$message
		);
 
	}

}

/**
 * Misspelling of "dependency", used for backwards compatibility
 * 
 * @package Peachy_Exceptions
 */
class DependancyError extends DependencyError {
	public function __construct( $software, $url = false ) {
		parent::__construct( $software, $url );
	}
}

/**
 * Login Error
 * 
 * @package Peachy_Exceptions
 */
class LoginError extends Exception {
	public function __construct( $error ) {
		parent::__construct( 
			"Login Error: " . $error[0] . " (" . $error[1] . ")"
		);
	}
}

/**
 * Peachy Hook Error
 * 
 * @package Peachy_Exceptions
 * @package Peachy_Hooks
 */
class HookError extends Exception {
	public function __construct( $error ) {
		parent::__construct( 
			"Hook Error: " . $error 
		);
	}
}

/**
 * Generic Database Error
 * 
 * @package Peachy_Exceptions
 * @package Peachy_Database
 */
class DBError extends Exception {
	public function __construct( $error, $errno, $sql = null ) {
		parent::__construct( 
			"Database Error: " . $error . " (code $errno) " . $sql
		);
	}
}

/**
 * Generic Edit Error
 * 
 * @package Peachy_Exceptions
 */
class EditError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Edit Error: " . $error . " ($text)"
		);
	}
}

/**
 * Generic Move Error
 * 
 * @package Peachy_Exceptions
 */
class MoveError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Move Error: " . $error . " ($text)"
		);
	}
}

/**
 * Generic Delete Error
 * 
 * @package Peachy_Exceptions
 */
class DeleteError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Delete Error: " . $error . " ($text)"
		);
	}
}

/**
 * Generic Undelete Error
 * 
 * @package Peachy_Exceptions
 */
class UndeleteError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Undelete Error: " . $error . " ($text)"
		);
	}
}

/**
 * Generic Protect Error
 * 
 * @package Peachy_Exceptions
 */
class ProtectError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Protect Error: " . $error . " ($text)"
		);
	}
}

/**
 * Generic Email Error
 * 
 * @package Peachy_Exceptions
 */
class EmailError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Email Error: " . $error . " ($text)"
		);
	}
}

/**
 * Generic Image Error
 * 
 * @package Peachy_Exceptions
 */
class ImageError extends Exception {
	public function __construct( $error ) {
		parent::__construct( 
			"Image Error: " . $error 
		);
	}
}

/**
 * Error for wrong parameters in a function
 * 
 * @package Peachy_Exceptions
 */
class BadEntryError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Bad Entry Error: " . $error . " ($text)"
		);
	}
}

/**
 * Generic XML Error
 * 
 * @package Peachy_Exceptions
 * @package XML
 */
class XMLError extends Exception {
	public function __construct( $error ) {
		parent::__construct( 
			"XML Error: " . $error
		);
	}
}

