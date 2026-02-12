<?php
ob_start();




// Token-ბეისდ ავტორიზაცია
$SECRET_TOKEN = "AsGeorgiaToken2026FMG"; 

// სხვადასხვა სერვერული კონფიგურაციისთვის header-ის წაკითხვა
$authHeader = "";
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers["Authorization"])) {
        $authHeader = $headers["Authorization"];
    } elseif (isset($headers["authorization"])) {
        $authHeader = $headers["authorization"];
    }
}

// თუ getallheaders() არ მუშაობს, სცადე $_SERVER-დან
if (empty($authHeader)) {
    if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
        $authHeader = $_SERVER["HTTP_AUTHORIZATION"];
    } elseif (isset($_SERVER["Authorization"])) {
        $authHeader = $_SERVER["Authorization"];
    } elseif (isset($_SERVER["REDIRECT_HTTP_AUTHORIZATION"])) {
        $authHeader = $_SERVER["REDIRECT_HTTP_AUTHORIZATION"];
    }
}

if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(["error" => "Missing Authorization bearer token"], JSON_UNESCAPED_UNICODE);
    exit();
}

$token = trim($matches[1]);

if ($token !== $SECRET_TOKEN) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(["error" => "Invalid token"], JSON_UNESCAPED_UNICODE);
    exit();
}


require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Title");
global $USER;
$user_id = $USER->GetID();

$authorizedUser = false;
if(empty($user_id)) {
    $USER->Authorize(1);
    $authorizedUser = true;
}

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getCIBlockElementsByFilter($arFilter = array()) {
    $arElements = array();
    $arSelect = Array("ID","IBLOCK_ID", "IBLOCK_SECTION_ID", "NAME","DATE_ACTIVE_FROM","PROPERTY_*");
    $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>99999), $arSelect);
    while($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];

        $price = CPrice::GetBasePrice($arPushs["ID"]);
        $arPushs["PRICE"] = isset($price["PRICE"]) ? $price["PRICE"] : null;

        array_push($arElements, $arPushs);
    }
    return $arElements;
}


function getProduct($building, $block, $floor, $number, $project) {
    $arFilter = array(
        "IBLOCK_ID" => 14,
    );
    if (!empty($project)) {
        $arFilter["IBLOCK_SECTION_ID"] = $project;
    }
    // მხოლოდ გადაცემული პარამეტრების დამატება filter-ში
    if (!empty($building)) {
        $arFilter["PROPERTY_BUILDING"] = $building;
    }
    if (!empty($block)) {
        $arFilter["PROPERTY_KORPUSIS_NOMERI_XE3NX2"] = $block;
    }
    if (!empty($floor)) {
        $arFilter["PROPERTY_FLOOR"] = $floor;
    }
    if (!empty($number)) {
        $arFilter["PROPERTY_Number"] = $number;
    }

    $elements = getCIBlockElementsByFilter($arFilter);
    $result = array();

    foreach ($elements as $el) {
        // $filePathAP = CFile::GetPath($el["sartulinew"]);
        // $filePathAP = "https://crm.homer.ge". $filePathAP;


        // $filePathFLOOR = CFile::GetPath($el["floorplan"]);
        // $filePathFLOOR = "https://crm.homer.ge". $filePathFLOOR;


        $thisElement = array(
            "ID"                    => $el["ID"],
            "NAME"                  => $el["NAME"],
        
            // ძირითადი
            "STATUS"                => $el["STATUS"],
            "PROJECT"               => $el["PROJECT"],
            "PRODUCT_TYPE"          => $el["PRODUCT_TYPE"],
            "FLOOR"                 => $el["FLOOR"],
            "NUMBER"                => $el["Number"],
        
            // შენობა / ბლოკი
            "CORPS"                 => $el["KORPUSIS_NOMERI_XE3NX2"],
            "ENTRANCE"              => $el["_15MYD6"],
            "BUILDING"              => $el["BUILDING"],
        
            // ფართები
            "FULL_PART"             => $el["TOTAL_AREA"],
            "LIVING_SPACE"          => $el["LIVING_SPACE"],
            "BALCONY_PART"          => $el["BALCONY_AREA"],
            "TERRACE_AREA"          => $el["terrace_area"],
            "YARDAREA"              => $el["__XM7Y1P"],
        
            // საძინებლები / სველი წერტილები
            "NUMBOFROOMS"           => $el["__4IOFZC"],
            "NUMBOFBATHROOMS"       => $el["__9GBYAF"],
            "NUMOFBEDROOMS"         => $el["Bedrooms"],
            // "BEDROOM1_AREA"         => $el["Bedroom1"],
            // "BEDROOM2_AREA"         => $el["Bedroom2"],
            // "BEDROOM3_AREA"         => $el["Bedroom3"],

        
            // ფასები
            "KVM_PRICE"             => str_replace("|USD", "", $el["KVM_PRICE"]),
            "KVM_PRICE_GEL"         => $el["_M2__SUXOA7"],
            "TOTAL_PRICE"           => $el["PRICE"],
            "RETAIL_PRICE"          => $el["__ACHC7B"],
            "RETAIL_PRICE_GEL"      => $el["RETAIL_PRICE__CO8K0T"],
        
            // პროექტი
            "projEndDate"           => $el["projEndDate"],
        
            // დამატებითი
            "CADASTRAL_CODE"        => $el["CADASTRAL_CODE"],
            "VIEW"                  => $el["_V5K9G7"],
            // "QUEUE"                 => $el["QUEUE"],
            // "PTO"                   => $el["PTO_2ID0NS"],
        
            // ფაილები
            // "MORE_PHOTO"            => $el["MORE_PHOTO"],
            // "APARTMENTDRAW"         => $el["binis_naxazi"],
            // "FLOORPLAN"             => $el["binis_gegmareba"],
            // "RENDER_3D"             => $el["render_3D"],
        
            // მფლობელები
            "OWNER_DEAL"            => $el["OWNER_DEAL"],
            "OWNER_CONTACT"         => $el["OWNER_CONTACT"],
            "OWNER_COMPANY"         => $el["OWNER_COMPANY"],
            "DEAL_RESPONSIBLE"      => $el["DEAL_RESPONSIBLE"],
        );
        
        array_push($result, $thisElement);
    }

    return $result;
}


$building = isset($_GET["building"]) ? $_GET["building"] : "";
$block = isset($_GET["block"]) ? $_GET["block"] : "";
$floor = isset($_GET["floor"]) ? $_GET["floor"] : "";
$number = isset($_GET["number"]) ? $_GET["number"] : "";
$project = isset($_GET["project"]) ? $_GET["project"] : "";

// მინიმუმ ერთი პარამეტრი უნდა იყოს გადაცემული
if (!empty($building) || !empty($block) || !empty($floor) || !empty($number) || !empty($project)) {
    $prod = getProduct($building, $block, $floor, $number, $project);

    if(!empty($prod) && is_array($prod) && count($prod) > 0) {
        $resArray["status"] = 200;
        $resArray["message"] = "OK";
        $resArray["result"] = $prod;
        $resArray["count"] = count($prod);
    } else {
        $resArray["status"] = 404;
        $resArray["message"] = "Products Not Found";
        $resArray["result"] = array();
        $resArray["count"] = 0;
    }
} else {
    $resArray["status"] = 405;
    $resArray["message"] = "Bad Request";
    $resArray["error"] = "At least one parameter (building, block, floor, or number) is required";
}

if($authorizedUser) {
    $USER->Logout();
}

?>
<?php
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($resArray, JSON_UNESCAPED_UNICODE);
