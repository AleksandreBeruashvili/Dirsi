<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
header('Content-Type: application/json; charset=utf-8');

if (!CModule::IncludeModule("crm")) {
    echo json_encode(["status" => "error", "message" => "CRM module not loaded"]);
    exit;
}

global $USER;
$currentUserId = (int)$USER->GetID();
$dealId = (int)$_POST["dealId"];

if (!$dealId) {
    echo json_encode(["status" => "error", "message" => "Deal ID not provided"]);
    exit;
}

function popupsServicesAgreementCancellationFetchDeal($dealId)
{
    $res = CCrmDeal::GetList(["ID" => "ASC"], ["ID" => $dealId], []);
    if ($arDeal = $res->Fetch()) {
        return $arDeal;
    }
    return false;
}

$deal = popupsServicesAgreementCancellationFetchDeal($dealId);
if (!$deal) {
    echo json_encode(["status" => "error", "message" => "Deal not found"]);
    exit;
}

$comment = trim((string)$_POST["comment"]);
$agreementFile = trim((string)$_POST["agreementFile"]);

if ($comment === '' || $agreementFile === '') {
    echo json_encode(["status" => "error", "message" => "Comment and file are required"]);
    exit;
}

$arErrorsTmp = [];
CBPDocument::StartWorkflow(
    114,
    ["crm", "CCrmDocumentDeal", "DEAL_$dealId"],
    [
        "Parameter1" => $comment,
        "Parameter2" => $agreementFile,
        "TargetUser" => "user_" . $currentUserId
    ],
    $arErrorsTmp
);

if (!empty($arErrorsTmp)) {
    echo json_encode(["status" => "error", "message" => "Workflow error", "errors" => $arErrorsTmp]);
    exit;
}

echo json_encode(["status" => "success", "message" => "Agreement cancellation submitted successfully"]);
exit;
