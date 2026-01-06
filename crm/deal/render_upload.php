<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CModule::IncludeModule("iblock");

function printArr($arr)
{
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
}

function getciblockelementsbyfilter($arFilter = array(), $limit = 9999999)
{
    $arElements = array();
    $arSelect = array("ID", "NAME", "IBLOCK_ID", "DETAIL_PICTURE", "PREVIEW_PICTURE");
    $res = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter, false, array("nPageSize" => $limit), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFields = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = $arFields;
        foreach ($arProps as $key => $arProp) {
            $arPushs['PROPERTY_' . $key] = $arProp["VALUE"];
        }
        array_push($arElements, $arPushs);
    }
    return $arElements;
}

if (!empty($_POST)) {
    
    $fileType = $_POST["file_type"];
    $flatNumbers = $_POST["flat_numbers"];
    $ptoNumber = $_POST["pto_number"];
    $floor = $_POST["floor"];
    $project = $_POST["project"];
    $block = $_POST["block"];
    $flat_type = $_POST["flat_type"];


    $file = $_FILES["file"];


    // Split flat numbers by comma and trim whitespace
    $flatNumbersArray = array_map('trim', explode(',', $flatNumbers));

    // Prepare the base filter
    $baseFilter = array(
        "IBLOCK_ID" => 14,
        "IBLOCK_SECTION_ID" => $project,
        "PROPERTY_60" => $flat_type,
        "PROPERTY_73" => $block,
        "PROPERTY_214" => $ptoNumber,
        "PROPERTY_61" => $floor

    );

    $successCount = 0;
    $errors = array();

    // Process the uploaded file once
    $filePath = $_SERVER["DOCUMENT_ROOT"] . "/upload/" . time() . "_" . $file["name"];

    if (move_uploaded_file($file["tmp_name"], $filePath)) {
        // Process each flat number
        foreach ($flatNumbersArray as $flatNumber) {
            // Add flat number to filter
            $filter = $baseFilter;
            $filter["PROPERTY_65"] = $flatNumber;

            // Get elements using your function
            $elements = getciblockelementsbyfilter($filter);

            if (!empty($elements)) {
                // Create a new file array for each element
                $fileArray = CFile::MakeFileArray($filePath);
                $fileArray['del'] = 'Y'; // This allows overwriting existing files
                $fileArray['old_file'] = ''; // Clear old file reference

                if ($fileArray && !isset($fileArray["error"])) {
                    // Determine field based on file type
                    $fieldCode = ($fileType == "3d_render") ? "DETAIL_PICTURE" : "PREVIEW_PICTURE";

                    // Update each found element
                    foreach ($elements as $element) {
                        $el = new CIBlockElement;

                        $updateFields = array(
                            $fieldCode => $fileArray
                        );

                        $updateResult = $el->Update($element["ID"], $updateFields);

                        if ($updateResult) {
                            $successCount++;
                        } else {
                            $errors[] = "Failed to update element ID: " . $element["ID"] . " - " . $el->LAST_ERROR;
                        }
                    }
                } else {
                    $errors[] = "File processing error for flat $flatNumber: " . print_r($fileArray["error"], true);
                }
            } else {
                $errors[] = "No elements found for flat number: " . $flatNumber;
            }
        }

        // Clean up the file after processing all flats
        @unlink($filePath);

    } else {
        $errors[] = "Failed to move uploaded file to " . $filePath;
    }

    // Output results
    if ($successCount > 0) {
        echo "<div class='alert alert-success'>Files uploaded successfully to $successCount items!</div>";
    }
    if (!empty($errors)) {
        echo "<div class='alert alert-danger'><strong>Errors:</strong><br>" . implode("<br>", $errors) . "</div>";
    }
}
?>

<!-- HTML form with PTO field -->

<html>

<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h1 class="mb-4">File Upload</h1>

        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="file_type" class="form-label">File Type</label>
                <select name="file_type" id="file_type" class="form-select" required>
                    <option value="">Select file type</option>
                    <option value="3d_render">3D Render</option>
                    <option value="floor_plan">Floor Planning</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="project" class="form-label">Project</label>
                <select name="project" id="project" class="form-select" required>
                    <option value="">Select Project</option>
                    <option value="33">Park Boulevard</option>
                  
                </select>
            </div>

            <div class="mb-3">
                <label for="block" class="form-label">Block</label>
                <select name="block" id="block" class="form-select" required>
                    <option value="">Select block</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="flat_numbers" class="form-label">Flat Numbers (comma separated)</label>
                <input type="text" class="form-control" id="flat_numbers" name="flat_numbers" required>
                <div class="form-text">Enter multiple flat numbers separated by commas (e.g., 101, 102, 103)</div>
            </div>

            <div class="mb-3">
                <label for="pto_number" class="form-label">PTO</label>
                <input type="text" class="form-control" id="pto_number" name="pto_number" required>
                <div class="form-text">Enter a single PTO number</div>
            </div>

            <div class="mb-3">
                <label for="floor" class="form-label">Floor</label>
                <input type="text" class="form-control" id="floor" name="floor" required>
                <div class="form-text">Enter the floor number (e.g., 1, 2, 3)</div>
            </div>

            <div class="mb-3">
                <label for="file" class="form-label">File</label>
                <input type="file" class="form-control" id="file" name="file" required>
            </div>

            <button type="submit" class="btn btn-primary">Upload</button>
        </form>
    </div>
</body>

</html>