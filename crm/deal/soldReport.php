<?
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CJSCore::Init(array("jquery"));

$APPLICATION->SetTitle("Sold Report");

// ------------------------------FUNCTIONS---------------------------------
function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDaricxvebi($dealId) {
    $daricxvebi = array();

    // daricxvebi
    $arSelect = Array("ID", "IBLOCK_SECTION_ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $arFilter = array(
        "IBLOCK_ID"             => 20,
        "PROPERTY_DEAL"         => $dealId
    );

    $res = CIBlockElement::GetList(Array("PROPERTY_TARIGI" => "ASC"), $arFilter, false, Array("nPageSize" => 99999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arProps = $ob->GetProperties();

        $daricxvebi[] = array(
            "DEAL_ID" => $arProps["DEAL"]["VALUE"],
            "DATE" => $arProps["TARIGI"]["VALUE"]
        );
    }

    return $daricxvebi;
}

function getUserName ($id) {
    $res = CUser::GetByID($id)->Fetch();
    return $res["NAME"]." ".$res["LAST_NAME"];
}

function getDealsByFilter($arFilter, $arrSelect=array()) {
    // if (empty($arrSelect)) {
    //     $arrSelect = false;
    // }
    $res = CCrmDeal::GetListEx(array("ID" => "ASC"), $arFilter, false, false, array("ID", "DATE_CREATE", "CONTACT_ID","COMPANY_ID", "TITLE","CONTACT_FULL_NAME","OPPORTUNITY","COMPANY_TITLE","UF_CRM_1761658532158","ASSIGNED_BY_ID","UF_CRM_1766560177934", "UF_CRM_1764317005", "UF_CRM_1761658516561", "UF_CRM_1766736693236", "UF_CRM_1761658577987", "UF_CRM_1770888201367", "UF_CRM_1770640981002", "UF_CRM_1767011536", "UF_CRM_1761658608306", "UF_CRM_1761658503260", "UF_CRM_1761658559005", "UF_CRM_1762416342444"));
    $resArr = array();
    while($arDeal = $res->Fetch()){
        $daricxvebi = getDaricxvebi($arDeal["ID"]);
        $arDeal["firstDaricxvaDate"] = $daricxvebi[array_key_first($daricxvebi)]["DATE"];
        $arDeal["lastDaricxvaDate"]  = $daricxvebi[array_key_last($daricxvebi)]["DATE"];

        $gadaxdebi = getGadaxdebi($arDeal["ID"]);
        $arDeal["payment"] = 0;
        foreach ($gadaxdebi as $g) {
            $arDeal["payment"] += $g["gadaxda_amount"];
        }

        $arDeal["responsible"] = getUserName($arDeal["ASSIGNED_BY_ID"]);

        $contact = getContactInfo($arDeal["CONTACT_ID"]);
        $arDeal["CONTACT_FULL_NAME"]         = ($contact["NAME"] ?? '') . ' ' . ($contact["LAST_NAME"] ?? '');
        $arDeal["OWNER_CONTACT_PN"]          = $contact["UF_CRM_1761651998145"];
        $arDeal["OWNER_CONTACT_CITIZENSHIP"] = getCountry($contact["UF_CRM_1769506891465"]); 
        $arDeal["OWNER_CONTACT_PHONE"]       = $contact["PHONE"];

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

function getCountry($enumId) {
    if (empty($enumId)) return '';
    
    $res = CUserFieldEnum::GetList([], ["ID" => $enumId]);
    if ($row = $res->Fetch()) {
        return $row["VALUE"];
    }
    return '';
}

function getProducts($dealIds) {
    if (empty($dealIds)) {
        return array();
    }
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
        foreach ($arProps as $key => $arProp){
            $fieldId = $arProp["CODE"];
            $arPushs[$fieldId] = $arProp["VALUE"];
        }
        if ($arPushs["OWNER_CONTACT"]) {
            $contact = getContactInfo($arPushs["OWNER_CONTACT"]);
            $arPushs["OWNER_CONTACT_NAME"] = $contact["FULL_NAME"];
            $arPushs["OWNER_CONTACT_PN"] = $contact["UF_CRM_1761651998145"];
            $arPushs["OWNER_CONTACT_CITIZENSHIP"] = getCountry($contact["UF_CRM_1769506891465"]); 
            $arPushs["OWNER_CONTACT_PHONE"] = $contact["PHONE"];
        }
        if ($arPushs["DEAL_RESPONSIBLE"]) {
            $arPushs["DEAL_RESPONSIBLE_NAME"] = getUserName($arPushs["DEAL_RESPONSIBLE"]);
        }
        $price = CPrice::GetBasePrice($arPushs["ID"]);
        $arPushs["PRICE"] = isset($price["PRICE"]) ? round($price["PRICE"], 2) : 0;
        $arPushs['PRICE_GEL'] = round($arPushs["PRICE"] * $nbg, 2);
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

function getDealByIDForPrice($id, $arSelect = array(), $arSort = array("ID"=>"DESC")) {
    $res = CCrmDeal::GetList($arSort, array("ID"=>$id), ["OPPORTUNITY"]);
    if($arDeal = $res->Fetch()){
        return $arDeal;
    } else {
        return array();
    }
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

/**
 * Sort BB keys numerically then alphabetically.
 * e.g. "6A" < "6B" < "9A" < "11" < "12B"
 */
function sortBBKeys($keys) {
    usort($keys, function($a, $b) {
        // Extract leading number and trailing letter suffix
        preg_match('/^(\d+)([A-Za-z]*)$/', $a, $mA);
        preg_match('/^(\d+)([A-Za-z]*)$/', $b, $mB);

        $numA = isset($mA[1]) ? (int)$mA[1] : 0;
        $numB = isset($mB[1]) ? (int)$mB[1] : 0;
        $sufA = isset($mA[2]) ? strtoupper($mA[2]) : '';
        $sufB = isset($mB[2]) ? strtoupper($mB[2]) : '';

        if ($numA !== $numB) return $numA - $numB;
        return strcmp($sufA, $sufB);
    });
    return $keys;
}

// ------------------------------MAIN CODE---------------------------------

$filterProject     = isset($_GET['project'])     ? trim($_GET['project'])     : '';
$filterBlock       = isset($_GET['block'])       ? trim($_GET['block'])       : '';
$filterBuilding    = isset($_GET['building'])    ? trim($_GET['building'])    : '';
$filterFloor       = isset($_GET['floor'])       ? trim($_GET['floor'])       : '';
$filterProductType = isset($_GET['prodType'])    ? trim($_GET['prodType'])    : '';
$filterResponsible = isset($_GET['responsible']) ? trim($_GET['responsible']) : '';
$filterSource      = isset($_GET['source'])      ? trim($_GET['source'])      : '';

$displayDateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$displayDateTo   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';

$filterDateFrom = '';
$filterDateTo = '';

if ($displayDateFrom !== '') {
    $dateObj = DateTime::createFromFormat('Y-m-d', $displayDateFrom);
    if ($dateObj) $filterDateFrom = $dateObj->format('d/m/Y');
}
if ($displayDateTo !== '') {
    $dateObj = DateTime::createFromFormat('Y-m-d', $displayDateTo);
    if ($dateObj) $filterDateTo = $dateObj->format('d/m/Y');
}

$arFilter = ["STAGE_ID" => "WON"];

if ($filterProject !== '')     $arFilter["UF_CRM_1761658516561"]   = $filterProject;
if ($filterBlock !== '')       $arFilter["UF_CRM_1766560177934"]   = $filterBlock;
if ($filterBuilding !== '')    $arFilter["UF_CRM_1766736693236"]   = $filterBuilding;
if ($filterFloor !== '')       $arFilter["UF_CRM_1761658577987"]   = $filterFloor;
if ($filterResponsible !== '') $arFilter["ASSIGNED_BY_ID"]         = $filterResponsible;
if ($filterDateFrom !== '')    $arFilter[">=UF_CRM_1762416342444"] = $filterDateFrom;
if ($filterDateTo !== '')      $arFilter["<=UF_CRM_1762416342444"] = $filterDateTo;
if (!empty($filterSource))     $arFilter["SOURCE_ID"]              = $filterSource;

if ($filterProductType !== '') {
    if (str_contains($filterProductType, "Flat")) {
        preg_match('/\d+/', $filterProductType, $matches);
        if (isset($matches[0])) $arFilter["UF_CRM_1770888201367"] = $matches[0];
    } else {
        $arFilter["UF_CRM_1761658532158"] = $filterProductType;
    }
}

$deals    = getDealsByFilter($arFilter);
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'eng';
$dealIds  = array_keys($deals);
$products = getProducts($dealIds);

$gadaxdebi = getGadaxdebi($dealIds);
$gadaxdebiByDeal = [];
foreach ($gadaxdebi as $g) {
    $gadaxdebiByDeal[$g["DEAL_ID"]] = ($gadaxdebiByDeal[$g["DEAL_ID"]] ?? 0) + $g["gadaxda_amount"];
}

$labels = [
    'eng' => [
        // Filter labels
        'project'        => 'Project',
        'block'          => 'Block',
        'building'       => 'Building',
        'floor'          => 'Floor',
        'prodType'       => 'Product Type',
        'responsible'    => 'Responsible',
        'source'         => 'Source',
        'date_from'      => 'Date From',
        'date_to'        => 'Date To',
        'all_projects'   => 'All Projects',
        'all_blocks'     => 'All Blocks',
        'all_buildings'  => 'All Buildings',
        'all_floors'     => 'All Floors',
        'all_types'      => 'All Product Types',
        'all_resp'       => 'All Responsible',
        'all_sources'    => 'All Sources',
        'apply'          => 'Apply Filters',
        'clear'          => 'Clear',
        'export'         => '📥 Export to Excel',

        // Table headings
        'sales_summary'  => 'Sales Summary',
        'sales_by_bb'    => 'Sales by Building / Block',
        'total_all'      => 'TOTAL — All Buildings / Blocks',
        'bb_label'       => 'Building / Block: ',
        'col_type'       => 'Product Type',
        'col_unit'       => 'Unit',
        'col_unit_sold'  => 'Unit Sold',
        'col_sqm'        => 'Sq.m',
        'col_sqm_sold'   => 'Sq.m Sold',
        'col_total_price'=> 'Total Price',
        'col_deals'      => 'Price by sold sq.m (Deals)',
        'col_products'   => 'Price by sold sq.m (Products)',
        'col_received'   => 'Received payments',
        'col_difference'   => 'Balance',
        'col_avg'        => 'Avg Price per sq.m / Sold Unit ($)',
        'col_total'      => 'TOTAL',

        // Excel column headers
        'xls_deal'       => 'Deal#',
        'xls_client'     => 'Client',
        'xls_resp'       => 'Responsible',
        'xls_contract'   => 'Contract Signing Date',
        'xls_sched_start'=> 'Schedual Start Date',
        'xls_sched_end'  => 'Schedual End Date',
        'xls_buyer_type' => 'Buyer Type',
        'xls_id_num'     => 'ID Number',
        'xls_citizen'    => 'Citizenship',
        'xls_phone'      => 'Mobile Number',
        'xls_re_type'    => 'Real Estate Type',
        'xls_area'       => 'Area (sqm)',
        'xls_building'   => 'Building',
        'xls_block'      => 'Block',
        'xls_floor'      => 'Floor',
        'xls_apt'        => 'Apartment #',
        'xls_price_sqm'  => 'Price per sqm',
        'xls_total_price'=> 'Total Price',
        'xls_paid'       => 'Amount Paid',
    ],
    'ge' => [
        // Filter labels
        'project'        => 'პროექტი',
        'block'          => 'ბლოკი',
        'building'       => 'კორპუსი',
        'floor'          => 'სართული',
        'prodType'       => 'პროდუქტის ტიპი',
        'responsible'    => 'პასუხისმგებელი',
        'source'         => 'წყარო',
        'date_from'      => 'თარიღიდან',
        'date_to'        => 'თარიღამდე',
        'all_projects'   => 'ყველა პროექტი',
        'all_blocks'     => 'ყველა ბლოკი',
        'all_buildings'  => 'ყველა კორპუსი',
        'all_floors'     => 'ყველა სართული',
        'all_types'      => 'ყველა ტიპი',
        'all_resp'       => 'ყველა პასუხისმგებელი',
        'all_sources'    => 'ყველა წყარო',
        'apply'          => 'ფილტრის გამოყენება',
        'clear'          => 'გასუფთავება',
        'export'         => '📥 Excel-ში ექსპორტი',

        // Table headings
        'sales_summary'  => 'გაყიდვების შეჯამება',
        'sales_by_bb'    => 'გაყიდვები კორპუს/ბლოკის მიხედვით',
        'total_all'      => 'სულ — ყველა კორპუსი / ბლოკი',
        'bb_label'       => 'კორპუსი / ბლოკი: ',
        'col_type'       => 'პროდუქტის ტიპი',
        'col_unit'       => 'ერთეული',
        'col_unit_sold'  => 'გაყიდული',
        'col_sqm'        => 'კვ.მ',
        'col_sqm_sold'   => 'გაყიდული კვ.მ',
        'col_total_price'=> 'სრული ფასი',
        'col_deals'      => 'ფასი გაყიდულ კვ.მ-ზე (გარიგებები)',
        'col_products'   => 'ფასი გაყიდულ კვ.მ-ზე (პროდუქტები)',
        'col_received'   => 'მიღებული გადახდები',
        'col_difference'   => 'სხვაობა',
        'col_avg'        => 'საშ. ფასი კვ.მ-ზე / გაყიდულ ერთეულზე ($)',
        'col_total'      => 'სულ',

        // Excel column headers
        'xls_deal'       => 'გარიგება#',
        'xls_client'     => 'კლიენტი',
        'xls_resp'       => 'პასუხისმგებელი',
        'xls_contract'   => 'ხელშეკრულების თარიღი',
        'xls_sched_start'=> 'გრაფიკის დაწყების თარიღი',
        'xls_sched_end'  => 'გრაფიკის დასრულების თარიღი',
        'xls_buyer_type' => 'მყიდველის ტიპი',
        'xls_id_num'     => 'პირადი ნომერი',
        'xls_citizen'    => 'მოქალაქეობა',
        'xls_phone'      => 'მობილური',
        'xls_re_type'    => 'უძრავი ქონების ტიპი',
        'xls_area'       => 'ფართობი (კვ.მ)',
        'xls_building'   => 'კორპუსი',
        'xls_block'      => 'ბლოკი',
        'xls_floor'      => 'სართული',
        'xls_apt'        => 'ბინის №',
        'xls_price_sqm'  => 'ფასი კვ.მ-ზე',
        'xls_total_price'=> 'სრული ფასი',
        'xls_paid'       => 'გადახდილი თანხა',
    ],
];

$t = $labels[$lang];

// Dropdown options
$projects     = getUniqueValues($products, 'PROJECT');
$blocks       = array_diff(getUniqueValues($products, 'KORPUSIS_NOMERI_XE3NX2'), ['P']);
$responsibles = getUniqueValues($products, 'DEAL_RESPONSIBLE_NAME');
$buildings    = getUniqueValues($products, 'BUILDING');
$floors       = getUniqueValues($products, 'FLOOR');
$prodTypes    = array_merge(getUniqueValues($products, 'PRODUCT_TYPE'), ["Flat (1 Bed.)", "Flat (2 Bed.)", "Flat (3 Bed.)"]);
$sourceIds    = getUniqueValues($deals, 'SOURCE_ID');
$sources      = array();
foreach ($sourceIds as $sourceId) {
    $sources[$sourceId] = getSourceNameById($sourceId);
}

// Apply filters to products
$filteredProducts = array();
foreach ($products as $product) {
    $match = true;
    if ($filterProject !== ''     && $product['PROJECT'] != $filterProject)                   $match = false;
    if ($filterBlock !== ''       && $product['KORPUSIS_NOMERI_XE3NX2'] != $filterBlock)      $match = false;
    if ($filterBuilding !== ''    && $product['BUILDING'] != $filterBuilding)                 $match = false;
    if ($filterFloor !== ''       && $product['FLOOR'] != $filterFloor)                       $match = false;
    if ($filterResponsible !== '' && $product['DEAL_RESPONSIBLE_NAME'] != $filterResponsible) $match = false;
    if ($match) $filteredProducts[$product["ID"]] = $product;
}

// ---- Build $resArray ----
$resArray = [];

foreach ($filteredProducts as $product) {
    $prodType      = $product["PRODUCT_TYPE"];
    $prodBlock     = $product["KORPUSIS_NOMERI_XE3NX2"];
    $prodBuilding  = $product["BUILDING"];
    $BB            = $product["BUILDING"] . $product["KORPUSIS_NOMERI_XE3NX2"];
    $status        = $product["STATUS"];
    $prodTotalArea = $product["TOTAL_AREA"];

    // --- TOTAL group ---
    if (!isset($resArray["TOTAL"][$prodType])) {
        $resArray["TOTAL"][$prodType] = [
            "unitsTotal"         => 0,
            "unitSold"           => 0,
            "sqmTotal"           => 0,
            "sqlSold"            => 0,
            "soldPricesSum"      => 0,
            "soldPricesDeal"     => 0,
            "soldPricesProduct"  => 0,
            "receivedPayments"   => 0,
            "averagePricePerSqm" => 0
        ];
    }
    $resArray["TOTAL"][$prodType]["unitsTotal"]++;
    $resArray["TOTAL"][$prodType]["sqmTotal"] += $prodTotalArea;

    // --- BB group ---
    if (!isset($resArray[$BB][$prodType])) {
        $resArray[$BB][$prodType] = [
            "unitsTotal"         => 0,
            "unitSold"           => 0,
            "sqmTotal"           => 0,
            "sqlSold"            => 0,
            "soldPricesSum"      => 0,
            "soldPricesDeal"     => 0,
            "soldPricesProduct"  => 0,
            "receivedPayments"   => 0,
            "averagePricePerSqm" => 0
        ];
    }
    $resArray[$BB][$prodType]["unitsTotal"]++;
    $resArray[$BB][$prodType]["sqmTotal"] += $prodTotalArea;

    $price = $product["PRICE"];
    $resArray[$BB][$prodType]["soldPricesSum"] += $price;
    $resArray["TOTAL"][$prodType]["soldPricesSum"] += $price;
    if ($status === "გაყიდული") {
        $resArray[$BB][$prodType]["unitSold"]++;
        $resArray[$BB][$prodType]["sqlSold"] += $prodTotalArea;
        $priceDeal = floatVal(getDealByIDForPrice($product["OWNER_DEAL"])["OPPORTUNITY"]);
        $resArray[$BB][$prodType]["soldPricesDeal"] += $priceDeal;
        $resArray["TOTAL"][$prodType]["soldPricesDeal"] += $priceDeal;
        $resArray[$BB][$prodType]["soldPricesProduct"] += $price;
        $resArray["TOTAL"][$prodType]["soldPricesProduct"] += $price;
        $resArray[$BB][$prodType]["averagePricePerSqm"] = $resArray[$BB][$prodType]["soldPricesDeal"] / $resArray[$BB][$prodType]["sqlSold"];

        $resArray["TOTAL"][$prodType]["unitSold"]++;
        $resArray["TOTAL"][$prodType]["sqlSold"] += $prodTotalArea;
        $resArray["TOTAL"][$prodType]["averagePricePerSqm"] = $resArray["TOTAL"][$prodType]["soldPricesDeal"] / $resArray["TOTAL"][$prodType]["sqlSold"];

        $filteredProducts[$product["ID"]]["firstDaricxvaDate"] = $deals[$product["OWNER_DEAL"]]["firstDaricxvaDate"];
        $filteredProducts[$product["ID"]]["lastDaricxvaDate"] = $deals[$product["OWNER_DEAL"]]["lastDaricxvaDate"];

        $payment = $gadaxdebiByDeal[$product["OWNER_DEAL"]] ?? 0;
        $resArray[$BB][$prodType]["receivedPayments"]    += $payment;
        $resArray["TOTAL"][$prodType]["receivedPayments"] += $payment;
        $resArray[$BB][$prodType]["differences"]          = $resArray[$BB][$prodType]["soldPricesDeal"] - $resArray[$BB][$prodType]["receivedPayments"];
        $resArray["TOTAL"][$prodType]["differences"]      = $resArray["TOTAL"][$prodType]["soldPricesDeal"] - $resArray["TOTAL"][$prodType]["receivedPayments"];
    }

    // --- Flat bedroom breakdown ---
    if ($prodType === "Flat") {
        $bedroom = $product["Bedrooms"] ?? '';
        if ($bedroom === "1")     $prodTypeAnothaOne = "Flat (1 Bed.)";
        elseif ($bedroom === "2") $prodTypeAnothaOne = "Flat (2 Bed.)";
        elseif ($bedroom === "3") $prodTypeAnothaOne = "Flat (3 Bed.)";
        else continue;

        if (!isset($resArray[$BB][$prodTypeAnothaOne])) {
            $resArray[$BB][$prodTypeAnothaOne] = [
                "unitsTotal"         => 0,
                "unitSold"           => 0,
                "sqmTotal"           => 0,
                "sqlSold"            => 0,
                "soldPricesSum"      => 0,
                "soldPricesDeal"     => 0,
                "soldPricesProduct"  => 0,
                "receivedPayments"   => 0,
                "averagePricePerSqm" => 0
            ];
        }
        $resArray[$BB][$prodTypeAnothaOne]["unitsTotal"]++;
        $resArray[$BB][$prodTypeAnothaOne]["sqmTotal"] += $prodTotalArea;

        if (!isset($resArray["TOTAL"][$prodTypeAnothaOne])) {
            $resArray["TOTAL"][$prodTypeAnothaOne] = [
                "unitsTotal"         => 0,
                "unitSold"           => 0,
                "sqmTotal"           => 0,
                "sqlSold"            => 0,
                "soldPricesSum"      => 0,
                "soldPricesDeal"     => 0,
                "soldPricesProduct"  => 0,
                "receivedPayments"   => 0,
                "averagePricePerSqm" => 0
            ];
        }
        $resArray["TOTAL"][$prodTypeAnothaOne]["unitsTotal"]++;
        $resArray["TOTAL"][$prodTypeAnothaOne]["sqmTotal"] += $prodTotalArea;

        $price = $product["PRICE"];
        $resArray["TOTAL"][$prodTypeAnothaOne]["soldPricesSum"] += $price;
        $resArray[$BB][$prodTypeAnothaOne]["soldPricesSum"] += $price;
        if ($status === "გაყიდული") {
            $priceDeal = floatVal(getDealByIDForPrice($product["OWNER_DEAL"])["OPPORTUNITY"]);
            
            $resArray[$BB][$prodTypeAnothaOne]["unitSold"]++;
            $resArray[$BB][$prodTypeAnothaOne]["sqlSold"] += $prodTotalArea;
            $resArray[$BB][$prodTypeAnothaOne]["soldPricesDeal"] += $priceDeal;
            $resArray[$BB][$prodTypeAnothaOne]["soldPricesProduct"] += $price;
            $resArray[$BB][$prodTypeAnothaOne]["averagePricePerSqm"] = $resArray[$BB][$prodTypeAnothaOne]["soldPricesDeal"] / $resArray[$BB][$prodTypeAnothaOne]["sqlSold"];

            $resArray["TOTAL"][$prodTypeAnothaOne]["unitSold"]++;
            $resArray["TOTAL"][$prodTypeAnothaOne]["sqlSold"] += $prodTotalArea;
            $resArray["TOTAL"][$prodTypeAnothaOne]["soldPricesDeal"] += $priceDeal;
            $resArray["TOTAL"][$prodTypeAnothaOne]["soldPricesProduct"] += $price;
            $resArray["TOTAL"][$prodTypeAnothaOne]["averagePricePerSqm"] = $resArray["TOTAL"][$prodTypeAnothaOne]["soldPricesDeal"] / $resArray["TOTAL"][$prodTypeAnothaOne]["sqlSold"];

            $resArray[$BB][$prodTypeAnothaOne]["receivedPayments"]    += $payment;
            $resArray["TOTAL"][$prodTypeAnothaOne]["receivedPayments"] += $payment;
            $resArray[$BB][$prodTypeAnothaOne]["differences"]          = $resArray[$BB][$prodTypeAnothaOne]["soldPricesDeal"] - $resArray[$BB][$prodTypeAnothaOne]["receivedPayments"];
            $resArray["TOTAL"][$prodTypeAnothaOne]["differences"]      = $resArray["TOTAL"][$prodTypeAnothaOne]["soldPricesDeal"] - $resArray["TOTAL"][$prodTypeAnothaOne]["receivedPayments"];
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
    .sales-table td {
        padding: 10px;
        border: 1px solid #ddd;
    }
    .sales-table tr:nth-child(even) { background-color: #f2f2f2; }
    .sales-table tr:hover { background-color: #ddd; }
    .total-row {
        background-color: #c9ccd0 !important;
        font-weight: bold;
    }
    .breakdown-row td {
        color: #555;
        font-style: italic;
        padding-left: 24px;
    }
    h2 { font-family: Arial, sans-serif; color: #333; margin-top: 30px; }
    h3.bb-title {
        font-family: Arial, sans-serif;
        color: #2c3e50;
        margin-top: 30px;
        margin-bottom: 6px;
        border-left: 4px solid #2c3e50;
        padding-left: 10px;
    }
</style>

<!-- FILTER FORM -->
<div class="filter-container">
    <form method="GET" action="">
        <input type="hidden" name="lang" value="<?= htmlspecialchars($lang) ?>">
        <div class="filter-row">

            <div class="filter-group">
                <label for="project"><?= $t['project'] ?>:</label>
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
                <label for="block"><?= $t['block'] ?>:</label>
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
                <label for="building"><?= $t['building'] ?>:</label>
                <select name="building" id="building">
                    <option value=""><?= $t['all_buildings'] ?></option>
                    <?php foreach ($buildings as $building): ?>
                        <option value="<?= htmlspecialchars($building) ?>" <?= $filterBuilding == $building ? 'selected' : '' ?>>
                            <?= htmlspecialchars($building) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="floor"><?= $t['floor'] ?>:</label>
                <select name="floor" id="floor">
                    <option value=""><?= $t['all_floors'] ?></option>
                    <?php foreach ($floors as $floor): ?>
                        <option value="<?= htmlspecialchars($floor) ?>" <?= $filterFloor == $floor ? 'selected' : '' ?>>
                            <?= htmlspecialchars($floor) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="prodType"><?= $t['prodType'] ?>:</label>
                <select name="prodType" id="prodType">
                    <option value=""><?= $t['all_types'] ?></option>
                    <?php foreach ($prodTypes as $pt): ?>
                        <option value="<?= htmlspecialchars($pt) ?>" <?= $filterProductType == $pt ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="responsible"><?= $t['responsible'] ?>:</label>
                <select name="responsible" id="responsible">
                    <option value=""><?= $t['all_resp'] ?></option>
                    <?php foreach ($responsibles as $name): ?>
                        <option value="<?= htmlspecialchars($name) ?>" <?= $filterResponsible == $name ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="source"><?= $t['source'] ?>:</label>
                <select name="source" id="source">
                    <option value=""><?= $t['all_sources'] ?></option>
                    <?php foreach ($sources as $id => $source): ?>
                        <option value="<?= htmlspecialchars($id) ?>" <?= $filterSource == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($source) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="date_from"><?= $t['date_from'] ?>:</label>
                <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($displayDateFrom) ?>">
            </div>

            <div class="filter-group">
                <label for="date_to"><?= $t['date_to'] ?>:</label>
                <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($displayDateTo) ?>">
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

$apartmentTypes = ["Flat (1 Bed.)", "Flat (2 Bed.)", "Flat (3 Bed.)"];

// Collect ordered product types: main types first (sorted), bedroom breakdowns last (sorted ascending)
$allProdTypes = [];
foreach ($resArray as $groupData) {
    foreach (array_keys($groupData) as $pt) {
        if (!in_array($pt, $allProdTypes)) $allProdTypes[] = $pt;
    }
}
$mainTypes      = array_values(array_filter($allProdTypes, fn($pt) => !in_array($pt, $apartmentTypes)));
$breakdownTypes = array_values(array_filter($allProdTypes, fn($pt) =>  in_array($pt, $apartmentTypes)));

// Sort main types alphabetically
sort($mainTypes);

// Sort bedroom breakdowns ascending: Flat (1 Bed.) → Flat (2 Bed.) → Flat (3 Bed.)
usort($breakdownTypes, function($a, $b) {
    preg_match('/(\d+)/', $a, $mA);
    preg_match('/(\d+)/', $b, $mB);
    return (int)($mA[1] ?? 0) - (int)($mB[1] ?? 0);
});

$allProdTypes = array_merge($mainTypes, $breakdownTypes);

// ---- Render function ----
function renderBBTable($groupKey, $groupData, $allProdTypes, $apartmentTypes, $t) {
    $label = ($groupKey === "TOTAL") ? $t['total_all'] : $t['bb_label'] . $groupKey;
    ?>
    <h3 class="bb-title"><?= htmlspecialchars($label) ?></h3>
    <table class="sales-table">
        <thead>
            <tr>
                <th><?= $t['col_type'] ?></th>
                <th><?= $t['col_unit'] ?></th>
                <th><?= $t['col_unit_sold'] ?></th>
                <th><?= $t['col_sqm'] ?></th>
                <th><?= $t['col_sqm_sold'] ?></th>
                <th><?= $t['col_total_price'] ?></th>
                <th><?= $t['col_deals'] ?></th>
                <th><?= $t['col_products'] ?></th>
                <th><?= $t['col_received'] ?></th>
                <th><?= $t['col_difference'] ?></th>
                <th><?= $t['col_avg'] ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $t_unitsTotal = $t_unitSold = $t_sqmTotal = $t_sqlSold = $t_soldPricesSum = $t_soldPricesDeal = $t_soldPricesProduct= $t_receivedPayments = $t_soldDifferences = 0;

            foreach ($allProdTypes as $pt):
                if (!isset($groupData[$pt])) continue;
                $info        = $groupData[$pt];
                $isBreakdown = in_array($pt, $apartmentTypes);
                $rowClass    = $isBreakdown ? 'class="breakdown-row"' : '';

                if (!$isBreakdown) {
                    $t_unitsTotal        += $info['unitsTotal']        ?? 0;
                    $t_unitSold          += $info['unitSold']          ?? 0;
                    $t_sqmTotal          += $info['sqmTotal']          ?? 0;
                    $t_sqlSold           += $info['sqlSold']           ?? 0;
                    $t_soldPricesSum     += $info['soldPricesSum']     ?? 0;
                    $t_soldPricesDeal    += $info['soldPricesDeal']    ?? 0;
                    $t_soldPricesProduct += $info['soldPricesProduct'] ?? 0;
                    $t_receivedPayments  += $info['receivedPayments']  ?? 0;
                    $t_soldDifferences   += $info['differences']       ?? 0;
                }
            ?>
            <tr <?= $rowClass ?>>
                <td><?= htmlspecialchars($pt) ?></td>
                <td><?= $info['unitsTotal'] ?? 0 ?></td>
                <td><?= $info['unitSold'] ?? 0 ?></td>
                <td><?= number_format($info['sqmTotal']      ?? 0, 2) ?></td>
                <td><?= number_format($info['sqlSold']        ?? 0, 2) ?></td>
                <td>$<?= number_format($info['soldPricesSum'] ?? 0, 2) ?></td>
                <td>$<?= number_format($info['soldPricesDeal'] ?? 0, 2) ?></td>
                <td>$<?= number_format($info['soldPricesProduct'] ?? 0, 2) ?></td>
                <td>$<?= number_format($info['receivedPayments'] ?? 0, 2) ?></td>
                <td>$<?= number_format($info['differences'] ?? 0, 2) ?></td>
                <td>$<?= number_format($info['averagePricePerSqm'] ?? 0, 2) ?></td>
            </tr>
            <?php endforeach; ?>

            <?php
                $t_avg        = $t_unitSold  > 0 ? $t_soldPricesSum / $t_unitSold  : 0;
                $t_pricePerSqm = $t_sqlSold  > 0 ? $t_soldPricesSum / $t_sqlSold   : 0;
            ?>
            <tr class="total-row">
                <td><?= $t['col_total'] ?></td>
                <td><?= $t_unitsTotal ?></td>
                <td><?= $t_unitSold ?></td>
                <td><?= number_format($t_sqmTotal,      2) ?></td>
                <td><?= number_format($t_sqlSold,       2) ?></td>
                <td>$<?= number_format($t_soldPricesSum, 2) ?></td>
                <td>$<?= number_format($t_soldPricesDeal,  2) ?></td>
                <td>$<?= number_format($t_soldPricesProduct,  2) ?></td>
                <td>$<?= number_format($t_receivedPayments, 2) ?></td>
                <td>$<?= number_format($t_soldDifferences, 2) ?></td>
                <td>$<?= number_format($t_avg,          2) ?></td>
            </tr>
        </tbody>
    </table>
    <?php
}

// ---- Render TOTAL first, then each BB sorted numerically+alphabetically ----
if (isset($resArray["TOTAL"])) {
    echo '<h2>' . $t['sales_summary'] . '</h2>';
    renderBBTable("TOTAL", $resArray["TOTAL"], $allProdTypes, $apartmentTypes, $t);
}

$bbKeys = array_filter(array_keys($resArray), fn($k) => $k !== "TOTAL");
$bbKeys = sortBBKeys(array_values($bbKeys));

if (!empty($bbKeys)) {
    echo '<h2>' . $t['sales_by_bb'] . '</h2>';
    foreach ($bbKeys as $bbKey) {
        renderBBTable($bbKey, $resArray[$bbKey], $allProdTypes, $apartmentTypes, $t);
    }
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
    const productsData = <?= json_encode(array_values($filteredProducts)) ?>;
    const dealsData = <?= json_encode(array_values($deals)) ?>;
    const t = <?= json_encode($t) ?>;

    function exportToExcel() {
        const wb = XLSX.utils.book_new();

        // =============================================
        // SHEET 1: Summary Tables (from rendered HTML)
        // =============================================
        const summaryRows = [];

        const sections = document.querySelectorAll('h3.bb-title');
        sections.forEach(function(titleEl) {
            summaryRows.push([titleEl.innerText.trim()]);

            let table = titleEl.nextElementSibling;
            while (table && table.tagName !== 'TABLE') {
                table = table.nextElementSibling;
            }
            if (!table) return;

            const headerCells = table.querySelectorAll('thead tr th');
            const headerRow = [];
            headerCells.forEach(function(th) { headerRow.push(th.innerText.trim()); });
            summaryRows.push(headerRow);

            const bodyRows = table.querySelectorAll('tbody tr');
            bodyRows.forEach(function(tr) {
                const row = [];
                tr.querySelectorAll('td').forEach(function(td) {
                    let val = td.innerText.trim();
                    if (val.startsWith('$')) {
                        const num = parseFloat(val.replace('$', '').replace(/,/g, ''));
                        val = isNaN(num) ? val : num;
                    } else {
                        const num = parseFloat(val.replace(/,/g, ''));
                        if (!isNaN(num) && val !== '') val = num;
                    }
                    row.push(val);
                });
                summaryRows.push(row);
            });

            summaryRows.push([]);
        });

        const ws1 = XLSX.utils.aoa_to_sheet(summaryRows);
        ws1['!cols'] = [
            { wch: 22 }, { wch: 10 }, { wch: 12 }, { wch: 12 }, { wch: 14 },
            { wch: 16 }, { wch: 24 }, { wch: 26 }, { wch: 20 }, { wch: 28 },
        ];
        XLSX.utils.book_append_sheet(wb, ws1, 'Sales Summary');

        // =============================================
        // SHEET 2: Deal Details (existing logic)
        // =============================================
        const fields = [
            { key: 'ID',                        label: t.xls_deal },
            { key: 'CONTACT_FULL_NAME',         label: t.xls_client },
            { key: 'responsible',               label: t.xls_resp },
            { key: 'UF_CRM_1762416342444',      label: t.xls_contract },
            { key: 'firstDaricxvaDate',         label: t.xls_sched_start },
            { key: 'lastDaricxvaDate',          label: t.xls_sched_end },
            { key: '',                          label: t.xls_buyer_type },
            { key: 'OWNER_CONTACT_PN',          label: t.xls_id_num },
            { key: 'OWNER_CONTACT_CITIZENSHIP', label: t.xls_citizen },
            { key: 'OWNER_CONTACT_PHONE',       label: t.xls_phone },
            { key: 'prodTypeNew',               label: t.xls_re_type },
            { key: 'UF_CRM_1761658608306',      label: t.xls_area },
            { key: 'UF_CRM_1766736693236',      label: t.xls_building },
            { key: 'UF_CRM_1766560177934',      label: t.xls_block },
            { key: 'UF_CRM_1761658577987',      label: t.xls_floor },
            { key: 'UF_CRM_1761658559005',      label: t.xls_apt },
            { key: 'UF_CRM_1761658503260',      label: t.xls_price_sqm },
            { key: 'OPPORTUNITY',               label: t.xls_total_price },
            { key: 'payment',                   label: t.xls_paid },
        ];

        const productByDeal = {};
        productsData.forEach(function(p) {
            if (p['OWNER_DEAL']) productByDeal[p['OWNER_DEAL']] = p;
        });

        const rows = dealsData.map(function(deal) {
            const product = productByDeal[deal['ID']] || {};
            const merged = Object.assign({}, product, deal);
            if (merged['OPPORTUNITY']) merged['OPPORTUNITY'] = parseFloat(merged['OPPORTUNITY']);

            if (product['PRODUCT_TYPE'] === 'Flat') {
                merged['prodTypeNew'] = (product['Bedrooms'] || '') + ' Rooms Studio';
            } else {
                merged['prodTypeNew'] = product['PRODUCT_TYPE'] || '';
            }

            const row = {};
            fields.forEach(function(f) {
                row[f.label] = f.key ? (merged[f.key] ?? '') : '';
            });
            return row;
        });

        const ws2 = XLSX.utils.json_to_sheet(rows, { header: fields.map(function(f) { return f.label; }) });
        ws2['!cols'] = fields.map(function(f) { return { wch: Math.max(f.label.length, 14) }; });
        XLSX.utils.book_append_sheet(wb, ws2, 'Deal Details');

        const today = new Date().toISOString().slice(0, 10);
        XLSX.writeFile(wb, 'sold_report_' + today + '.xlsx');
    }
</script>