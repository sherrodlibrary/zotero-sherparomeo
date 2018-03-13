<?php
echo ("Running update script".PHP_EOL);
exec('./run_list.sh');
echo ("Done!".PHP_EOL);

header( 'Location: ../zotero' ) ;

?>
