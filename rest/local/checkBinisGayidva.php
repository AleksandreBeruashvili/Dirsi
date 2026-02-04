<?php
ob_start();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Title");
CModule::IncludeModule('webservice');

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}


function getDealsByFilterBINISGAYIDVA($arFilter, $arSelect = array(), $arSort = array("ID"=>"DESC")) {

    $arDeals = array();
    $arSelect=array("ID","OPPORTUNITY","SOURCE_ID","CONTACT_ID","UF_CRM_1693398443196","UF_CRM_1700510912","UF_CRM_1702019032102","UF_CRM_1704806909","UF_CRM_1733496270818", "UF_CRM_1697108578810" , "UF_CRM_1733485628918" , "UF_CRM_1733486130558" , "UF_CRM_1733486949326" , "UF_CRM_1733487194024" , "UF_CRM_1734427590765" , "UF_CRM_1733487242872" , "UF_CRM_1733487405331" , "UF_CRM_1733487448695" , "UF_CRM_1733487522526" , "UF_CRM_1733487600274" , "UF_CRM_1760692022" , "UF_CRM_1712842020404");
    $res = CCrmDeal::GetListEx($arSort, $arFilter, false, false, $arSelect);
    while($arDeal = $res->Fetch()) array_push($arDeals, $arDeal);
    return (count($arDeals) > 0) ? $arDeals : false;
}

function getContactInfoBINISGAYIDVA($contactId) {
    $arContact = array();
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $contactId), array());
    if($arContact = $res->Fetch()){
        $EMAIL=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'EMAIL', 'VALUE_TYPE' => 'WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $PHONE=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'MOBILE|WORK|HOME', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $arContact["EMAIL"] = $EMAIL["VALUE"];
        $arContact["PHONE"] = $PHONE["VALUE"];

        return $arContact;
    }

    return $arContact;
}

function getCIBlockElementByFilterT($arFilter)
{    
    $res = CIBlockElement::GetList(array("property_date"=>"ASC"), $arFilter, false, Array("nPageSize" => 1), array());
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        return $arPushs;
    }
    return false;
}



function getCIBlockElementsByFilterBINISGAYIDVA($arFilter = array()) {
    $arElements = array();
    $arSelect = array("ID","NAME","PROPERTY_TANXA");
    $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>1000000), $arSelect);
    while($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        $arPushs["image"]    = CFile::GetPath($arPushs["DETAIL_PICTURE"]);
        $arPushs["image1"]    = CFile::GetPath($arPushs["PREVIEW_PICTURE"]);
        $price      = CPrice::GetBasePrice($arPushs["ID"]);
        $arPushs["PRICE"] = $price["PRICE"];

        array_push($arElements, $arPushs);
    }
    return $arElements;
}

$deal_ID = $_GET['dealID'];


$arFilter=array("ID"=>$deal_ID);
$deals = getDealsByFilterBINISGAYIDVA($arFilter);

$deal_price = $deals[0]["OPPORTUNITY"];


$arFilter=array(
    "IBLOCK_ID" =>20,
    "PROPERTY_DEAL" =>$deal_ID,
);

$ganvadebebi = getCIBlockElementsByFilterBINISGAYIDVA($arFilter);

$ganvadebebisJami = 0;

foreach($ganvadebebi as $ganvadeba){
    $tanxa = str_replace("|USD", "", $ganvadeba["PROPERTY_TANXA_VALUE"]);
    $tanxa = floatval(str_replace(",", "", $tanxa)); // აქ ვაქცევთ რიცხვად
    $ganvadebebisJami += $tanxa;
}

//  printArr($deal_price);
//  printArr($ganvadebebi);
//  printArr($ganvadebebisJami);

$contactID = $deals[0]["CONTACT_ID"];

$hasAllFieldsSelected = "no";

if($contactID){
    $contact=getContactInfoBINISGAYIDVA($contactID);
    $fieldsToCheck = [
        "სახელი" => $contact["NAME"],
        "გვარი" => $contact["LAST_NAME"],
//        "ტელეფონი" => $contact["PHONE"],
//        "მეილი" => $contact["EMAIL"],
        // "დაბადების თარიღი" => $contact["BIRTHDATE"],
//        "ფაქტიური მისამრთი" => $contact["UF_CRM_1761653727005"],
//        "იურიდიული მისამრთი" => $contact["UF_CRM_1761653738978"],
//        "მოქალაქეობა" => $contact["UF_CRM_1761651978222"],
//        "ნაციონალობა" => $contact["UF_CRM_1769506891465"],

    ];
    
    $missingFields = [];


    if(!$contact["UF_CRM_1761651998145"] && !$contact["UF_CRM_1761652010097"]){
        $missingFields[] = "პირადი ნომერი|პასპორტი";
    }
    
    foreach ($fieldsToCheck as $fieldName => $fieldValue) {
        if (empty($fieldValue)) {
            $missingFields[] = $fieldName;
        }
    }


    if (empty($missingFields)) {
        $hasAllFieldsSelected = "yes";
    } else {
        $hasAllFieldsSelected = "no";
        $missingFieldsString = ": " . implode(", ", $missingFields);
    }

}


$resArr=array();

if($hasAllFieldsSelected == "no"){
    $resArr["status"] = 400;
    $resArr["message"] = "კონტაქტზე არაა შევსებული სავალდებულო ველები! $missingFieldsString";
} elseif (round(floatval($deal_price), 2) != round(floatval($ganvadebebisJami), 2)) {
    $resArr["status"] = 400;
    $resArr["message"] = "აღნიშნული გარიგების ფასი არ ემთხვევა განვადების ჯამს!";
}
else{
    $resArr["status"] = 200;
    $resArr["message"] = "Sold Succesfully";
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($resArr, JSON_UNESCAPED_UNICODE);
?>
