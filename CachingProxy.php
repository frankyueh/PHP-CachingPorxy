<?php

class CachingConfigs {
	
	// Where cached file stored (must tail with '/')
	const CACHE_FILE_ROOT = 'c:/webserver/tmp/';
	
	// Expiring time for cached fie (in second)
	const CACHE_EXPIRE_TIME = 86400; // 24 * 60 * 60
	
	// Url get variable name
	const GET_VAR_NAME = 'url';

	// Maximum size of cached file size (in byte)
	const CACHE_FILE_MAX_SIZE = 10485760; // 10 * 1024 * 1024
}

class CachingObject {
	
	private $_cache_file_header = false;
	private $_cache_file_content = false;
	
	private function setCachFiles($raw_url) {
		$this->_cache_file_header = CachingConfigs::CACHE_FILE_ROOT . sha1($raw_url) . '.header';
		$this->_cache_file_content = CachingConfigs::CACHE_FILE_ROOT . sha1($raw_url) . '.content';
	}
	
	private function isSetCacheFiles() {
		return !empty($this->_cache_file_header) && !empty($this->_cache_file_content);
	}
	
	public function __construct() {
	}
	
	public function cacheFile($raw_url) {

		$this->setCachFiles($raw_url);
		
		$fp_header = fopen($this->_cache_file_header, 'c');
		if (!flock($fp_header, LOCK_EX)) {
			return false;
		}
		
		if (!file_exists($this->_cache_file_content) ||
				filemtime($this->_cache_file_content) < (time() - CachingConfigs::CACHE_EXPIRE_TIME)) {
			
			$success = false;
			do {
				
				$contents = CachingProxyUtility::getHeadersAndContents($raw_url);
				
				if ($contents === false) {
					break;
				}

				$serialized_header = serialize($contents['header']);
				if (fwrite($fp_header, $serialized_header) !== strlen($serialized_header)) {
					break;
				}

				if (file_put_contents($this->_cache_file_content, $contents['content'], LOCK_EX) !== strlen($contents['content'])) {
					break;
				}
				
				$success = true;
			} while (false);

			flock($fp_header, LOCK_UN);
			fclose($fp_header);
			return $success;
		}
		
		flock($fp_header, LOCK_UN);
		fclose($fp_header);
		return true;
	}
	
	public function outputFile() {
		if (!$this->isSetCacheFiles()) {
			return false;
		}
		
		$fp_header = fopen($this->_cache_file_header, 'r');
		if (!flock($fp_header, LOCK_SH)) {
			return false;
		}
		
		$success = false;
		do {
			
			$header_file_size = filesize($this->_cache_file_header);
			$unserialized_headers = fread($fp_header, $header_file_size);
			if (strlen($unserialized_headers) != $header_file_size) {
				break;
			}
			
			$headers = @unserialize($unserialized_headers);
			if ($headers === false) {
				break;
			}
			
			foreach ($headers as $header) {
				header($header);
			}
			
			echo @file_get_contents($this->_cache_file_content);

			$success = true;
		} while (false);
		
		flock($fp_header, LOCK_UN);
		fclose($fp_header);
		
		return $success;
	}
}

abstract class CachingProxyHelper {
	
	public static function encodeProxyUrl($raw_url) {
		$url_path = parse_url($raw_url, PHP_URL_PATH);
		if ($url_path === false) {
			return false;
		}
		$ext = @array_pop(explode('.', $url_path));
		
		return
			rawurlencode(strtr(base64_encode($raw_url), '+/', '-_')) .
			(!empty($ext) ? '.'.$ext : '');
	}
	
	public static function decodeProxyUrl($encoded_url) {
		return
			filter_var(
					base64_decode(
							strtr(
									@array_shift(
											explode('.', rawurldecode($encoded_url))
									),
									'-_',
									'+/'
							)
					),
					FILTER_SANITIZE_URL
			);
	}
	
	public static function isRequesting() {
		return filter_has_var(INPUT_GET, CachingConfigs::GET_VAR_NAME);
	}
	
	public static function executeRequest() {
		 
		$encoded_url = filter_var(
				filter_input(INPUT_GET, CachingConfigs::GET_VAR_NAME), FILTER_SANITIZE_URL);
		
		if (empty($encoded_url)) {
			CachingProxyHeaders::header404();
			return false;
		}
		
		$raw_url = Self::decodeProxyUrl($encoded_url);
		if (empty($raw_url)) {
			CachingProxyHeaders::header500();
			return false;
		}
		
		$cache_object = new CachingObject();
		
		if (!$cache_object->cacheFile($raw_url)) {
			CachingProxyHeaders::header500();
			return false;
		}
		
		if (!$cache_object->outputFile()) {
			CachingProxyHeaders::header500();
			return false;
		}
		
		return true;
	}
}

abstract class CachingProxyHeaders {
	
	public static function header404() {
		header('HTTP/1.0 404 Not Found');
	}
	public static function header500() {
		header('HTTP/1.1 500 Internal Server Error');
	}
}

abstract class CachingProxyUtility {
	
	// Note that the HTTP wrapper has a hard limit of 1024 characters for the header lines.
	// (Can use cURL extension to fix this problem)
	public static function getHeadersAndContents($raw_url) {
		$contents = @file_get_contents($raw_url, null, null, 0, CachingConfigs::CACHE_FILE_MAX_SIZE);
		if ($contents === false) {
			return false;
		}
		return array(
			'header' => $http_response_header,
			'content' => $contents
		);
	}
}