<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
	'PHTH.' . $_EXTKEY,
	'Pongbackfrontend',
	array(
		'Pingback' => 'list, show',
		
	),
	// non-cacheable actions
	array(
		'Pingback' => '',
		
	)
);

?>