<?php

require_once 'CachingProxy.php';

// Handle the caching request

if (CachingProxyHelper::isRequesting()) {
	CachingProxyHelper::executeRequest();
	exit;
}

?>

<form action="<?=basename(__FILE__) ?>" method="POST">
Raw url:<br />
<input type="text" size="64" name="raw_url" value="<?=filter_input(INPUT_POST, 'raw_url') ?>" />
<input type="submit" value="Get encoded url" />
</form>

<?php if (filter_has_var(INPUT_POST, 'raw_url')) { ?>
<hr />
<a href="<?=  CachingProxyHelper::encodeProxyUrl(filter_input(INPUT_POST, 'raw_url')) ?>">
	<?=  CachingProxyHelper::encodeProxyUrl(filter_input(INPUT_POST, 'raw_url')) ?></a>

<?php } ?>