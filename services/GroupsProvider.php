<?php


namespace Services;


use GuzzleHttp\Client;

class GroupsProvider
{
    private static $errorMessage;

    public static function getGroups($namespace)
    {
        $config = ConfigProvider::getConfig();

        if (!file_exists("groups/$namespace.txt")) {
            static::$errorMessage = "Группы для namespace '$namespace' не заданы!";
            return false;
        }

        $groupsText = file_get_contents("groups/$namespace.txt");
        preg_match_all('/(?<=vk\.com\/)[\w.]+|(?<=@)[\w.]+|\[.*?\|\K[\w.]+(?=\])/', $groupsText, $groupLinks);

        $client = new Client([
            'base_uri' => 'https://api.vk.com/method/',
        ]);
        $response = $client->request('POST', 'groups.getById', [
            'form_params' => [
                'access_token' => $config['vk_access_token'],
                'v' => '5.95',
                'group_ids' => implode(',', $groupLinks[0]),
            ],
        ]);
        $response = json_decode($response->getBody()->getContents(), true);
        if (isset($response['error'])) {
            static::$errorMessage = $response['error']['error_msg'];
            return false;
        }
        $groupIds = array_column($response['response'], 'id');
        return array_map(function ($item) { return "-$item"; }, $groupIds);
    }

    public static function getErrorMessage()
    {
        return static::$errorMessage;
    }
}