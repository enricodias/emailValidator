<?php

namespace enricodias;

class emailValidator {

    public static function validate($email) {
        
        if (!Respect\Validation\Validator::email()->validate($email)) return false;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.validator.pizza/email/'.$email);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        
        if (empty($response->status)) return true;
        
        if ($response->status == 400 || !$response->mx || $response->disposable) return false;

        return true;

    }

}

?>