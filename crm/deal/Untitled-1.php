<?
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CJSCore::Init(array("jquery"));

$APPLICATION->SetTitle("Status Report");

// ------------------------------FUNCTIONS---------------------------------
function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDealsByFilter($arFilter,$arrSelect=array()) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), $arFilter, $arrSelect);
    
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

// CHANGE 3: updated getProducts signature + attach deal fields
function getProducts($dealIds, $deals) {
    $arFilter = array(
        "IBLOCK_ID" => 14,
        "PROPERTY_DEAL" => $dealIds
    );

    $arSelect = [];
    $sort = array();
    $count = 99999;
    $nbg = getNBG_inventory(date("Y-m-d"));
    $arElements = array();

    $res = CIBlockElement::GetList($sort, $arFilter, false, array("nPageSize" => $count), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) {
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
        $arPushs['PRICE_GEL'] = round($arPushs["PRICE"] * $nbg, 2);

        // Attach deal OPPORTUNITY and deal TOTAL_AREA
        $dealId = $arPushs["OWNER_DEAL"];
        if ($dealId && isset($deals[$dealId])) {
            $arPushs["DEAL_OPPORTUNITY"]  = (float)($deals[$dealId]["OPPORTUNITY"] ?? 0);
            $arPushs["DEAL_TOTAL_AREA"]   = (float)($deals[$dealId]["UF_CRM_1761658608306"] ?? 0);
        } else {
            $arPushs["DEAL_OPPORTUNITY"]  = 0;
            $arPushs["DEAL_TOTAL_AREA"]   = 0;
        }

        $arElements[$arPushs["ID"]] = $arPushs;
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

// ------------------------------MAIN CODE---------------------------------

// Get filter values from request
$filterProject = isset($_GET['project']) ? $_GET['project'] : '';
$filterPhase = isset($_GET['phase']) ? $_GET['phase'] : '';
$filterBlock = isset($_GET['block']) ? $_GET['block'] : '';
$filterResponsible = isset($_GET['responsible']) ? $_GET['responsible'] : '';

$arFilter = array("STAGE_ID" => "WON");
$deals = getDealsByFilter($arFilter);
$dealIds = array_keys($deals);

$products = getProducts($dealIds, $deals);

// Get unique values for filter dropdowns
$projects = getUniqueValues($products, 'PROJECT');
$phases = getUniqueValues($products, 'phase');
$blocks = array_diff(getUniqueValues($products, 'KORPUSIS_NOMERI_XE3NX2'), ['P']);
$responsibles = getUniqueValues($products, 'DEAL_RESPONSIBLE_NAME');

// Apply filters
$filteredProducts = array();
foreach ($products as $product) {
    $match = true;
    
    if ($filterProject && $product['PROJECT'] != $filterProject) {
        $match = false;
    }
    if ($filterPhase && $product['phase'] != $filterPhase) {
        $match = false;
    }
    if ($filterBlock && $product['KORPUSIS_NOMERI_XE3NX2'] != $filterBlock) {
        $match = false;
    }
    if ($filterResponsible && $product['DEAL_RESPONSIBLE_NAME'] != $filterResponsible) {
        $match = false;
    }
    
    if ($match) {
        $filteredProducts[$product["ID"]] = $product;
    }
}

$resArray = [];

foreach ($filteredProducts as $product) {
    $prodType   = $product["PRODUCT_TYPE"];
    $prodStatus = $product["STATUS"];

    $types = [];

    if ($product["KORPUSIS_NOMERI_XE3NX2"] === "P") {
        $types[] = "გარე ავტოსადგომი";
    } else {
        if ($prodType === "ავტოსადგომი") {
            $types[] = "შიდა ავტოსადგომი";
        } else {
            $types[] = $prodType;
            if ($prodType === "ბინა") {
                if ($product["Bedrooms"] === "1")      $types[] = "ბინა (1 საძ.)";
                elseif ($product["Bedrooms"] === "2")  $types[] = "ბინა (2 საძ.)";
                elseif ($product["Bedrooms"] === "3")  $types[] = "ბინა (3 საძ.)";
            }
        }
    }

    foreach ($types as $t_key) {
        $resArray[$t_key][$prodStatus]["num"]        = ($resArray[$t_key][$prodStatus]["num"]        ?? 0) + 1;
        $resArray[$t_key][$prodStatus]["total_area"] = ($resArray[$t_key][$prodStatus]["total_area"] ?? 0) + (float)($product["TOTAL_AREA"] ?? 0);
        $resArray[$t_key][$prodStatus]["price"]      = ($resArray[$t_key][$prodStatus]["price"]      ?? 0) + (float)($product["PRICE"]      ?? 0);

        // For avg price calc — deal-side data (გაყიდული only)
        $resArray[$t_key][$prodStatus]["deal_price"] = ($resArray[$t_key][$prodStatus]["deal_price"] ?? 0) + (float)($product["DEAL_OPPORTUNITY"] ?? 0);
        $resArray[$t_key][$prodStatus]["deal_area"]  = ($resArray[$t_key][$prodStatus]["deal_area"]  ?? 0) + (float)($product["DEAL_TOTAL_AREA"]  ?? 0);
        // deal_num increments for every product (used for parking avg)
        $resArray[$t_key][$prodStatus]["deal_num"]   = ($resArray[$t_key][$prodStatus]["deal_num"]   ?? 0) + 1;
    }
}
// Define explicit display order
$typeOrder = [
    "ბინა"               => 0,
    "ბინა (1 საძ.)"      => 1,
    "ბინა (2 საძ.)"      => 2,
    "ბინა (3 საძ.)"      => 3,
    "სტუდიო"             => 4,
    "დუპლექსი"           => 5,
    "შიდა ავტოსადგომი"        => 6,
    "გარე ავტოსადგომი"   => 7,
    "დამხმარე"           => 8,
];

uksort($resArray, function($a, $b) use ($typeOrder) {
    $posA = isset($typeOrder[$a]) ? $typeOrder[$a] : 99;
    $posB = isset($typeOrder[$b]) ? $typeOrder[$b] : 99;
    if ($posA !== $posB) return $posA - $posB;
    return strcmp($a, $b);
});

$lang = isset($_GET['lang']) ? $_GET['lang'] : 'ge';

$labels = [
    'ge' => [
        'filter_project'     => 'პროექტი:',
        'filter_phase'       => 'ფაზა:',
        'filter_block'       => 'ბლოკი:',
        'filter_responsible' => 'პასუხისმგებელი:',
        'all_projects'       => 'ყველა პროექტი',
        'all_phases'         => 'ყველა ფაზა',
        'all_blocks'         => 'ყველა ბლოკი',
        'all_responsible'    => 'ყველა პასუხისმგებელი',
        'apply'              => 'ფილტრის გამოყენება',
        'clear'              => 'გასუფთავება',
        'export'             => '📥 Excel-ში ექსპორტი',
        'h2_count'           => 'სტატუსების მიხედვით - რაოდენობები',
        'h2_area'            => 'სტატუსების მიხედვით - კვადრატულობები',
        'h2_price'           => 'სტატუსების მიხედვით - თანხები ($)',
        'col_type'           => 'ქონების ტიპი',
        'col_free'           => 'თავისუფალი',
        'col_reserved'       => 'დაჯავშნილი',
        'col_sold'           => 'გაყიდული',
        'col_transferred'    => 'გადაცემული',
        'col_total'          => 'TOTAL',
        'prod_types' => [
            "ბინა"             => "ბინა",
            "ბინა (1 საძ.)"    => "ბინა (1 საძ.)",
            "ბინა (2 საძ.)"    => "ბინა (2 საძ.)",
            "ბინა (3 საძ.)"    => "ბინა (3 საძ.)",
            "სტუდიო"           => "სტუდიო",
            "დუპლექსი"         => "დუპლექსი",
            "შიდა ავტოსადგომი" => "შიდა ავტოსადგომი",
            "გარე ავტოსადგომი" => "გარე ავტოსადგომი",
            "დამხმარე"         => "დამხმარე",
            "აპარტამენტი" => "აპარტამენტი",
            "კომერციული"  => "კომერციული",
        ],
        'h2_avg_price' => 'სტატუსების მიხედვით - საშუალო ფასი ($)',
    ],
    'eng' => [
        'filter_project'     => 'Project:',
        'filter_phase'       => 'Phase:',
        'filter_block'       => 'Block:',
        'filter_responsible' => 'Responsible:',
        'all_projects'       => 'All Projects',
        'all_phases'         => 'All Phases',
        'all_blocks'         => 'All Blocks',
        'all_responsible'    => 'All Responsible',
        'apply'              => 'Apply Filters',
        'clear'              => 'Clear',
        'export'             => '📥 Export to Excel',
        'h2_count'           => 'By Status - Count',
        'h2_area'            => 'By Status - Sq. Meters',
        'h2_price'           => 'By Status - Price ($)',
        'col_type'           => 'Property Type',
        'col_free'           => 'For Sale',
        'col_reserved'       => 'Reserved',
        'col_sold'           => 'Sold',
        'col_transferred'    => 'Transferred',
        'col_total'          => 'TOTAL',
        'prod_types' => [
            "ბინა"             => "Flat",
            "ბინა (1 საძ.)"    => "Flat (1 Bed.)",
            "ბინა (2 საძ.)"    => "Flat (2 Bed.)",
            "ბინა (3 საძ.)"    => "Flat (3 Bed.)",
            "სტუდიო"           => "Studio",
            "დუპლექსი"         => "Duplex",
            "შიდა ავტოსადგომი" => "Indoor Parking",
            "გარე ავტოსადგომი" => "Outdoor Parking",
            "დამხმარე"         => "Additional",
            "აპარტამენტი" => "Apartment",
            "კომერციული"  => "Commercial",
        ],
        'h2_avg_price' => 'By Status - Average Price ($)',
    ],
];

$t = $labels[$lang] ?? $labels['ge'];

function translateProdType($name, $t) {
    return $t['prod_types'][$name] ?? $name;
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
    .filter-group select {
        width: 100%;
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
    .btn-primary { background-color: #007bff; color: white; }
    .btn-primary:hover { background-color: #0056b3; }
    .btn-secondary { background-color: #6c757d; color: white; }
    .btn-secondary:hover { background-color: #545b62; }
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
    .sales-table td { padding: 10px; border: 1px solid #ddd; }
    .sales-table tr:nth-child(even) { background-color: #f2f2f2; }
    .sales-table tr:hover { background-color: #ddd; }
    .total-row { background-color: #c9ccd0 !important; font-weight: bold; }
    h2 { font-family: Arial, sans-serif; color: #333; margin-top: 20px; }
    .sales-table tr.sub-row td { padding-left: 28px; color: #555; font-style: italic; }
    .sales-table tr.sub-row td:first-child { padding-left: 28px; }
    .sales-table tr.sub-row { background-color: #fafafa !important; }
</style>

<div class="filter-container">
    <form method="GET" action="">
        <input type="hidden" name="lang" value="<?= htmlspecialchars($lang) ?>">
        <div class="filter-row">
            <div class="filter-group">
                <label for="project"><?= $t['filter_project'] ?></label>
                <select name="project" id="project">
                    <option value=""><?= $t['all_projects'] ?></option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= htmlspecialchars($project) ?>" <?= $filterProject == $project ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="phase"><?= $t['filter_phase'] ?></label>
                <select name="phase" id="phase">
                    <option value=""><?= $t['all_phases'] ?></option>
                    <?php foreach ($phases as $phase): ?>
                        <option value="<?= htmlspecialchars($phase) ?>" <?= $filterPhase == $phase ? 'selected' : '' ?>>
                            <?= htmlspecialchars($phase) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="block"><?= $t['filter_block'] ?></label>
                <select name="block" id="block">
                    <option value=""><?= $t['all_blocks'] ?></option>
                    <?php foreach ($blocks as $block): ?>
                        <option value="<?= htmlspecialchars($block) ?>" <?= $filterBlock == $block ? 'selected' : '' ?>>
                            <?= htmlspecialchars($block) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="responsible"><?= $t['filter_responsible'] ?></label>
                <select name="responsible" id="responsible">
                    <option value=""><?= $t['all_responsible'] ?></option>
                    <?php foreach ($responsibles as $responsible): ?>
                        <option value="<?= htmlspecialchars($responsible) ?>" <?= $filterResponsible == $responsible ? 'selected' : '' ?>>
                            <?= htmlspecialchars($responsible) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary"><?= $t['apply'] ?></button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'">
                    <?= $t['clear'] ?>
                </button>
            </div>
        </div>
    </form>

    <div style="margin-top: 15px;">
        <button class="btn btn-primary" onclick="exportToExcel()"><?= $t['export'] ?></button>
    </div>
</div>

<?php
    $apartmentTypes = ["ბინა (1 საძ.)", "ბინა (2 საძ.)", "ბინა (3 საძ.)"];
    $statuses = ['თავისუფალი', 'დაჯავშნილი', 'გაყიდული', 'გადაცემული', 'NFS'];

    $status_totals_num = $status_totals_area = $status_totals_price = [];
    foreach ($statuses as $status) {
        $status_totals_num[$status] = $status_totals_area[$status] = $status_totals_price[$status] = 0;
    }

    foreach ($resArray as $prodType => $infos) {
        if (in_array($prodType, $apartmentTypes)) continue;
        foreach ($statuses as $status) {
            $status_totals_num[$status]   += $infos[$status]['num']        ?? 0;
            $status_totals_area[$status]  += $infos[$status]['total_area'] ?? 0;
            $status_totals_price[$status] += $infos[$status]['price']      ?? 0;
        }
    }

    $apt_status_totals_num = $apt_status_totals_area = $apt_status_totals_price = [];
    foreach ($statuses as $status) {
        $apt_status_totals_num[$status] = $apt_status_totals_area[$status] = $apt_status_totals_price[$status] = 0;
    }
    foreach ($apartmentTypes as $aptType) {
        if (isset($resArray[$aptType])) {
            foreach ($statuses as $status) {
                $apt_status_totals_num[$status]   += $resArray[$aptType][$status]['num']        ?? 0;
                $apt_status_totals_area[$status]  += $resArray[$aptType][$status]['total_area'] ?? 0;
                $apt_status_totals_price[$status] += $resArray[$aptType][$status]['price']      ?? 0;
            }
        }
    }
?>

<h2><?= $t['h2_count'] ?></h2>
<table class="sales-table">
    <thead>
        <tr>
            <th><?= $t['col_type'] ?></th>
            <th><?= $t['col_free'] ?></th>
            <th>%</th>
            <th><?= $t['col_reserved'] ?></th>
            <th>%</th>
            <th><?= $t['col_sold'] ?></th>
            <th>%</th>
            <th><?= $t['col_transferred'] ?></th>
            <th>%</th>
            <th>NFS</th>
            <th>%</th>
            <th><?= $t['col_total'] ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($resArray as $prodType => $infos):
            $row_total = 0;
            foreach ($statuses as $status) $row_total += $infos[$status]['num'] ?? 0;
            $isSubRow = in_array($prodType, $apartmentTypes);
        ?>
        <tr <?= $isSubRow ? 'class="sub-row"' : '' ?>>
            <td><?= $isSubRow ? '&nbsp;&nbsp;&nbsp;&nbsp;↳ ' . translateProdType($prodType, $t) : translateProdType($prodType, $t) ?></td>
            <?php foreach (['თავისუფალი','დაჯავშნილი','გაყიდული','გადაცემული','NFS'] as $s):
                $val = $infos[$s]['num'] ?? 0;
                $pct = $row_total > 0 ? number_format($val / $row_total * 100, 1) . '%' : '—';
            ?>
            <td><?= $val ?></td><td style="color:#888;font-size:12px"><?= $pct ?></td>
            <?php endforeach; ?>
            <td><?= $row_total ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td><?= $t['col_total'] ?></td>
            <?php
            $grand_total = array_sum($status_totals_num);
            foreach (['თავისუფალი','დაჯავშნილი','გაყიდული','გადაცემული','NFS'] as $s):
                $val = $status_totals_num[$s];
                $pct = $grand_total > 0 ? number_format($val / $grand_total * 100, 1) . '%' : '—';
            ?>
            <td><?= $val ?></td><td style="color:#555;font-size:12px"><?= $pct ?></td>
            <?php endforeach; ?>
            <td><?= $grand_total ?></td>
        </tr>
    </tbody>
</table>

<h2><?= $t['h2_area'] ?></h2>
<table class="sales-table">
    <thead>
        <tr>
            <th><?= $t['col_type'] ?></th>
            <th><?= $t['col_free'] ?></th>
            <th>%</th>
            <th><?= $t['col_reserved'] ?></th>
            <th>%</th>
            <th><?= $t['col_sold'] ?></th>
            <th>%</th>
            <th><?= $t['col_transferred'] ?></th>
            <th>%</th>
            <th>NFS</th>
            <th>%</th>
            <th><?= $t['col_total'] ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($resArray as $prodType => $infos):
            $apt_row_total = 0;
            foreach ($statuses as $status) $apt_row_total += $resArray[$prodType][$status]['total_area'] ?? 0;
            $isSubRow = in_array($prodType, $apartmentTypes);
        ?>
        <tr <?= $isSubRow ? 'class="sub-row"' : '' ?>>
            <td><?= $isSubRow ? '&nbsp;&nbsp;&nbsp;&nbsp;↳ ' . translateProdType($prodType, $t) : translateProdType($prodType, $t) ?></td>
            <?php foreach (['თავისუფალი','დაჯავშნილი','გაყიდული','გადაცემული','NFS'] as $s):
                $val = $resArray[$prodType][$s]['total_area'] ?? 0;
                $pct = $apt_row_total > 0 ? number_format($val / $apt_row_total * 100, 1) . '%' : '—';
            ?>
            <td><?= number_format($val, 2) ?></td><td style="color:#888;font-size:12px"><?= $pct ?></td>
            <?php endforeach; ?>
            <td><?= number_format($apt_row_total, 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td><?= $t['col_total'] ?></td>
            <?php
            $grand_total_area = array_sum($apt_status_totals_area);
            foreach (['თავისუფალი','დაჯავშნილი','გაყიდული','გადაცემული','NFS'] as $s):
                $val = $apt_status_totals_area[$s];
                $pct = $grand_total_area > 0 ? number_format($val / $grand_total_area * 100, 1) . '%' : '—';
            ?>
            <td><?= number_format($val, 2) ?></td><td style="color:#555;font-size:12px"><?= $pct ?></td>
            <?php endforeach; ?>
            <td><?= number_format($grand_total_area, 2) ?></td>
        </tr>
    </tbody>
</table>

<h2><?= $t['h2_price'] ?></h2>
<table class="sales-table">
    <thead>
        <tr>
            <th><?= $t['col_type'] ?></th>
            <th><?= $t['col_free'] ?></th>
            <th><?= $t['col_reserved'] ?></th>
            <th><?= $t['col_sold'] ?></th>
            <th><?= $t['col_transferred'] ?></th>
            <th>NFS</th>
            <th><?= $t['col_total'] ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($resArray as $prodType => $infos):
            $row_total_price = 0;
            foreach ($statuses as $status) $row_total_price += $infos[$status]['price'] ?? 0;
            $isSubRow = in_array($prodType, $apartmentTypes);
        ?>
        <tr <?= $isSubRow ? 'class="sub-row"' : '' ?>>
            <td><?= $isSubRow ? '&nbsp;&nbsp;&nbsp;&nbsp;↳ ' . translateProdType($prodType, $t) : translateProdType($prodType, $t) ?></td>
            <?php foreach (['თავისუფალი','დაჯავშნილი','გაყიდული','გადაცემული','NFS'] as $s):
                $val = $infos[$s]['price'] ?? 0;
            ?>
            <td><?= number_format($val, 2) ?></td>
            <?php endforeach; ?>
            <td><?= number_format($row_total_price, 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
// Types that use price/area formula (not price/count)
$areaBasedTypes = ["ბინა", "ბინა (1 საძ.)", "ბინა (2 საძ.)", "ბინა (3 საძ.)", "აპარტამენტი", "კომერციული", "დამხმარე"];
$parkingTypes   = ["შიდა ავტოსადგომი", "გარე ავტოსადგომი"];
$soldStatus     = "გაყიდული";
?>

<h2><?= $t['h2_avg_price'] ?></h2>
<table class="sales-table">
    <thead>
        <tr>
            <th><?= $t['col_type'] ?></th>
            <th><?= $t['col_free'] ?></th>
            <th><?= $t['col_reserved'] ?></th>
            <th><?= $t['col_sold'] ?></th>
            <th><?= $t['col_transferred'] ?></th>
            <th>NFS</th>
            <th><?= $t['col_total'] ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($resArray as $prodType => $infos):
        $isSubRow    = in_array($prodType, $apartmentTypes);
        $isAreaBased = in_array($prodType, $areaBasedTypes);
        $isParking   = in_array($prodType, $parkingTypes);

        // Totals across all statuses for the last column
        $total_price     = 0; $total_area  = 0;
        $total_deal_price = 0; $total_deal_area = 0; $total_deal_num = 0; $total_num = 0;
        foreach ($statuses as $status) {
            if ($status === $soldStatus) {
                $total_deal_price += $infos[$status]['deal_price'] ?? 0;
                $total_deal_area  += $infos[$status]['deal_area']  ?? 0;
                $total_deal_num   += $infos[$status]['deal_num']   ?? 0;
            } else {
                $total_price += $infos[$status]['price']      ?? 0;
                $total_area  += $infos[$status]['total_area'] ?? 0;
                $total_num   += $infos[$status]['num']        ?? 0;
            }
        }
        // Grand avg across all statuses (combine both sold and non-sold)
        if ($isAreaBased) {
            $all_price = $total_price + $total_deal_price;
            $all_area  = $total_area  + $total_deal_area;
            $grand_avg = $all_area > 0 ? '$' . number_format($all_price / $all_area, 2) : '—';
        } elseif ($isParking) {
            $all_price = $total_price + $total_deal_price;
            $all_num   = $total_num   + $total_deal_num;
            $grand_avg = $all_num > 0 ? '$' . number_format($all_price / $all_num, 2) : '—';
        } else {
            // fallback: count-based
            $all_num = array_sum(array_column($infos, 'num'));
            $all_price = array_sum(array_column($infos, 'price'));
            $grand_avg = $all_num > 0 ? '$' . number_format($all_price / $all_num, 2) : '—';
        }
    ?>
        <tr <?= $isSubRow ? 'class="sub-row"' : '' ?>>
            <td><?= $isSubRow ? '&nbsp;&nbsp;&nbsp;&nbsp;↳ ' . translateProdType($prodType, $t) : translateProdType($prodType, $t) ?></td>
            <?php foreach (['თავისუფალი','დაჯავშნილი','გაყიდული','გადაცემული','NFS'] as $s):
                $price_val = $infos[$s]['price']      ?? 0;
                $area_val  = $infos[$s]['total_area'] ?? 0;
                $num_val   = $infos[$s]['num']        ?? 0;
                $d_price   = $infos[$s]['deal_price'] ?? 0;
                $d_area    = $infos[$s]['deal_area']  ?? 0;
                $d_num     = $infos[$s]['deal_num']   ?? 0;

                if ($s === $soldStatus) {
                    // Always use deal data for გაყიდული
                    if ($isAreaBased) {
                        $avg = $d_area  > 0 ? '$' . number_format($d_price / $d_area,  2) : '—';
                    } elseif ($isParking) {
                        $avg = $d_num   > 0 ? '$' . number_format($d_price / $d_num,   2) : '—';
                    } else {
                        $avg = $d_num   > 0 ? '$' . number_format($d_price / $d_num,   2) : '—';
                    }
                } else {
                    // Non-sold statuses use product PRICE
                    if ($isAreaBased) {
                        $avg = $area_val > 0 ? '$' . number_format($price_val / $area_val, 2) : '—';
                    } else {
                        // parking + fallback: price / count
                        $avg = $num_val  > 0 ? '$' . number_format($price_val / $num_val,  2) : '—';
                    }
                }
            ?>
            <td><?= $avg ?></td>
            <?php endforeach; ?>
            <td style="font-weight:bold"><?= $grand_avg ?></td>
        </tr>
    <?php endforeach; ?>

        <?php
        // TOTAL row — aggregate across all non-parking, non-subrow types
        $tr_free_price = $tr_free_area = $tr_free_num = 0;
        $tr_res_price  = $tr_res_area  = $tr_res_num  = 0;
        $tr_sold_dp    = $tr_sold_da   = $tr_sold_dn  = 0;
        $tr_trans_price= $tr_trans_area= $tr_trans_num = 0;
        $tr_nfs_price  = $tr_nfs_area  = $tr_nfs_num  = 0;

        foreach ($resArray as $prodType => $infos) {
            if (in_array($prodType, $apartmentTypes)) continue; // skip sub-rows
            $tr_free_price  += $infos['თავისუფალი']['price']      ?? 0;
            $tr_free_area   += $infos['თავისუფალი']['total_area'] ?? 0;
            $tr_free_num    += $infos['თავისუფალი']['num']        ?? 0;
            $tr_res_price   += $infos['დაჯავშნილი']['price']      ?? 0;
            $tr_res_area    += $infos['დაჯავშნილი']['total_area'] ?? 0;
            $tr_res_num     += $infos['დაჯავშნილი']['num']        ?? 0;
            $tr_sold_dp     += $infos['გაყიდული']['deal_price']   ?? 0;
            $tr_sold_da     += $infos['გაყიდული']['deal_area']    ?? 0;
            $tr_sold_dn     += $infos['გაყიდული']['deal_num']     ?? 0;
            $tr_trans_price += $infos['გადაცემული']['price']      ?? 0;
            $tr_trans_area  += $infos['გადაცემული']['total_area'] ?? 0;
            $tr_trans_num   += $infos['გადაცემული']['num']        ?? 0;
            $tr_nfs_price   += $infos['NFS']['price']             ?? 0;
            $tr_nfs_area    += $infos['NFS']['total_area']        ?? 0;
            $tr_nfs_num     += $infos['NFS']['num']               ?? 0;
        }
        // Total row uses area-based for non-sold, deal-based for sold
        $tot_free  = $tr_free_area  > 0 ? '$'.number_format($tr_free_price  / $tr_free_area,  2) : '—';
        $tot_res   = $tr_res_area   > 0 ? '$'.number_format($tr_res_price   / $tr_res_area,   2) : '—';
        $tot_sold  = $tr_sold_da    > 0 ? '$'.number_format($tr_sold_dp     / $tr_sold_da,    2) : '—';
        $tot_trans = $tr_trans_area > 0 ? '$'.number_format($tr_trans_price / $tr_trans_area, 2) : '—';
        $tot_nfs   = $tr_nfs_area   > 0 ? '$'.number_format($tr_nfs_price   / $tr_nfs_area,   2) : '—';
        $all_p = $tr_free_price + $tr_res_price + $tr_sold_dp + $tr_trans_price + $tr_nfs_price;
        $all_a = $tr_free_area  + $tr_res_area  + $tr_sold_da + $tr_trans_area  + $tr_nfs_area;
        $tot_grand = $all_a > 0 ? '$'.number_format($all_p / $all_a, 2) : '—';
        ?>
        <tr class="total-row">
            <td><?= $t['col_total'] ?></td>
            <td><?= $tot_free ?></td>
            <td><?= $tot_res ?></td>
            <td><?= $tot_sold ?></td>
            <td><?= $tot_trans ?></td>
            <td><?= $tot_nfs ?></td>
            <td><?= $tot_grand ?></td>
        </tr>
    </tbody>
</table>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
    const productsData = <?= json_encode(array_values($filteredProducts)) ?>;
    const prodTypeMap = <?= json_encode($t['prod_types']) ?>;

    function translateType(name) {
        return prodTypeMap[name] || name;
    }

    function exportToExcel() {
        const wb = XLSX.utils.book_new();

        const summaryRows = [];
        document.querySelectorAll('h2').forEach(function(titleEl) {
            summaryRows.push([titleEl.innerText.trim()]);
            let table = titleEl.nextElementSibling;
            while (table && table.tagName !== 'TABLE') table = table.nextElementSibling;
            if (!table) return;
            const headerRow = [];
            table.querySelectorAll('thead tr th').forEach(function(th) { headerRow.push(th.innerText.trim()); });
            summaryRows.push(headerRow);
            table.querySelectorAll('tbody tr').forEach(function(tr) {
                const row = [];
                tr.querySelectorAll('td').forEach(function(td) {
                    let val = td.innerText.trim();
                    const num = parseFloat(val.replace(/,/g, ''));
                    if (!isNaN(num) && val !== '') val = num;
                    row.push(val);
                });
                summaryRows.push(row);
            });
            summaryRows.push([]);
        });

        const ws1 = XLSX.utils.aoa_to_sheet(summaryRows);
        ws1['!cols'] = [{ wch: 22 }, { wch: 12 }, { wch: 12 }, { wch: 12 }, { wch: 12 }, { wch: 12 }, { wch: 12 }];
        XLSX.utils.book_append_sheet(wb, ws1, 'Status Summary');

        const fields = [
            { key: '',                       label: '#' },
            { key: 'PROJECT',                label: 'Project' },
            { key: 'KORPUSIS_NOMERI_XE3NX2', label: 'Block' },
            { key: 'NAME',                   label: 'Unit Name' },
            { key: 'STATUS',                 label: 'Status' },
            { key: 'PRODUCT_TYPE',           label: 'Product Type' },
            { key: 'Bedrooms',               label: 'Bedrooms' },
            { key: 'TOTAL_AREA',             label: 'Total Area (sqm)' },
            { key: 'PRICE',                  label: 'Price ($)' },
            { key: 'PRICE_GEL',              label: 'Price (GEL)' },
            { key: 'DEAL_RESPONSIBLE_NAME',  label: 'Responsible' },
            { key: 'OWNER_CONTACT_NAME',     label: 'Owner' },
        ];

        let counter = 1;
        const rows = productsData.map(function(p) {
            const row = {};
            fields.forEach(function(f) {
                if (f.label === '#') {
                    row[f.label] = counter;
                } else if (f.key === 'STATUS') {
                    row[f.label] = translateType(p[f.key] ?? '');
                } else if (f.key === 'PRODUCT_TYPE') {
                    row[f.label] = translateType(p[f.key] ?? '');
                } else {
                    row[f.label] = p[f.key] ?? '';
                }
            });
            counter++;
            return row;
        });

        const ws2 = XLSX.utils.json_to_sheet(rows, { header: fields.map(function(f) { return f.label; }) });
        ws2['!cols'] = fields.map(function(f) { return { wch: Math.max(f.label.length, 16) }; });
        XLSX.utils.book_append_sheet(wb, ws2, 'Products');

        const today = new Date().toISOString().slice(0, 10);
        XLSX.writeFile(wb, 'product_report_' + today + '.xlsx');
    }
</script>