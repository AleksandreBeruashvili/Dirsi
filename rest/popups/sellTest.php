<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

if (!CModule::IncludeModule('crm')) {
    die('CRM module not installed');
}

function getDealInfoByIDToolbar($dealId) {
    $res = CCrmDeal::GetList(["ID" => "ASC"], ["ID" => $dealId], []);
    return $res->Fetch();
}

function getContactInfo($contactId) {
    $arContact = array();
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $contactId), array());
    if($arContact = $res->Fetch()){
        $PHONE=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'MOBILE|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $MAIL=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'EMAIL', 'VALUE_TYPE' => 'HOME|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $arContact["PHONE"] = $PHONE["VALUE"];
        $arContact["EMAIL"] = $MAIL["VALUE"];
        return $arContact;
    }
    return $arContact;
}

function getDealFieldsToolbar($fieldName)
{
    $option = array();
    $rsUField = CUserFieldEnum::GetList(array(), array("USER_FIELD_NAME" => $fieldName));
    while ($arUField = $rsUField->GetNext()) {
        $option[$arUField["ID"]] = $arUField["VALUE"];
    }

    return $option;
}

$citizenOf = getDealFieldsToolbar("UF_CRM_1770187155776");
$dealId = isset($_REQUEST['DEAL_ID']) ? intval($_REQUEST['DEAL_ID']) : 0;
$deal = getDealInfoByIDToolbar($dealId);
$contact = getContactInfo($deal["CONTACT_ID"]);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>გაყიდვა</title>
    <style>
        .sell-form {
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
            margin-top: 50px;
        }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; color: #333; }
        label.required::after { content: ' *'; color: red; }
        input, textarea {
            width: 100%; padding: 8px 10px; border: 1px solid #ddd;
            border-radius: 4px; font-size: 14px;
        }
        input:focus, textarea:focus { border-color: #0286ce; outline: none; }
        .button-group { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #0286ce; color: #fff; }
        .btn-primary:hover { background: #026ba5; }
        .btn-secondary { background: #f5f5f5; }
        .error { color: red; font-size: 13px; display: none; }
        .error-input { border-color: red; }


        .gtranslate_wrapper{
            margin-left: 450px;
        }

    </style>
</head>
<body>
<div class="sell-form">
    <form id="sellForm" enctype="multipart/form-data">
        <input type="hidden" id="dealId" name="dealId" value="<?= $dealId ?>">

        <div class="form-group">
            <label for="contractDate" class="required">ხელშეკრულების გაფორმების თარიღი</label>
            <input type="date" id="contractDate" name="contractDate" style="width: 30%;" required >
            <div class="error" id="contractDate-error">გთხოვთ მიუთითოთ თარიღი</div>
        </div>

        <!-- style="width: 35%;"  -->
        <div id="SellFlatDiv" class="bizproc-modern-type-control-container documentsSell" style="margin: 20px 0 17px 0;">
            <span style="display: block; margin: 0 0 15px 0; font-size: 13px; color: #80868e;">ხელშეკრულება:</span>
            <input id="sellFlat" type="file" onchange="fileShetvirtva('sellFlat')"  />
            <input id="sellFlatText" type="hidden" />
        </div>

        <div id="SellAttachDiv" class="bizproc-modern-type-control-container documentsSell" style="margin: 20px 0 17px 0;">
            <span style="display: block; margin: 0 0 15px 0; font-size: 13px; color: #80868e;">დოკუმენტის ასლი:</span>
            <input id="sellAttach" type="file" onchange="fileShetvirtva('sellAttach')" />
            <input id="sellAttachText" type="hidden" />
        </div>

        <!-- საკონტაქტო ინფორმაცია -->
        <div class="form-group">
            <label for="phone">ტელეფონი</label>
            <input type="text" id="phone" name="phone">
        </div>

        <div class="form-group">
            <label for="email">მეილი</label>
            <input type="email" id="email" name="email">
        </div>

        <!-- ერთერთია მხოლოდ სავალდებულო -->
        <div class="form-group">
            <label for="personalId">პირადი ნომერი</label>
            <input type="text" id="personalId" name="personalId">
        </div>

        <div class="form-group">
            <label for="passportId">პასპორტის ნომერი</label>
            <input type="text" id="passportId" name="passportId">
        </div>


        <!-- ორივე სავალდებულოა -->
        <div class="form-group">
            <label for="legalAddress" class="required">იურიდიული მისამართი</label>
            <input type="text" id="legalAddress" name="legalAddress" required>
            <div class="error" id="legalAddress-error">გთხოვთ შეიყვანოთ იურიდიული მისამართი</div>
        </div>

        <div class="form-group">
            <label for="actualAddress" class="required">ფაქტიური მისამართი</label>
            <input type="text" id="actualAddress" name="actualAddress" required>
            <div class="error" id="actualAddress-error">გთხოვთ შეიყვანოთ ფაქტიური მისამართი</div>
        </div>

        <!-- მოქალაქეობა -->
        <div class="form-group">
            <label for="citizenshipType" class="required">მოქალაქეობა</label>
            <select class="form-control"
                    id="citizenshipType"
                    name="citizenshipType"
                    style="width: 40%; height: 30px; border-radius: 5px;"
                    onchange="toggleCitizenOf()"
                    required>
            <option value="">აირჩიეთ...</option>
                <option value="45">Resident</option>
                <option value="46">Non-resident</option>
            </select>
            <div class="error" id="citizenshipType-error">აირჩიეთ მოქალაქეობა</div>
        </div>

        <div class="form-group" id="citizenOfDiv" style="display:none;">
            <label for="citizenOf" class="required">Citizen of</label>
            <select id="citizenOf" name="citizenOf" style="width: 40%; height: 30px;">
                <option value=""></option>
            </select>
            <div class="error" id="citizenOf-error">გთხოვთ შეავსოთ ქვეყანა</div>
        </div>

        <!-- ნაციონალობა -->
        <div class="form-group">
            <label for="nationality" class="required">ნაციონალობა</label>
            <select class="form-control" id="nationality" name="nationality" style="width: 40%; height: 30px; border-radius: 5px;"  required>
                <option value="">აირჩიეთ...</option>
                <option value="156">Georgian</option>
                <option value="157">Russian</option>
            </select>
        </div>

        <div class="form-group">
            <label for="clientDesc" >კლიენტის დახასიათება</label>
            <textarea id="clientDesc" name="clientDesc" rows="3" ></textarea>
            <div class="error" id="clientDesc-error">გთხოვთ შეიყვანოთ აღწერა</div>
        </div>

        <div class="form-group">
            <label for="miznobrioba" class="required">შეძენის მიზნობრიობა</label>
            <select class="form-control" id="miznobrioba" name="miznobrioba" style="width: 40%; height: 30px; border-radius: 5px;"  required>
                <option value="">აირჩიეთ...</option>
                <option value="170">საცხოვრებელი</option>
                <option value="171">საინვესტიციო</option>
            </select>
            <div class="error" id="miznobrioba-error">გთხოვთ აირჩიოთ შეძენის მიზნობრიობა</div>
        </div>


        <div class="form-group">
            <label for="contactType" class="required">კონტრაქტის ტიპი</label>
            <select class="form-control" id="contactType" name="contactType" style="width: 40%; height: 30px; border-radius: 5px;"  required>
                <option value="">აირჩიეთ...</option>
                <option value="174">Standard</option>
                <option value="175">Non standard</option>
            </select>
            <div class="error" id="contactType-error">გთხოვთ აირჩიოთ კონტრაქტის ტიპი</div>
        </div>

        <div class="button-group">
            <button type="button" class="btn btn-secondary" onclick="closePopup()">გაუქმება</button>
            <button type="submit" class="btn btn-primary">გაგზავნა</button>
        </div>
    </form>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>

    // თარიღის კონვერტაცია DD/MM/YYYY -> YYYY-MM-DD ფორმატში
    var rawDate = <?php echo json_encode($deal["UF_CRM_1762416342444"], JSON_UNESCAPED_UNICODE); ?>;
    if (rawDate) {
        var parts = rawDate.split('/');
        if (parts.length === 3) {
            // DD/MM/YYYY -> YYYY-MM-DD
            document.getElementById('contractDate').value = parts[2] + '-' + parts[1] + '-' + parts[0];
        }
    }


    let deal=<?php echo json_encode($deal, JSON_UNESCAPED_UNICODE); ?>;
    let contact=<?php echo json_encode($contact, JSON_UNESCAPED_UNICODE); ?>;

    //console.log(contact)

    if (contact["PHONE"]){
        document.getElementById("phone").value=contact["PHONE"];
    } else {
        document.getElementById("phone").value="";
    }

    if (contact["EMAIL"]){
        document.getElementById("email").value=contact["EMAIL"];
    } else {
        document.getElementById("email").value="";
    }

    if (contact["UF_CRM_1761651998145"]){
        document.getElementById("personalId").value=contact["UF_CRM_1761651998145"];
    } else {
        document.getElementById("personalId").value="";
    }

    if (contact["UF_CRM_1761652010097"]){
        document.getElementById("passportId").value=contact["UF_CRM_1761652010097"];
    } else {
        document.getElementById("passportId").value="";
    }

    if (contact["UF_CRM_1761653738978"]){
        document.getElementById("legalAddress").value=contact["UF_CRM_1761653738978"];
    } else {
        document.getElementById("legalAddress").value="";
    }

    if (contact["UF_CRM_1761653727005"]){
        document.getElementById("actualAddress").value=contact["UF_CRM_1761653727005"];
    } else {
        document.getElementById("actualAddress").value="";
    }

    if (contact["UF_CRM_1770187155776"]) {
        $("#citizenshipType").val(contact["UF_CRM_1770187155776"]);
    }


    if(contact["UF_CRM_1769506891465"]=="156"){
        document.getElementById("nationality").value="156";
    }else if(contact["UF_CRM_1769506891465"]=="157"){
        document.getElementById("nationality").value="157";
    }
    else{
        document.getElementById("nationality").value="";
    }

    if(deal["UF_CRM_1770204855111"]=="174"){
        document.getElementById("contactType").value="174";
    }else if(deal["UF_CRM_1770204855111"]=="175"){
        document.getElementById("contactType").value="175";
    }
    else{
        document.getElementById("contactType").value="";
    }


    if(deal["UF_CRM_1770204779269"]=="170"){
        document.getElementById("miznobrioba").value="170";
    }else if(deal["UF_CRM_1770204779269"]=="171"){
        document.getElementById("miznobrioba").value="171";
    }
    else{
        document.getElementById("miznobrioba").value="";
    }


    function clearError(id) {
        $("#" + id).removeClass("error-input");
        $("#" + id + "-error").hide();
    }


    function toggleCitizenOf() {
        const citizenship = $("#citizenshipType").val();

        if (citizenship === "46") { // Non-resident
            $("#citizenOfDiv").show();
        } else {
            $("#citizenOfDiv").hide();
            $("#citizenOf").val("");
            clearError("citizenOf");
        }
    }



    if(contact["UF_CRM_1761651978222"]=="45"){
        document.getElementById("citizenshipType").value="45";
    }else if(contact["UF_CRM_1761651978222"]=="46"){
        document.getElementById("citizenshipType").value="46";
        //console.log(document.getElementById("citizenshipType").value)
    }
    else{
        document.getElementById("citizenshipType").value="";
    }

    let citizenOf = <?php echo json_encode($citizenOf); ?>;
    //console.log(citizenOf)

    function fillReservationDropdown(dropDownData, id, fieldID) {
        const select = document.getElementById(id);
        if (!select) return;

        let fieldValue = contact?.[fieldID] ?? "";

        let html = `<option value=""></option>`;

        Object.entries(dropDownData).forEach(([key, value]) => {
            html += `<option value="${key}">${value}</option>`;
        });

        select.innerHTML = html;

        if (fieldValue) {
            select.value = fieldValue;
        }
    }

    //let citizenOf = <?php //echo json_encode($citizenOf); ?>//;

    fillReservationDropdown(citizenOf, "citizenOf", "UF_CRM_1770187155776");


    function closePopup() {
        if (window.BX && BX.SidePanel) {
            BX.SidePanel.Instance.close();
        } else {
            window.close();
        }
    }


    function fileShetvirtva(fieldID) {
        let input = document.getElementById(fieldID);
        let fileIdInput = document.getElementById(`${fieldID}Text`);
        fileIdInput.value = "";

        if (input && input.files.length > 0) {
            let deal_id = <?php echo json_encode($dealId, JSON_UNESCAPED_UNICODE); ?>;
            let data = new FormData();
            data.append('file', input.files[0]);
            data.append('dealId', deal_id);

            fetch(`${location.origin}/rest/local/AXdocUploadFile.php`, {
                method: 'POST',
                body: data
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200 && data.uploaded) {
                        fileIdInput.value = data.uploaded; // ← ატვირთული ფაილის ID / path
                        console.log("File uploaded:", data.uploaded);
                    } else {
                        alert("ფაილის ატვირთვა ვერ მოხერხდა!");
                    }
                })
                .catch(err => {
                    console.error("Upload error:", err);
                });
        }
    }


    $(document).ready(function() {

        fillReservationDropdown(
            citizenOf,
            "citizenOf",
            "UF_CRM_1770187155776" // CONTACT UF
        );

        toggleCitizenOf(); // რომ Resident/Non-resident-ზეც სწორად გამოჩნდეს

        function showError(id, msg) {
            $("#" + id).addClass("error-input");
            $("#" + id + "-error").text(msg).show();
        }


        function validateForm() {
            let valid = true;
            ["contractDate", "sellFlat", "sellAttach"].forEach(id => {
                let el = $("#" + id)[0];
                if (!el) return; // თუ არ არსებობს, გამოტოვე

                let val = $("#" + id).val();
                if ((el.type === "file" && el.files.length === 0) || val.trim() === "") {
                    showError(id, "გთხოვთ შეავსოთ ველი");
                    valid = false;
                } else {
                    clearError(id);
                }
            });
            let personalId  = $("#personalId").val().trim();
            let passportId = $("#passportId").val().trim();


            // 🟢 პირადი ნომერი OR პასპორტი (ერთ-ერთი მაინც)
            if (!personalId && !passportId) {
                showError("personalId", "შეავსეთ პირადი ნომერი ან პასპორტი");
                showError("passportId", "შეავსეთ პირადი ნომერი ან პასპორტი");
                valid = false;
            } else {
                clearError("personalId");
                clearError("passportId");
            }

            // 🟢 Non-resident → citizenOf required
            if ($("#citizenshipType").val() === "46") {
                if (!$("#citizenOf").val()) {
                    showError("citizenOf", "გთხოვთ აირჩიოთ ქვეყანა");
                    valid = false;
                } else {
                    clearError("citizenOf");
                }
            }

            return valid;
        }

        $("#sellForm").on("submit", function(e) {
            e.preventDefault();
            if (!validateForm()) return;

            let sellFlatFileId = document.getElementById('sellFlatText').value;
            let sellAttachFileId = document.getElementById('sellAttachText').value;

            let formData = new FormData();
            formData.append("dealId", $("#dealId").val());
            formData.append("contractDate", $("#contractDate").val());
            formData.append("sellFlatFile", sellFlatFileId);
            formData.append("sellAttachFile", sellAttachFileId);
            formData.append("clientDesc", $("#clientDesc").val());

            formData.append("phone", $("#phone").val())
            formData.append("email", $("#email").val())
            formData.append("personalId", $("#personalId").val())
            formData.append("passportId", $("#passportId").val())
            formData.append("legalAddress", $("#legalAddress").val())
            formData.append("actualAddress", $("#actualAddress").val())
            formData.append("citizenshipType", $("#citizenshipType").val())
            formData.append("citizenOf", $("#citizenOf").val())
            formData.append("nationality", $("#nationality").val())

            formData.append("miznobrioba", $("#miznobrioba").val());
            formData.append("contactType", $("#contactType").val());

            $.ajax({
                url: "/rest/popupsservices/sell.php",
                type: "POST",
                data: formData,
                dataType: "json",
                contentType: false,
                processData: false,
                success: function (response) {
                    console.log(response);
                    if (response.status === "success") {
                        alert("მოთხოვნა წარმატებით გაიგზავნა");
                        setTimeout(() => {
                            closePopup();
                            window.top.location.reload();
                        }, 500);
                    } else {
                        alert("შეცდომა: " + response.message);
                    }
                },
                error: function (xhr, status, error) {
                    alert("Server error: " + error);
                }
            });






        });
    });


    if (!window.gtranslateInitialized) {
        window.gtranslateInitialized = true;
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
            const reservationForm = document.querySelector('.sell-form');
            if (reservationForm) {
                // შევქმნათ თარგმანის HTML
                const translateHtml = document.createElement('div');
                translateHtml.className = 'gtranslate_wrapper';

                // ჩავსვათ რეზერვაციის ფორმის ზემოთ
                reservationForm.parentNode.insertBefore(translateHtml, reservationForm);
            }
        }, 3000);
    }

</script>
</body>
</html>
