<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Title");

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}


function getSourceNameById($sourceId) {
    $list = CCrmStatus::GetStatusList('SOURCE');
    return $list[$sourceId] ?? null;
}

function getUfEnumIdByName($fieldCode, $searchName) {
    $rsEnum = CUserFieldEnum::GetList([], [
        "USER_FIELD_NAME" => $fieldCode
    ]);

    while ($enum = $rsEnum->Fetch()) {
        if (trim($enum["VALUE"]) === trim($searchName)) {
            return $enum["ID"];
        }
    }

    return null;
}


function getDealsByFilter($arFilter) {
    $arDeals = array();

    $res = CCrmDeal::GetListEx(
        array("DATE_CREATE" => "ASC"), // ყველაზე ძველი პირველია
        $arFilter,
        false,
        array("nPageSize" => 1), // მხოლოდ 1 Deal
        array("ID", "TITLE", "CATEGORY_ID", "ASSIGNED_BY_NAME", "ASSIGNED_BY_LAST_NAME", "SOURCE_ID", "DATE_CREATE", "UF_CRM_1763356180625")
    );

    if ($arDeal = $res->Fetch()) {
        return $arDeal; // ერთი ყველაზე ძველი Deal
    }

    return null;
}

function ckeckDeals($mobileNumber, $dealId) {
    $mobileNumber = substr($mobileNumber, -9);

    $dbFieldMulti = \CCrmFieldMulti::GetList(
        [],
        ['ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', "%VALUE" => $mobileNumber]
    );

    while ($info = $dbFieldMulti->Fetch()) {

        if (!empty($info["ELEMENT_ID"])) {

            $resDeal = getDealsByFilter([
                "!ID" => $dealId,
                "CONTACT_ID" => $info["ELEMENT_ID"],
                "CHECK_PERMISSIONS" => "N",
            ]);

            $resDealLast = getDealsByFilter([
                "ID" => $dealId,
                "CONTACT_ID" => $info["ELEMENT_ID"],
                "CHECK_PERMISSIONS" => "N",
            ]);

            $lastDealSourceId = $resDealLast ? $resDealLast["SOURCE_ID"] : null;

            if ($resDeal) {
                return [
                    "ID" => $resDeal["ID"],
                    "TITLE" => $resDeal["TITLE"],
                    "SOURCE_ID" => $resDeal["SOURCE_ID"],
                    "DATE_CREATE" => $resDeal["DATE_CREATE"],
                    "PHONE" => $info["VALUE"],
                    "LAST_SOURCE_ID" => $lastDealSourceId,
                ];
            }
        }
    }

    return null;
}


$root   = $this->GetRootActivity();
$dealId = $root->GetVariable("dealId");
$rawPhone = $root->GetVariable("phone");
// $dealId =1961;
// $rawPhone ="+995 551 52 82 07";

$cleanedPhone = preg_replace('/\D/', '', $rawPhone);

if (strlen($cleanedPhone) >= 9) {
    $searchPhone = substr($cleanedPhone, -10); 

    $resDeals = ckeckDeals($searchPhone, $dealId);
    if($resDeals){
        $sourceId = $resDeals["SOURCE_ID"];

        $lastSourceId = $resDeals["LAST_SOURCE_ID"] ?? null;

        $sourceName = $lastSourceId ? getSourceNameById($lastSourceId) : null;
        
        $ufEnumId = $sourceName 
            ? getUfEnumIdByName("UF_CRM_1763356180625", $sourceName) 
            : null;
          
        $Deal = new CCrmDeal();
        $arrForAdd ["SOURCE_ID"] = $sourceId;   
        if($ufEnumId){
            $arrForAdd ["UF_CRM_1763356180625"] = $ufEnumId;
        }
        $result = $Deal->Update($dealId, $arrForAdd);

    }
}
