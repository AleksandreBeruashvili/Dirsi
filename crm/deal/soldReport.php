<?
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CJSCore::Init(array("jquery"));

$APPLICATION->SetTitle("Sold Report");

// ------------------------------FUNCTIONS---------------------------------
function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDealsByFilter($arFilter, $arrSelect=array()) {
    // If no select fields specified, get all fields
    if (empty($arrSelect)) {
        $arrSelect = false;
    }
    
    $res = CCrmDeal::GetListEx(array("ID" => "ASC"), $arFilter, false, false, $arrSelect);
    
    $resArr = array();
    while($arDeal = $res->Fetch()){
        $resArr[$arDeal["ID"]] = $arDeal;
    }
    return $resArr;
}

function getNBG_inventory($date){

    $url="https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies?Currencies=USD&date={$date}";
    
    $seb = file_get_contents($url);
    
    $seb = json_decode($seb);
    
    $seb_currency=$seb[0]->currencies[0]->rate;
    
    return $seb_currency;
}


function getUserName ($id) {
    $res = CUser::GetByID($id)->Fetch();

    return $res["NAME"]." ".$res["LAST_NAME"];
}

function getContactInfo($contactId) {
    $arContact = array();
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $contactId), array());
    if($arContact = $res->Fetch()){
        $PHONE=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'MOBILE|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $MAIL=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'EMAIL', 'VALUE_TYPE' => 'HOME|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $arContact["PHONE"] = $PHONE["VALUE"];
        $arContact["EMAIL"] = $MAIL["VALUE"];
        return $arContact;
    }
    return $arContact;
}

function getProducts($dealIds) {
    // Return empty array if no deals
    if (empty($dealIds)) {
        return array();
    }
    
    $arFilter = array(
            "IBLOCK_ID" => 14,
            "PROPERTY_STATUS" => "გაყიდული",
            "PROPERTY_DEAL" => $dealIds
    );

    $arSelect = [];
    $sort= array();
    $count = 99999;
    $nbg = getNBG_inventory(date("Y-m-d"));
    $arElements = array();

    $res = CIBlockElement::GetList($sort, $arFilter, false, array("nPageSize" => $count), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp){
            $fieldId = $arProp["CODE"];
            $arPushs[$fieldId] = $arProp["VALUE"];
        }

        if ($arPushs["OWNER_CONTACT"]) {
            $arPushs["OWNER_CONTACT_NAME"] = getContactInfo($arPushs["OWNER_CONTACT"])["FULL_NAME"];
        }

        if ($arPushs["DEAL_RESPONSIBLE"]) {
            $arPushs["DEAL_RESPONSIBLE_NAME"] = getUserName($arPushs["DEAL_RESPONSIBLE"]);
        }

        $price = CPrice::GetBasePrice($arPushs["ID"]);
        $arPushs["PRICE"] = isset($price["PRICE"]) ? round($price["PRICE"], 2) : 0;
        $arPushs['PRICE_GEL'] = round($arPushs["PRICE"] * $nbg,2);

        $arElements[$arPushs["ID"]] = $arPushs;
    }
    return $arElements;
}

function getSourceNameById($sourceId) {
    $list = CCrmStatus::GetStatusList('SOURCE');
    return $list[$sourceId] ?? null;
}

function getUniqueValues($products, $field) {
    $values = array();
    foreach ($products as $product) {
        if (!empty($product[$field]) && !in_array($product[$field], $values) && $product[$field] !== "Flat") {
            $values[] = $product[$field];
        }
    }
    sort($values);
    return $values;
}

// ------------------------------MAIN CODE---------------------------------

// Get filter values from request
$filterProject     = isset($_GET['project'])      ? trim($_GET['project'])      : '';
$filterBlock       = isset($_GET['block'])        ? trim($_GET['block'])        : '';
$filterBuilding    = isset($_GET['building'])     ? trim($_GET['building'])     : '';
$filterFloor       = isset($_GET['floor'])        ? trim($_GET['floor'])        : '';
$filterProductType = isset($_GET['prodType'])     ? trim($_GET['prodType'])     : '';
$filterResponsible = isset($_GET['responsible'])  ? trim($_GET['responsible'])  : '';
$filterSource      = isset($_GET['source'])       ? trim($_GET['source'])       : '';

// Store original date values for display in HTML inputs (YYYY-MM-DD format)
$displayDateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$displayDateTo   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';

// Convert dates for Bitrix filter (DD/MM/YYYY format)
$filterDateFrom = '';
$filterDateTo = '';

if ($displayDateFrom !== '') {
    $dateObj = DateTime::createFromFormat('Y-m-d', $displayDateFrom);
    if ($dateObj) {
        $filterDateFrom = $dateObj->format('d/m/Y');
    }
}
if ($displayDateTo !== '') {
    $dateObj = DateTime::createFromFormat('Y-m-d', $displayDateTo);
    if ($dateObj) {
        $filterDateTo = $dateObj->format('d/m/Y');
    }
}

// Build the filter array with applied filters
$arFilter = ["STAGE_ID" => "WON"];

// Apply project filter 
if ($filterProject !== '') {
    $arFilter["UF_CRM_1761658516561"] = $filterProject;
}

// Apply block filter 
if ($filterBlock !== '') {
    $arFilter["UF_CRM_1766560177934"] = $filterBlock; 
}

// Apply building filter 
if ($filterBuilding !== '') {
    $arFilter["UF_CRM_1766736693236"] = $filterBuilding; 
}

// Apply floor filter 
if ($filterFloor !== '') {
    $arFilter["UF_CRM_1761658577987"] = $filterFloor; 
}

// Apply prod type filter 
if ($filterProductType !== '') {
    if (str_contains($filterProductType, "Flat")) {
        preg_match('/\d+/', $filterProductType, $matches);
        if (isset($matches[0])) {
            $bedroom = $matches[0];
            $arFilter["UF_CRM_1770888201367"] = $bedroom;
        }
    } else {
        $arFilter["UF_CRM_1761658532158"] = $filterProductType; 
    }
}

// Apply responsible filter
if ($filterResponsible !== '') {
    $arFilter["ASSIGNED_BY_ID"] = $filterResponsible;
}

// Apply date filters - only when converted values exist
if ($filterDateFrom !== '') {
    $arFilter[">=UF_CRM_1762416342444"] = $filterDateFrom; 
}

if ($filterDateTo !== '') {
    $arFilter["<=UF_CRM_1762416342444"] = $filterDateTo; 
}

if (!empty($filterSource)) {
    $arFilter["SOURCE_ID"] = $filterSource; 
}

$deals = getDealsByFilter($arFilter);
$dealIds = array_keys($deals);

$products = getProducts($dealIds);

// Get unique values for filter dropdowns
$projects = getUniqueValues($products, 'PROJECT');
$blocks = array_diff(getUniqueValues($products, 'KORPUSIS_NOMERI_XE3NX2'), ['P']);
$responsibles = getUniqueValues($products, 'DEAL_RESPONSIBLE_NAME');
$buildings = getUniqueValues($products, 'BUILDING');
$floors = getUniqueValues($products, 'FLOOR');
$prodTypes = ["Flat (1 Bed.)", "Flat (2 Bed.)", "Flat (3 Bed.)"];
$prodTypes = array_merge(getUniqueValues($products, 'PRODUCT_TYPE'), $prodTypes);
$sourceIds = getUniqueValues($deals, 'SOURCE_ID');
$sources = array();
foreach ($sourceIds as $sourceId) {
    $sources[$sourceId] = getSourceNameById($sourceId);
}

// Apply filters
$filteredProducts = array();
foreach ($products as $product) {
    $match = true;
    
    if ($filterProject !== '' && $product['PROJECT'] != $filterProject) {
        $match = false;
    }
    if ($filterBlock !== '' && $product['KORPUSIS_NOMERI_XE3NX2'] != $filterBlock) {
        $match = false;
    }
    if ($filterResponsible !== '' && $product['DEAL_RESPONSIBLE_NAME'] != $filterResponsible) {
        $match = false;
    }
    
    if ($match) {
        $filteredProducts[$product["ID"]] = $product;
    }
}

$resArray = [];

foreach ($filteredProducts as $product) {
    $prodType = $product["PRODUCT_TYPE"];

    if ($product["KORPUSIS_NOMERI_XE3NX2"] === "P") {
        if (!isset($resArray["გარე პარკინგი"])) {
            $resArray["გარე პარკინგი"] = ["num" => 0, "total_area" => 0, "price" => 0];
        }
        $resArray["გარე პარკინგი"]["num"]++;
        $resArray["გარე პარკინგი"]["total_area"] += (float) ($product["TOTAL_AREA"] ?? 0);
        $resArray["გარე პარკინგი"]["price"] += (float) ($product["PRICE"] ?? 0);
    } else if ($product["PRODUCT_TYPE"] === $prodType) {
        if (!isset($resArray[$prodType])) {
            $resArray[$prodType] = ["num" => 0, "total_area" => 0, "price" => 0, "KVM_PRICE" => 0];
        }
        $resArray[$prodType]["num"]++;
        $resArray[$prodType]["total_area"] += (float) ($product["TOTAL_AREA"] ?? 0);
        $resArray[$prodType]["price"] += (float) ($product["PRICE"] ?? 0);
        $resArray[$prodType]["KVM_PRICE"] += (float) ($product["KVM_PRICE"] ?? 0);

        if ($product["PRODUCT_TYPE"] === "Flat") {
            $bedroom = $product["Bedrooms"] ?? '';
            if ($bedroom === "1") {
                $prodTypeAnothaOne = "Flat (1 Bed.)";
            } else if ($bedroom === "2") {
                $prodTypeAnothaOne = "Flat (2 Bed.)";
            } else if ($bedroom === "3") {
                $prodTypeAnothaOne = "Flat (3 Bed.)";
            } else {
                continue;
            }
    
            if (!isset($resArray[$prodTypeAnothaOne])) {
                $resArray[$prodTypeAnothaOne] = ["num" => 0, "total_area" => 0, "price" => 0, "KVM_PRICE" => 0];
            }
            $resArray[$prodTypeAnothaOne]["num"]++;
            $resArray[$prodTypeAnothaOne]["total_area"] += (float) ($product["TOTAL_AREA"] ?? 0);
            $resArray[$prodTypeAnothaOne]["price"] += (float) ($product["PRICE"] ?? 0);
            $resArray[$prodTypeAnothaOne]["KVM_PRICE"] += (float) ($product["KVM_PRICE"] ?? 0);
        }
    }
}

foreach ($resArray as $prodType => $infos) {
    if (isset($infos["num"]) && $infos["num"] > 0) {
        if (str_contains($prodType, "Flat") || $prodType === "Commercial") {
            $resArray[$prodType]["average_price"] = round($infos["KVM_PRICE"]/$infos["num"], 2);
        } else {
            $resArray[$prodType]["average_price"] = round($infos["price"]/$infos["num"], 2);
        }
    } else {
        $resArray[$prodType]["average_price"] = 0;
    }
}

ob_end_clean();
?>

<style>
    .filter-container {
        background-color: #f8f9fa;
        padding: 20px;
        margin-bottom: 30px;
        border-radius: 5px;
        border: 1px solid #dee2e6;
    }
    
    .filter-row {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .filter-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #495057;
    }
    
    .filter-group select, .filter-group input[type="date"] {
        width: 95%;
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
        background-color: white;
    }
    
    .filter-buttons {
        display: flex;
        gap: 10px;
    }
    
    .btn {
        padding: 8px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: bold;
    }
    
    .btn-primary {
        background-color: #007bff;
        color: white;
    }
    
    .btn-primary:hover {
        background-color: #0056b3;
    }
    
    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background-color: #545b62;
    }
    
    .sales-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 40px;
        font-family: Arial, sans-serif;
    }
    
    .sales-table th {
        background-color: #2c3e50;
        color: white;
        padding: 12px;
        text-align: left;
        font-weight: bold;
    }
    
    .sales-table td {
        padding: 10px;
        border: 1px solid #ddd;
    }
    
    .sales-table tr:nth-child(even) {
        background-color: #f2f2f2;
    }
    
    .sales-table tr:hover {
        background-color: #ddd;
    }
    
    .total-row {
        background-color: #c9ccd0 !important;
        font-weight: bold;
    }
    
    h2 {
        font-family: Arial, sans-serif;
        color: #333;
        margin-top: 20px;
    }
</style>


<div class="filter-container">
    <form method="GET" action="">
        <div class="filter-row">
            <div class="filter-group">
                <label for="project">Project:</label>
                <select name="project" id="project">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= htmlspecialchars($project) ?>" <?= $filterProject == $project ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="block">Block:</label>
                <select name="block" id="block">
                    <option value="">All Blocks</option>
                    <?php foreach ($blocks as $block): ?>
                        <option value="<?= htmlspecialchars($block) ?>" <?= $filterBlock == $block ? 'selected' : '' ?>>
                            <?= htmlspecialchars($block) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="building">Building:</label>
                <select name="building" id="building">
                    <option value="">All Buildings</option>
                    <?php foreach ($buildings as $building): ?>
                        <option value="<?= htmlspecialchars($building) ?>" <?= $filterBuilding == $building ? 'selected' : '' ?>>
                            <?= htmlspecialchars($building) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="floor">Floor:</label>
                <select name="floor" id="floor">
                    <option value="">All Floors</option>
                    <?php foreach ($floors as $floor): ?>
                        <option value="<?= htmlspecialchars($floor) ?>" <?= $filterFloor == $floor ? 'selected' : '' ?>>
                            <?= htmlspecialchars($floor) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="prodType">Product Type:</label>
                <select name="prodType" id="prodType">
                    <option value="">All Product Types</option>
                    <?php foreach ($prodTypes as $prodType): ?>
                        <option value="<?= htmlspecialchars($prodType) ?>" <?= $filterProductType == $prodType ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prodType) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="responsible">Responsible:</label>
                <select name="responsible" id="responsible">
                    <option value="">All Responsible</option>
                    <?php foreach ($responsibles as $id => $name): ?>
                        <option value="<?= htmlspecialchars($id) ?>" <?= $filterResponsible == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="source">Source:</label>
                <select name="source" id="source">
                    <option value="">All Sources</option>
                    <?php foreach ($sources as $id => $source): ?>
                        <option value="<?= htmlspecialchars($id) ?>" <?= $filterSource == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($source) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Payment date range filters with ORIGINAL format for HTML inputs -->
            <div class="filter-group">
                <label for="date_from">Date From:</label>
                <input type="date" name="date_from" id="date_from"
                       value="<?= htmlspecialchars($displayDateFrom) ?>">
            </div>

            <div class="filter-group">
                <label for="date_to">Date To:</label>
                <input type="date" name="date_to" id="date_to"
                       value="<?= htmlspecialchars($displayDateTo) ?>">
            </div>
            
            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'">Clear</button>
            </div>
        </div>
    </form>
</div>

<?php
// Calculate totals for first table (excluding apartment breakdowns)
$apartmentTypes = ["Flat (1 Bed.)", "Flat (2 Bed.)", "Flat (3 Bed.)"];
$total_num = 0;
$total_area = 0;
$total_price = 0;

foreach ($resArray as $prodType => $infos) {
    // Skip apartment breakdown types for the total
    if (in_array($prodType, $apartmentTypes)) continue;
    
    $total_num += isset($infos['num']) ? $infos['num'] : 0;
    $total_area += isset($infos['total_area']) ? $infos['total_area'] : 0;
    $total_price += isset($infos['price']) ? $infos['price'] : 0;
}
?>

<h2>Solds Summary</h2>
<table class="sales-table">
    <thead>
        <tr>
            <th>Product Type</th>
            <th>Amount</th>
            <th>Total Area (m²)</th>
            <th>Total Price ($)</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        foreach ($resArray as $prodType => $infos): 
            // Skip apartment breakdown types as they're shown separately
            if (in_array($prodType, $apartmentTypes)) continue;
        ?>
        <tr>
            <td><?= $prodType ?></td>
            <td><?= isset($infos['num']) ? $infos['num'] : 0 ?></td>
            <td><?= number_format(isset($infos['total_area']) ? $infos['total_area'] : 0, 2) ?></td>
            <td>$<?= number_format(isset($infos['price']) ? $infos['price'] : 0, 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td>TOTAL</td>
            <td><?= $total_num ?></td>
            <td><?= number_format($total_area, 2) ?></td>
            <td>$<?= number_format($total_price, 2) ?></td>
        </tr>
    </tbody>
</table>

<?php
// Apartment breakdown table data
$apt_total_num = 0;
$apt_total_area = 0;
$apt_total_price = 0;

foreach ($apartmentTypes as $aptType) {
    if (isset($resArray[$aptType])) {
        $apt_total_num += isset($resArray[$aptType]['num']) ? $resArray[$aptType]['num'] : 0;
        $apt_total_area += isset($resArray[$aptType]['total_area']) ? $resArray[$aptType]['total_area'] : 0;
        $apt_total_price += isset($resArray[$aptType]['price']) ? $resArray[$aptType]['price'] : 0;
    }
}
?>

<h2>Apartment Infos By Flat Bedrooms</h2>
<table class="sales-table">
    <thead>
        <tr>
            <th>Bedroom Amount</th>
            <th>Amount</th>
            <th>Total Area (m²)</th>
            <th>Total Price ($)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($apartmentTypes as $aptType): ?>
            <?php if (isset($resArray[$aptType])): ?>
            <tr>
                <td><?= $aptType ?></td>
                <td><?= isset($resArray[$aptType]['num']) ? $resArray[$aptType]['num'] : 0 ?></td>
                <td><?= number_format(isset($resArray[$aptType]['total_area']) ? $resArray[$aptType]['total_area'] : 0, 2) ?></td>
                <td>$<?= number_format(isset($resArray[$aptType]['price']) ? $resArray[$aptType]['price'] : 0, 2) ?></td>
            </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        <tr class="total-row">
            <td>TOTAL</td>
            <td><?= $apt_total_num ?></td>
            <td><?= number_format($apt_total_area, 2) ?></td>
            <td>$<?= number_format($apt_total_price, 2) ?></td>
        </tr>
    </tbody>
</table>

<h2>Average Prices</h2>
<table class="sales-table">
    <thead>
        <tr>
            <th>Product Type</th>
            <th>Average Price ($)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($resArray as $prodType => $infos): ?>
        <tr>
            <td><?= $prodType ?></td>
            <td>$<?= number_format(isset($infos['average_price']) ? $infos['average_price'] : 0, 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>