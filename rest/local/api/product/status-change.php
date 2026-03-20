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

$postJson = array();
try {
    $postJson = \Bitrix\Main\Web\Json::decode(\Bitrix\Main\HttpRequest::getInput());
} catch (Exception $e) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
    die();
}

$resArray = array();

if (!empty($postJson["ids"]) && !empty($postJson["status"])) {

    global $USER;
    $currentUserId = (int)$USER->GetID();

    $ids        = array_map("intval", $postJson["ids"]);
    $status     = $postJson["status"];
    $filterInfo = $postJson["filter_info"] ?? "";

    $products = getCIBlockElementsByFilter(array(
        "IBLOCK_ID" => 14,
        "ID"        => $ids,
    ));

    $successCount = 0;
    $errors       = array();
    $prodIds      = array();

    foreach ($products as $product) {
        $el   = new CIBlockElement;
        $PROP = $product;

        $PROP["STATUS"] = $status;

        $arFields = array(
            "IBLOCK_ID"         => 14,
            "IBLOCK_SECTION_ID" => $product["IBLOCK_SECTION_ID"],
            "NAME"              => $product["NAME"],
            "PROPERTY_VALUES"   => $PROP,
        );

        $result = $el->Update($product["ID"], $arFields);

        if ($result) {
            $successCount++;
            $prodIds[] = array("VALUE" => $product["ID"]);
        } else {
            $errors[] = array("id" => $product["ID"], "error" => $el->LAST_ERROR);
        }
    }

    // ლოგის შენახვა
    if ($successCount > 0) {
        $logEl    = new CIBlockElement;
        $arForAdd = array(
            "IBLOCK_ID" => 37,
            "NAME"      => "პროდუქტების მოდული - სტატუსის ცვლილება " . date("d.m.Y H:i"),
            "ACTIVE"    => "Y",
            "PROPERTY_VALUES" => array(
                "changedBy"   => $currentUserId,
                "changeType2" => 187,
                "change"      => "სტატუსი შეიცვალა: {$status}",
                "prodID"      => $prodIds,
                "filterInfo"  => $filterInfo,
                "changeDate"  => date("Y-m-d"),
            ),
        );
        $logEl->Add($arForAdd);
    }

    $resArray["success"] = empty($errors);
    $resArray["updated"] = $successCount;
    if (!empty($errors)) {
        $resArray["failed_ids"] = $errors;
    }

} else {
    $resArray["success"] = false;
    $resArray["error"]   = "invalid_params";
}

header("Content-Type: application/json; charset=utf-8");
echo json_encode($resArray, JSON_UNESCAPED_UNICODE);
?>