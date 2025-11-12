<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/functions/element.php");
require($_SERVER["DOCUMENT_ROOT"]."/functions/functions.php");
$userID = $_GET["user_ID"];
$elements = getCIBlockElementsByFilter(array("IBLOCK_ID"=>20,"PROPERTY_USER"=>$userID));
$fieldsForHeader =array();
array_unshift($elements[0]["FIELDS"], '309');
foreach ($elements[0]["FIELDS"] as $neededFields){
    if($neededFields === "PRICE"){
        $needed["ID"]=$neededFields;
        $needed["NAME"]="სრული ფასი $";
        array_push($fieldsForHeader,$needed);
    }
    else if($neededFields === "SECTION_NAME"){
        $needed["ID"]=$neededFields;
        $needed["NAME"]="პროექტი";
        array_push($fieldsForHeader,$needed);
    }
    else{
        $res = $DB->Query("
                    SELECT ID,NAME FROM b_iblock_property   where ID = $neededFields
            ");
        while ($ardata = $res->fetch()) array_push($fieldsForHeader,array("ID"=>$ardata["ID"],"NAME"=>$ardata["NAME"]));
    }
}
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
echo json_encode($fieldsForHeader, JSON_UNESCAPED_UNICODE);