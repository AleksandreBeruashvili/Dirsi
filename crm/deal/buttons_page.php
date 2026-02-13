<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Bitrix\Main\Loader;
use Bitrix\Crm\Service;

// ===== კონფიგურაცია =====
define('DISK_PARENT_ID', 37);
define('TECH_CONTACT_ID', 4521);
define('TECH_COMPANY_ID', 23);
define('API_URL', 'http://tfs.fmgsoft.ge:7799/API/FMGSoft/Admin/GetPDFFromWordS');

global $USER, $DB;

// ===== უტილიტა ფუნქციები =====

function printArr($arr) {
    echo "<pre>" . print_r($arr, true) . "</pre>";
}

function formatNumber($value) {
    return number_format((float)$value, 2, '.', ',');
}

function getUserName($id) {
    $res = CUser::GetByID($id)->Fetch();
    return $res ? trim($res["NAME"] . " " . $res["LAST_NAME"]) : '';
}

function getGeorgianDate() {
    $months = [
        1 => "იანვარი", 2 => "თებერვალი", 3 => "მარტი",
        4 => "აპრილი", 5 => "მაისი", 6 => "ივნისი",
        7 => "ივლისი", 8 => "აგვისტო", 9 => "სექტემბერი",
        10 => "ოქტომბერი", 11 => "ნოემბერი", 12 => "დეკემბერი"
    ];
    return date("d") . " " . $months[(int)date("n")] . " " . date("Y");
}

// ===== CRM ფუნქციები =====

function getFieldMultiValue($entityId, $typeId, $elementId) {
    $result = CCrmFieldMulti::GetList([], [
        'ENTITY_ID' => $entityId,
        'TYPE_ID' => $typeId,
        'VALUE_TYPE' => 'MOBILE|WORK|HOME',
        'ELEMENT_ID' => $elementId
    ])->Fetch();
    
    return $result["VALUE"] ?? '';
}

function getContactInfo($contactId) {
    if (!$contactId) return [];
    
    $res = CCrmContact::GetList(["ID" => "ASC"], ["ID" => $contactId]);
    $arContact = $res->Fetch();
    
    if ($arContact) {
        $arContact["PHONE"] = getFieldMultiValue('CONTACT', 'PHONE', $contactId);
        $arContact["EMAIL"] = getFieldMultiValue('CONTACT', 'EMAIL', $contactId);
    }
    
    return $arContact ?: [];
}

function getCompanyInfo($companyId) {
    if (!$companyId) return [];
    
    $res = CCrmCompany::GetList(["ID" => "ASC"], ["ID" => $companyId]);
    $arCompany = $res->Fetch();
    
    if ($arCompany) {
        $arCompany["PHONE"] = getFieldMultiValue('COMPANY', 'PHONE', $companyId);
        $arCompany["EMAIL"] = getFieldMultiValue('COMPANY', 'EMAIL', $companyId);
    }
    
    return $arCompany ?: [];
}

function getDealInfo($dealId) {
    if (!$dealId) return [];
    
    $res = CCrmDeal::GetList(["ID" => "ASC"], ["ID" => $dealId]);
    return $res->Fetch() ?: [];
}

function getDealProducts($dealId) {
    $prods = CCrmDeal::LoadProductRows($dealId);
    $products = [];
    
    foreach ($prods as $prod) {
        $elements = getIBlockElements(["ID" => $prod["PRODUCT_ID"]]);
        if (!empty($elements[0])) {
            $price = CPrice::GetBasePrice($prod["PRODUCT_ID"]);
            $elements[0]["PRICE"] = $price["PRICE"] ?? 0;
            $elements[0]["RAODENOBA"] = $prod["QUANTITY"];
            $products[] = $elements;
        }
    }
    
    return $products;
}

function getDealContactIds($dealId) {
    return \Bitrix\Crm\Binding\DealContactTable::getDealContactIDs($dealId);
}

function getIBlockElements($arFilter, $limit = 99999) {
    $arElements = [];
    $res = CIBlockElement::GetList(["ID" => "ASC"], $arFilter, false, ["nPageSize" => $limit]);
    
    while ($ob = $res->GetNextElement()) {
        $arFields = $ob->GetFields();
        $arProps = $ob->GetProperties();
        
        $element = $arFields;
        foreach ($arProps as $key => $prop) {
            $element[$key] = $prop["VALUE"];
        }
        $arElements[] = $element;
    }
    
    return $arElements;
}

// ===== ფაილების ფუნქციები =====

function getFilesFromDisk($parentId) {
    global $DB;
    
    $files = [];
    $parentId = (int)$parentId;
    $dbRes = $DB->query("SELECT * FROM b_disk_object WHERE PARENT_ID = {$parentId}");
    
    while ($object = $dbRes->Fetch()) {
        $files[] = [
            "NAME" => $object["NAME"],
            "ID" => $object["ID"],
            "FILE_ID" => $object["FILE_ID"]
        ];
    }
    
    return $files;
}

function processFileArray($files) {
    $result = [];
    
    foreach ($files as $file) {
        if (empty($file["NAME"])) continue;
        
        $parts = explode('$', $file["NAME"]);
        
        if (count($parts) >= 3) {
            $file["PIPE"] = $parts[0];
            $file["LANG"] = $parts[1];
            $file["NAME"] = $parts[2];
            $result[] = $file;
        }
    }
    
    return $result;
}

function getFileById($fileId) {
    global $DB;
    
    $fileId = (int)$fileId;
    $dbRes = $DB->query("SELECT * FROM b_disk_object WHERE PARENT_ID = " . DISK_PARENT_ID . " AND ID = {$fileId}");
    return $dbRes->Fetch();
}

function getFileContent($bitrixFileId) {
    $filePath = CFile::GetPath($bitrixFileId);
    $fullPath = $_SERVER["DOCUMENT_ROOT"] . $filePath;
    
    if (!file_exists($fullPath)) {
        return null;
    }
    
    return file_get_contents($fullPath);
}

function getFileNameFromDiskObject($diskName) {
    $parts = explode('$', $diskName);
    $name = $parts[5] ?? $parts[2] ?? 'document';
    return explode('.', $name)[0];
}

// ===== დოკუმენტის ცვლადების ფუნქციები =====

function combineArrays($arrays, $separator = '/') {
    $combined = [];
    
    foreach ($arrays as $array) {
        foreach ($array as $key => $value) {
            $value = ($value === '' || $value === null) ? str_repeat(' ', 11) : $value;
            
            if (!isset($combined[$key])) {
                $combined[$key] = $value;
            } else {
                $combined[$key] .= $separator . $value;
            }
        }
    }
    
    return $combined;
}

function addVariable(&$variables, $name, $value, $type = null) {
    $var = [
        'VarName' => $name,
        'VarValue' => ($value === null || $value === '') ? '' : (($value === "0" || $value === 0) ? "0" : $value)
    ];
    
    if ($type) {
        $var['VarType'] = $type;
    }
    
    $variables[] = $var;
}

function addArrayWithSuffix(&$variables, $array, $suffix) {
    foreach ($array as $key => $value) {
        if (!is_array($value)) {
            addVariable($variables, '$' . $key . $suffix . '$', $value);
        }
    }
}

function getLatestSchedule($dealId) {
    if (!$dealId) return null;

    $arFilter = [
        "IBLOCK_ID" => 23,
        "PROPERTY_DEAL" => $dealId,
        "PROPERTY_DASTURI" => "დადასტურებული",
    ];
    
    $res = CIBlockElement::GetList(
        ["PROPERTY_dadasturebisDro" => "DESC"],
        $arFilter,
        false,
        ["nPageSize" => 1],
        ["ID", "PROPERTY_JSON"]
    );

    if ($element = $res->GetNext()) {
        return $element["PROPERTY_JSON_VALUE"] ?? null;
    }

    return null;
}

function generateSimpleScheduleTable($jsonString, $language = 'geo') {
    if (empty($jsonString)) return '';
    
    $json1 = str_replace("&quot;", "\"", $jsonString);
    $data = json_decode($json1, true);
    
    if (!$data || empty($data["data"])) return '';
    
    $html = "<table style='border-collapse: collapse; width:100%; font-family: sylfaen;'><tbody>";
    
    $rowNum = 1;
    foreach ($data["data"] as $row) {
        $amount = (float)($row["amount"] ?? 0);
        $date = $row["date"] ?? '';
        
        // ფორმატირება: 800 USD ან 53 577.6 USD
        $formattedAmount = number_format($amount, ($amount == floor($amount)) ? 0 : 1, '.', ' ') . ' USD';
        
        $html .= "<tr>
            <td style='border: 1px solid black; font-size:11px; padding:3px 8px; text-align:left;'>{$rowNum})</td>
            <td style='border: 1px solid black; font-size:11px; padding:3px 8px; text-align:center;'>{$formattedAmount}</td>
            <td style='border: 1px solid black; font-size:11px; padding:3px 8px; text-align:center;'>{$date}</td>
        </tr>";
        
        $rowNum++;
    }
    
    return $html . "</tbody></table>";
}


function buildDocumentVariables($dealId) {
    $variables = [];
    $deal = getDealInfo($dealId);
    
    // კონტაქტების ჩატვირთვა
    $contacts = [];
    $contactIds = getDealContactIds($deal["ID"] ?? 0);
    foreach ($contactIds as $contactId) {
        $contacts[] = getContactInfo($contactId);
    }
    
    // კომპანიის ჩატვირთვა
    $company = getCompanyInfo($deal["COMPANY_ID"] ?? 0);
    
    // თუ ცარიელია - ტექნიკური მონაცემები
    if (empty($contacts)) {
        $template = getContactInfo(TECH_CONTACT_ID);
        $contacts[] = array_fill_keys(array_keys($template), '');
    }
    if (empty($company)) {
        $template = getCompanyInfo(TECH_COMPANY_ID);
        $company = array_fill_keys(array_keys($template), '');
    }
    
    // დილის ცვლადები
    foreach ($deal as $key => $value) {
        if (!is_array($value)) {
            addVariable($variables, '$' . $key . '$', $value);
        }
    }
    
    // კონტაქტის ცვლადები
    $combinedContacts = combineArrays($contacts);
    addArrayWithSuffix($variables, $combinedContacts, '_USER');
    
    // კომპანიის ცვლადები
    addArrayWithSuffix($variables, $company, '_COM');
    
    // ძველი მფლობელები
    $oldContacts = [];
    $oldCompanies = [];
    
    if (!empty($deal["UF_CRM_1710484037"])) {
        foreach ($deal["UF_CRM_1710484037"] as $value) {
            $parts = explode("_", $value);
            
            if ($parts[0] === "CO") {
                $oldCompanies[] = getCompanyInfo($parts[1]);
            } elseif ($parts[0] === "C") {
                $oldContacts[] = getContactInfo($parts[1]);
            }
        }
    }
    
    if (!empty($oldContacts)) {
        addArrayWithSuffix($variables, combineArrays($oldContacts, ','), '_OLD_CON');
    }
    if (!empty($oldCompanies)) {
        addArrayWithSuffix($variables, combineArrays($oldCompanies, ','), '_OLD_COM');
    }
    
    // ახალი მფლობელები
    $newContacts = [];
    if (!empty($deal["UF_CRM_1657805299"])) {
        foreach ($deal["UF_CRM_1657805299"] as $contactId) {
            $newContacts[] = getContactInfo($contactId);
        }
    }
    
    if (!empty($newContacts)) {
        addArrayWithSuffix($variables, combineArrays($newContacts, ','), '_NEW_CON');
    }
    
    // თარიღები
    addVariable($variables, '$TODAY_DATE$', date("d/m/Y"));
    addVariable($variables, '$DATE_WORD$', getGeorgianDate());
    
    // ბანკის კონვერტაცია
    $bankMapping = [
        "3210" => "საქართველოს ბანკი",
        "3211" => "თი-ბი-სი ბანკი"
    ];
    $bankValue = $deal["UF_CRM_1733485628918"] ?? '';
    if (isset($bankMapping[$bankValue])) {
        addVariable($variables, '$UF_CRM_1733485628918$', $bankMapping[$bankValue]);
    }
    
    // მრავლობითი კონტაქტები
    for ($i = 0; $i < count($contacts) && $i < 3; $i++) {
        addArrayWithSuffix($variables, $contacts[$i], '_USER_' . ($i + 1));
    }
    
    // გაერთიანებული დილი
    if (!empty($deal["UF_CRM_1745316640"])) {
        $mergedDeal = getDealInfo($deal["UF_CRM_1745316640"][0]);
        foreach ($mergedDeal as $key => $value) {
            if (!is_array($value)) {
                addVariable($variables, '$' . $key . '_2$', $value);
            }
        }
    }

    $jsonData = getLatestSchedule($dealId);
    if ($jsonData) {

        $variables[] = ['VarName' => '$grafiki$', 'VarValue' => generateSimpleScheduleTable($jsonData), 'VarType' => 'T'];
    }
    
    return $variables;
}

// ===== API ფუნქციები =====

function generateDocument($fileData, $variables, $convertToPdf = true) {
    $jsonArray = [
        "FileData" => base64_encode($fileData),
        "FileName" => "document.docx",
        "Convert" => $convertToPdf,
        "jsonVars" => $variables
    ];
    
    $jsonString = json_encode($jsonArray);
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($jsonString) . "\r\n",
            'content' => $jsonString,
            'ignore_errors' => true,
            'timeout' => 60
        ]
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents(API_URL, false, $context);
    
    if ($response === false) {
        throw new Exception("API-სთან დაკავშირება ვერ მოხერხდა");
    }
    
    $decodedData = base64_decode($response);
    
    if ($decodedData === false) {
        throw new Exception("Base64 დეკოდირების შეცდომა");
    }
    
    return $decodedData;
}

function outputFile($fileData, $fileName, $isPdf = true) {
    ob_end_clean();
    
    $extension = $isPdf ? 'pdf' : 'docx';
    $contentType = $isPdf 
        ? 'application/pdf' 
        : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    
    $encodedName = rawurlencode($fileName);
    
    header("Content-Type: {$contentType}");
    header("Content-Disposition: attachment; filename=\"{$encodedName}.{$extension}\"; filename*=UTF-8''{$encodedName}.{$extension}");
    header("Content-Length: " . strlen($fileData));
    
    echo $fileData;
    exit;
}

// ===== მთავარი ლოგიკა =====



if($USER->GetID()){
    $NotAuthorized=false;
    $user_id=$USER->GetID();
    $USER->Authorize(1);
}
else{
    $NotAuthorized=true;
    $USER->Authorize(1);
}


// GET პარამეტრები
$dealId = isset($_GET["dealid"]) ? (int)$_GET["dealid"] : 0;

// ფაილების ჩატვირთვა
$filesArr = getFilesFromDisk(DISK_PARENT_ID);
$fullArr = processFileArray($filesArr);

// დილის ჩატვირთვა
$deal = $dealId ? getDealInfo($dealId) : [];

// POST დამუშავება
$popupMode = 'nopop';
$emptyGet = false;
$errorCode = "";

if (!empty($_POST)) {
    $popupMode = $_POST['popup'] ?? '';
    $docId = isset($_POST["docs"]) ? (int)$_POST["docs"] : 0;
    $fileType = $_POST["type"] ?? 'docx';
    $postDealId = isset($_POST["deal_id"]) ? (int)$_POST["deal_id"] : 0;
    
    $isPdf = ($fileType === "pdf");
    
    if ($docId && $postDealId) {
        try {
            $variables = buildDocumentVariables($postDealId);
            $fileObject = getFileById($docId);
            
            if (!$fileObject) {
                throw new Exception("ფაილი ვერ მოიძებნა");
            }
            
            $fileContent = getFileContent($fileObject["FILE_ID"]);
            
            if (!$fileContent) {
                throw new Exception("ფაილის წაკითხვა ვერ მოხერხდა");
            }
            
            $fileName = getFileNameFromDiskObject($fileObject["NAME"]);
            $generatedFile = generateDocument($fileContent, $variables, $isPdf);
            
            // ავტორიზაციის აღდგენა გენერაციამდე
            if($NotAuthorized) {
                $USER->Logout();
            }
            else{
                $USER->Authorize($user_id);
            }
            
            outputFile($generatedFile, $fileName, $isPdf);
            
        } catch (Exception $e) {
            error_log("Document generation error: " . $e->getMessage());
            $errorCode = $e->getMessage();
            $emptyGet = true;
        }
    }
}


if($NotAuthorized) {
    $USER->Logout();
}
else{
    $USER->Authorize($user_id);
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>დოკუმენტების გენერაცია</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        #maincontent { margin-top: 100px; }
        
        .form-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 30px;
            max-width: 800px;
            margin: 50px;
        }
        
        .divider {
            width: 100%;
            height: 1px;
            background-color: #dee2e6;
            margin: 25px 0;
        }
        
        .form-select, button { border-radius: 10px; }
        .buttonDiv { display: flex; justify-content: center; }
        
        .buttonDoc {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            padding: 12px 28px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 150px;
            margin-top: 40px;
        }
        
        .buttonDoc:hover {
            background: linear-gradient(135deg, #45a049, #3d8b40);
            transform: translateY(-2px);
        }
        
        .gtranslate_wrapper { margin-left: 450px; }
    </style>
</head>
<body>

<div id="maincontent" class="maincontent">
    <div class="form-card">
        <h4 class="mb-4 text-center fw-semibold">📄 დოკუმენტების გენერაცია</h4>

        <form method="post" class="d-flex flex-column gap-4">
            <div style="display: none;">
                <label for="pipeline" class="form-label fw-semibold">Pipeline</label>
                <select onchange="filterDocuments()" id="pipeline" class="form-select w-auto">
                    <option value="SALE">გაყიდვები</option>
                    <option value="AFTER_SALE">After Sale</option>
                    <option value="დიზაინერი">დიზაინერი</option>
                </select>
            </div>

            <div class="divider"></div>

            <div class="d-flex flex-wrap align-items-center gap-3">
                <input name="deal_id" id="deal_id" type="hidden">
                <input name="popup" id="popup" type="hidden">

                <div>
                    <label for="language" class="form-label fw-semibold">ენა</label>
                    <select id="language" required class="form-select" onchange="filterDocuments()">
                        <option value="geo">GEO</option>
                        <option value="eng">ENG</option>
                        <option value="rus">RUS</option>
                    </select>
                </div>

                <div class="flex-grow-1">
                    <label for="docs" class="form-label fw-semibold">დოკუმენტი</label>
                    <select required name="docs" id="docs" class="form-select">
                        <option value="" disabled selected>აირჩიეთ დოკუმენტი</option>
                    </select>
                </div>

                <div>
                    <label for="format" class="form-label fw-semibold">ფორმატი</label>
                    <select name="type" id="format" required class="form-select">
                        <option value="docx">Word</option>
                        <option value="pdf">PDF</option>
                    </select>
                </div>

                <div class="buttonDiv">
                    <button type="submit" class="buttonDoc">📥 გენერაცია</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const CONFIG = {
    fullArr: <?= json_encode($fullArr) ?>,
    dealId: <?= json_encode($dealId) ?>,
    emptyGet: <?= json_encode($emptyGet) ?>,
    errorCode: <?= json_encode($errorCode) ?>,
    popupMode: <?= json_encode($popupMode) ?>
};

function filterDocuments() {
    const lang = document.getElementById('language').value.toLowerCase();
    const pipe = document.getElementById('pipeline').value;
    const docs = document.getElementById('docs');
    
    docs.innerHTML = '<option value="" disabled selected>დოკუმენტი</option>';
    
    const filtered = CONFIG.fullArr
        .filter(item => item?.LANG?.toLowerCase() === lang && (item.PIPE === pipe || item.PIPE === "ყველა"))
        .sort((a, b) => (a.NAME || '').localeCompare(b.NAME || ''));
    
    if (filtered.length === 0) {
        docs.innerHTML += '<option disabled>დოკუმენტები არ მოიძებნა</option>';
    } else {
        filtered.forEach(item => {
            if (item.ID && item.NAME) {
                docs.innerHTML += `<option value="${item.ID}">${item.NAME}</option>`;
            }
        });
    }
}

function init() {
    if (CONFIG.emptyGet) {
        alert(CONFIG.errorCode || 'შეცდომა');
        document.getElementById('maincontent').innerHTML = '';
        return;
    }

    document.getElementById('deal_id').value = CONFIG.dealId || '';
    document.getElementById('popup').value = CONFIG.popupMode;
    
    filterDocuments();
    
    // GTranslate
    // setTimeout(() => {
    //     window.gtranslateSettings = {
    //         default_language: "ka",
    //         languages: ["ka", "en", "ru"],
    //         wrapper_selector: ".gtranslate_wrapper",
    //         flag_size: 24
    //     };
        
    //     const script = document.createElement('script');
    //     script.src = "https://cdn.gtranslate.net/widgets/latest/flags.js";
    //     script.defer = true;
    //     document.body.appendChild(script);
        
    //     const wrapper = document.createElement('div');
    //     wrapper.className = 'gtranslate_wrapper';
    //     document.getElementById('maincontent').parentNode.insertBefore(wrapper, document.getElementById('maincontent'));
    // }, 3000);
}

document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
</script>

</body>
</html>