<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
ob_end_clean();
$APPLICATION->SetTitle("Title");


function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}


// function getDealInfoByID ($dealID) {
//     $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), array());

//     $resArr = array();
//     if($arDeal = $res->Fetch()){
//         return $arDeal;
//     }
//     return $resArr;
// }

// function findElementById($section_ID) {
//     $arElements = array();
//     $sections = array();
//     $res = CIBlockSection::GetList(Array(), array("IBLOCK_SECTION_ID" => $section_ID), true);

//     while ($ob = $res->GetNextElement()) {
//         $arFilds = $ob->GetFields();
//         $arProps = $ob->GetProperties();

//         $arPushs = array();
//         foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
//         foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
//         array_push($arElements, $arPushs);
//     }


//     for($i=0;$i<count($arElements);$i++){
//         if($arElements[$i]["IBLOCK_SECTION_ID"]==$section_ID ){
//             if(gbe_strpos("old",$arElements[$i]["NAME"])) {
//                 $sections[$arElements[$i]["ID"]]=$arElements[$i]["NAME"];
//             }
//         }
//     }
//     return $sections;
// }

// amas viyenebt
function getSectionIdAndName($section_ID) {
    $arElements = array();
    $sections=array();
    $res = CIBlockSection::GetList(Array(), array("IBLOCK_SECTION_ID" => $section_ID), true);

    while ($ob = $res->GetNextElement()) {
        // echo $ob;
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        array_push($arElements, $arPushs);
    }

    for($i=0;$i<count($arElements);$i++){
        if($arElements[$i]["IBLOCK_SECTION_ID"]==$section_ID ){
            $temp = array(
                "id" => $arElements[$i]["ID"],
                "name" => $arElements[$i]["NAME"]
            );

            array_push($sections, $temp);
        }
    }

    return $sections;
}
// end of amas viyenebt

// function gbe_strpos($search_string,$string){
//     if(strpos($string,$search_string) > - 1){
//         return false;
//     }
//     else{
//         return true;
//     }
// }

$data = array();
global $USER;

$USER_1 = $USER->GetID();
$userGroup  =   $USER->GetUserGroupArray();



$USER->Authorize(1);

$parentSections=array(13);

foreach ($parentSections as $parentSection) {
    $sections = getSectionIdAndName($parentSection);
}
$USER->Authorize($USER_1);


$data["result"] = $sections;
$data['status'] = 200;
json_encode($data);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_UNESCAPED_UNICODE);