<?
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle(" ");
CJSCore::Init(array("jquery"));

function getCIBlockElementsByFilter($arFilter = array())
{
    $arElements = array();
    $arSelect = array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*", "PREVIEW_PICTURE", "DETAIL_PICTURE", "IBLOCK_SECTION_ID");
    $res = CIBlockElement::GetList(array(), $arFilter, false, array("nPageSize" => 50), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild)
            $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp)
            $arPushs[$key] = $arProp["VALUE"];
        $arPushs["binis_naxazi"] = CFile::GetPath($arPushs["binis_naxazi"]);
        $arPushs["binis_gegmareba"] = CFile::GetPath($arPushs["binis_gegmareba"]);
        $arPushs["render_3D"] = CFile::GetPath($arPushs["render_3D"]);
        $arPushs["xedi_1"] = CFile::GetPath($arPushs["xedi_1"]);
        $arPushs["xedi_2"] = CFile::GetPath($arPushs["xedi_2"]);
        $arPushs["xedi_3"] = CFile::GetPath($arPushs["xedi_3"]);
        $price = CPrice::GetBasePrice($arPushs["ID"]);
        $arPushs["PRICE"] = $price["PRICE"];
        array_push($arElements, $arPushs);
    }
    return $arElements;
}

function printArr($arr)
{
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
}

function getDealsByFilter($arFilter, $arSelect = array(), $arSort = array("ID" => "DESC"))
{
    $arDeals = array();
    $res = CCrmDeal::GetList($arSort, $arFilter, $arSelect);
    while ($arDeal = $res->Fetch())
        array_push($arDeals, $arDeal);
    return (count($arDeals) > 0) ? $arDeals : false;
}

function getUserName($ASSIGNED_BY_ID)
{
    $res = CUser::GetByID($ASSIGNED_BY_ID)->Fetch();
    return $res["NAME"] . " " . $res["LAST_NAME"];
}

function getUsersdsByID($id)
{
    $arrUsers = array();
    $arSelect = array('SELECT' => array("ID", "WORK_POSITION", "PERSONAL_ICQ", "UF_*"));
    $arFilter = array("ID" => $id);
    $rsUsers = CUser::GetList(($by = "NAME"), ($order = "desc"), $arFilter, $arSelect);
    while ($arUser = $rsUsers->Fetch()) {
        return $arUser;
    }
    return array();
}

global $USER;
$userID = $USER->GetID();
$salesmeneger = getUsersdsByID($userID);

$salesmenegerphone = $salesmeneger["PERSONAL_MOBILE"];
$salesmenegername = $salesmeneger["NAME"] . " " . $salesmeneger["LAST_NAME"];
$salesmenegermail = $salesmeneger["EMAIL"];
$salesmenegerworkphone = $salesmeneger["WORK_PHONE"];
$misamarti = $salesmeneger["PERSONAL_STREET"];

$date = date("Y-m-d");
$url = "https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies?Currencies=USD&date={$date}";
$seb = file_get_contents($url);
$seb = json_decode($seb);
$seb_currency = $seb[0]->currencies[0]->rate;

if (isset($_GET["prod_ID"]) && !empty($_GET["prod_ID"]))
    $documentid = $_GET["prod_ID"];

$prod_ID = $documentid;
$arFilter = array("ID" => $prod_ID);
$product = getCIBlockElementsByFilter($arFilter);

$korpusi = $product[0]['KORPUSIS_NOMERI_XE3NX2'];
$sadarbazo = $product[0]['_0AF2S0'];
$sartuli = $product[0]['FLOOR'];
$flatNum = $product[0]['Number'];
$totalspace = $product[0]['TOTAL_AREA'];
$sacxovrebelifarti = $product[0]['LIVING_SPACE'];
$aivani = $product[0]['BALCONY_AREA'];

$kvmdollar = $product[0]['livingarea_price_per'];
$kvmezo = $product[0]['yardKvmPrice'];
$kvmterasa = $product[0]['terraceprice_per'];

$totalprice = round($product[0]['PRICE']);

$sartulinew = $product[0]["binis_naxazi"];
$floorplan = $product[0]['binis_gegmareba'];
$threeD = $product[0]["render_3D"];

$xedi_1 = $product[0]['xedi_1'];
$xedi_2 = $product[0]["xedi_2"];
$xedi_3 = $product[0]["xedi_3"];

$chabarebisforma = $product[0]['SUBMISSION_TYPE'];
$projectName = $deals[0]['UF_CRM_1693385948133'];
$kvmPrice = $product[0]['livingarea_price_per'];
$projectID = $product[0]['IBLOCK_SECTION_ID'];
$projectName = $product[0]['PROJECT'];

$fartisType1 = $product[0]['PRODUCT_TYPE'];

if($fartisType1=="ბინა"){
    $fartisType="ბინი";
}else {
    $fartisType=$fartisType1;
}

$arFilter = array("ID" => 10983);
$boulvard = getCIBlockElementsByFilter($arFilter);
if (count($boulvard)) {
    $boulvardfoto = CFile::GetPath($boulvard[0]["PHOTO"]);
}

$arFilter = array("ID" => 10996);
$background = getCIBlockElementsByFilter($arFilter);
if (count($background)) {
    $backgroundfoto = CFile::GetPath($background[0]["PHOTO"]);
}

$arFilter = array("ID" => 11131);
$z = getCIBlockElementsByFilter($arFilter);
if (count($z)) {
    $zfoto = CFile::GetPath($z[0]["PHOTO"]);
}

$arFilter = array("ID" => 11133);
$logo2 = getCIBlockElementsByFilter($arFilter);
if (count($logo2)) {
    $logo2foto = CFile::GetPath($logo2[0]["PHOTO"]);
}

$arFilter = array("ID" => 11134);
$bade = getCIBlockElementsByFilter($arFilter);
if (count($bade)) {
    $badefoto = CFile::GetPath($bade[0]["PHOTO"]);
}


$arFilter = array("ID" => 11419);
$gayidvebi = getCIBlockElementsByFilter($arFilter);
if (count($gayidvebi)) {
    $gayidvebifoto = CFile::GetPath($gayidvebi[0]["PHOTO"]);
}

$arFilter = array("ID" => 11420);
$gayidvebi2 = getCIBlockElementsByFilter($arFilter);
if (count($gayidvebi2)) {
    $gayidvebi2foto = CFile::GetPath($gayidvebi2[0]["PHOTO"]);
}

$arFilter = array("ID" => 11390);
$gayidvebi3 = getCIBlockElementsByFilter($arFilter);
if (count($gayidvebi3)) {
    $gayidvebi3foto = CFile::GetPath($gayidvebi3[0]["PHOTO"]);
}
	

$arFilter = array("ID" => 11447);
$green = getCIBlockElementsByFilter($arFilter);
if (count($green)) {
    $greenfoto = CFile::GetPath($green[0]["PHOTO"]);
}
	


	


?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>შეთავაზება - Park Boulevard</title>
    <link rel="stylesheet" href="//cdn.web-fonts.ge/fonts/bpg-nino-elite-exp-caps/css/bpg-nino-elite-exp-caps.min.css">
    <link rel="stylesheet" href="//cdn.web-fonts.ge/fonts/bpg-web-001-caps/css/bpg-web-001-caps.min.css">
    <link rel="stylesheet" href="//cdn.web-fonts.ge/fonts/arial-geo-bolditalic/css/arial-geo-bolditalic.min.css">

    <style>
/* Reset everything to the absolute edges */
html, body {
    margin: 0 !important;
    padding: 0 !important;
    width: 100%;
    background-color: white;
}

/* Target Bitrix specific containers to remove their forced padding */
.workarea-content-paddings, 
#workarea-content, 
.bx-layout-inner-inner-cont {
    padding: 0 !important;
    margin: 0 !important;
}

.page {
    width: 100%;
    margin: 0;
    background: white;
    position: relative;
    box-sizing: border-box;
}

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start; /* Changed from center */
    width: 100%;
    margin: 0;
    padding: 0;
}

.logo-placeholder {
    width: 100%;
    margin: 0;
    padding: 0;
    line-height: 0; /* Remove any line-height gaps */
}

/* Ensure logo image touches the top/sides with zero gaps */
.logo-placeholder img {
    display: block;
    max-width: 100%;
    height: auto;
    margin: 0;
    padding: 0;
    vertical-align: top; /* Prevents baseline gap */
}

.divider {
    height: 2px;
    background: var(--primary-color);
    margin: 5mm 0;
    width: 100%;
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8mm;
}

@media print {
    @page {
        margin: 0;
        size: auto;
    }
    
    body {
        margin: 0;
    }
    
    .page {
        page-break-after: always;
        padding:0;
    }
    
    .header {

    }
}

.page__workarea-content {
    padding: 0;

  }

  .app__page {
    padding: 0;
  }

  .page{
    width: 234mm;
  }

  .info-container-bg {
    padding: 5mm;
    border-radius: 8px;
    background-color: rgba(255, 255, 255, 0.9); /* Semi-transparent white overlay */
}
.info-table {
    border-radius: 2mm;
    padding: 4mm;
    margin-bottom: 4mm;
    margin-top:20%;
    color: var(--text-dark); /* Keeps text dark for readability */
}

.info-container-bg .section-title {
    color: white; 
    border-bottom-color: white;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
}

img {
    border: none;
    margin-top: 10%;
}

.info-table,
.info-table .info-label,
.info-table .info-value,
.price-box,
.price-box .price-label,
.price-box .price-amount,
.price-box .price-per-sqm {
    color: rgb(52, 87, 112);
    font-size: 18px;
    margin-left: 5px;
    font-family: "BPG WEB 001 Caps", sans-serif;
}

.info-row {
    margin-bottom: 15px;
}

.price-box {
    margin-top: 15px;       
    display: flex;
    flex-direction: column;
    gap: 12px;    
        margin-left: 0;           
}

.gayidvebismenejeri{

    color: rgb(52, 87, 112);
    font-size: 28px;
    font-weight:bolder;
    margin-left: 25px;
    font-family: "BPG WEB 001 Caps", sans-serif;
    margin-top:20px;
}

.footer2{

color: rgb(52, 87, 112);
font-size: 20px;
font-weight:bolder;
margin-left: 25px;
font-family: "BPG WEB 001 Caps", sans-serif;
}

.tableclass{
    color: rgb(52, 87, 112);
    font-size: 18px;
    margin-left: 5px;
    font-family: "BPG WEB 001 Caps", sans-serif;
}

</style>
    </style>
</head>
<body>
    <!-- PAGE 1 -->
    <div class="page">
        <div class="header">
            <div class="header-top">
                <div class="logo-placeholder">
                    <?php if(isset($boulvardfoto) && $boulvardfoto): ?>
                        <img src="<?php echo $boulvardfoto; ?>" alt="Park Boulevard">
                    <?php else: ?>
                        PARK BOULEVARD
                    <?php endif; ?>
                </div>
               
            </div>
            <div class="divider"></div>
        </div>

        <div class="content-grid">
            <!-- LEFT COLUMN -->
            <div class="property-info">
    <div class="info-container-bg" style="<?php echo (isset($backgroundfoto) && $backgroundfoto) ? 'background-image: url(\'' . $backgroundfoto . '\'); background-size: 60%; width: 38%; background-repeat: no-repeat;
    height: 35%;' : ''; ?>">
                
                <div class="info-table">
                    <div class="info-row">
                        <span class="info-label">პროექტი:</span>
                        <span class="info-value" id="projectName"><?php echo $projectName; ?></span>
                    </div>
                    <div class="info-row" id="korpusiDiv">
                        <span class="info-label">კორპუსი:</span>
                        <span class="info-value" id="korpusi"><?php echo $korpusi; ?></span>
                    </div>
                    <div class="info-row" id="sadarbazoDiv" style="display: none;">
                        <span class="info-label">ბლოკი:</span>
                        <span class="info-value" id="sadarbazo"><?php echo $sadarbazo; ?></span>
                    </div>
                    <div class="info-row" id="sartuliDiv">
                        <span class="info-label">სართული:</span>
                        <span class="info-value" id="sartuli"><?php echo $sartuli; ?></span>
                    </div>
                    <div class="info-row" id="flatNumDiv">
                        <span class="info-label">ბინის #:</span>
                        <span class="info-value" id="flatNum"><?php echo $flatNum; ?></span>
                    </div>
                    <div class="info-row" id="totalspaceDiv">
                        <span class="info-label">სრული ფართი:</span>
                        <span class="info-value" id="totalspace"><?php echo $totalspace; ?> მ²</span>
                    </div>
                

                <div class="price-box">
                    <div class="price-per-sqm" id="kvmPrice">ჯამური ღირებულება: <?php echo number_format($totalprice); ?></div>
                    <div class="price-per-sqm" id="kvmPrice">ღირებულება მ²: $ <?php echo number_format($kvmdollar); ?></div>
                </div>
                </div>
                <br> <br>
                <br>
                <br>
                <br>

                <div class="gayidvebismenejeri">გაყიდვების მენეჯერი</div>
<table class="tableclass" style="border-collapse: collapse; margin-top: 10px; margin-left: 25px;">
    <tr>
        <td style="padding-right: 100px; vertical-align: top;">
            <?php if(isset($gayidvebifoto) && $gayidvebifoto): ?>
                <table style="border-collapse: collapse; margin-bottom: 10px;">
                    <tr>
                        <td style="vertical-align: middle; padding-right: 6px;">
                            <img src="<?php echo $gayidvebifoto; ?>" alt="გაყიდვები" style="display:block; max-width:40px; height:auto;">
                        </td>
                        <td style="vertical-align: middle;">
                            <?php echo $salesmenegername; ?>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>

            <?php if(isset($gayidvebi2foto) && $gayidvebi2foto): ?>
                <table style="border-collapse: collapse;">
                    <tr>
                        <td style="vertical-align: middle; padding-right: 6px;">
                            <img src="<?php echo $gayidvebi2foto; ?>" alt="გაყიდვები" style="display:block; max-width:40px; height:auto;">
                        </td>
                        <td style="vertical-align: middle;">
                            <?php echo $salesmenegermail; ?>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
        </td>
        <td style="vertical-align: top;">
            <?php if(isset($gayidvebi3foto) && $gayidvebi3foto): ?>
                <table style="border-collapse: collapse;">
                    <tr>
                        <td style="vertical-align: middle; padding-right: 6px;">
                            <img src="<?php echo $gayidvebi3foto; ?>" alt="გაყიდვები" style="display:block; max-width:40px; height:auto;">
                        </td>
                        <td style="vertical-align: middle;">
                            <?php echo $salesmenegerphone; ?>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
        </td>
    </tr>
</table>

<div class="info-container-bg" style="<?php echo (isset($greenfoto) && $greenfoto) ? 'background-image: url(\'' . $greenfoto . '\'); background-size: 60%; width: 100%; background-repeat: no-repeat; height: 13%; margin-left: -20px; margin-top:18px;' : ''; ?>">
    <div class="footer2">ᲨᲔᲗᲐᲕᲐᲖᲔᲑᲘᲡ ᲗᲐᲠᲘᲦᲘ:</div>
    <table class="tableclass" style="border-collapse: collapse; margin-top: 25px; margin-left: 25px;">
        <tr>
            <!-- Column 1 -->
            <td style="padding-right: 100px; vertical-align: top;">
                <table style="border-collapse: collapse; margin-bottom: 10px;">
                    <tr>
                        <td style="vertical-align: middle;">
                            ჯამური ღირებულება (USD):
                        </td>
                    </tr>
                </table>
                <table style="border-collapse: collapse;">
                    <tr>
                        <td style="vertical-align: middle;">
                            ღირებულება მ2 (USD):
                        </td>
                    </tr>
                </table>
            </td>

            <!-- Column 2 -->
            <td style="vertical-align: top;">
                <table style="border-collapse: collapse; margin-bottom: 10px;">
                    <tr>
                        <td style="vertical-align: middle;">
                            ჯამური ღირებულება (GEL):
                        </td>
                    </tr>
                </table>
                <table style="border-collapse: collapse;">
                    <tr>
                        <td style="vertical-align: middle;">
                            ღირებულება მ2 (GEL):
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
</div>

                <div class="floor-plan-box" id="floorplan">
                    <?php if($floorplan): ?>
                        <img src="<?php echo $floorplan; ?>" alt="ბინის გეგმა">
                    <?php else: ?>
                        <div class="image-placeholder">ბინის გეგმა</div>
                    <?php endif; ?>
                </div>
     

            <!-- RIGHT COLUMN -->
            <div class="renders-features">
                <div class="render-box" id="threeDRender">
                    <?php if($threeD): ?>
                        <img src="<?php echo $threeD; ?>" alt="სართულის რენდერი">
                    <?php else: ?>
                        <div class="image-placeholder">სართულის რენდერი</div>
                    <?php endif; ?>
                </div>

    <div class="floor-plan-box" id="floorplan">
        <?php if($floorplan): ?>
            <img src="<?php echo $floorplan; ?>" alt="ბინის გეგმა">
        <?php else: ?>
            <div class="image-placeholder">ბინის გეგმა</div>
        <?php endif; ?>
    </div>

                <div class="project-description">
                    მულტიფუნქციური საცხოვრებელი კომპლექსი „პარკ ბულვარი" აერთიანებს ურბანულ 
                    კომფორტს, თანამედროვე ცხოვრების სტილს და ყველაზე დიდ რეკრეაციულ ზონას ქალაქში. 
                    იგი მდებარეობს ქალაქის ყველაზე მშვიდ ნაწილში, მდინარე მტკვრის სანაპიროზე.
                    კომპლექსი მოიცავს 17 საცხოვრებელ კორპუსს და 8 ჰექტრამდე ტერიტორიაზე განლაგებულ პარკს.
                </div>
            </div>
        </div>

 
    <!-- PAGE 2 -->
    <div class="page page-break">
        <div class="layout-title">სართულის რენდერი და ხედები</div>
        
        <div class="floor-layout" id="sartulinew">
            <?php if($sartulinew): ?>
                <img src="<?php echo $sartulinew; ?>" alt="სართულის განლაგება">
            <?php else: ?>
                <div class="image-placeholder" style="aspect-ratio: 16/9;">სართულის განლაგება</div>
            <?php endif; ?>
        </div>

        <div class="views-grid">
            <div class="view-box" id="xedi_1">
                <?php if($xedi_1): ?>
                    <img src="<?php echo $xedi_1; ?>" alt="ხედი 1">
                <?php else: ?>
                    <div class="image-placeholder" style="aspect-ratio: 4/3;">ხედი 1</div>
                <?php endif; ?>
            </div>
            
            <div class="view-box" id="xedi_2">
                <?php if($xedi_2): ?>
                    <img src="<?php echo $xedi_2; ?>" alt="ხედი 2">
                <?php else: ?>
                    <div class="image-placeholder" style="aspect-ratio: 4/3;">ხედი 2</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if($xedi_3): ?>
        <div class="views-grid" style="margin-top: 6mm;">
            <div class="view-box" id="xedi_3">
                <img src="<?php echo $xedi_3; ?>" alt="ხედი 3">
            </div>
        </div>
        <?php endif; ?>

        <div class="footer">
            <div style="font-size: 9pt; color: #999;">
                <strong>PARK BOULEVARD</strong> - ქალაქი სუნთქავს
            </div>
            <div style="text-align: right;">
                <strong>pb.ge</strong> | +995 591 165 555
            </div>
        </div>
    </div>

<script>
function formatNumber(num) {
    const options = { useGrouping: true };
    if (num % 1 !== 0) {
        options.minimumFractionDigits = 2;
        options.maximumFractionDigits = 2;
    }
    return num.toLocaleString('en', options);
}

document.addEventListener("DOMContentLoaded", function() {
        var containers = [
            document.querySelector('.workarea-content-paddings'),
            document.querySelector('#workarea-content'),
            document.querySelector('.bx-layout-inner-inner-cont')
        ];
        containers.forEach(function(el) {
            if (el) {
                el.style.padding = "0";
                el.style.margin = "0";
            }
        });
    });

let projectName = <?php echo json_encode($projectName); ?>;
let korpusi = <?php echo json_encode($korpusi); ?>;
let sadarbazo = <?php echo json_encode($sadarbazo); ?>;
let sartuli = <?php echo json_encode($sartuli); ?>;
let flatNum = <?php echo json_encode($flatNum); ?>;
let totalspace = <?php echo json_encode($totalspace); ?>;
let sacxovrebelifarti = <?php echo json_encode($sacxovrebelifarti); ?>;
let aivani = <?php echo json_encode($aivani); ?>;

let kvmdollar = Number(<?php echo json_encode($kvmdollar); ?>);
kvmdollar = Math.floor(kvmdollar);
let kvmdollarFormated = formatNumber(kvmdollar);

let kvmezo = Number(<?php echo json_encode($kvmezo); ?>);
kvmezo = Math.floor(kvmezo);
let kvmezoFormated = formatNumber(kvmezo);

let kvmterasa = Number(<?php echo json_encode($kvmterasa); ?>);
kvmterasa = Math.floor(kvmterasa);
let kvmterasaFormated = formatNumber(kvmterasa);

let totalprice = <?php echo json_encode($totalprice); ?>;
totalprice = Math.floor(totalprice);
let totalpriceFormated = formatNumber(totalprice);

let threeD = <?php echo json_encode($threeD); ?>;
let floorplan = <?php echo json_encode($floorplan); ?>;
let sartulinew = <?php echo json_encode($sartulinew); ?>;
let xedi_1 = <?php echo json_encode($xedi_1); ?>;
let xedi_2 = <?php echo json_encode($xedi_2); ?>;
let xedi_3 = <?php echo json_encode($xedi_3); ?>;

// Update dynamic content
if (document.getElementById("projectName")) {
    document.getElementById("projectName").innerText = projectName;
}

if (document.getElementById("kvmPrice")) {
    document.getElementById("kvmPrice").innerText = ${kvmdollarFormated};
}

if (document.getElementById("totalprice")) {
    document.getElementById("totalprice").innerText = `$ ${totalpriceFormated}`;
}

// Handle conditional fields
if (!korpusi) {
    document.getElementById("korpusiDiv").style.display = "none";
} else {
    document.getElementById("korpusi").innerText = korpusi;
}

if (!sadarbazo) {
    document.getElementById("sadarbazoDiv").style.display = "none";
} else {
    document.getElementById("sadarbazoDiv").style.display = "flex";
    document.getElementById("sadarbazo").innerText = sadarbazo;
}

if (!sartuli) {
    document.getElementById("sartuliDiv").style.display = "none";
} else {
    document.getElementById("sartuli").innerText = sartuli;
}

if (!flatNum) {
    document.getElementById("flatNumDiv").style.display = "none";
} else {
    document.getElementById("flatNum").innerText = flatNum;
}

if (!totalspace) {
    document.getElementById("totalspaceDiv").style.display = "none";
} else {
    document.getElementById("totalspace").innerText = `${totalspace} მ²`;
}

// Update images dynamically if needed via JavaScript
if (threeD && document.getElementById("threeDRender")) {
    document.getElementById("threeDRender").innerHTML = `<img src='${threeD}' alt='სართულის რენდერი'>`;
}

if (floorplan && document.getElementById("floorplan")) {
    document.getElementById("floorplan").innerHTML = `<img src='${floorplan}' alt='ბინის გეგმა'>`;
}

if (sartulinew && document.getElementById("sartulinew")) {
    document.getElementById("sartulinew").innerHTML = `<img src='${sartulinew}' alt='სართულის განლაგება'>`;
}

if (xedi_1 && document.getElementById("xedi_1")) {
    document.getElementById("xedi_1").innerHTML = `<img src='${xedi_1}' alt='ხედი 1'>`;
}

if (xedi_2 && document.getElementById("xedi_2")) {
    document.getElementById("xedi_2").innerHTML = `<img src='${xedi_2}' alt='ხედი 2'>`;
}

if (xedi_3 && document.getElementById("xedi_3")) {
    document.getElementById("xedi_3").innerHTML = `<img src='${xedi_3}' alt='ხედი 3'>`;
}
</script>

</body>
</html>
