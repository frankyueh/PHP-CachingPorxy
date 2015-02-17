<?php
require_once 'CachingProxy.php';

function util_post_value($name, $default = '') {
	return !empty(filter_input(INPUT_POST, $name)) ?
			filter_input(INPUT_POST, $name) : $default;
}

function util_make_full_uri($filename = '') {
	$request_uri = filter_input(INPUT_SERVER, 'REQUEST_URI');
	return 
		filter_input(INPUT_SERVER, 'REQUEST_SCHEME') . '://' .
		filter_input(INPUT_SERVER, 'HTTP_HOST') .
		substr($request_uri, 0, strrpos($request_uri, '/') + 1) .
		$filename;
}

$url_value = filter_input(INPUT_POST, 'url');
$encoded_url_value = basename(filter_input(INPUT_POST, 'encoded_url'));

if (filter_has_var(INPUT_POST, 'encoding') ||
		filter_input(INPUT_POST, 'action') == 'encoding') {
	
	$encoded_url_value = CachingProxyHelper::encodeProxyUrl($url_value);
	
} else if (filter_has_var(INPUT_POST, 'decoding') ||
		filter_input(INPUT_POST, 'action') == 'decoding') {
	
	$url_value = CachingProxyHelper::decodeProxyUrl($encoded_url_value);
}

?>

<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Caching Proxy</title>
</head>
<body>
	
<script>
function util_make_full_uri(filename) {
	return '<?= util_make_full_uri() ?>' + filename;
}
function generate_and_encode_test_url() {
	var form = document.forms['url_form'];
	form['url'].value = util_make_full_uri('testfile.php') + '?' +
			'f_sz=' + form['f_sz'].value + '&' +
			'f_bw=' + form['f_bw'].value + '&' +
			'f_dt=' + form['f_dt'].value;
	form['action'].value = 'encoding';
	form.submit();
}
</script>

<form action="<?= basename(__FILE__) ?>" name="url_form" method="POST">
	
	<h3>Input URL:</h3>
	<input type="hidden" name="action" value="" />
	<input type="text" size="50" name="url" value="<?= $url_value ?>" />
	<input type="submit" name="encoding" value="Encoding URL" /><br />
	<input type="text" size="50" name="encoded_url" value="<?= $encoded_url_value ?>" />
	<input type="submit" name="decoding" value="Decoding URL" />
	
	<h4>Generate URL to make request to testfile.php:</h4>
	Size (byte):
		<input type="text" size="10" name="f_sz" value="<?= util_post_value('f_sz', 200000) ?>" /><br />
	Bandwidth (byte/s):
		<input type="text" size="10" name="f_bw" value="<?= util_post_value('f_bw', 50000) ?>" /><br />
	Dalay time (first byte time, millisecond):
		<input type="text" size="10" name="f_dt" value="<?= util_post_value('f_dt', 100) ?>" /><br />
	<input type="button" name="encoding" value="Generate test URL" onclick="generate_and_encode_test_url()" />
		
</form>

<?php
if (!empty($url_value) && !empty($encoded_url_value)) {
	?>

	<hr />
	<h3>Encoded URL for Caching Proxy:</h3>
	
	Pretty: <a href="
			<?= util_make_full_uri($encoded_url_value) ?>">
			<?= util_make_full_uri($encoded_url_value) ?></a><br />
	Normal: <a href="
			<?= util_make_full_uri('request.php') . '?' . CachingConfigs::GET_URL_VAR . '=' . $encoded_url_value ?>">
			<?= util_make_full_uri('request.php') . '?' . CachingConfigs::GET_URL_VAR . '=' . $encoded_url_value ?></a>

	<?php
	if (!empty(CachingConfigs::GET_REFRESH_VAR)) {
		?>

		<h4>Force refresh:</h4>
		Pretty: <a href="
				<?= util_make_full_uri($encoded_url_value) . '?' . CachingConfigs::GET_REFRESH_VAR . '=1' ?>">
				<?= util_make_full_uri($encoded_url_value) . '?' . CachingConfigs::GET_REFRESH_VAR . '=1' ?></a><br />
		Normal: <a href="
				<?= util_make_full_uri('request.php') . 'request.php?' . CachingConfigs::GET_URL_VAR . '=' . $encoded_url_value . '&' . CachingConfigs::GET_REFRESH_VAR . '=1' ?>">
				<?= util_make_full_uri('request.php') . 'request.php?' . CachingConfigs::GET_URL_VAR . '=' . $encoded_url_value . '&' . CachingConfigs::GET_REFRESH_VAR . '=1' ?></a>

		<?php
	}
	?>
	<?php
}
?>

</body>
</html>
