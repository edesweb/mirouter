<?php
/**
 * This script would be to check if the users is authenticated
 */



// If auth is ok then...
return (object)[
	'returnCode'    => 1,
	'returnReason'  => null
];



// If auth is not ok then...
return (object)[
	'returnCode'    => -1,
	'returnReason'  => 'The reason...'
];