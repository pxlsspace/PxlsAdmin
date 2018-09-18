function jsLogger(action,target) {
    var data = {
        'action': action,
        'target': target
    };
    $.post("/api/log", data, function () {
        console.log("logged");
    }).fail(function () {
        console.log("Something went wrong!");
    });
}
$(function() {
    var koala = " #\##|   |\n" +
        "##\\ |   |\n" +
        "   \\|   \'---'/\n" +
        "    \   _'.'O'.'\n" +
        "     | :___   \\ \n" +
        "     |  _| :  |\n" +
        "     | :__,___/\n" +
        "     |   |\n";

    console.log("%c" + koala, "background:#808080;  -webkit-background-clip: text; color:transparent; font-size:16px;display: inline-block;");
});