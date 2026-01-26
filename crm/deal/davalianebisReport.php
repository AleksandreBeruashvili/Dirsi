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

    // if (!empty($project)) {
    //     $arFilter["UF_CRM_1761658516561"] = $project;
    // }

    $res_deals = CCrmDeal::GetList($arSort, $arFilter, array("ID", "DATE_CREATE", "CONTACT_ID","COMPANY_ID", "TITLE","CONTACT_FULL_NAME","OPPORTUNITY","COMPANY_TITLE","UF_CRM_1761658532158","ASSIGNED_BY_ID","UF_CRM_1762948106980", "UF_CRM_1764317005", "UF_CRM_1761658516561"));
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
        "IBLOCK_ID"             => 20,
        "PROPERTY_DEAL"         => $deals_IDs,
        "<=PROPERTY_TARIGI"     => date("Y-m-d")
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
$filterProject = isset($_GET['project']) ? trim($_GET['project']) : '';
// $filterPhase = isset($_GET['phase']) ? trim($_GET['phase']) : '';
$filterBlock = isset($_GET['block']) ? trim($_GET['block']) : '';
$filterResponsible = isset($_GET['responsible']) ? trim($_GET['responsible']) : '';

// Build the filter array with applied filters
$arFilter = ["STAGE_ID" => "WON"];

// Apply project filter (assuming UF_CRM_1761658516561 is the project field)
if (!empty($filterProject)) {
    $arFilter["UF_CRM_1761658516561"] = $filterProject;
}

// Apply phase filter (you'll need to replace with actual field code)
// if (!empty($filterPhase)) {
//     $arFilter["UF_CRM_1764317005"] = $filterPhase; 
// }

// Apply block filter (you'll need to replace with actual field code)
if (!empty($filterBlock)) {
    $arFilter["UF_CRM_1762948106980"] = $filterBlock; // Replace with actual block field code
}

// Apply responsible filter
if (!empty($filterResponsible)) {
    $arFilter["ASSIGNED_BY_ID"] = $filterResponsible;
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
$gadaxdebi = getGadaxdebi($deals_IDs);
$products = getProducts($deals_IDs);

// Get unique values for filter dropdowns
$projects = array();
// $phases = array();
$blocks = array();
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

        // if (!empty($deal["UF_CRM_1764317005"]) && !in_array($deal["UF_CRM_1764317005"], $phases)) {
        //     $phases[] = $deal["UF_CRM_1764317005"];
        // }

        if (!empty($deal["UF_CRM_1762948106980"]) && !in_array($deal["UF_CRM_1762948106980"], $blocks) && $deal["UF_CRM_1762948106980"] !== 'P') {
            $blocks[] = $deal["UF_CRM_1762948106980"];
        }
    }
}

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
    $prodType = $deal["UF_CRM_1761658532158"];
    $bedroomAmount = isset($products[$deal["ID"]]["Bedrooms"]) ? $products[$deal["ID"]]["Bedrooms"] : '';

    if ($deal["UF_CRM_1762948106980"] === 'P') {
        $prodType = "გარე პარკინგი";
    }

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
    }
}

$apartmentTypes = ["Flat (1 Bed.)", "Flat (2 Bed.)", "Flat (3 Bed.)"];
$generalProducts = [];
$apartmentProducts = [];

foreach ($resArray as $prodType => $data) {
    if (in_array($prodType, $apartmentTypes)) {
        $apartmentProducts[$prodType] = $data;
    } else {
        $generalProducts[$prodType] = $data;
    }
}

// Calculate totals for general products
$generalTotals = [
    "jamuriGayidvebisAmount" => 0,
    "jamuriDaricxvaUpToToday" => 0,
    "jamuriGadaxdaUpToToday" => 0,
    "mimdinareDavalianeba" => 0
];

foreach ($generalProducts as $data) {
    $generalTotals["jamuriGayidvebisAmount"] += $data["jamuriGayidvebisAmount"];
    $generalTotals["jamuriDaricxvaUpToToday"] += $data["jamuriDaricxvaUpToToday"];
    $generalTotals["jamuriGadaxdaUpToToday"] += $data["jamuriGadaxdaUpToToday"];
    $generalTotals["mimdinareDavalianeba"] += $data["mimdinareDavalianeba"];
}

// Calculate totals for apartments
$apartmentTotals = [
    "jamuriGayidvebisAmount" => 0,
    "jamuriDaricxvaUpToToday" => 0,
    "jamuriGadaxdaUpToToday" => 0,
    "mimdinareDavalianeba" => 0
];

foreach ($apartmentProducts as $data) {
    $apartmentTotals["jamuriGayidvebisAmount"] += $data["jamuriGayidvebisAmount"];
    $apartmentTotals["jamuriDaricxvaUpToToday"] += $data["jamuriDaricxvaUpToToday"];
    $apartmentTotals["jamuriGadaxdaUpToToday"] += $data["jamuriGadaxdaUpToToday"];
    $apartmentTotals["mimdinareDavalianeba"] += $data["mimdinareDavalianeba"];
}

ob_end_clean();

?>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>  <!-- delete column-->
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
    .filter-group select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
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
            
            <!-- <div class="filter-group">
                <label for="phase">Phase:</label>
                <select name="phase" id="phase">
                    <option value="">All Phases</option>
                    <?php foreach ($phases as $phase): ?>
                        <option value="<?= htmlspecialchars($phase) ?>" <?= $filterPhase == $phase ? 'selected' : '' ?>>
                            <?= htmlspecialchars($phase) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div> -->
            
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
        <?php if (empty($generalProducts)): ?>
        <tr>
            <td colspan="5" style="text-align: center;">No data available</td>
        </tr>
        <?php else: ?>
            <?php foreach ($generalProducts as $prodType => $data): ?>
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