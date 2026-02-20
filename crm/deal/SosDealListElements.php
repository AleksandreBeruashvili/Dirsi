<?php
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
ignore_user_abort(true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("iblock");

$startTime = time();
$timeLimit = 25; // seconds per batch
$batchSize = 1000; // elements per batch

// Direct SQL for faster deletion
global $DB;

// Get IDs in batches
$query = "
    SELECT ID
    FROM b_iblock_element
    WHERE IBLOCK_ID = 21
    LIMIT {$batchSize}
";

$rs = $DB->Query($query);
$deleted = 0;

$el = new CIBlockElement;

while($row = $rs->Fetch()) {
    $el->Delete($row["ID"]);
    $deleted++;
    
    // Time check
    if (time() - $startTime > $timeLimit) {
        break;
    }
}

echo "Deleted this batch: {$deleted}<br>";
echo "Time: " . date("H:i:s") . "<br>";

if ($deleted > 0) {
    echo "<script>setTimeout(function(){ location.reload(); }, 500);</script>";
    echo "Reloading in 0.5 sec...";
} else {
    echo "<strong>Done! All elements deleted.</strong>";
}
?>