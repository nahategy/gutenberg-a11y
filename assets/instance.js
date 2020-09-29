jQuery(document).ready(function ($) {
    console.log("Loaded");
    console.log(gutenberA11yConfig);
    setTimeout(function (){
        console.log($("[contenteditable='true']"))
    },500)
});
