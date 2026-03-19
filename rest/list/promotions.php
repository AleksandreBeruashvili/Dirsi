<?php
ob_start();

$SECRET_TOKEN = "AsGeorgiaToken2026FMG";

$authHeader = "";
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers["Authorization"])) {
        $authHeader = $headers["Authorization"];
    } elseif (isset($headers["authorization"])) {
        $authHeader = $headers["authorization"];
    }
}

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

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
global $USER;
$authorizedUser = false;
if (empty($USER->GetID())) {
    $USER->Authorize(1);
    $authorizedUser = true;
}

$arFilter = [
    "IBLOCK_ID" => 22,
    "PROPERTY_ACTIVE" => 115,
    "PROPERTY_WEB" => 178,
];

$arSelect = ["ID", "IBLOCK_ID", "NAME", "PROPERTY_*"];
$res = CIBlockElement::GetList([], $arFilter, false, ["nPageSize" => 99999], $arSelect);

$result = [];

while ($ob = $res->GetNextElement()) {
    $fields = $ob->GetFields();
    $props = $ob->GetProperties();

    $getEnum = function ($prop) {
        if ($prop["MULTIPLE"] === "Y") {
            if (is_array($prop["VALUE_ENUM"])) return $prop["VALUE_ENUM"];
            if (is_array($prop["VALUE"])) return $prop["VALUE"];
            return [];
        }
        return !empty($prop["VALUE_ENUM"]) ? $prop["VALUE_ENUM"] : $prop["VALUE"];
    };

    $numbers = [];
    if (!empty($props["number"]["VALUE"])) {
        $numbers = array_map('trim', explode("/", $props["number"]["VALUE"]));
    }

    $item = [
        "id"              => $fields["ID"],
        "name"            => $fields["NAME"],
        "project"         => $getEnum($props["PROJECT_LIST"]),
        "product_type"    => $getEnum($props["product_type"]),
        "corp"            => $getEnum($props["CORP_LIST"]),
        "floor"           => $getEnum($props["floor"]),
        "number"          => $numbers,
        "discount_type"   => $getEnum($props["discount_type"]),
        "discount"        => $props["discount"]["VALUE"],
        "advance_payment" => $props["Advance_payment"]["VALUE"],
        "last_payment"    => $props["lastPayment"]["VALUE"],
    ];

    $result[] = $item;
}

if (!empty($result)) {
    $resArray = [
        "status" => 200,
        "message" => "OK",
        "result" => $result,
        "count" => count($result),
    ];
} else {
    $resArray = [
        "status" => 404,
        "message" => "No active promotions found",
        "result" => [],
        "count" => 0,
    ];
}

if ($authorizedUser) {
    $USER->Logout();
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($resArray, JSON_UNESCAPED_UNICODE);
