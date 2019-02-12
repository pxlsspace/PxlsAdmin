function jsLogger(action,target,reason=null) {
    var data = {
        'action': action,
        'target': target
    };
    if (reason) data.reason = reason;
    $.post("/api/log", data, function () {
        console.log("logged data: %o", data);
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