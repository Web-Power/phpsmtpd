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
ini_set('output_handler', '');
error_reporting(E_ALL | E_STRICT);
@ob_end_flush();
set_time_limit(0);
include("phpsocketdaemon/socket.php");
include("SMTPServer.php");
include("MailLog.php");

$port = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : 3025; 

$daemon = new socketDaemon();
$server = new SMTPServer('SMTPServerClient', 0, $port);
$server->setStore(new MailStore());
$daemon->servers[(int) $server->socket] = $server;
$daemon->process();
