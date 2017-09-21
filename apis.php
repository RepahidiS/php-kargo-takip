<?php

/*
 * @Author:Emre Can ÖZKÖK
 * @Data:15.07.2017
 * @Mail:emrecanozkok@gmail.com
 */

/*Türkçe Karakter Fix */
header('Content-Type: text/html; charset=utf-8');
/*Kütüphaneleri Yükle*/
require 'vendor/autoload.php';

/*Hataları aç*/
error_reporting(E_ERROR );
ini_set('display_errors', 1);

/*Import Class*/
require 'takip.php';
/*Define Class*/
$tkp = new takip();

/*
 * Commentli Satırlar Çalıştırıldıktan sonra commentlenmiştir
 * Ptt de ülke içi,ülke dışı olmak üzere 2 ihtimal var
 */

//echo json_encode($tkp->pttTakip('RF377514257CN'));
//echo json_encode($tkp->pttTakip('2605853197631'));
//echo json_encode($tkp->pttTakip('UR856206197YP'));


//echo json_encode($tkp->mngTakip('375531879149'));
//echo json_encode($tkp->mngTakip('OK411695'));

//echo json_encode($tkp->suratTakip('16619015009597'));


echo json_encode($tkp->yurticiTakip('104474117378'));



