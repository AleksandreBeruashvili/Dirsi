<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
ob_end_clean();
$APPLICATION->SetTitle("Title");
CModule::IncludeModule("main");

// --------------------- functions --------------------
function printArr ($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getNBG_inventory($date){

    $url="https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies?Currencies=USD&date={$date}";
    
    $seb = file_get_contents($url);
    
    $seb = json_decode($seb);
    
    $seb_currency=$seb[0]->currencies[0]->rate;
    
    return $seb_currency;
}


function getUserName ($id) {
    $res = CUser::GetByID($id)->Fetch();

    return $res["NAME"]." ".$res["LAST_NAME"];
}

function getContactInfo($contactId) {
    $arContact = array();
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $contactId), array());
    if($arContact = $res->Fetch()){
        $PHONE=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'MOBILE|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $MAIL=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'EMAIL', 'VALUE_TYPE' => 'HOME|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $arContact["PHONE"] = $PHONE["VALUE"];
        $arContact["EMAIL"] = $MAIL["VALUE"];
        return $arContact;
    }
    return $arContact;
}

function getProducts($projId = null, $blockId = null) {
    $arFilter = array(
            "IBLOCK_ID" => 14
    );

    if (!is_null($projId) && $projId !== '') {
        $arFilter["IBLOCK_SECTION_ID"] = $projId;
    }

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
            $fieldId = $arProp["CODE"];
            $arPushs[$fieldId] = $arProp["VALUE"];
        }

        if ($blockId !== null) {
            if (is_array($blockId)) {
                if (!in_array($arPushs["KORPUSIS_NOMERI_XE3NX2"], $blockId)) continue; // skip this element
            }
        }

        if ($arPushs["OWNER_CONTACT"]) {
            $arPushs["OWNER_CONTACT_NAME"] = getContactInfo($arPushs["OWNER_CONTACT"])["FULL_NAME"];
        }

        if ($arPushs["DEAL_RESPONSIBLE"]) {
            $arPushs["DEAL_RESPONSIBLE_NAME"] = getUserName($arPushs["DEAL_RESPONSIBLE"]);
        }

        $image = CFile::GetPath($arPushs['render_3D']); 
        if ($image) {
            $image = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $image;
        } else {
            $image = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/catalog/projects/resources/noimage.jpg";
        }
        $arPushs['image'] = $image;

        $image2 = CFile::GetPath($arPushs['binis_gegmareba']);
        if ($image2) {
            $image2 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $image2;
        } else {
            $image2 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/catalog/projects/resources/noimage.jpg";
        }
        $arPushs['image2'] = $image2;

        $image3 = CFile::GetPath($arPushs['binis_naxazi']);
        if ($image3) {
            $image3 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $image3;
        } else {
            $image3 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/catalog/projects/resources/noimage.jpg";
        }
        $arPushs['image3'] = $image3;

        $price = CPrice::GetBasePrice($arPushs["ID"]);
        $arPushs["PRICE"] = isset($price["PRICE"]) ? round($price["PRICE"], 2) : 0;

        array_push($arElements, $arPushs);
    }
    return $arElements;
}


// --------------------- main code --------------------

$projId = isset($_GET['projId']) ? $_GET['projId'] : null;
$blockId = isset($_GET['blockId']) ? $_GET['blockId'] : null;

if(!is_array($blockId) && $blockId !== null){
    $blockId = [$blockId];
}
$resArray["products"] = getProducts($projId, $blockId);

$blocks = [];
$apartmentTypes = [];
$statuses = [];
$buildings = [];
if (!empty($resArray["products"])) {
    foreach ($resArray["products"] as $product) {
        if (isset($product["KORPUSIS_NOMERI_XE3NX2"]) && $product["KORPUSIS_NOMERI_XE3NX2"] !== null && $product["KORPUSIS_NOMERI_XE3NX2"] !== '') {
            $apartmentTypes[] = $product["PRODUCT_TYPE"];
            $statuses[] = $product["STATUS"];
            $buildings[] = $product["BUILDING"];
            $blocks[] = $product["KORPUSIS_NOMERI_XE3NX2"];
        }
    }
    $apartmentTypes = array_values(array_unique($apartmentTypes)); 
    $statuses = array_values(array_unique($statuses)); 
    $buildings = array_values(array_unique($buildings)); 
    $blocks = array_values(array_unique($blocks)); 

    // Sort alphabetically and numerically
    natsort($blocks);          // Natural order sorting (numbers & letters)
    natsort($buildings);
    $blocks = array_values($blocks); // Reindex after sorting
    $buildings = array_values($buildings); // Reindex after sorting
}
$resArray["apartmentTypes"] = $apartmentTypes;
$resArray["statuses"] = $statuses;
$resArray["buildings"] = $buildings;
$resArray["blocks"] = $blocks;
// printArr($resArray);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($resArray, JSON_UNESCAPED_UNICODE);
?>