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
	const CACHE_SOCKET_TIMEOUT = 3;
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

		$raw_url = CachingProxyHelper::decodeProxyUrl($encoded_url);
		if (empty($raw_url)) {
			throw new CachingProxyException('Invalid url request.');
		}

		$cache_object = new CachingObject();
		
		$cache_object->cacheFile(
				$raw_url,
				!empty(CachingConfigs::GET_REFRESH_VAR) &&
					filter_has_var(INPUT_GET, CachingConfigs::GET_REFRESH_VAR)
		);
		
		$cache_object->outputFile();
	}

}

class CachingObject {

	private $_cache_file_header = false;
	private $_cache_file_content = false;
	
	private static function getStreamContextOptions() {
		
		return array(
			'http' => array(
				'timeout' => 
					CachingConfigs::CACHE_SOCKET_TIMEOUT > 0 ?
						CachingConfigs::CACHE_SOCKET_TIMEOUT : (int)ini_get('default_socket_timeout')
			)
		);
	}

	// Note that the HTTP wrapper has a hard limit of 1024 characters for the header lines.
	// (Can use cURL extension to fix this problem)
	private static function getHeadersAndContents($raw_url) {
		
		$context = stream_context_create(self::getStreamContextOptions());
		$contents = @file_get_contents(
				$raw_url, false, $context, 0, CachingConfigs::CACHE_FILE_MAX_SIZE);
		
		if (strlen($contents) >= CachingConfigs::CACHE_FILE_MAX_SIZE) {
			throw new CachingProxyException(
					'Unable to cache file which size reach the maximum limit of '.
					CachingConfigs::CACHE_FILE_MAX_SIZE.'.');
		}
		if ($contents === false) {
			throw new CachingProxyException('Unable get remote contents.');
		}
		return array(
			'header' => $http_response_header,
			'content' => $contents
		);
	}

	private function setCachFiles($raw_url) {
		if (empty($raw_url)) {
			throw new CachingProxyException('Empty url set.');
		}
		
		$this->_cache_file_header = CachingConfigs::CACHE_FILE_ROOT . sha1($raw_url) . '.header';
		$this->_cache_file_content = CachingConfigs::CACHE_FILE_ROOT . sha1($raw_url) . '.content';
	}

	private function isSetCacheFiles() {
		return !empty($this->_cache_file_header) && !empty($this->_cache_file_content);
	}

	public function __construct() {
		
	}

	public function cacheFile($raw_url, $force_refresh = false) {

		$this->setCachFiles($raw_url);

		$fp_header = fopen($this->_cache_file_header, 'c');
		if (!flock($fp_header, LOCK_EX)) {
			throw new CachingProxyException('Cache file exclusive lock failed.');
		}
		
		try {
			if ($force_refresh ||
					!file_exists($this->_cache_file_content) ||
						@filemtime($this->_cache_file_content) < (time() - CachingConfigs::CACHE_EXPIRE_TIME)) {

				$contents = self::getHeadersAndContents($raw_url);

				$serialized_header = serialize($contents['header']);
				if (fwrite($fp_header, $serialized_header) !== strlen($serialized_header)) {
					throw new CachingProxyException('Header file write failed unexpected.');
				}

				if (file_put_contents($this->_cache_file_content, $contents['content'], LOCK_EX) !== strlen($contents['content'])) {
					throw new CachingProxyException('Content file write failed unexpected.');
				}
			}
			
		} catch (\Exception $ex) {
			throw $ex;
			
		} finally {
			flock($fp_header, LOCK_UN);
			fclose($fp_header);
		}
	}

	public function outputFile() {
		if (!$this->isSetCacheFiles()) {
			throw new CachingProxyException('Cache file is not set.');
		}

		$fp_header = fopen($this->_cache_file_header, 'r');
		if (!flock($fp_header, LOCK_SH)) {
			throw new CachingProxyException('Cache file shared lock failed.');
		}
		
		try {
			$header_file_size = filesize($this->_cache_file_header);
			$unserialized_headers = fread($fp_header, $header_file_size);
			if (strlen($unserialized_headers) != $header_file_size) {
				throw new CachingProxyException('Header file read failed unexpected.');
			}

			$headers = @unserialize($unserialized_headers);
			if ($headers === false) {
				throw new CachingProxyException('Header file unserialize failed.');
			}

			foreach ($headers as $header) {
				header($header);
			}

			echo @file_get_contents($this->_cache_file_content);
			
		} catch (\Exception $ex) {
			throw $ex;
			
		} finally {
			flock($fp_header, LOCK_UN);
			fclose($fp_header);
		}
	}

}

abstract class CachingProxyHelper {

	public static function encodeProxyUrl($raw_url) {
		$url_path = parse_url($raw_url, PHP_URL_PATH);
		if ($url_path === false) {
			return false;
		}

		$names = explode('.', basename($url_path));
		$ext = count($names) > 1 ? '.' . array_pop($names) : '';

		return
				rawurlencode(strtr(base64_encode($raw_url), '+/', '-_')) . $ext;
	}

	public static function decodeProxyUrl($encoded_url) {
		$names = explode('.', rawurldecode($encoded_url));

		return
				filter_var(
				base64_decode(strtr(array_shift($names), '-_', '+/')), FILTER_SANITIZE_URL
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
