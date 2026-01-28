<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/element.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/functions.php");

use Bitrix\Main\Loader;
CModule::IncludeModule('crm');

// Initialize Bitrix JS libraries - IMPORTANT!
CJSCore::Init(array("jquery", "date"));

GLOBAL $USER;

$APPLICATION->SetTitle("");


// ================== ფუნქციები ==================

/**
 * NBG-ის კურსის მიღება
 */
function getNBGkursi($date) {
    $url = "https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies?Currencies=USD&date={$date}";
    $response = file_get_contents($url);
    $data = json_decode($response);
    return $data[0]->currencies[0]->rate ?? 0;
}

// ================== პარამეტრების ინიციალიზაცია ==================

$prod_ID = $_GET["ProductID"] ?? null;
$docID = $_GET["docID"] ?? null;
$dealID = null;
$dealGet = "fromInventory";

if (!empty($_GET["dealid"]) && $_GET["dealid"] != "UNDEFINED") {
    $dealGet = $_GET["dealid"];
    $dealID = $_GET["dealid"];
}

// თავდაპირველი მნიშვნელობები
$dealProdPrice = 0;
$totalKVM = 0;
$dateForNBG = date("Y-m-d");
$hasProduct = false;
$binisNomeri = "";
$projEndDate = "";

// ================== დილის მონაცემების დამუშავება ==================

if ($dealID) {
    if (!is_numeric($dealID)) exit("არასწორი დილის ID");
    
    $dealData = getDealInfoByID($dealID);
    
    if (!$dealData) {
        exit("დილი ვერ მოიძებნა");
    }
    
    // გადახდების შემოწმება
    $payed = getCIBlockElementsByFilter(array(
        "IBLOCK_ID" => 21,
        "PROPERTY_DEAL" => $dealID
    ));
    
    if (count($payed) || $dealData["STAGE_ID"] == "WON") {
        header("Location: /custom/calculator/restruct.php?dealid=$dealID");
        exit();
    }
    
    $dealProds = CCrmDeal::LoadProductRows($dealID);
    $chabarebatype = "მწვანე კარკასი";
    $dealProdPrice = $dealData["OPPORTUNITY"];


    
    // პროდუქტის მონაცემები
    if ($prod_ID) {
        $productInfo = getProductDataByID($prod_ID);
        $totalKVM += is_numeric($productInfo[0]["TOTAL_AREA"]) ? $productInfo[0]["TOTAL_AREA"] : 1;
        $projEndDate = $productInfo[0]["projEndDate"];
        $binisNomeri = $productInfo[0]["Number"];
        $dealProdPrice = $productInfo[0]["PRICE"];
        $hasProduct = false;
    } 
    elseif (count($dealProds)) {
        foreach ($dealProds as $dealProd) {
            $prod_ID = $dealProds[0]["PRODUCT_ID"];
            $productInfo = getProductDataByID($prod_ID);
            // printArr($productInfo);
            $totalKVM += is_numeric($productInfo[0]["TOTAL_AREA"]) ? $productInfo[0]["TOTAL_AREA"] : 1;
            $projEndDate = $productInfo[0]["projEndDate"];
            $dealProdPrice = $productInfo[0]["PRICE"];
            $binisNomeri = $productInfo[0]["Number"];
        }
        $hasProduct = true;
    } 
    else {
        exit("დილზე პროდუქტი არ არის მიბმული");
    }
    
    // გაფორმების თარიღი
    $gaformebisTarigi = $dealData["UF_CRM_1693398443196"];
    if ($gaformebisTarigi) {
        $dateArr = explode("/", $gaformebisTarigi);
        $dateForNBG = $dateArr[2] . "-" . $dateArr[1] . "-" . $dateArr[0];
    }
}
elseif ($prod_ID) {
    $productInfo = getProductDataByID($prod_ID);
    $dealProdPrice = $productInfo[0]["PRICE"];
    $totalKVM = is_numeric($productInfo[0]["TOTAL_AREA"]) ? $productInfo[0]["TOTAL_AREA"] : 1;
    $projEndDate = $productInfo[0]["projEndDate"];
    $dealID = 1;
    $binisNomeri = $productInfo[0]["Number"];
}
else {
    exit("არასწორი პარამეტრები");
}

// ================== გრაფიკის დოკუმენტის შემოწმება ==================

$graphHeaderByDoc = "";
$graphByDoc = array();

if (is_numeric($docID)) {
    $calculation = getElementByID($docID);
    $json = str_replace("&quot;", "\"", $calculation["HEADER_JSON"]);
    $jsonGraph = str_replace("&quot;", "\"", $calculation["GRAPH_JSON"]);
    $graphHeaderByDoc = json_decode($json, true);
    $graphByDoc = json_decode($jsonGraph, true);
    
    if ($binisNomeri != $graphHeaderByDoc["binisNomeri"]) {
        exit("არასწორი გრაფიკი");
    }
}

// ================== განვადების გეგმების მომზადება ==================

$gegmaFilter = array(
    "IBLOCK_ID" => 22,
    "PROPERTY_product_type" => getWorkflowFieldsKeyByValue("147", $productInfo[0]["PRODUCT_TYPE"]),
    "PROPERTY_PROJECT_LIST" => getWorkflowFieldsKeyByValue("211", $productInfo[0]["PROJECT"]),
    "PROPERTY_CORP_LIST" => getWorkflowFieldsKeyByValue("220", $productInfo[0]["BUILDING"]),

    "PROPERTY_floor" => getWorkflowFieldsKeyByValue("148", $productInfo[0]["FLOOR"]),
    "PROPERTY_ACTIVE" => 115,
);

$gegmaElements = getCIBlockElementsByFilter($gegmaFilter);

// printArr($gegmaElements);

$holiday = getHolidays();
$instalmentPlanArr = array();
$kvmPrice = $productInfo[0]["TOTAL_AREA"] ? round($dealProdPrice / $productInfo[0]["TOTAL_AREA"], 2) : $dealProdPrice;
$startSqmPrice = $productInfo[0]["KVM_PRICE"];

$scheduleTypeArr["customType"] = array(
    "price" => $dealProdPrice,
    "kvmPrice" => $kvmPrice,
    "discountAmpunt" => 0,
    "oldPrice" => $dealProdPrice,
    "TOTAL_AREA" => $totalKVM,
    "projEndDate" => $projEndDate,
    "startSqmPrice" => $startSqmPrice
);

$oldPrice = $dealProdPrice;

foreach ($gegmaElements as $element) {
    if (in_array($productInfo[0]["FLOOR"], $element["floor"])) {
        $instalmentPlanArr[$element["ID"]] = $element["NAME"];
        
        if ($element["discount_type"] == "თანხა") {
            $discountAmpunt = round($element["discount"] * $productInfo[0]["TOTAL_AREA"], 2);
        } else {
            $discountAmpunt = round($element["discount"] / 100 * $oldPrice, 2);
        }
        
        $price = round($oldPrice - $discountAmpunt, 2);
        $kvmPrice = $productInfo[0]["TOTAL_AREA"] ? round($price / $productInfo[0]["TOTAL_AREA"], 2) : 0;
        $advancePayAmount = round($price / 100 * $element["Advance_payment"], 2);
        $lastPayment = round($price / 100 * $element["lastPayment"], 2);
        
        $scheduleTypeArr[$element["ID"]] = array(
            "price" => $price,
            "oldPrice" => $oldPrice,
            "kvmPrice" => $kvmPrice,
            "startSqmPrice" => $startSqmPrice,
            "TOTAL_AREA" => $productInfo[0]["TOTAL_AREA"] ?: 1,
            "month" => $element["endDate"],
            "advancePayAmount" => $advancePayAmount,
            "discountAmpunt" => $discountAmpunt,
            "advancedPercentageBySchedule" => $element["Advance_payment"],
            "lastPayment" => $lastPayment,
            "lastPaymentPercent" => $element["lastPayment"],
            "projEndDate" => $projEndDate,
        );
    }
}

$nbgKursi = getNBGkursi($dateForNBG);
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.min.css">
    <link rel="stylesheet" href="//fonts.googleapis.com/earlyaccess/notosansgeorgian.css">
    
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Noto Sans Georgian', sans-serif;
            font-size: 14px;
            margin: 0;
            padding: 20px;
        }

        h1 {
            text-align: center;
            font-size: 28px;
            margin: 0 0 30px 0;
        }

        .main-container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
        }

        .form-field.full-width {
            grid-column: span 5;
        }

        .form-field.half-width {
            grid-column: span 2;
        }

        @media (max-width: 1200px) {
            .form-row {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .form-field.full-width {
                grid-column: span 3;
            }
        }

        @media (max-width: 900px) {
            .form-row {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-field.full-width {
                grid-column: span 2;
            }
            
            .form-field.half-width {
                grid-column: span 2;
            }
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-field.full-width,
            .form-field.half-width {
                grid-column: span 1;
            }
        }

        .form-field label {
            display: block;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .form-control,
        .form-select {
            width: 100%;
            height: 35px;
            padding: 0 10px;
            font-size: 12px;
            border: 1px solid #929292;
            border-radius: 3px;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: #abd4f3ff;
        }

        .form-control:disabled {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .green-border {
            border-color: #abd4f3ff !important;
        }

        .section-divider {
            border-top: 2px solid #ccc;
            margin: 10px 0;
        }

        .btn-primary-custom {
            background-color: #abd4f3ff;
            border: none;
            color: white;
            padding: 10px 20px;
            font-size: 14px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            color: black;
        }

        .btn-primary-custom:hover {
            background-color: #25679aff;
            transform: translateY(-1px);
        }

        .btn-success-custom {
            background-color: #25679aff;
            border: none;
            color: white;
            padding: 10px 20px;
            font-size: 14px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .table-graph {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            font-size: 12px;
        }

        .table-graph th {
            background-color: #abd4f3ff;
            color: white;
            padding: 10px;
            text-align: center;
            border: 1px solid black;
        }

        .table-graph td {
            padding: 8px;
            text-align: center;
            border: 1px solid black;
        }

        .table-graph tbody tr:hover {
            background-color: #f0f0f0;
        }

        .table-graph input {
            border: none;
            width: 100%;
            text-align: center;
            background: transparent;
        }

        .weekend-red {
            color: red !important;
        }

        .error-message {
            color: red;
            font-weight: bold;
            margin: 10px 0;
        }

        .success-message {
            color: green;
            font-weight: bold;
            margin: 10px 0;
        }

        .hidden {
            display: none !important;
        }

        /* Print styles */
        @media print {
            .element-to-hide {
                display: none !important;
            }
            
            @page {
                margin: 0.21in 0.33in 0.89in 0.35in;
            }
        }

        /* Number input styling */
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type="number"] {
            -moz-appearance: textfield;
        }

        textarea.form-control {
            /* height: auto !important; */
            /* min-height: 60px; */
            padding: 8px 10px;
            max-width: 950px;
        }

        .file-input {
            font-size: 12px;
            padding: 4px;
        }

        .fileFiled{
            width: 200px;
        }

        .DEALID{
            display: flex;
            align-items: center;
            padding: 0px 10px;
        }

        .export-btn {
            background-color: #9ec6d3;
            border: none;
            color: black;
            padding: 8px 16px;
            font-size: 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;

        }

        .export-btn:hover {
            background-color: #7fb5c4;
        }

        .buttonsDiv{
            padding-left: 1300px;
            padding-bottom: 20px;
        }

    </style>
</head>
<body>

<div class="main-container calc">
    <h1>განვადების კალკულატორი</h1>

    <!-- Error/Success Messages -->
    <div id="messages"></div>
    <div id="errors"></div>
    <span id="confirmTXT" class="error-message element-to-hide hidden">* განვადება საჭიროებს დასტურს</span>


        <!-- Export Buttons -->
    <div class="export-buttons element-to-hide buttonsDiv">
        <button class="export-btn" onclick="openOffer('grafik')">გრაფიკი (GEO)</button>
        <button class="export-btn" onclick="openOffer('grafikEng')">გრაფიკი (ENG)</button>
        <!-- <button class="export-btn" onclick="openOffer('geo')" id="offerGeo">offer (GEO)</button>
        <button class="export-btn" onclick="openOffer('eng')" id="offerEng">offer (ENG)</button>
        <button class="export-btn hidden" onclick="openOffer('geoOdd')" id="offerGeoOdd">offer (GEO) 20-80</button>
        <button class="export-btn hidden" onclick="openOffer('engOdd')" id="offerEngOdd">offer (ENG) 20-80</button>
        <button class="export-btn" onclick="openRender('renderebi')">ნახაზები</button>
        <button class="export-btn" onclick="openRender('naxazebi')">რენდერები</button>
        <button class="export-btn" onclick="openRender('gegmareba')">გეგმარება</button>
        <button class="export-btn" onclick="exportTableToExcel()">Excel</button> -->
    </div>


    <!-- ძირითადი ინფორმაცია -->
    <div class="form-row">
        <div class="form-field" style="display: none;">
            <label>ჩაბარების ტიპი</label>
            <select id="chabarebatype" class="form-select green-border">
                <option value="მწვანე კარკასი" selected>მწვანე კარკასი</option>
                <option value="თეთრი კარკასი">თეთრი კარკასი</option>
                <option value="რემონტი">რემონტი</option>
            </select>
        </div>

        <div class="form-field element-to-hide " >
            <label>მოლაპარაკება №</label>
            <div id="DEALID" class="form-control DEALID" style="font-weight: bold;"></div>
        </div>

        <div class="form-field">
            <label>უძრავი ქონების №</label>
            <input id="binisNomeri" class="form-control" disabled style="font-weight: bold;">
        </div>

        <div class="form-field">
            <label>მ²</label>
            <input id="total_kvm" class="form-control" disabled type="number" style="font-weight: bold;">
        </div>

        <div class="form-field">
            <label>მშენებლობის დასრულების თარიღი</label>
            <input id="projectAndDate" class="form-control" disabled style="font-weight: bold;">
        </div>
    </div>

    <!-- განვადების ტიპი -->
    <div class="form-row">
        <div class="form-field">
            <label>განვადების ტიპი</label>
            <select id="ganvadebaType" class="form-select green-border" onchange="changeType();">
                <option value="customType" selected>არასტანდარტული</option>
                <option value="mortgage">განვადება</option>
                <option value="allCash">ერთიანი გადახდა</option>
                <option value="bankLoan">ბანკის სესხი</option>
            </select>
        </div>

        <div class="form-field">
            <label>გადახდის ტიპი</label>
            <select id="type_select" class="form-select green-border" onchange="checkGadaxdaType()">
                <option value="customType" selected>არასტანდარტული</option>
            </select>
        </div>

        <div class="form-field element-to-hide">
            <label>გადახდის პერიოდულობა</label>
            <select id="period" class="form-select green-border">
                <option value="1" selected>თვეში ერთხელ</option>
                <option value="3">3 თვეში ერთხელ</option>
                <option value="4">4 თვეში ერთხელ</option>
                <option value="6">6 თვეში ერთხელ</option>
                <option value="12">წელიწადში ერთხელ</option>
            </select>
        </div>

        <div class="form-field fileFiled element-to-hide" >
            <label>გრაფიკის ფაილი (.xlsx)</label>
            <input type="file" id="fileInput" name="planFile" class="form-control file-input">
        </div>
    </div>

    <div class="section-divider"></div>

    <!-- ფასები - USD -->
    <div class="form-row element-to-hide">
        <div class="form-field">
            <label>საწყისი ფასი $</label>
            <input id="startPrice" class="form-control" disabled>
        </div>

        <div class="form-field">
            <label>საწყისი m² ფასი $</label>
            <input id="startSqmPrice" class="form-control" disabled>
        </div>

        <div class="form-field">
            <label>ფასდაკლება</label>
            <select id="discountBY" class="form-select green-border">
                <option value="fullPrice" selected>სრულ ფართზე</option>
                <option value="kvm">მ²_ზე</option>
            </select>
        </div>

        <div class="form-field">
            <label>ფასდაკლების ტიპი</label>
            <select id="discountType" class="form-select green-border">
                <option value="AMOUNT" selected>თანხა</option>
                <option value="PERCENT">პროცენტი</option>
            </select>
        </div>

        <div class="form-field">
            <label>ფასდაკლება</label>
            <input id="discountNum" class="form-control green-border" value="0" 
                   oninput="validateNumericInput(event,this)" 
                   onblur="handleInputFinish(this,'discountNum')">
        </div>

        <input id="discount" type="hidden" value="0">
    </div>

    <!-- ფასები - GEL -->
    <div class="form-row element-to-hide">
        <div class="form-field">
            <label>საწყისი ფასი ₾</label>
            <input id="startPriceGel" class="form-control" disabled>
        </div>

        <div class="form-field">
            <label>საწყისი m² ფასი ₾</label>
            <input id="startSqmPriceGel" class="form-control" disabled>
        </div>

        <div class="form-field">
            <label>NBG კურსი</label>
            <input id="nbgKursi" class="form-control" type="number" disabled>
        </div>
    </div>

    <div class="section-divider"></div>

    <!-- საბოლოო ფასები -->
    <div class="form-row">
        <div class="form-field">
            <label>საბოლოო ფასი $</label>
            <input id="price" class="form-control" disabled>
        </div>

        <div class="form-field element-to-hide">
            <label>საბოლოო m² ფასი $</label>
            <input id="kvmPrice" class="form-control" disabled>
        </div>

        <div class="form-field">
            <label>საბოლოო ფასი ₾</label>
            <input id="priceGel" class="form-control" disabled>
        </div>

        <div class="form-field element-to-hide">
            <label>საბოლოო m² ფასი ₾</label>
            <input id="kvmPriceGel" class="form-control" disabled>
        </div>
    </div>

    <div class="section-divider"></div>

    <!-- გადახდების თარიღები და თანხები -->
    <div class="form-row element-to-hide">
        <div class="form-field">
            <label>ჯავშნის თარიღი</label>
            <input id="bookPayDate" class="form-control green-border" type="text" 
                   placeholder="dd/mm/YYYY" autocomplete="off"
                   onclick="BX.calendar({node: this, field: this, bTime: false, bSetFocus: false})">
        </div>

        <div class="form-field">
            <label>ჯავშნის ავანსი $</label>
            <input id="bookPayment" class="form-control green-border" 
                   oninput="validateNumericInput(event,this)" 
                   onblur="handleInputFinish(this,'bookPayment')">
        </div>
    </div>

    <div class="form-row element-to-hide">
        <div class="form-field">
            <label>პირველადი შენატანის თარიღი</label>
            <input id="advancePayDate" class="form-control green-border" type="text" 
                   placeholder="dd/mm/YYYY" autocomplete="off"
                   onclick="BX.calendar({node: this, field: this, bTime: false, bSetFocus: false})">
        </div>

        <div class="form-field">
            <label>პირველადი შენატანი $</label>
            <input id="advancePayment" class="form-control green-border" 
                   oninput="validateNumericInput(event,this)" 
                   onblur="handleInputFinish(this,'advancePayment')">
        </div>

        <div class="form-field">
            <label>პირველადი შენატანი %</label>
            <input id="advancePaymentPercent" class="form-control green-border" 
                   oninput="validateNumericInput(event,this)" 
                   onblur="handleInputFinish(this,'advancePaymentPercent')">
        </div>

        <input id="first_stage_months" type="hidden">
        <input id="firstStageAmount" type="hidden">
    </div>

    <div class="form-row element-to-hide">
        <div class="form-field">
            <label>დაწყების თარიღი</label>
            <input id="startDate" class="form-control green-border" type="text" 
                   placeholder="dd/mm/YYYY" autocomplete="off"
                   onclick="BX.calendar({node: this, field: this, bTime: false, bSetFocus: false})">
        </div>

        <div class="form-field">
            <label>დასრულების თარიღი</label>
            <input id="endDate" class="form-control green-border" type="text" 
                   placeholder="dd/mm/YYYY" autocomplete="off" onchange="getAndFillGraph()"
                   onclick="BX.calendar({node: this, field: this, bTime: false, bSetFocus: false})">
        </div>
    </div>

    <div class="form-row">
        <div class="form-field">
            <label>ბოლო შენატანის თარიღი</label>
            <input id="lastPayDate" class="form-control green-border" type="text" 
                   placeholder="dd/mm/YYYY" autocomplete="off"
                   onclick="BX.calendar({node: this, field: this, bTime: false, bSetFocus: false})">
        </div>

        <div class="form-field element-to-hide">
            <label>ბოლო შენატანი $</label>
            <input id="lastPayment" class="form-control green-border" 
                   oninput="validateNumericInput(event,this)" 
                   onblur="handleInputFinish(this,'lastPayment')">
        </div>

        <div class="form-field element-to-hide">
            <label>ბოლო შენატანი %</label>
            <input id="lastPaymentPercent" class="form-control green-border" 
                   oninput="validateNumericInput(event,this)" 
                   onblur="handleInputFinish(this,'lastPaymentPercent')">
        </div>
    </div>

    <!-- კომენტარი -->
    <div class="form-row element-to-hide">
        <div class="form-field full-width">
            <label>კომენტარი</label>
            <textarea id="commentInput" class="form-control" rows="3"></textarea>
        </div>
    </div>

    <!-- ღილაკები -->
    <div class="action-buttons element-to-hide">
        <button id="countBTN" class="btn-primary-custom" onclick="getAndFillGraph()">გამოთვლა</button>
    </div>

    <!-- გრაფიკის ცხრილი -->
    <table id="graphData" class="table-graph"></table>

    <!-- იპოთეკა -->
    <div class="action-buttons element-to-hide"  style="display: none;">
        <button id="countIpoteka" class="btn-primary-custom">იპოთეკური სესხი</button>
    </div>

    <div id="ipoteka" class="hidden" style="margin-top: 20px;">
        <div class="form-row element-to-hide">
            <div class="form-field">
                <label>თანამონაწილეობა $</label>
                <input id="participation" class="form-control green-border" type="number">
            </div>

            <div class="form-field">
                <label>თანამონაწილეობა %</label>
                <input id="participationPercent" class="form-control green-border" type="number">
            </div>

            <div class="form-field">
                <label>სესხის მოცულობა $</label>
                <input id="Loan" class="form-control green-border" type="number" disabled>
            </div>

            <div class="form-field">
                <label>სესხის მოცულობა ₾</label>
                <input id="startLoan_GEL" class="form-control green-border" type="number" disabled>
            </div>

            <div class="form-field">
                <label>ეროვნული ბანკის კურსი</label>
                <input id="NBG" class="form-control green-border" type="number" disabled>
            </div>
        </div>

        <div class="form-row element-to-hide">
            <div class="form-field">
                <label>წლიური %</label>
                <input id="annualInterest" class="form-control green-border" type="number">
            </div>

            <div class="form-field">
                <label>სესხის ვადა</label>
                <input id="annualTerm" class="form-control green-border" type="number">
            </div>

            <div class="form-field">
                <label>გადასახადი $ თვეში</label>
                <input id="monthlyPayment" class="form-control green-border" type="number" disabled>
            </div>

            <div class="form-field">
                <label>გადასახადი ₾ თვეში</label>
                <input id="monthlyPayment_GEL" class="form-control green-border" type="number" disabled>
            </div>
        </div>

        <input id="startLoan" type="hidden">
        <input id="sruliTanxa" type="hidden">
        <input id="kvmPriceIpoteka" type="hidden">
    </div>

    <!-- შენახვის ღილაკები -->
    <div class="action-buttons element-to-hide" style="margin-top: 20px;">
        <button id="saveBTN" class="btn-success-custom hidden">გრაფიკის შენახვა</button>
        <button id="saveCalculation" class="btn-success-custom hidden">კალკულაციის შენახვა</button>
    </div>

</div>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/1.3.2/jspdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="//unpkg.com/xlsx/dist/shim.min.js"></script>
<script src="//unpkg.com/xlsx/dist/xlsx.full.min.js"></script>

<script>


document.addEventListener("DOMContentLoaded", function () {
    const h1 = document.querySelector("header.app__header");
    const h2 = document.querySelector("header.page__header");

    if (h1) h1.style.display = "none";
    if (h2) h2.style.display = "none";
});

    

// ================== კონფიგურაცია ==================
const CONFIG = {
    nbgKursi: <?php echo json_encode($nbgKursi); ?>,
    instalmentPlanArr: <?php echo json_encode($instalmentPlanArr); ?>,
    scheduleTypeArr: <?php echo json_encode($scheduleTypeArr); ?>,
    holiday: <?php echo json_encode($holiday); ?>,
    hasProduct: <?php echo json_encode($hasProduct); ?>,
    graphHeaderByDoc: <?php echo json_encode($graphHeaderByDoc); ?>,
    graphByDoc: <?php echo json_encode($graphByDoc); ?>,
    dealGet: <?php echo json_encode($dealGet); ?>,
    dealID: <?php echo $dealID; ?>,
    prodID: <?php echo json_encode($prod_ID); ?>,
    binisNomeri: <?php echo json_encode($binisNomeri); ?>,
    oldPrice: <?php echo $oldPrice; ?>,
    userID: <?php echo $USER->GetID(); ?>
};

// თარგმანები
const translations = {
    en: {
        graph_date: "Date",
        graph_amount: "Amount",
        graph_left: "Left to pay",
        firstPayment: "First payment",
        lastPayment: "Last payment",
    },
    ge: {
        graph_date: "თარიღი",
        graph_amount: "თანხა",
        graph_left: "დარჩენილი ძირი",
        firstPayment: "პირველი შენატანი",
        lastPayment: "ბოლო შენატანი",
    }
};

// ================== ინიციალიზაცია ==================
let image = '';
let isDragging = false;
let fillValue = "";

// გვერდის ჩატვირთვისას
document.addEventListener('DOMContentLoaded', function() {

    
    fillType("არასტანდარტული");
    fillData();
    fillIpoteka();
    
    if (CONFIG.graphHeaderByDoc) {
        fillCalculatorHeader();
        fillGraphByDoc();
    }
    
    initializeEventListeners();
});



// ================== Event Listeners ==================
function initializeEventListeners() {
    // File upload
    document.getElementById('fileInput').addEventListener('change', handleFileUpload);
    
    // იპოთეკა
    document.getElementById('countIpoteka').addEventListener('click', toggleIpoteka);
    
    // გადახდის ტიპი
    document.getElementById('type_select').addEventListener('change', () => {
        fillData();
        getAndFillGraph();
    });
    
    // პერიოდი
    document.getElementById('period').addEventListener('change', () => {
        fillData();
        getAndFillGraph();
    });
    
    // განვადების ტიპი
    document.getElementById('ganvadebaType').addEventListener('change', () => {
        fillData();
        getAndFillGraph();
    });
    
    // ფასდაკლება
    document.getElementById('discountType').addEventListener('change', () => {
        document.getElementById('discountNum').value = 0;
        document.getElementById('discount').value = 0;
        calculateDiscount();
    });
    
    document.getElementById('discountNum').addEventListener('input', calculateDiscount);
    document.getElementById('discountBY').addEventListener('input', calculateDiscount);
    
    // იპოთეკის გამოთვლები
    document.getElementById('participation').addEventListener('input', calculateParticipationPercent);
    document.getElementById('participationPercent').addEventListener('input', calculateParticipationAmount);
    document.getElementById('annualInterest').addEventListener('input', calculateIpoteka);
    document.getElementById('annualTerm').addEventListener('input', calculateIpoteka);
    
    // ბოლო გადახდის თარიღი
    document.getElementById('lastPayDate').addEventListener('change', () => {
        fillEndDateByLastPayDate();
        validateDate();
    });
    
    // შენახვა
    document.getElementById('saveBTN').addEventListener('click', function() {
        this.style.display = 'none';
        saveGraph();
    });
    
    document.getElementById('saveCalculation').addEventListener('click', function() {
        this.style.display = 'none';
        saveGraphCalculation();
    });
}

// ================== ძირითადი ფუნქციები ==================

/**
 * ფაილის ატვირთვა
 */
async function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('planFile', file);
    
    try {
        const response = await fetch(
            `${location.origin}/rest/local/api/calculator/upload_dealPlan.php?dealid=${CONFIG.dealID}`,
            { method: 'POST', body: formData }
        );
        
        if (!response.ok) throw new Error('Upload failed');
        
        const data = await response.json();
        
        if (data.status === 200) {
            showMessage('', 'success');
            fillGraph(data);
            
            if (CONFIG.hasProduct) {
                show('saveBTN');
            }
            if (CONFIG.dealGet !== "fromInventory") {
                show('saveCalculation');
            }
            
            recalculateDebt();
        } else {
            showMessage(data.errorTXT, 'error');
            hide('saveBTN');
            hide('saveCalculation');
            clearTable('graphData');
            document.getElementById('fileInput').value = '';
        }
    } catch (error) {
        showMessage('ფაილის ატვირთვა ვერ მოხერხდა', 'error');
        document.getElementById('fileInput').value = '';
    }
}

/**
 * განვადების ტიპის შეცვლა
 */
function changeType() {
    const ganvadebaType = document.getElementById('ganvadebaType').value;
    
    if (ganvadebaType === "customType") {
        fillType("არასტანდარტული");
        enable('discountType');
        enable('discountNum');
    } else if (ganvadebaType === "allCash") {
        fillType("ერთიანი გადახდა");
    } else if (ganvadebaType === "mortgage") {
        fillType("განვადება");
    } else if (ganvadebaType === "bankLoan") {
        fillType("ბანკის");
    }
}

/**
 * ტიპის შევსება
 */
function fillType(opt) {

    console.log("opt")
   
    console.log(opt) 

    const typeSelect = document.getElementById('type_select');
    typeSelect.innerHTML = '';
    
    if (opt === "არასტანდარტული") {
        typeSelect.innerHTML = '<option value="customType" selected>არასტანდარტული</option>';
        return;
    }
    
    typeSelect.innerHTML = '<option value="">აირჩიეთ გადახდის ტიპი</option>';
    
    // for (let key in CONFIG.instalmentPlanArr) {
    //     let value = CONFIG.instalmentPlanArr[key];
    //     if (value.includes(opt)) {
    //         typeSelect.innerHTML += `<option value="${key}">${value}</option>`;
    //     }
    // }

    for (let key in CONFIG.instalmentPlanArr) {
        let value = CONFIG.instalmentPlanArr[key];

        if (value.includes(opt)) {

            // გამოვაკლოთ სიტყვა "განვადება"
            let cleanValue = value.replace("განვადება", "").trim();

            typeSelect.innerHTML += `<option value="${key}">${cleanValue}</option>`;
        }
    }
}

/**
 * მონაცემების შევსება
 */
function fillData() {
    const ganvadebaType = document.getElementById('ganvadebaType').value;
    const selectedType = document.getElementById('type_select').value;
    
    // Reset values
    setValue('bookPayment', 0);
    setValue('advancePayDate', '');
    setValue('advancePayment', 0);
    setValue('advancePaymentPercent', 0);
    setValue('lastPayment', '');
    setValue('lastPayDate', '');
    setValue('discountNum', '');
    setValue('discountType', 'AMOUNT');
    if(document.getElementById('projectAndDate')){
        if(document.getElementById('projectAndDate').value==''){
            setValue('projectAndDate', CONFIG.scheduleTypeArr[selectedType]?.projEndDate || '');
        }
    }
    setHTML('DEALID', `<a href="/crm/deal/details/${CONFIG.dealID}/" target="_blank" style="color: black; text-decoration: none;">${CONFIG.dealID}</a>`);
    setValue('binisNomeri', CONFIG.binisNomeri);
    setValue('chabarebatype', <?= json_encode($chabarebatype) ?>);
    setValue('total_kvm', <?= $totalKVM ?>);
    
    if (ganvadebaType === "customType") {
        show('confirmTXT');
        fillDataForCustomType();
        enable('discountNum');
        enable('discountType');
        enable('discountBY');
        show('fileInput', 'parent');
    } else {
        const period = parseInt(document.getElementById('period').value);
        // if (period > 5) show('confirmTXT');
        // else 
        hide('confirmTXT');
        
        fillDataBySchedule();
        disable('discountNum');
        disable('discountType');
        disable('discountBY');
        hide('fileInput', 'parent');
    }
}

/**
 * არასტანდარტული ტიპის მონაცემები
 */
function fillDataForCustomType() {
    const selectedType = document.getElementById('type_select').value;
    const data = CONFIG.scheduleTypeArr[selectedType];
    
    if (!data) return;
    
    setValue('price', formatNumber(data.price));
    setValue('startPrice', formatNumber(data.price));
    setValue('kvmPrice', formatNumber(data.kvmPrice));
    setValue('startSqmPrice', formatNumber(data.startSqmPrice));
    setValue('nbgKursi', CONFIG.nbgKursi);
    setValue('startPriceGel', formatNumber(data.price * CONFIG.nbgKursi));
    setValue('startSqmPriceGel', formatNumber(data.startSqmPrice * CONFIG.nbgKursi));
    setValue('priceGel', formatNumber(data.price * CONFIG.nbgKursi));
    setValue('kvmPriceGel', formatNumber(data.kvmPrice * CONFIG.nbgKursi));
    setValue('endDate', data.projEndDate);
    
    enable('endDate');
}

/**
 * სტანდარტული ტიპის მონაცემები
 */
function fillDataBySchedule() {
    const selectedType = document.getElementById('type_select').value;
    const data = CONFIG.scheduleTypeArr[selectedType];
    
    if (!data) return;
    
    setValue('startPrice', formatNumber(data.oldPrice));
    setValue('discountNum', formatNumber(data.discountAmpunt));
    setValue('price', formatNumber(data.price));
    setValue('kvmPrice', formatNumber(data.kvmPrice));
    setValue('startSqmPrice', formatNumber(data.startSqmPrice));
    setValue('startDate', dateAddMonth(today(), 1));
    setValue('nbgKursi', CONFIG.nbgKursi);
    setValue('startPriceGel', formatNumber(data.price * CONFIG.nbgKursi));
    setValue('startSqmPriceGel', formatNumber(data.startSqmPrice * CONFIG.nbgKursi));
    setValue('priceGel', formatNumber(data.price * CONFIG.nbgKursi));
    setValue('kvmPriceGel', formatNumber(data.kvmPrice * CONFIG.nbgKursi));
    setValue('bookPayment', 0);
    
    fillEndDate();
    fillAdvancePaymentData();
    fillLastPaymentData();
}

/**
 * პირველადი შენატანის მონაცემები
 */
function fillAdvancePaymentData() {
    const price = parseFormattedNumber(getValue('price'));
    const selectedType = document.getElementById('type_select').value;
    const data = CONFIG.scheduleTypeArr[selectedType];
    
    if (data?.advancedPercentageBySchedule) {
        const advancedBySchedule = (price / 100 * data.advancedPercentageBySchedule).toFixed(2);
        setValue('advancePayDate', today());
        setValue('advancePayment', formatNumber(advancedBySchedule));
        setValue('advancePaymentPercent', data.advancedPercentageBySchedule);
    } else {
        setValue('advancePayDate', '');
        setValue('advancePayment', '');
        setValue('advancePaymentPercent', '');
    }
}

/**
 * ბოლო გადახდის მონაცემები
 */
function fillLastPaymentData() {
    const selectedType = document.getElementById('type_select').value;
    const data = CONFIG.scheduleTypeArr[selectedType];
    const endDate = getValue('endDate');
    
    if (data?.lastPayment) {
        setValue('lastPayDate', endDate);
        setValue('endDate', dateAddMonth(endDate, -1));
        setValue('lastPayment', formatNumber(data.lastPayment));
        setValue('lastPaymentPercent', formatNumber(data.lastPaymentPercent));
    } else {
        setValue('lastPayment', '');
        setValue('lastPaymentPercent', '');
    }
}

/**
 * დასრულების თარიღის შევსება
 */
function fillEndDate() {
    const selectedType = document.getElementById('type_select').value;
    const data = CONFIG.scheduleTypeArr[selectedType];
    setValue('endDate', data?.projEndDate || '');
}

/**
 * დასრულების თარიღის შევსება ბოლო გადახდის მიხედვით
 */
function fillEndDateByLastPayDate() {
    const selectedType = document.getElementById('type_select').value;
    const projEndDate = CONFIG.scheduleTypeArr[selectedType]?.projEndDate;
    const lastPayDate = getValue('lastPayDate');
    const period = parseInt(getValue('period'));
    
    // if (lastPayDate) {
    //     setValue('endDate', dateAddMonth(lastPayDate, -period));
    // } else {
    //     setValue('endDate', projEndDate);
    // }
}

/**
 * იპოთეკის მონაცემები
 */
function fillIpoteka() {
    const amount = CONFIG.scheduleTypeArr["customType"].price;
    
    setValue('startLoan', amount);
    setValue('Loan', amount);
    setValue('kvmPriceIpoteka', CONFIG.scheduleTypeArr["customType"].kvmPrice);
    setValue('participation', 0);
    setValue('annualInterest', 0);
    setValue('annualTerm', 1);
    setValue('sruliTanxa', amount);
    setValue('monthlyPayment', amount);
    setValue('startLoan_GEL', (amount * CONFIG.nbgKursi).toFixed(2));
    setValue('monthlyPayment_GEL', (amount * CONFIG.nbgKursi).toFixed(2));
    setValue('NBG', CONFIG.nbgKursi.toFixed(4));
}

/**
 * იპოთეკის გამოთვლა
 */
function calculateIpoteka() {
    let amount = parseFloat(getValue('startLoan'));
    let participation = parseFloat(getValue('participation')) || 0;
    let annualInterest = parseFloat(getValue('annualInterest'));
    let annualTerm = parseInt(getValue('annualTerm'));
    
    let r = annualInterest / 1200;
    let loanAmount = amount - participation;
    
    setValue('Loan', loanAmount.toFixed(2));
    
    let monthlyPayment = amount;
    let fullAmount = amount;
    
    if (annualInterest > 0 && annualTerm > 0) {
        monthlyPayment = (loanAmount * r * Math.pow((1 + r), annualTerm)) / (Math.pow((1 + r), annualTerm) - 1);
        fullAmount = monthlyPayment * annualTerm + participation;
    } else if (annualTerm > 0) {
        monthlyPayment = loanAmount / annualTerm;
        fullAmount = amount;
    }
    
    setValue('sruliTanxa', fullAmount.toFixed(2));
    setValue('monthlyPayment', monthlyPayment.toFixed(2));
    setValue('startLoan_GEL', (fullAmount * CONFIG.nbgKursi).toFixed(2));
    setValue('monthlyPayment_GEL', (monthlyPayment * CONFIG.nbgKursi).toFixed(2));
}

/**
 * თანამონაწილეობის პროცენტის გამოთვლა
 */
function calculateParticipationPercent() {
    const amount = parseFloat(getValue('startLoan'));
    let participation = parseFloat(getValue('participation'));
    
    if (participation > amount) {
        participation = 0;
        setValue('participation', '');
        setValue('participationPercent', '');
    } else {
        const percent = (participation * 100 / amount).toFixed(2);
        setValue('participationPercent', percent);
    }
    
    calculateIpoteka();
}

/**
 * თანამონაწილეობის თანხის გამოთვლა
 */
function calculateParticipationAmount() {
    const amount = parseFloat(getValue('startLoan'));
    let participationPercent = parseFloat(getValue('participationPercent'));
    
    if (participationPercent > 100) {
        participationPercent = 0;
        setValue('participation', '');
        setValue('participationPercent', '');
    } else {
        const participation = (amount * participationPercent / 100).toFixed(2);
        setValue('participation', participation);
    }
    
    calculateIpoteka();
}

/**
 * ფასდაკლების გამოთვლა
 */
function calculateDiscount() {
    const selectedType = document.getElementById('type_select').value;
    const data = CONFIG.scheduleTypeArr[selectedType];
    
    let kvm = data?.TOTAL_AREA || 1;
    let discountNum = parseFormattedNumber(getValue('discountNum'));
    let discountBY = getValue('discountBY');
    let discountType = getValue('discountType');
    let startPrice = parseFormattedNumber(getValue('startPrice'));
    let startKvmPrice = (startPrice / kvm).toFixed(2);
    
    let discountValue = 0;
    
    if (discountBY === "fullPrice") {
        if (discountType === "AMOUNT") {
            discountValue = discountNum || 0;
        } else {
            discountValue = (startPrice / 100 * discountNum).toFixed(2);
        }
    } else {
        let kvmDiscountValue = 0;
        if (discountType === "AMOUNT") {
            kvmDiscountValue = discountNum || 0;
        } else {
            kvmDiscountValue = (startKvmPrice / 100 * discountNum).toFixed(2);
        }
        discountValue = (kvmDiscountValue * kvm).toFixed(2);
    }
    
    if (parseFloat(discountValue) > parseFloat(startPrice)) {
        discountValue = 0;
        setValue('discountNum', 0);
        setValue('discount', 0);
    } else {
        setValue('discount', discountValue);
    }
    
    let priceAfterDiscount = startPrice - discountValue;
    setValue('price', formatNumber(priceAfterDiscount));
    setValue('kvmPrice', formatNumber(priceAfterDiscount / kvm));
    setValue('priceGel', formatNumber(priceAfterDiscount * CONFIG.nbgKursi));
    setValue('kvmPriceGel', formatNumber((priceAfterDiscount / kvm) * CONFIG.nbgKursi));
    
    fillAdvancePaymentData();
}

/**
 * გრაფიკის გამოთვლა და შევსება
 */
async function getAndFillGraph() {
    const typeSelected = getValue('type_select');
    const ganvadebaType = getValue('ganvadebaType');
    console.log("ganvadebaType");
    console.log(ganvadebaType);

        console.log("typeSelected");
    console.log(typeSelected);
    
    if (!typeSelected) {
        if(ganvadebaType =="customType"){
            alert("გთხოვთ აირჩიოთ გადახდის ტიპი");
            console.log("test")
        }

        hide('saveBTN');
        hide('saveCalculation');
        clearTable('graphData');
        return;
    }
    
    const startDate = getValue('startDate');
    const endDate = getValue('endDate');
    
    if (!startDate || !endDate) {
        alert("გთხოვთ შეავსოთ დაწყება/დასრულების თარიღი");
        hide('saveBTN');
        hide('saveCalculation');
        clearTable('graphData');
        return;
    }
    
    if (!validateDate()) {
        hide('saveBTN');
        hide('saveCalculation');
        clearTable('graphData');
        return;
    }
    
    const requestData = {
        dealId: CONFIG.dealID,
        binisNomeri: CONFIG.binisNomeri,
        chabarebatype: getValue('chabarebatype'),
        type_selected: typeSelected,
        price: parseFormattedNumber(getValue('price')),
        startDate: startDate,
        endDate: endDate,
        advancePayment: parseFormattedNumber(getValue('advancePayment')),
        advancePayDate: getValue('advancePayDate'),
        lastPayment: parseFormattedNumber(getValue('lastPayment')),
        lastPayDate: getValue('lastPayDate'),
        bookPayDate: getValue('bookPayDate'),
        bookPayment: parseFormattedNumber(getValue('bookPayment')),
        first_stage_months: getValue('first_stage_months'),
        firstStageAmount: getValue('firstStageAmount'),
        projEndDate: CONFIG.scheduleTypeArr[typeSelected]?.projEndDate,
        period: getValue('period'),
    };
    console.log('Sending data:', JSON.stringify(requestData));


    try {
        const response = await fetch(
            `${location.origin}/rest/local/api/calculator/stageCalculateGraph.php`,
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestData)
            }
        );
        
        const data = await response.json();
        
        if (data.status === 200) {
            showMessage('', 'success');
            fillGraph(data);
            
            if (CONFIG.hasProduct) {
                show('saveBTN');
            }
            if (CONFIG.dealGet !== "fromInventory") {
                show('saveCalculation');
            }
            
            document.getElementById('fileInput').value = '';
            recalculateDebt();
        } else {
            showMessage(data.errorTXT, 'error');
            hide('saveBTN');
            hide('saveCalculation');
            clearTable('graphData');
            document.getElementById('fileInput').value = '';
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('გრაფიკის გამოთვლა ვერ მოხერხდა', 'error');
    }
}

/**
 * გრაფიკის შევსება
 */
function fillGraph(data) {
    const selectedType = document.getElementById('type_select').value;
    const isEditable = selectedType === "customType";
    
    let html = `
        <thead>
            <tr>
                <th>#</th>
                <th>გადახდის თარიღი</th>
                <th>თანხა</th>
                <th style="display: none;">დარჩენილი ძირი</th>
            </tr>
        </thead>
        <tbody>
    `;
    
    data.result.forEach(row => {
        if (row.amount <= 0) return;
        
        const [day, month, year] = row.date.split('/');
        const dateObj = new Date(year, month - 1, day);
        const isWeekend = dateObj.getDay() === 0 || dateObj.getDay() === 6 || CONFIG.holiday.includes(row.date);
        
        html += '<tr>';
        html += `<td>${row.payment}</td>`;
        html += `<td><input type="text" class="${isWeekend ? 'weekend-red' : ''}" 
                         value="${row.date}" data-date="${row.date}" 
                         onchange="checkDate(this)" autocomplete="off"
                         onclick="BX.calendar({node: this, field: this, bTime: false, bSetFocus: false})"></td>`;
        
        if (isEditable) {
            html += `<td class="editable-column" style="position: relative;">
                        <input value="${formatNumber(row.amount)}" data-amount="${row.amount}"
                               oninput="validateNumericInputGraph(event,this)" 
                               onblur="handleInputFinish(this,'graphData')">
                        <span style="position: absolute; left: 60%; top: 50%; transform: translateY(-50%);">$</span>
                     </td>`;
        } else {
            html += `<td style="position: relative;">
                        <input value="${formatNumber(row.amount)}" data-amount="${row.amount}" disabled>
                        <span style="position: absolute; left: 60%; top: 50%; transform: translateY(-50%);">$</span>
                     </td>`;
        }
        
        html += `<td style="display: none;">${formatNumber(row.leftToPay)}</td>`;
        html += '</tr>';
    });
    
    html += '</tbody>';
    
    document.getElementById('graphData').innerHTML = html;
    
    if (isEditable) {
        const editableInputs = document.querySelectorAll("#graphData .editable-column input");
        editableInputs.forEach(input => {
            input.addEventListener("mousedown", handleMouseDown);
            input.addEventListener("mousemove", handleMouseMove);
            input.addEventListener("mouseup", handleMouseUp);
        });
    }
}

/**
 * დოკუმენტიდან გრაფიკის შევსება
 */
function fillGraphByDoc() {
    const selectedType = document.getElementById('type_select').value;
    const isEditable = selectedType === "customType";
    
    let html = `
        <thead>
            <tr>
                <th>#</th>
                <th>გადახდის თარიღი</th>
                <th>თანხა</th>
                <th style="display: none;">დარჩენილი ძირი</th>
            </tr>
        </thead>
        <tbody>
    `;
    
    CONFIG.graphByDoc.forEach(row => {
        if (parseFormattedNumber(row.amount) <= 0) return;
        
        const [day, month, year] = row.date.split('/');
        const dateObj = new Date(year, month - 1, day);
        const isWeekend = dateObj.getDay() === 0 || dateObj.getDay() === 6 || CONFIG.holiday.includes(row.date);
        
        html += '<tr>';
        html += `<td>${row.payment}</td>`;
        html += `<td><input type="text" class="${isWeekend ? 'weekend-red' : ''}" 
                         value="${row.date}" data-date="${row.date}" 
                         onchange="checkDate(this)" autocomplete="off"
                         onclick="BX.calendar({node: this, field: this, bTime: false, bSetFocus: false})"></td>`;
        
        if (isEditable) {
            html += `<td class="editable-column">
                        <input value="${formatNumber(parseFormattedNumber(row.amount))}" 
                               data-amount="${parseFormattedNumber(row.amount)}"
                               oninput="validateNumericInputGraph(event,this)" 
                               onblur="handleInputFinish(this,'graphData')">
                     </td>`;
        } else {
            html += `<td>
                        <input value="${formatNumber(parseFormattedNumber(row.amount))}" 
                               data-amount="${parseFormattedNumber(row.amount)}" disabled>
                     </td>`;
        }
        
        html += `<td style="display: none;">${formatNumber(row.leftToPay)}</td>`;
        html += '</tr>';
    });
    
    html += '</tbody>';
    
    document.getElementById('graphData').innerHTML = html;
    
    if (isEditable) {
        const editableInputs = document.querySelectorAll("#graphData .editable-column input");
        editableInputs.forEach(input => {
            input.addEventListener("mousedown", handleMouseDown);
            input.addEventListener("mousemove", handleMouseMove);
            input.addEventListener("mouseup", handleMouseUp);
        });
    }
    
    recalculateDebt();
}

/**
 * კალკულატორის ჰედერის შევსება
 */
function fillCalculatorHeader() {
    const header = CONFIG.graphHeaderByDoc;
    
    setValue('advancePayDate', header.advancePayDate);
    setValue('advancePayment', header.advancePayment);
    setValue('advancePaymentPercent', header.advancePaymentPercent);
    setValue('chabarebatype', header.chabarebatype);
    setValue('commentInput', header.commentInput);
    setValue('discountNum', header.discountNum);
    setValue('discountType', header.discountType);
    setValue('endDate', header.endDate);
    setValue('kvmPrice', header.kvmPrice);
    setValue('kvmPriceGel', header.kvmPriceGel);
    setValue('lastPayDate', header.lastPayDate);
    setValue('lastPayment', header.lastPayment);
    setValue('lastPaymentPercent', header.lastPaymentPercent);
    setValue('period', header.period);
    setValue('price', header.price);
    setValue('priceGel', header.priceGel);
    setValue('startDate', header.startDate);
    setValue('startPrice', header.startPrice);
    setValue('startPriceGel', header.startPriceGel);
    setValue('startSqmPriceGel', header.startSqmPriceGel);
    setValue('type_select', header.type_select);
}

/**
 * დარჩენილი თანხის გადათვლა
 */
function recalculateDebt() {
    const table = document.getElementById('graphData');
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    const startingDebt = parseFormattedNumber(getValue('price'));
    let leftDebt = startingDebt;
    
    Array.from(tbody.rows).forEach((row, index) => {
        const payment = parseFormattedNumber(row.cells[2].querySelector('input').value);
        leftDebt -= payment;
        row.cells[3].textContent = formatNumber(leftDebt);
        
        // ბოლო რიგის შემოწმება
        if (index === tbody.rows.length - 1) {
            if (Math.abs(leftDebt) > 0.01) {
                row.style.backgroundColor = "red";
            } else {
                row.style.backgroundColor = "";
            }
        }
    });
    
    // გამოთვლა სწორია თუ არა
    const isCorrect = Math.abs(leftDebt) <= 0.01;
    
    if (isCorrect) {
        if (CONFIG.hasProduct) show('saveBTN');
        if (CONFIG.dealGet !== "fromInventory") show('saveCalculation');
    } else {
        hide('saveBTN');
        hide('saveCalculation');
    }
}

/**
 * თარიღის ვალიდაცია
 */
function validateDate() {
    const typeSelected = getValue('type_select');
    const projEndDate = CONFIG.scheduleTypeArr[typeSelected]?.projEndDate;
    
    if (!projEndDate || typeSelected === "customType") return true;
    
    const endDateValue = getValue('endDate');
    const lastPayDateValue = getValue('lastPayDate');
    
    const [projDay, projMonth, projYear] = projEndDate.split('/');
    const cutoffDate = new Date(projYear, projMonth - 1, projDay);
    
    // დასრულების თარიღის შემოწმება
    if (endDateValue) {
        const [day, month, year] = endDateValue.split('/');
        const inputDate = new Date(year, month - 1, day);
        
        if (inputDate > cutoffDate) {
            showMessage(`დასრულების თარიღი არ უნდა აღემატებოდეს ${projEndDate}`, 'error');
            setValue('endDate', projEndDate);
            return false;
        }
    }
    
    // ბოლო გადახდის თარიღის შემოწმება
    if (lastPayDateValue) {
        const [day, month, year] = lastPayDateValue.split('/');
        const inputDate = new Date(year, month - 1, day);
        
        if (inputDate > cutoffDate) {
            showMessage(`ბოლო შენატანის თარიღი არ უნდა აღემატებოდეს ${projEndDate}`, 'error');
            setValue('lastPayDate', projEndDate);
            return false;
        }
    }
    
    return true;
}

/**
 * გრაფიკის შენახვა
 */
async function saveGraph() {
    const selectedType = getValue('type_select');
    const selectedGraph = selectedType === "customType" ? 
        "არასტანდარტული" : CONFIG.instalmentPlanArr[selectedType];
    
    const price = parseFormattedNumber(getValue('price'));
    const table = document.getElementById('graphData');
    const tbody = table.querySelector('tbody');
    
    const paymentPlan = Array.from(tbody.rows).map(row => ({
        payment: row.cells[0].textContent,
        date: row.cells[1].querySelector('input').value,
        amount: parseFormattedNumber(row.cells[2].querySelector('input').value)
    }));
    
    const bookPayment = parseFormattedNumber(getValue('bookPayment')) || 0;
    const advancePayment = parseFormattedNumber(getValue('advancePayment')) || 0;
    const advancePaymentPercent = parseFormattedNumber(getValue('advancePaymentPercent')) || 0;
    const lastPayment = parseFormattedNumber(getValue('lastPayment')) || 0;
    const lastPaymentPercent = parseFormattedNumber(getValue('lastPaymentPercent')) || 0;
    
    const distributedPayment = (price - (advancePayment + lastPayment)).toFixed(2);
    const distributedPaymentPercent = ((distributedPayment / price) * 100).toFixed(2);
    
    const savingJson = {
        dealId: CONFIG.dealID,
        binisNomeri: CONFIG.binisNomeri,
        data: paymentPlan,
        selected_type: selectedType,
        graph: selectedGraph,
        PRICE: price,
        image: image,
        kvmPrice: parseFormattedNumber(getValue('kvmPrice')),
        commentInput: getValue('commentInput'),
        chabarebatype: getValue('chabarebatype'),
        period: getValue('period'),
        author: CONFIG.userID,
        PROD_ID: CONFIG.prodID,
        oldPrice: CONFIG.oldPrice,
        advancePayment: `${advancePayment} $ / ${advancePaymentPercent} %`,
        bookPayment: `${bookPayment} $`,
        lastPayment: `${lastPayment} $ / ${lastPaymentPercent} %`,
        DistributedPayment: `${distributedPayment} $ / ${distributedPaymentPercent} %`
    };
    
    try {
        const response = await fetch(
            `${location.origin}/rest/local/api/calculator/saveGraphEndRunWorkflow.php`,
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(savingJson)
            }
        );
        
        const data = await response.json();
        alert(data.TEXT);
        
        // if (window.opener) {
        //     window.opener.location.reload();
        // }
        window.close();
    } catch (error) {
        console.error('Error:', error);
        alert('შენახვა ვერ მოხერხდა');
    }
}

/**
 * კალკულაციის შენახვა
 */

    async function  saveGraphCalculation() {
        let type = document.getElementById("type_select");
        let selected_type = type.value;
        let selectedGraph = "";
        if(selected_type=="customType"){
            selectedGraph = "არასტანდარტული";
        }else{
            selectedGraph = CONFIG.instalmentPlanArr[selected_type];
        }
        let price= parseFormattedNumber(document.getElementById("price").value);
        let graph = document.getElementById("graphData");
        let kvmPrice = parseFormattedNumber(document.getElementById("kvmPrice").value);
        let commentInput = document.getElementById("commentInput").value;
        let period = document.getElementById("period").value;
        let startPriceUSD = parseFormattedNumber(document.getElementById("startPrice").value);
        let startKVMPriceUSD = parseFormattedNumber(document.getElementById("startSqmPrice").value);
        let priceGel= parseFormattedNumber(document.getElementById("priceGel").value);
        let startpriceGel = parseFormattedNumber(document.getElementById("startPriceGel").value);
        let kvmPriceGel = parseFormattedNumber(document.getElementById("kvmPriceGel").value);
        let startSqmPriceGel = parseFormattedNumber(document.getElementById("startSqmPriceGel").value);


        let loan_amount = " ";
        let tanamonawileoba = " ";
        let wliuriProcent = " ";
        let sesxisVada = " ";
        let dasafariSul = " ";
        let gadasaxadiTveshi = " ";

        let ipotekaDiv = document.getElementById("ipoteka");
        if(ipotekaDiv.classList.contains("hide")){
        }else{
            loan_amount = document.getElementById("startLoan").value;
            tanamonawileoba = document.getElementById("participation").value;
            wliuriProcent = document.getElementById("annualInterest").value;
            sesxisVada = document.getElementById("annualTerm").value;
            dasafariSul = document.getElementById("sruliTanxa").value;
            gadasaxadiTveshi = document.getElementById("monthlyPayment").value;
        }
        // loan_amount = document.getElementById("startLoan").value;
        // tanamonawileoba = document.getElementById("participation").value;
        // wliuriProcent = document.getElementById("annualInterest").value;
        // sesxisVada = document.getElementById("annualTerm").value;
        // dasafariSul = document.getElementById("sruliTanxa").value;
        // gadasaxadiTveshi = document.getElementById("monthlyPayment").value;

        let arrPaymentPlan = [];
        for (let i = 1; i < graph.rows.length; i++) {
            let PaymentPlan = {
                "payment" : graph.rows[i].cells[0].innerText,
                "date" : graph.rows[i].cells[1].children[0].value,
                "amount" : graph.rows[i].cells[2].children[0].value,
                "leftToPay" : graph.rows[i].cells[3].innerText,

            }
            arrPaymentPlan.push(PaymentPlan);
        }


        let calculatorHead = {
            "chabarebatype" : document.getElementById("chabarebatype").value,
            "binisNomeri" : document.getElementById("binisNomeri").value,
            "type_select" : document.getElementById("type_select").value,
            "period" : document.getElementById("period").value,
            "startPrice" : document.getElementById("startPrice").value,
            "discountType" : document.getElementById("discountType").value,
            "discountNum" : document.getElementById("discountNum").value,
            "startPriceGel" : document.getElementById("startPriceGel").value,
            "startSqmPriceGel" : document.getElementById("startSqmPriceGel").value,
            "price" : document.getElementById("price").value,
            "kvmPrice" : document.getElementById("kvmPrice").value,
            "priceGel" : document.getElementById("priceGel").value,
            "kvmPriceGel" : document.getElementById("kvmPriceGel").value,
            "startDate" : document.getElementById("startDate").value,
            "endDate" : document.getElementById("endDate").value,
            "advancePayDate" : document.getElementById("advancePayDate").value,
            "advancePayment" : document.getElementById("advancePayment").value,
            "advancePaymentPercent" : document.getElementById("advancePaymentPercent").value,
            "lastPayDate" : document.getElementById("lastPayDate").value,
            "lastPayment" : document.getElementById("lastPayment").value,
            "lastPaymentPercent" : document.getElementById("lastPaymentPercent").value,
            "commentInput" : document.getElementById("commentInput").value,
        }



        let savingJson = {
            "dealId": <?php echo $dealID;?>,
            "binisNomeri": <?php echo json_encode($binisNomeri);?>,
            "data": arrPaymentPlan,
            "selected_type": selected_type,
            "graph": selectedGraph,
            "calculatorHead": calculatorHead,
            "PRICE": price,
            "image": image,
            "kvmPrice": kvmPrice,
            "commentInput": commentInput,
            "period": period,
            "author": <?echo $USER->GetID();?>,
            "PROD_ID" : <?php echo $prod_ID;?>,
            "oldPrice" : <?php echo $oldPrice;?>,
            "loan_amount": loan_amount,
            "tanamonawileoba": tanamonawileoba,
            "wliuriProcent": wliuriProcent,
            "sesxisVada": sesxisVada,
            "dasafariSul": dasafariSul,
            "gadasaxadiTveshi": gadasaxadiTveshi,
            "priceGel": priceGel,
            "startpriceGel": startpriceGel,
            "kvmPriceGel": kvmPriceGel,
            "startSqmPriceGel": startSqmPriceGel,
            "nbgKursi":nbgKursi,
            "startPriceUSD":startPriceUSD,
            "startKVMPriceUSD":startKVMPriceUSD,
        };

    
             try {
                const response = await fetch(
                    `${location.origin}/rest/local/api/calculator/saveCalculation.php`,
                    {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(savingJson)
                    }
                );
                
                const data = await response.json();
                alert(data.TEXT);
                
                // if (window.opener) {
                //     window.opener.location.reload();
                // }
                // window.close();
            } catch (error) {
                console.error('Error:', error);
                alert('შენახვა ვერ მოხერხდა');
            }


    }

/**
 * Excel-ში ექსპორტი
 */
function exportTableToExcel() {
    const table = document.getElementById('graphData');
    const wb = XLSX.utils.book_new();
    
    wb.Props = {
        Title: "განვადების გრაფიკი",
        Subject: "plan",
        Author: "homer.ge",
        CreatedDate: new Date()
    };
    
    const ws_data = [];
    
    Array.from(table.rows).forEach((row, i) => {
        const rowData = [];
        Array.from(row.cells).forEach((cell, j) => {
            if (j === 3) return; // Skip hidden column
            
            if (j === 0 || i === 0) {
                rowData.push(cell.textContent);
            } else {
                let value = cell.querySelector('input')?.value || cell.textContent;
                if (j === 2) value += " $ "; // Add dollar sign to amount column
                rowData.push(value);
            }
        });
        ws_data.push(rowData);
    });
    
    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    XLSX.utils.book_append_sheet(wb, ws, "განვადების გრაფიკი");
    XLSX.writeFile(wb, "განვადების_გრაფიკი.xlsx");
}

// ================== დამხმარე ფუნქციები ==================

/**
 * რიცხვის ფორმატირება კომებით
 */
function formatNumber(num) {
    const number = parseFloat(num);
    if (isNaN(number)) return '0.00';
    return number.toLocaleString('en-US', { 
        minimumFractionDigits: 2, 
        maximumFractionDigits: 2 
    });
}

/**
 * ფორმატირებული რიცხვის პარსვა
 */
function parseFormattedNumber(str) {
    if (typeof str !== 'string') return parseFloat(str) || 0;
    return parseFloat(str.replace(/,/g, '')) || 0;
}

/**
 * დღევანდელი თარიღი
 */
function today() {
    const today = new Date();
    const dd = String(today.getDate()).padStart(2, '0');
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const yyyy = today.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

/**
 * თარიღს თვის დამატება
 */
function dateAddMonth(date, months) {
    const [day, month, year] = date.split('/');
    const dateObj = new Date(year, month - 1, day);
    dateObj.setMonth(dateObj.getMonth() + parseInt(months));
    
    const newMonth = String(dateObj.getMonth() + 1).padStart(2, '0');
    const newDay = String(dateObj.getDate()).padStart(2, '0');
    const newYear = dateObj.getFullYear();
    
    return `${newDay}/${newMonth}/${newYear}`;
}

/**
 * შეტყობინების ჩვენება
 */
function showMessage(text, type = 'error') {
    const container = document.getElementById('errors');
    if (!text) {
        container.innerHTML = '';
        return;
    }
    
    const color = type === 'error' ? 'red' : 'green';
    container.innerHTML = `<p style="color: ${color}; font-weight: bold;">* ${text}</p>`;
}

/**
 * ელემენტის მნიშვნელობის დაყენება
 */
function setValue(id, value) {
    const element = document.getElementById(id);
    if (!element) return;
    
    if (element.tagName === 'SELECT' || element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
        element.value = value;
    }
}

/**
 * ელემენტის მნიშვნელობის მიღება
 */
function getValue(id) {
    const element = document.getElementById(id);
    return element ? element.value : '';
}

/**
 * HTML-ის დაყენება
 */
function setHTML(id, html) {
    const element = document.getElementById(id);
    if (element) element.innerHTML = html;
}

/**
 * ელემენტის ჩვენება
 */
function show(id, target = 'self') {
    const element = document.getElementById(id);
    if (!element) return;
    
    if (target === 'parent') {
        element.closest('.form-field')?.classList.remove('hidden');
    } else {
        element.classList.remove('hidden');
        element.style.display = 'flex';
    }
}

/**
 * ელემენტის დამალვა
 */
function hide(id, target = 'self') {
    const element = document.getElementById(id);
    if (!element) return;
    
    if (target === 'parent') {
        element.closest('.form-field')?.classList.add('hidden');
    } else {
        element.classList.add('hidden');
        element.style.display = 'none';
    }
}

/**
 * ელემენტის გააქტიურება
 */
function enable(id) {
    const element = document.getElementById(id);
    if (element) element.disabled = false;
}

/**
 * ელემენტის გამორთვა
 */
function disable(id) {
    const element = document.getElementById(id);
    if (element) element.disabled = true;
}

/**
 * ცხრილის გასუფთავება
 */
function clearTable(id) {
    const element = document.getElementById(id);
    if (element) element.innerHTML = '';
}

/**
 * იპოთეკის ტოგლი
 */
function toggleIpoteka() {
    const ipoteka = document.getElementById('ipoteka');
    ipoteka.classList.toggle('hidden');
}

/**
 * რიცხვითი input-ის ვალიდაცია
 */
function validateNumericInput(event, element) {
    const newValue = element.value.replace(/[^\d.-]/g, '');
    element.value = newValue;
    clearTable('graphData');
}

/**
 * რიცხვითი input-ის ვალიდაცია გრაფიკში
 */
function validateNumericInputGraph(event, element) {
    const newValue = element.value.replace(/[^\d.-]/g, '');
    element.value = newValue;
}

/**
 * Input-ის დასრულების დამუშავება
 */
function handleInputFinish(inputElement, type) {
    const numericValue = parseFormattedNumber(inputElement.value);
    inputElement.value = isNaN(numericValue) ? '0.00' : formatNumber(numericValue);
    
    switch(type) {
        case 'graphData':
            recalculateDebt();
            break;
        case 'discountNum':
            calculateDiscount();
            break;
        case 'advancePayment':
            advancePaymentChange();
            break;
        case 'advancePaymentPercent':
            advancePaymentPercentChange();
            break;
        case 'lastPayment':
            LastPayment_change();
            break;
        case 'lastPaymentPercent':
            LastPaymentPercent_change();
            break;
        case 'bookPayment':
            bookPayment_change();
            break;
    }
}

/**
 * პირველადი შენატანის ცვლილება
 */
function advancePaymentChange() {
    const bookPayment = parseFormattedNumber(getValue('bookPayment'));
    const advancePayment = parseFormattedNumber(getValue('advancePayment'));
    const price = parseFormattedNumber(getValue('price'));
    const selectedType = getValue('type_select');
    const data = CONFIG.scheduleTypeArr[selectedType];
    
    const advancedPercentBySchedule = parseFloat(data?.advancedPercentageBySchedule || 0);
    const advancedBySchedule = (price / 100 * advancedPercentBySchedule).toFixed(2);
    
    const total = advancePayment + bookPayment;
    const percent = total > 0 ? (100 * total / price).toFixed(2) : 0;
    
    setValue('advancePaymentPercent', percent);
    
    if (selectedType !== "customType" && percent < advancedPercentBySchedule) {
        alert(`პირველადი შენატანი არ უნდა იყოს ${advancedBySchedule}$_ზე ნაკლები`);
        setValue('bookPayment', 0);
        fillAdvancePaymentData();
    }
}

/**
 * პირველადი შენატანის პროცენტის ცვლილება
 */
function advancePaymentPercentChange() {
    const advancePaymentPercent = parseFormattedNumber(getValue('advancePaymentPercent'));
    const price = parseFormattedNumber(getValue('price'));
    const selectedType = getValue('type_select');
    const data = CONFIG.scheduleTypeArr[selectedType];
    
    const advancedPercentBySchedule = parseFloat(data?.advancedPercentageBySchedule || 0);
    const advancedBySchedule = (price / 100 * advancedPercentBySchedule).toFixed(2);
    
    if (advancePaymentPercent) {
        const amount = (price * advancePaymentPercent / 100).toFixed(2);
        setValue('advancePayment', formatNumber(amount));
    } else {
        setValue('advancePayment', 0);
    }
    
    setValue('bookPayment', 0);
    
    if (selectedType !== "customType" && advancePaymentPercent < advancedPercentBySchedule) {
        alert(`პირველადი შენატანი არ უნდა იყოს ${advancedBySchedule}$_ზე ნაკლები`);
        fillAdvancePaymentData();
    } else if (advancePaymentPercent > 100) {
        alert("პირველადი შენატანი არასწორადაა შევსებული");
        fillAdvancePaymentData();
    }
}

/**
 * ბოლო გადახდის ცვლილება
 */
function LastPayment_change() {
    const lastPayment = parseFormattedNumber(getValue('lastPayment'));
    const price = parseFormattedNumber(getValue('price'));
    const selectedType = getValue('type_select');
    const data = CONFIG.scheduleTypeArr[selectedType];
    
    const percent = lastPayment > 0 ? (100 * lastPayment / price).toFixed(2) : 0;
    setValue('lastPaymentPercent', percent);
    
    const maxPercent = parseFloat(data?.lastPaymentPercent || 100);
    const maxAmount = data?.lastPayment || price;
    
    if (selectedType !== "customType" && percent > maxPercent) {
        alert(`ბოლო შენატანი არ უნდა იყოს ${maxAmount}$_ზე მეტი`);
        fillLastPaymentData();
    } else if (percent > 100) {
        alert("ბოლო შენატანი არ არის სწორად შევსებული");
        fillLastPaymentData();
    }
}

/**
 * ბოლო გადახდის პროცენტის ცვლილება
 */
function LastPaymentPercent_change() {
    const lastPaymentPercent = parseFormattedNumber(getValue('lastPaymentPercent'));
    const price = parseFormattedNumber(getValue('price'));
    const selectedType = getValue('type_select');
    const data = CONFIG.scheduleTypeArr[selectedType];
    
    if (lastPaymentPercent) {
        const amount = (price / 100 * lastPaymentPercent).toFixed(2);
        setValue('lastPayment', formatNumber(amount));
    } else {
        setValue('lastPayment', 0);
    }
    
    const maxPercent = parseFloat(data?.lastPaymentPercent || 100);
    const maxAmount = data?.lastPayment || price;
    
    if (selectedType !== "customType" && lastPaymentPercent > maxPercent) {
        alert(`ბოლო შენატანი არ უნდა იყოს ${maxAmount}$_ზე მეტი`);
        fillLastPaymentData();
    } else if (lastPaymentPercent > 100) {
        alert("ბოლო შენატანი არ არის სწორად შევსებული");
        fillLastPaymentData();
    }
}

/**
 * ჯავშნის გადახდის ცვლილება
 */
function bookPayment_change() {
    const bookPayment = parseFormattedNumber(getValue('bookPayment'));
    const price = parseFormattedNumber(getValue('price'));
    const selectedType = getValue('type_select');
    const data = CONFIG.scheduleTypeArr[selectedType];
    
    const advancedPercentBySchedule = parseFloat(data?.advancedPercentageBySchedule || 0);
    const advancedBySchedule = (price / 100 * advancedPercentBySchedule).toFixed(2);
    
    let advancePayment = 0;
    if (advancedBySchedule - bookPayment > 0) {
        advancePayment = parseFloat(advancedBySchedule - bookPayment);
        setValue('advancePayment', formatNumber(advancePayment));
    } else {
        setValue('advancePayment', formatNumber(0));
    }
    
    const total = advancePayment + bookPayment;
    const percent = total > 0 ? (100 * total / price).toFixed(2) : 0;
    
    setValue('advancePaymentPercent', percent);
    
    if (selectedType !== "customType" && percent < advancedPercentBySchedule) {
        alert(`პირველადი შენატანი არ უნდა იყოს ${advancedBySchedule}$_ზე ნაკლები`);
        fillAdvancePaymentData();
    }
}

/**
 * თარიღის შემოწმება
 */
function checkDate(element) {
    const date = element.value;
    const [day, month, year] = date.split('/');
    const dateObj = new Date(year, month - 1, day);
    
    const isWeekend = dateObj.getDay() === 0 || dateObj.getDay() === 6 || CONFIG.holiday.includes(date);
    
    if (isWeekend) {
        element.classList.add('weekend-red');
    } else {
        element.classList.remove('weekend-red');
    }
    
    recalculateDebt();
}

/**
 * გადახდის ტიპის შემოწმება
 */
function checkGadaxdaType() {
    const selectedType = getValue('type_select');
    // Implementation based on payment type
}

// ================== Mouse Events for Drag Fill ==================

function handleMouseDown(event) {
    isDragging = true;
    fillValue = event.target.value;
    document.body.style.cursor = "crosshair";
}

function handleMouseMove(event) {
    if (!isDragging) return;
    
    const cell = event.target.closest("td");
    if (cell) {
        cell.classList.add("selected");
    }
}

function handleMouseUp(event) {
    if (!isDragging) return;
    
    const selectedCells = document.querySelectorAll("td.selected");
    selectedCells.forEach(cell => {
        const input = cell.querySelector("input");
        if (input) {
            input.value = fillValue;
        }
        cell.classList.remove("selected");
    });
    
    isDragging = false;
    fillValue = "";
    document.body.style.cursor = "default";
    recalculateDebt();
}

// ================== Offer Generation ==================

async function openOffer(offerType) {
    const dealID = CONFIG.dealID;
    if (!dealID || dealID === 1) {
        dealID = 'noDeal';
    }

    let offerLink = "offerCalculator";
    
    if (offerType == "grafik") {
        offerLink = 'grafikCalculator';
    } else if (offerType == "grafikEng") {
        offerLink = 'grafikCalculatorEng';
    } else if (offerType == "eng") {
        offerLink = 'offerCalculator-eng';
    } else if (offerType == "geoOdd") {
        offerLink = 'offerCalculatorB';
    } else if (offerType == "engOdd") {
        offerLink = 'offerCalculatorEngB';
    }
    
    const graph = document.getElementById("graphData");
    const discountNum = parseFormattedNumber(document.getElementById("discountNum").value);
    const startKvmPrice = parseFormattedNumber(document.getElementById("startSqmPrice").value);
    const kvmPrice = parseFormattedNumber(document.getElementById("kvmPrice").value);
    const startPrice = parseFormattedNumber(document.getElementById("startPrice").value);
    const priceGel = parseFormattedNumber(document.getElementById("priceGel").value);
    
    const discountType = document.getElementById("discountType");
    
    let discountValue = 0;
    if (discountType.value == "AMOUNT") {
        discountValue = discountNum || 0;
    } else {
        discountValue = (startPrice / 100 * discountNum).toFixed(2);
    }
    
    const prodID = CONFIG.prodID;
    let arrPaymentPlan = [];
    
    for (let i = 1; i < graph.rows.length; i++) {
        let PaymentPlan = {
            "payment": graph.rows[i].cells[0].innerText,
            "date": graph.rows[i].cells[1].children[0].value,
            "amount": graph.rows[i].cells[2].children[0].value,
            "leftToPay": graph.rows[i].cells[3].innerText,
        }
        arrPaymentPlan.push(PaymentPlan);
    }
    
    let savingJson = {
        "dealId": dealID,
        "data": arrPaymentPlan
    };
    
    // post_fetch(`${location.origin}/rest/local/api/calculator/addOfferData.php`, savingJson)
    //     .then(data => data.json())
    //     .then(data => {
    //         const dataID = data.dataID;
    //         window.open(`/crm/deal/${offerLink}.php?dealID=${dealID}&dataID=${dataID}&discountNum=${discountValue}&prodID=${prodID}&startKvmPrice=${startKvmPrice}&kvmPrice=${kvmPrice}&startPrice=${startPrice}&priceGel=${priceGel}`);
    //     })
    //     .catch(error => {
    //         console.log(error);
    //     });



            try {
                const response = await fetch(
                    `${location.origin}/rest/local/api/calculator/addOfferData.php`,
                    {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(savingJson)
                    }
                );
                
                const data = await response.json();
                const dataID = data.dataID;
                window.open(`/crm/deal/${offerLink}.php?dealID=${dealID}&dataID=${dataID}&discountNum=${discountValue}&prodID=${prodID}&startKvmPrice=${startKvmPrice}&kvmPrice=${kvmPrice}&startPrice=${startPrice}&priceGel=${priceGel}`);
            } catch (error) {
                console.error('Error:', error);
                alert('ვერ მოხერხდა');
            }
}

function openRender(type) {
    const prodID = CONFIG.prodID;
    window.open(`/crm/deal/${type}.php?prodid=${prodID}`);
}



        // setTimeout(() => {
        //     // დავამატოთ GTranslate-ის პარამეტრები
        //     const settingsScript = document.createElement('script');
        //     settingsScript.textContent = `
        //         window.gtranslateSettings = {
        //             "default_language": "ka",
        //             "languages": ["ka", "en", "ru"],
        //             "wrapper_selector": ".gtranslate_wrapper",
        //             "flag_size": 24
        //         };
        //     `;
        //     document.body.appendChild(settingsScript);

        //     // დავამატოთ თვითონ თარგმანის სკრიპტი
        //     const gtranslateScript = document.createElement('script');
        //     gtranslateScript.src = "https://cdn.gtranslate.net/widgets/latest/flags.js";
        //     gtranslateScript.defer = true;
        //     document.body.appendChild(gtranslateScript);

        //     // ვიპოვოთ რეზერვაციის ფორმის ელემენტი
        //     const reservationForm = document.querySelector('.calc');
        //     if (reservationForm) {
        //         // შევქმნათ თარგმანის HTML
        //         const translateHtml = document.createElement('div');
        //         translateHtml.className = 'gtranslate_wrapper';

        //         // ჩავსვათ რეზერვაციის ფორმის ზემოთ
        //         reservationForm.parentNode.insertBefore(translateHtml, reservationForm);
        //     }
        // }, 3000);

</script>

</body>
</html>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>