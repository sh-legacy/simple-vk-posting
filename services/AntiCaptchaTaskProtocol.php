<?php


namespace Services;


interface AntiCaptchaTaskProtocol {

    public function getPostData();
    public function getTaskSolution();

}