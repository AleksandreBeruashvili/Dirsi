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


$arFilter = [
    "STAGE_ID" => "WON",
    // "ID"       => 1663
];

$deals = getDealsByFilter($arFilter);

// printArr(count($deals));

if(empty($deals)){
    echo "Deals not found";
    return;
}

foreach($deals as $deal){

    $dealId = (int)$deal["ID"];

    // ---------- Get first element from list 20 (TARIGI) ----------
    $contractSigningDate = "";
    $arFilter_list20 = array(
        "IBLOCK_ID" => 20,
        "PROPERTY_DEAL" => $dealId,
    );
    $arSelect = array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $res = CIBlockElement::GetList(array("PROPERTY_TARIGI" => "ASC"), $arFilter_list20, false, Array("nPageSize" => 1), $arSelect);
    if ($ob = $res->GetNextElement()) {
        $arProps = $ob->GetProperties();
        if (!empty($arProps["TARIGI"]["VALUE"])) {
            $contractSigningDate = $arProps["TARIGI"]["VALUE"]; 
        }
    }
    // printArr($dealId);
    // printArr($contractSigningDate);

    // ---------- UF UPDATE ----------
    $updateArr = array();

    if (!empty($contractSigningDate)) {
        $updateArr["UF_CRM_1762416342444"] = $contractSigningDate;
    }

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