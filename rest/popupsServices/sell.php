<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
header('Content-Type: application/json; charset=utf-8');

// CRM მოდულის ჩატვირთვა
if (!CModule::IncludeModule("crm")) {
    echo json_encode(["status" => "error", "message" => "CRM module not loaded"]);
    exit;
}

global $USER;
$currentUserId = $USER->GetID();

// Deal ID აუცილებელია
$dealId = intval($_POST["dealId"]);
if (!$dealId) {
    echo json_encode(["status" => "error", "message" => "Deal ID not provided"]);
    exit;
}

// --- ფუნქციები ---
function getDealInfoByID($dealId)
{
    $res = CCrmDeal::GetList(["ID" => "ASC"], ["ID" => $dealId], []);
    if ($arDeal = $res->Fetch()) {
        return $arDeal;
    }
    return false;
}

// --- ძირითადი ლოგიკა ---
$deal = getDealInfoByID($dealId);
if (!$deal) {
    echo json_encode(["status" => "error", "message" => "Deal not found"]);
    exit;
}


$contractDate = trim($_POST["contractDate"]);
$sellFlatFile = trim($_POST["sellFlatFile"]);
$sellAttachFile = trim($_POST["sellAttachFile"]); 
$clientDesc = trim($_POST["clientDesc"]); 



if($dealId){
    $arErrorsTmp = array();
    $wfId = CBPDocument::StartWorkflow(
        19,
        ["crm", "CCrmDocumentDeal", "DEAL_$dealId"],
        [
            "contractDate" => $contractDate,
            "sellFlatFile" => $sellFlatFile,
            "sellAttachFile" => $sellAttachFile,
            "clientDesc" => $clientDesc,
            "TargetUser" => "user_" . $currentUserId
        ],
        $arErrorsTmp
    );

    if (!empty($arErrorsTmp)) {
        echo json_encode(["status" => "error", "errors" => $arErrorsTmp]);
        exit;
    }
}


echo json_encode(["status" => "success", "message" => "Contact saved successfully"]);
exit;

