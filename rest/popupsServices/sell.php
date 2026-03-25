<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
header('Content-Type: application/json; charset=utf-8');

// CRM მოდულის ჩატვირთვა
if (!CModule::IncludeModule("crm")) {
    echo json_encode(["status" => "error", "message" => "CRM module not loaded"]);
    exit;
}

if (!CModule::IncludeModule("bizproc")) {
    echo json_encode(["status" => "error", "message" => "Bizproc module not loaded"]);
    exit;
}

if (!CModule::IncludeModule("iblock")) {
    echo json_encode(["status" => "error", "message" => "Iblock module not loaded"]);
    exit;
}

global $USER;
$currentUserId = $USER->GetID();

// Deal ID აუცილებელია (ფორმა იგზავნება dealId ან DEAL_ID-ით)
$dealId = intval($_POST["dealId"] ?? $_POST["DEAL_ID"] ?? 0);
if (!$dealId) {
    echo json_encode(["status" => "error", "message" => "Deal ID not provided"]);
    exit;
}

// --- ფუნქციები (სახელი უნდა განსხვავდებოდეს functions/bp_workflow_functions.php-ის getDealInfoByID-სგან) ---
function popupsServicesSellFetchDeal($dealId)
{
    $res = CCrmDeal::GetList(["ID" => "ASC"], ["ID" => $dealId], []);
    if ($arDeal = $res->Fetch()) {
        return $arDeal;
    }
    return false;
}

// --- ძირითადი ლოგიკა ---
$deal = popupsServicesSellFetchDeal($dealId);
if (!$deal) {
    echo json_encode(["status" => "error", "message" => "Deal not found"]);
    exit;
}


$postStr = function ($key) {
    $v = isset($_POST[$key]) ? $_POST[$key] : '';
    return trim(is_string($v) ? $v : (string)$v);
};

$contractDate = $postStr("contractDate");
$sellFlatFile = $postStr("sellFlatFile");
$sellAttachFile = $postStr("sellAttachFile");
$clientDesc = $postStr("clientDesc");

$phone = $postStr("phone");
$email = $postStr("email");
$personalId = $postStr("personalId");
$passportId = $postStr("passportId");
$legalAddress = $postStr("legalAddress");
$actualAddress = $postStr("actualAddress");
$citizenshipType = $postStr("citizenshipType");
$citizenOf = $postStr("citizenOf");
$nationality = $postStr("nationality");

$nameRU = $postStr("nameRU");
$legalAddressRU = $postStr("legalAddressRU");
$actualAddressRU = $postStr("actualAddressRU");
$nameENG = $postStr("nameENG");
$legalAddressENG = $postStr("legalAddressENG");
$actualAddressENG = $postStr("actualAddressENG");

$miznobrioba = $postStr("miznobrioba");
$contactType = $postStr("contactType");

$registrationInRest = $postStr("registrationInRest");
$keytReceived = $postStr("keytReceived");
$barter = $postStr("barter");

if ($dealId) {
    $arErrorsTmp = array();
    try {
        CBPDocument::StartWorkflow(
            19,
            ["crm", "CCrmDocumentDeal", "DEAL_$dealId"],
            [
                "contractDate" => $contractDate,
                "sellFlatFile" => $sellFlatFile,
                "sellAttachFile" => $sellAttachFile,
                "clientDesc" => $clientDesc,
                "phone" => $phone,
                "email" => $email,
                "personalId" => $personalId,
                "passportId" => $passportId,
                "legalAddress" => $legalAddress,
                "actualAddress" => $actualAddress,
                "citizenshipType" => $citizenshipType,
                "citizenOf" => $citizenOf,
                "nationality" => $nationality,
                "nameRU" => $nameRU,
                "legalAddressRU" => $legalAddressRU,
                "actualAddressRU" => $actualAddressRU,
                "nameENG" => $nameENG,
                "legalAddressENG" => $legalAddressENG,
                "actualAddressENG" => $actualAddressENG,
                "miznobrioba" => $miznobrioba,
                "contactType" => $contactType,
                "registrationInRest" => $registrationInRest,
                "keytReceived" => $keytReceived,
                "TargetUser" => "user_" . (int)$currentUserId
            ],
            $arErrorsTmp
        );
    } catch (\Throwable $e) {
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage(),
        ]);
        exit;
    }

    if (!empty($arErrorsTmp)) {
        echo json_encode(["status" => "error", "message" => "Workflow error", "errors" => $arErrorsTmp]);
        exit;
    }


    if ($barter === "1" || $barter === "0") {
        // ინფობლოკი 14, თვისება BARTER (სია): yes=188, no=189 — დილის UF იგზავნება "1"/"0"
        $barterListEnumId = ($barter === "1") ? 188 : 189;

        $updateArr = array();
        if (!empty($barter)) {
            $updateArr["UF_CRM_1774442641"] = $barter;

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

        }

        if ($dealId) {
            $productRows = CCrmDeal::LoadProductRows($dealId);
            if (is_array($productRows)) {
                foreach ($productRows as $row) {
                    $productId = (int)($row["PRODUCT_ID"] ?? 0);
                    if ($productId <= 0) {
                        continue;
                    }
            
                    $propertyValues = array();
                    $propertyValues['BARTER'] = $barterListEnumId;
                
                    $element = new CIBlockElement();
                
                    $updateResult = $element->SetPropertyValuesEx($productId, 14, $propertyValues);
                }
            }
        }
    }

}


echo json_encode(["status" => "success", "message" => "Contact saved successfully"]);
exit;