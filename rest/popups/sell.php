<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

if (!CModule::IncludeModule('crm')) {
    die('CRM module not installed');
}

function getDealInfoByIDToolbar($dealId) {
    $res = CCrmDeal::GetList(["ID" => "ASC"], ["ID" => $dealId], []);
    return $res->Fetch();
}

$dealId = isset($_REQUEST['DEAL_ID']) ? intval($_REQUEST['DEAL_ID']) : 0;
$deal = getDealInfoByIDToolbar($dealId);
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

        <div class="form-group">
            <label for="clientDesc" >კლიენტის დახასიათება</label>
            <textarea id="clientDesc" name="clientDesc" rows="3" ></textarea>
            <div class="error" id="clientDesc-error">გთხოვთ შეიყვანოთ აღწერა</div>
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

    function showError(id, msg) {
        $("#" + id).addClass("error-input");
        $("#" + id + "-error").text(msg).show();
    }

    function clearError(id) {
        $("#" + id).removeClass("error-input");
        $("#" + id + "-error").hide();
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
