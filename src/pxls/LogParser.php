<?php

namespace pxls;

// TODO(netux): replace /userinfo/{username} with /userinfo/id/{id} when possible once pxlsspace/PxlsAdmin#17 is merged
class LogParser {

    public function __construct() {

    }

    public function parse($logline) {
        $regex = []; $action = null;
        $regex['alert'] = '/Sent a server-wide broadcast with the content: (\S*)/i';
        $regex['alertuser'] = '/Sent an alert to (\S*) \(UID: (\d*)\) with the content: (\S*)/i';
        $regex['selfshadow'] = '/self-shadowban via (.+)/i';
        $regex['selfban'] = '/self-ban via script/i';
        $regex['permaban'] = '/^(permaban) (\S*)/i';
        $regex['shadowban'] = '/^(shadowban) (\S*)/i';
        $regex['unban'] = '/^(unban) (\S*)/i';
        $regex['ban'] = '/^(ban) (\S*)/i';
        $regex['chatpermaban'] = '/\(chatban\) PERMA: {Target: (\S*)} {Initiator: (\S*)} {Length: (\S*)} {Purge: (true|false)} {PurgeAmount: (\d+)} {Reason: (.+)}/i';
        $regex['chatunban'] = '/\(chatban\) UNBAN: {Target: (\S*)} {Initiator: (\S*)} {Length: (\S*)} {Purge: (true|false)} {PurgeAmount: (\d+)} {Reason: (.+)}/i';
        $regex['chatban'] = '/\(chatban\) TEMP: {Target: (\S*)} {Initiator: (\S*)} {Length: (\S*)} {Purge: (true|false)} {PurgeAmount: (\d+)} {Reason: (.+)}/i';
        $regex['chatpurge'] = '/<(\S*), (\d*)> purged (\d*) messages from <(\S*), (\d*)>/i';
        $regex['chatdelete'] = '/<(\S*), (\d*)> purged message with id (\d*) from <(\S*), (\d*)>/i';
        $regex['setroles'] = '/Set (\S*)\'s roles? to (\S*)/i';
        $regex['addroles'] = '/Added roles "(.+)" to (\S*)/i';
        $regex['removeroles'] = '/Removed roles "(.+)" from (\S*)/i';
        $regex['removeallroles'] = '/Removed (\S*)\'s roles/i';
        $regex['flagrename'] = '/((?:un)?flagged) (\S*) \((\d+)\) for name change/i';
        $regex['rename'] = '/User (\S*) \((\d+)\) has just changed their name to (\S*)/i';
        $regex['forcedrename'] = '/Changed (\S*)\'s name to (\S*) \(uid: (\d*)\)/i';
        $regex['factionrestrict'] = '/Set (\S*)\'s faction_restricted state to (true|false)/i';
        $regex['canvasunclaim'] = '/(unclaimed report) (\S*)/i';
        $regex['canvasclaim'] = '/(claimed report) (\S*)/i';
        $regex['canvasresolve'] = '/(resolved report) (\S*)/i';
        $regex['chatunclaim'] = '/(unclaimed chat report) (\S*)/i';
        $regex['chatclaim'] = '/(claimed chat report) (\S*)/i';
        $regex['chatresolve'] = '/(resolved chat report) (\S*)/i';
        $regex['publicapi'] = '/(public api invoked by) (\S*)/i';
        $regex['ratelimit'] = '/(ratelimited) (\S*)/i';

        foreach($regex as $key=>$re) {
            if(preg_match($re, $logline,$matches,PREG_OFFSET_CAPTURE, 0)) {
                $action = $key;
                break;
            }
        }

        if(isset($matches)) {
            switch($action) {
                case 'alert':
                    return ['scope' => 'modaction', 'action' => $action, 'extra' => $matches[1][0]];
                    break;
                case 'alertuser':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[1][0], 'extra' => ['content' => $matches[3][0], 'uid' => $matches[2][0]]];
                    break;
                case 'selfban':
                    return ['scope' => 'modaction', 'action' => $action];
                    break;
                case 'selfshadow':
                    return ['scope' => 'modaction', 'action' => $action, 'extra' => $matches[1][0]];
                    break;
                case 'permaban':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[2][0]];
                    break;
                case 'shadowban':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[2][0]];
                    break;
                case 'unban':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[2][0]];
                    break;
                case 'ban':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[2][0]];
                    break;
                case 'chatpermaban':
                    $purgeCount = intval($matches[5][0]);
                    if ($purgeCount >= 2147483647) {
                        $purgeCount = "all";
                    }
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[1][0], 'extra' => $matches[4][0] === 'true' ? " and got $purgeCount messages purged" : ''];
                    break;
                case 'chatunban':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[1][0]];
                    break;
                case 'chatban':
                    $purgeCount = intval($matches[5][0]);
                    if ($purgeCount >= 2147483647) {
                        $purgeCount = "all";
                    }
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[1][0], 'extra' => $matches[4][0] === 'true' ? " and got $purgeCount messages purged" : ''];
                    break;
                case 'chatpurge':
                    $amount = intval($matches[3][0]);
                    if ($amount >= 2147483647) {
                        $amount = 'All';
                    }
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[4][0], 'extra' => ['amount' => $amount, 'uid' => $matches[5][0]]];
                    break;
                case 'chatdelete':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[4][0], 'extra' => ['cmid' => $matches[3][0], 'uid' => $matches[5][0]]];
                    break;
                case 'setroles':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[1][0], 'extra' => $matches[2][0]];
                    break;
                case 'addroles':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[2][0], 'extra' => $matches[1][0]];
                    break;
                case 'removeroles':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[2][0], 'extra' => $matches[1][0]];
                    break;
                case 'removeallroles':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[1][0]];
                    break;
                case 'flagrename':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[2][0], 'extra' => ['action' => strtolower($matches[1][0]), 'uid' => $matches[3][0]]];
                    break;
                case 'rename':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[3][0], 'extra' => ['old_username' => $matches[1][0], 'uid' => $matches[2][0]]];
                    break;
                case 'forcedrename':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[2][0], 'extra' => ['old_username' => $matches[1][0], 'uid' => $matches[3][0]]];
                    break;
                case 'factionrestrict':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[1][0], 'extra' => $matches[2][0] == 'true' ? 'restricted' : 'unrestricted'];
                    break;
                case 'canvasclaim':
                case 'chatclaim':
                    return ['scope' => 'report', 'action' => $action, 'target' => $matches[2][0]];
                    break;
                case 'canvasunclaim':
                case 'chatunclaim':
                    return ['scope' => 'report', 'action' => $action, 'target' => $matches[2][0]];
                    break;
                case 'canvasresolve':
                case 'chatresolve':
                    return ['scope' => 'report', 'action' => $action, 'target' => $matches[2][0]];
                    break;
                case 'publicapi':
                    return ['scope' => 'api', 'action' => $action, 'target' => $matches[2][0]];
                    break;
                case 'ratelimit':
                    return ['scope' => 'api', 'action' => $action, 'target' => $matches[2][0]];
                    break;
            }
        }
        return false;
    }

    public function humanLogMessage($messageArray,$user_name,$raw_message) {
        $m = $messageArray; $messageTpl = [];
        // Scope: ModAction
        $messageTpl["modaction"]["alert"]           = 'Sent an alert: %extra%';
        $messageTpl["modaction"]["alertuser"]       = 'Sent an alert to <a href="/userinfo/%target%" target="_blank">%target%</a> (UID %extra.uid%): %extra.content%';
        $messageTpl["modaction"]["selfshadow"]      = '<a href="/userinfo/%user_name%" target="_blank">%user_name%</a> was shadowbanned automatically. (%extra%)';
        $messageTpl["modaction"]["selfban"]         = '<a href="/userinfo/%user_name%" target="_blank">%user_name%</a> was banned automatically. (Scripting)';
        $messageTpl["modaction"]["permaban"]        = '<a href="/userinfo/%target%" target="_blank">%target%</a> was canvas banned permanently.';
        $messageTpl["modaction"]["shadowban"]       = '<a href="/userinfo/%target%" target="_blank">%target%</a> was shadowbanned.';
        $messageTpl["modaction"]["ban"]             = '<a href="/userinfo/%target%" target="_blank">%target%</a> was canvas time-banned.';
        $messageTpl["modaction"]["unban"]           = '<a href="/userinfo/%target%" target="_blank">%target%</a> was canvas unbanned.';
        $messageTpl["modaction"]["chatpermaban"]    = '<a href="/userinfo/%target%" target="_blank">%target%</a> was chat banned permanently%extra%.';
        $messageTpl["modaction"]["chatban"]         = '<a href="/userinfo/%target%" target="_blank">%target%</a> was chat time-banned%extra%.';
        $messageTpl["modaction"]["chatunban"]       = '<a href="/userinfo/%target%" target="_blank">%target%</a> was chat unbanned.';
        $messageTpl["modaction"]["chatpurge"]       = '%extra.amount% messages from <a href="/userinfo/%target%" target="_blank">%target%</a> (UID %extra.uid%) were purged from chat.';
        $messageTpl["modaction"]["chatdelete"]      = 'Message <a href="/chatContext?cmid=%extra.cmid%" target="_blank">ID %extra.cmid%</a> from <a href="/userinfo/%target%" target="_blank">%target%</a> (UID %extra.uid%) was deleted from chat.';
        $messageTpl["modaction"]["setroles"]        = '<a href="/userinfo/%target%" target="_blank">%target%</a>\'s role(s) were set to %extra%.';
        $messageTpl["modaction"]["addroles"]        = '<a href="/userinfo/%target%" target="_blank">%target%</a> was given the role(s) %extra%.';
        $messageTpl["modaction"]["removeroles"]     = '<a href="/userinfo/%target%" target="_blank">%target%</a> was revoked of the role(s) %extra%.';
        $messageTpl["modaction"]["removeallroles"]  = '<a href="/userinfo/%target%" target="_blank">%target%</a> had all of their roles removed.';
        $messageTpl["modaction"]["flagrename"]      = '<a href="/userinfo/%target%" target="_blank">%target%</a> (UID %extra.uid%) was %extra.action% for rename.';
        $messageTpl["modaction"]["rename"]          = '<a href="/userinfo/%target%" target="_blank">%target%</a> (UID %extra.uid%) changed their name from %extra.old_username% to %target%.';
        $messageTpl["modaction"]["forcedrename"]    = '<a href="/userinfo/%target%" target="_blank">%target%</a> (UID %extra.uid%)\'s name was forcefully changed from %extra.old_username% to %target%.';
        $messageTpl["modaction"]["factionrestrict"] = '<a href="/userinfo/%target%" target="_blank">%target%</a> was faction %extra%.';
        // Scope: Report
        $messageTpl["report"]["canvasclaim"]   = 'Canvas Report <a href="#" data-toggle="modal" data-reportid="%target%" data-target="#report_info">ID %target%</a> has been claimed';
        $messageTpl["report"]["canvasunclaim"] = 'Canvas Report <a href="#" data-toggle="modal" data-reportid="%target%" data-target="#report_info">ID %target%</a> has been unclaimed';
        $messageTpl["report"]["canvasresolve"] = 'Canvas Report <a href="#" data-toggle="modal" data-reportid="%target%" data-target="#report_info">ID %target%</a> has been resolved';
        $messageTpl["report"]["chatclaim"]     = 'Chat Report <a href="#" data-toggle="modal" data-reportid="%target%" data-target="#chat_report_modal">ID %target%</a> has been claimed';
        $messageTpl["report"]["chatunclaim"]   = 'Chat Report <a href="#" data-toggle="modal" data-reportid="%target%" data-target="#chat_report_modal">ID %target%</a> has been unclaimed';
        $messageTpl["report"]["chatresolve"]   = 'Chat Report <a href="#" data-toggle="modal" data-reportid="%target%" data-target="#chat_report_modal">ID %target%</a> has been resolved';
        // Scope: API
        $messageTpl["api"]["publicapi"]     = '[ACCESS] <a target="_ipinfo" href="http://netip.de/search?query=%target%">%target%</a> accessed the public api (<a target="_ipinfo" href="https://apps.db.ripe.net/search/query.html?searchtext=%target%">RIPE</a>)';
        $messageTpl["api"]["ratelimit"]     = '[RATELIMIT] <a target="_ipinfo" href="http://netip.de/search?query=%target%">%target%</a> exceeded 15 requests per 15 minutes.  (<a target="_ipinfo" href="https://apps.db.ripe.net/search/query.html?searchtext=%target%">RIPE</a>)';

        $messageTpl["unknown"]              = '[UNKNOWN] %raw%';

        $messageData = [
            "%target%" => $m["target"],
            "%user_name%" => $user_name,
            "%extra%" => $m["extra"],
            "%raw%" => $raw_message
        ];
        if (is_array($m["extra"])) {
            foreach ($m["extra"] as $key => $value) {
                $messageData["%extra.$key%"] = $value;
            }
        }

        $tplArray = (isset($messageTpl[$m['scope']][$m['action']]) && !empty($messageTpl[$m['scope']][$m['action']]))?$messageTpl[$m['scope']][$m['action']]:$messageTpl["unknown"];

        return strtr($tplArray,$messageData);
    }
}
