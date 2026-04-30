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

// --- ფუნქციები (არ ემთხვევა bp_workflow_functions.php getDealInfoByID-ს) ---
function popupsServicesAgreementCheckFetchDeal($dealId)
{
    $res = CCrmDeal::GetList(["ID" => "ASC"], ["ID" => $dealId], []);
    if ($arDeal = $res->Fetch()) {
        return $arDeal;
    }
    return false;
}

// --- ძირითადი ლოგიკა ---
$deal = popupsServicesAgreementCheckFetchDeal($dealId);
if (!$deal) {
    echo json_encode(["status" => "error", "message" => "Deal not found"]);
    exit;
}

$contactType = trim($_POST["contactType"]);
$contactTypeStr = "";

if($contactType == "174"){
    $contactTypeStr = "სტანდარტული";
}else if($contactType == "175"){
    $contactTypeStr = "არასტანდარტული";
}

$agreementFile = trim($_POST["agreementFile"]);
$identityFile = trim($_POST["identityFile"]);
$comment = trim($_POST["comment"]);
$agreementNumber = trim($_POST["agreementNumber"]);

if (!preg_match('/^PB\/SALES\/\d+$/', $agreementNumber)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid agreement number format. Use: PB/SALES/123"
    ]);
    exit;
}

if($dealId){
    $arErrorsTmp = array();
    
    // Update deal fields if needed
    $dealFields = array();
    if ($contactType) {
        $dealFields["UF_CRM_1770204855111"] = $contactType;
    }
    
    if (!empty($dealFields)) {
        $dealObj = new CCrmDeal(false);
        $dealObj->Update($dealId, $dealFields, true, true, array("CURRENT_USER" => $currentUserId));
    }
    
    // Start workflow with the form data
    $wfId = CBPDocument::StartWorkflow(
        103, // Workflow ID - adjust if you have a different workflow for agreement check
        ["crm", "CCrmDocumentDeal", "DEAL_$dealId"],
        [
            "contactType" => $contactType,
            "contactTypeStr" => $contactTypeStr,
            "agreementFile" => $agreementFile,
            "identityFile" => $identityFile,
            "comment" => $comment,
            "agreementNumber" => $agreementNumber,
            "TargetUser" => "user_" . $currentUserId
        ],
        $arErrorsTmp
    );

    if (!empty($arErrorsTmp)) {
        echo json_encode(["status" => "error", "message" => "Workflow error", "errors" => $arErrorsTmp]);
        exit;
    }
}

echo json_encode(["status" => "success", "message" => "Agreement check submitted successfully"]);
exit;

