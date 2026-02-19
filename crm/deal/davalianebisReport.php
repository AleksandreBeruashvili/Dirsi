<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Cashflow Report");


// ======================== FUNCTIONS ========================

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDealsByFilter($arFilter, $project = '', $arSelect = array(), $arSort = array("ID"=>"DESC")) {
    $result["deals_data"] = array();
    $result["deals_IDs"] = array();

    $res_deals = CCrmDeal::GetList($arSort, $arFilter, array("ID", "DATE_CREATE", "CONTACT_ID","COMPANY_ID", "TITLE","CONTACT_FULL_NAME","OPPORTUNITY","COMPANY_TITLE","UF_CRM_1761658532158","ASSIGNED_BY_ID","UF_CRM_1766560177934", "UF_CRM_1764317005", "UF_CRM_1761658516561", "UF_CRM_1766736693236", "UF_CRM_1761658577987", "UF_CRM_1770888201367", "UF_CRM_1770640981002"));
    while($arDeal = $res_deals->Fetch()) {
        $arDeal["payment"] = 0;
        $result["deals_data"][$arDeal["ID"]] = $arDeal;
        $result["deals_IDs"][] = $arDeal["ID"];
    }
    return (count($result["deals_IDs"]) > 0) ? $result : false;
}

function getDaricxvebi($deals_IDs) {
    if (empty($deals_IDs)) return array();
    
    $daricxvebi = array();

    // daricxvebi
    $arSelect = Array("ID", "IBLOCK_SECTION_ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $arFilter = array(
        "IBLOCK_ID"     => 20,
        "PROPERTY_DEAL" => $deals_IDs,
    );

    $res = CIBlockElement::GetList(Array("PROPERTY_TARIGI" => "ASC"), $arFilter, false, Array("nPageSize" => 99999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();

        $amount = (float) str_replace("|USD","",$arProps["TANXA"]["VALUE"]);

        $daricxvebi[] = array(
            "DEAL_ID" => $arProps["DEAL"]["VALUE"],
            "daricxva_date" => $arProps["TARIGI"]["VALUE"],
            "daricxva_amount" => $amount
        );
    }

    return $daricxvebi;
}

function getGadaxdebi($deals_IDs){
    if (empty($deals_IDs)) return array();

    $gadaxdebi = array();

    // gadaxdebi
    $arSelect = Array("ID", "IBLOCK_SECTION_ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $arFilter = array(
        "IBLOCK_ID"             => 21,
        "PROPERTY_DEAL"         => $deals_IDs
    );

    $res = CIBlockElement::GetList(Array("date" => "ASC"), $arFilter, false, Array("nPageSize" => 99999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();

        // --- FIX: Normalize DEAL ID ---
        $dealID = trim($arProps["DEAL"]["VALUE"]);

        if ($dealID === "") {
            continue;
        }

        $amount = (float) str_replace("|USD","",$arProps["TANXA"]["VALUE"]);

        $gadaxdebi[] = array(
            "DEAL_ID" => $dealID,
            "gadaxda_date" => $arProps["date"]["VALUE"],
            "gadaxda_amount" => $amount
        );
    }
    
    return $gadaxdebi;
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
    if (empty($dealIds)) return array();
    
    $arFilter = array(
            "IBLOCK_ID" => 14,
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

        $arElements[$arPushs["OWNER_DEAL"]] = $arPushs;
    }
    return $arElements;
}

// =============================== MAIN CODE ===============================

// Get filter values from request
$filterProject     = isset($_GET['project'])      ? trim($_GET['project'])      : '';
$filterBlock       = isset($_GET['block'])        ? trim($_GET['block'])        : '';
$filterBuilding    = isset($_GET['building'])     ? trim($_GET['building'])     : '';
$filterFloor       = isset($_GET['floor'])        ? trim($_GET['floor'])        : '';
$filterProductType = isset($_GET['prodType'])     ? trim($_GET['prodType'])     : '';
$filterResponsible = isset($_GET['responsible'])  ? trim($_GET['responsible'])  : '';
// --- NEW FILTERS ---
$filterDateFrom    = isset($_GET['date_from'])    ? trim($_GET['date_from'])    : '';
$filterDateTo      = isset($_GET['date_to'])      ? trim($_GET['date_to'])      : '';
$filterCustomField = isset($_GET['custom_field']) ? trim($_GET['custom_field']) : '';

// Build the filter array with applied filters
$arFilter = ["STAGE_ID" => "WON"];

// Apply project filter 
if (!empty($filterProject)) {
    $arFilter["UF_CRM_1761658516561"] = $filterProject;
}

// Apply block filter 
if (!empty($filterBlock)) {
    $arFilter["UF_CRM_1766560177934"] = $filterBlock; 
}

// Apply building filter 
if (!empty($filterBuilding)) {
    $arFilter["UF_CRM_1766736693236"] = $filterBuilding; 
}

// Apply floor filter 
if (!empty($filterFloor)) {
    $arFilter["UF_CRM_1761658577987"] = $filterFloor; 
}

// Apply prod type filter 
if (!empty($filterProductType)) {
    if (str_contains($filterProductType, "Flat")) {
        preg_match('/\d+/', $filterProductType, $matches);
        $bedroom = $matches[0];
        $arFilter["UF_CRM_1770888201367"] = $bedroom; 
    } else {
        $arFilter["UF_CRM_1761658532158"] = $filterProductType; 
    }
}

// Apply responsible filter
if (!empty($filterResponsible)) {
    $arFilter["ASSIGNED_BY_ID"] = $filterResponsible;
}

// Apply custom string field filter (LIKE search)
if (!empty($filterCustomField)) {
    $arFilter["%UF_CRM_1770640981002"] = $filterCustomField;
}

// Get deals with applied filters
$result = getDealsByFilter($arFilter);

// Check if we have results
if ($result === false) {
    $deals = array();
    $deals_IDs = array();
} else {
    $deals = $result["deals_data"];
    $deals_IDs = $result["deals_IDs"];
}

$daricxvebi = getDaricxvebi($deals_IDs);
$gadaxdebi  = getGadaxdebi($deals_IDs);

// Filter gadaxdebi by payment date range if provided
if (!empty($filterDateFrom) || !empty($filterDateTo)) {
    $gadaxdebi = array_filter($gadaxdebi, function($g) use ($filterDateFrom, $filterDateTo) {
        $raw = trim($g["gadaxda_date"]);
        if (empty($raw)) return false;

        // Try multiple formats
        $dateStr = null;

        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $raw, $m)) {
            // DD.MM.YYYY
            $dateStr = $m[3] . '-' . $m[2] . '-' . $m[1];
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            // YYYY-MM-DD (with or without time)
            $dateStr = $m[1] . '-' . $m[2] . '-' . $m[3];
        } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $raw, $m)) {
            // MM/DD/YYYY
            $dateStr = $m[3] . '-' . $m[1] . '-' . $m[2];
        } else {
            // Last resort: let PHP parse it
            $ts = strtotime($raw);
            if ($ts === false) return true; // can't parse, don't filter out
            $dateStr = date('Y-m-d', $ts);
        }

        if (!empty($filterDateFrom) && $dateStr < $filterDateFrom) return false;
        if (!empty($filterDateTo)   && $dateStr > $filterDateTo)   return false;
        return true;
    });

    $daricxvebi = array_filter($daricxvebi, function($d) use ($filterDateFrom, $filterDateTo) {
        $raw = trim($d["daricxva_date"]);
        if (empty($raw)) return false;

        $dateStr = null;

        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $raw, $m)) {
            $dateStr = $m[3] . '-' . $m[2] . '-' . $m[1];
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            $dateStr = $m[1] . '-' . $m[2] . '-' . $m[3];
        } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $raw, $m)) {
            $dateStr = $m[3] . '-' . $m[1] . '-' . $m[2];
        } else {
            $ts = strtotime($raw);
            if ($ts === false) return true;
            $dateStr = date('Y-m-d', $ts);
        }

        if (!empty($filterDateFrom) && $dateStr < $filterDateFrom) return false;
        if (!empty($filterDateTo)   && $dateStr > $filterDateTo)   return false;
        return true;
    });
}

$products = getProducts($deals_IDs);

// Get unique values for filter dropdowns
$projects = array();
$blocks = array();
$buildings = array();
$floors = array();
$prodTypes = ["Flat (1 Bed.)", "Flat (2 Bed.)", "Flat (3 Bed.)"];
$responsibles = array();

// Fetch all deals to populate filter options
$allDealsResult = getDealsByFilter(["STAGE_ID" => "WON"]);
if ($allDealsResult !== false) {
    $allDeals = $allDealsResult["deals_data"];
    foreach ($allDeals as $deal) {
        // Collect projects (assuming UF_CRM_1761658516561 is project field)
        if (!empty($deal["UF_CRM_1761658516561"]) && !in_array($deal["UF_CRM_1761658516561"], $projects)) {
            $projects[] = $deal["UF_CRM_1761658516561"];
        }
        
        // Collect responsible users
        if (!empty($deal["ASSIGNED_BY_ID"]) && !in_array($deal["ASSIGNED_BY_ID"], $responsibles)) {
            $responsibles[$deal["ASSIGNED_BY_ID"]] = getUserName($deal["ASSIGNED_BY_ID"]);
        }

        if (!empty($deal["UF_CRM_1766560177934"]) && !in_array($deal["UF_CRM_1766560177934"], $blocks) && $deal["UF_CRM_1766560177934"] !== 'P') {
            $blocks[] = $deal["UF_CRM_1766560177934"];
        }

        if (!empty($deal["UF_CRM_1766736693236"]) && !in_array($deal["UF_CRM_1766736693236"], $buildings)) {
            $buildings[] = $deal["UF_CRM_1766736693236"];
        }

        if (!empty($deal["UF_CRM_1761658577987"]) && !in_array($deal["UF_CRM_1761658577987"], $floors)) {
            $floors[] = $deal["UF_CRM_1761658577987"];
        }

        if (!empty($deal["UF_CRM_1761658532158"]) && !in_array($deal["UF_CRM_1761658532158"], $prodTypes) && $deal["UF_CRM_1761658532158"] !== "Flat") {
            $prodTypes[] = $deal["UF_CRM_1761658532158"];
        }
    }
}

sort($projects);
sort($blocks);
sort($buildings);
usort($floors, function($a, $b) {
    return (int)$a - (int)$b;
});

asort($responsibles);

// Process deals data
foreach ($deals as &$deal) {
    $deal["jamuriDaricxvaUpToToday"] = 0;
    $deal["jamuriGadaxdaUpToToday"]  = 0;
}
unset($deal);

foreach ($daricxvebi as $d) {
    $dealID = trim($d["DEAL_ID"]);
    if (!isset($deals[$dealID])) {
        continue;
    }
    $deals[$dealID]["jamuriDaricxvaUpToToday"] += $d["daricxva_amount"];
}

foreach ($gadaxdebi as $g) {
    $dealID = trim($g["DEAL_ID"]);
    if (!isset($deals[$dealID])) {
        continue;
    }
    $deals[$dealID]["jamuriGadaxdaUpToToday"] += $g["gadaxda_amount"];
}

$resArray = [];
foreach ($deals as $deal) {
    $product = $products[$deal["ID"]];
    $prodType = $product["PRODUCT_TYPE"];

    if (!$prodType) continue;

    if ($product["PRODUCT_TYPE"] === "Flat") {
        if ($product["Bedrooms"] === "1") {
            $prodTypeAnothaOne = "Flat (1 Bed.)";
        } else if ($product["Bedrooms"] === "2") {
            $prodTypeAnothaOne = "Flat (2 Bed.)";
        } else if ($product["Bedrooms"] === "3") {
            $prodTypeAnothaOne = "Flat (3 Bed.)";
        } else {
            continue;
        }

        if (!isset($resArray[$prodTypeAnothaOne])) {
            $resArray[$prodTypeAnothaOne] = ["jamuriGayidvebisAmount" => 0,
                                            "jamuriDaricxvaUpToToday" => 0,
                                            "jamuriGadaxdaUpToToday" => 0,
                                            "mimdinareDavalianeba" => 0];
        }

        $resArray[$prodTypeAnothaOne]["jamuriGayidvebisAmount"] += (float) ($deal["OPPORTUNITY"] ?? 0);
        $resArray[$prodTypeAnothaOne]["jamuriDaricxvaUpToToday"] += (float) ($deal["jamuriDaricxvaUpToToday"] ?? 0);
        $resArray[$prodTypeAnothaOne]["jamuriGadaxdaUpToToday"] +=  (float) ($deal["jamuriGadaxdaUpToToday"] ?? 0);
        $resArray[$prodTypeAnothaOne]["mimdinareDavalianeba"] = $resArray[$prodTypeAnothaOne]["jamuriDaricxvaUpToToday"] - $resArray[$prodTypeAnothaOne]["jamuriGadaxdaUpToToday"];
    } else {
        if (!isset($resArray[$prodType])) {
            $resArray[$prodType] = ["jamuriGayidvebisAmount" => 0,
                                    "jamuriDaricxvaUpToToday" => 0,
                                    "jamuriGadaxdaUpToToday" => 0,
                                    "mimdinareDavalianeba" => 0];
        }

        $resArray[$prodType]["jamuriGayidvebisAmount"] += (float) ($deal["OPPORTUNITY"] ?? 0);
        $resArray[$prodType]["jamuriDaricxvaUpToToday"] += (float) ($deal["jamuriDaricxvaUpToToday"] ?? 0);
        $resArray[$prodType]["jamuriGadaxdaUpToToday"] +=  (float) ($deal["jamuriGadaxdaUpToToday"] ?? 0);
        $resArray[$prodType]["mimdinareDavalianeba"] = $resArray[$prodType]["jamuriDaricxvaUpToToday"] - $resArray[$prodType]["jamuriGadaxdaUpToToday"];
    }
}

$generalTotals = [
    "jamuriGayidvebisAmount"  => 0,
    "jamuriDaricxvaUpToToday" => 0,
    "jamuriGadaxdaUpToToday"  => 0,
    "mimdinareDavalianeba"    => 0,
];

foreach ($resArray as $data) {
    $generalTotals["jamuriGayidvebisAmount"]  += $data["jamuriGayidvebisAmount"];
    $generalTotals["jamuriDaricxvaUpToToday"] += $data["jamuriDaricxvaUpToToday"];
    $generalTotals["jamuriGadaxdaUpToToday"]  += $data["jamuriGadaxdaUpToToday"];
}
$generalTotals["mimdinareDavalianeba"] = $generalTotals["jamuriDaricxvaUpToToday"] - $generalTotals["jamuriGadaxdaUpToToday"];

ob_end_clean();

?>


<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>

<style>
    .filter-container {
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        min-width: 180px;
    }
    .filter-group label {
        font-weight: 600;
        margin-bottom: 5px;
        color: #333;
    }
    .filter-group select,
    .filter-group input[type="date"],
    .filter-group input[type="text"] {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        height: 38px;
        box-sizing: border-box;
    }
    .filter-buttons {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }
    .btn {
        padding: 8px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: background-color 0.3s;
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
    .cashflow-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
        font-family: Arial, sans-serif;
    }
    .cashflow-table th {
        background-color: #2c3e50;
        color: white;
        padding: 12px;
        text-align: left;
        border: 1px solid #ddd;
    }
    .cashflow-table td {
        padding: 10px;
        border: 1px solid #ddd;
    }
    .cashflow-table tr:nth-child(even) {
        background-color: #f2f2f2;
    }
    .cashflow-table tr:hover {
        background-color: #e8e8e8;
    }
    .total-row {
        background-color: #d4edda !important;
        font-weight: bold;
    }
    .table-title {
        font-size: 20px;
        font-weight: bold;
        margin: 20px 0 10px 0;
        color: #333;
    }
    .amount {
        text-align: right;
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

            <!-- NEW: Payment date range filters -->
            <div class="filter-group">
                <label for="date_from">Payment Date From:</label>
                <input type="date" name="date_from" id="date_from"
                       value="<?= htmlspecialchars($filterDateFrom) ?>">
            </div>

            <div class="filter-group">
                <label for="date_to">Payment Date To:</label>
                <input type="date" name="date_to" id="date_to"
                       value="<?= htmlspecialchars($filterDateTo) ?>">
            </div>

            <!-- NEW: Custom field string filter -->
            <div class="filter-group">
                <label for="custom_field">Contract №:</label>
                <input type="text" name="custom_field" id="custom_field"
                       value="<?= htmlspecialchars($filterCustomField) ?>"
                       placeholder="Search...">
            </div>
            
            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'">Clear</button>
            </div>
        </div>
    </form>
</div>

<div class="table-title">Products</div>
<table class="cashflow-table">
    <thead>
        <tr>
            <th>Product Type</th>
            <th class="amount">Total Sold Prices (by Deal)</th>
            <th class="amount">Total Plan</th>
            <th class="amount">Total Payment</th>
            <th class="amount">Current Debt</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($resArray)): ?>
        <tr>
            <td colspan="5" style="text-align: center;">No data available</td>
        </tr>
        <?php else: ?>
            <?php foreach ($resArray as $prodType => $data): ?>
            <tr>
                <td><?php echo htmlspecialchars($prodType); ?></td>
                <td class="amount"><?php echo number_format($data["jamuriGayidvebisAmount"], 2); ?></td>
                <td class="amount"><?php echo number_format($data["jamuriDaricxvaUpToToday"], 2); ?></td>
                <td class="amount"><?php echo number_format($data["jamuriGadaxdaUpToToday"], 2); ?></td>
                <td class="amount"><?php echo number_format($data["mimdinareDavalianeba"], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>ჯამი</td>
                <td class="amount"><?php echo number_format($generalTotals["jamuriGayidvebisAmount"], 2); ?></td>
                <td class="amount"><?php echo number_format($generalTotals["jamuriDaricxvaUpToToday"], 2); ?></td>
                <td class="amount"><?php echo number_format($generalTotals["jamuriGadaxdaUpToToday"], 2); ?></td>
                <td class="amount"><?php echo number_format($generalTotals["mimdinareDavalianeba"], 2); ?></td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>