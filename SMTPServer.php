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

interface SMTPStore
{
	public function sendMail($from, array $to, $message);
}

class SMTPServer extends socketServer
{
	private $store;
	
	public function setStore(SMTPStore $store)
	{
		$this->store = $store;
	}
	
	public function sendMail($from, array $to, $message)
	{
		$this->store->sendMail($from, $to, $message);
	}
	
	public function on_accept(socketServerClient $client)
	{
		$client->setServer($this);
	}
}

class SMTPServerClient extends socketServerClient
{
	private $server;
	private $last_action;
	private $hostname;
	private $mail_from;
	private $mail_to = array();
	private $sending_data = false;
	private $quit = false;
	private $message = array();
	
	public function setServer(smtpServer $server)
	{
		$this->server = $server;
	}
	
	public function on_read()
	{
		if (strpos($this->read_buffer, "\r\n") !== false) {
			$reads = explode("\r\n", $this->read_buffer);
			foreach ($reads as $read) {
				$this->handleRead($read);
			}
		}
		$this->read_buffer = '';
	}

	private function handleRead($read)
	{
		echo 'onread: ' . str_replace(str_split("\r\n"), array('\r', '\n'), $read) . "\n";
		$this->last_action = time();
		
		if ($this->sending_data) {
			if (trim($read) === '.') {
				$this->sending_data = false;
				$this->writeClean('250 Ok: queued as 12345');
				$this->server->sendMail(
					$this->mail_from,
					$this->mail_to,
					implode("\r\n", $this->message)
				);
			} else {
				$this->message[] = $read;
			}
		} else if (strtoupper(substr($read, 0, 4)) === 'QUIT') {
			$this->quit = true;
			$this->writeClean('221 Bye');
		} else if (strtoupper(substr($read, 0, 4)) === 'EHLO') {
			$this->hostname = substr($read, 5);
			$this->writeClean('250-smtp2.example.com Hello '.$this->hostname.' ['.$this->remote_address.']');
			$this->writeClean('250-SIZE 14680064');
			$this->writeClean('250-PIPELINING');
			$this->writeClean('250 HELP');
		} else if (strtoupper(substr($read, 0, 4)) === 'HELO') {
			$this->hostname = trim(substr($read, 5));
			$this->writeClean('250 Hello '.$this->hostname.', I am glad to meet you');
		} else if (strtoupper(substr($read, 0, 10)) === 'MAIL FROM:') {
			$this->mail_from = substr($read, 10);
			$this->writeClean('250 Ok');
		} else if (strtoupper(substr($read, 0, 8)) === 'RCPT TO:') {
			$this->mail_to[] = substr($read, 8);
			$this->writeClean('250 Ok');
		} else if (strtoupper(substr($read, 0, 4)) === 'DATA') {
			$this->sending_data = true;
			$this->writeClean("354 End data with <CR><LF>.<CR><LF>");
		}
	}
	
	public function write($buffer, $length = 4096)
	{
		echo 'onwrite: ' . $buffer;
		return parent::write($buffer, $length);
	}
	
	public function writeClean($buffer, $length = 4096)
	{
		$this->write($buffer . "\r\n", $length);
	}

	public function on_connect()
	{
		$this->last_action = time();
		echo "[".__CLASS__."] accepted connection from {$this->remote_address}\n";
		$this->write("220 smtp2.example.com ESMTP PHPsmtpd\r\n");
	}

	public function on_disconnect()
	{
		echo "[".__CLASS__."] {$this->remote_address} disconnected\n";
	}

	public function on_write()
	{
		$this->last_action = time();
		if (strlen($this->write_buffer) == 0 && $this->quit) {
			$this->disconnected = true;
			$this->on_disconnect();
			$this->close();
		}
	}

	public function on_timer()
	{
		$idle_time  = time() - $this->last_action;
		if ($idle_time > 15) {
			echo "[".__CLASS__."] Client timeout exceeded ({$this->remote_address})\n";
			$this->close();
		}
	}
}
