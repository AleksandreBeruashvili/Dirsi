<?require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog.php");?>
<?php


$url = $_SERVER['REQUEST_URI'];
$url = explode('/', trim($url, '/'));

global $USER;

$userID = 0; // default if not logged in

if ($USER->IsAuthorized()) {
    $userID = (int)$USER->GetID(); // make sure it's integer
}

?>

<script>

    const userID = <?php echo $userID; ?>;
	url = <? echo json_encode($url); ?>;

    if(userID !== 1){
        // ვერ შეცვალონ ეტაპი გარე ხედვით
            console.log(url)
            if (url[0] == "crm" && url[1] == "deal"){ 
                console.log("kanban")
                setInterval(() => {
                    // ლისტ ხედვა
                    stageCvlileba=document.querySelectorAll('.crm-list-stage-bar-table');
                    if(stageCvlileba){        
                        stageCvlileba.forEach(element => {
                            element.style.pointerEvents = 'none';
                        });        
                    }
                    // კენბან ხედვა
                    document.querySelectorAll('.main-kanban-item-wrapper').forEach(item => {
                        item.removeAttribute('draggable');
                        item.addEventListener('dragstart', event => {
                            event.preventDefault();
                            event.stopPropagation();
                        }, true);
                        item.addEventListener('mousedown', event => {
                            if (event.button === 0) {
                                event.stopPropagation();
                            }
                        }, true);

                        item.addEventListener('touchstart', event => {
                            event.stopPropagation();
                        }, true);
                    });
                        
                }, 500);
            }
        //
    }



    // setTimeout(() => {
    //     const settingsScript = document.createElement('script');
    //     settingsScript.textContent = `
    //         window.gtranslateSettings = {
    //             "default_language":"en","languages":["ka","en","ru"],"wrapper_selector":".gtranslate_wrapper","flag_size":24};
    //     `;
    //     document.body.appendChild(settingsScript);

    //     const gtranslateScript = document.createElement('script');
    //     gtranslateScript.src = "https://cdn.gtranslate.net/widgets/latest/flags.js";
    //     gtranslateScript.defer = true;
    //     document.body.appendChild(gtranslateScript);

    //     var logo = document.getElementsByClassName('menu-items-header');

    //     if(logo){
    //         var translatehtml = `
    //                     <div class="gtranslate_wrapper"></div>
    //                 `;
    //         if(logo[0]){
    //             logo[0].insertAdjacentHTML('afterbegin', translatehtml);
    //         }

    //     }
    // }, 3000);


setTimeout(() => {
    // CSS დამატება loader-ის დასამალად
    const style = document.createElement('style');
    style.textContent = `
        .gt_loader,
        .gt-loading,
        .gtranslate-loading,
        .skiptranslate .goog-te-spinner-pos,
        .goog-te-spinner-animation {
            display: none !important;
        }
    `;
    document.head.appendChild(style);

    const settingsScript = document.createElement('script');
    settingsScript.textContent = `
        window.gtranslateSettings = {
            "default_language":"en",
            "languages":["ka","en","ru"],
            "wrapper_selector":".gtranslate_wrapper",
            "flag_size":24
        };
    `;
    document.body.appendChild(settingsScript);

    const gtranslateScript = document.createElement('script');
    gtranslateScript.src = "https://cdn.gtranslate.net/widgets/latest/flags.js";
    gtranslateScript.defer = true;
    document.body.appendChild(gtranslateScript);

    var logo = document.getElementsByClassName('menu-items-header');

    if(logo && logo[0]){
        var translatehtml = `<div class="gtranslate_wrapper"></div>`;
        logo[0].insertAdjacentHTML('afterbegin', translatehtml);
    }
}, 3000);

</script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>