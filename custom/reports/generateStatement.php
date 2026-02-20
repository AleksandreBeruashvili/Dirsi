<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('crm');
CModule::IncludeModule('iblock');

// ============================================================
//  generateStatement.php — განცხადების Word გენერატორი
//  გამოყენება: generateStatement.php?deal_id=123
// ============================================================

// ---------- პარამეტრები ----------
$deal_ID = (int)$_GET["deal_id"];

if (!$deal_ID) {
    die("მონაცემები არასრულია. საჭიროა: deal_id");
}

// ---------- ქართულიდან ლათინურად ----------
function transliterateGeo_st($str) {
    $map = [
        "ქ" => "q", "წ" => "ts", "ჭ" => "ch", "ე" => "e", "რ" => "r", "ღ" => "gh",
        "ტ" => "t", "თ" => "t", "ყ" => "y", "უ" => "u", "ი" => "i", "ო" => "o",
        "პ" => "p", "ა" => "a", "ს" => "s", "შ" => "sh", "დ" => "d", "ფ" => "p",
        "გ" => "g", "ჰ" => "h", "ჯ" => "j", "ჟ" => "zh", "კ" => "k", "ლ" => "l",
        "ზ" => "z", "ხ" => "x", "ძ" => "dz", "ც" => "c", "ჩ" => "ch", "ვ" => "v",
        "ბ" => "b", "ნ" => "n", "მ" => "m"
    ];

    $result = '';
    $chars = mb_str_split($str);
    foreach ($chars as $char) {
        if (isset($map[$char])) {
            $result .= $map[$char];
        } elseif (isset($map[mb_strtolower($char)])) {
            $result .= ucfirst($map[mb_strtolower($char)]);
        } else {
            $result .= $char;
        }
    }

    return mb_convert_case($result, MB_CASE_TITLE, 'UTF-8');
}

function isGeorgian_st($text) { return preg_match('/[\x{10A0}-\x{10FF}]/u', $text); }
function isRussian_st($text) { return preg_match('/[\x{0400}-\x{04FF}]/u', $text); }

// ---------- დილის ინფორმაცია ----------
function getDealInfoByID_st($dealID, $arrSelect = array()) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), $arrSelect);
    if ($arDeal = $res->Fetch()) return $arDeal;
    return [];
}

function getContactName_st($id) {
    $res = CCrmContact::GetList(["ID" => "ASC"], ["ID" => $id], ["ID", "FULL_NAME"]);
    if ($arContact = $res->Fetch()) return $arContact["FULL_NAME"];
    return "";
}

// ---------- ჯამური გადახდები ----------
function getTotalPayments_st($deal_ID) {
    $total = 0;
    $arFilter = array("IBLOCK_ID" => 21, "PROPERTY_DEAL" => $deal_ID);
    $arSelect = Array("ID", "IBLOCK_ID", "PROPERTY_*");
    $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize" => 99999), $arSelect);

    while ($ob = $res->GetNextElement()) {
        $arProps = $ob->GetProperties();
        $amount = (float)str_replace("|USD", "", $arProps["TANXA"]["VALUE"]);
        $refund = $arProps["refund"]["VALUE"];

        if ($refund == "YES") {
            $total -= $amount;
        } else {
            $total += $amount;
        }
    }

    return round($total, 2);
}

// ---------- მონაცემები ----------
$dealData    = getDealInfoByID_st($deal_ID);
$contactId   = $dealData["CONTACT_ID"];
$contact     = getContactName_st($contactId);
$project     = $dealData["UF_CRM_1761658516561"];
$floor       = $dealData["UF_CRM_1761658577987"];
$number      = $dealData["UF_CRM_1761658559005"];
$xelshNom    = $dealData["UF_CRM_1766563053146"];
$totalArea   = $dealData["UF_CRM_1761658608306"];
$block       = $dealData["UF_CRM_1766560177934"];
$totalPrice  = (float)$dealData["OPPORTUNITY"];

// კონტაქტის ინფო
$contactPN = "";
$contactLabel = "";
$contactLabelEng = "";
$resContact = CCrmContact::GetList(["ID" => "ASC"], ["ID" => $contactId]);
if ($arC = $resContact->Fetch()) {
    if (!empty($arC["UF_CRM_1761651998145"])) {
        $contactPN = $arC["UF_CRM_1761651998145"];
        $contactLabel = "პ.ნ.";
        $contactLabelEng = "P.N";
    } elseif (!empty($arC["UF_CRM_1761652010097"])) {
        $contactPN = $arC["UF_CRM_1761652010097"];
        $contactLabel = "პასპორტი";
        $contactLabelEng = "Passport";
    }
}

// სახელის ენა
if (isGeorgian_st($contact)) {
    $contactNameLine1 = $contact;
    $contactNameLine2 = transliterateGeo_st($contact);
} elseif (isRussian_st($contact)) {
    $contactNameLine1 = "";
    $contactNameLine2 = $contact;
} else {
    $contactNameLine1 = "";
    $contactNameLine2 = $contact;
}

// საკადასტრო კოდი პროდუქტიდან
$cadastralCode = "";
$prod = CCrmDeal::LoadProductRows($deal_ID);
if (!empty($prod[0]["PRODUCT_ID"])) {
    $prodFilter = array("IBLOCK_ID" => 14, "ID" => $prod[0]["PRODUCT_ID"]);
    $prodSelect = Array("ID", "IBLOCK_ID", "PROPERTY_*");
    $prodRes = CIBlockElement::GetList(Array(), $prodFilter, false, false, $prodSelect);
    if ($prodOb = $prodRes->GetNextElement()) {
        $prodProps = $prodOb->GetProperties();
        $cadastralCode = $prodProps["CADASTRAL_CODE"]["VALUE"] ?? "";
    }
}

// გამოთვლები
$totalPayments      = getTotalPayments_st($deal_ID);
$remainingAmount    = round($totalPrice - $totalPayments, 2);
$paymentsPercentage = $totalPrice > 0 ? round(($totalPayments / $totalPrice) * 100, 2) : 0;

$dateFormatted = date("d.m.Y");

// ---------- შაბლონი (გასწორებული ვერსია!) ----------
$templatePath = $_SERVER["DOCUMENT_ROOT"] . "/crm/deal/Invoice/განცხადება_v1.docx";

if (!file_exists($templatePath)) {
    die("შაბლონი ვერ მოიძებნა: " . $templatePath);
}

// ---------- ჩანაცვლებები ----------
$replacements = [
    '${CLIENT_NAME}'          => htmlspecialchars($contactNameLine1, ENT_XML1, 'UTF-8'),
    '${CLIENT_NAME_2}'        => htmlspecialchars($contactNameLine2, ENT_XML1, 'UTF-8'),
    '${CLIENT_PN}'            => htmlspecialchars($contactPN, ENT_XML1, 'UTF-8'),
    '${CLIENT_LABEL}'         => htmlspecialchars($contactLabel, ENT_XML1, 'UTF-8'),
    '${CLIENT_LABEL_ENG}'     => htmlspecialchars($contactLabelEng, ENT_XML1, 'UTF-8'),
    '${CONTRACT_NUM}'         => htmlspecialchars($xelshNom, ENT_XML1, 'UTF-8'),
    '${PROJECT}'              => htmlspecialchars($project, ENT_XML1, 'UTF-8'),
    '${BLOCK}'                => htmlspecialchars($block, ENT_XML1, 'UTF-8'),
    '${FLOOR}'                => htmlspecialchars($floor, ENT_XML1, 'UTF-8'),
    '${APT_NUMBER}'           => htmlspecialchars($number, ENT_XML1, 'UTF-8'),
    '${TOTAL_AREA}'           => htmlspecialchars($totalArea, ENT_XML1, 'UTF-8'),
    '${TOTAL_PRICE}'          => number_format($totalPrice, 2, '.', ' '),
    '${TOTAL_PAYMENTS}'       => number_format($totalPayments, 2, '.', ' '),
    '${PAYMENTS_PERCENTAGE}'  => $paymentsPercentage,
    '${REMAINING_AMOUNT}'     => number_format($remainingAmount, 2, '.', ' '),
    '${CADASTRAL_CODE}'       => htmlspecialchars($cadastralCode, ENT_XML1, 'UTF-8'),
    '${DATE}'                 => $dateFormatted,
];

// ==========================================================
//  ZIP: იგივე მიდგომა რაც generateInvoice.php-ში მუშაობს
//  copy -> open -> deleteName/addFromString -> close
//  მხოლოდ str_replace — არანაირი regex!
// ==========================================================
$tempFile = tempnam(sys_get_temp_dir(), 'statement_');
copy($templatePath, $tempFile);

$zip = new ZipArchive();
if ($zip->open($tempFile) === true) {

    $xmlFiles = ['word/document.xml', 'word/header1.xml', 'word/footer1.xml'];

    foreach ($xmlFiles as $xmlFile) {
        $content = $zip->getFromName($xmlFile);
        if ($content === false) continue;

        // მხოლოდ str_replace — არანაირი regex!
        foreach ($replacements as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }

        $zip->deleteName($xmlFile);
        $zip->addFromString($xmlFile, $content);
    }

    $zip->close();
} else {
    die("ZIP ფაილი ვერ გაიხსნა");
}

// ---------- ფაილის გადმოწერა ----------
if (ob_get_level()) {
    ob_end_clean();
}

$filename = "Statement_{$xelshNom}_{$dateFormatted}.docx";
$filename = str_replace(["/", "\\", " "], ["-", "-", "_"], $filename);

header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Length: " . filesize($tempFile));
header("Cache-Control: max-age=0");

readfile($tempFile);
unlink($tempFile);
exit;