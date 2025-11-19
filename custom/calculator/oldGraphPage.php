<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/element.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/functions.php");

if ($_GET["docID"] && is_numeric($_GET["docID"])) {
    $element = getElementByID($_GET["docID"]);
    if (count($element)) {
        $json = str_replace("&quot;", "\"", $element["JSON"]);
        $dealID = $element["DEAL_ID"];
        $json = json_decode($json, true);
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
    .tabler {
        width: 40%;
        min-width: 500px;
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
        background-color: #7fbc61;
        height: 30px;
        font-size: 15px;
        color: white;
    }

    .tabler td {
        height: 25px;
        font-size: 15px;
    }
</style>

<div>
    <div id="deal"></div>
    <p id="price"></p>
</div>
<div  class="blocks">
    <table id="graphData" class="tabler"></table>
</div>




<script>

    let json = <? echo json_encode($json); ?>;
    let dealID = <? echo json_encode($dealID); ?>;
    fillGraph(json,dealID);


    function fillGraph(data,dealID){
        document.getElementById("deal").innerHTML = "<a href = '/crm/deal/details/"+ dealID +"/'>ხელშეკრულება # "+ dealID + "</a>";


        let table = `
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>გადახდის თარიღი</th>
                                <th>თანხა</th>
                            </tr>
                        </thead>
                        <tbody>
                    `;
        let price = 0;
        for(let i=0;i<data.length;i++) {
            console.log(data[i]["amount"]);
            price += Number(data[i]["amount"]);
            table += `
                            <tr>
                                <td>${data[i]["PLAN_TYPE"]}</td>
                                <td>${data[i]["date"]}</td>
                                <td>${data[i]["amount"]}</td>
                            </tr>
                    `;
        }
        document.getElementById("graphData").innerHTML = table;
        document.getElementById("price").innerText = "ფასი: $" + price;
    }


</script>



