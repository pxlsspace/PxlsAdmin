<?php
/**
 * Created by PhpStorm.
 * User: maurice
 * Date: 10.05.17
 * Time: 11:09
 *
 * application/json {
 * 'content': message (req),
 * 'username': (overrides username if set),
 * 'avatar_url': (overrides avatar if set),
 * 'tts': [true|false],
 * 'file: contents,
 * 'embeds': array of embed obj
 * }
 *
 */

namespace pxls;


class DiscordHook
{
    protected $construct_url       = null;
    protected $url       = null;
    protected $agent     = 'pxlsAdmin-Discord-Webhook';
    protected $name      = null;
    protected $avatar    = null;
    protected $message   = null;

    public function __construct($url) {
        $this->construct_url = $url;
        $this->url = $url;
    }

    public function setUrl($url) {
        $this->url = $url;
    }

    public function setName($param) {
        $this->name = $param;
    }

    public function setAvatar($avatar) {
        $this->avatar = $avatar;
    }

    public function setMessage($message) {
        $this->message = $message;
    }

    public function execute() {
        if(is_null($this->message)) {
            return false;
        }

        $data = [];

        if(!is_null($this->avatar)) $data['avatar'] = $this->avatar;
        if(!is_null($this->name)) $data['username'] = $this->name;
        if(!is_null($this->message)) $data['content'] = $this->message;

        $dataJSON = json_encode($data);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $dataJSON);

        $output = curl_exec($curl);
        $output = json_decode($output,true);

        if(curl_getinfo($curl, CURLINFO_HTTP_CODE) != 204) throw new \Exception($output['message']);

        curl_close($curl);

        $this->url = $this->construct_url;

        return true;
    }

}