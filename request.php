<?php

require_once 'CachingProxy.php';

try {
	CachingProxy::executeRequest();
	
} catch (CachingProxyException $ex) {
	
	header('HTTP/1.1 500 Internal Server Error');
	echo $ex->getMessage();
	
} catch (\Exception $ex) {
	
	header('HTTP/1.1 500 Internal Server Error');
	echo (string) $ex;
}
