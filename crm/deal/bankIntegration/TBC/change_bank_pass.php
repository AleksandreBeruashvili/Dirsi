<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getCIBlockElementsByFilter($arFilter,$arSelect=Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*"),$arSort=array("ID"=>"DESC"))
{
    $arElements = array();
    $res = CIBlockElement::GetList($arSort, $arFilter, false, Array("nPageSize" => 99999), $arSelect);
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

function updateCIBlockElement($blockId, $arForAdd, $arProps = array()) {
    $el = new CIBlockElement;
    $arForAdd["PROPERTY_VALUES"] = $arProps;
    if ($PRODUCT_ID = $el->Update($blockId, $arForAdd)) return $PRODUCT_ID;
    else return 'Error: ' . $el->LAST_ERROR;
}

if(!empty($_POST)){
    $url = 'http://tfs.fmgsoft.ge:7780/API/FMGSoft/TBC_ChangePassword';

    $old_pass=$_POST["old"];
    $newpass=$_POST["pwd"];
    $company=$_POST["COMPANY"];
    $token=$_POST["token"];

    $list=getCIBlockElementsByFilter(array("IBLOCK_ID"=>34,"PROPERTY_PROJECT"=>$company));

    $data = [
        "User"=> $list[0]["LOGIN"],
        "OldPass"=> $old_pass,
        "NewPass"=> $newpass,
        "OTP"=> $token,
        "KeyCode"=> $list[0]["KEY_KODE"]
    ];

    $postData = http_build_query($data);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $postData
        ]
    ]);

    $response = file_get_contents($url, false, $context);
    $response = json_decode($response);

    printArr($response);

    $list[0]["PASS"]=$newpass;

    $arForAdd = array(
        'IBLOCK_ID' => 34,
        'NAME' => $list[0]["NAME"],
        'ACTIVE' => 'Y',
    );

    $res = updateCIBlockElement($list[0]["ID"], $arForAdd, $list[0]);

}


?>
<html>
<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>

        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .container{
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }

        form {
            width: 50%;
        }

        #header_text{
            width: 100%;
            font-size: 20px;
            /*padding: 15px;*/
            display: flex;
            justify-content: center;
            align-items: center;
        }
    </style>
</head>
<body>
<div id="header_text"><b>TBC Password Change</b></div>
<div class="container">
    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" >
        <div class="mb-3 mt-3">
            <label for="COMPANY" class="form-label">Company:</label>
            <select required ID="COMPANY" name="COMPANY" class="form-select">
                <option value="ParkBoulevard">Park_Boulevard</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="pwd" class="form-label">Old Password:</label>
            <input required type="text" class="form-control" id="pwd" placeholder="Enter password" name="old">
        </div>
        <div class="mb-3">
            <label for="pwd" class="form-label">New Password:</label>
            <input required type="text" class="form-control" id="pwd" placeholder="Enter password" name="pwd">
        </div>
        <div class="mb-3">
            <label for="token" class="form-label">Digipass:</label>
            <input required type="text" class="form-control" id="token" placeholder="Digipass" name="token">
        </div>
        <button type="submit" class="btn btn-dark mt-3">Change</button>
    </form>
</div>
</body>
</html>
