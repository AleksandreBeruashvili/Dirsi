<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Test Index Page");
use Bitrix\Main\Loader;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Crm\DealTable;

Loader::includeModule('crm');
if (!Loader::includeModule('iblock')) {
    http_response_code(500);
    die(json_encode(['error' => 'iblock module not installed/loaded']));
}

// ------------------------------FUNCTIONS---------------------------------
function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDealByFilter ($arFilter,$arrSelect=array()) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), $arFilter, $arrSelect);
    
    $resArr = array();
    if($arDeal = $res->Fetch()){
        array_push($resArr,$arDeal);
    }
    return $resArr;
}

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

function getDealProds($dealID) {
    $prods = CCrmDeal::LoadProductRows($dealID);
    $products = [];
    foreach ($prods as $prod) {
        $arFilter = array(
            "ID" => $prod["PRODUCT_ID"]
        );
        $each = getCIBlockElementsByFilter($arFilter);
        if($each[0]["IBLOCK_SECTION_ID"]!=31) {
            if ($each[0]["OWNER_CONTACT"]) {
                $each[0]["OWNER_CONTACT_NAME"] = getContactInfo($each[0]["OWNER_CONTACT"])["FULL_NAME"];
            }

            if ($each[0]["DEAL_RESPONSIBLE"]) {
                $each[0]["DEAL_RESPONSIBLE_NAME"] = getUserName($each[0]["DEAL_RESPONSIBLE"]);
            }

            $image = CFile::GetPath($each[0]['binis_naxazi']);
            if ($image) {
                $image = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $image;
            } else {
                $image = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/catalog/projects/resources/noimage.jpg";
            }
            $each[0]['image'] = $image;

            $image2 = CFile::GetPath($each[0]['binis_gegmareba']);
            if ($image2) {
                $image2 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $image2;
            } else {
                $image2 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/catalog/projects/resources/noimage.jpg";
            }
            $each[0]['image2'] = $image2;

            $image3 = CFile::GetPath($each[0]['render_3D']);
            if ($image3) {
                $image3 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $image3;
            } else {
                $image3 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/catalog/projects/resources/noimage.jpg";
            }
            $each[0]['image3'] = $image3;

            $price = CPrice::GetBasePrice($each[0]["ID"]);
            $each[0]["PRICE"] = isset($price["PRICE"]) ? round($price["PRICE"], 2) : 0;

            array_push($products, $each[0]);
        }
    }
    return $products;
}

function getProjects(){
    $IBLOCK_ID = 14;

    $arFilter = [
        "IBLOCK_ID" => $IBLOCK_ID,
        "IBLOCK_SECTION_ID" => 13, // parent section ID
        "DEPTH_LEVEL" => 2,        // second-level sections
        "ACTIVE" => "Y",
    ];

    $arSelect = [
        "ID",
        "NAME",
        "DEPTH_LEVEL",
        "IBLOCK_SECTION_ID" // parent section (if nested)
    ];

    $res = CIBlockSection::GetList(
        ["SORT" => "ASC"], // sort order
        $arFilter,
        false,
        $arSelect
    );

    $projects = [];
    while ($section = $res->GetNext()) {
        $projects[] = [
            "ID" => $section["ID"],
            "NAME" => $section["NAME"]
        ];
    }

    return $projects;
}

function getProductProperties() {
    $iblockId = 14;

    $propertiesArr = array();

    $properties = CIBlockProperty::GetList(
        ["SORT" => "ASC"], // Sort by the "SORT" field
        ["IBLOCK_ID" => $iblockId] // Filter by the IBlock ID
    );

    while ($propFields = $properties->Fetch()) {
        $thisArr = array(
            "ID" => $propFields["ID"],
            "NAME" => $propFields["NAME"],
            "CODE" => $propFields["CODE"],
        );

        array_push($propertiesArr, $thisArr);
    }

    return $propertiesArr;
}


// ------------------------------MAIN CODE---------------------------------

global $USER;

if ($USER->IsAuthorized()) {
    $userId = $USER->GetID();
    $userName = $USER->GetFullName();
    $userEmail = $USER->GetEmail();
} else {
    $userId = null;
}

// fetch deal if exists
$dealID = $_GET["dealid"] ?? null;
if($dealID) {
    $arFilter = array("ID" => $dealID);
    $deal=getDealByFilter($arFilter);
    
    // fetch products for the deal
    $products = getDealProds($dealID);
    $productsIds = array_column($products, 'ID');
}

// get projects
$projects = getProjects();
usort($projects, function($a, $b) {
    return strnatcasecmp($a['NAME'], $b['NAME']);
});

// get product properties for extra filter dropdown
$productProperties = getProductProperties();

ob_end_clean();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.js"
            integrity="sha256-H+K7U5CnXl1h5ywQfKtSj8PCmoN9aaq30gDh27Xc0jk=" crossorigin="anonymous"></script>
    <link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">


    <style>
        /* ------------------------ GLOBAL STYLES ------------------------ */
        /* ===================== BODY & GENERAL ===================== */
        body {
            margin: 0;
            font-family: "Inter", Arial, sans-serif;
            background: #f2f2f2; /* soft creamy background */
            color: #1a1a1f; /* main text */
        }

        /* ===================== CONTAINER ===================== */
        .containerCatalog {
            display: flex;
            padding: 20px;
        }

        /* ===================== FILTER BOX ===================== */
        #filterContainer {
            width: 220px;
            height: fit-content;
            padding: 16px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);
            position: sticky;
            top: 20px;
        }

        #filterContainer h2 {
            margin: 12px 0;
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1f;
        }

        /* ===================== SELECTS ===================== */
        #filterContainer select {
            width: 100%;
            margin-bottom: 12px;
            padding: 8px;
            border-radius: 8px;
            border: 1px solid #ccc;
            background: #f8f8f8;
            color: #1a1a1f;
            transition: 0.2s ease;
        }

        #filterContainer select:hover {
            cursor: pointer;
        }

        #filterContainer select:focus {
            outline: none;
            border-color: #5a7dff;
            box-shadow: 0 0 0 3px rgba(90,125,255,0.15);
        }

        /* ===================== DROPDOWNS ===================== */
        .dropdown-checkbox {
            width: 100%;
            margin-bottom: 12px;
            position: relative;
        }

        .dropdown-header {
            padding: 8px;
            background: #f8f8f8;
            border: 1px solid #ccc;
            border-radius: 8px;
            cursor: pointer;
            color: #1a1a1f;
            font-weight: 500;
            transition: 0.2s ease;
        }

        .dropdown-header:hover {
            background: #e0e0e5;
        }

        .dropdown-content {
            display: none;
            flex-direction: column;
            position: absolute;
            top: 42px;
            background: #fff;
            width: 92%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
            z-index: 10;
        }

        .dropdown-content label {
            display: block;
            margin: 6px 0;
            font-size: 13px;
            color: #1a1a1f;
        }

        /* ===================== RANGE FILTER ===================== */
        .range-filter label {
            font-size: 13px;
            color: #1a1a1f;
        }

        .range-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
        }

        .range-row input {
            width: 100%;
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background: #f8f8f8;
            color: #1a1a1f;
        }

        /* ===================== FILTER BUTTONS ===================== */
        #addFiltersBtn,
        #clean,
        #search {
            padding: 8px 12px;
            width: 100%;
            border: 2px solid #ccc;
            border-radius: 8px;
            background: #fff;
            color: #000;
            cursor: pointer;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.25s ease, transform 0.1s ease;
        }

        #addFiltersBtn:hover {
            background: #0070f3;
            color: #fff;
            border-color: #0070f3;
            transform: translateY(-1px);
        }

        #clean:hover {
            background: #e53935;
            color: #fff;
            border-color: #e53935;
            transform: translateY(-1px);
        }

        #search:hover {
            background: #2e7d32;
            color: #fff;
            border-color: #2e7d32;
            transform: translateY(-1px);
        }

        /* ===================== MAIN CONTENT ===================== */
        main {
            flex-grow: 1;
        }

        #apsDisplayWrapper{
            display: flex;
            overflow: hidden;
            max-width: calc(100vw - 300px);
        }

        #apsDisplay {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex-shrink: 0;
            overflow-x: auto;
            overflow-y: overlay;
            max-width: 1170px;
            transition: right 0.35s ease;
            padding: 10px;
            padding-left: 30px;
            padding-right: 20px;
        }

        #apsDisplay::-webkit-scrollbar {
            height: 7px;
        }

        #apsDisplay::-webkit-scrollbar-track {
            background: #f2f2f2;
            border-radius: 4px;
        }

        #apsDisplay::-webkit-scrollbar-thumb {
            background: #5a7dff;
            border-radius: 4px;
        }

        #apsDisplay::-webkit-scrollbar-thumb:hover {
            background: #4a6ddf;
        }

        #block-labels {
            display: flex;
            flex-direction: row;
        }

        #label-div {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            width: 350px;
            height: 30px;
            flex-shrink: 0;  /* ✅ ADD THIS */
        }

        .blockOnFloor {
            display: flex;
            gap: 5px;
            width: 350px;
            height: 30px;
            flex-shrink: 0;  /* ✅ ADD THIS */
            border-radius: 10px;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .blockOnFloor:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(0,0,0,0.12);
        }

        /* ===================== FLOOR ROW ===================== */
        #floors {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            flex-shrink: 0;
            background: #f2f2f2;
        }

        .floor-row {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 50px;
            padding: 3px 12px;
            flex-wrap: nowrap; 
            min-width: fit-content;
        }

        /* ===================== FLOOR LABEL ===================== */
        .floor-label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            border-right: 3px solid #5a7dff;
            width: 30px;
            height: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 3px 0;
        }

        /* ===================== APARTMENTS ===================== */
        .apartmentsDiv {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            flex: 1;
        }

        /* ===================== APARTMENT TILE ===================== */
        .apt {
            width: 30px;     
            height: 30px;     
            border-radius: 6px; 
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 10px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 3px 6px rgba(0,0,0,0.12);
        }

        .apt:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(0,0,0,0.18);
        }

        /* when filtered - dim down */
        .dimmed {
            opacity: 0.35;        /* dim the apartment */
            filter: grayscale(60%); /* optional: add subtle gray */
            pointer-events: none;  /* optional: prevent click */
        }


        /* ===================== STATUS COLORS ===================== */
        .status-active { background: #28c7a9; }      /* free → green/aquamarine */
        .status-reserved { background: #f9c74f; }    /* reserved → yellow */
        .status-queue { background: #4d79ff; }       /* reservation queue → blue */
        .status-sold { background: #e63946; }        /* sold → red */
        .status-notforsale { background: #9b59b6; }  /* not for sale → purple */

        /* ===================== TOOLTIP ===================== */
        #apsDisplay .apt[data-status]:hover::after {
            content: attr(data-status);
            position: absolute;
            background: rgba(0,0,0,0.8);
            color: #fff;
            font-size: 11px;
            padding: 3px 6px;
            border-radius: 6px;
            top: -26px;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            opacity: 0;
            animation: fadeIn 0.2s forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(-50%) translateY(3px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }

        /* ===================== LEGEND ===================== */
        #legendBar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            background: rgba(255,255,255,0.12);
            padding: 10px 14px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            backdrop-filter: blur(6px);
            margin-left: 10px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #333;
            font-weight: 500;
            font-size: 13px;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            display: inline-block;
            box-shadow: 0 1px 4px rgba(0,0,0,0.15);
        }

        /* ===================== POPUP ===================== */
        #apartmentPopup {
            position: fixed;
            top: 0;
            right: -400px;
            width: 380px;
            height: 100%;
            background: rgba(255,255,255,0.95);
            box-shadow: -4px 0 16px rgba(0,0,0,0.22);
            border-radius: 12px 0 0 12px;
            overflow-y: auto;
            transition: right 0.35s ease;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            padding: 20px 16px 60px;
            backdrop-filter: blur(10px);
        }

        #apartmentPopup.active {
            right: 0;
        }

        .popup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .popup-header h3 {
            max-width: 126px;
            margin: 0;
            color: #1f2a44;
            font-weight: 700;
        }

        #popupClose {
            background: none;
            border: none;
            font-size: 26px;
            cursor: pointer;
            line-height: 1;
            color: #555;
            transition: 0.2s;
        }

        #popupClose:hover {
            color: #000;
            transform: scale(1.15);
        }

        /* ===================== POPUP HIGHLIGHTS ===================== */
        #popupHighlights {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 12px;
        }

        .highlight-card {
            background: #28c7a9;
            color: #fff;
            border-radius: 8px;
            padding: 6px;
            text-align: center;
            font-size: 13px;
            box-shadow: 0 2px 6px rgba(33,136,56,0.25);
        }

        .highlight-card span {
            display: block;
            font-size: 11px;
            opacity: 0.85;
        }

        .highlight-card b {
            font-size: 14px;
        }

        /* ===================== POPUP DETAILS ===================== */
        #popupDetails,
        #popupDetailsMore {
            list-style: none;
            padding: 0;
            margin: 6px 0 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        #popupDetails li,
        #popupDetailsMore li {
            background: #f8fafc;
            padding: 5px 6px;
            border-radius: 8px;
            font-size: 13px;
            color: #344055;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: 0.25s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* little animated bullet */
        #popupDetails li::before,
        #popupDetailsMore li::before {
            content: "•";
            color: #28c7a9;
            font-size: 18px;
            transition: transform 0.2s ease;
        }

        #popupDetails li:hover,
        #popupDetailsMore li:hover {
            transform: translateX(4px);
            background: #eef7f5;
        }

        #popupDetails li:hover::before,
        #popupDetailsMore li:hover::before {
            transform: scale(1.4);
        }

        /* Bold label */
        #popupDetails li b,
        #popupDetailsMore li b {
            font-weight: 700;
            color: #1f2a44;
        }

        /* ===================== EXPAND / COLLAPSE DETAILS ===================== */
        #popupDetailsWrapper {
            overflow: hidden;
            max-height: 0;
            transition: max-height 0.4s ease, opacity 0.35s ease;
            opacity: 0;
        }

        #popupDetailsWrapper.open {
            opacity: 1;
            max-height: 500px; /* enough for all items */
        }

        /* expand button */
        #toggleDetailsBtn {
            margin-top: 14px;
            margin-bottom: 3px;
            background: none;
            border: none;
            color: #28c7a9;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            padding: 6px 0;
            width: 100%;
            text-align: center;
            transition: 0.2s ease;
        }

        #toggleDetailsBtn:hover {
            transform: scale(1.05);
        }

        /* ===================== STICKY FOOTER ===================== */
        .popup-footer {
            /* position: sticky;
            bottom: 0; */
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            padding: 12px 0 6px;
            display: flex;
            gap: 8px;
            border-top: 2px solid rgba(40,199,169,0.25);
        }

        .sandrosBtns {
            flex: 1;
            background: linear-gradient(135deg, #28c7a9, #22b39a);
            color: white;
            border: none;
            padding: 10px 12px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: 0.22s ease;
            box-shadow: 0 2px 6px rgba(40,199,169,0.25);
            position: relative;
            overflow: hidden;
        }

        /* hover */
        .sandrosBtns:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(40,199,169,0.35);
        }

        /* press (click) */
        .sandrosBtns:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(40,199,169,0.25);
        }

        /* subtle highlight */
        .sandrosBtns::before {
            content: "";
            position: absolute;
            top: 0;
            left: -40%;
            width: 80%;
            height: 100%;
            background: rgba(255,255,255,0.25);
            transform: skewX(-20deg);
            transition: 0.45s ease;
            opacity: 0;
        }

        .sandrosBtns:hover::before {
            left: 120%;
            opacity: 1;
        }


        /* ===================== SEPARATOR ===================== */
        .popup-separator {
            margin: 16px 0 10px;
            text-align: center;
            position: relative;
            color: #5a6a85;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .popup-separator::before,
        .popup-separator::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: #d5d8df;
        }

        .popup-separator::before {
            left: 0;
        }

        .popup-separator::after {
            right: 0;
        }

        /* ===================== GRADIENT DIVIDER ===================== */
        .popup-separator {
            margin: 18px 0 12px;
            text-align: center;
            position: relative;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.3px;
            color: #3a4a63;
        }

        .popup-separator::before,
        .popup-separator::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 38%;
            height: 2px;
            background: linear-gradient(
                to right,
                rgba(40,199,169,0),
                rgba(40,199,169,0.7),
                rgba(40,199,169,0)
            );
        }

        .popup-separator::before { left: 0; }
        .popup-separator::after { right: 0; }
        
        /* ===================== POPUP ACTION BUTTONS ===================== */

        .popup-buttons {
            position: sticky;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            padding: 12px 0 6px;
            display: flex;
            justify-content: center;
            gap: 8px;
        }

        /* #e1eefb; */ /* MOVUBRUNDE */

        #popupSelectBtn {
            justify-content: center;
            width: 50%;
            padding: 12px 16px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
            margin-top: 12px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.15);
        }

        #popupSelectBtn {
            background: linear-gradient(135deg, #28c7a9, #1fa388);
            color: white;
        }

        #popupSelectBtn::before {
            font-size: 16px;
            margin-right: 4px;
        }

        #popupSelectBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 14px rgba(40,199,169,0.35);
            background: linear-gradient(135deg, #2eddb8, #28c7a9);
        }

        #popupSelectBtn:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(40,199,169,0.25);
        }

        /* ============================== EXTRA FILTERS ============================== */
        #addFiltersDropdown {
            margin-bottom: 0;
        }

        .extraFilters {
            background: none;
            border: none;
            color: #1a1a1f;
            font-size: 13px;
            cursor: pointer;
            padding: 4px 0;
            width: 100%;
            text-align: left;
            transition: 0.2s ease;
        }

        .extraFilters:hover {
            color: #5a7dff;
            transform: translateX(4px);
        }

        .filter-chip {
            width: 90%;
            display: flex;
            margin: 4px;
            padding: 4px 8px;
            background: #eee;
            border-radius: 4px;
        }
        .remove-chip {
            margin-left: 6px;
            background: transparent;
            border: none;
            cursor: pointer;
        }

        .disabled-button {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none; /* disables clicks and hovers */
        }

        /* Optional: remove hover styling */
        .disabled-button:hover {
            transform: none;
            box-shadow: none;
            background-color: #ddd; /* or keep same as normal */
        }

        .filter-chip input[type="text"] {
            width: 100%;
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background: #f8f8f8;
            color: #1a1a1f;
        }

        /* ===================== SAVE BUTTON ===================== */
        #saveBtn {
            width: 50px;
            height: 35px;
            padding: 2px 5px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #0070f3, #0051cc);
            color: white;
            font-weight: 200;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 3px 8px rgba(0, 112, 243, 0.25);
            margin-top: 30px;
            margin-left: 10px;
        }

        #saveBtn::before {
            font-size: 16px;
            margin-right: 6px;
        }

        #saveBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 14px rgba(0,112,243,0.4);
        }

        #saveBtn:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(0,112,243,0.25);
        }

        /* ========================== TRANSLATE BOX ========================== */
        .gtranslate_wrapper {
            display: flex;
            height: 30px;
            width: 75px;
            justify-content: center;
            align-items: center;
            position: absolute;
            top: 32px;
            left: 186.5px;
            z-index: 9999;
            background: white;
            padding: 3px;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        /* =========================== PRODUCTS BOX =========================== */
        #productsBox {
            display: flex;
            min-width: 200px;
            width: fit-content;
            height: 30px;
            margin-left: 10px;
            margin-top: 17px;
            padding: 15px;
            border: 2px solid lightgrey;
            border-radius: 10px;
            gap: 10px;
        }

        .border-text {
            color: gray;
            font-size: 10px;
            position: absolute;
            top: 69px;
            left: 275px;
            background: #eef2f4;
            padding: 0 3px;
            font-weight: 500;
            border-radius: 15px;
        }

        /* ===================== IMAGE CAROUSEL POPUP ===================== */
        #imageCarouselPopup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.92);
            backdrop-filter: blur(8px);
            z-index: 3000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        #imageCarouselPopup.active {
            display: flex;
            opacity: 1;
        }

        .carousel-container {
            position: relative;
            max-width: 90vw;
            max-height: 85vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .carousel-image-wrapper {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            max-width: 80vw;
            max-height: 80vh;
        }

        .carousel-image {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s ease;
        }

        /* Navigation Arrows */
        .carousel-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            font-size: 32px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .carousel-arrow:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 4px 16px rgba(255, 255, 255, 0.3);
        }

        .carousel-arrow:active {
            transform: translateY(-50%) scale(0.95);
        }

        .carousel-arrow.prev {
            left: -80px;
        }

        .carousel-arrow.next {
            right: -80px;
        }

        /* Close Button */
        #carouselClose {
            position: absolute;
            top: 20px;
            right: 30px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            font-size: 36px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        #carouselClose:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: rotate(90deg) scale(1.1);
            box-shadow: 0 4px 16px rgba(255, 255, 255, 0.3);
        }

        /* Image Counter */
        .carousel-counter {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(10px);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Dots Indicator */
        .carousel-dots {
            position: absolute;
            bottom: -40px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
        }

        .carousel-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .carousel-dot.active {
            background: white;
            width: 12px;
            height: 12px;
        }

        .carousel-dot:hover {
            background: rgba(255, 255, 255, 0.7);
            transform: scale(1.2);
        }

        /* Make the popup image clickable */
        #popupImg {
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        #popupImg:hover {
            transform: scale(1.02);
        }
    </style>

</head>

<body>
<div class="containerCatalog">
    <div id="filterContainer">

        <h2>ძირითადი ფილტრი</h2>

        <!-- PROJECT SELECT -->
        <select id="projects">
            <option value="" disabled selected>პროექტი<span style="color:red">*</span></option>
        </select>

        <!-- BLOCK DROPDOWN (CHECKBOXES) -->
        <div class="dropdown-checkbox" id="blockFilter">
            <div class="dropdown-header">ბლოკი<span style="color:red">*</span></div>
            <div class="dropdown-content">
                <!-- dynamically filled via JS -->
            </div>
        </div>

        <!-- STATUS DROPDOWN (CHECKBOXES) -->
        <div class="dropdown-checkbox" id="statusFilter">
            <div class="dropdown-header">სტატუსი</div>
            <div class="dropdown-content"></div>
        </div>

        <!-- CONDITION DROPDOWN (CHECKBOXES) -->
        <div class="dropdown-checkbox" id="conditionFilter">
            <div class="dropdown-header">კონდიცია</div>
            <div class="dropdown-content"></div>
        </div>

        <!-- APARTMENT TYPE DROPDOWN (CHECKBOXES) -->
        <div class="dropdown-checkbox" id="apartmentTypeFilter">
            <div class="dropdown-header">ფართის ტიპი</div>
            <div class="dropdown-content"></div>
        </div>

        <!-- RANGE FILTER -->
        <div class="range-filter">
            <label>ბინის ნომერი</label>
            <div class="range-row">
                <input type="number" id="aptMin" placeholder="Min">
                <span>-</span>
                <input type="number" id="aptMax" placeholder="Max">
            </div>
        </div>

        <h2>დამატებითი ფილტრი</h2>
        <div class="filter-chips" id="extraFilterChips"></div>

        <div class="dropdown-checkbox" id="addFiltersDropdown">
            <button id="addFiltersBtn" type="button">+</button>
            <div class="dropdown-content" id="extraFiltersDropdown" style="overflow: auto;">
                <input type="text" id="filterSearch" placeholder="ძებნა..." style="width: 94%; margin-bottom: 8px; padding: 6px; border: 1px solid #ccc; border-radius: 8px;">
                <div id="filterButtonsContainer"></div>
            </div>
        </div>
        <button id="clean" type="button">გასუფთავება</button>
        <button id="search" type="button">ძიება</button>

    </div>

    <!-- APARTMENT DISPLAYS -->
    <div style="flex-grow:1; min-width: 0; max-width: 100%;">
        <!-- LEGEND -->
        <div id="legendBar">
            <div class="legend-item"><span class="legend-color status-active"></span> თავისუფალი</div>
            <div class="legend-item"><span class="legend-color status-queue"></span> ჯავშნის რიგი</div>
            <div class="legend-item"><span class="legend-color status-reserved"></span> დაჯავშნილი</div>
            <div class="legend-item"><span class="legend-color status-sold"></span> გაყიდული</div>
            <div class="legend-item"><span class="legend-color status-notforsale"></span> NFS</div>
        </div>

        <!-- PRODUCTS BOX -->
        <div id="productsBoxWrapper" style="display: none;">
            <div id="productsBox"></div>
            <button id="saveBtn">Save</button>
        </div>

        <div id="apsDisplayWrapper">
            <div id="floors"></div>
            <div id="apsDisplay"></div>
        </div>

        <!-- APARTMENT POPUP -->
        <div id="apartmentPopup">
            <div class="popup-header"> 
                <h3 id="popupTitle">ბინის დეტალები</h3>
                <div class="header-buttons">
                    <a id="popupOffer" target="_blank"><button class="sandrosBtns">შეთავაზება</button></a>
                    <a id="popupCalc" target="_blank"><button class="sandrosBtns">კალკულატორი</button></a>
                    <button id="popupClose">&times;</button>
                </div>
            </div>

            <div class="popup-body">
                <div class="popup-img-wrap">
                <img id="popupImg" src="" alt="Apartment photo">
                </div>

                <div class="popup-buttons">
                    <button id="popupSelectBtn">დამატება</button>
                </div>

                <div class="popup-separator">დეტალები</div>

                <ul id="popupDetails"></ul>

                <button id="toggleDetailsBtn">► დამატებითი დეტალები</button>
                <div class="popup-footer"></div>

                <div id="popupDetailsWrapper">
                    <ul id="popupDetailsMore"></ul>
                </div>

            </div>

            
            
        </div>

    </div>

    <!-- IMAGE CAROUSEL POPUP -->
    <div id="imageCarouselPopup">
        <button id="carouselClose">&times;</button>
        
        <div class="carousel-container">
            <button class="carousel-arrow prev">‹</button>
            
            <div class="carousel-image-wrapper">
                <img id="carouselImage" class="carousel-image" src="" alt="Apartment image">
            </div>
            
            <button class="carousel-arrow next">›</button>
        </div>
        
        <div class="carousel-counter">
            <span id="currentImageNum">1</span> / <span id="totalImages">3</span>
        </div>
        
        <div class="carousel-dots">
            <div class="carousel-dot active" data-index="0"></div>
            <div class="carousel-dot" data-index="1"></div>
            <div class="carousel-dot" data-index="2"></div>
        </div>
    </div>
    
</div>


<!------------------------------SCRIPT----------------------------->
<script>
    let dealID = <?php echo json_encode($dealID); ?>;
    let deal = <?php echo json_encode($deal); ?>;
    let stage_id = "";
    let products = <?php echo json_encode($products); ?>;
    let productsIds = <?php echo json_encode($productsIds); ?>;
    let projects = <?php echo json_encode($projects); ?>;
    let productProperties = <?php echo json_encode($productProperties); ?>;
    let openedOnDeal = false;
    let allowedStages = ["PREPAYMENT_INVOICE", "UC_12CJ1Z", "UC_2EW8VW", "UC_15207E", "EXECUTING", "UC_BAUB5P", "UC_F3FOBF"];
    let inAllowedStages = true;
    let productsBoxWrapper = document.getElementById("productsBoxWrapper");


    // check if deal exists - do some stuff
    if (Array.isArray(deal) && deal.length !== 0) {
        stage_id = deal[0].STAGE_ID; // get stage
        openedOnDeal = true; // this section is for when opened through a deal

        // aps display scroll designs
        document.getElementById("apsDisplay").style.maxWidth = '820px'; 
        document.getElementById("apartmentPopup").style.position = 'absolute';
        let containerDiv = document.querySelector(".containerCatalog");
        containerDiv.style.paddingLeft = '0';

        // make extra filter dropdown scrollable
        document.getElementById("extraFiltersDropdown").style.height = '165px';

        // only allow save and delete on allowed stages
        if (!allowedStages.includes(stage_id)) {
            inAllowedStages = false;
            document.getElementById("saveBtn").style.display = "none";
        }

        // show products box
        productsBoxWrapper.style.display = "flex";
        productsBoxWrapper.innerHTML += `<span class="border-text">დილზე დამატებული ბინები</span>`;

        // if the deal has products
        if (Array.isArray(products) && products.length !== 0) {
  
            // start loading damatebuli products project and blocks
            // Get unique project ID and blocks from products
            const productProject = parseInt(products[0]["IBLOCK_SECTION_ID"]); // Assuming all products are from same project
            const productBlocks = [...new Set(products.map(p => p["KORPUSIS_NOMERI_XE3NX2"]))];
            
            // Set the project dropdown
            if (productProject) {
                let projectSelectBefore = document.getElementById("projects");
                projectSelectBefore.value = productProject;
                
                // Trigger the change event to load blocks
                setTimeout(() => {
                    projectSelectBefore.dispatchEvent(new Event('change', { bubbles: true }));
                }, 0);
                
                // Wait for blocks to load, then check them
                setTimeout(() => {
                    productBlocks.forEach(blockName => {
                        const checkbox = document.querySelector(`#blockFilter .dropdown-content label input[value="${blockName}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            // // Trigger change to load products
                            setTimeout(() => {
                                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                            }, 10);
                        }
                    });
                    
                    // Update the block filter header
                    updateDropdownHeader("blockFilter", "ბლოკი");
                }, 500); // Give time for the AJAX request to complete
            }
            // end loading project and blocks on open

            // fill products box, draw apts there with the functionalities
            let productsBox = document.getElementById("productsBox");
            productsBox.innerHTML = "";
            products.forEach(apartment => {
                switch (apartment["STATUS"]) {
                    case "თავისუფალი":
                        statusForClassList = 'active';
                        break;

                    case "ჯავშნის რიგი":
                        statusForClassList = 'queue';
                        break;
                    
                    case "დაჯავშნილი":
                        statusForClassList = 'reserved';
                        break;
                    
                    case "გაყიდული":
                        statusForClassList = 'sold';
                        break;

                    default:
                        statusForClassList = 'notforsale';
                };


                const aptTile = document.createElement("div");
                aptTile.classList.add("apt", `status-${statusForClassList}`);
                aptTile.dataset.id = apartment["ID"];
                aptTile.dataset.status = apartment["STATUS"];
                aptTile.textContent = apartment["Number"];
                
                // Select the apartment div with the specific data-id
                let apartmentDiv = document.querySelector(`#apsDisplay .apt[data-id="${apartment["ID"]}"]`);

                // If it exists, add a class
                if (apartmentDiv) {
                    apartmentDiv.classList.add("dimmed");
                }


                // add remove button
                const removeBtn = document.createElement("button");
                removeBtn.textContent = "×";
                removeBtn.classList.add("remove-chip");
                removeBtn.onclick = () => {
                    aptTile.remove();
                    // Re-query the apartment div to potentially remove dimmed class
                    let apartmentToUndim = document.querySelector(`#apsDisplay .apt[data-id="${apartment["ID"]}"]`);
                    if (apartmentToUndim) {
                        // Check if it should still be dimmed by filters
                        const filters = getAllFilters();
                        const apartmentToUndim = productsCache.find(p => p["ID"] == apartment["ID"]);
                        
                        let matchesFilters = true;
                        
                        if (apartmentToUndim) {
                            // Status filter
                            if (filters.status.length > 0 && !filters.status.includes(apartmentToUndim["STATUS"])) matchesFilters = false;
                            
                            // Condition filter
                            if (filters.condition.length > 0 && !filters.condition.includes(apartmentToUndim["_H8WF0T"])) matchesFilters = false;
                            
                            // Apartment type filter
                            if (filters.aptType.length > 0 && !filters.aptType.includes(apartmentToUndim["PRODUCT_TYPE"])) matchesFilters = false;
                            
                            // Blocks filter
                            if (filters.blocks.length > 0 && !filters.blocks.includes(apartmentToUndim["KORPUSIS_NOMERI_XE3NX2"])) matchesFilters = false;
                            
                            // Apartment range filter
                            const min = parseInt(filters.aptRange.min);
                            const max = parseInt(filters.aptRange.max);
                            const aptNum = parseInt(apartmentToUndim["Number"]);
                            if (!isNaN(min) && aptNum < min) matchesFilters = false;
                            if (!isNaN(max) && aptNum > max) matchesFilters = false;
                            
                            // Extra filters
                            for (const [key, val] of Object.entries(filters.extraFilters)) {
                                const prop = apartmentToUndim[key];
                                if (typeof val === "object") { // range
                                    if ((val.min !== null && prop < val.min) || (val.max !== null && prop > val.max)) {
                                        matchesFilters = false;
                                        break;
                                    }
                                } else { // text
                                    if (!prop || !prop.toString().toLowerCase().includes(val.toLowerCase())) {
                                        matchesFilters = false;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Only remove dimmed if it matches current filters
                        if (matchesFilters && !productsIds.includes(apartment["ID"])) {
                            apartmentToUndim.classList.remove("dimmed");
                        }
                        document.getElementById("popupSelectBtn").style.display = "flex";
                    }
                    
                }
                removeBtn.style.marginLeft = "4px";
                removeBtn.style.fontSize = "10px";
                removeBtn.style.background = "transparent";
                removeBtn.style.border = "none";
                removeBtn.style.cursor = "pointer";

                if (inAllowedStages) {
                    aptTile.appendChild(removeBtn);
                }
                productsBox.appendChild(aptTile);
            });
            // end of fill

        }
    }

    // ======================== FOR FILTER DROPDOWNS ========================
    // Open/Close checkbox dropdowns
    $(".dropdown-header").on("click", function (event) {
        event.stopPropagation(); // prevent closing immediately

        // Close all other dropdowns
        $(".dropdown-content").not($(this).next()).slideUp(150);

        // Toggle the clicked one
        $(this).next(".dropdown-content").slideToggle(150);
    });

    // Prevent closing when clicking inside the dropdown content
    $(".dropdown-content").on("click", function (event) {
        event.stopPropagation();
    });

    // Close dropdowns when clicking anywhere else on the page
    $(document).on("click", function () {
        $(".dropdown-content").slideUp(150);
    });

    // ====================== GET FILTER VALUES ======================
    // GET SELECTED CHECKBOX VALUES
    function getCheckboxValues(id) {
        let values = [];
        $("#" + id + " .dropdown-content input:checked").each(function () {
            values.push($(this).val());
        });
        return values;
    }

    // GET RANGE VALUES
    function getRangeValues() {
        return {
            min: $("#aptMin").val(),
            max: $("#aptMax").val()
        };
    }

    // EXAMPLE: GET ALL FILTERS
    function getAllFilters() {
        return {
            project: $("#projects").val(),
            blocks: getCheckboxValues("blockFilter"), // <- multi-select now
            status: getCheckboxValues("statusFilter"),
            condition: getCheckboxValues("conditionFilter"),
            aptType: getCheckboxValues("apartmentTypeFilter"),
            aptRange: getRangeValues(),
            extraFilters: getExtraFilterValues()
        };
    }


    // ============================= CLEAN BUTTON =============================
    $("#clean").on("click", function () {
        // Clear selects
        // $("select").val("");
        
        // Clear checkboxes
        $("#statusFilter input[type=checkbox], #conditionFilter input[type=checkbox], #apartmentTypeFilter input[type=checkbox]").prop("checked", false);
        
        // Clear range inputs
        $("#aptMin, #aptMax").val("");
        
        // Reset dropdown headers to default text
        $("#statusFilter .dropdown-header").text("სტატუსი");
        $("#conditionFilter .dropdown-header").text("კონდიცია");
        $("#apartmentTypeFilter .dropdown-header").text("ფართის ტიპი");
        
        // Remove all extra filter chips
        const chipsContainer = document.getElementById("extraFilterChips");
        const chips = chipsContainer.querySelectorAll(".filter-chip");
        chips.forEach(chip => {
            // Find the associated button and re-enable it
            const label = chip.querySelector("label")?.textContent || chip.querySelector("input[type=text]")?.placeholder;
            const button = Array.from(document.querySelectorAll("#addFiltersDropdown .extraFilters"))
                .find(btn => btn.textContent === label);
            
            if (button) {
                button.style.background = "none";
                button.style.opacity = "1";
                button.style.cursor = "pointer";
                button.disabled = false;
                button.classList.remove("disabled-button");
            }
            
            // Remove the chip
            chip.remove();
        });
        
        // Close all dropdowns
        $(".dropdown-content").slideUp(150);
        
        // Clear any applied filters (remove dimmed class from apartments)
        document.querySelectorAll("#apsDisplay .apt.dimmed").forEach(apt => {
            apt.classList.remove("dimmed");
        });
    });


    // loading projects for dropdown select
    const projectSelect = document.getElementById("projects");
    const blockSelect = document.getElementById("blockFilter");

    // PROJECTS DROPDOWN
    if (projects && Array.isArray(projects)) {
        projects.forEach(project => {
            const option = document.createElement("option");
            option.value = project["ID"];
            option.textContent = project["NAME"];
            projectSelect.appendChild(option);
        });
    }

    // ================================ FETCH APARTMENTS ================================
    projectSelect.addEventListener("change", function() {
        const projId = this.value;

        // Clear previous blocks
        blockSelect.querySelectorAll('option:not(:first-child)').forEach(o => o.remove());

        if (!projId) return;

        // Fetch blocks for selected project (do NOT send empty blockId)
        fetch(`/rest/local/api/projects/getTest.php?projId=${projId}`)
            .then(res => res.json())
            .then(data => {
                // Populate Blocks dropdown
                if (data.blocks) {
                    const blockContainer = document.querySelector("#blockFilter .dropdown-content");
                    blockContainer.innerHTML = ""; // clear old
                    data.blocks
                    .sort((a, b) => {
                        const numA = parseInt(a.match(/\d+/)[0], 10);
                        const numB = parseInt(b.match(/\d+/)[0], 10);

                        // First: sort by number
                        if (numA !== numB) return numA - numB;

                        // If numbers are equal: sort by letter
                        const letterA = a.match(/[A-Z]+/)[0];
                        const letterB = b.match(/[A-Z]+/)[0];
                        return letterA.localeCompare(letterB);
                    })
                    .forEach(block => {
                        const label = document.createElement("label");
                        label.innerHTML = `<input type="checkbox" value="${block}"> ${block}`;
                        blockContainer.appendChild(label);
                    });


                }
            })
            .catch(err => console.error(err));
    });

    let productsCache = []; // To Store fetched products for the selected block

    // ======================== When a block is selected, fetch products from API ========================
    $(document).on("change", "#blockFilter input[type=checkbox]", function () {
        const projId = $("#projects").val();
        const selectedBlocks = getCheckboxValues("blockFilter");

        if (!projId || selectedBlocks.length === 0) return;

        // Fetch all selected blocks at once
        const params = new URLSearchParams();
        params.append("projId", projId);
        selectedBlocks.forEach(b => params.append("blockId[]", b));

        fetch(`/rest/local/api/projects/getTest.php?${params.toString()}`)
            .then(res => res.json())
            .then(data => {
                productsCache = data.products; 
                renderProductsByBlock(productsCache, selectedBlocks, data.blockNames);
                updateDynamicFilters(productsCache); 
                })
                .catch(err => console.error(err));
    });


    // =========================== DRAWING APARTMENTS ===========================

    function renderProductsByBlock(products, selectedBlocks, blockNames = {}) {
        const container = document.getElementById("apsDisplay");
        container.innerHTML = ""; // clear old content

        if (!products || products.length === 0) {
            container.innerHTML = "<p style='color:#999;'>ბინები ვერ მოიძებნა.</p>";
            return;
        }



        // Calculate max apartments per block across all floors
        const maxApartmentsPerBlock = {};
        
        // Group by floor first
        let byFloor = {};
        products.forEach(apartment => {
            if (!byFloor[apartment["FLOOR"]]) byFloor[apartment["FLOOR"]] = [];
            byFloor[apartment["FLOOR"]].push(apartment);
        });

        // For each floor, count apartments per block
        Object.values(byFloor).forEach(floorApartments => {
            const blockCounts = {};
            floorApartments.forEach(apartment => {
                const blockName = apartment["KORPUSIS_NOMERI_XE3NX2"];
                blockCounts[blockName] = (blockCounts[blockName] || 0) + 1;
            });

            // Update max for each block
            Object.entries(blockCounts).forEach(([blockName, count]) => {
                if (!maxApartmentsPerBlock[blockName] || count > maxApartmentsPerBlock[blockName]) {
                    maxApartmentsPerBlock[blockName] = count;
                }
            });
        });

        // Calculate width for each block (30px per apt + 5px gap between apts)
        const blockWidths = {};
        Object.entries(maxApartmentsPerBlock).forEach(([blockName, maxCount]) => {
            blockWidths[blockName] = (maxCount * 30) + ((maxCount - 1) * 5);
        });

        // Render block labels with calculated widths
        let blockLabel = `<div class="floor-row" id="block-labels">`;
        selectedBlocks.forEach(block => {
            const width = blockWidths[block] || 350; // fallback to 350px if no data
            blockLabel += `<div id="label-div" style="width: ${width}px; display: flex; align-items: center; justify-content: center; gap: 5px; height: 30px; flex-shrink: 0;"><div>${block}</div></div>`;
        });
        blockLabel += `</div>`;
        container.innerHTML += blockLabel;

        // Get floor range
        const floors = [];
        products.forEach(apartment => {
            const floor = parseInt(apartment["FLOOR"]);
            if (!isNaN(floor)) {
                floors.push(floor);
            }
        });

        // Display floors
        let min = Math.min(...floors);
        let max = Math.max(...floors);
        const floorsContainer = document.getElementById("floors");
        floorsContainer.innerHTML = ""; // clear old labels first

        for (let i = max; i >= min; i--) {
            floorsContainer.innerHTML += `<div class="floor-label"><div>${i}</div></div>`;
        }

        // Render each floor with calculated block widths
        Object.keys(byFloor).sort((a, b) => b - a).forEach(floorNumber => {
            const floor = byFloor[floorNumber]; 

            let blocks = {};
            selectedBlocks.forEach(block => {
                blocks[block] = [];
            });

            Object.values(floor).forEach(apartment => {
                blocks[apartment["KORPUSIS_NOMERI_XE3NX2"]].push(apartment);
            });

            let floorDiv = `<div class="floor-row">`;

            Object.entries(blocks).forEach(([blockName, blockOnFloor]) => {
                const width = blockWidths[blockName] || 350; // use calculated width
                
                let blockOnFloorDiv = `<div 
                        class="blockOnFloor"
                        data-floor="${floor[0]["FLOOR"]}"
                        data-block="${blockName}"
                        style="height:30px; width: ${width}px; flex-shrink: 0;">`;

                if (blockOnFloor.length > 0) {
                    blockOnFloor.forEach(apartment => {
                        let statusClass = "status-active";
                        switch(apartment["STATUS"]) {
                            case "გაყიდული": statusClass="status-sold"; break;
                            case "დაჯავშნილი": statusClass="status-reserved"; break;
                            case "ჯავშნის რიგი": statusClass="status-queue"; break;
                            case "თავისუფალი": statusClass="status-active"; break;
                            case "NFS": statusClass="status-notforsale"; break;
                        }

                        if (productsIds && productsIds.includes(apartment["ID"])) {
                            blockOnFloorDiv += `<div class="apt ${statusClass}"
                                        data-id="${apartment["ID"]}"
                                        data-status="${apartment["STATUS"]}"
                                        data-floor="${apartment["FLOOR"]}"
                                        data-block="${apartment["KORPUSIS_NOMERI_XE3NX2"]}"
                                        style="transform: scale(1.2); outline: 2px solid #ff343a;">
                                        <div>${apartment["Number"]}</div>
                                </div>`;
                        } else {
                            blockOnFloorDiv += `<div class="apt ${statusClass}"
                                            data-id="${apartment["ID"]}"
                                            data-status="${apartment["STATUS"]}"
                                            data-floor="${apartment["FLOOR"]}"
                                            data-block="${apartment["KORPUSIS_NOMERI_XE3NX2"]}">
                                            <div>${apartment["Number"]}</div>
                                    </div>`;
                        }

                    });
                }
                
                blockOnFloorDiv += `</div>`;
                floorDiv += blockOnFloorDiv;
            });
            
            floorDiv += `</div>`;
            container.innerHTML += floorDiv;
        });

        let floor = document.getElementById("block-labels");
        let apsDisplay = document.getElementById("apsDisplay");
        let floorsDiv = document.getElementById("floors");
        if (parseInt(getComputedStyle(floor).width) > parseInt(getComputedStyle(apsDisplay).maxWidth)) {
            floorsDiv.style.paddingBottom = "16px";
        } else {
            floorsDiv.style.paddingBottom = "9px";
        }
    }


    function updateDynamicFilters(products) {
        if (!products || products.length === 0) return;

        // Collect unique values for each filter
        const statusSet = new Set();
        const conditionSet = new Set();
        const typeSet = new Set();

        products.forEach(p => {
            if (p["STATUS"]) statusSet.add(p["STATUS"]);       // Status
            if (p["_H8WF0T"]) conditionSet.add(p["_H8WF0T"]);   // Condition
            if (p["PRODUCT_TYPE"]) typeSet.add(p["PRODUCT_TYPE"]);        // Apartment type
        });

        // Helper to build dropdown
        function buildDropdown(containerSelector, valuesSet, defaultText) {
            const container = document.querySelector(containerSelector + " .dropdown-content");
            container.innerHTML = ""; // clear previous
            Array.from(valuesSet).forEach(val => {
                const label = document.createElement("label");
                label.innerHTML = `<input type="checkbox" value="${val}"> ${val}`;
                container.appendChild(label);
            });
            // Reset header
            const header = document.querySelector(containerSelector + " .dropdown-header");
            header.textContent = defaultText;
        }

        // Populate each filter
        buildDropdown("#statusFilter", statusSet, "სტატუსი");
        buildDropdown("#conditionFilter", conditionSet, "კონდიცია");
        buildDropdown("#apartmentTypeFilter", typeSet, "ფართის ტიპი");

        // Reattach checkbox change handler to update headers
        $(".dropdown-content input[type=checkbox]").off("change").on("change", function() {
            const parentId = $(this).closest(".dropdown-checkbox").attr("id");
            const defaultText = parentId === "statusFilter" ? "სტატუსი" :
                                parentId === "conditionFilter" ? "კონდიცია" :
                                parentId === "apartmentTypeFilter" ? "ფართის ტიპი" : "";
            updateDropdownHeader(parentId, defaultText);

            if (parentId === "blockFilter") {
                const values = getCheckboxValues("blockFilter");
                if (values.length === 0) {
                    document.querySelector("#blockFilter .dropdown-header").textContent = "ბლოკი";
                }
            }
        });
    }

    // Function to apply all filters to productsCache
    function applyFilters() {
        const filters = getAllFilters(); // Collect values from checkboxes, select, and range

        document.querySelectorAll("#apsDisplay .apt").forEach(aptEl => {
            const aptId = aptEl.dataset.id; // fetches the data-id attribute
            const apartment = productsCache.find(p => p["ID"] == aptId);

            if (!apartment) return;

            let match = true;

            // Status filter
            if (filters.status.length > 0 && !filters.status.includes(apartment["STATUS"])) match = false;

            // Condition filter
            if (filters.condition.length > 0 && !filters.condition.includes(apartment["_H8WF0T"])) match = false;

            // Apartment type filter
            if (filters.aptType.length > 0 && !filters.aptType.includes(apartment["PRODUCT_TYPE"])) match = false;

            // Blocks filter
            if (filters.blocks.length > 0 && !filters.blocks.includes(apartment["KORPUSIS_NOMERI_XE3NX2"])) match = false;

            // Apartment range filter
            const min = parseInt(filters.aptRange.min);
            const max = parseInt(filters.aptRange.max);
            const aptNum = parseInt(apartment["Number"]);
            if (!isNaN(min) && aptNum < min) match = false;
            if (!isNaN(max) && aptNum > max) match = false;

            // Extra filters
            for (const [key, val] of Object.entries(filters.extraFilters)) {
                const prop = apartment[key]; 
                if (typeof val === "object") { // range
                    if ((val.min !== null && prop < val.min) || (val.max !== null && prop > val.max)) {
                        match = false;
                        break;
                    }
                } else { // text
                    if (!prop || !prop.toString().toLowerCase().includes(val.toLowerCase())) {
                        match = false;
                        break;
                    }
                }
            }

            // Apply dimmed class if it does NOT match
            if (match) {
                aptEl.classList.remove("dimmed");
            } else {
                aptEl.classList.add("dimmed");
            }
        });
    }


    // SEARCH BUTTON triggers filtering
    document.getElementById("search").addEventListener("click", applyFilters);


    // Attach popup handlers once apartments are rendered / resize when clicked 
    let currentlyActiveApt = null;
    document.addEventListener("click", e => {
        const apt = e.target.closest(".apt");
        if (!apt) return;

        if (currentlyActiveApt && currentlyActiveApt !== apt){
            currentlyActiveApt.style.transform = "scale(1)";
            currentlyActiveApt.style.border = "none";
        }

        currentlyActiveApt = apt;
        apt.style.transform = "scale(1.2)";
        apt.style.border = "2px solid black";
        // Check which container this apartment belongs to/ where is it clicked from
        const isFromProductsBox = apt.closest("#productsBox") !== null;
        const isFromApsDisplay = apt.closest("#apsDisplay") !== null;

        // Get product data (from dataset or global)
        const apartmentId = apt.dataset.id || "";
        const status = apt.dataset.status || "";
        const floor = apt.dataset.floor || "";
        const block = apt.dataset.block || "";
        const aptNumber = apt.textContent.trim();

        openPopup({
            id: apartmentId,
            source: isFromProductsBox ? "productsBox" : "apsDisplay"
        });
    });

    // ================================== POPUP ==================================
    async function openPopup(apartmentInfo) {
        const popup = document.getElementById("apartmentPopup");
        popup.classList.add("active");

        // Reduce apsDisplay width when popup opens
        const apsDisplay = document.getElementById("apsDisplay");
        if (openedOnDeal) {
            apsDisplay.style.maxWidth = "420px";
        } else {
            apsDisplay.style.maxWidth = "777px"; // luckyyyyyyyy
        }

        // apartments container
        const container = document.getElementById("productsBox");
        let incontainer = false;
        if (container.querySelector(`.apt [data-id="${apartmentInfo.id}"]`)) {
            incontainer = true;
        }

        // products box
        let inProdBox = false;

        let apartment; 
        if(apartmentInfo.source == "productsBox"){
            apartment = products.find(p => p["ID"] == apartmentInfo.id);
            inProdBox = true;
        } else {
            apartment = productsCache.find(p => p["ID"] == apartmentInfo.id);
        }
        // reset UI
        document.getElementById("popupTitle").innerText = `${apartment["PRODUCT_TYPE"]} N${apartment["Number"] || "-"}`;
        document.getElementById("popupTitle").dataset.id = apartmentInfo.id;
        document.getElementById("popupTitle").dataset.status = apartment["STATUS"] || "active";
        document.getElementById("popupImg").style.display = "none";
        document.getElementById("popupDetails").innerHTML = "<li>იტვირთება...</li>";

        // set button links
        if (openedOnDeal) {
            document.getElementById("popupCalc").href = `/custom/calculator/?dealid=${dealID}&ProductID=${apartment["ID"]}`;
        } else {
            document.getElementById("popupCalc").href = `/custom/calculator/?ProductID=${apartment["ID"]}`;
        }
            
        document.getElementById("popupOffer").href = `/crm/deal/offer-catalog.php?prod_ID=${apartmentInfo.id}`;

        // display image
        if (apartment["image"]) {
            const img = document.getElementById("popupImg");
            img.src = apartment["image"];
            img.style.display = "block";
        }
        
        // // fill details
        const detailsList = document.getElementById("popupDetails");

        
        detailsList.innerHTML = `
            <li><b>პროექტი: </b> ${apartment["PROJECT"] || "—"}</li>
            <li><b>ბლოკი: </b> ${apartment["KORPUSIS_NOMERI_XE3NX2"] || "—"}</li>
            <li><b>სადარბაზო: </b> ${apartment["_15MYD6"] || "—"}</li>
            <li><b>სართული: </b> ${apartment["FLOOR"] || "—"}</li>
            <li>
                <div style="display: flex; justify-content: space-between; width:85%;">
                    <div><b>სრული ფასი $: </b> ${apartment["PRICE"] || "—"}</div>
                    <div><b>ფასი m<sup>2</sup> $: </b> ${apartment["KVM_PRICE"] || "—"}</div>
                </div>
            </li>
            <li><b>სრული ფართი: </b> ${apartment["TOTAL_AREA"] || "—"}</li>
            <li><b>ფართი (საცხოვრებელი): </b> ${apartment["LIVING_SPACE"] || "—"}</li>
            <li><b>ფართი (საზაფხულო): </b> ${apartment["__FVE8A2"] || "—"}</li>
        `;
        
        if(apartment["OWNER_DEAL"]){
            let ownerDeal = `<a href="/crm/deal/details/${apartment["OWNER_DEAL"]}/" target="_blank">${apartment["OWNER_DEAL"]}</a>`;
            detailsList.innerHTML += `<li><b>მფლობელის დილი: </b> ${ownerDeal}</li>`;
        }

        if (apartment["QUEUE"]!=='') {

            var queue='';
            var javshaniarr = apartment["QUEUE"].split('|');

            for (let f = 0; f < javshaniarr.length; f++) {
                if (javshaniarr[f] !== '') {
                    if (f==1) {
                        queue = `<a href="/crm/deal/details/${javshaniarr[f]}/" target="_blank">${javshaniarr[f]}</a>`;
                    } else {
                        queue += `,<a href="/crm/deal/details/${javshaniarr[f]}/" target="_blank">${javshaniarr[f]}</a>`;
                    }
                }
            }

            detailsList.innerHTML += `<li><b>ჯავშნის რიგი: </b><span>${queue}</span></li>`;
        }

        if (apartment["OWNER_CONTACT"]) {
            linkshtmlContact = `<a href="//146.255.242.182/crm/contact/details/${apartment["OWNER_CONTACT"]}/" target="_blank">${apartment["OWNER_CONTACT_NAME"]}</a>`;
            detailsList.innerHTML += `<li><b>კონტაქტი: </b><span>${linkshtmlContact}</span></li>`;
        }
        
        if (apartment["DEAL_RESPONSIBLE"]) {
            linkshtmlResponsible = `<a href="//146.255.242.182/company/personal/user/${apartment["DEAL_RESPONSIBLE"]}/" target="_blank">${apartment["DEAL_RESPONSIBLE_NAME"]}</a>`;
            detailsList.innerHTML += `<li><b>პასუხისმგებელი: </b><span>${linkshtmlResponsible}</span></li>`;
        }
        
        // დამატებითი დეტალები
        const moreDetailsList = document.getElementById("popupDetailsMore");
        moreDetailsList.innerHTML = `
            <li><b>საძინებლების რაოდენობა: </b> ${apartment["Bedrooms"] || "—"}</li>
            <li><b>კონდიცია: </b> ${apartment["_H8WF0T"] || "—"}</li>
            <li><b>პროექტის დასრულების თარიღი: </b> ${apartment["projEndDate"] || "—"}</li>
        `;
        
        // LOOSEN UP MY BUTTONS BABE (UH HUH) 
        // BUT YOU KEEP FRONTIN' (HUH)
        if (!openedOnDeal || (apartment["STATUS"] == "გაყიდული") || (apartment["STATUS"] == "NFS") || !inAllowedStages || inProdBox || incontainer) {
            document.getElementById("popupSelectBtn").style.display = "none";
        } 
        else {
            document.getElementById("popupSelectBtn").style.display = "flex";                
        }

        let floor2 = document.getElementById("block-labels");
        let apsDisplay2 = document.getElementById("apsDisplay");
        let floorsDiv2 = document.getElementById("floors");
        if (parseInt(getComputedStyle(floor2).width) > parseInt(getComputedStyle(apsDisplay2).maxWidth)) {
            floorsDiv2.style.paddingBottom = "16px";
        } else {
            floorsDiv2.style.paddingBottom = "9px";
        }
    }

    // Popup close button
    document.getElementById("popupClose").addEventListener("click", () => {
        document.getElementById("apartmentPopup").classList.remove("active");
        let apartmentId = document.getElementById("popupTitle").dataset.id;
        document.querySelector(`#apsDisplay .apt[data-id="${apartmentId}"]`).style.transform = "scale(1)";
        document.querySelector(`#apsDisplay .apt[data-id="${apartmentId}"]`).style.border = "none";

        if (openedOnDeal) {
            document.getElementById("apsDisplay").style.maxWidth = '820px';
        } else {
            document.getElementById("apsDisplay").style.maxWidth = "1170px";
        }
    });
    
    // close popup when clicking outside of it
    document.addEventListener("click", (e) => {
        const popup = document.getElementById("apartmentPopup");
        if (popup.classList.contains("active")) {
            const isInside = popup.contains(e.target);
            const isApt = e.target.closest(".apt");
            let apartmentId = document.getElementById("popupTitle").dataset.id;
            
            if (!isInside && !isApt) {
                popup.classList.remove("active");
                
                document.querySelector(`#apsDisplay .apt[data-id="${apartmentId}"]`).style.transform = "scale(1)";
                document.querySelector(`#apsDisplay .apt[data-id="${apartmentId}"]`).style.border = "none";
                // Restore apsDisplay width when popup closes
                if (openedOnDeal) {
                    document.getElementById("apsDisplay").style.maxWidth = '820px';
                } else {
                    document.getElementById("apsDisplay").style.maxWidth = "1170px";
                }
            } 
                

        }
    });

    // popup extra details toggle
    document.getElementById("toggleDetailsBtn").addEventListener("click", function () {
        const wrapper = document.getElementById("popupDetailsWrapper");

        wrapper.classList.toggle("open");

        if (wrapper.classList.contains("open")) {
            this.textContent = "▼ დამალე";
        } else {
            this.textContent = "► დამატებითი დეტალები";
        }
    });
    
    
    // update filter texts when filtered
    function updateDropdownHeader(dropdownId, defaultText) {
        const values = getCheckboxValues(dropdownId);
        const header = document.querySelector(`#${dropdownId} .dropdown-header`);
        if (values.length > 0) {
            header.textContent = values.join(", ");
        } else {
            header.textContent = defaultText;
        }
    }

    // Update headers on checkbox change
    $(".dropdown-content input[type=checkbox]").on("change", function() {
        const parentId = $(this).closest(".dropdown-checkbox").attr("id");

        const defaultText = parentId === "statusFilter" ? "სტატუსი" :
                            parentId === "conditionFilter" ? "კონდიცია" :
                            parentId === "apartmentTypeFilter" ? "ფართის ტიპი" :
                            parentId === "blockFilter" ? "ბლოკი" : "";
        updateDropdownHeader(parentId, defaultText);
    });

    // ============================== EXTRA FILTER FUNCTIONALITIES ==============================
    // Function to add the extra filter chip
    function addFilter(id, code, value, buttonElement) {
        const chipsContainer = document.getElementById("extraFilterChips");

        range_filters = ['61', '62', '63', '64', '74', '76', '77', '78', '83', '84', '85', '86', '87', '88', '89', '103', '105', '106', '107'];
        // Create chip element

        const chip = document.createElement("div");
        chip.className = "filter-chip";
        if(range_filters.includes(id)){
            chip.innerHTML = `
                <div class="range-filter">
                    <label>${value}</label>
                    <div class="range-row">
                        <input type="number" id="${code}_Min" placeholder="Min">
                        <span>-</span>
                        <input type="number" id="${code}_Max" placeholder="Max">
                    </div>
                </div>
            `;
        } else {
            chip.innerHTML = `
                <input type="text" id="textFilter" placeholder="${value}">
            `;
        }

        // Color button element when added
        buttonElement.style.background = "#f88"; // visual feedback
        buttonElement.disabled = true;
        buttonElement.style.opacity = "0.5"; // optional visual feedback
        buttonElement.style.cursor = "not-allowed";
        buttonElement.classList.add("disabled-button");

        // Add remove button
        const removeBtn = document.createElement("button");
        removeBtn.textContent = "x";
        removeBtn.className = "remove-chip";
        removeBtn.addEventListener("click", (event) => {
            event.stopPropagation(); // prevent dropdown from closing

            chip.remove();
            // Restore button element style
            buttonElement.style.background = "none";

            buttonElement.style.opacity = "1";
            buttonElement.style.cursor = "pointer";

            // Re-enable button when chip is removed
            buttonElement.disabled = false;
            buttonElement.classList.remove("disabled-button");
        });

        chip.appendChild(removeBtn);
        chipsContainer.appendChild(chip);
    }

    // Populate extra filters for +
    function fillAdditionalFilters() {
        const container = document.getElementById("filterButtonsContainer");
        container.innerHTML = ""; // clear previous

        if (!Array.isArray(productProperties)) return;
        allowedExtraFilters = ['61', '62', '63', '64', '74', '75', '76', '77', '78', '83', '84', '85', '86', '87', '88', '89', '103', '105', '106', '107', '108']

        productProperties.forEach(prop => {
            // skip if not allowed
            if (!allowedExtraFilters.includes(prop.ID)) return;

            // else append
            const button = document.createElement("button");
            button.className = "extraFilters";
            button.dataset.id = prop.CODE; // store ID in data attribute
            button.textContent = prop.NAME;

            // Add click listener to append filter
            button.addEventListener("click", () => addFilter(prop.ID, prop.CODE, prop.NAME, button));

            container.appendChild(button);
        });
    }
    fillAdditionalFilters();

    // Search functionality for extra filters
    document.getElementById("filterSearch")?.addEventListener("input", function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const buttons = document.querySelectorAll("#filterButtonsContainer .extraFilters");
        
        buttons.forEach(button => {
            const name = button.textContent;
            if (name.includes(searchTerm)) {
                button.style.display = "block";
            } else {
                button.style.display = "none";
            }
        });
    });

    // Toggle addFiltersDropdown when + is clicked
    $("#addFiltersBtn").on("click", function (event) {
        event.stopPropagation(); // prevent closing immediately
        const content = $(this).siblings(".dropdown-content");
        let containerDiv = document.querySelector(".containerCatalog");
        let currentHeight = parseInt(getComputedStyle(containerDiv).height);

        // Close other dropdowns
        $(".dropdown-content").not(content).slideUp(150);

        // Toggle this one
        content.slideToggle(150);
    });

    // Prevent closing when clicking inside the content
    $("#addFiltersDropdown .dropdown-content").on("click", function(event){
        event.stopPropagation();
    });

    // Close when clicking elsewhere
    $(document).on("click", function () {
        $(".dropdown-content").slideUp(150);
    });

    // for filtering apartments
    function getExtraFilterValues() {
        const extraFilters = {};

        document.querySelectorAll("#extraFilterChips .filter-chip").forEach(chip => {
            const rangeInput = chip.querySelector(".range-filter");
            const textInput = chip.querySelector("input[type=text]");

            if (rangeInput) {
                const min = parseFloat(chip.querySelector("input[id$='_Min']").value);
                const max = parseFloat(chip.querySelector("input[id$='_Max']").value);
                const label = chip.querySelector("label").textContent;
                const input = document.querySelector('.filter-chip input');
                const code = input.id.split("_")[0];
                extraFilters[code] = { min: isNaN(min) ? null : min, max: isNaN(max) ? null : max };
            } else if (textInput) {
                const value = textInput.value.trim();
                const input = document.querySelector('.filter-chip input');
                const code = input.id.split("_")[0];
                if (value) extraFilters[code] = value;
            }
        });

        return extraFilters;
    }

    // ========================== PRODUCTS BOX ==========================
    // Function to add apartment to box
    function addSelectedApartment() {
        const container = document.getElementById("productsBox");
        const popupTitle = document.getElementById("popupTitle");

        const apartmentId = popupTitle.dataset.id;
        const apartmentNumber = popupTitle.innerText.split(" ")[1].replace("N", "");
        const status = popupTitle.dataset.status;

        if(status=="გაყიდული"){
            alert("ბინა უკვე გაყიდულია. თქვენ არ გაქვთ ბინის დამატების უფლება");
            return;
        } 
        // Prevent duplicates
        if (container.querySelector(`.apt[data-id="${apartmentId}"]`)) {
            alert("ბინა უკვე დამატებულია სიაში");
            return;
        }

        switch (status) {
            case "თავისუფალი":
                statusForClassList = 'active';
                break;

            case "ჯავშნის რიგი":
                statusForClassList = 'queue';
                break;
            
            case "დაჯავშნილი":
                statusForClassList = 'reserved';
                break;
            
            case "გაყიდული":
                statusForClassList = 'sold';
                break;

            default:
                statusForClassList = 'notforsale';
        };


        const aptTile = document.createElement("div");
        aptTile.classList.add("apt", `status-${statusForClassList}`);
        aptTile.dataset.id = apartmentId;
        aptTile.dataset.status = status;
        aptTile.textContent = apartmentNumber;
        
        // Select the apartment div with the specific data-id
        let apartmentDiv = document.querySelector(`#apsDisplay .apt[data-id="${apartmentId}"]`);

        // If it exists, add a class
        if (apartmentDiv) {
            apartmentDiv.classList.add("dimmed");
        }


        // Optional: add remove button
        const removeBtn = document.createElement("button");
        removeBtn.textContent = "×";
        removeBtn.classList.add("remove-chip");
        removeBtn.onclick = () => {
            aptTile.remove();
            // Re-query the apartment div to potentially remove dimmed class
            let apartmentToUndim = document.querySelector(`#apsDisplay .apt[data-id="${apartmentId}"]`);
            if (apartmentToUndim) {
                // Check if it should still be dimmed by filters
                const filters = getAllFilters();
                const apartment = productsCache.find(p => p["ID"] == apartmentId);
                
                let matchesFilters = true;
                
                if (apartment) {
                    // Status filter
                    if (filters.status.length > 0 && !filters.status.includes(apartment["STATUS"])) matchesFilters = false;
                    
                    // Condition filter
                    if (filters.condition.length > 0 && !filters.condition.includes(apartment["_H8WF0T"])) matchesFilters = false;
                    
                    // Apartment type filter
                    if (filters.aptType.length > 0 && !filters.aptType.includes(apartment["PRODUCT_TYPE"])) matchesFilters = false;
                    
                    // Blocks filter
                    if (filters.blocks.length > 0 && !filters.blocks.includes(apartment["KORPUSIS_NOMERI_XE3NX2"])) matchesFilters = false;
                    
                    // Apartment range filter
                    const min = parseInt(filters.aptRange.min);
                    const max = parseInt(filters.aptRange.max);
                    const aptNum = parseInt(apartment["Number"]);
                    if (!isNaN(min) && aptNum < min) matchesFilters = false;
                    if (!isNaN(max) && aptNum > max) matchesFilters = false;
                    
                    // Extra filters
                    for (const [key, val] of Object.entries(filters.extraFilters)) {
                        const prop = apartment[key];
                        if (typeof val === "object") { // range
                            if ((val.min !== null && prop < val.min) || (val.max !== null && prop > val.max)) {
                                matchesFilters = false;
                                break;
                            }
                        } else { // text
                            if (!prop || !prop.toString().toLowerCase().includes(val.toLowerCase())) {
                                matchesFilters = false;
                                break;
                            }
                        }
                    }
                }
                
                // Only remove dimmed if it matches current filters
                if (matchesFilters) {
                    apartmentToUndim.classList.remove("dimmed");
                }
            }
            
            // if (container.children.length === 0) {
            //     parentContainer.style.display = "none";
            // }
        }
        removeBtn.style.marginLeft = "4px";
        removeBtn.style.fontSize = "10px";
        removeBtn.style.background = "transparent";
        removeBtn.style.border = "none";
        removeBtn.style.cursor = "pointer";

        aptTile.appendChild(removeBtn);
        container.appendChild(aptTile);

        document.getElementById("popupSelectBtn").style.display = "none";
    }


    // trigger when user clicks "damateba" in popup
    document.getElementById("popupSelectBtn")?.addEventListener("click", () => {
        addSelectedApartment();
    });

    // ====================================== SAVE APTS ======================================
    // save 
    function saveApartments(productIds){
        fetch(`/rest/local/api/projects/saveApartments.php?deal_id=${dealID}&productIds=${productIds}`).then(data => {
            return data.json();
        }).then(data => {
            if(data.status == 200){
                alert(data.message);
                location.reload();
            } else {
                alert(data.error);
                // Re-enable button on error
                const saveBtn = document.getElementById("saveBtn");
                saveBtn.disabled = false;
                saveBtn.textContent = "Save";
                saveBtn.style.opacity = "1";
            }
        }).catch((err) => {
            console.log('error:', err);
            // Re-enable button on error
            const saveBtn = document.getElementById("saveBtn");
            saveBtn.disabled = false;
            saveBtn.textContent = "Save";
            saveBtn.style.opacity = "1";
        });
    }

    // damatebuli produqtebis shenaxva
    document.getElementById("saveBtn")?.addEventListener("click", () => {
        console.log("ra xdebatqo bejan");
        const saveBtn = document.getElementById("saveBtn");
        const container = document.getElementById("productsBox");
        let shesanaxiAptsIds = [...container.children].map(el => Number(el.dataset.id));
        console.log(shesanaxiAptsIds);

        // Disable button and show loading state
        saveBtn.disabled = true;
        saveBtn.textContent = "...";
        saveBtn.style.opacity = "0.6";

        saveApartments(shesanaxiAptsIds);
    });

    // ============================== TRANSLATE ==============================
    function initGTranslate() {        
        const translateContainer = document.createElement("div");
        translateContainer.className = "gtranslate_wrapper";
        let containerDiv = document.querySelector(".containerCatalog");

        if (!containerDiv) {
            console.log("containerCatalog not found yet");
            return;
        }

        if(openedOnDeal) {
            translateContainer.style.display = "flex";
            translateContainer.style.justifyContent = "center";
            translateContainer.style.alignItems = "center";
            translateContainer.style.width = "45px";
            translateContainer.style.height = "20px";
            translateContainer.style.top = "40px";
            translateContainer.style.left = "192px";
        }
        
        containerDiv.insertBefore(translateContainer, containerDiv.firstChild);

        const settingsScript = document.createElement('script');
        settingsScript.textContent = `
            window.gtranslateSettings = {
                "default_language": "ka",
                "languages": ["ka", "en", "ru"],
                "wrapper_selector": ".gtranslate_wrapper",
                "flag_size": 24
            };
        `;
        document.body.appendChild(settingsScript);

        const gtranslateScript = document.createElement('script');
        gtranslateScript.src = "https://cdn.gtranslate.net/widgets/latest/flags.js";
        gtranslateScript.defer = true;
        
        document.body.appendChild(gtranslateScript);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initGTranslate);
    } else {
        initGTranslate();
    }


    // ===================== IMAGE CAROUSEL =====================
    let currentImageIndex = 0;
    let carouselImages = [];

    // Open carousel when popup image is clicked
    document.addEventListener("click", (e) => {
        if (e.target.id === "popupImg" && e.target.src) {
            document.querySelector(".gtranslate_wrapper").style.display = "none";;
            const apartmentId = document.getElementById("popupTitle").dataset.id;
            const apartment = productsCache.find(p => p["ID"] == apartmentId) || 
                            products.find(p => p["ID"] == apartmentId);
            
            if (apartment) {
                // Prepare image array (main image + 2 placeholder images)
                // You can modify this to use actual property images if available
                carouselImages = [
                    apartment["image"] || "",
                    apartment["image2"] || apartment["image"] || "", // fallback to main image
                    apartment["image3"] || apartment["image"] || ""  // fallback to main image
                ];
                
                currentImageIndex = 0;
                openCarousel();
            }
        }
    });

    function openCarousel() {
        const carousel = document.getElementById("imageCarouselPopup");
        carousel.classList.add("active");
        updateCarouselImage();
    }

    function closeCarousel() {
        const carousel = document.getElementById("imageCarouselPopup");
        carousel.classList.remove("active");
        document.querySelector(".gtranslate_wrapper").style.display = "block";;
    }

    function updateCarouselImage() {
        const img = document.getElementById("carouselImage");
        img.src = carouselImages[currentImageIndex];
        
        // Update counter
        document.getElementById("currentImageNum").textContent = currentImageIndex + 1;
        document.getElementById("totalImages").textContent = carouselImages.length;
        
        // Update dots
        document.querySelectorAll(".carousel-dot").forEach((dot, index) => {
            if (index === currentImageIndex) {
                dot.classList.add("active");
            } else {
                dot.classList.remove("active");
            }
        });
    }

    function nextImage() {
        currentImageIndex = (currentImageIndex + 1) % carouselImages.length;
        updateCarouselImage();
    }

    function prevImage() {
        currentImageIndex = (currentImageIndex - 1 + carouselImages.length) % carouselImages.length;
        updateCarouselImage();
    }

    // Event listeners
    document.getElementById("carouselClose").addEventListener("click", closeCarousel);

    document.querySelector(".carousel-arrow.next").addEventListener("click", nextImage);

    document.querySelector(".carousel-arrow.prev").addEventListener("click", prevImage);

    // Dot navigation
    document.querySelectorAll(".carousel-dot").forEach(dot => {
        dot.addEventListener("click", () => {
            currentImageIndex = parseInt(dot.dataset.index);
            updateCarouselImage();
        });
    });

    // Keyboard navigation
    document.addEventListener("keydown", (e) => {
        const carousel = document.getElementById("imageCarouselPopup");
        if (!carousel.classList.contains("active")) return;
        
        if (e.key === "ArrowRight") nextImage();
        if (e.key === "ArrowLeft") prevImage();
        if (e.key === "Escape") closeCarousel();
    });

    // Close on background click
    document.getElementById("imageCarouselPopup").addEventListener("click", (e) => {
        if (e.target.id === "imageCarouselPopup") {
            document.querySelector(".gtranslate_wrapper").style.display = "block";
            closeCarousel();
        }
    });

</script>

</body>

</html>