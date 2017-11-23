### PHash

Perceptual hash implementation for PHP

### Support
- Average hash
- DCT hash

### Need
- Imagick

### Example usage
``` php
$hashString1 = ImageHash::getHashByFile("test1.png", 'dhash');
$hash1 = ImageHash::conver2Int($hashString1);

$hashString2 = ImageHash::getHashByFile("test2.png", 'dhash');
$hash2 = ImageHash::conver2Int($hashString2);

$dis = ImageHash::getDistanceInt($hash1, $hash2);
echo "$dis\n";

$dis = ImageHash::getDistance($hashString1, $hashString2);
echo "$dis\n";

echo "$hashString1, $hashString2\n";
```