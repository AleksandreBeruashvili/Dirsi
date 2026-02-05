<?
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CJSCore::Init(array("jquery"));

$APPLICATION->SetTitle("Sold Report");

// ------------------------------FUNCTIONS---------------------------------
function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDealsByFilter($arFilter = array(), $arrSelect=array()) {
    $res = CCrmDeal::GetListEx(array("ID" => "ASC"), $arFilter, $arrSelect);
    
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

        if ($arPushs["OWNER_DEAL"]) {
            $arElements[$arPushs["OWNER_DEAL"]] = $arPushs;
        } else continue;
        // $arElements[$arPushs["DEAL"]][] = $arPushs;
    }
    return $arElements;
}

function getUniqueValues($products, $field) {
    $values = array();
    foreach ($products as $product) {
        if (!empty($product[$field]) && !in_array($product[$field], $values)) {
            $values[] = $product[$field];
        }
    }
    sort($values);
    return $values;
}

function getSourcesList() {
    $arResult = array();
    $arStatuses = CCrmStatus::GetStatusList('SOURCE');
    
    foreach($arStatuses as $id => $name) {
        $arResult[$id] = $name;
    }
    
    return $arResult;
}

function getUsersList() {
    $arResult = array();
    $arFilter = array(
        'ACTIVE' => 'Y'
    );
    $arSelect = array('ID', 'NAME', 'LAST_NAME');
    
    $rsUsers = CUser::GetList(($by = 'NAME'), ($order = 'ASC'), $arFilter, array('SELECT' => $arSelect));
    
    while($arUser = $rsUsers->Fetch()) {
        $arResult[$arUser['ID']] = $arUser['NAME'] . ' ' . $arUser['LAST_NAME'];
    }
    
    return $arResult;
}

function getMarketingStats($products, $deals) {
    $stats = array();
    $sourcesList = getSourcesList();
    $marketingCostebi = getCIBlockElement();
    
    $allowedSources = ["Facebook messenger", "Facebook comment", "Deals from: Facebook leads", "Instagram", "Instagram* Direct - instagram", "Facebook*: Comments - comments", "Web lead", "Whatsapp"];
    $facebookSources = ["Facebook messenger", "Facebook comment", "Deals from: Facebook leads", "Instagram", "Instagram* Direct - instagram", "Facebook*: Comments - comments", "Whatsapp"];
    $googleSources = ["Web lead"];

    foreach ($deals as $dealId => $deal) {
        // Get UTM parameters from the deal
        $sourceId = trim((string)($deal["SOURCE_ID"] ?? ''));
        $source = isset($sourcesList[$sourceId]) ? $sourcesList[$sourceId] : 'No Source';

        if (!in_array($source, $allowedSources)) continue;

        if (in_array($source, $facebookSources)) {
            $marketingSource = "Facebook";
        } else {
            $marketingSource = "Google Ads";
        }

        $utmCampaign = isset($deal['UF_CRM_1769498174']) ? $deal['UF_CRM_1769498174'] : 'None';
        
        // Get marketing costs
        $marketingCost = isset($marketingCostebi[$marketingSource]['BUDGET']) ? floatval($marketingCostebi[$marketingSource]['BUDGET']) : 0;
        
        if (!isset($stats[$marketingSource])) {
            $stats[$marketingSource] = array(
                'MARKETING_SOURCE' => $marketingSource,
                'CAMPAIGN' => $utmCampaign,
                'COST' => 0,
                'LEADS' => 0,
                'RESERVED' => 0,
                'SOLD' => 0,
                'REVENUE' => 0,
                'SOURCES' => array()
            );
        }
        
        if (!isset($stats[$marketingSource]['SOURCES'][$source])) {
            $stats[$marketingSource]['SOURCES'][$source] = array(
                'SOURCE' => $source,
                'MARKETING_COST' => $marketingSource,
                'CAMPAIGN' => $utmCampaign,
                'COST' => 0,
                'LEADS' => 0,
                'RESERVED' => 0,
                'SOLD' => 0,
                'REVENUE' => 0
            );
        }

        $stats[$marketingSource]['COST'] = $marketingCost;
        $stats[$marketingSource]['LEADS']++;
        $stats[$marketingSource]['SOURCES'][$source]['LEADS']++;
        
        // Count reserved and sold
        if ($deal['STAGE_ID'] === 'WON') {
            $stats[$marketingSource]['SOLD']++;
            $stats[$marketingSource]['SOURCES'][$source]['SOLD']++;
        }

        if (isset($products[$dealId]) && $products[$dealId]["STATUS"] === 'დაჯავშნილი') {
            $stats[$marketingSource]['RESERVED']++;
            $stats[$marketingSource]['SOURCES'][$source]['RESERVED']++;
        }
        
        // Calculate revenue from this deal's product
        if (isset($products[$dealId])) {
            $stats[$marketingSource]['REVENUE'] += floatval($products[$dealId]["PRICE"]);
            $stats[$marketingSource]['SOURCES'][$source]['REVENUE'] += floatval($products[$dealId]["PRICE"]);
        }
    }
    
    // Calculate derived metrics
    foreach ($stats as &$row) {
        $row['CPL'] = $row['LEADS'] > 0 ? $row['COST'] / $row['LEADS'] : 0;
        $row['CR'] = $row['LEADS'] > 0 ? ($row['SOLD'] / $row['LEADS']) * 100 : 0;
        $row['COST_PER_APT'] = $row['SOLD'] > 0 ? $row['COST'] / $row['SOLD'] : 0;
        $row['ROI'] = $row['COST'] > 0 ? (($row['REVENUE'] - $row['COST']) / $row['COST']) * 100 : 0;
        
        // Calculate cost allocation per source (divide total cost by number of sources)
        $numSources = count($row['SOURCES']);
        $costPerSource = $numSources > 0 ? $row['COST'] / $numSources : 0;
        
        // Calculate metrics for each source
        foreach ($row['SOURCES'] as &$sourceData) {
            // Allocate portion of total marketing cost to this source
            $sourceData['COST'] = round($costPerSource, 2);
            
            // CPL for this specific source
            $sourceData['CPL'] = $sourceData['LEADS'] > 0 ? $costPerSource / $sourceData['LEADS'] : 0;
            
            // Conversion rate for this source
            $sourceData['CR'] = $sourceData['LEADS'] > 0 ? ($sourceData['SOLD'] / $sourceData['LEADS']) * 100 : 0;
            
            // Cost per apartment sold through this source
            $sourceData['COST_PER_APT'] = $sourceData['SOLD'] > 0 ? $costPerSource / $sourceData['SOLD'] : 0;
            
            // ROI for this source (revenue from source minus allocated cost)
            $sourceData['ROI'] = $costPerSource > 0 ? (($sourceData['REVENUE'] - $costPerSource) / $costPerSource) * 100 : 0;
        }
    }
    
    return $stats;
}

function getCIBlockElement(){
    $marketingCostebi = array();

    // marketingCostebi
    $arSelect = Array();
    $arFilter = array(
        "IBLOCK_ID"             => 29
    );
    $res = CIBlockElement::GetList(Array("PROPERTY_TARIGI" => "ASC"), $arFilter, false, Array("nPageSize" => 99999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp){
            $fieldId = $arProp["CODE"];
            $arPushs[$fieldId] = $arProp["VALUE"];
        }
        $marketingCostebi[$arPushs["SOURCE"]] = $arPushs;
    }

    return $marketingCostebi;
}

// ------------------------------MAIN CODE---------------------------------

// Get filter values from request
$filterProject = isset($_GET['project']) ? $_GET['project'] : '';
$filterPhase = isset($_GET['phase']) ? $_GET['phase'] : '';
$filterBlock = isset($_GET['block']) ? $_GET['block'] : '';
$filterResponsible = isset($_GET['responsible']) ? $_GET['responsible'] : '';
$filterSource = isset($_GET['source']) ? $_GET['source'] : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build deal filter based on user input
$dealFilter = array();

if ($filterResponsible) {
    $dealFilter['ASSIGNED_BY_ID'] = $filterResponsible;
}

if ($filterSource) {
    $dealFilter['SOURCE_ID'] = $filterSource;
}

if ($filterDateFrom) {
    $dealFilter['>=DATE_CREATE'] = $filterDateFrom . ' 00:00:00';
}

if ($filterDateTo) {
    $dealFilter['<=DATE_CREATE'] = $filterDateTo . ' 23:59:59';
}

$deals = getDealsByFilter($dealFilter, [
        "ID",
        "SOURCE_ID",
        "STAGE_ID",
        "UF_CRM_1769498174",
        "UF_CRM_MARKETING_COST",
        "ASSIGNED_BY_ID",
        "DATE_CREATE"
    ]
);
$dealIds = array_keys($deals);

$products = getProducts($dealIds);

// Get unique values for filter dropdowns
$projects = getUniqueValues($products, 'PROJECT');
$phases = getUniqueValues($products, 'phase');
$blocks = array_diff(getUniqueValues($products, 'KORPUSIS_NOMERI_XE3NX2'), ['P']);
// $responsibles = getUniqueValues($products, 'DEAL_RESPONSIBLE_NAME');

// Get lists for filters
// $usersList = getUsersList();
$responsibles = getUniqueValues($products, 'DEAL_RESPONSIBLE_NAME');
$sourcesList = getSourcesList();

// After getting products
$marketingStats = getMarketingStats($products, $deals);
ob_end_clean();
?>

<style>
    .report-section {
        margin: 30px 0;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .section-title {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px 30px;
        font-size: 24px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .filter-section {
        background: #f8f9fa;
        padding: 25px 30px;
        border-bottom: 1px solid #dee2e6;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
    }

    .filter-label {
        font-size: 13px;
        font-weight: 600;
        color: #495057;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-input,
    .filter-select {
        padding: 10px 14px;
        border: 2px solid #dee2e6;
        border-radius: 6px;
        font-size: 14px;
        color: #495057;
        background: white;
        transition: all 0.2s ease;
    }

    .filter-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .filter-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background: #5a6268;
    }

    .table-wrapper {
        overflow-x: auto;
        padding: 0;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    .data-table thead {
        background: #f8f9fa;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .data-table th {
        padding: 16px 12px;
        text-align: left;
        font-weight: 600;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
        white-space: nowrap;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
    }

    .data-table tbody tr {
        transition: all 0.2s ease;
    }

    .data-table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .data-table td {
        padding: 14px 12px;
        border-bottom: 1px solid #e9ecef;
        color: #212529;
    }

    .marketing-source-row {
        cursor: pointer;
        background: linear-gradient(to right, #f8f9fa 0%, #ffffff 100%);
        font-weight: 600;
    }

    .marketing-source-row:hover {
        background: linear-gradient(to right, #e9ecef 0%, #f8f9fa 100%);
    }

    .marketing-source-row td:first-child {
        position: relative;
        padding-left: 40px;
    }

    .marketing-source-row td:first-child::before {
        content: '▶';
        position: absolute;
        left: 20px;
        transition: transform 0.3s ease;
        color: #667eea;
        font-size: 12px;
    }

    .marketing-source-row.expanded td:first-child::before {
        transform: rotate(90deg);
    }

    .source-detail-row {
        display: none;
        background: #f1f3f5;
        animation: slideDown 0.3s ease;
    }

    .source-detail-row.visible {
        display: table-row;
    }

    .source-detail-row td {
        font-size: 13px;
        color: #495057;
    }

    .source-detail-row td:first-child {
        font-weight: 500;
        padding-left: 40px;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .toggle-button {
        width: 100%;
        padding: 15px;
        background: #667eea;
        color: white;
        border: none;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .toggle-button:hover {
        background: #5568d3;
    }

    .positive-metric {
        color: #28a745;
        font-weight: 600;
    }

    .negative-metric {
        color: #dc3545;
        font-weight: 600;
    }

    .metric-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge-success {
        background: #d4edda;
        color: #155724;
    }

    .badge-warning {
        background: #fff3cd;
        color: #856404;
    }

    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }
</style>

<div class="report-section">
    <div class="section-title">📊 Marketing Performance Dashboard</div>

    <div class="filter-section">
        <form method="GET" action="">
            <div class="filter-grid">
                <div class="filter-group">
                    <label class="filter-label">📅 Date From</label>
                    <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($filterDateFrom) ?>">
                </div>

                <div class="filter-group">
                    <label class="filter-label">📅 Date To</label>
                    <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($filterDateTo) ?>">
                </div>

                <div class="filter-group">
                    <label class="filter-label">👤 Responsible</label>
                    <select name="responsible" class="filter-select">
                        <option value="">All Responsibles</option>
                        <?php foreach ($responsibles as $responsible): ?>
                            <option value="<?= htmlspecialchars($responsible) ?>" <?= $filterResponsible == $responsible ? 'selected' : '' ?>>
                                <?= htmlspecialchars($responsible) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">📍 Source</label>
                    <select name="source" class="filter-select">
                        <option value="">All Sources</option>
                        <?php foreach($sourcesList as $sourceId => $sourceName): ?>
                            <option value="<?= $sourceId ?>" <?= $filterSource == $sourceId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sourceName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'">🔄 Reset</button>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Marketing Source</th>
                    <th>Campaign</th>
                    <th>Total Cost</th>
                    <th>Leads</th>
                    <th>CPL</th>
                    <th>Reserved</th>
                    <th>Sold</th>
                    <th>CR</th>
                    <th>Cost Per Apt.</th>
                    <th>Revenue</th>
                    <th>ROI</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $index = 0;
                foreach ($marketingStats as $marketingSource => $row): 
                    $rowId = 'marketing-' . $index;
                    $roiClass = $row['ROI'] > 0 ? 'positive-metric' : 'negative-metric';
                ?>
                    <tr class="marketing-source-row" onclick="toggleSourceDetails('<?= $rowId ?>', this)">
                        <td><?= htmlspecialchars($row['MARKETING_SOURCE']) ?></td>
                        <td><?= htmlspecialchars($row['CAMPAIGN']) ?></td>
                        <td>$<?= number_format($row['COST'], 2) ?></td>
                        <td><span class="metric-badge badge-warning"><?= $row['LEADS'] ?></span></td>
                        <td>$<?= number_format($row['CPL'], 2) ?></td>
                        <td><?= $row['RESERVED'] ?></td>
                        <td><span class="metric-badge badge-success"><?= $row['SOLD'] ?></span></td>
                        <td><?= number_format($row['CR'], 2) ?>%</td>
                        <td>$<?= number_format($row['COST_PER_APT'], 2) ?></td>
                        <td>$<?= number_format($row['REVENUE'], 2) ?></td>
                        <td class="<?= $roiClass ?>"><?= number_format($row['ROI'], 2) ?>%</td>
                    </tr>
                    
                    <?php foreach ($row['SOURCES'] as $source => $sourceData): 
                        $sourceRoiClass = $sourceData['ROI'] > 0 ? 'positive-metric' : 'negative-metric';
                    ?>
                        <tr class="source-detail-row" data-parent="<?= $rowId ?>">
                            <td><?= htmlspecialchars($sourceData['SOURCE']) ?></td>
                            <td><?= htmlspecialchars($sourceData['CAMPAIGN']) ?></td>
                            <td>$<?= number_format($sourceData['COST'], 2) ?></td>
                            <td><?= $sourceData['LEADS'] ?></td>
                            <td>$<?= number_format($sourceData['CPL'], 2) ?></td>
                            <td><?= $sourceData['RESERVED'] ?></td>
                            <td><?= $sourceData['SOLD'] ?></td>
                            <td><?= number_format($sourceData['CR'], 2) ?>%</td>
                            <td>$<?= number_format($sourceData['COST_PER_APT'], 2) ?></td>
                            <td>$<?= number_format($sourceData['REVENUE'], 2) ?></td>
                            <td class="<?= $sourceRoiClass ?>"><?= number_format($sourceData['ROI'], 2) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    
                <?php 
                    $index++;
                endforeach; 
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleSourceDetails(rowId, element) {
    // Toggle expanded class on the marketing source row
    element.classList.toggle('expanded');
    
    // Find all source detail rows for this marketing source
    const detailRows = document.querySelectorAll(`.source-detail-row[data-parent="${rowId}"]`);
    
    // Toggle visibility
    detailRows.forEach(row => {
        row.classList.toggle('visible');
    });
}

// Optional: Toggle table button functionality if you still have it
function toggleTable(tableId, button) {
    const wrapper = document.querySelector('.table-wrapper');
    wrapper.classList.toggle('collapsed');
    
    if (wrapper.classList.contains('collapsed')) {
        button.textContent = 'მეტის ნახვა';
    } else {
        button.textContent = 'დამალვა';
    }
}
</script>