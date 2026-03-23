<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

CJSCore::Init(["jquery"]);
set_time_limit(0);

$APPLICATION->SetTitle("Deal → Product Auto Bind (Minimal)");

/* ================= HELPERS ================= */

function printArr ($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function normalizeBlock($block)
{
    // A12 → A-12
    if (preg_match('/^([A-Za-z]+)(\d+)$/', trim($block), $m)) {
        return $m[1] . '-' . $m[2];
    }
    return trim($block);
}

function getDealsByFilter(
    $arFilter,
    $arSelect = [
        "ID",
        "DATE_CREATE",
        "UF_CRM_1766560580335", // floor
        "UF_CRM_1766421158035", // building number
        "UF_CRM_1774254583", // number
        "UF_CRM_1766652554644", // product type
        "ASSIGNED_BY_ID",
        "CONTACT_ID",
        "COMPANY_ID",
    ],
    $arSort = ["ID" => "ASC"]
){
    $result = [];
    $res = CCrmDeal::GetList($arSort, $arFilter, $arSelect);
    while ($row = $res->Fetch()) {
        $result[] = $row;
    }
    return $result;
}

/**
 * პროდუქტის პოვნა დილის ველებით
 * block: A12 (დილზე) → A-12 (პროდუქტზე)
 */
function getProductByDealFields($buildingNumber, $type, $floor, $number)
{

    $filter = [
        "IBLOCK_ID" => 14,
        "IBLOCK_SECTION_ID" => 33,
        "PROPERTY_BUILDING" => $buildingNumber,
        "PROPERTY_PRODUCT_TYPE" => $type,
        "PROPERTY_FLOOR" => $floor,
        "PROPERTY_Number" => $number,
        "ACTIVE" => "Y"
    ];

    $res = CIBlockElement::GetList([], $filter, false, ["nTopCount" => 1], ["ID"]);
    if ($el = $res->Fetch()) {
        return (int)$el["ID"];
    }
    return false;
}

/**
 * მხოლოდ საჭირო ველების წამოღება პროდუქტიდან
 */
function getCIBlockElementsByID($productId)
{
    if (!$productId) return [];

    $arSelect = [
        "ID",
        "PROPERTY_PROJECT",
        "PROPERTY_KORPUSIS_NOMERI_XE3NX2",
        "PROPERTY_BUILDING",
        "PROPERTY_FLOOR",
        "PROPERTY_Number",
        "PROPERTY_TOTAL_AREA",
        "PROPERTY_LIVING_SPACE",
        "PROPERTY_Bedrooms",
        "PROPERTY_PRODUCT_TYPE",
        "PROPERTY_KVM_PRICE"
    ];

    $res = CIBlockElement::GetList(
        [],
        ["ID" => (int)$productId, "ACTIVE" => "Y"],
        false,
        false,
        $arSelect
    );

    if (!$el = $res->GetNext()) {
        return [];
    }

    // CPrice-იდან ფასის წამოღება
    $price = 0;
    $priceRes = CPrice::GetList(
        ["ID" => "ASC"],
        ["PRODUCT_ID" => (int)$productId]
    );
    if ($priceRow = $priceRes->Fetch()) {
        $price = (float)$priceRow["PRICE"];
    }

    return [
        "ID"            => (int)$el["ID"],
        "PROJECT"       => $el["PROPERTY_PROJECT_VALUE"],
        "BUILDING"      => $el["PROPERTY_BUILDING_VALUE"],
        "Bedrooms"      => $el["PROPERTY_BEDROOMS_VALUE"],
        "PRODUCT_TYPE"  => $el["PROPERTY_PRODUCT_TYPE_VALUE"],
        "KORPUS"        => $el["PROPERTY_KORPUSIS_NOMERI_XE3NX2_VALUE"],
        "FLOOR"         => $el["PROPERTY_FLOOR_VALUE"],
        "Number"        => $el["PROPERTY_NUMBER_VALUE"],
        "TOTAL_AREA"    => (float)$el["PROPERTY_TOTAL_AREA_VALUE"],
        "LIVING_SPACE"  => (float)$el["PROPERTY_LIVING_SPACE_VALUE"],
        "PRICE"         => $price,
        "KVM_PRICE"     => (float)$el["PROPERTY_KVM_PRICE_VALUE"]
    ];
}

/* ================= MAIN ================= */


$arFilter = [
    "STAGE_ID" => "WON",
    // In this codebase filters are built as 'd/m/Y HH:MM:SS' to satisfy Bitrix parsing.
    ">=DATE_CREATE" => date('d/m/Y', strtotime("2026-03-23")) . ' 00:00:00',
    "<=DATE_CREATE" => date('d/m/Y', strtotime("2026-03-23")) . ' 23:59:59',
    // "ID" => 5340 
];

$deals = getDealsByFilter($arFilter);

// printArr($deals);

printArr(count($deals));

foreach ($deals as $deal) {

    $dealId    = (int)$deal["ID"];


    $buildingNumber = $deal["UF_CRM_1766421158035"];
    $type      = $deal["UF_CRM_1766652554644"];
    $floor     = $deal["UF_CRM_1766560580335"];
    $number     = $deal["UF_CRM_1774254583"];
   
    // printArr("ტესტ");

    $productId = getProductByDealFields($buildingNumber, $type, $floor, $number);
    if (!$productId) {
        continue;
    }

    // $productData = getCIBlockElementsByID($productId);
    // if (empty($productData)) {
    //     continue;
    // }

    $propertyValues = array();
    $propertyValues['OWNER_DEAL'] = $dealId;
    $propertyValues['DEAL_RESPONSIBLE'] = $deal["ASSIGNED_BY_ID"];
    $propertyValues['OWNER_CONTACT'] = $deal["CONTACT_ID"];
    $propertyValues['OWNER_COMPANY'] = $deal["COMPANY_ID"];
    $propertyValues['STATUS'] = "გაყიდული";

    $element = new CIBlockElement();

    $updateResult = $element->SetPropertyValuesEx($productId, 14, $propertyValues);


    // $ProductID = $productId;

    // $arLoadProductArray = array(
    //     "PROPERTY_VALUES" => $productData,
    //     "NAME" => $productData["NAME"],
    //     "ACTIVE" => "Y",
    // );


    // if($dealId && $productData){
    //     $arLoadProductArray["PROPERTY_VALUES"]["STATUS"] = "გაყიდული";
    //     $arLoadProductArray["PROPERTY_VALUES"]["OWNER_DEAL"] = $dealId;
    //     $arLoadProductArray["PROPERTY_VALUES"]["DEAL_RESPONSIBLE"] = $deal["ASSIGNED_BY_ID"];
    //     $arLoadProductArray["PROPERTY_VALUES"]["OWNER_CONTACT"] = $deal["CONTACT_ID"];
    //     $arLoadProductArray["PROPERTY_VALUES"]["OWNER_COMPANY"] = $deal["COMPANY_ID"];
    // }
    
        
    // $el = new CIBlockElement;
    // $res = $el->Update($ProductID, $arLoadProductArray);



    // $rows = [[
    //     "PRODUCT_ID" => $productId,
    //     "PRICE" => $productData["PRICE"],
    //     "QUANTITY" => 1
    // ]];

    // if (!CCrmDeal::SaveProductRows($dealId, $rows)) {
    //     continue;
    // }

    // $arrForAdd = [
    //     "UF_CRM_1761658516561" => $productData["PROJECT"],
    //     "UF_CRM_1761658532158" => $productData["PRODUCT_TYPE"],
    //     "UF_CRM_1766736693236" => $productData["BUILDING"],
    //     "UF_CRM_1766560177934" => $productData["KORPUS"],
    //     "UF_CRM_1761658559005" => $productData["Number"],
    //     "UF_CRM_1761658577987" => $productData["FLOOR"],
    //     "UF_CRM_1761658608306" => $productData["TOTAL_AREA"],
    //     "UF_CRM_1761658765237" => $productData["LIVING_SPACE"],
    //     "UF_CRM_1770888201367" => $productData["Bedrooms"],
    //     "UF_CRM_1761658503260" => $productData["KVM_PRICE"],
    // ];

    // $Deal = new CCrmDeal();
    // if (!$Deal->Update($dealId, $arrForAdd)) {
    //     continue;
    // }
}

echo "<h3>დამუშავება დასრულდა</h3>";

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
