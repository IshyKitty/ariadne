<?php
	class WebDAV_files  {

		function WebDAV_files(&$webdav) {
			debug("webdav:files:init()");
			$this->webdav = $webdav;
			$this->store = $webdav->store;
		}

		function make_path($curr_dir, $path) {
			debug("webdav:files:make_path($curr_dir, $path)");
			$path = $this->store->make_path($curr_dir, $path);
			$result = eregi_replace('[^.a-z0-9/_-]', '_', $path);
			debug("webdav:files:make_path [$result]");
			return $result;
		}

		function propfind(&$options, &$files) {
			debug("webdav:files:propfind([path=".$options['path']."], [files])");

			$path = $this->make_path($options['path']);
			$parent = $this->make_path($options['path'], '..');
			$options['path'] = $path;

			if (!$this->store->exists($path)) {
				debug("webdav:files:propfind $path does not exist");
				return "404 Not Found";
			}

			$files['files'][] = $this->webdav->get_info(current($this->store->call('webdav.files.get.info.phtml', '', $this->store->get($path))));
			foreach ($this->store->call('webdav.files.get.info.phtml', '', $this->store->ls($path)) as $entry) {
				if ($entry) {
					$files['files'][] = $this->webdav->get_info($entry);
				}
			}
			debug("webdav:files:propfind properties");
			ob_start();
				print_r($files);
				debug(ob_get_contents());
			ob_end_clean();

			debug("webdav:files:propfind [end]");
			return true;
		}

		function head($options) {
			$path = $this->make_path($options['path']);
			$parent = $this->make_path($options['path'], '..');
			debug("webdav:files:head ($path)");

			if (!$this->store->exists($path)) {
				debug("webdav:files:head $path does not exist");
				$status = "404 Not Found";
			} else {
				$info = current($this->store->call('webdav.files.get.info.phtml', '',
						$this->store->get($path)));

				ldHeader("Last-Modified: ".gmstrftime("%a, %d %b %Y %H:%M:%S GMT", $info['props']['getlastmodified']));
				ldHeader("Content-Length: ".(int)$info['props']['getcontentlength']);

				$status = "200 OK";
			}
			debug("webdav:files:head [$status]");
			return $status;
		}

		function delete($options) {
			$path = $this->make_path($options['path']);
			debug("webdav:files:delete (path='$path')");
			$object = current($this->store->call('system.get.phtml', '', 
								$this->store->get($path)));			
			if (!$object) {
				debug("webdav:files:delete: $path not found or not readable");
				$status = "404 Not Found";
			} else {
				$info = $object->call('webdav.files.get.info.phtml');
				if (!$object->call('webdav.files.delete.phtml')) {
					if (!$info['props']['resourcetype'] != 'collection') {
					}
					$status = "403 Forbidden";
				} else {
					$status = "200 OK";
				}
			}
			debug("webdav:files:delete [$status]");
			return $status;
		}

		function lock( &$options ) {
			debug("method lock called");
		}

		function checkLock($path) {
			debug("method check lock");
		}

		function mkcol($options) {
			$path = $this->make_path($options['path']);
			$parent = $this->make_path($path, '..');
			$collection = substr($path, strlen($parent), -1);
			debug("webdav:files:mkcol (($parent)/$collection)");

			if (!$this->store->exists($parent)) {
				$status = "409 Forbidden";
			} else
			if ($this->store->exists($parent.$collection.'/')) {
				$status = "405 Method Not Allowed";
			} else {
				$status = current($this->store->call('webdav.files.create.collection.phtml', Array('collection' => $collection),
					$this->store->get($parent)));
			}
			debug("webdav:files:mkcol [$status]");
			return $status;
		}

		function get(&$options) {
			$path = $this->store->make_path('/', $options['path']);
			debug("webdav:files:get ($path)");
			$options['path'] = $path;

			if (!$this->store->exists($path)) {
				$status = "404 Not Found";
			} else {
				$info = current($this->store->call('webdav.files.get.file.phtml', '',
							$this->store->get($path)));
							
				foreach ($info['props'] as $key => $value) {
					$options[$key] = $value;
				}
				$options['data'] = $info['contents'];
				$status = "200 OK";
			}
			debug("webdav:files:get [$status]");
			return $status;
		}


		function move($options) {
			$path = $this->make_path($options['path']);
			$parent = $this->make_path($options['path'], '..');
			$dest = $this->make_path($options['dest']);
			$dparent = $this->store->make_path($dest, '..');
			debug("webdav:files:move ($path to $dest)");

			if (isset($options['dest_url'])) {
				$status = "502 Bad Gateway";
			} else
			if (!$this->store->exists($path)) {
				$status = "404 Not Found";
			} else
			if (!$this->store->exists($dparent)) {
				$status = "409 Conflict";
			} else {
				$status = current($this->store->call('webdav.files.move.phtml', Array('dest' => $dest),
							$this->store->get($path)));
			}
			debug("webdav:files:move [$status]");
			return $status;
		}

		function put(&$params) {
		global $ARCurrent;
			
			$filename = basename($params['path']);
			$path = $this->store->make_path($params['path']);
			$parent = $this->store->make_path($path, '..');
			$size = $params['content_length'];
			$stream = $params['stream'];

			debug("webdav:files:put ($path, [size:$size])");

			$temp = tempnam($this->store->files."temp/", "webdav");
			@unlink($temp);

			debug("webdav:files:put using tempfile; $temp");

			$fp = @fopen($temp, "w");
			if (!$fp) {
				debug("webdav:files:put error: could not open $temp for writing");
				$status = "204 No Content";
			} else {

				while (!feof($stream)) {
					fwrite($fp, fread($stream, 4096));
				}
				fclose($fp);

				include_once($this->store->code."modules/mod_mimemagic.php");
				$mime_type = get_mime_type($temp, MIME_DATA);
				if (!$mime_type) {
					$mime_type = get_mime_type($filename, MIME_EXT);
				}


				if (!$this->store->exists($parent."$filename/")) {
					$status = "201 Created";
				} else {
					$status = "204 No Content";
				}

				debug("webdav:files:put creating $filename in $parent");
				$result = current($this->store->call('webdav.files.put.phtml',
						Array( 'file' =>
							Array(
								'temp_name' => $temp,
								'name' => $filename,
								'type' => $mime_type,
								'size' => filesize($temp)
							)
						), $this->store->get($parent)));

				debug("webdav:files:put done creating");
				@unlink($temp);
			}

			debug("webdav:files:put [$status]");
			return $status;
		}



	} // end class definition
?>