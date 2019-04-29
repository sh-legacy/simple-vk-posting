<?php

namespace Services;


class ListProvider
{
    public static function getListTexts()
    {
        $lists = [];
        $namespaces = array_filter(array_diff(scandir('lists'), ['..', '.']), function ($item) { return is_dir('lists/' . $item); });

        foreach ($namespaces as $namespace) {
            $listFileNames = array_filter(array_diff(scandir('lists/' . $namespace), ['..', '.']), function ($item) use ($namespace) { return is_file("lists/$namespace/" . $item) && (substr($item, '-4') == '.txt'); });
            foreach ($listFileNames as $listFileName) {
                $lists[$namespace][substr($listFileName, 0, -4)] = trim(file_get_contents("lists/$namespace/$listFileName"));
            }
        }

        return $lists;
    }
}