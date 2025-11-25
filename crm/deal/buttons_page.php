<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Bitrix\Main\Loader;
use Bitrix\Crm\Service;

// ===== კონფიგურაცია =====
define('DISK_PARENT_ID', 37);
define('TECH_CONTACT_ID', 4521);
define('TECH_COMPANY_ID', 23);
define('API_URL', 'http://tfs.fmgsoft.ge:7799/API/FMGSoft/Admin/GetPDFFromWordS');

// ===== ავტორიზაცია =====
class AuthManager {
    private $user;
    private $originalUserId;
    private $wasAuthorized;
    
    public function __construct($user) {
        $this->user = $user;
        $this->originalUserId = $user->GetID();
        $this->wasAuthorized = (bool)$this->originalUserId;
    }
    
    public function getUserId() {
        return $this->originalUserId ?: 0;
    }
    
    public function isAuthorized() {
        return $this->wasAuthorized;
    }
    
    public function authorizeAsAdmin() {
        $this->user->Authorize(1);
    }
    
    public function restoreOriginalUser() {
        if (!$this->wasAuthorized) {
            $this->user->Logout();
        } else {
            $this->user->Authorize($this->originalUserId);
        }
    }
}

// ===== უტილიტა ფუნქციები =====
class Utils {
    public static function printArr($arr) {
        echo "<pre>" . print_r($arr, true) . "</pre>";
    }
    
    public static function formatNumber($value) {
        return number_format((float)$value, 2, '.', ',');
    }
    
    public static function getUserName($id) {
        $res = CUser::GetByID($id)->Fetch();
        return $res ? trim($res["NAME"] . " " . $res["LAST_NAME"]) : '';
    }
    
    public static function getGeorgianDate() {
        $months = [
            1 => "იანვარი", 2 => "თებერვალი", 3 => "მარტი",
            4 => "აპრილი", 5 => "მაისი", 6 => "ივნისი",
            7 => "ივლისი", 8 => "აგვისტო", 9 => "სექტემბერი",
            10 => "ოქტომბერი", 11 => "ნოემბერი", 12 => "დეკემბერი"
        ];
        return date("d") . " " . $months[(int)date("n")] . " " . date("Y");
    }
    
    public static function sanitizeForSql($value) {
        global $DB;
        return $DB->ForSql($value);
    }
}

// ===== CRM მონაცემების მენეჯერი =====
class CrmDataManager {
    
    public static function getContactInfo($contactId) {
        if (!$contactId) return [];
        
        $res = CCrmContact::GetList(["ID" => "ASC"], ["ID" => $contactId]);
        $arContact = $res->Fetch();
        
        if ($arContact) {
            $arContact["PHONE"] = self::getFieldMultiValue('CONTACT', 'PHONE', $contactId);
            $arContact["EMAIL"] = self::getFieldMultiValue('CONTACT', 'EMAIL', $contactId);
        }
        
        return $arContact ?: [];
    }
    
    public static function getCompanyInfo($companyId) {
        if (!$companyId) return [];
        
        $res = CCrmCompany::GetList(["ID" => "ASC"], ["ID" => $companyId]);
        $arCompany = $res->Fetch();
        
        if ($arCompany) {
            $arCompany["PHONE"] = self::getFieldMultiValue('COMPANY', 'PHONE', $companyId);
            $arCompany["EMAIL"] = self::getFieldMultiValue('COMPANY', 'EMAIL', $companyId);
        }
        
        return $arCompany ?: [];
    }
    
    public static function getDealInfo($dealId) {
        if (!$dealId) return [];
        
        $res = CCrmDeal::GetList(["ID" => "ASC"], ["ID" => $dealId]);
        return $res->Fetch() ?: [];
    }
    
    public static function getDealProducts($dealId) {
        $prods = CCrmDeal::LoadProductRows($dealId);
        $products = [];
        
        foreach ($prods as $prod) {
            $elements = self::getIBlockElements(["ID" => $prod["PRODUCT_ID"]]);
            if (!empty($elements[0])) {
                $price = CPrice::GetBasePrice($prod["PRODUCT_ID"]);
                $elements[0]["PRICE"] = $price["PRICE"] ?? 0;
                $elements[0]["RAODENOBA"] = $prod["QUANTITY"];
                $products[] = $elements;
            }
        }
        
        return $products;
    }
    
    public static function getDealContactIds($dealId) {
        return \Bitrix\Crm\Binding\DealContactTable::getDealContactIDs($dealId);
    }
    
    public static function getIBlockElements($arFilter, $limit = 99999) {
        $arElements = [];
        $res = CIBlockElement::GetList(
            ["ID" => "ASC"], 
            $arFilter, 
            false, 
            ["nPageSize" => $limit]
        );
        
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
    
    public static function getSpaInfo($arFilter, $typeId) {
        $factory = Service\Container::getInstance()->getFactory($typeId);
        if (!$factory) return [];
        
        $items = $factory->getItems([
            'select' => ['*'],
            'filter' => $arFilter
        ]);
        
        return array_map(fn($item) => $item->getData(), $items);
    }
    
    private static function getFieldMultiValue($entityId, $typeId, $elementId) {
        $result = \CCrmFieldMulti::GetList(
            [], 
            [
                'ENTITY_ID' => $entityId,
                'TYPE_ID' => $typeId,
                'VALUE_TYPE' => 'MOBILE|WORK|HOME',
                'ELEMENT_ID' => $elementId
            ]
        )->Fetch();
        
        return $result["VALUE"] ?? '';
    }
}

// ===== გრაფიკის გენერატორი =====
class ScheduleTableGenerator {
    
    private static function getTableHeader($isEnglish = false) {
        $headers = $isEnglish 
            ? ['№', 'Payment Date', 'Amount Due', 'Remaining principal $']
            : ['№', 'გადახდის თარიღი', 'თანხა', 'ნაშთი'];
        
        return "
        <table style='border-collapse: collapse; table-layout: fixed; width:60%; margin:0 auto;'>
            <thead>
                <tr>
                    <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen; width:50px;'><b>{$headers[0]}</b></th>
                    <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen; width:calc((100% - 50px)/3);'><b>{$headers[1]}</b></th>
                    <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen; width:calc((100% - 50px)/3);'><b>{$headers[2]}</b></th>
                    <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen; width:calc((100% - 50px)/3);'><b>{$headers[3]}</b></th>
                </tr>
            </thead>
            <tbody>";
    }
    
    private static function getTableRow($n, $date, $amount, $remaining) {
        return "<tr>
            <td style='border: 1px solid black;font-size:13.5px;text-align:center;font-family: sylfaen;'>{$n}</td>
            <td style='border: 1px solid black;font-size:13.5px;text-align:center;font-family: sylfaen;'>{$date}</td>
            <td style='border: 1px solid black;font-size:13.5px;text-align:center;font-family: sylfaen;'>{$amount}</td>
            <td style='border: 1px solid black;font-size:13.5px;text-align:center;font-family: sylfaen;'>{$remaining}</td>
        </tr>";
    }
    
    public static function generateFromArray($data, $totalAmount, $isEnglish = false) {
        $html = self::getTableHeader($isEnglish);
        $remaining = $totalAmount;
        $count = 0;
        $totalItems = count($data);
        
        foreach ($data as $item) {
            if (!isset($item["TARIGI"]) || !isset($item["TANXA_NUMBR"])) continue;
            
            $count++;
            $amount = $item["TANXA_NUMBR"];
            $remaining = round($remaining - $amount, 2);
            
            if ($count === $totalItems) {
                $remaining = 0;
            }
            
            $html .= self::getTableRow(
                $count,
                htmlspecialchars($item["TARIGI"]),
                Utils::formatNumber($amount),
                Utils::formatNumber($remaining)
            );
        }
        
        return $html . "</tbody></table>";
    }
    
    public static function generateFromJson($jsonData, $isEnglish = false) {
        $html = self::getTableHeader($isEnglish);
        $count = 0;
        
        foreach ($jsonData["data"] ?? [] as $row) {
            if (empty($row["amount"])) continue;
            
            $count++;
            $html .= self::getTableRow(
                $count,
                htmlspecialchars($row["date"] ?? ''),
                $row["amount"],
                $row["leftToPay"] ?? ''
            );
        }
        
        return $html . "</tbody></table>";
    }
}

// ===== დოკუმენტის ვარიაბილების ბილდერი =====
class DocumentVariablesBuilder {
    private $variables = [];
    
    public function addVariable($name, $value, $type = null) {
        $var = [
            'VarName' => $name,
            'VarValue' => $this->sanitizeValue($value)
        ];
        
        if ($type) {
            $var['VarType'] = $type;
        }
        
        $this->variables[] = $var;
        return $this;
    }
    
    public function addArrayWithSuffix($array, $suffix) {
        foreach ($array as $key => $value) {
            if (!is_array($value)) {
                $this->addVariable('$' . $key . $suffix . '$', $value);
            }
        }
        return $this;
    }
    
    public function addDealVariables($deal) {
        foreach ($deal as $key => $value) {
            if (!is_array($value)) {
                $this->addVariable('$' . $key . '$', $value);
            }
        }
        return $this;
    }
    
    public function getVariables() {
        return $this->variables;
    }
    
    public function merge(array $otherVariables) {
        $this->variables = array_merge($this->variables, $otherVariables);
        return $this;
    }
    
    private function sanitizeValue($value) {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value === "0" || $value === 0) {
            return "0";
        }
        return $value;
    }
}

// ===== ფაილების მენეჯერი =====
class FileManager {
    
    public static function getFilesFromDisk($parentId) {
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
    
    public static function processFileArray($files) {
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
    
    public static function getFileById($fileId) {
        global $DB;
        
        $fileId = (int)$fileId;
        $dbRes = $DB->query("SELECT * FROM b_disk_object WHERE PARENT_ID = " . DISK_PARENT_ID . " AND ID = {$fileId}");
        return $dbRes->Fetch();
    }
    
    public static function getFileContent($bitrixFileId) {
        $filePath = CFile::GetPath($bitrixFileId);
        $fullPath = $_SERVER["DOCUMENT_ROOT"] . $filePath;
        
        if (!file_exists($fullPath)) {
            return null;
        }
        
        return file_get_contents($fullPath);
    }
    
    public static function getFileNameFromDiskObject($diskName) {
        $parts = explode('$', $diskName);
        $name = $parts[5] ?? $parts[2] ?? 'document';
        return explode('.', $name)[0];
    }
}

// ===== API კლიენტი =====
class DocumentApiClient {
    
    public static function generateDocument($fileData, $variables, $convertToPdf = true) {
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
            throw new Exception("Error connecting to API");
        }
        
        $decodedData = base64_decode($response);
        
        if ($decodedData === false) {
            throw new Exception("Error decoding Base64 response");
        }
        
        return $decodedData;
    }
    
    public static function outputFile($fileData, $fileName, $isPdf = true) {
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
}

// ===== დოკუმენტის პროცესორი =====
class DocumentProcessor {
    private $deal;
    private $contacts = [];
    private $company = [];
    private $variablesBuilder;
    
    public function __construct($dealId) {
        $this->deal = CrmDataManager::getDealInfo($dealId);
        $this->variablesBuilder = new DocumentVariablesBuilder();
        $this->loadRelatedData();
    }
    
    private function loadRelatedData() {
        // კონტაქტების ჩატვირთვა
        $contactIds = CrmDataManager::getDealContactIds($this->deal["ID"] ?? 0);
        foreach ($contactIds as $contactId) {
            $this->contacts[] = CrmDataManager::getContactInfo($contactId);
        }
        
        // კომპანიის ჩატვირთვა
        $this->company = CrmDataManager::getCompanyInfo($this->deal["COMPANY_ID"] ?? 0);
        
        // თუ კონტაქტი/კომპანია ცარიელია, ტექნიკური მონაცემები
        if (empty($this->contacts)) {
            $this->contacts[] = $this->getEmptyArray(CrmDataManager::getContactInfo(TECH_CONTACT_ID));
        }
        if (empty($this->company)) {
            $this->company = $this->getEmptyArray(CrmDataManager::getCompanyInfo(TECH_COMPANY_ID));
        }
    }
    
    private function getEmptyArray($template) {
        return array_fill_keys(array_keys($template), '');
    }
    
    public function buildVariables() {
        // კონტაქტების გაერთიანება
        $combinedContacts = $this->combineArrays($this->contacts);
        
        // დილის ცვლადები
        $this->variablesBuilder->addDealVariables($this->deal);
        
        // კონტაქტის ცვლადები
        $this->variablesBuilder->addArrayWithSuffix($combinedContacts, '_USER');
        
        // კომპანიის ცვლადები
        $this->variablesBuilder->addArrayWithSuffix($this->company, '_COM');
        
        // ძველი მფლობელები
        $this->processOldOwners();
        
        // ახალი მფლობელები
        $this->processNewOwners();
        
        // თარიღები
        $this->variablesBuilder
            ->addVariable('$TODAY_DATE$', date("d/m/Y"))
            ->addVariable('$DATE_WORD$', Utils::getGeorgianDate());
        
        // ბანკის კონვერტაცია
        $this->convertBankField();
        
        // მრავლობითი კონტაქტები
        $this->processMultipleContacts();
        
        // გაერთიანებული დილის დამუშავება
        $this->processMergedDeal();
        
        return $this->variablesBuilder->getVariables();
    }
    
    private function combineArrays($arrays, $separator = '/') {
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
    
    private function processOldOwners() {
        $oldContacts = [];
        $oldCompanies = [];
        
        if (!empty($this->deal["UF_CRM_1710484037"])) {
            foreach ($this->deal["UF_CRM_1710484037"] as $value) {
                $parts = explode("_", $value);
                
                if ($parts[0] === "CO") {
                    $oldCompanies[] = CrmDataManager::getCompanyInfo($parts[1]);
                } elseif ($parts[0] === "C") {
                    $oldContacts[] = CrmDataManager::getContactInfo($parts[1]);
                }
            }
        }
        
        $combinedOldContacts = $this->combineArrays($oldContacts, ',');
        $combinedOldCompanies = $this->combineArrays($oldCompanies, ',');
        
        if (!empty($combinedOldContacts)) {
            $this->variablesBuilder->addArrayWithSuffix($combinedOldContacts, '_OLD_CON');
        }
        if (!empty($combinedOldCompanies)) {
            $this->variablesBuilder->addArrayWithSuffix($combinedOldCompanies, '_OLD_COM');
        }
    }
    
    private function processNewOwners() {
        $newContacts = [];
        
        if (!empty($this->deal["UF_CRM_1657805299"])) {
            foreach ($this->deal["UF_CRM_1657805299"] as $contactId) {
                $newContacts[] = CrmDataManager::getContactInfo($contactId);
            }
        }
        
        $combinedNewContacts = $this->combineArrays($newContacts, ',');
        
        if (!empty($combinedNewContacts)) {
            $this->variablesBuilder->addArrayWithSuffix($combinedNewContacts, '_NEW_CON');
        }
    }
    
    private function processMultipleContacts() {
        $count = count($this->contacts);
        
        for ($i = 0; $i < $count && $i < 3; $i++) {
            $suffix = '_USER_' . ($i + 1);
            $this->variablesBuilder->addArrayWithSuffix($this->contacts[$i], $suffix);
        }
    }
    
    private function processMergedDeal() {
        if (!empty($this->deal["UF_CRM_1745316640"])) {
            $mergedDealId = $this->deal["UF_CRM_1745316640"][0];
            $mergedDeal = CrmDataManager::getDealInfo($mergedDealId);
            
            foreach ($mergedDeal as $key => $value) {
                if (!is_array($value)) {
                    $this->variablesBuilder->addVariable('$' . $key . '_2$', $value);
                }
            }
        }
    }
    
    private function convertBankField() {
        $bankMapping = [
            "3210" => "საქართველოს ბანკი",
            "3211" => "თი-ბი-სი ბანკი"
        ];
        
        $bankValue = $this->deal["UF_CRM_1733485628918"] ?? '';
        
        if (isset($bankMapping[$bankValue])) {
            $this->variablesBuilder->addVariable('$UF_CRM_1733485628918$', $bankMapping[$bankValue]);
        }
    }
    
    public function getDeal() {
        return $this->deal;
    }
}

// ===== მთავარი ლოგიკა =====
global $USER, $DB;

$authManager = new AuthManager($USER);
$authManager->authorizeAsAdmin();

// GET პარამეტრები
$dealId = isset($_GET["dealid"]) ? (int)$_GET["dealid"] : 0;
$spaId = isset($_GET["spaID"]) ? (int)$_GET["spaID"] : 0;

// ფაილების ჩატვირთვა
$filesArr = FileManager::getFilesFromDisk(DISK_PARENT_ID);
$fullArr = FileManager::processFileArray($filesArr);

// დილის და პროდუქტების ჩატვირთვა
$deal = [];
$Product = [];

if ($dealId) {
    $Product = CrmDataManager::getDealProducts($dealId);
    $deal = CrmDataManager::getDealInfo($dealId);
}

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
            // დოკუმენტის პროცესორის ინიციალიზაცია
            $processor = new DocumentProcessor($postDealId);
            $variables = $processor->buildVariables();
            
            // ფაილის მიღება
            $fileObject = FileManager::getFileById($docId);
            
            if (!$fileObject) {
                throw new Exception("File not found");
            }
            
            $fileContent = FileManager::getFileContent($fileObject["FILE_ID"]);
            
            if (!$fileContent) {
                throw new Exception("Could not read file content");
            }
            
            $fileName = FileManager::getFileNameFromDiskObject($fileObject["NAME"]);
            
            // დოკუმენტის გენერაცია
            $generatedFile = DocumentApiClient::generateDocument($fileContent, $variables, $isPdf);
            
            // ფაილის გამოტანა
            DocumentApiClient::outputFile($generatedFile, $fileName, $isPdf);
            
        } catch (Exception $e) {
            error_log("Document generation error: " . $e->getMessage());
            $errorCode = $e->getMessage();
            $emptyGet = true;
        }
    }
}

// ავტორიზაციის აღდგენა
$authManager->restoreOriginalUser();
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>დოკუმენტების ჩამოტვირთვა</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4CAF50, #45a049);
            --primary-shadow: rgba(76, 175, 80, 0.3);
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        #maincontent {
            margin-top: 100px;
        }
        
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
        
        .form-select, button {
            border-radius: 10px;
        }
        
        .buttonDiv {
            display: flex;
            justify-content: center;
        }
        
        .buttonDoc {
            background: var(--primary-gradient);
            color: white;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            padding: 12px 28px;
            cursor: pointer;
            box-shadow: 0 4px 12px var(--primary-shadow);
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
            box-shadow: 0 6px 16px rgba(76, 175, 80, 0.4);
        }
        
        .buttonDoc:active {
            transform: translateY(0);
            box-shadow: 0 3px 8px rgba(76, 175, 80, 0.2);
        }
        
        .gtranslate_wrapper {
            margin-left: 450px;
        }
    </style>
</head>
<body>

<div id="maincontent" class="maincontent">
    <div class="form-card">
        <h4 class="mb-4 text-center fw-semibold">📄 დოკუმენტების ჩამოტვირთვა</h4>

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
                    <button type="submit" class="buttonDoc">
                        📥 ჩამოტვირთვა
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    // PHP-დან მიღებული მონაცემები
    const CONFIG = {
        fullArr: <?= json_encode($fullArr) ?>,
        dealId: <?= json_encode($dealId) ?>,
        emptyGet: <?= json_encode($emptyGet) ?>,
        errorCode: <?= json_encode($errorCode) ?>,
        popupMode: <?= json_encode($popupMode) ?>
    };
    
    // DOM ელემენტები
    const elements = {
        language: document.getElementById('language'),
        pipeline: document.getElementById('pipeline'),
        docs: document.getElementById('docs'),
        dealId: document.getElementById('deal_id'),
        popup: document.getElementById('popup'),
        mainContent: document.getElementById('maincontent')
    };
    
    /**
     * დოკუმენტების ფილტრაცია ენისა და პაიპლაინის მიხედვით
     */
    function filterDocuments() {
        const selectedLanguage = elements.language.value.toLowerCase();
        const selectedPipeline = elements.pipeline.value;
        
        // გაწმენდა
        elements.docs.innerHTML = '<option value="" disabled selected>დოკუმენტი</option>';
        
        // ფილტრაცია
        const filtered = CONFIG.fullArr
            .filter(item => {
                if (!item || !item.LANG || !item.PIPE) return false;
                
                return item.LANG.toLowerCase() === selectedLanguage &&
                       (item.PIPE === selectedPipeline || item.PIPE === "ყველა");
            })
            .sort((a, b) => (a.NAME || '').localeCompare(b.NAME || ''));
        
        // ოფშენების დამატება
        if (filtered.length === 0) {
            elements.docs.innerHTML += '<option disabled>დოკუმენტები არ მოიძებნა</option>';
        } else {
            filtered.forEach(item => {
                if (item.ID && item.NAME) {
                    const option = document.createElement('option');
                    option.value = item.ID;
                    option.textContent = item.NAME;
                    elements.docs.appendChild(option);
                }
            });
        }
    }
    
    /**
     * GTranslate-ის ინიციალიზაცია
     */
    function initGTranslate() {
        window.gtranslateSettings = {
            default_language: "ka",
            languages: ["ka", "en", "ru"],
            wrapper_selector: ".gtranslate_wrapper",
            flag_size: 24
        };
        
        const script = document.createElement('script');
        script.src = "https://cdn.gtranslate.net/widgets/latest/flags.js";
        script.defer = true;
        document.body.appendChild(script);
        
        const wrapper = document.createElement('div');
        wrapper.className = 'gtranslate_wrapper';
        elements.mainContent.parentNode.insertBefore(wrapper, elements.mainContent);
    }
    
    /**
     * ინიციალიზაცია
     */
    function init() {
        if (CONFIG.emptyGet) {
            alert(CONFIG.errorCode || 'შეცდომა');
            elements.mainContent.innerHTML = '';
            return;
        }

        elements.dealId.value = CONFIG.dealId || '';
        elements.popup.value = CONFIG.popupMode;
        
        filterDocuments();
        
        // GTranslate 3 წამში
        setTimeout(initGTranslate, 3000);
    }

    // გლობალური ფუნქცია (თავსებადობისთვის)
    window.filterDocuments = filterDocuments;
    window.filter_documents = filterDocuments;
    window.change_pipe = filterDocuments;
    
    // დაწყება
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

</body>
</html>