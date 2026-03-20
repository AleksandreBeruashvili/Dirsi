<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
ob_end_clean();

function getCIBlockElementsByFilter($arFilter = array()) {
    $arElements = array();
    $arSelect   = array("ID", "IBLOCK_ID", "IBLOCK_SECTION_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $res        = CIBlockElement::GetList(array(), $arFilter, false, array("nPageSize" => 999999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        
        array_push($arElements, $arPushs);
    }
    return $arElements;
}

function updatePrice($products, $price, $type, $direction) {
    $result = array();

    foreach ($products as $product) {
        $el   = new CIBlockElement;
        $PROP = $product;

        $oldSqmPrice = floatval($product["KVM_PRICE"]);
        $livingSpace = floatval($product["TOTAL_AREA"]);

        if ($type === "fixed") {
            $diff        = ($direction === "increase") ? floatval($price) : -floatval($price);
            $newSqmPrice = $oldSqmPrice + $diff;
        } else {
            $calculatedPercent = ($oldSqmPrice * floatval($price)) / 100;
            $diff              = ($direction === "increase") ? $calculatedPercent : -$calculatedPercent;
            $newSqmPrice       = $oldSqmPrice + $diff;
        }

        $newSqmPrice    = round(max(0, $newSqmPrice), 0);
        $newOpportunity = round($newSqmPrice * $livingSpace, 2);

        // priceChangeLog — არსებულს დავამატოთ, არ წავშალოთ
        $existingLog = $product["priceChangeLog"] ?? "";
        if (is_array($existingLog)) {
            $existingLog = implode("\n", $existingLog);
        }
        $existingLog = trim($existingLog);

        $directionWord = ($direction === "increase") ? "მოემატა" : "დააკლდა";
        $logEntry      = "ფასი {$directionWord} {$price}$-ით - ძველი თანხა: {$oldSqmPrice}$ - " . date("d.m.Y H:i");

        $PROP["priceChangeLog"] = $existingLog ? $existingLog . "\n" . $logEntry : $logEntry;
        $PROP["KVM_PRICE"]       = $newSqmPrice;

        $arFields = array(
            "IBLOCK_ID"         => 14,
            "IBLOCK_SECTION_ID" => $product["IBLOCK_SECTION_ID"],
            "NAME"              => $product["NAME"],
            "PROPERTY_VALUES"   => $PROP,
        );

        $updateResult   = $el->Update($product["ID"], $arFields);
        $resPriceUpdate = CPrice::SetBasePrice($product["ID"], $newOpportunity, "USD");

        $result[] = array(
            "id"          => $product["ID"],
            "status"      => $updateResult,
            "error"       => $el->LAST_ERROR,
            "oldSqmPrice" => $oldSqmPrice,
            "newSqmPrice" => $newSqmPrice,
            "totalPrice"  => $newOpportunity,
            "priceUpdate" => $resPriceUpdate,
        );
    }

    return $result;
}

function saveLogs($successProducts, $changeType, $changeType2, $userId, $filterInfo, $direction, $price, $priceType) {
    $el           = new CIBlockElement;
    $prodIds      = array();
    $changeStr    = "";

    // change სტრიქონი
    $dirWord   = ($direction === "increase") ? "დავამატეთ" : "დავაკლეთ";
    $typeLabel = ($priceType === "percent") ? "{$price}%" : "{$price}$";
    $changeStr = "{$dirWord} {$typeLabel}";

    foreach ($successProducts as $p) {
        $prodIds[] = array("VALUE" => (int)$p["id"]);
        $changeValues[] = array("VALUE" => $p["id"] . " | " . ($p["newSqmPrice"] - $p["oldSqmPrice"]) . " | " . $p["newSqmPrice"]);
    }

    $arForAdd = array(
        "IBLOCK_ID" => 37,
        "NAME"      => "პროდუქტების მოდული - ფასის ცვლილება " . date("d.m.Y H:i"),
        "ACTIVE"    => "Y",
        "PROPERTY_VALUES" => array(
            "changeType"  => $changeType,
            "changedBy"   => $userId,
            "changeType2" => $changeType2,
            "change"      => $changeStr,      
            "prodId"      => $prodIds,          
            "filterInfo"  => $filterInfo,        // ფილტრის ინფო
            "changeDate"  => date("Y-m-d H:i:s"),
        ),
    );

    return $el->Add($arForAdd);
}

// ---- მთავარი ლოგიკა ----

$postJson = array();
try {
    $postJson = \Bitrix\Main\Web\Json::decode(\Bitrix\Main\HttpRequest::getInput());
} catch (Exception $e) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
    die();
}

$resArray = array();

if (
    !empty($postJson["ids"])         &&
    !empty($postJson["change_type"]) &&
    !empty($postJson["area"])        &&
    !empty($postJson["direction"])   &&
    isset($postJson["value"])
) {
    global $USER;
    $currentUserId = (int)$USER->GetID();

    $ids        = array_map("intval", $postJson["ids"]);
    $changeType = $postJson["change_type"]; // "fixed" | "percent"
    $area       = $postJson["area"];        // "inner"
    $direction  = $postJson["direction"];   // "increase" | "decrease"
    $value      = $postJson["value"];

    $logChangeTypeSaxe = 182;
    $logChangeType2    = ($changeType === "percent") ? 185 : 186;

    // პროდუქტების ჩამოტვირთვა
    $products = getCIBlockElementsByFilter(array(
        "IBLOCK_ID" => 14,
        "ID"        => $ids,
    ));

    // ფასის განახლება
    $updateInfo = updatePrice($products, $value, $changeType, $direction);

    // წარმატებული და წარუმატებელი
    $successProducts = array_filter($updateInfo, fn($r) => $r["status"] === true);
    $failedProducts  = array_filter($updateInfo, fn($r) => $r["status"] !== true);

    // ლოგის შენახვა
    $filterInfo = $postJson["filter_info"] ?? "";

    if (!empty($successProducts)) {
        saveLogs(
            array_values($successProducts),
            $logChangeTypeSaxe,
            $logChangeType2,
            $currentUserId,
            $filterInfo,
            $direction,
            $value,
            $changeType
        );
    }

    $resArray["success"] = empty($failedProducts);
    $resArray["updated"] = count($successProducts);
    $resArray["result"]  = array_values($updateInfo);

    if (!empty($failedProducts)) {
        $resArray["failed_ids"] = array_map(fn($r) => ["id" => $r["id"], "error" => $r["error"]], array_values($failedProducts));
    }

} else {
    $resArray["success"] = false;
    $resArray["error"]   = "invalid_params";
}

header("Content-Type: application/json; charset=utf-8");
echo json_encode($resArray, JSON_UNESCAPED_UNICODE);
?>