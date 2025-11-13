<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

// Check if CRM module is loaded
if (!CModule::IncludeModule('crm')) {
    die('CRM module not installed');
}

function getContactById($id) {
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $id), array("ID", "NAME", "LAST_NAME", "FULL_NAME", "UF_CRM_1761652010097" , "UF_CRM_1761651998145"));
    while($arContact = $res->Fetch()){
        $EMAIL=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'EMAIL', 'VALUE_TYPE' => 'WORK', "ELEMENT_ID" => $id))->Fetch();
        $PHONE=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'MOBILE|WORK|HOME', "ELEMENT_ID" => $id))->Fetch();
        $arContact["EMAIL"] = $EMAIL["VALUE"];
        $arContact["PHONE"] = $PHONE["VALUE"];
        return $arContact;
    }
}

function getDealInfoByIDToolbar($dealId)
{
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealId), array());
    if ($arDeal = $res->Fetch()) {
        return $arDeal;
    }
}
// Get Deal ID from request
$dealId = isset($_REQUEST['DEAL_ID']) ? intval($_REQUEST['DEAL_ID']) : 0;

$ResChange = isset($_REQUEST['ResChange']) ? intval($_REQUEST['ResChange']) : 0;


// $deal = CCrmDeal::GetByID($dealId);

$deal =getDealInfoByIDToolbar($dealId);

$contactId = intval($deal["CONTACT_ID"]);
$contactInfo= getContactById($contactId);

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>რეზერვაცია</title>
    <style>

        html, body {
            height: auto !important;
            min-height: auto !important;
            overflow: visible !important;
        }

        .reservation-form {
            padding: 20px;
            max-width: 500px;
            margin: 0 auto;
            height: auto !important;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group label.required::after {
            content: ' *';
            color: #ff0000;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0286ce;
            box-shadow: 0 0 0 3px rgba(2, 134, 206, 0.1);
        }
        
        select.form-control {
            height: 38px;
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #0286ce;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #026ba5;
        }
        
        .btn-secondary {
            background-color: #f5f5f5;
            color: #333;
        }
        
        .btn-secondary:hover {
            background-color: #e5e5e5;
        }
        
        .error {
            color: #ff0000;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        
        .form-control.error-input {
            border-color: #ff0000;
        }
        .gtranslate_wrapper{
            margin-left: 450px;
        }
    </style>
</head>
<body>
    <div class="reservation-form">
        <form id="reservationForm">
            <input type="hidden" id="dealId" value="<?php echo $dealId; ?>">
            
            <div class="form-group">
                <label for="reservationType" class="required">რეზერვაციის ტიპი</label>
                <select class="form-control" id="reservationType" name="reservationType" style="width: 40%;" onchange="javshnisTypeFunc()" required>
                    <option value="">აირჩიეთ...</option>
                    <option value="ufaso">უფასო 2 სამუშაო დღე</option>
                    <option value="uvado">არასტანდარტული ჯავშანი</option>
                </select>
                <div class="error" id="reservationType-error">გთხოვთ აირჩიოთ რეზერვაციის ტიპი</div>
            </div>

            <div id="vadaDiv" class="form-group" style="display: none;">
                <label for="vada" class="required">ვადა</label>
                <input type="date" class="form-control" id="vada" name="vada">
                <div class="error" id="vada-error">გთხოვთ შეიყვანოთ ვადა</div>
            </div>
            

            <div id="resChangeDiv" class="form-group" style="display: none;">
                <label for="resChange" class="required">ახალი რეზერვაციის თარიღი</label>
                <input type="date" class="form-control" id="resChange" name="resChange">
                <div class="error" id="resChange-error">გთხოვთ შეიყვანოთ რეზერვაციის თარიღი</div>
            </div>

            <div class="form-group">
                <label for="firstName" class="required">სახელი</label>
                <input type="text" class="form-control" id="firstName" name="firstName" required>
                <div class="error" id="firstName-error">გთხოვთ შეიყვანოთ სახელი</div>
            </div>
            
            <div class="form-group">
                <label for="lastName" class="required">გვარი</label>
                <input type="text" class="form-control" id="lastName" name="lastName" required>
                <div class="error" id="lastName-error">გთხოვთ შეიყვანოთ გვარი</div>
            </div>
            
            <div class="form-group">
                <label for="phone">ტელეფონის ნომერი</label>
                <input type="tel" class="form-control" id="phone" name="phone" placeholder="+995 5XX XXX XXX">
            </div>
            
            <div class="form-group">
                <label for="personalId">პირადი ნომერი</label>
                <input type="text" class="form-control" id="personalId" name="personalId" maxlength="11">
            </div>
            
            <div class="form-group">
                <label for="passportId">პასპორტის ნომერი</label>
                <input type="text" class="form-control" id="passportId" name="passportId">
            </div>
            
            <div class="form-group">
                <label for="comment">კომენტარი</label>
                <textarea class="form-control" id="comment" name="comment" rows="3"></textarea>
            </div>
            
            <div class="button-group">
                <button type="button" class="btn btn-secondary" onclick="closePopup()">გაუქმება</button>
                <button type="submit" class="btn btn-primary">გაგზავნა</button>
            </div>
        </form>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <script>
    	dealId = <? echo json_encode($dealId); ?>;
        contactInfo = <? echo json_encode($contactInfo); ?>;
        deal = <? echo json_encode($deal); ?>;
    	ResChange = <? echo json_encode($ResChange); ?>;

        console.log("ResChange");
        console.log(ResChange);


        // if(ResChange==1){
        //     document.getElementById("resChangeDiv").style.display="";
        //     document.getElementById("resChange").setAttribute("required", "required");
        //     document.getElementById("reservationType").value="ufaso";
        //     document.getElementById("reservationType").style.pointerEvents="none";
        // }else{
        //     document.getElementById("resChangeDiv").style.display="none"
        // }

        if(ResChange==1){
            document.getElementById("resChangeDiv").style.display="";
            document.getElementById("resChange").setAttribute("required", "required");
                if(deal["UF_CRM_1762331240"]=="71"){
                    typeValue="ufaso";
                }else if(deal["UF_CRM_1762331240"]=="72"){
                    typeValue="uvado";
                }else{
                    typeValue=""; 
                }

            document.getElementById("reservationType").value=typeValue;
            // document.getElementById("resChange").value=deal["UF_CRM_1762330753"];
            // document.getElementById("reservationType").style.pointerEvents="none";
        }else{
            document.getElementById("resChangeDiv").style.display="none"
        }


        function javshnisTypeFunc(){

            if(ResChange!=1){
                if(document.getElementById("reservationType").value=="uvado" || document.getElementById("reservationType").value=="fasiani"){
                    document.getElementById("vadaDiv").style.display="";
                }else{
                    document.getElementById("vadaDiv").style.display="none";
                }

            }

        }
        
        function fillContactInfo(id, value){
            if(document.getElementById(id)){
                document.getElementById(id).value=contactInfo[value];
            }
        }

        fillContactInfo("firstName", "NAME");       
        fillContactInfo("lastName", "LAST_NAME"); 
        fillContactInfo("phone", "PHONE"); 
        fillContactInfo("personalId", "UF_CRM_1761651998145");
        fillContactInfo("passportId", "UF_CRM_1761652010097");


        function closePopup() {
            if (window.BX && BX.SidePanel) {
                BX.SidePanel.Instance.close();
            } else {
                window.close();
            }
        }


        $(document).ready(function () {
            // Input validation helper
            function showError(inputId, message) {
                $("#" + inputId).addClass("error-input");
                $("#" + inputId + "-error").text(message).show();
            }

            function clearError(inputId) {
                $("#" + inputId).removeClass("error-input");
                $("#" + inputId + "-error").hide();
            }

            function validateForm() {
                let isValid = true;
                let requiredFields = []; // აქ უნდა გამოცხადდეს გარეთ

                if (ResChange == 1) {
                    requiredFields = [
                        "reservationType",
                        "resChange",
                        "firstName",
                        "lastName"
                    ];
                } else {
                    if (
                        document.getElementById("reservationType").value == "uvado"
                    ) {
                        requiredFields = [
                            "reservationType",
                            "vada",
                            "firstName",
                            "lastName"
                        ];
                    } else {
                        requiredFields = [
                            "reservationType",
                            "firstName",
                            "lastName"
                        ];
                    }
                }

                requiredFields.forEach(function (field) {
                    let value = $("#" + field).val().trim();
                    if (!value) {
                        showError(field, "გთხოვთ შეავსოთ ეს ველი");
                        isValid = false;
                    } else {
                        clearError(field);
                    }
                });

                return isValid;
            }


            // Submit form
            $("#reservationForm").on("submit", function (e) {
                e.preventDefault();

                if (!validateForm()) return;

                let formData = {
                    dealId: $("#dealId").val(),
                    reservationType: $("#reservationType").val(),
                    firstName: $("#firstName").val(),
                    lastName: $("#lastName").val(),
                    phone: $("#phone").val(),
                    personalId: $("#personalId").val(),
                    passportId: $("#passportId").val(),
                    comment: $("#comment").val(),
                    vada: $("#vada").val(),
                    ResChange: ResChange,              
                    ResChangeDate: $("#resChange").val() || ""
                };

                console.log("formData")
                console.log(formData)

                $.ajax({
                    url: "/rest/popupsservices/reservation.php",
                    type: "POST",
                    data: formData,
                    dataType: "json",
                    beforeSend: function () {
                        $(".btn-primary").text("მუშავდება...").prop("disabled", true);
                    },
                    success: function (response) {
                        if (response.status === "success") {
                            alert("რეზერვაციის მონაცემები გაგზავნილია!");
                            setTimeout(() => {
                                closePopup();
                                window.top.location.reload();
                            }, 500);

                        } else {
                            alert("შეტყობინება: " + response.message);
                        }
                    },
                    error: function () {
                        alert("დაფიქსირდა შეცდომა! სცადეთ თავიდან.");
                    },
                    complete: function () {
                        $(".btn-primary").text("გაგზავნა").prop("disabled", false);
                    }
                });
            });
        });


        setTimeout(() => {
            // დავამატოთ GTranslate-ის პარამეტრები
            const settingsScript = document.createElement('script');
            settingsScript.textContent = `
                window.gtranslateSettings = {
                    "default_language": "ka",
                    "languages": ["ka", "en", "ru"],
                    "wrapper_selector": ".gtranslate_wrapper",
                    "flag_size": 24
                };
            `;
            document.body.appendChild(settingsScript);

            // დავამატოთ თვითონ თარგმანის სკრიპტი
            const gtranslateScript = document.createElement('script');
            gtranslateScript.src = "https://cdn.gtranslate.net/widgets/latest/flags.js";
            gtranslateScript.defer = true;
            document.body.appendChild(gtranslateScript);

            // ვიპოვოთ რეზერვაციის ფორმის ელემენტი
            const reservationForm = document.querySelector('.reservation-form');
            if (reservationForm) {
                // შევქმნათ თარგმანის HTML
                const translateHtml = document.createElement('div');
                translateHtml.className = 'gtranslate_wrapper';

                // ჩავსვათ რეზერვაციის ფორმის ზემოთ
                reservationForm.parentNode.insertBefore(translateHtml, reservationForm);
            }
        }, 3000);

    </script>