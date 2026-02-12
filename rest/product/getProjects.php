<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Title");
global $USER;
$user_id = $USER->GetID();

$authodizedUser = false;
if(empty($user_id)) {
    $USER->Authorize(1);
    $authodizedUser = true;
}

function getSections($arFilter = array()) {
    $sections = array();

    $res = CIBlockSection::GetList(Array(), array("IBLOCK_ID" => 14, "IBLOCK_SECTION_ID" => 13), true);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];

        if($arPushs["IBLOCK_SECTION_ID"] == 13) {
            $section = array(
                "ID" => $arPushs["ID"],
                "NAME" => $arPushs["NAME"],
            );
    
            array_push($sections, $section);
        }
    }

    return $sections;
}

$resArray = array();

$resProject = getSections();

if(count($resProject) > 0) {
    $resArray["status"] = 200;
    $resArray["message"] = "OK";
    $resArray["projects"] = $resProject;
} else {
    $resArray["status"] = 404;
    $resArray["message"] = "Project Not Found!";
    $resArray["result"] = $resProject;
}

if($authodizedUser) {
    $USER->Logout();
}
$user_id = $USER->GetID();

?>
<?
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($resArray, JSON_UNESCAPED_UNICODE);
