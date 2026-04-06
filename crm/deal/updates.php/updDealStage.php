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
    "STAGE_ID" =>["NEW", "PREPARATION", "PREPAYMENT_INVOICE","UC_12CJ1Z", "UC_2EW8VW", "UC_15207E", "UC_BAUB5P", "UC_F3FOBF", "EXECUTING", "FINAL_INVOICE"],
];

$deals = getDealsByFilter($arFilter);

printArr(count($deals));

if(empty($deals)){
    echo "Deals not found";
    return;
}

foreach($deals as $deal){

    $dealId = (int)$deal["ID"];

    // ---------- UF UPDATE ----------
    $updateArr = array();

    $updateArr["UF_CRM_1775462387"] = "330";

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