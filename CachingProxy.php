<?php

class CachingConfigs {

	// GET variable name for 'request url'
	const GET_URL_VAR = 'url';
	
	// GET variable name for 'force refresh cache file' (leave '' to disable this feature)
	const GET_REFRESH_VAR = 'refresh';
	
	// Where cached file stored (must tail with '/')
	const CACHE_FILE_ROOT = 'c:/webserver/tmp/';
	
	// Expiring time for cached fie (in second)
	const CACHE_EXPIRE_TIME = 86400; // 24 * 60 * 60
	
	// Maximum size of cached file size (in byte)
	const CACHE_FILE_MAX_SIZE = 3145728; // 3 * 1024 * 1024
	
	// Socket timeout for file_get_contents (in second)
	// NOTE: leave 0 for using php.ini default setting 'default_socket_timeout'
	const CACHE_SOCKET_TIMEOUT = 5;
}

abstract class CachingProxy {
	
	public static function isRequesting() {
		return filter_has_var(INPUT_GET, CachingConfigs::GET_URL_VAR);
	}

	public static function executeRequest() {

		$encoded_url = filter_var(
				filter_input(INPUT_GET, CachingConfigs::GET_URL_VAR), FILTER_SANITIZE_URL);

		if (empty($encoded_url)) {
			throw new CachingProxyException('Empty url request.');
		}

		$url = CachingProxyHelper::decodeProxyUrl($encoded_url);
		if (empty($url)) {
			throw new CachingProxyException('Invalid url request.');
		}

		$cache_object = new CachingObject();
		
		$cache_object->setUrl($url);
		
		if (!empty(CachingConfigs::GET_REFRESH_VAR) &&
					filter_has_var(INPUT_GET, CachingConfigs::GET_REFRESH_VAR)) {
			
			$cache_object->setForceRefresh($url);
		}
		
		$cache_object->cacheFile();
		$cache_object->outputFile();
	}

}

abstract class CachingProxyHelper {

	public static function encodeProxyUrl($url) {
		
		$url_path = parse_url($url, PHP_URL_PATH);
		if ($url_path === false) {
			return false;
		}
		
		$url_path_base = basename($url_path);
		$ext_rpos = strrpos($url_path_base, '.');
		$ext_name = $ext_rpos !== false ? substr($url_path_base, $ext_rpos) : '';

		return rawurlencode(strtr(base64_encode($url), '+/', '-_')) . $ext_name;
	}

	public static function decodeProxyUrl($encoded_url) {
		
		$ext_rpos = strrpos($encoded_url, '.');
		$encoded_url_noext = $ext_rpos !== false ? substr($encoded_url, 0, $ext_rpos) : $encoded_url;

		return
				filter_var(
						base64_decode(strtr(rawurldecode($encoded_url_noext), '-_', '+/')),
						FILTER_SANITIZE_URL
				);
	}

}

class CachingProxyException extends Exception {
	
	public function __construct($message = null, $code = 0, Exception $previous = null) {
		if ($message === null) {
			$message = "Unknown error occurred from caching proxy.";
		}
		parent::__construct($message, $code, $previous);
	}

	// custom string representation of object
	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}

}

class CachingObject {

	private $_url = false;
	private $_parsed_url = false;
	private $_cache_header_file = false;
	private $_cache_content_file = false;
	private $_force_refresh = false;
	
	private function setCachFiles($url) {
		
		$this->_cache_header_file = CachingConfigs::CACHE_FILE_ROOT . sha1($url) . '.header';
		$this->_cache_content_file = CachingConfigs::CACHE_FILE_ROOT . sha1($url) . '.content';
	}

	private function isSetCacheFiles() {
		
		return !empty($this->_cache_header_file) && !empty($this->_cache_content_file);
	}

	private function getStreamContextOptions() {
		
		return array(
			'http' => array(
				'method' => 'GET',
				'timeout' => 
					CachingConfigs::CACHE_SOCKET_TIMEOUT > 0 ?
						CachingConfigs::CACHE_SOCKET_TIMEOUT : (int)ini_get('default_socket_timeout')
			)
		);
	}

	// Note that the HTTP wrapper has a hard limit of 1024 characters for the header lines.
	// (Can use cURL extension to fix this problem)
	private function getHeadersAndContents() {
		
		$context = stream_context_create($this->getStreamContextOptions());
		$contents = @file_get_contents(
				$this->_url, false, $context, 0, CachingConfigs::CACHE_FILE_MAX_SIZE);
		
		if ($http_response_header === false) {
			throw new CachingProxyException('Unable to get remote contents from: ' . $this->_url);
		}
		
		if (strlen((string)$contents) >= CachingConfigs::CACHE_FILE_MAX_SIZE) {
			throw new CachingProxyException(
					'Unable to cache file which size reach the maximum limit of '.
					CachingConfigs::CACHE_FILE_MAX_SIZE.'.');
		}
		
		return array(
			'header' => $http_response_header,
			'content' => $contents
		);
	}
	
	public function __construct() {
		
	}
	
	public function setUrl($url) {
		
		if (empty($url)) {
			throw new CachingProxyException('Empty url set.');
		}
		
		$this->_url = $url;
		$this->_parsed_url = parse_url($url);
		if (!$this->_parsed_url) {
			throw new CachingProxyException('Invalid url set.');
		}
		
		$this->setCachFiles($url);
	}
	
	public function setForceRefresh($val = true) {
		$this->_force_refresh = $val;
	}
	
	public function isCacheFileExists() {
		
		if (!$this->isSetCacheFiles()) {
			throw new CachingProxyException('Cache file is not set.');
		}
		
		return file_exists($this->_cache_header_file);
	}

	public function cacheFile() {

		if (!$this->isSetCacheFiles()) {
			throw new CachingProxyException('Cache file is not set.');
		}

		$ignore_user_abort = ignore_user_abort(true);
		$fp_header = fopen($this->_cache_header_file, 'c');
		try {
			if ($fp_header === false) {
				throw new CachingProxyException('Unable to create/open cached file header.');
			}
			
			if (!flock($fp_header, LOCK_EX)) {
				throw new CachingProxyException('Cache file exclusive lock failed.');
			}
			
			if ($this->_force_refresh ||
					!file_exists($this->_cache_content_file) ||
						@filemtime($this->_cache_content_file) < (time() - CachingConfigs::CACHE_EXPIRE_TIME)) {

				$contents = $this->getHeadersAndContents();

				$serialized_headers = serialize($contents['header']);
				if (fwrite($fp_header, $serialized_headers) !== strlen($serialized_headers)) {
					throw new CachingProxyException('Header file write failed unexpected.');
				}

				if (file_put_contents($this->_cache_content_file, (string)$contents['content'], LOCK_EX) !==
						strlen((string)$contents['content'])) {
					throw new CachingProxyException('Content file write failed unexpected.');
				}

			}
			
		} catch (\Exception $ex) {
			throw $ex;
			
		} finally {
			if ($fp_header !== false) {
				flock($fp_header, LOCK_UN);
				fclose($fp_header);
			}
			ignore_user_abort($ignore_user_abort);
		}
	}

	public function outputFile() {
		
		if (!$this->isSetCacheFiles()) {
			throw new CachingProxyException('Cache file is not set.');
		}
		
		if (!file_exists($this->_cache_header_file)) {
			throw new CachingProxyException('Cache file is not exists.');
		}

		$fp_header = fopen($this->_cache_header_file, 'r');
		try {
			if ($fp_header === false) {
				throw new CachingProxyException('Unable to open cached file header.');
			}
			
			if (!flock($fp_header, LOCK_SH)) {
				throw new CachingProxyException('Cache file shared lock failed.');
			}

			$header_file_size = filesize($this->_cache_header_file);
			$unserialized_headers = fread($fp_header, $header_file_size);
			if (strlen($unserialized_headers) != $header_file_size) {
				throw new CachingProxyException('Header file read failed unexpected.');
			}

			$headers = @unserialize($unserialized_headers);
			if ($headers === false) {
				throw new CachingProxyException('Header file unserialize failed.');
			}

			if ($headers) {
				foreach ($headers as $header) {
					header($header);
				}
			}
			
			echo @file_get_contents($this->_cache_content_file);
			
		} catch (\Exception $ex) {
			throw $ex;
			
		} finally {
			if ($fp_header !== false) {
				flock($fp_header, LOCK_UN);
				fclose($fp_header);
			}
		}
	}

}
