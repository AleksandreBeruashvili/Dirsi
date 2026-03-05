<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("test");

function printArr($arr){
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
}


function getDealsByFilter($arFilter, $arSelect = array(), $arSort = array("ID"=>"ASC")) {
    $arDeals = array();

    if(empty($arSelect)){
        $arSelect = array("ID");
    }
    $res = CCrmDeal::GetList($arSort, $arFilter,$arSelect);
    while($arDeal = $res->Fetch()) array_push($arDeals, $arDeal);
    return (count($arDeals) > 0) ? $arDeals : false;
}

function getDealProducts($dealId) {
    $products = CCrmDeal::LoadProductRows($dealId);
    return $products;
}

function getProductDataByID($prodId) {
    $bedrooms = null;
    $res = CIBlockElement::GetList(array("ID" => "ASC"), array("ID" => $prodId), false, array("nPageSize" => 1), array("ID", "IBLOCK_ID", "NAME", "PROPERTY_Bedrooms"));
    while($ob = $res->GetNextElement()){
        $arProps = $ob->GetProperties(); 
        // printArr($arProps);
        if (!empty($arProps["Bedrooms"]["VALUE"])) {
            $bedrooms = $arProps["Bedrooms"]["VALUE"];
        }
    }
    return $bedrooms;
}


$arFilter = [
    "STAGE_ID" => "WON"
    // "ID"       => 1560
];

$deals = getDealsByFilter($arFilter);

// printArr(count($deals));

if(empty($deals)){
    echo "Deals not found";
    return;
}

$updateArr = array();

foreach($deals as $deal){

    $dealId = (int)$deal["ID"];


    $products = getDealProducts($dealId);

    foreach($products as $product){

        $prodId=$product["PRODUCT_ID"];
        $bedrooms = getProductDataByID($prodId);
        if(!empty($bedrooms)){
            $updateArr["UF_CRM_1770888201367"] = $bedrooms;
        }

    }


    // printArr($updateArr);

    // ---------- UF UPDATE ----------

    if (!empty($updateArr)) {
        $dealObj = new CCrmDeal(false);

        $result = $dealObj->Update(
            $dealId,
            $updateArr,
            false,
            false,
            ["CHECK_PERMISSIONS" => false]
        );

        if(!$result){
            echo "Update Error: ".$dealObj->LAST_ERROR."<br>";
        }else{
            echo "Deal ".$dealId." updated<br>";
        }
    } else {
        echo "Deal ".$dealId." - no contract signing date found<br>";
    }
}
?>