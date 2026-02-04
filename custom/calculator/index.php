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
            margin-right: 8px;

        }

        .export-btn:hover {
            background-color: #7fb5c4;
        }

        .buttonsDiv{
            /*padding-left: 1300px;*/
            padding-bottom: 20px;
        }

        /* Modal/Popup Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 600px;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #333;
        }

        .modal-body {
            padding: 30px 20px;
        }

        .modal-body p {
            margin: 0 0 20px 0;
            font-size: 14px;
            color: #666;
        }

        .export-options {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .export-option-btn {
            flex: 1;
            min-width: 120px;
            max-width: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
            border: 2px solid #abd4f3ff;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .export-option-btn:hover {
            background: #abd4f3ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .export-option-btn i {
            font-size: 48px;
            margin-bottom: 10px;
            color: #25679aff;
        }

        .export-option-btn:hover i {
            color: white;
        }

        .export-option-btn span {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .export-option-btn:hover span {
            color: white;
        }


    </style>
</head>
<body>

<div id="exportModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>გრაფიკის ექსპორტი</h3>
            <button class="modal-close" onclick="closeExportModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>აირჩიეთ ექსპორტის ფორმატი:</p>
            <div class="export-options">
                <button class="export-option-btn" onclick="exportGraphToExcel()">
                    <i class="fas fa-file-excel"></i>
                    <span>Excel</span>
                </button>
                <button class="export-option-btn" onclick="exportGraphToPDF()">
                    <i class="fas fa-file-pdf"></i>
                    <span>PDF</span>
                </button>
                <button class="export-option-btn" onclick="exportGraphToWord()">
                    <i class="fas fa-file-word"></i>
                    <span>Word</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="main-container calc">
    <h1>განვადების კალკულატორი</h1>

    <!-- Error/Success Messages -->
    <div id="messages"></div>
    <div id="errors"></div>
    <span id="confirmTXT" class="error-message element-to-hide hidden">* განვადება საჭიროებს დასტურს</span>


        <!-- Export Buttons -->
    <div class="export-buttons buttonsDiv hidden" id="graphExportButtons">
        <button class="export-btn" onclick="openExportModal('geo')">გრაფიკი (GEO)</button>
        <button class="export-btn" onclick="openExportModal('eng')">გრაფიკი (ENG)</button>
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
                <option value="mortgage">სტანდარტული</option>
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
        <div class="form-field"  style="display: none;">
            <label>ჯავშნის თარიღი</label>
            <input id="bookPayDate" class="form-control green-border" type="text" 
                   placeholder="dd/mm/YYYY" autocomplete="off"
                   onclick="BX.calendar({node: this, field: this, bTime: false, bSetFocus: false})">
        </div>

        <div class="form-field"  style="display: none;">
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
<!-- jsPDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<!-- jsPDF AutoTable -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

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
        hide('graphExportButtons');
        clearTable('graphData');
        return;
    }
    
    const startDate = getValue('startDate');
    const endDate = getValue('endDate');
    
    if (!startDate || !endDate) {
        alert("გთხოვთ შეავსოთ დაწყება/დასრულების თარიღი");
        hide('saveBTN');
        hide('saveCalculation');
        hide('graphExportButtons');
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

            show('graphExportButtons');

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
            hide('graphExportButtons');
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

    let fasdaklebaTanxa=0;
    if(data.PRICE && CONFIG.oldPrice){
        fasdaklebaTanxa=parseFormattedNumber(CONFIG.oldPrice)-parseFormattedNumber(data.PRICE);
        setValue('discountNum', formatNumber(fasdaklebaTanxa));
    }


    if(data.PRICE) {
        const newPrice = parseFormattedNumber(data.PRICE);
        const totalKVM = parseFormattedNumber(getValue('total_kvm')) || 1;
        const kvmPrice = totalKVM > 0 ? (newPrice / totalKVM) : 0;
        
        setValue('price', formatNumber(newPrice));
        setValue('priceGel', formatNumber(newPrice * CONFIG.nbgKursi));
        setValue('kvmPrice', formatNumber(kvmPrice));
        setValue('kvmPriceGel', formatNumber(kvmPrice * CONFIG.nbgKursi));

        // მშობელი form-row-ის დამალვა
        const bookPayDateField = document.getElementById('bookPayDate');
        if (bookPayDateField) {
            const formRow = bookPayDateField.closest('.form-row');
            if (formRow) {
                formRow.classList.add('hidden');
                formRow.style.display = 'none';
            }
        }
        const bookPaymentField = document.getElementById('bookPayment');
        if (bookPaymentField) {
            const formRow = bookPaymentField.closest('.form-row');
            if (formRow) {
                formRow.classList.add('hidden');
                formRow.style.display = 'none';
            }
        }

    }



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
/**
 * Excel export with improved column widths
 */
function exportTableToExcel(language = 'geo') {
    const table = document.getElementById('graphData');
    const wb = XLSX.utils.book_new();

    wb.Props = {
        Title: "განვადების გრაფიკი",
        Subject: "plan",
        Author: "homer.ge",
        CreatedDate: new Date()
    };

    const ws_data = [];

// HEADER
    ws_data.push([
        '#',
        language === 'eng' ? 'Payment Date' : 'გადახდის თარიღი',
        language === 'eng' ? 'Amount ($)' : 'თანხა ($)'
    ]);

// BODY
    for (let i = 1; i < table.rows.length; i++) {
        const row = table.rows[i];

        ws_data.push([
            row.cells[0].textContent,
            row.cells[1].querySelector('input').value,
            row.cells[2].querySelector('input').value + ' $'
        ]);
    }


    const ws = XLSX.utils.aoa_to_sheet(ws_data);

    // Set column widths (improved sizes)
    ws['!cols'] = [
        { wch: 10 },  // Column # - width 10
        { wch: 20 },  // Date column - width 20
        { wch: 25 }   // Amount column - width 25
    ];

    const sheetName = language === 'eng'
        ? 'Payment Schedule'
        : 'განვადების გრაფიკი';

    XLSX.utils.book_append_sheet(wb, ws, sheetName);

    XLSX.writeFile(
        wb,
        language === 'eng'
            ? 'payment_schedule.xlsx'
            : 'განვადების_გრაფიკი.xlsx'
    );

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

/* 5. ADD THESE JAVASCRIPT FUNCTIONS TO YOUR <script> SECTION */

// Global variable to store current export language
let currentExportLanguage = 'geo';

/**
 * Open export modal
 */
function openExportModal(language) {
    currentExportLanguage = language;
    const modal = document.getElementById('exportModal');
    modal.style.display = 'flex';

    // Update modal title based on language
    const modalTitle = modal.querySelector('.modal-header h3');
    if (language === 'eng') {
        modalTitle.textContent = 'Export Schedule (English)';
    } else {
        modalTitle.textContent = 'გრაფიკის ექსპორტი (ქართული)';
    }
}

/**
 * Close export modal
 */
function closeExportModal() {
    const modal = document.getElementById('exportModal');
    modal.style.display = 'none';
}

/**
 * Export graph to Excel from modal
 */
function exportGraphToExcel() {
    exportTableToExcel(currentExportLanguage);
    closeExportModal();
}

/**
 * Export graph to PDF from modal
 */
function exportGraphToPDF() {
    generatePDFFromGraph(currentExportLanguage);
    closeExportModal();
}

/**
 * Export graph to Word from modal
 */
function exportGraphToWord() {
    generateWordFromGraph(currentExportLanguage);
    closeExportModal();
}



/**
 * Generate PDF from graph table with full table format
 */
function generatePDFFromGraph(language) {

    const table = document.getElementById('graphData');
    if (!table || table.rows.length <= 1) {
        alert(language === 'eng' ? 'The schedule is empty' : 'გრაფიკი ცარიელია');
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');

// ფონტის დამატება
    doc.addFileToVFS("NotoSansGeorgian-Regular.ttf", notoGeorgianFont);
    doc.addFont("NotoSansGeorgian-Regular.ttf", "NotoGeorgian", "normal");

// აქტიური ფონტი
    doc.setFont("NotoGeorgian", "normal");



    // 👉 ცხრილი დაიწყოს ზედიდან
    const headers = language === 'eng'
        ? ['#', 'Payment Date', 'Amount ($)']
        : ['#', 'გადახდის თარიღი', 'თანხა ($)'];

    const data = [];
    for (let i = 1; i < table.rows.length; i++) {
        const row = table.rows[i];
        data.push([
            row.cells[0].textContent,
            row.cells[1].querySelector('input').value,
            row.cells[2].querySelector('input').value
        ]);
    }

    doc.autoTable({
        head: [[
            '#',
            language === 'eng' ? 'Payment Date' : 'გადახდის თარიღი',
            language === 'eng' ? 'Amount ($)' : 'თანხა ($)'
        ]],
        body: data,
        startY: 20,
        theme: 'grid',
        styles: {
            font: 'NotoGeorgian',   // 🔥 აქ
            fontSize: 10,
            halign: 'center'
        },
        headStyles: {
            font: 'NotoGeorgian',   // 🔥 აქაც
            fillColor: [171, 212, 243],
            textColor: [0, 0, 0],
            fontStyle: 'normal'
        }
    });



    doc.save(`გრაფიკი_${language}.pdf`);
}



/**
 * Generate Word document from graph table
 */
function generateWordFromGraph(language) {
    const table = document.getElementById('graphData');
    if (!table || table.rows.length <= 1) {
        alert(language === 'eng' ? 'The schedule is empty' : 'გრაფიკი ცარიელია');
        return;
    }

    // Get data from form
    const binisNomeri = document.getElementById('binisNomeri').value;
    const price = document.getElementById('price').value;
    const priceGel = document.getElementById('priceGel').value;
    const totalKvm = document.getElementById('total_kvm').value;
    const kvmPrice = document.getElementById('kvmPrice').value;

    // Build HTML content
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page {
                    size: A4;
                    margin: 20mm;
                }

                body {
                    font-family: "Sylfaen", "Arial", sans-serif;
                    font-size: 10pt;              /* 🔥 PDF-სავით */
                    margin: 0;
                    padding: 0;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;          /* 🔥 ძალიან მნიშვნელოვანია */
                }

                th, td {
                    border: 1px solid #000;
                    text-align: center;
                    vertical-align: middle;
                    padding: 4px 6px;             /* 🔥 პატარა padding */
                    line-height: 1.2;             /* 🔥 სიმჭიდროვე */
                    font-size: 10pt;
                }

                th {
                    background-color: #abd4f3;
                    font-weight: bold;
                }

                /* სვეტების ზუსტი ზომები */
                th:nth-child(1),
                td:nth-child(1) {
                    width: 10%;
                }

                th:nth-child(2),
                td:nth-child(2) {
                    width: 55%;
                }

                th:nth-child(3),
                td:nth-child(3) {
                    width: 35%;
                }
        </style>

        </head>
        <body>


            <table>
                <thead>
                    <tr>
                        <th>${language === 'eng' ? '#' : '#'}</th>
                        <th>${language === 'eng' ? 'Payment Date' : 'გადახდის თარიღი'}</th>
                        <th>${language === 'eng' ? 'Amount ($)' : 'თანხა ($)'}</th>
                    </tr>
                </thead>
                <tbody>
    `;

    // Add table rows
    for (let i = 1; i < table.rows.length; i++) {
        const row = table.rows[i];
        const num = row.cells[0].textContent;
        const date = row.cells[1].querySelector('input').value;
        const amount = row.cells[2].querySelector('input').value;

        htmlContent += `
                    <tr>
                        <td>${num}</td>
                        <td>${date}</td>
                        <td>${amount}</td>
                    </tr>
        `;
    }

    htmlContent += `
                </tbody>
            </table>


        </body>
        </html>
    `;

    // Convert HTML to Word-compatible format
    const blob = new Blob(
        ['\ufeff', htmlContent],
        { type: 'application/msword' }
    );

    // Create download link
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `გრაფიკი_${binisNomeri}_${language}.doc`;

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('exportModal');
    if (event.target === modal) {
        closeExportModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeExportModal();
    }
});

const notoGeorgianFont = `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Q0FUIABOSVBQSACATUFIIABOTU9MIAA+TkFWIABOUk9NIAAuAAD//wAFAAAAAQADAAcACAAA//8ABQAAAAEAAwAGAAgAAP//AAQAAAABAAMACAAA//8ABAAAAAEABQAIAAQAAAAA//8ABAAAAAEABAAIAAQAAAAA//8ABAAAAAEAAgAIAAlhYWx0AHRjYXNlAG5jY21wAGZjY21wAFZjY21wAGZjY21wAEpsb2NsAERsb2NsAD5vcmRuADgAAAABAAcAAAABAAYAAAABAAUAAAAEAAIABAACAAQAAAAGAAIABAACAAQAAgAEAAAAAgACAAQAAAABAAEAAAABAAAACQJEAXoBOgEmAKIAjACMADYAFAABAAAAAQAIAAIADgAEAa0BrgGtAa4AAQAEALsA+AErAaIABgAAAAIAJAAKAAMAAQA0AAEAEgAAAAEAAAAIAAEAAgD4AaIAAwABABoAAQASAAAAAQAAAAgAAQACALsBKwABAAoBZgFzAXQBngGsAdMB1AHbAd8B/AABAAAAAQAIAAEABgABAAEAAgELAc8ABAAQAAEACgAAAAEAZgAIAFwAUgBIAD4ANAAqACAAFgABAAQB6QACAagAAQAEAYwAAgGoAAEABAFsAAIBqAABAAQBNQACAagAAQAEARkAAgGoAAEABADpAAIBqAABAAQA2AACAagAAQAEAMMAAgGoAAEACAC7AM8A4gERASsBXwGFAeAAAQAQAAEACgAAAAIAQgACAYkBjgAGABAAAQAKAAAAAwAAAAEALgABABIAAQAAAAMAAQAMAFkAaQBqAJABRAFJAVQBXgF7AYQBywHdAAEAAgGFAY0AAQAAAAEACAACAGIALgABAAMABAAGAAkACwANAA8AEQATABQAFgAXABoAHAAeACAAIQAjACUAJwApACsALQAvADEAMgA0ADcAOQA7AD0APwBBAEMARQBHAEkAGQBLAE0ATwBRAFMAVQBXAAEALgBaAFwAXQBfAGEAYwBlAGcAawBtAG4AcABxAHMAdQB3AHkAegB8AH4AggCEAIYAiACKAIwAjgCRAJMAlwCZAJ0AnwChAKMApQCoAKoArACtAK8AsQCzALUAtwC5AAEAAAABAAgAAgByADYAAQADAAQABgAJAAsADQAPABEAEwAUABYAFwAaABwAHgAgACEAIwAlACcAKQArAC0ALwAxADIANAA3ADkAOwA9AD8AQQBDAEUARwBJABkASwBNAE8AUQBTAFUAVwGtAa4BDAGtAYkBjgGuAdAAAQA2AFoAXABdAF8AYQBjAGUAZwBrAG0AbgBwAHEAcwB1AHcAeQB6AHwAfgCCAIQAhgCIAIoAjACOAJEAkwCXAJkAnQCfAKEAowClAKgAqgCsAK0ArwCxALMAtQC3ALkAuwD4AQsBKwGFAY0BogHPAAAAAgAAAAMAAAAUAAMAAQAAABQABAW+AAAAegBAAAUAOgAAAA0AfgCjAKUAqwCwALQAuAC7AQcBEwEbASMBJwErATEBNwE+AUgBTQFbAWEBZQF+AhsCNwLHAskC3QMEAwgDDAMSAygFiRDFEMcQzRD/HLocvx6FHp4e8yAQIBQgGiAeICIgJiA6IKwgviEWISIiEi0lLSctLf//AAAAAAANACAAoAClAKcArgC0ALYAugC/AQoBFgEeASYBKgEuATYBOQFBAUoBUAFeAWQBagIYAjcCxgLJAtgDAAMGAwoDEgMmBYkQoBDHEM0Q0ByQHL0egB6eHvIgECATIBggHCAiICYgOSCsIL4hFiEiIhItAC0nLS3//wA2//sAAAAAAVEAAAAAAHsAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD/VwAA/zYAAAAAAAAAAP5CAAD7EwAA743vNQAAAAAAAAAA4kEAAOBxAADhqQAA4SPhQeFG4MPfz+CL4LzfhgAA04/TLgABAAAAAAB2ATIAAAE2AT4AAAFAAUQBRgHWAegB8gH8Af4CAAIGAggCEgIgAiYCPAJCAkQCbAAAAnAAAAJwAnoCggKGAAACiAAAAooAAAAAAtADLgOCA4YAAAOOAAADjgAAA44AAAAAAAAAAAAAAAAAAAAAA4IAAAAAAAAApwFwAb0BoAFcAbYBNAHEAbQBtQE5AbkBUgCAAbcB1QH8AawB3wHbAXQBcwHUAdMBZgGeAVEB0gGVAW0BfAG7AToAuwDGAMcAzADPANoA2wDgAOIA6gDrAO0A8gDzAPgBAgEDAQQBCAENAREBGwEcASEBIgEnAUEBPQFCATcB6AF6ASsBPAFGAVYBXwFyAXUBgQGFAY0BjwGRAZcBmgGiAbIBugHFAcwB1wHgAesB7AHxAfIB+AE/AT4BQAE4AJYBcQFPAdYB0QFaAVUBrQF9AckBsQFZAbMBuAFNAa4BfgG8AMEAvQC/AMUAwADEALwAygDVANAA0gDTAOcA4wDkAOUA2QD3AP0A+gD7AQEA/AGZAQABFgESARQBFQEjARABeQEyASwBLgE7ATABNgExAUsBZQFgAWIBYwGKAYYBhwGIAW4BnwGpAaMBpAGwAaUBWwGvAeUB4QHjAeQB8wHaAfUAwgEzAL4BLQDDATUAyAFHAMsBTADJAUoAzQFXAM4BWADWAWgA1AFkANgBbADRAWEA3AF2AN4BeADdAXcA4QGCAOgBiwDpAYwA5gGJAOwBkADuAZIA8AGUAO8BkwDxAZYA9AGbAPYBnQD1AZwA1wFrAP8BqwD+AaoA+QGmAQUBxgEHAcgBBgHHAQkBzQELAc8BCgHOAQ4B2AEYAecBEwHiARoB6gEXAeYBGQHpAR4B7gEkAfQBJQEoAfkBKgH7ASkB+gEMAdABDwHZAVABSAFDAV0BygGnAdwBgwF7AFkAaQHdAJABRAFeAGoBywGEAUkBUwFOAagABQAHABgAEgAVAE4AVgBIACgALgAzADUAOAA6ADwAWABCAEQASgBMAD4AMAAbAEAARgAOAAoALAAQAAwAUgAqAB0AIgAkAFAAHwAmAF0AXwBxAGsAbgCvALcAqACCAIgAjgCRAJMAlwCZALkAoQCjAKoArQCdAIoAcwCfAKUAZQBhAIYAZwBjALMAhAB1AHoAfACxAHcAfgBwALUAbQCsAFwAmwCVAFoAeQCMAAQABgAXABEAFABNAFUARwAnAC0AMgA0ADcAOQA7AFcAQQBDAEkASwA9AC8AGgA/AEUADQAJACsADwALAFEAKQAcACEAIwBPAB4AJQAWAFMAEwAZAAMAAQAgADEBIAHwAR0B7QEfAe8BJgH3AWoBaQG/AcABvgBeAGAAcgBsAG8AsAC4AKkAgwCJAI8AkgCUAJgAmgC6AKIApACrAK4AngCLAHQAoACmAGYAYgCHAGgAZAC0AIUAdgB7AH0AsgB4AH8AAAJYAF4CRwA3AjwAJgInAD0CWgA3AncAPQJzAD0DCQA4AQQAAAK8AD0CwAA9AnAANwJAAC0CagBTAq0AYgJ9AFMCXgBiA5wAPQL7AD0CcQBTAm4ANwKCAEwDNwA9AmcANwLlAEwCZwA3A04APQLgAF0CVgA1A7kATAJSADcCvABLAnIAPQK/AD0CtgBiAnIAPQJnACoCvwA9A0kAMgJ8AEIB4gAFAlUANwKLAA8CfAA9A3gAPQJTADICvwBaAmoANwJoADMCkABCBHcAPQPrAEwCegA9Au4APQAAAAACegBTAsYAYgOEAEIDYAA9Al8ANwNVAAUDkgA9AzcAPQJxAD0CpwA4A00APQOUADgCfABTAngAXQJ9ACsCvABdA80AQgOSAD0DKgA9AmgAYgJxAC0DDAA9AmMANwM5AEwCYwA3Ap0AOAJ8AFMCtwBiAkcANwJAADoCiwAhAx8ABQKMADMC4wAKAAD+uwIpADIB1gAnAeYANwIlADICtQAUAl4ANwKKAFIClgA3AmkAVQJKADICZQBVAlMASwJpAFUCXQBLA6YAVQAA/1kAAP9zA3EANwJeADcCVABLAkwAMgJqAFUDngA3AlQAMgKMAFUDJAA3AmoAVQI3ADADrgBVAjsAMgJTAFECSAA3ApsANwJDAFUCSAA3AlsANwKWADcDoAA3AUIAKAFCACgCPgA3AbwAFAI3ADICLv/BAl0ANwOgADcCNgAtAnkAUQJMADIB2wAUAl4ANwMDAEEEQQA3A6YAVQAA/2wCXQA3AmIANwJdAEsCZQBRAWIAKAEEAAADJAA3A6cAUQJAADICvwAUAhUASAEMAEgDWwA3A54ANwJUADcCbABRAyIANwOlAFECXQBLAmwAUQJdACcCbABRAQQAAANxADcDogBRAwAANwJnAFUCXgA3AlwAKQOnAFECRQAyA6YAVQJFADICbABRAl0ASwJpAFUCKQAyAhUAMgJrAB4CtwAUAmwALgLAABQCfwAAA3H//wJ/AAACfwAAAn8AAAJ/AAACfwAAAn8AAAJ/AAACfwAAAn8AAAKKAGECeAA9AngAPQJ4AD0CeAA9AngAPQLaAGEC2gBhAtoAHgIsAGECLABhAiwAYQIsAGECLABhAiwAYQIsAGECLABhAvgAYQIsAGEC2gAeAgcAYQLYAD0C2AA9AtgAPQLYAD0CxQBaAuUAYQLlAAABUwAoAVMAKAFTAAEBUwAeAVMAKAFTACgBUwAVAVMAKAER/7ICawBhAmsAYQIMAGECDABXAgwAYQIMAGECDAANA4sAYQL4AGEC+ABhAvgAYQL4AGEC+ABhAw0APQOgAD0DDQA9Aw0APQMNAD0DDQA9Aw0APQMNAD0DDQA9Aw0APQJdAGEDDQA9Am4AYQJuAGECbgBhAm4AYQIlADMCJQAzAiUAMwIlADMCJQAzAiwACgIsAAoCLAAKAl0AYQLbAFoC2wBaAtsAWgLbAFoC2wBaAtsAWgLbAFoC2wBaAtsAWgLbAFoCWAAAA6IADAOiAAwDogAMA6IADAOiAAwCSgAEAjYAAAI2AAACNgAAAjYAAAI2AAACPAAmAjwAJgI8ACYCPAAmAjEALgIxAC4CMQAuAjEALgEZACgCMQAuA2AALgIxAC4CMQAuAtwANQIxAC4CMQAuAjwAJgI8ADICJwApA4MAOgIxAC4CZwBVAXQACgInAO8BfAAcAXwAIAFJAFABSQAZAYcAKAAA/2UBeABNAeAANwHgADcBogAoAAD/VwHgADcB4AA3AeAANwDhAA4AAP+eAjwAWwGiACgBDABIAQwAKQAA/8AAAP+xA0AAMQJnADcCZwA3AmkANwGsADcCRACVAjwAMgI8AD4AtwAoAAD/zQI0ADcCNAA3AjQANwI0ADcCNAA3AjQANwI0ADcCPAAxAxcASAI0ADcD6AAoAfQAKAJqAFUCNAA3AjwAOAJdADcCPAAXAQ0ASAENAEgBWAAPAjwAPwI8ABUCZwA3AmcANwJnADcCZwA3AncAVQEZACgAAP4TAjwAMgH9ACgB/QAnATYAKAE2ACcCagBVAmoACQG3ACgAAP+CAQIATgECAEwBAv/YAQL/9QECAFUBAv//AQL/7AECABsBAv/JAQL/yQIWAFUCFgBVAQIAVQECAEwBAgBVAQIAQQI8ADIBAv/3A6cAVQFCACgCPABAAmoAVQJqAFUCagBVAmoAVQI8ADICagBVAoYAGQP8AF8CXQA3Al0ANwJdADcCXQA3A7IANgD1ACgAAP+uAl0ANwJdADcCXQA3AjwAWQFlACABeAAgAl0ANwJdADcB9P/9AmcAVQKPADcBLAAoASwAHgM/ADEBDABIAQwASAI8ADICZwA3AbIADAGyABgBmABBAaAAHwFnAAwBZwAMAK8ADACvAAwA+gAfAOEAQQGdAFUBnQBVAZ0ARwGdAD4DQAAxASwAKAAA/5QB3wAzAd8AMwHfADMB3wAzAd8AMwIBADsBDAAfAjwALAI8ADcBdAAKAjwAIAFpABABaQAQAWkAEAJnAFUCPAAtAb8AKAAA/hUDBQARAjwAMAJqAE8CagBPAmoATwJqAE8CagBPAmoATwJqAE8CagBPAbz//gJqAE8CagBPAfwAAAMSAAsDEgALAxIACwMSAAsDEgALAhEAEgH+AAEB/gABAf4AAQH+AAECPAAOAf4AAQHWACcB1gAnAdYAJwHWACcCPAAxAfQAvgH0ALkBeQAoAAAAIwGqAAMAAQQJAAAAngXCAAMAAQQJAAEAJAWeAAMAAQQJAAIADgWQAAMAAQQJAAMARgVKAAMAAQQJAAQANAUWAAMAAQQJAAUAGgT8AAMAAQQJAAYAMATMAAMAAQQJAAcARASIAAMAAQQJAAgAFAR0AAMAAQQJAAkASAQsAAMAAQQJAAoAngOOAAMAAQQJAAsAPgNQAAMAAQQJAAwAPAMUAAMAAQQJAA0BIgHyAAMAAQQJAA4ANgG8AAMAAQQJABkAIAGcAAMAAQQJAQAADAGQAAMAAQQJAQEACgGGAAMAAQQJASYAGgFsAAMAAQQJAScAdgD2AAMAAQQJASgAIgDUAAMAAQQJASkAGgC6AAMAAQQJASsACACyAAMAAQQJASwAFACeAAMAAQQJAS0ACgCUAAMAAQQJAS4ADgWQAAMAAQQJAS8ADACIAAMAAQQJATAAEAB4AAMAAQQJATEACABwAAMAAQQJATIAEgBeAAMAAQQJATMACgBUAAMAAQQJATQAHAA4AAMAAQQJATUAEgAmAAMAAQQJATYAGgAMAAMAAQQJATcADAAAAE4AbwByAG0AYQBsAFMAZQBtAGkAQwBvAG4AZABlAG4AcwBlAGQAQwBvAG4AZABlAG4AcwBlAGQARQB4AHQAcgBhAEMAbwBuAGQAZQBuAHMAZQBkAEIAbABhAGMAawBFAHgAdAByAGEAQgBvAGwAZABCAG8AbABkAFMAZQBtAGkAQgBvAGwAZABNAGUAZABpAHUAbQBMAGkAZwBoAHQARQB4AHQAcgBhAEwAaQBnAGgAdABUAGgAaQBuAGkAbwB0AGEAIABhAGQAcwBjAHIAaQBwAHQAQQBjAGMAZQBuAHQAZQBkACAARwByAGUAZQBrACAAUwBDAFQAaQB0AGwAaQBuAGcAIABBAGwAdABlAHIAbgBhAHQAZQBzACAASQAgAGEAbgBkACAASgAgAGYAbwByACAAdABpAHQAbABpAG4AZwAgAGEAbgBkACAAYQBsAGwAIABjAGEAcAAgAHMAZQB0AHQAaQBuAGcAcwBmAGwAbwByAGkAbgAgAHMAeQBtAGIAbwBsAFcAaQBkAHQAaABXAGUAaQBnAGgAdABOAG8AdABvAFMAYQBuAHMARwBlAG8AcgBnAGkAYQBuAGgAdAB0AHAAcwA6AC8ALwBvAHAAZQBuAGYAbwBuAHQAbABpAGMAZQBuAHMAZQAuAG8AcgBnAFQAaABpAHMAIABGAG8AbgB0ACAAUwBvAGYAdAB3AGEAcgBlACAAaQBzACAAbABpAGMAZQBuAHMAZQBkACAAdQBuAGQAZQByACAAdABoAGUAIABTAEkATAAgAE8AcABlAG4AIABGAG8AbgB0ACAATABpAGMAZQBuAHMAZQAsACAAVgBlAHIAcwBpAG8AbgAgADEALgAxAC4AIABUAGgAaQBzACAAbABpAGMAZQBuAHMAZQAgAGkAcwAgAGEAdgBhAGkAbABhAGIAbABlACAAdwBpAHQAaAAgAGEAIABGAEEAUQAgAGEAdAA6ACAAaAB0AHQAcABzADoALwAvAG8AcABlAG4AZgBvAG4AdABsAGkAYwBlAG4AcwBlAC4AbwByAGcAaAB0AHQAcAA6AC8ALwB3AHcAdwAuAG0AbwBuAG8AdAB5AHAAZQAuAGMAbwBtAC8AcwB0AHUAZABpAG8AaAB0AHQAcAA6AC8ALwB3AHcAdwAuAGcAbwBvAGcAbABlAC4AYwBvAG0ALwBnAGUAdAAvAG4AbwB0AG8ALwBEAGUAcwBpAGcAbgBlAGQAIABiAHkAIABNAG8AbgBvAHQAeQBwAGUAIABkAGUAcwBpAGcAbgAgAHQAZQBhAG0ALgAgAEcAZQBvAHIAZwBpAGEAbgAgAGMAaABhAHIAYQBjAHQAZQByAHMAIABkAGUAcwBpAGcAbgAgAGIAeQAgAEEAawBhAGsAaQAgAFIAYQB6AG0AYQBkAHoAZQAuAE0AbwBuAG8AdAB5AHAAZQAgAEQAZQBzAGkAZwBuACAAVABlAGEAbQAsACAAQQBrAGEAawBpACAAUgBhAHoAbQBhAGQAegBlAEcAbwBvAGcAbABlACAATABMAEMATgBvAHQAbwAgAGkAcwAgAGEAIAB0AHIAYQBkAGUAbQBhAHIAawAgAG8AZgAgAEcAbwBvAGcAbABlACAASQBuAGMALgBOAG8AdABvAFMAYQBuAHMARwBlAG8AcgBnAGkAYQBuAC0AUgBlAGcAdQBsAGEAcgBWAGUAcgBzAGkAbwBuACAAMgAuADAAMAA1AE4AbwB0AG8AIABTAGEAbgBzACAARwBlAG8AcgBnAGkAYQBuACAAUgBlAGcAdQBsAGEAcgAyAC4AMAAwADUAOwBHAE8ATwBHADsATgBvAHQAbwBTAGEAbgBzAEcAZQBvAHIAZwBpAGEAbgAtAFIAZQBnAHUAbABhAHIAUgBlAGcAdQBsAGEAcgBOAG8AdABvACAAUwBhAG4AcwAgAEcAZQBvAHIAZwBpAGEAbgBDAG8AcAB5AHIAaQBnAGgAdAAgADIAMAAyADIAIABUAGgAZQAgAE4AbwB0AG8AIABQAHIAbwBqAGUAYwB0ACAAQQB1AHQAaABvAHIAcwAgACgAaAB0AHQAcABzADoALwAvAGcAaQB0AGgAdQBiAC4AYwBvAG0ALwBuAG8AdABvAGYAbwBuAHQAcwAvAGcAZQBvAHIAZwBpAGEAbgApAAAAAgAAAAAAAP+cADIAAAAAAAAAAAAAAAAAAAAAAAAAAAIAAAABAgEDAQQBBQEGAQcBCAEJAQoBCwEMAQ0BDgEPARABEQESARMBFAEVARYBFwEYARkBGgEbARwBHQEeAR8BIAEhASIBIwEkASUBJgEnASgBKQEqASsBLAEtAS4BLwEwATEBMgEzATQBNQE2ATcBOAE5AToBOwE8AT0BPgE/AUABQQFCAUMBRAFFAUYBRwFIAUkBSgFLAUwBTQFOAU8BUAFRAVIBUwFUAVUBVgFXAVgBWQFaAVsBXAFdAV4BXwFgAWEBYgFjAWQBZQFmAWcBaAFpAWoBawFsAW0BbgFvAXABcQFyAXMBdAF1AXYBdwF4AXkBegF7AXwBfQF+AX8BgAAQAYEBggGDAYQBhQGGAYcBiAGJAYoBiwGMAY0BjgGPAZABkQGSAZMBlAGVAZYBlwGYAZkBmgGbAZwBnQGeAZ8BoAGhAaIBowGkAaUBpgADAacBqAGpAaoBqwGsAa0BrgGvAbABsQGyAbMBtAG1AbYBtwG4AbkAJACQAMkBugDHAGIArQG7AbwAYwCuACUAJgD9AP8AZAG9ACcBvgG/ACgAZQHAAMgAygHBAMsBwgHDAcQA6QApACoA+AHFAcYBxwArAcgALADMAM0AzgD6AM8ByQHKAC0ALgHLAC8BzAHNAc4A4gAwADEBzwHQAdEAZgAyALAA0ADRAGcA0wHSAdMAkQCvADMANAA1AdQB1QHWADYB1wDkAPsB2AA3AdkB2gDtADgA1AHbANUAaADWAdwB3QHeAd8AOQA6AeAB4QHiAeMAOwA8AOsB5AC7AeUAPQHmAOYB5wBEAGkB6ABrAI0AbACgAGoB6QAJAeoAbgBBAGEADQAjAG0ARQA/AF8AXgBgAD4AQADbAesAhwBGAP4A4QHsAQAAbwHtAN4B7gCEANgAHQAPAe8B8ACLAEcB8QEBAIMAjgC4AAcA3AHyAEgAcAHzAHIAcwH0AHEAGwCrAfUAswCyAfYB9wAgAOoB+AAEAKMASQAYABcASgD5AfkB+gCJAEMB+wAhAKkAqgC+AL8ASwH8AN8B/QBMAHQAdgB3ANcAdQH+Af8ATQIAAE4CAQBPAgICAwIEAB8A4wBQAO8A8ABRAgUCBgIHABwAeAAGAggAUgB5AHsAfACxAOACCQB6AgoCCwAUAJ0AngChAH0CDABTAIgACwAMAAgAEQDDAA4AVAAiAKIABQDFALQAtQC2ALcAxAAKAFUCDQIOAg8AigDdAhAAVgIRAOUA/AISAIYAHgAaABkAEgCFAFcCEwIUAO4AFgDZAhUAjAAVAFgAfgIWAIAAgQB/AhcCGABCAhkCGgBZAFoCGwIcAh0CHgBbAFwA7AIfALoAlgIgAF0CIQDnAiIAEwIjAiQCJQd1bmkxQ0JEB3VuaTEwQ0QHdW5pMUNCQQd1bmkxQzkwB3VuaTEwQTAHdW5pMUM5MQd1bmkxMEExAkNSB3VuaTFDQUEHdW5pMTBCQQd1bmkxQ0FEB3VuaTEwQkQHdW5pMUNBOQd1bmkxMEI5B3VuaTFDQUMHdW5pMTBCQwd1bmkxQzkzB3VuaTEwQTMHdW5pMUNCOAd1bmkxQzk0B3VuaTEwQTQHdW5pMUNCNgd1bmkxQzkyB3VuaTEwQTIHdW5pMUNCOQd1bmkxQ0E2B3VuaTEwQjYHdW5pMUNCMAd1bmkxMEMwB3VuaTFDQjQHdW5pMTBDNAd1bmkxQ0JFB3VuaTFDQjEHdW5pMTBDMQd1bmkxQ0IyB3VuaTEwQzIHdW5pMUNCNQd1bmkxMEM1B3VuaTFDOTgHdW5pMTBBOAd1bmkxQ0FGB3VuaTEwQkYHdW5pMUNBQgd1bmkxMEJCB3VuaTFDOTkHdW5pMTBBOQd1bmkxQ0E1B3VuaTEwQjUHdW5pMUNCRgd1bmkxQzlBB3VuaTEwQUEHdW5pMUM5Qgd1bmkxMEFCBE5VTEwHdW5pMUM5Qwd1bmkxMEFDB3VuaTFDOUQHdW5pMTBBRAd1bmkxQzlFB3VuaTEwQUUHdW5pMUNBNAd1bmkxMEI0B3VuaTFDQTcHdW5pMTBCNwd1bmkxQ0EwB3VuaTEwQjAHdW5pMUNBMQd1bmkxMEIxB3VuaTFDQTgHdW5pMTBCOAd1bmkxQzk3B3VuaTEwQTcHdW5pMUNBMgd1bmkxMEIyB3VuaTFDQTMHdW5pMTBCMwd1bmkxQzk1B3VuaTEwQTUHdW5pMUNCMwd1bmkxMEMzB3VuaTFDQUUHdW5pMTBCRQd1bmkxQ0I3B3VuaTEwQzcHdW5pMUM5Ngd1bmkxMEE2B3VuaTFDOUYHdW5pMTBBRglhY3V0ZWNvbWIHdW5pMTBGRAd1bmkyRDJEB3VuaTEwRkEHdW5pMTBEMAd1bmkyRDAwB3VuaTEwRDEHdW5pMkQwMQd1bmkxMEVBB3VuaTJEMUEHdW5pMTBFRAd1bmkyRDFEB3VuaTEwRTkHdW5pMkQxOQd1bmkxMEVDB3VuaTJEMUMHdW5pMDMwMgd1bmkwMzA4B3VuaTEwRDMHdW5pMkQwMwd1bmkxMEY4B3VuaTEwRDQHdW5pMkQwNAd1bmkxMEY2B3VuaTEwRDIHdW5pMkQwMgd1bmkxMEU2B3VuaTJEMTYHdW5pMTBGMAd1bmkyRDIwB3VuaTEwRjQHdW5pMkQyNAd1bmkxMEZFB3VuaTEwRjEHdW5pMkQyMQd1bmkxMEYyB3VuaTJEMjIHdW5pMTBGNQd1bmkyRDI1B3VuaTIwMTAHdW5pMTBEOAd1bmkyRDA4B3VuaTEwRUYHdW5pMkQxRgd1bmkxMEVCB3VuaTJEMUIHdW5pMTBEOQd1bmkyRDA5B3VuaTEwRTUHdW5pMkQxNQd1bmkxMEZGB3VuaTIwQkUHdW5pMTBEQQd1bmkyRDBBB3VuaTAzMDQHdW5pMTBEQgd1bmkyRDBCB3VuaTEwREMHdW5pMkQwQwd1bmkxMEZDB3VuaTAwQTAHdW5pMTBERAd1bmkyRDBEB3VuaTEwREUHdW5pMkQwRQd1bmkxMEZCB3VuaTA1ODkHdW5pMTBFNAd1bmkyRDE0B3VuaTEwRTcHdW5pMkQxNwd1bmkxMEUwB3VuaTJEMTAHdW5pMTBFMQd1bmkyRDExB3VuaTEwRTgHdW5pMkQxOAd1bmkxMEQ3B3VuaTJEMDcHdW5pMTBFMgd1bmkyRDEyB3VuaTEwRjkHdW5pMTBFMwd1bmkyRDEzB3VuaTEwRDUHdW5pMkQwNQd1bmkxMEYzB3VuaTJEMjMHdW5pMTBFRQd1bmkyRDFFB3VuaTEwRjcHdW5pMkQyNwd1bmkxMEQ2B3VuaTJEMDYHdW5pMTBERgd1bmkyRDBGBkFicmV2ZQdBbWFjcm9uB0FvZ29uZWsKQ2RvdGFjY2VudAZEY2Fyb24GRGNyb2F0BkVjYXJvbgpFZG90YWNjZW50B0VtYWNyb24DRW5nB0VvZ29uZWsHdW5pMDEyMgpHZG90YWNjZW50B3VuaTFFOUUESGJhcgdJbWFjcm9uB0lvZ29uZWsHdW5pMDEzNgZMYWN1dGUGTGNhcm9uB3VuaTAxM0IGTmFjdXRlBk5jYXJvbgd1bmkwMTQ1DU9odW5nYXJ1bWxhdXQHT21hY3JvbgZSYWN1dGUGUmNhcm9uB3VuaTAxNTYGU2FjdXRlB3VuaTAyMTgGVGNhcm9uB3VuaTAyMUEGVWJyZXZlDVVodW5nYXJ1bWxhdXQHVW1hY3JvbgdVb2dvbmVrBVVyaW5nBldhY3V0ZQtXY2lyY3VtZmxleAlXZGllcmVzaXMGV2dyYXZlC1ljaXJjdW1mbGV4BllncmF2ZQZaYWN1dGUKWmRvdGFjY2VudAZhYnJldmUHYW1hY3Jvbgdhb2dvbmVrB3VuaTAzMDYHdW5pMDMwQwpjZG90YWNjZW50B3VuaTAzMjcHdW5pMDMyNgd1bmkwMzEyBmRjYXJvbgd1bmkwMzA3BmVjYXJvbgplZG90YWNjZW50B2VtYWNyb24DZW5nB2VvZ29uZWsERXVybwd1bmkwMTIzCmdkb3RhY2NlbnQJZ3JhdmVjb21iBGhiYXIHdW5pMDMwQgdpbWFjcm9uB2lvZ29uZWsHdW5pMDIzNwd1bmkwMTM3BmxhY3V0ZQZsY2Fyb24HdW5pMDEzQwZuYWN1dGUGbmNhcm9uB3VuaTAxNDYHdW5pMjExNgd1bmkwMzI4DW9odW5nYXJ1bWxhdXQHb21hY3JvbglvdmVyc2NvcmUGcmFjdXRlBnJjYXJvbgd1bmkwMTU3B3VuaTAzMEEGc2Fj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//YAXf/sAF//9gBw//YAdf/sAHn/9gCG//YAjP/2AJH/9gCZ/+wAnf/2AKj/9gCs//YAt//sAAEAdf/sABYAWv/2AF3/7ABf//YAa//2AHD/9gBxAAoAc//2AHn/9gB6//YAgv/2AIQACgCG//YAjP/2AI7/9gCR//YAl//2AJn/7ACd//YAof/2AKX/9gCo//YArP/2AAEAhQAUAAQAXf/sAHX/7ACZ/+wAt//sAAMAd//YAIT/9gCx/+IABABh/9gAqv+6ALX/2AC3ADwAAgCF/+wAuP/iAA4AYP/2AGz/7AB4//YAff/sAIn/9gCY//YAoP/2AKL/9gCk//YApv/2AKn/9gCu//YAsv/2ALj/7AAFAG3/9gB3/8QAqv/2ALH/4gC5//YADABn//YAbgAKAHEACgCEAAoAigAKAJP/9gCV/84AnwAKAKP/9gCvAAoAs//2ALUACgAEAHf/zgCE//YArf/2ALH/4gABALcAFAAGAF3/9gB3/9gAhP/sAJn/9gCt//YAsf/sAAIAW//2AIX/7AAWAFT/9gBa/+wAXf/sAF//7ABn//YAa//sAHD/7ABz/+wAdf/sAHn/7ACG/+wAjP/sAI7/7ACR/+wAk//2AJn/7ACd/+wAo//2AKj/7ACs/+wAs//2ALf/7AABAIUACgADAHf/2ACx/+IAuf/2AAEAbP/sAAUAbgAKAHr/4gCKAAoArwAKALUACgABALj/xAAGAF7/4gCD/+IAi//iAJr/4gC4/+IAuv/iAAIAd//OALH/2AAUAFr/4gBf/+IAa//iAHD/4gBz/+IAef/iAHz/7AB+//YAgv/sAIb/4gCM/+IAjv/iAJH/4gCX/+wAnf/iAKH/7ACl/+wAqP/iAKz/4gC5//YAFQBa//YAX//2AGf/9gBr//YAcP/2AHP/9gB5//YAev/2AHz/9gCG//YAjP/2AI7/9gCR//YAk//2AJX/7ACd//YAo//2AKX/7ACo//YArP/2ALP/9gAdAF7/4gBg//YAbP/sAG//9gBy//YAdP/2AHb/9gB4//YAf//2AIP/4gCH//YAif/2AIv/4gCP//YAkv/2AJT/9gCY//YAmv/iAJ7/9gCg//YAov/2AKT/9gCm//YAqf/2AKv/9gCu//YAsP/2ALL/9gC6/+IAAQCF//YAAwB8//YAhP/iALH/2AACAD3/9gCx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
`;






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