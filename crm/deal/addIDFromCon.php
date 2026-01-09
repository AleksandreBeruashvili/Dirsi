<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

CModule::IncludeModule("catalog"); 
CJSCore::Init(["jquery"]);


function printArr ($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDealsByFilter(
    $arFilter,
    $arSelect = ["ID", "COMPANY_ID", "CONTACT_ID"],
    $arSort = ["ID" => "ASC"]
){
    $result = [];
    $res = CCrmDeal::GetList($arSort, $arFilter, $arSelect);
    while ($row = $res->Fetch()) {
        $result[] = $row;
    }
    return $result;
}


function getContactInfo($id) {
    $arSelect = array("ID","UF_CRM_1761651998145","UF_CRM_1761652010097");
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID"=>$id), $arSelect);
    if($arContact = $res->Fetch()){
        return $arContact;
    }
    return false;
}

function getCompanyInfo($id) {
    $arSelect = array(
        "ID",
        "UF_CRM_1762421912281"
    );

    $res = CCrmCompany::GetList(
        array("ID" => "ASC"),
        array("ID" => $id),
        false,
        false,
        $arSelect
    );

    if ($arCompany = $res->Fetch()) {
        return $arCompany;
    }

    return false;
}


function getDealInfo($dealID) {
    $arDeal = array();
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), array());
    if($arDeal = $res->Fetch()){
        return $arDeal;
    }
    return $arDeal;
}


$root=$this->GetRootActivity();
$dealID     = intval($root->GetVariable("deal_id"));

if($dealID) {
    $deal = getDealInfo($dealID);

    if($deal) {
        $contact = [];
        $company = [];
        $identificatonNumber = "";

        if ($deal["CONTACT_ID"]) {
            $contact = getContactInfo($deal["CONTACT_ID"]);
            if ($contact["UF_CRM_1761651998145"]) {
                $identificatonNumber = $contact["UF_CRM_1761651998145"];
            }else{
                $identificatonNumber = $contact["UF_CRM_1761652010097"];
            }

        }elseif($deal["COMPANY_ID"]) {
            $company = getCompanyInfo($deal["COMPANY_ID"]);
            if ($company["UF_CRM_1762421912281"]) {
                $identificatonNumber = $company["UF_CRM_1762421912281"];
            }
        }

        if(!empty($identificatonNumber) && empty($deal["UF_CRM_1767096218068"])) {
            $Deal = new CCrmDeal();

            $arUpdateFields = [
                "UF_CRM_1767096218068" => $identificatonNumber
            ];
            
            $Deal->Update($dealID, $arUpdateFields);
        }

    }
    
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");