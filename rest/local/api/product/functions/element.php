<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('iblock');


use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock')) {
    http_response_code(500);
    die(json_encode(['error' => 'iblock module not installed/loaded']));
}

function getProductsByFilter($arFilter = array(),$arSelect = array("ID", "IBLOCK_ID","DETAIL_PICTURE", "STATUS","FLOOR","OWNER_DEAL","NUMBER","TOTAL_AREA"),$sort= array(),$count =10)
{
    $arElements = array();

    $res = CIBlockElement::GetList($sort, $arFilter, false, array("nPageSize" => $count), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();

        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp){
            if(in_array($key,$arSelect)) {
                $arPushs[$key] = $arProp["VALUE"];
            }
        }

        $arPushs["PRICE"] = CPrice::GetBasePrice($arPushs["ID"])["PRICE"];
        $arPushs["DETAIL_PICTURE"] = CFile::GetPath($arPushs['DETAIL_PICTURE']);

        array_push($arElements, $arPushs);
    }
    return $arElements;
}

function getCIBlockElementsByFilter($arFilter,$arSelect=Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*"),$arSort=array("ID"=>"DESC"),$count=990990)
{
    $arElements = array();
    $res = CIBlockElement::GetList($arSort, $arFilter, false, Array("nPageSize" => $count), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        array_push($arElements, $arPushs);
    }
    return $arElements;
}

function getProductDataByID($ID)
{
    $arElements=array();

    if(is_numeric($ID)) {
        $arElements = array();
        $arSelect = array("ID", "IBLOCK_ID", "IBLOCK_SECTION_ID", "PRICE", "NAME", "DATE_ACTIVE_FROM", "PREVIEW_PICTURE", "PROPERTY_*");
        $res = CIBlockElement::GetList(array("376" => "DESC"), array("ID" => $ID), false, array("nPageSize" => 99999), $arSelect);
        while ($ob = $res->GetNextElement()) {
            $arFilds = $ob->GetFields();
            $arProps = $ob->GetProperties();
            $arPushs = array();
            foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
            foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
            foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
            $price = CPrice::GetBasePrice($arPushs["ID"]);
//        printArr($arPushs["PREVIEW_PICTURE"]);
//        $picture    = CFile::GetPath($arPushs["PREVIEW_PICTURE"]);

            $arPushs["PRICE"] = $price["PRICE"];
            array_push($arElements, $arPushs);
        }
    }
    return $arElements;
}


function addCIBlockElement($arForAdd, $arProps = array())
{
    $el = new CIBlockElement;
    $arForAdd["PROPERTY_VALUES"] = $arProps;
    if ($PRODUCT_ID = $el->Add($arForAdd)) return $PRODUCT_ID;
    else return 'Error: ' . $el->LAST_ERROR;
}


function getElementByID($ID)
{
    $arElements = array();
    if($ID && is_numeric($ID)) {
        $arSelect = array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
        $res = CIBlockElement::GetList(array(), array("ID" => $ID), false, array("nPageSize" => 99999), $arSelect);
        if ($ob = $res->GetNextElement()) {
            $arFilds = $ob->GetFields();
            $arProps = $ob->GetProperties();
            $arPushs = array();
            foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
            foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];

            return $arPushs;
        }
    }
    return $arElements;
}
