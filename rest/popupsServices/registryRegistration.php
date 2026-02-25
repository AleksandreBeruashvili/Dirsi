<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");


global $USER;
$currentUserId = $USER->GetID();

if (!CModule::IncludeModule('crm')) {
    echo json_encode(['status' => 'error', 'message' => 'CRM module not installed']);
    die();
}

if (!CModule::IncludeModule('bizproc')) {
    echo json_encode(['status' => 'error', 'message' => 'Bizproc module not installed']);
    die();
}

header('Content-Type: application/json');

$dealId         = isset($_POST['DEAL_ID'])         ? intval($_POST['DEAL_ID'])         : 0;
$contractType   = isset($_POST['contract_type'])   ? trim($_POST['contract_type'])     : '';
$registryStatus = $_POST['registry_status'] ?? '';
$registryDate   = isset($_POST['registry_date'])   ? trim($_POST['registry_date'])     : '';
$registryStatusString   = isset($_POST['registry_status_string'])   ? trim($_POST['registry_status_string'])     : '';
$fullPrice        = isset($_POST['full_price'])         ? intval($_POST['full_price'])         : 0;

if (!$dealId || !$contractType || $registryDate === '' || $registryStatus === '') {
    echo json_encode(['status' => 'error', 'message' => 'მონაცემები არასრულია']);
    die();
}

$arErrorsTmp = [];
CBPDocument::StartWorkflow(
    107,
    ["crm", "CCrmDocumentDeal", "DEAL_$dealId"],
    [
        "contractType"   => $contractType,
        "registryStatus" => $registryStatus,
        "registryStatusString" => $registryStatusString,
        "registryDate"   => $registryDate,
        "fullPrice"  => $fullPrice,
        "TargetUser" => "user_" . $currentUserId,

    ],
    $arErrorsTmp
);

if (!empty($arErrorsTmp)) {
    echo json_encode(["status" => "error", "errors" => $arErrorsTmp]);
    die();
}

echo json_encode(["status" => "success"]);