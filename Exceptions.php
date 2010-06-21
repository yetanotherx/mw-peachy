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

class APIError extends Exception {

	public function __construct( $error ) {
		parent::__construct( 
			"API Error: " . $error['code'] . " (" . $error['text'] . $error['info'] . ")"
		);
	}
}

class PermissionsError extends Exception {
	public function __construct( $error ) {
		parent::__construct( 
			"Permissions Error: " . $error
		);
	}
}

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

class BadTitle extends Exception {

	public function __construct( $title ) {
		parent::__construct( 
			"Invalid title: $title"
		);
 
	}
}

class NoTitle extends Exception {

	public function __construct() {
		parent::__construct( 
			"No title or pageid stated when instantiating Page class"
		);
 
	}
}

class NoUser extends Exception {

	public function __construct( $title ) {
		parent::__construct( 
			"Non-existant user: $title"
		);
 
	}
}

class UserBlocked extends Exception {

	public function __construct( $username = "User" ) {
		parent::__construct( 
			$username . " is currently blocked."
		);
 
	}

}

class LoggedOut extends Exception {

	public function __construct() {
		parent::__construct( 
			"User is not logged in."
		);
 
	}

}

class DependencyError extends Exception {

	public function __construct( $software, $url = false ) {
		$message = "Missing dependency: \`" . $software . "\`. ";
		if( $url ) $message .= "Download from <$url>";
		parent::__construct( 
			$message
		);
 
	}

}

class LoginError extends Exception {
	public function __construct( $error ) {
		parent::__construct( 
			"Login Error: " . $error[0] . " (" . $error[1] . ")"
		);
	}
}

class HookError extends Exception {
	public function __construct( $error ) {
		parent::__construct( 
			"Hook Error: " . $error 
		);
	}
}

class DBError extends Exception {
	public function __construct( $error ) {
		parent::__construct( 
			"Database Error: " . $error 
		);
	}
}

class EditError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Edit Error: " . $error . " ($text)"
		);
	}
}

class MoveError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Move Error: " . $error . " ($text)"
		);
	}
}

class DeleteError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Delete Error: " . $error . " ($text)"
		);
	}
}

class UndeleteError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Undelete Error: " . $error . " ($text)"
		);
	}
}

class ProtectError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Protect Error: " . $error . " ($text)"
		);
	}
}

class EmailError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Email Error: " . $error . " ($text)"
		);
	}
}

class ImageError extends Exception {
	public function __construct( $error ) {
		parent::__construct( 
			"Image Error: " . $error 
		);
	}
}

class BadEntryError extends Exception {
	public function __construct( $error, $text ) {
		parent::__construct( 
			"Bad Entry Error: " . $error . " ($text)"
		);
	}
}

class XMLError extends Exception {
	public function __construct( $error ) {
		parent::__construct( 
			"XML Error: " . $error
		);
	}
}

