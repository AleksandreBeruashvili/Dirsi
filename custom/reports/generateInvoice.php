<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('crm');

// ============================================================
//  generateInvoice.php — Word ინვოისის გენერატორი (PHPWord-ის გარეშე)
//  იყენებს ZipArchive-ს — არანაირი დამატებითი ბიბლიოთეკა არ სჭირდება
//  გამოყენება: generateInvoice.php?deal_id=123&date=10/03/2026&amount=2981
// ============================================================

// ---------- პარამეტრები ----------
$deal_ID = (int)$_GET["deal_id"];
$date    = $_GET["date"]   ?? "";
$amount  = $_GET["amount"] ?? "";

if (!$deal_ID || !$date || !$amount) {
    die("მონაცემები არასრულია. საჭიროა: deal_id, date, amount");
}

// ---------- ეროვნული ბანკის კურსი ----------
function getNbgKurs($date) {
    if (!$date) return null;

    $dateObj = DateTime::createFromFormat('d/m/Y', $date);
    if (!$dateObj) return null;

    $dateFormatted = $dateObj->format('Y-m-d');
    $url = "https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies?Currencies=USD&date={$dateFormatted}";

    $resp = @file_get_contents($url);
    if (!$resp) return null;

    $json = json_decode($resp);
    return $json[0]->currencies[0]->rate ?? null;
}

// ---------- დილის ინფორმაცია ----------
function getDealInfoByID($dealID, $arrSelect = array()) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), $arrSelect);
    if ($arDeal = $res->Fetch()) {
        return $arDeal;
    }
    return [];
}

function getContactName_inv($id) {
    $res = CCrmContact::GetList(["ID" => "ASC"], ["ID" => $id], ["ID", "FULL_NAME"]);
    if ($arContact = $res->Fetch()) return $arContact["FULL_NAME"];
    return "";
}

// ქართულიდან ლათინურად ტრანსლიტერაცია
function transliterateGeo($str) {
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

    // სიტყვების პირველი ასოების გადიდება
    return mb_convert_case($result, MB_CASE_TITLE, 'UTF-8');
}

// სახელის ენის ამოცნობა და განაწილება
function isGeorgian($text) {
    return preg_match('/[\x{10A0}-\x{10FF}]/u', $text);
}

function isRussian($text) {
    return preg_match('/[\x{0400}-\x{04FF}]/u', $text);
}


$dealData    = getDealInfoByID($deal_ID);
$contactId   = $dealData["CONTACT_ID"];
$contact     = getContactName_inv($contactId);
$project     = $dealData["UF_CRM_1761658516561"];       // პროექტი
$floor       = $dealData["UF_CRM_1761658577987"];       // სართული
$number      = $dealData["UF_CRM_1761658559005"];       // ბინის N
$prodType    = $dealData["UF_CRM_1761658532158"];       // ფართის ტიპი
$xelshNom    = $dealData["UF_CRM_1766563053146"];       // ხელშეკრულების ნომერი
$totalArea   = $dealData["UF_CRM_1761658608306"];       // საერთო ფართი
$block       = $dealData["UF_CRM_1762948106980"];       // ბლოკი
$entrance    = $dealData["UF_CRM_1762867479699"];       // სადარბაზო
$totalPrice  = $dealData["OPPORTUNITY"];                // მთლიანი ღირებულება

// კონტაქტის დამატებითი ინფო (პირადი ნომერი/პასპორტი)
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

if (isGeorgian($contact)) {
    $contactNameLine1 = $contact;
    $contactNameLine2 = transliterateGeo($contact);
} elseif (isRussian($contact)) {
    $contactNameLine1 = "";
    $contactNameLine2 = $contact;
} else {
    $contactNameLine1 = "";
    $contactNameLine2 = $contact;
}

// ---------- თარიღის ფორმატირება ----------
$todayFormatted = date("d.m.Y");           // დღევანდელი თარიღი დოკუმენტისთვის
$todayForKurs   = date("d/m/Y");           // დღევანდელი თარიღი კურსისთვის
$dateFormatted  = str_replace("/", ".", $date);  // გეგმის თარიღი (ფაილის სახელისთვის)

// ---------- კურსი და გამოთვლები ----------
$nbgRate        = getNbgKurs($todayForKurs);
$amountFloat    = (float)$amount;
$commission     = round($amountFloat * 0.0015, 2);          // 0.15% საკომისიო
$withCommission = round($amountFloat + $commission, 2);      // თანხა + საკომისიო
$amountGel      = $nbgRate ? round($amountFloat * $nbgRate, 2) : "";  // $ * კურსი = ₾

// ---------- შაბლონის path ----------
$templatePath = $_SERVER["DOCUMENT_ROOT"] . "/crm/deal/Invoice/ინვოისი.docx";

if (!file_exists($templatePath)) {
    die("შაბლონი ვერ მოიძებნა: " . $templatePath);
}

// ---------- ჩანაცვლებები ----------
$replacements = [
    'CLIENT_NAME'   => $contactNameLine1,   // ქართული
    'CLIENT_NAME_2' => $contactNameLine2,   // ინგლისური/რუსული
    'CLIENT_PN'        => $contactPN,
    'CLIENT_LABEL'     => $contactLabel,
    'CLIENT_LABEL_ENG' => $contactLabelEng,
    'CONTRACT_NUM'     => $xelshNom,
    'PROJECT'          => $project,
    'BLOCK'            => $block,
    'FLOOR'            => $floor,
    'APT_NUMBER'       => $number,
    'TOTAL_AREA'       => $totalArea,
    'TOTAL_PRICE'      => number_format((float)$totalPrice, 2, '.', ' '),
    'P_AMOUNT'         => number_format($amountFloat, 2, '.', ' '),
    'NBG_RATE'         => $nbgRate ? number_format($nbgRate, 4, '.', '') : "",
    'P_WITH_COMMISSION'=> number_format($withCommission, 2, '.', ' '),
    'P_AMOUNT_GEL'     => $amountGel ? number_format($amountGel, 2, '.', ' ') : "",
    'AMOUNT'           => number_format($amountFloat, 2, '.', ' '),
    'DATE'             => $todayFormatted,
    'INVOICE_NUM'      => "INV-{$deal_ID}-" . str_replace("/", "", $date),
];



// ---------- დროებითი ფაილის შექმნა ----------
$tempFile = tempnam(sys_get_temp_dir(), 'invoice_');
copy($templatePath, $tempFile);

// ---------- ZIP გახსნა და XML-ში ჩანაცვლება ----------
$zip = new ZipArchive();
if ($zip->open($tempFile) === true) {

    $xmlFiles = ['word/document.xml', 'word/header1.xml', 'word/footer1.xml'];

    foreach ($xmlFiles as $xmlFile) {
        $content = $zip->getFromName($xmlFile);
        if ($content === false) continue;

        foreach ($replacements as $key => $value) {
            $safeValue = htmlspecialchars($value, ENT_XML1, 'UTF-8');

            // პირდაპირი ჩანაცვლება
            $content = str_replace('${' . $key . '}', $safeValue, $content);

            // Word-მა შეიძლება placeholder დაშალოს რამდენიმე <w:r> ელემენტად
            $pattern = '/\$\{(<\/w:t><\/w:r>.*?<w:r[^>]*>.*?<w:t[^>]*>)*'
                . preg_quote($key, '/')
                . '(<\/w:t><\/w:r>.*?<w:r[^>]*>.*?<w:t[^>]*>)*\}/s';
            $content = preg_replace($pattern, $safeValue, $content);
        }

        $zip->deleteName($xmlFile);
        $zip->addFromString($xmlFile, $content);
    }

    $zip->close();
} else {
    die("ZIP ფაილი ვერ გაიხსნა");
}

// ---------- ფაილის გადმოწერა ----------
$filename = "Invoice_{$xelshNom}_{$dateFormatted}.docx";
$filename = str_replace(["/", "\\", " "], ["-", "-", "_"], $filename);

header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Length: " . filesize($tempFile));
header("Cache-Control: max-age=0");

readfile($tempFile);
unlink($tempFile);
exit;