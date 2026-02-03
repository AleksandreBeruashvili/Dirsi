<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CJSCore::Init(array("jquery"));

$APPLICATION->SetTitle("");

use Bitrix\Crm\DealTable;
use Bitrix\Main\ORM\Query\Query;


function getDealsByFilter(array $filter, int $limit = 5): array
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

function getCIBlockElementByFilter($arFilter)
{
    $res = CIBlockElement::GetList(array("ID"=>"ASC"), $arFilter, false, Array("nPageSize" => 1), array());
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        return $arPushs;
    }
    return false;
}

function printArr($arr){
    echo "<pre>"; print_r($arr); echo "</pre>";
}
$arFilter = array(
    //"ID" => "1783",
    "STAGE_ID" => "WON",
);
$resDeals = getDealsByFilter($arFilter);



foreach($resDeals as $deal) {
    $prods = CCrmDeal::LoadProductRows($deal["ID"]);
    $contactInfo = getContactInfo($deal["CONTACT_ID"]);
    $productFilter = array(
        "PRODUCT_ID" => $prods[0]["PRODUCT_ID"],
    );
    $product = getCIBlockElementByFilter($productFilter);
    $sources = \CCrmStatus::GetStatusList('SOURCE');
    //    printArr($deal["SOURCE_ID"]);
    //    printArr($sources);
    //printArr($prods[0]["PRODUCT_ID"]);
    //printArr($contactInfo);
    //printArr($prods);
    //printArr($product);
}

?>


<table class="deal-table">
    <thead>
    <tr>
        <th>Deal ID</th>
        <th>Product ID</th>
        <th>Floor</th>
        <th>Number</th>
        <th>Source</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($resDeals as $deal): ?>
        <?php
        $products = CCrmDeal::LoadProductRows($deal["ID"]);
        $productId = !empty($products) ? $products[0]['PRODUCT_ID'] : '—';
        $productFilter = array(
            "ID" => $productId,
        );
        $product = getCIBlockElementByFilter($productFilter);
        $floor = !empty($product) ? $product['FLOOR'] : '—';
        $number = !empty($product) ? $product['Number'] : 0;
        $sources = \CCrmStatus::GetStatusList('SOURCE');
        $sourceCode = $deal['SOURCE_ID'] ?? '';
        $sourceName = $sources[$sourceCode] ?? '—';
        ?>
        <tr>
            <td>
                <a target="_blank"
                   href="https://crmasgroup.ge/crm/deal/details/<?= $deal['ID']; ?>/">
                    <?= $deal['ID']; ?>
                </a>
            </td>
            <td>
                <!--<?= htmlspecialchars($productId); ?>-->
                <a target="_blank"
                   href="https://crmasgroup.ge/crm/catalog/14/product/<?= $productId; ?>/">
                    <?= $productId; ?>
                </a>
            </td>
            <td>
                <?= htmlspecialchars($floor); ?>
            </td>
            <td>
                <?= htmlspecialchars($number); ?>
            </td>
            <td>
                <?= htmlspecialchars($sourceName); ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>


<script>
    $(document).ready(function () {

        // tab switch
        $('.tab-btn').on('click', function () {
            let tab = $(this).data('tab');

            $('.tab-btn').removeClass('active');
            $(this).addClass('active');

            $('.tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');
        });

        // deal click
        $('.deal-link').on('click', function (e) {
            e.preventDefault();

            let dealId = $(this).data('deal-id');

            // switch to second tab
            $('.tab-btn[data-tab="details"]').click();

            // placeholder content
            $('#deal-placeholder').html(
                'Deal ID: <b>' + dealId + '</b><br>' +
                '<a target="_blank" href="https://crmasgroup.ge/crm/deal/details/' + dealId + '/">Go to deal</a>'
            );
        });

    });
</script>

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



