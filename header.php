<?require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog.php");?>
<?php


$url = $_SERVER['REQUEST_URI'];
$url = explode('/', trim($url, '/'));

?>

<script>
	url = <? echo json_encode($url); ?>;

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


</script>
