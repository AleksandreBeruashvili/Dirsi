<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CJSCore::Init(array("jquery"));

$APPLICATION->SetTitle("");

function printArr($arr){
    echo "<pre>"; print_r($arr); echo "</pre>";
}
function getDealsByFilter(array $filter, int $limit = 10): array
{
    $deals = [];

    $res = CCrmDeal::GetListEx(
        ['ID' => 'DESC'],
        $filter,
        false,
        ['nPageSize' => $limit],
        [
            'ID',
            'OPPORTUNITY',
            'CONTACT_ID',
            'UF_CRM_1701778190',
            'UF_CRM_1702019032102',
            'SOURCE_ID',
        ]
    );

    while ($deal = $res->Fetch()) {

        $deal['AMOUNT_USD'] = (float)str_replace('|USD', '', $deal['OPPORTUNITY']);
        $deal['AMOUNT_GEL'] = (float)str_replace('|GEL', '', $deal['UF_CRM_1701778190']);

        if ($deal['UF_CRM_1702019032102'] == 322) {
            $deal['CURRENCY'] = 'GEL';
        } elseif ($deal['UF_CRM_1702019032102'] == 323) {
            $deal['CURRENCY'] = 'USD';
        } else {
            $deal['CURRENCY'] = '';
        }

        $deals[] = $deal;
    }

    return $deals;
}

function getContactInfo($contactId) {
    $arContact = array();
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $contactId), array());
    if($arContact = $res->Fetch()){
        $EMAIL=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'EMAIL', 'VALUE_TYPE' => 'WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $PHONE=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'MOBILE|WORK|HOME', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $arContact["EMAIL"] = $EMAIL["VALUE"];
        $arContact["PHONE"] = $PHONE["VALUE"];

        return $arContact;
    }

    return $arContact;
}

function getCIBlockElementsByFilter($arFilter = array(),$sort=array()) {
    $arElements = array();
    $arSelect=array("ID","IBLOCK_ID","NAME","DATE_ACTIVE_FROM","PROPERTY_*");
    $res = CIBlockElement::GetList(array("PROPERTY_TARIGI"=>"ASC"), $arFilter, false, Array("nPageSize"=>9999999), $arSelect);
    while($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        array_push($arElements, $arPushs);
    }
    return $arElements;
}

$arFilter = array(
    //"ID" => "1344",
    "STAGE_ID" => "WON",
);

$resDeals = getDealsByFilter($arFilter);

foreach($resDeals as $deal) {
    //$prods = CCrmDeal::LoadProductRows($deal["ID"]);
    $productFilter = array(
        "IBLOCK_ID" => 20,
        "PROPERTY_DEAL" =>$deal["ID"],
    );
    $products = getCIBlockElementsByFilter($productFilter);
    $firstInstallmentsRaw = $products[0]["TANXA"];
    $sum = 0;
    foreach ($products as $product) {
        $installment = $product["TANXA"];
        $installmentNumber = (float) str_replace('|USD', '', $installment);
        $sum += $installmentNumber;
    }
    $firstInstallments = (float) str_replace('|USD', '', $firstInstallmentsRaw);
    printArr($deal);
    //printArr($sum);
    //printArr($firstInstallments);
    //printArr($product);
}

?>

<table class="deal-table">
    <thead>
    <tr>
        <th>Deal ID</th>
        <th>Pirveladi Shenatani</th>
        <th>Sum</th>
        <th>Opportunity</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($resDeals as $deal): ?>
        <?php
        $productFilter = array(
            "IBLOCK_ID" => 20,
            "PROPERTY_DEAL" =>$deal["ID"],
        );
        $products = getCIBlockElementsByFilter($productFilter);
        $firstInstallmentsRaw = $products[0]["TANXA"];
        $sum = 0;
        foreach ($products as $product) {
            $installment = $product["TANXA"];
            $installmentNumber = (float) str_replace('|USD', '', $installment);
            $sum += $installmentNumber;
        }
        $firstInstallments = (float) str_replace('|USD', '', $firstInstallmentsRaw);
        $firstInstallmentsFormatted = number_format($firstInstallments, 1);

        $sumFormatted = number_format($sum, 1);

        $opportunityValue = (float) str_replace('|USD', '', $deal["OPPORTUNITY"]);
        $opportunityFormatted = number_format($opportunityValue, 1);
        if ($firstInstallments <= 0) {
            continue;
        }
        ?>
        <tr>
            <td>

                <?= htmlspecialchars($deal['ID']); ?>
            </td>

            <td>
                <?= htmlspecialchars($firstInstallments); ?>$
            </td>
            <td>
                <?= htmlspecialchars($sum); ?>$
            </td>
            <td>
                <?= htmlspecialchars($opportunityFormatted); ?>$
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<style>
    .deal-table {
        width: 100%;
        border-collapse: collapse;
    }
    .deal-table th,
    .deal-table td {
        border: 1px solid #ccc;
        padding: 8px 12px;
        text-align: left;
    }
    .deal-table th {
        background: #f5f5f5;
    }
    .deal-table tr:hover {
        background: #fafafa;
    }
</style>