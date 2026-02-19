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

function getProducts($dealIds) {
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
$filterProject     = isset($_GET['project'])      ? trim($_GET['project'])      : '';
$filterBlock       = isset($_GET['block'])        ? trim($_GET['block'])        : '';
$filterBuilding    = isset($_GET['building'])     ? trim($_GET['building'])     : '';
$filterFloor       = isset($_GET['floor'])        ? trim($_GET['floor'])        : '';
$filterProductType = isset($_GET['prodType'])     ? trim($_GET['prodType'])     : '';
$filterResponsible = isset($_GET['responsible'])  ? trim($_GET['responsible'])  : '';

$arFilter = array("STAGE_ID" => "WON");
$deals = getDealsByFilter($arFilter);
$dealIds = array_keys($deals);

$products = getProducts($dealIds);

// Get unique values for filter dropdowns
$projects = getUniqueValues($products, 'PROJECT');
// $phases = getUniqueValues($products, 'phase');
$blocks = array_diff(getUniqueValues($products, 'KORPUSIS_NOMERI_XE3NX2'), ['P']);
$responsibles = getUniqueValues($products, 'DEAL_RESPONSIBLE_NAME');
$buildings = getUniqueValues($products, 'BUILDING');
$floors = getUniqueValues($products, 'FLOOR');
$prodTypes = ["Flat (1 Bed.)", "Flat (2 Bed.)", "Flat (3 Bed.)"];
$prodTypes = array_merge(getUniqueValues($products, 'PRODUCT_TYPE'), $prodTypes);

// Apply filters
$filteredProducts = array();
foreach ($products as $product) {
    $match = true;
    
    if ($filterProject && $product['PROJECT'] != $filterProject) {
        $match = false;
    }
    // if ($filterPhase && $product['phase'] != $filterPhase) {
    //     $match = false;
    // }
    if ($filterBlock && $product['KORPUSIS_NOMERI_XE3NX2'] != $filterBlock) {
        $match = false;
    }
    if ($filterResponsible && $product['DEAL_RESPONSIBLE_NAME'] != $filterResponsible) {
        $match = false;
    }

    if ($filterBuilding && $product['BUILDING'] != $filterBuilding) {
        $match = false;
    }

    if ($filterFloor && $product['FLOOR'] != $filterFloor) {
        $match = false;
    }

    if ($filterProductType) {
        if (str_contains($filterProductType, "Flat")) {
            preg_match('/\d+/', $filterProductType, $matches);
            $bedroom = $matches[0];
            if ($product['Bedrooms'] !== $bedroom) $match = false;
        } else {
            if ($product['PRODUCT_TYPE'] !== $filterProductType) $match = false;
        }
    }
    
    if ($match) {
        $filteredProducts[$product["ID"]] = $product;
    }
}

$resArray = [];

foreach ($filteredProducts as $product) {
    $prodType = $product["PRODUCT_TYPE"];
    $prodStatus = $product["STATUS"];

    if ($product["KORPUSIS_NOMERI_XE3NX2"] === "P") {
        $resArray["გარე პარკინგი"][$prodStatus]["num"]++;
        $resArray["გარე პარკინგი"][$prodStatus]["total_area"] += (float) $product["TOTAL_AREA"] ?? 0;
        $resArray["გარე პარკინგი"][$prodStatus]["price"] += (float) $product["PRICE"] ?? 0;
    } else if ($product["PRODUCT_TYPE"] === $prodType) {
        $resArray[$prodType][$prodStatus]["num"]++;
        $resArray[$prodType][$prodStatus]["total_area"] += (float) $product["TOTAL_AREA"] ?? 0;
        $resArray[$prodType][$prodStatus]["price"] += (float) $product["PRICE"] ?? 0;

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
    
            $resArray[$prodTypeAnothaOne][$prodStatus]["num"]++;
            $resArray[$prodTypeAnothaOne][$prodStatus]["total_area"] += (float) $product["TOTAL_AREA"] ?? 0;
            $resArray[$prodTypeAnothaOne][$prodStatus]["price"] += (float) $product["PRICE"] ?? 0;
        }
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
$statuses = ['თავისუფალი', 'დაჯავშნილი', 'გაყიდული', 'გადაცემული', 'NFS'];

// Initialize totals for each status
$status_totals_num = [];
$status_totals_area = [];
$status_totals_price = [];
foreach ($statuses as $status) {
    $status_totals_num[$status] = 0;
    $status_totals_area[$status] = 0;
    $status_totals_price[$status] = 0;
}

// Calculate status totals (excluding apartment breakdowns)
foreach ($resArray as $prodType => $infos) {
    if (in_array($prodType, $apartmentTypes)) continue;
    
    foreach ($statuses as $status) {
        $status_totals_num[$status] += $infos[$status]['num'] ?? 0;
        $status_totals_area[$status] += $infos[$status]['total_area'] ?? 0;
        $status_totals_price[$status] += $infos[$status]['price'] ?? 0;
    }
}

// Calculate apartment totals for each status
$apt_status_totals_num = [];
$apt_status_totals_area = [];
$apt_status_totals_price = [];
foreach ($statuses as $status) {
    $apt_status_totals_num[$status] = 0;
    $apt_status_totals_area[$status] = 0;
    $apt_status_totals_price[$status] = 0;
}

foreach ($apartmentTypes as $aptType) {
    if (isset($resArray[$aptType])) {
        foreach ($statuses as $status) {
            $apt_status_totals_num[$status] += $resArray[$aptType][$status]['num'] ?? 0;
            $apt_status_totals_area[$status] += $resArray[$aptType][$status]['total_area'] ?? 0;
            $apt_status_totals_price[$status] += $resArray[$aptType][$status]['price'] ?? 0;
        }
    }
}
?>

<h2>Amounts By Statuses of Product Types</h2>
<table class="sales-table">
    <thead>
        <tr>
            <th>Product Type</th>
            <th>For Sale</th>
            <th>Reserved</th>
            <th>Sold</th>
            <th>NFS</th>
            <th>TOTAL</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        foreach ($resArray as $prodType => $infos): 
            $row_total = 0;
            foreach ($statuses as $status) {
                $row_total += $infos[$status]['num'] ?? 0;
            }
        ?>
        <tr>
            <td><?= $prodType ?></td>
            <td><?= $infos['თავისუფალი']['num'] + $infos['დაჯავშნილი']['num'] ?? 0 ?></td>
            <td><?= $infos['დაჯავშნილი']['num'] ?? 0 ?></td>
            <td><?= $infos['გაყიდული']['num'] ?? 0 ?></td>
            <td><?= $infos['NFS']['num'] ?? 0 ?></td>
            <td><?= $row_total ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td>TOTAL</td>
            <td><?= $status_totals_num['თავისუფალი'] ?></td>
            <td><?= $status_totals_num['დაჯავშნილი'] ?></td>
            <td><?= $status_totals_num['გაყიდული'] ?></td>
            <td><?= $status_totals_num['NFS'] ?></td>
            <td><?= array_sum($status_totals_num) ?></td>
        </tr>
    </tbody>
</table>

<h2>Areas By Statuses of Product Types</h2>
<table class="sales-table">
    <thead>
        <tr>
            <th>Product Type</th>
            <th>For Sale</th>
            <th>Reserved</th>
            <th>Sold</th>
            <th>NFS</th>
            <th>TOTAL</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($resArray as $prodType => $infos): ?>
            <?php if (isset($resArray[$prodType])): 
                $apt_row_total = 0;
                foreach ($statuses as $status) {
                    $apt_row_total += $resArray[$prodType][$status]['total_area'] ?? 0;
                }
            ?>
            <tr>
                <td><?= $prodType ?></td>
                <td><?= number_format($resArray[$prodType]['თავისუფალი']['total_area'] + $resArray[$prodType]['დაჯავშნილი']['total_area'] ?? 0, 2) ?></td>
                <td><?= number_format($resArray[$prodType]['დაჯავშნილი']['total_area'] ?? 0, 2) ?></td>
                <td><?= number_format($resArray[$prodType]['გაყიდული']['total_area'] ?? 0, 2) ?></td>
                <td><?= number_format($resArray[$prodType]['NFS']['total_area'] ?? 0, 2) ?></td>
                <td><?= number_format($apt_row_total, 2) ?></td>
            </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        <tr class="total-row">
            <td>TOTAL</td>
            <td><?= number_format($apt_status_totals_area['თავისუფალი'], 2) ?></td>
            <td><?= number_format($apt_status_totals_area['დაჯავშნილი'], 2) ?></td>
            <td><?= number_format($apt_status_totals_area['გაყიდული'], 2) ?></td>
            <td><?= number_format($apt_status_totals_area['NFS'], 2) ?></td>
            <td><?= number_format(array_sum($apt_status_totals_area), 2) ?></td>
        </tr>
    </tbody>
</table>

<h2>Prices By Statuses of Product Types</h2>
<table class="sales-table">
    <thead>
        <tr>
            <th>Product Type</th>
            <th>For Sale</th>
            <th>Reserved</th>
            <th>Sold</th>
            <th>NFS</th>
            <th>TOTAL</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        foreach ($resArray as $prodType => $infos): 
            $row_total_price = 0;
            foreach ($statuses as $status) {
                $row_total_price += $infos[$status]['price'] ?? 0;
            }
        ?>
        <tr>
            <td><?= $prodType ?></td>
            <td><?= number_format($infos['თავისუფალი']['price'] + $infos['დაჯავშნილი']['price'] ?? 0, 2) ?></td>
            <td><?= number_format($infos['დაჯავშნილი']['price'] ?? 0, 2) ?></td>
            <td><?= number_format($infos['გაყიდული']['price'] ?? 0, 2) ?></td>
            <td><?= number_format($infos['NFS']['price'] ?? 0, 2) ?></td>
            <td><?= number_format($row_total_price, 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>