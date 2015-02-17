<?php

$f_sz = filter_input(INPUT_GET, 'f_sz', FILTER_VALIDATE_INT);
$f_bw = filter_input(INPUT_GET, 'f_bw', FILTER_VALIDATE_INT);
$f_dt = filter_input(INPUT_GET, 'f_dt', FILTER_VALIDATE_INT);

if ($f_dt) {
	usleep($f_dt * 1000);
}

header("HTTP/1.1 200 OK");
header('Content-Type: application/force-download');

if ($f_sz) {
	header('Content-Length: ' . $f_sz);
	
	do {
		
		$send_sz = $f_bw / 20;
		if ($send_sz <= 1) {
			$send_sz = 1;
		}

		echo str_repeat('B', $send_sz);
		
		$f_sz -= $send_sz;
		if ($f_sz > 0) {
			usleep(50 * 1000);
		}
		
	} while ($f_sz > 0);
}
