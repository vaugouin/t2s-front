<?php
$strquerystring=(isset($_SERVER['QUERY_STRING'])?$_SERVER['QUERY_STRING']:"");
header("Location: index.php".($strquerystring!=""?"?".$strquerystring:""),true,301);
exit;
?>
