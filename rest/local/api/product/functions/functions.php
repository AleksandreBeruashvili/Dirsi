<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");


use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock')) {
    http_response_code(500);
    die(json_encode(['error' => 'iblock module not installed/loaded']));
}


function printArr ($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}



function gbe_strpos($search_string,$string){
    if(strpos($string,$search_string) > -1){
        return false;
    }
    else{
        return true;
    }
}

function getDealByFilter ($arFilter,$arrSelect=array()) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), $arFilter, $arrSelect);

    $resArr = array();
    if($arDeal = $res->Fetch()){
        array_push($resArr,$arDeal);
    }
    return $resArr;
}


function getSectionIdAndName($section_ID) {
    $arElements = array();
    $sections = array();
    $res = CIBlockSection::GetList(Array(), array("IBLOCK_SECTION_ID" => $section_ID), true);

    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();

        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        array_push($arElements, $arPushs);
    }


    for($i=0;$i<count($arElements);$i++){
        if($arElements[$i]["IBLOCK_SECTION_ID"]==$section_ID ){
            if(gbe_strpos("old",$arElements[$i]["NAME"])) {
                $sections[$arElements[$i]["ID"]]=$arElements[$i]["NAME"];
            }
        }
    }
    return $sections;
}

function getSectionId($section_ID) {
    $arElements = array();
    $sections = array();
    $res = CIBlockSection::GetList(Array(), array("IBLOCK_SECTION_ID" => $section_ID), true);

    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        array_push($arElements, $arPushs);
    }


    for($i=0;$i<count($arElements);$i++){
        if($arElements[$i]["IBLOCK_SECTION_ID"]==$section_ID ){
            if(gbe_strpos("old",$arElements[$i]["NAME"])) {
                array_push($sections,$arElements[$i]["ID"]);
            }
        }
    }
    return $sections;
}


function getUserFullName($userid) {
    $arSelect = array('SELECT'=>array("ID","NAME","LAST_NAME","WORK_POSITION","UF_*"));
    $rsUsers = CUser::GetList(($by="NAME"), ($order="desc"), array("ID"=>$userid), $arSelect);
    if($arUser = $rsUsers->Fetch()) return "{$arUser['NAME']} {$arUser['LAST_NAME']}";
    else return 'unknown';
}


function getDealInfoByID ($dealID) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), array());

    $resArr = array();
    if($arDeal = $res->Fetch()){
        return $arDeal;
    }
    return false;
}



function monthsBetweenDates($date1,$date2){
    $dateTime1 = DateTime::createFromFormat('d/m/Y', $date1);
    $dateTime2 = DateTime::createFromFormat('d/m/Y', $date2);
    $interval = $dateTime1->diff($dateTime2);
    return  $interval->format('%m') + 12 * $interval->format('%y');
}


function dateWorkingDays($date){
    $holidays = getHolidays();
    $dateTime = DateTime::createFromFormat('d/m/Y', $date);

    while ($dateTime->format('N') >= 6 || (int)$dateTime->format('d') > 28 || in_array($dateTime->format('d/m/Y'),$holidays)) {
        $dateTime->modify('+1 day');
    }

    return $dateTime->format('d/m/Y');
}

function dateAddMonths($date,$month){
    $dateTime = DateTime::createFromFormat('d/m/Y', $date);
    $dateTime->modify("+$month months");


    return $dateTime->format('d/m/Y');
}


function getWorkflowFieldsKeyByValue ($property_id,$value) {
    $property_enums = CIBlockPropertyEnum::GetList(array("DEF" => "DESC", "SORT" => "ASC"), array("PROPERTY_ID"=>$property_id));
    while ($enum_fields = $property_enums->GetNext()) {
        if($enum_fields["VALUE"] == $value) return $enum_fields["ID"];
    }
    return false;
}


function getHolidays(){
    $holidaysElements = getCIBlockElementsByFilter(array("IBLOCK_ID" => 40));
    $holidays = array();
    foreach ($holidaysElements as $element){
        if(is_array($element["date"])) {
            $holidays = array_merge($holidays, $element["date"]);
        }
    }

    return $holidays;
}


function dateToNumbr($date){
    $dateARR=explode("/",$date);
    return $dateARR[2].$dateARR[1].$dateARR[0];
}