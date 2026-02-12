<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

// ============================================================
//  generate.php — Word ინვოისის გენერატორი (PHPWord-ის გარეშე)
//  იყენებს ZipArchive-ს — არანაირი დამატებითი ბიბლიოთეკა არ სჭირდება
//  გამოყენება: generate.php?deal_id=123&date=10/03/2026&amount=2981
// ============================================================

// ---------- პარამეტრები ----------
$deal_ID = (int)$_GET["deal_id"];
$date    = $_GET["date"]   ?? "";
$amount  = $_GET["amount"] ?? "";

if (!$deal_ID || !$date || !$amount) {
    die("მონაცემები არასრულია. საჭიროა: deal_id, date, amount");
}

// ---------- დილის ინფორმაცია (დაკომენტარებულია სატესტოდ) ----------
//function getDealInfoByID_inv($dealID) {
//    $res = CCrmDeal::GetList(["ID" => "ASC"], ["ID" => $dealID]);
//    if ($arDeal = $res->Fetch()) return $arDeal;
//    return [];
//}
//
//function getContactName_inv($id) {
//    $res = CCrmContact::GetList(["ID" => "ASC"], ["ID" => $id], ["ID", "FULL_NAME"]);
//    if ($arContact = $res->Fetch()) return $arContact["FULL_NAME"];
//    return "";
//}
//
//$dealData    = getDealInfoByID_inv($deal_ID);
//$contactId   = $dealData["CONTACT_ID"];
//$contact     = getContactName_inv($contactId);
//$xelshNom    = $dealData["UF_CRM_1699907477758"];
// ... სხვა ველები

// თარიღის ფორმატირება
$dateFormatted = str_replace("/", ".", $date);

// ---------- შაბლონის path ----------
$templatePath = $_SERVER["DOCUMENT_ROOT"] . "/crm/deal/Invoice/წერილი-ინვოისი.docx";

if (!file_exists($templatePath)) {
    die("შაბლონი ვერ მოიძებნა: " . $templatePath);
}

// ---------- ჩანაცვლებები ----------
// შაბლონში ჩასვი ${KEY} და აქ მიუთითე რა მნიშვნელობით ჩანაცვლდეს
$replacements = [
//    'CLIENT_NAME'   => $contact,
//    'CLIENT_PN'     => $contactPN,
//    'CONTRACT_NUM'  => $xelshNom,
//    'PROJECT'       => $project,
//    'BLOCK'         => $block,
//    'FLOOR'         => $floor,
//    'APT_NUMBER'    => $number,
//    'TOTAL_AREA'    => $totalArea,
//    'TOTAL_PRICE'   => number_format((float)$totalPrice, 2, '.', ' '),
//    'AMOUNT'        => number_format((float)$amount, 2, '.', ' '),
//    'DATE'          => $dateFormatted,
//    'INVOICE_NUM'   => "INV-{$deal_ID}-" . str_replace("/", "", $date),
];

// ---------- დროებითი ფაილის შექმნა ----------
$tempFile = tempnam(sys_get_temp_dir(), 'invoice_');
copy($templatePath, $tempFile);

// ---------- ZIP გახსნა და XML-ში ჩანაცვლება ----------
$zip = new ZipArchive();
if ($zip->open($tempFile) === true) {

    // document.xml — მთავარი კონტენტი
    $xmlFiles = ['word/document.xml', 'word/header1.xml', 'word/footer1.xml'];

    foreach ($xmlFiles as $xmlFile) {
        $content = $zip->getFromName($xmlFile);
        if ($content === false) continue;

        // ჩანაცვლება: ${KEY} → მნიშვნელობა
        foreach ($replacements as $key => $value) {
            // პირდაპირი ჩანაცვლება (თუ placeholder ერთიან ტექსტშია)
            $content = str_replace('${' . $key . '}', htmlspecialchars($value, ENT_XML1, 'UTF-8'), $content);

            // Word-მა შეიძლება placeholder დაშალოს რამდენიმე <w:r> ელემენტად
            // მაგ: <w:t>${</w:t></w:r><w:r><w:t>CLIENT_NAME</w:t></w:r><w:r><w:t>}</w:t>
            // ამისთვის regex pattern:
            $pattern = '/\$\{(<\/w:t><\/w:r>.*?<w:r[^>]*>.*?<w:t[^>]*>)*'
                . preg_quote($key, '/')
                . '(<\/w:t><\/w:r>.*?<w:r[^>]*>.*?<w:t[^>]*>)*\}/s';
            $content = preg_replace($pattern, htmlspecialchars($value, ENT_XML1, 'UTF-8'), $content);
        }

        $zip->deleteName($xmlFile);
        $zip->addFromString($xmlFile, $content);
    }

    $zip->close();
} else {
    die("ZIP ფაილი ვერ გაიხსნა");
}

// ---------- ფაილის გადმოწერა ----------
$filename = "Invoice_{$dateFormatted}.docx";
$filename = str_replace(["/", "\\", " "], ["-", "-", "_"], $filename);

header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Length: " . filesize($tempFile));
header("Cache-Control: max-age=0");

readfile($tempFile);
unlink($tempFile);
exit;