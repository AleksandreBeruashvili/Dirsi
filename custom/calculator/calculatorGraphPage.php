<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/element.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/functions.php");


function getProdNGraphPage($dealID) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), array("ID","UF_CRM_1693386021079","UF_CRM_1693385948133","CONTACT_ID","CONTACT_FULL_NAME","UF_CRM_1693385964548","UF_CRM_1693385992603","UF_CRM_1709803989","UF_CRM_1709803989"));
    $arrRes = array();
    if($arDeal = $res->Fetch()){
        $arrRes ["prodN"]   = "<b>".$arDeal["UF_CRM_1693385992603"] ."</b> N: " . $arDeal["UF_CRM_1693385964548"];
        $arrRes ["project"] = $arDeal["UF_CRM_1693385948133"];
        $arrRes ["kvm"] = $arDeal["UF_CRM_1693386021079"];
        $arrRes ["floor"]   = $arDeal["UF_CRM_1709803989"];
        $arrRes ["CONTACT_FULL_NAME"]   = $arDeal["CONTACT_FULL_NAME"];
        $arrRes ["CONTACT_ID"]   = $arDeal["CONTACT_ID"];
        $arrRes ["floor"]   = $arDeal["UF_CRM_1709803989"];

        return $arrRes;
    }else{
        return " ";
    }
}

function getProductInformation($prodId) {                                        //
    $arElements = array();                                                                        //
    $arSelect = Array("ID","IBLOCK_ID","NAME","DATE_ACTIVE_FROM","DATE_CREATE","PROPERTY_*");     //     სტანდარტული
    $res = CIBlockElement::GetList(Array(), array("ID"=>$prodId), false, Array("nPageSize"=>999), $arSelect); //       ფუნქცია
    if($ob = $res->GetNextElement()) {                                                         //         არ
        $arFilds = $ob->GetFields();                                                              //       იცვლება
        $arProps = $ob->GetProperties();                                                          //
        $arPushs = array();                                                                       //
        foreach($arFilds as $key => $arFild) $arPushs[$key] = $arFild;                            //
        foreach($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];                   //
        array_push($arElements, $arPushs);                                                        //
    }                                                                                             //
    return $arElements;                                                                           //
}


if ($_GET["docID"] && is_numeric($_GET["docID"])) {
    $element = getElementByID($_GET["docID"]);
    if (count($element)) {
        $json = str_replace("&quot;", "\"", $element["JSON"]);
        $json = json_decode($json, true);
        $dealID = $json["dealId"];
        if($dealID) {
            $arrRes = getProdNGraphPage($dealID);
            if(is_array($arrRes)) {
                $prodInfo = getProductInformation($json["PROD_ID"]);
                $json["prodN"] = $element["number"];
                $json["CONTACT_ID"] = $arrRes["CONTACT_ID"];
                $json["CONTACT_FULL_NAME"] = $arrRes["CONTACT_FULL_NAME"];
                $json["kvm"] = $prodInfo[0]["TOTAL_AREA"];
                $json["oldPriceM2"] = $element["startKVMPriceUSD"];
                $json["newPriceM2"] = $element["kvmPriceUSD"];
                $json["project"] = $element["project"];
                $json["floor"] = $element["FLOOR"];
                $json["floorNumber"] = "<b>" . $prodInfo[0]["PRODUCT_TYPE"] . "№ </b>" . $prodInfo[0]["Number"];
            }else{
                echo "<a style='color: red;font-size: 16px'>დილი ვერ მოიძებნა!!!</a>";
            }
        }else{

        }
        // printArr($prodInfo);
    } else {
        echo "<a style='color: red;font-size: 16px'>გრაფიკი ვერ მოიძებნა!!!</a>";
        exit();
    }
}
else{
    echo "<a style='color: red;font-size: 16px'>page not found!!!</a>";
    exit();
}



?>
<style>

    #contact{
        margin-top:10px;
    }

    .tabler {
        width: 81%;
        margin-top: 10px;
        padding: 10px;
        border-collapse: collapse;
        color: #333;
        font-family: 'Noto Sans Georgian', sans-serif;
        font-size: 9px;
    }

    .tabler tr {
        text-align: center !important
    }

    .tabler tbody tr:hover {
        background-color: #e2e1e1;
    }

    .tabler th {
        background-color: #abd4f3ff;
        height: 30px;
        font-size: 15px;
        color: black;
        
    }

    .tabler td {
        height: 25px;
        font-size: 15px;
    }
</style>

<div>
    <div id="deal"></div>
    <div id="contact"></div>
    <p id="project"></p>
    <!-- <p id="chabarebatype"></p> -->
    <p id="floor"></p>
    <p id="number"></p>
    <p id="oldPrice"></p>
    <p id="oldPriceM2"></p>
    <p id="discount"></p>
    <p id="discountPercentage"></p>
    <p id="kvmPrice"></p>
    <p id="price"></p>
    <p id="newPriceM2"></p>
    <p id="prodN"></p>
    <p id="uploadedFile"></p>
    <p id="comment" style="max-width: 90%"></p>
</div>
<div  class="blocks">
    <table id="graphData" class="tabler"></table>
</div>




<script>

    let json = <? echo json_encode($json); ?>;
    fillGraph(json);


    function fillGraph(data){
        let deal = data["dealId"];
        let contactID = data["CONTACT_ID"];
        let CONTACT_FULL_NAME = data["CONTACT_FULL_NAME"];
        let project = data["project"];
        let floor = data["floor"];
        let price = data["PRICE"];
        let kvm = data["kvm"];
        let newPriceM2 = (data["PRICE"]/kvm).toFixed(2);
        let oldPrice = data["oldPrice"];
        let oldPriceM2 = (data["oldPrice"]/kvm).toFixed(2);
        let comment = data["commentInput"];
        let prodN = data["floorNumber"];
        // let chabarebatype = data["chabarebatype"];
        // let uploadedImage = "/rest/local/api/calculator/" + data["image"];
        let discount = (oldPrice - price).toFixed(2);
        let discountPercentage = (discount * 100 / oldPrice).toFixed(2);
        let kvmPrice = data["kvmPrice"];
        document.getElementById("deal").innerHTML = "<b>მოლაპარაკება: </b><a href = '/crm/deal/details/"+ deal +"/'>ხელშეკრულება # "+ deal + "</a>";
        document.getElementById("contact").innerHTML = "<b>კლიენტი: </b><a href = '/crm/contact/details/"+ contactID +"/'>"+ CONTACT_FULL_NAME + "</a>";
        document.getElementById("project").innerHTML = "<b>პროექტი: </b>" + project;
        // document.getElementById("chabarebatype").innerHTML = "<b>ჩაბარების ტიპი: </b>" + chabarebatype;
        document.getElementById("floor").innerHTML = "<b>სართული:</b> " + floor;
        document.getElementById("number").innerHTML = prodN;
        document.getElementById("oldPrice").innerHTML = "<b>საწყისი ფასი:</b> " + numberFormat(oldPrice);
        document.getElementById("oldPriceM2").innerHTML = "<b>საწყისი ფასი m<sup>2</sup>:</b> " + numberFormat(oldPriceM2);
        document.getElementById("discount").innerHTML = "<b>ფასდაკლების თანხა:</b> " + numberFormat(discount);
        document.getElementById("discountPercentage").innerHTML = "<b>ფასდაკლების %:</b> " + discountPercentage;
        document.getElementById("price").innerHTML = "<b>ახალი ფასი:</b> " + numberFormat(parseFloat(price));
        document.getElementById("newPriceM2").innerHTML = "<b>ახალი ფასი m<sup>2</sup>:</b> " + numberFormat(parseFloat(newPriceM2));
        // document.getElementById("uploadedFile").innerHTML = `<a onclick="downloadFile('${uploadedImage}')" style="cursor: pointer">ატვირთული ფაილი</a>`;
        if (comment) document.getElementById("comment").innerHTML = "<b>კომენტარი:</b> " + comment;

        // document.getElementById("kvmPrice").innerText = "ფასი m²: " + kvmPrice;

        let table = `
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>გადახდის თარიღი</th>
                                <th>თანხა</th>
                                <th  style='display:none'>დარჩენილი ძირი</th>
                            </tr>
                        </thead>
                        <tbody>
                    `;

        for(let i=0;i<data["data"].length;i++){

            // console.log(data["data"]);
            // price = (parseFloat(price) - parseFloat(data["data"][i]["amount"])).toFixed(2);


            let leftToPay = data["data"][i]["leftToPay"];
            leftToPay = (leftToPay == -0.00) ? "0.00" : leftToPay;

            table += `
                            <tr>
                                <td>${data["data"][i]["payment"]}</td>
                                <td>${data["data"][i]["date"]}</td>
                                <td>${data["data"][i]["amount"]}</td>    
                                <td  style='display:none'>${leftToPay}</td>
                            </tr>
                    `;
        }
        document.getElementById("graphData").innerHTML = table;
    }

    function numberFormat(num) {
        if (num) {
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
        }
        else return "";
    }

    function downloadFile(filePath) {
        // Create an anchor element
        var link = document.createElement('a');
        link.href = filePath;

        // Set the download attribute to force download
        link.download = filePath.split('/').pop();

        // Append the anchor element to the document body
        document.body.appendChild(link);

        // Trigger a click event on the anchor element to start downloading
        link.click();

        // Remove the anchor element from the document body
        document.body.removeChild(link);
    }



</script>



