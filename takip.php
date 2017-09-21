<?php

/*
 * @Author:Emre Can ÖZKÖK
 * @Data:15.07.2017
 * @Mail:emrecanozkok@gmail.com
 */

/*Debug Aktif */

error_reporting(E_ERROR);
ini_set('display_errors', 1);

/*Html parse etmek için kütüphanemizi çağırıyoruz*/

use Sunra\PhpSimple\HtmlDomParser;

class takip {

    public $takipno;
    public $kayitvarPtt = false;

    function __construct() {
        
    }

    public function pttTakip($trackingNumber) {
        
        //bu takip numarası için oluşturulan cookie log unu sil
        unlink('cookies/cookie_' . $trackingNumber . '.log');
        //Captcha resmini getir
        $writeCookie = $this->getPttCapthca('http://gonderitakip.ptt.gov.tr/', array('tn' => $trackingNumber));
        //Az Bekle Güzel kardeşim
        sleep(5);
        //Yukarda sayfaya istek attık ama ptt seri istek atınca engelliyor biraz bekledik simdi resmi kendi linkinden çekecegiz
        $capimage = $this->getPttCapthca('http://gonderitakip.ptt.gov.tr/CaptchaSecurityImages.php?width=300&height=100&characters=5', array('tn' => $trackingNumber));
        //resim geldimi noldu bakalım
        if ($capimage == '') {
            return array(
                'success' => false,
                'message' => 'Pttye Ulaşılamıyor.'
            );
        }
        //bu string değeri resime dönüştür
        $imgim = imagecreatefromstring($capimage);
        //Resmi Çevir
        $rotated = imagerotate($imgim, -9, 0);
        //Pixel işlemleri
        //Yükseklik genişlik al 
        $width = imagesx($rotated);
        $height = imagesy($rotated);
        //yatayda bütün pixelleri dön
        for ($x = 0; $x < $width; $x++) {
            //dikeydeki pixelleri dön
            for ($y = 0; $y < $height; $y++) {
                //geçerli pixelin rengini al
                $color = imagecolorat($rotated, $x, $y);
                //Renk öğelerine göre ayırdık
                $color_tran = imagecolorsforindex($rotated, $color);
                /*
                 * Alttakileri tek tek yazmayayım kalabalık etmesin
                 * ptt captcha resminde arkada mavilik alan var o alanı yok etmek için
                 * aşağıdaki kontrolü yazdım, anlaşılacağı üzere renk tonlarını kontrol ederek
                 * manipule ediyoruz.
                 */
                if ($color_tran['blue'] > 50 && $color_tran['red'] < 200 && $color_tran['green'] < 200) {
                    $rgb = imageColorAllocate($rotated, 0, 0, 0);
                    imageSetPixel($rotated, $x, $y, $rgb);
                }
                if ($color_tran['blue'] < 50 && $color_tran['red'] < 50 && $color_tran['green'] < 50) {
                    $rgb = imageColorAllocate($rotated, 0, 0, 0);

                    imageSetPixel($rotated, $x, $y, $rgb);
                }
                if ($color_tran['red'] > 170 && $color_tran['green'] > 170) {
                    $rgb = imageColorAllocate($rotated, 255, 255, 255);
                    imageSetPixel($rotated, $x, $y, $rgb);
                }
            }
        }
        //resme biraz yumuşatma ekliyoruz
        imagefilter($rotated, IMG_FILTER_SMOOTH, 7);

        ob_start();
        //Resim objesine çevir
        imagejpeg($rotated);
        //Çıkış Bufferını ayarla
        $outputBuffer = ob_get_clean();
        //Çıktıyı base64çevir
        $base64 = base64_encode($outputBuffer);
        //işlenen resmi göster
        //echo '<img src="data:image/png;base64, ' . $base64 . '" alt="İşlenen resim" />';
        //resmi kaydet
        file_put_contents('tmpcaptcha/' . $trackingNumber . '_cap.jpg', base64_decode($base64));

        //Captcha codunu okuyoruz
        $captcha = $this->readCaptcha($trackingNumber);
        
        //ptt sayfasına atacağımız form post parametrelerini ayarlıyoruz
        $params = array(
            'tn' => $trackingNumber,
            'barkod' => $trackingNumber,
            'security_code' => $captcha,
            'Submit' => 'Sorgula'
        );
        
        //Burda post işlemimizi yaptık
        $sonuc = $this->getPttCapthca('http://gonderitakip.ptt.gov.tr', $params, 'POST');
        //Pttden dönen cevabı aldık.
        $htmlParsed = HtmlDomParser::str_get_html($sonuc);
        
        //Hata stringi varmı yokmu diye kontrol ediyoruz varsa zaten hata vardır
        $konum = strpos($htmlParsed, 'Barkod Numarasinda hata var');

        if ($konum != false) {

            return array(
                'success' => false,
                'message' => 'Yanlış Takip No.'
            );
        }


        try {
            //Herhangi bir sıkıntı yoksa işleme geçiyoruz burda html parse ediyoruz
            if ($htmlParsed->find('table', 5)) {
                if (is_numeric($trackingNumber)) {
                    $thirdTable = $htmlParsed->getElementsByTagName('table', 5);
                } else {
                    $thirdTable = $htmlParsed->getElementsByTagName('table', 6);
                }


                //echo 'thirdTAble' . $thirdTable->outertext;



                $rows = $thirdTable->find('tr');
                $sonc = array();
                foreach ($rows as $key => $value) {

                    if ($value->attr['class']) {
                        $this->kayitvarPtt = true;
                        
                        $yer = preg_replace('/[ ]{2,}|[\t]/', ' ', trim($value->children(5)->plaintext));
                        $yer = preg_replace('/[ ]{2,}|[\t]/', ' ', $yer);
                        if ($yer == '-') {

                            $yer = $value->children(7)->plaintext;
                        }
                        $sonc['opts'][] = array(
                            'tarih' => $value->children(1)->plaintext . ' ' . $value->children(2)->plaintext,
                            'islem' => $value->children(4)->plaintext,
                            'yer' => implode(' ', array_unique(explode(' ', $yer)))
                        );
                    }
                }
                if ($this->kayitvarPtt == false) {
                    return array(
                        'success' => false,
                        'message' => 'Kayıt var ancak hiçbir bilgi girilmemiş.'
                    );
                } else {
                    $sonc['success'] = true;
                    $sonc['message'] = 'Kayit Getirildi';
                    return $sonc;
                }
            } else {
                return array(
                    'success' => false,
                    'message' => 'Ptt Tarafında Sorun Oluştu.Tekrar Deneyiniz.'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Ptt Tarafında Sorun Oluştu.'
            );
        }
    }
    /*
     * Burdan sonra diğerleri başlyor onlarda sorun olmadığı için yorum atmıyorum
     * Direk html get çekiyoruz ve html parse ediyoruz.
     */
    public function mngTakip($trackcode) {
        $rawjson = file_get_contents('http://mobil.mngkargo.com.tr/HttpServisleri/?token=8BC372DD7CABC1465C264DF8A24B23649ED1B9EE21FB8380E77ACE92FB33C8BD&format=json&service=KT&vno=' . $trackcode . '&intmusno=');
        $return = array();
        if ($this->isJson($rawjson)) {
            $converted = json_decode($rawjson, true);
            $converted = $converted[0];

            if ($converted['Teslim_Tarihi'] != '-') {
                $sonc['opts'][] = array(
                    'tarih' => $converted['Teslim_Tarihi'] . ' ' . $converted['Teslim_Saati'],
                    'islem' => 'Teslim Edildi.-' . $converted['Teslim_Alan'],
                    'yer' => $converted['Teslim_Birimi_Adres']
                );
            } else {
                $date = strtotime($converted['Cikis_Tarihi']);
                $sonc['opts'][] = array(
                    'tarih' => date('d.m.Y', $date) . ' ' . $converted['Harelet_Saati'],
                    'islem' => $converted['Hareket_Aciklama'],
                    'yer' => '-'
                );
            }
        } else {
            return array(
                'success' => false,
                'message' => 'Mng Tarafında Sorun Oluştu.Tekrar Deneyiniz.'
            );
        }

        $sonc['success'] = true;
        $sonc['message'] = 'Kayit Getirildi';
        return $sonc;
    }

    public function yurticiTakip($trackingCode) {
        $rawhtml = file_get_contents('http://selfservis.yurticikargo.com/reports/SSWDocumentDetail.aspx?DocId=' . $trackingCode);
        $return = array();
        $htmlParsed = HtmlDomParser::str_get_html($rawhtml);

        $table = $htmlParsed->find('table.tableForm', 2);
        $gonderidurumu = $table->find('tr', 0)->find('td', 1)->plaintext;

        if ($gonderidurumu == '') {
            return array(
                'success' => false,
                'message' => 'Gönderi durumu boş.'
            );
        } else {
            $sonc['success'] = true;
            $sonc['message'] = 'Kayit Getirildi';
            $sonc['opts'][] = array(
                'tarih' => $table->find('tr', 2)->find('td', 1)->plaintext,
                'islem' => $table->find('tr', 2)->find('td', 0)->plaintext,
                'yer' => $table->find('tr', 3)->find('td', 1)->plaintext
            );
        }
        return $sonc;
    }

    public function suratTakip($trackingCode) {
        $rawhtml = file_get_contents('http://www.suratkargo.com.tr/?p=kargom_nerede_post&TakipNo=' . $trackingCode);

        $htmlParsed = HtmlDomParser::str_get_html($rawhtml);


        if (strlen($htmlParsed->plaintext) > 154) {


            $items = $htmlParsed->find('table.results_grid_table', 0)->children(0);
            $currTr = $items->find('tr', 1);
            //var_dump($currTr->find('td', 0)->outertext());


            $sonc['opts'][] = array(
                'tarih' => $currTr->find('td', 3)->plaintext,
                'islem' => $currTr->find('td', 7)->plaintext,
                'yer' => trim($currTr->find('td', 8)->plaintext)
            );
            $sonc['success'] = true;
            $sonc['message'] = 'Kayit Getirildi';
        } else {
            $sonc['success'] = false;
            $sonc['message'] = 'Böyle Bir Kayıt Bulunamadı.';
        }
        return $sonc;
    }

    public function readCaptcha($trackingcode) {
        return (new TesseractOCR('tmpcaptcha/' . $trackingcode . '_cap.jpg'))
                        ->whitelist(range('a', 'z'), range(0, 9))->psm(8)
                        ->lang('eng')
                        ->run();
    }

    public function getPttCapthca($url, $params = '', $type = 'GET') {
        $cookieTtk = 'cookies/cookie_' . $params['tn'] . '.log';
        unset($params['tn']);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieTtk);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.2) Gecko/20070219 Firefox/2.0.0.2');
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieTtk);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POST, $type == 'POST' ? 1 : 0);
        if (count($params) > 0) {
            //echo 'paramvar';
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        $output = curl_exec($ch);
        $headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);


        return $output;
        curl_close($ch);
    }

    function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

}
