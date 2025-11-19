<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('iblock');
ob_end_clean();
$APPLICATION->SetTitle("Title");

function getUserName ($id) {
    $res = CUser::GetByID($id)->Fetch();
    return $res["NAME"]." ".$res["LAST_NAME"];
}


function getContactName($id) {
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $id), array("ID", "FULL_NAME"));
    if($arContact = $res->Fetch()){
        return $arContact["FULL_NAME"];
    }
    return "";
}



function getNBG_inventory($date){

    $url="https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies?Currencies=USD&date={$date}";

    $seb = file_get_contents($url);

    $seb = json_decode($seb);

    $seb_currency=$seb[0]->currencies[0]->rate;

    return $seb_currency;
}



function getProductsByFilterS($proj) {
    // $sections,
    $arFilter = array(
            "IBLOCK_ID" => 14,
            "IBLOCK_SECTION_ID" => $proj
    );
    $arSelect = array("ID", "IBLOCK_ID","IBLOCK_SECTION_ID","DETAIL_PICTURE", "PROPERTY_*","STAGE_ID");
    $sort= array();
    $count = 99999;
    $nbg = getNBG_inventory(date("Y-m-d"));
    $arElements = array();

    $res = CIBlockElement::GetList($sort, $arFilter, false, array("nPageSize" => $count), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp){
            $fieldId = $arProp["ID"];
            $arPushs[$fieldId] = $arProp["VALUE"];
        }

        $arPushs["PRICE"] = CPrice::GetBasePrice($arPushs["ID"])["PRICE"];
        $arPushs["204"] = round($nbg*$arPushs["PRICE"],2);

        $arPushs["KVM_PRICE"] = CPrice::GetBasePrice($arPushs["ID"])["KVM_PRICE"];

        $arPushs["STATUS"] = $arPushs["71"];

        $arPushs["RESPONSIBLE"]='';
        $arPushs["RESPONSIBLE_NAME"]='';

        $arPushs["CONTACT_NAME"] = $arPushs["130"] ? getContactName($arPushs["130"]) : "";

        if(!empty($arPushs["89"])){
            $arPushs["RESPONSIBLE"] = $arPushs["89"];
            $arPushs["RESPONSIBLE_NAME"] = getUserName($arPushs["89"]);
        }

        if(!empty($arPushs["85"])){
            $queue = explode("|",$arPushs["85"]);

        }
        
        // $arPushs["218"] = CFile::GetPath($arPushs['218']);


        if($arPushs['218']){
            $arPushs['image'] = CFile::GetPath($arPushs['218']);
        }else{
            $arPushs['image'] = "https://crm.otium.ge/custom/img/noPhoto.svg";
        }

        if($arPushs['206']){
            $arPushs['image_plan'] =  CFile::GetPath($arPushs['206']);
        }else{
            $arPushs['image_plan'] = "https://crm.otium.ge/custom/img/noPhoto.svg";
        }

        if($arPushs['228']){
            $arPushs['image_plan_2d'] =  CFile::GetPath($arPushs['228']);
        }else{
            $arPushs['image_plan_2d'] = "https://crm.otium.ge/custom/img/noPhoto.svg";
        }


        $arPushs["DETAIL_PICTURE"] = CFile::GetPath($arPushs['DETAIL_PICTURE']);
        array_push($arElements, $arPushs);
    }
    return $arElements;
}

// function getCIBlockElementsByFilter($arFilter)
// {
//     $arElements = array();
//     $res = CIBlockElement::GetList(array("ID"=>"ASC"), $arFilter, false, Array("nPageSize" => 99999), array());
//     while ($ob = $res->GetNextElement()) {
//         $arFilds = $ob->GetFields();
//         $arProps = $ob->GetProperties();
//         $arPushs = array();
//         foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
//         foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
//         array_push($arElements, $arPushs);
//     }
//     return $arElements;
// }





$project = $_GET["project"];

$products = getProductsByFilterS($project);



$resArray = array();
if (!empty($project)) {
 
    if (sizeof($products) > 0) {
        $resArray["status"] = 200;
        $resArray["products"] = $products;
    }
    else {
        $resArray["status"] = 404;
        $resArray["error"] = "Not Found";
    }
}
else {
    $resArray["status"] = 400;
    $resArray["error"] = "Bad Request";
}


header('Content-Type: application/json; charset=utf-8');
echo json_encode($resArray , JSON_UNESCAPED_UNICODE);