<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/functions/element.php");
require($_SERVER["DOCUMENT_ROOT"]."/functions/functions.php");
ob_end_clean();
$APPLICATION->SetTitle("Title");

$data[57] = array();
$data[60] = array();
$data[61] = array();
$data[62] = array();
$data[63] = array();
$data[64] = array();
$data[65] = array();
$data[70] = array();
$data[71] = array();
$data[73] = array();
$data[86] = array();
$data[72] = array();




$filter = "e.IBLOCK_ID = 14";
$project = $_GET["projects"];
if($project) $filter .=  " and e.IBLOCK_SECTION_ID in ($project)";


$res = $DB->Query("
                    SELECT distinct IBLOCK_PROPERTY_ID,value FROM b_iblock_element_property ep inner join b_iblock_element e on ep.IBLOCK_ELEMENT_ID = e.ID
                    where $filter and IBLOCK_PROPERTY_ID in(57,60,61,62,63,64,65,70,71,72,73,86)
        ");
while ($ardata = $res->fetch()) array_push($data[$ardata["IBLOCK_PROPERTY_ID"]],$ardata["value"]);


header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_UNESCAPED_UNICODE);