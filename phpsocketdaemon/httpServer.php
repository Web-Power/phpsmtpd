<?php
/*
phpSocketDaemon 1.0 - httpd server demo implementation
Copyright (C) 2006 Chris Chabot <chabotc@xs4all.nl>
See http://www.chabotc.nl/ for more information

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
class httpdServer extends socketServer {
}

class httpdServerClient extends socketServerClient {
	private $max_total_time = 45;
	private $max_idle_time  = 15;
	private $keep_alive = false;
	private $accepted;
	private $last_action;

	private function handle_request($request)
	{

		if (!$request['version'] || ($request['version'] != '1.0' && $request['version'] != '1.1')) {
			// sanity check on HTTP version
			$header  = 'HTTP/'.$request['version']." 400 Bad Request\r\n";
			$output  = '400: Bad request';
			$header .= "Content-Length: ".strlen($output)."\r\n";
		} elseif (!isset($request['method']) || ($request['method'] != 'get' && $request['method'] != 'post')) {
			// sanity check on request method (only get and post are allowed)
			$header  = 'HTTP/'.$request['version']." 400 Bad Request\r\n";
			$output  = '400: Bad request';
			$header .= "Content-Length: ".strlen($output)."\r\n";
		} else {
			// handle request
			if (empty($request['url'])) {
				$request['url'] = '/';
			}
			if ($request['url'] == '/' || $request['url'] == '') {
				$request['url'] = '/index.html';
			}
			// parse get params into $params variable
			if (strpos($request['url'],'?') !== false) {
				$params = substr($request['url'], strpos($request['url'],'?') + 1);
				$params = explode('&', $params);
				foreach($params as $key => $param) {
					$pair = explode('=', $param);
					$params[$pair[0]] = isset($pair[1]) ? $pair[1] : '';
					unset($params[$key]);
				}
				$request['url'] = substr($request['url'], 0, strpos($request['url'], '?'));
			}

			$file = './htdocs'.$request['url'];
			if (file_exists($file) && is_file($file)) {
				$header  = "HTTP/{$request['version']} 200 OK\r\n";
				$header .= "Accept-Ranges: bytes\r\n";
				$header .= 'Last-Modified: '.gmdate('D, d M Y H:i:s T', filemtime($file))."\r\n";
				$size    = filesize($file);
				$header .= "Content-Length: $size\r\n";
				$output  = file_get_contents($file);
			} else {
				$output  = '<h1>404: Document not found.</h1>';
				$header  = "'HTTP/{$request['version']} 404 Not Found\r\n".
				"Content-Length: ".strlen($output)."\r\n";
			}
		}
		$header .=  'Date: '.gmdate('D, d M Y H:i:s T')."\r\n";
		if ($this->keep_alive) {
			$header .= "Connection: Keep-Alive\r\n";
			$header .= "Keep-Alive: timeout={$this->max_idle_time} max={$this->max_total_time}\r\n";
		} else {
			$this->keep_alive = false;
			$header .= "Connection: Close\r\n";
		}
		return $header."\r\n".$output;
	}

	public function on_read()
	{
		$this->last_action = time();
		if ((strpos($this->read_buffer,"\r\n\r\n")) !== FALSE || (strpos($this->read_buffer,"\n\n")) !== FALSE) {
			$request = array();
			$headers = split("\n", $this->read_buffer);
			$request['uri'] = $headers[0];
			unset($headers[0]);
			while (list(, $line) = each($headers)) {
				$line = trim($line);
				if ($line != '') {
					$pos  = strpos($line, ':');
					$type = substr($line,0, $pos);
					$val  = trim(substr($line, $pos + 1));
					$request[strtolower($type)] = strtolower($val);
				}
			}
			$uri                = $request['uri'];
			$request['method']  = strtolower(substr($uri, 0, strpos($uri, ' ')));
			$request['version'] = substr($uri, strpos($uri, 'HTTP/') + 5, 3);
			$uri                = substr($uri, strlen($request['method']) + 1);
			$request['url']     = substr($uri, 0, strpos($uri, ' '));
			foreach ($request as $type => $val) {
				if ($type == 'connection' && $val == 'keep-alive') {
					$this->keep_alive = true;
				}
			}
			$this->write($this->handle_request($request));
			$this->read_buffer  = '';
		}
	}

	public function on_connect()
	{
		//echo "[httpServerClient] accepted connection from {$this->remote_address}\n";
		$this->accepted    = time();
		$this->last_action = $this->accepted;
	}

	public function on_disconnect()
	{
		//echo "[httpServerClient] {$this->remote_address} disconnected\n";
	}

	public function on_write()
	{
		if (strlen($this->write_buffer) == 0 && !$this->keep_alive) {
			$this->disconnected = true;
			$this->on_disconnect();
			$this->close();
		}
	}

	public function on_timer()
	{
		$idle_time  = time() - $this->last_action;
		$total_time = time() - $this->accepted;
		if ($total_time > $this->max_total_time || $idle_time > $this->max_idle_time) {
			echo "[httpServerClient] Client keep-alive time exceeded ({$this->remote_address})\n";
			$this->close();
		}
	}
}

