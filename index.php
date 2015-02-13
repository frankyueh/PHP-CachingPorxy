<?php
require_once 'CachingProxy.php';

// Handle the caching request

if (CachingProxy::isRequesting()) {
	try {
		CachingProxy::executeRequest();
	} catch (CachingProxyException $ex) {
		header('HTTP/1.1 500 Internal Server Error');
		echo $ex->getMessage();
	} catch (\Exception $ex) {
		header('HTTP/1.1 500 Internal Server Error');
		echo (string) $ex;
	}
	exit;
}
?>

<form action="<?= basename(__FILE__) ?>" method="POST">
	Raw url:<br />
	<input type="text" size="64" name="raw_url" value="<?= filter_input(INPUT_POST, 'raw_url') ?>" />
	<input type="submit" value="Get encoded url" />
</form>

<?php
if (filter_has_var(INPUT_POST, 'raw_url')) {
	$encoded_url = CachingProxyHelper::encodeProxyUrl(filter_input(INPUT_POST, 'raw_url'));
	?>

	<hr />
	Pretty: <a href="
			<?= $encoded_url ?>">
			<?= $encoded_url ?></a><br />
	Normal: <a href="
			?<?= CachingConfigs::GET_URL_VAR . '=' . $encoded_url ?>">
			?<?= CachingConfigs::GET_URL_VAR . '=' . $encoded_url ?></a>

	<?php
	if (!empty(CachingConfigs::GET_REFRESH_VAR)) {
		?>

		<hr />
		Force refresh:<br />
		Pretty: <a href="
				<?= $encoded_url . '?' . CachingConfigs::GET_REFRESH_VAR . '=1' ?>">
				<?= $encoded_url . '?' . CachingConfigs::GET_REFRESH_VAR . '=1' ?></a><br />
		Normal: <a href="
				?<?= CachingConfigs::GET_URL_VAR . '=' . $encoded_url . '&' . CachingConfigs::GET_REFRESH_VAR . '=1' ?>">
				?<?= CachingConfigs::GET_URL_VAR . '=' . $encoded_url . '&' . CachingConfigs::GET_REFRESH_VAR . '=1' ?></a>

		<?php
	}
	?>
	<?php
}
?>