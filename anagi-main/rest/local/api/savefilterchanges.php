<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
ob_end_clean();

function getCIBlockElementsByFilter($arFilter)
{
    $arElements = array();
    $res = CIBlockElement::GetList(array("ID"=>"ASC"), $arFilter, false, Array("nPageSize" => 99999), array());
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

function addCIBlockElement($arForAdd, $arProps = array()) {
    $el = new CIBlockElement;
    $arForAdd["PROPERTY_VALUES"] = $arProps;
    if ($PRODUCT_ID = $el->Add($arForAdd)) return $PRODUCT_ID;
    else return 'Error: ' . $el->LAST_ERROR;
}

$postJson = array();

try {
    $postJson = \Bitrix\Main\Web\Json::decode(\Bitrix\Main\HttpRequest::getInput());
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

$filters=$postJson["filters"];

$id=$postJson["user_id"];

$type=$postJson["type"];

$arElements=getCIBlockElementsByFilter(array("IBLOCK_ID"=>17,"PROPERTY_USER"=>intval($id)));

if($type=="catalog") {

    if (empty($arElements)) {

        $arForAdd = array(
            'IBLOCK_ID' => 17,
            'NAME' => "Info",
            'ACTIVE' => 'Y',
        );

        $arProps = array();
        $arProps["USER"] = intval($id);
        $arProps["FIELDS"] = $filters;

        $res = addCIBlockElement($arForAdd, $arProps);

    } else {

        $el = new CIBlockElement;
        $arElements[0]["FIELDS"] = $filters;

        $arLoadProductArray = array(
            "PROPERTY_VALUES" => $arElements[0],
            "NAME" => $arElements[0]["NAME"],
            "ACTIVE" => "Y",            // активен
        );
        $res = $el->Update($arElements[0]["ID"], $arLoadProductArray);

    }
}else if($type=="inventory"){

    if (empty($arElements)) {

        $arForAdd = array(
            'IBLOCK_ID' => 17,
            'NAME' => "Info",
            'ACTIVE' => 'Y',
        );

        $arProps = array();
        $arProps["USER"] = intval($id);
        $arProps["FIELDS_INV"] = $filters;

        $res = addCIBlockElement($arForAdd, $arProps);

    } else {

        $el = new CIBlockElement;
        $arElements[0]["FIELDS_INV"] = $filters;

        $arLoadProductArray = array(
            "PROPERTY_VALUES" => $arElements[0],
            "NAME" => $arElements[0]["NAME"],
            "ACTIVE" => "Y",            // активен
        );
        $res = $el->Update($arElements[0]["ID"], $arLoadProductArray);

    }

}


header('Content-Type: application/json; charset=utf-8');
echo json_encode($res, JSON_UNESCAPED_UNICODE);
?>