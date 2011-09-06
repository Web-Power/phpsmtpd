<?php
/*
phpsmtpd, Fake SMTP server for development purposes
Copyright (C) 2011 Web Power BV, http://www.webpower.nl

This library is free software; you can redistribute it and/or
modify it under the terms of the GNU Lesser General Public
License as published by the Free Software Foundation; either
version 2.1 of the License, or (at your option) any later version.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public
License along with this library; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*/

class MailStore implements SMTPStore
{
	/**
	 * Writes the mails to a log file
	 * @param string $from E-mail set using MAIL FROM
	 * @param array $to E-mail adresses set using RCPT TO
	 * @param string $message raw mailbody contains both the headers and message
	 */
	public function sendMail($from, array $to, $message)
	{
		file_put_contents(
			'mail.log',
			"
MAIL FROM: {$from}
RCPT TO: " . implode(', ', $to) . "
DATA:
" . $message,
			FILE_APPEND
		);
	}
}