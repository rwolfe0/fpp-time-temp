<?php

# One entry per menu area. $plugin and $menu are set by FPP before this runs.
$menuEntries = Array(
	Array(
		'text' => 'Time & Temperature',
		'type' => 'content',
		'page' => 'settings.php',
		'wrap' => 1
	),
	Array(
		'text' => 'Time & Temperature - Help',
		'type' => 'help',
		'page' => 'about.php',
		'wrap' => 1
	)
);

foreach ($menuEntries as $entry)
{
	if ($entry['type'] != $menu)
		continue;

	$nopage = '';
	if (isset($entry['wrap']) && ($entry['wrap'] == 0))
		$nopage = '&nopage=1';

	printf("<li><a href='plugin.php?plugin=%s&page=%s%s'>%s</a></li>\n",
		$plugin, $entry['page'], $nopage, $entry['text']);
}
?>
