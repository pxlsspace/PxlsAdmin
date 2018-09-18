<?php

namespace pxls;

class LogParser {

    public function __construct() {

    }

    public function parse($logline) {
        $regex = []; $action = null;
        $regex['selfshadow'] = '/self-shadowban via (.+)/i';
        $regex['selfban'] = '/self-ban via script/i';
        $regex['permaban'] = '/(permaban) (\S*)/i';
        $regex['shadowban'] = '/(shadowban) (\S*)/i';
        $regex['unban'] = '/(unban) (\S*)/i';
        $regex['ban'] = '/(ban) (\S*)/i';
        $regex['setrole'] = '/Set (\S*)\'s role to (\S*)/i';
        $regex['unclaim'] = '/(unclaimed report) (\S*)/i';
        $regex['claim'] = '/(claimed report) (\S*)/i';
        $regex['resolve'] = '/(resolved report) (\S*)/i';
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
                case 'selfban':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => '', 'extra' => ''];
                    break;
                case 'selfshadow':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => '', 'extra' => $matches[1][0]];
                    break;
                case 'permaban':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[2][0], 'extra' => ''];
                    break;
                case 'shadowban':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[2][0], 'extra' => ''];
                    break;
                case 'unban':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[2][0], 'extra' => ''];
                    break;
                case 'ban':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[2][0], 'extra' => ''];
                    break;
                case 'setrole':
                    return ['scope' => 'modaction', 'action' => $action, 'target' => $matches[1][0], 'extra' => $matches[2][0]];
                    break;
                case 'claim':
                    return ['scope' => 'report', 'action' => $action, 'target' => $matches[2][0], 'extra' => ''];
                    break;
                case 'unclaim':
                    return ['scope' => 'report', 'action' => $action, 'target' => $matches[2][0], 'extra' => ''];
                    break;
                case 'resolve':
                    return ['scope' => 'report', 'action' => $action, 'target' => $matches[2][0], 'extra' => ''];
                    break;
                case 'publicapi':
                    return ['scope' => 'api', 'action' => $action, 'target' => $matches[2][0], 'extra' => ''];
                    break;
                case 'ratelimit':
                    return ['scope' => 'api', 'action' => $action, 'target' => $matches[2][0], 'extra' => ''];
                    break;
            }
        }
        return false;
    }

    public function humanLogMessage($messageArray,$user_name) {
        $m = $messageArray; $messageTpl = [];
        // Scope: ModAction
        $messageTpl["modaction"]["selfshadow"]  = '<a href="/userinfo/%user_name%" target="_blank">%user_name%</a> was shadowbanned automatically. (%extra%)';
        $messageTpl["modaction"]["selfban"]     = '<a href="/userinfo/%user_name%" target="_blank">%user_name%</a> was banned automatically. (Scripting)';
        $messageTpl["modaction"]["permaban"]    = '<a href="/userinfo/%target%" target="_blank">%target%</a> was banned permanently.';
        $messageTpl["modaction"]["shadowban"]   = '<a href="/userinfo/%target%" target="_blank">%target%</a> was shadowbanned.';
        $messageTpl["modaction"]["ban"]         = '<a href="/userinfo/%target%" target="_blank">%target%</a> was time-banned.';
        $messageTpl["modaction"]["unban"]       = '<a href="/userinfo/%target%" target="_blank">%target%</a> was unbanned.';
        $messageTpl["modaction"]["setrole"]     = '<a href="/userinfo/%target%" target="_blank">%target%</a> was pro/demoted to %extra%.';
        // Scope: Report
        $messageTpl["report"]["claim"]       = 'Report <a href="#" data-toggle="modal" data-reportid="%target%" data-target="#report_info">ID %target%</a> has been claimed';
        $messageTpl["report"]["unclaim"]     = 'Report <a href="#" data-toggle="modal" data-reportid="%target%" data-target="#report_info">ID %target%</a> has been unclaimed';
        $messageTpl["report"]["resolve"]     = 'Report <a href="#" data-toggle="modal" data-reportid="%target%" data-target="#report_info">ID %target%</a> has been resolved';
        // Scope: API
        $messageTpl["api"]["publicapi"]     = '[ACCESS] <a target="_ipinfo" href="http://netip.de/search?query=%target%">%target%</a> accessed the public api (<a target="_ipinfo" href="https://apps.db.ripe.net/search/query.html?searchtext=%target%">RIPE</a>)';
        $messageTpl["api"]["ratelimit"]     = '[RATELIMIT] <a target="_ipinfo" href="http://netip.de/search?query=%target%">%target%</a> exceeded 15 requests per 15 minutes.  (<a target="_ipinfo" href="https://apps.db.ripe.net/search/query.html?searchtext=%target%">RIPE</a>)';

        $messageTpl["unknown"]              = '[UNKNOWN] %target% %user_name% %extra%';

        $messageData = [
            "%target%" => $m["target"],
            "%user_name%" => $user_name,
            "%extra%" => $m["extra"],
        ];

        $tplArray = (isset($messageTpl[$m['scope']][$m['action']]) && !empty($messageTpl[$m['scope']][$m['action']]))?$messageTpl[$m['scope']][$m['action']]:$messageTpl["unknown"];

        return strtr($tplArray,$messageData);
    }
}
