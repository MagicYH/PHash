<?php

/**
 * 计算Image hash
 */
class ImageHash {
    private static $dctTable;
    private static $coefficient;
    private static $coefficientHalf;

    public static function init() {
        $N = 8;
        for ($k=0; $k < $N; $k++) { 
            for ($n=0; $n < $N; $n++) { 
                self::$dctTable[$k][$n] = cos($k * pi() * ($n + 0.5) / ( $N ));
            }
        }
        self::$coefficient = sqrt( 2 / $N );
        self::$coefficientHalf = sqrt( 1 / $N );
    }

    public static function getHashByUrl($url, $type = 'phash') {
        $content = file_get_contents($url);
        return self::getHash($content, $type);
    }

    public static function getHashByFile($fileName, $type = 'phash') {
        $content = file_get_contents($fileName);
        return self::getHash($content, $type);
    }

	public static function getHash($imgContent, $type = 'phash') {
        $img = new \Imagick();
        $img->readImageBlob($imgContent);
        switch ($type) {
            case 'phash':
                $hash = self::_getPhash($img);
                break;
            
            case 'dhash':
                $hash = self::_getDhash($img);
                break;
            
            default:
                $hash = self::_getPhash($img);
                break;
        }
        return $hash;
    }

    /**
     * 将hash字符串转换成数字
     */
    public static function conver2Int($hash) {
        $data = 0;
        for ($i=0; $i < strlen($hash); $i++) { 
            $data = ($data << 1) + $hash[$i];
        }
        return $data;
    }

    /**
     * 计算两个hash之间的汉明距离
     */
    public static function getDistance($hash1, $hash2) {
        $dist = 0;
        for($i=0; $i < strlen($hash1); $i++) {
            if($hash1[$i] != $hash2[$i]){
                $dist++;
            }
        }
        return $dist;
    }

    /**
     * 计算数值型韩明距离
     */
    public static function getDistanceInt($hash1, $hash2) {
        $dist = 0;
        // $xor = $hash1 ^ $hash2;
        // while ($xor != 0) {
        //     $xor &= ($xor - 1);
        //     $dist++;
        //     echo $xor.PHP_EOL;
        // }

        $mask = 1;
        for($i=0; $i < 64; $i++) {
            if( ($hash1 & $mask) != ($hash2 & $mask)) {
                $dist++;
            }
            $mask = $mask << 1;
        }
        return $dist;
    }

    /**
     * 计算感知哈希（Perceptual hash algorithm）
     */
    private static function _getPhash(\Imagick $img) {
        $width = 8;
        $height = 8;
        $img->scaleImage($width, $height);  // 图像缩放
        $img->transformImageColorspace(\Imagick::COLORSPACE_GRAY);  // 调整成黑白图像
        // $img->equalizeimage();  // 直方图均衡化
        // $img->modulateImage(100, 0, 100);
        
        $product = $width * $height;
        $result = array();
        for ($x=0; $x < $width; $x++) { 
            for ($y=0; $y < $height; $y++) { 
                $result[$x][$y] = self::_getPixel($img, $x, $y);
            }
        }

        return self::_calcHash($result);
    }

    /**
     * 计算DCT哈希
     */
    private static function _getDhash(\Imagick $img) {
        $width = 8;
        $height = 8;
        $img->scaleImage($width, $height);  // 图像缩放
        $img->transformImageColorspace(\Imagick::COLORSPACE_GRAY);  // 调整成黑白图像
        // $img->modulateImage(100, 0, 100);
        $img->equalizeimage();  // 直方图均衡化

        $imgData = self::_getImgData($img);
        $result = self::_calcDCT2d($imgData, $width, $height);
        return self::_calcHash($result, true);
    }

    /**
     * 计算DCT2D
     */
    private static function _calcDCT2d($in, $width, $height) {
        $result = array();
        $rows = array();
        $row = array();

        for ($y = 0; $y < $height; $y++) {
            $rows[$y] = self::_calcDCT1d($in[$y]);
        }

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $col[$y] = $rows[$y][$x];
            }
            $result[$x] = self::_calcDCT1d($col);
        }

        return $result;
    }

    /**
     * 计算DCT1d
     */
    private static function _calcDCT1d($in) {
        $results = array();
        $N = count( $in );
        for ($k = 0; $k < $N; $k++) {
            $sum = 0;
            for ($n = 0; $n < $N; $n++) {
                $sum += $in[$n] * self::$dctTable[$k][$n];
            }

            if ($k > 0) {
                $sum *= self::$coefficient;
            } else {
                $sum *= self::$coefficientHalf;
            }
            $results[$k] = $sum;
        }
        return $results;
    }

    private static function _getAvg($in, $isDhash = false) {
        $width = count($in);
        $height = count($in[0]);
        $sum = self::_getSum($in, $isDhash);
        return floor($sum / $width / $height);
    }

    private static function _getSum($in, $isDhash = false) {
        $width = count($in);
        $height = count($in[0]);
        $sum = 0;

        if ($isDhash == true) {
            $in[0][0] = 0;
        }
        for ($row = 0; $row < $height; $row ++) {
            for ($col = 0; $col < $width; $col ++) {
                $sum += $in[$row][$col];
            }
        }
        return $sum;
    }

    private static function _calcHash($in, $isDhash = false) {
        $width = count($in);
        $height = count($in[0]);
        $avg = self::_getAvg($in, $isDhash);

        for ($x=0; $x < $width; $x++) { 
            for ($y=0; $y < $height; $y++) { 
                $hash[] = $in[$x][$y] >= $avg ? 1 : 0;
            }
        }
        return implode("", $hash);
    }

    private static function _getImgData(\Imagick $img) {
        $width = $img->getImageWidth();
        $height = $img->getImageHeight();
        $data = array();
        for ($y=0; $y < $height; $y++) { 
            for ($x=0; $x < $width; $x++) {
                $data[$y][$x] = self::_getPixel($img, $x, $y);
            }
        }
        return $data;
    }

    private static function _getPixel(\Imagick $image, $x, $y) {
        $pixel = $image->getImagePixelColor($x, $y);
        $color = $pixel->getColor();
        return $color['r'];
    }
}

ImageHash::init();